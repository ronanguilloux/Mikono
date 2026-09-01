# 10. Build the production image in CI and deploy by pulling it

Date: 2026-09-01

## Status

Accepted

## Context

[ADR 0003](0003-adopt-docker-frankenphp-symfony-sqlite-tailwind-for-volunteer-manager.md)
chose Docker + FrankenPHP and explicitly deferred deployment: "No
production hosting target has been chosen yet; deployment specifics are
explicitly out of scope for v0.1." v0.1 is now feature-complete, so that
deferral has to end.

A hosting review on 2026-08-31 found that the deferral had costs beyond
the missing runbook:

- **The production image could not be built at all.** The `Dockerfile`
  ran `asset-map:compile` but never `tailwind:build`, and the Tailwind
  bundle throws rather than degrading when the built CSS is missing
  (strict mode is on outside the `test` environment). Nobody had noticed,
  because the production target had never been built.
- The production *builder* stage inherited Chromium from
  `frankenphp_base` via the Panther recipe — several hundred megabytes
  downloaded on every production build for a binary the final image
  never ships.
- There is no CI at all. The only quality gate is `.githooks/pre-commit`,
  which runs locally and can be bypassed with `--no-verify`.

Those three facts share a cause: nothing outside a developer's laptop
ever exercised the production path. Whatever deployment shape gets chosen
has to make that impossible again.

The hosting constraints themselves are settled by earlier decisions and
recorded in [`docs/project/hosting-plan.md`](../project/hosting-plan.md):
SQLite means one machine and one container, so this is a single-server
deployment with no orchestration, and the interesting question is only
*where the image is built and how it reaches the server*. The provider
and region are a separate, still-open decision pending latency
measurements from Nairobi and Mombasa.

## Decision

**CI builds the production image, publishes it to GitHub Container
Registry, and the server deploys by pulling that image; the pair of
compose files is the deployment unit.**

Concretely:

- `.github/workflows/ci.yml` builds the `frankenphp_dev` target on every
  push and pull request and runs `composer quality` and `bin/phpunit`
  inside it — the same commands the pre-commit hook runs, now somewhere
  they cannot be skipped.
- `.github/workflows/build-image.yml` builds the `frankenphp_prod`
  target on `main` and pushes it to
  `ghcr.io/ronanguilloux/app-php-prod`, tagged with the commit SHA and
  `latest`. Because it runs on every merge, a production build that
  cannot complete is now a red pipeline rather than a discovery made
  during a deployment.
- The server holds a checkout of this repository **only for the compose
  files**, and runs
  `docker compose -f compose.yaml -f compose.prod.yaml pull && … up -d`.
  No Composer, no PHP, no Node, no build toolchain on the production
  host.
- Deployment configuration lives in a root-only `deploy.env` on the
  server, consumed with `--env-file`. `APP_SECRET` is passed at runtime
  and **never** as a build argument: the published image is public, and
  `composer dump-env prod` bakes build-time environment into the image.
- `IMAGES_PREFIX` — already present in
  [`compose.prod.yaml`](../../compose.prod.yaml) — doubles as the
  registry prefix, so the same file covers a local production build and a
  registry pull.

## Consequences

- **Positive:** the production image is built continuously, so it cannot
  silently rot again — the class of bug that motivated this ADR is now
  caught by the pipeline. Deployment becomes `pull` + `up`, which a
  1 GB VPS can do; building on the server would have required roughly
  four times the RAM. The production host never holds source, Composer
  credentials, or a build cache. Rolling back is re-pulling an earlier
  tag.
- **Negative / trade-offs:** deployment now depends on GitHub Actions and
  GHCR being reachable — an outage at either blocks deploys (though not
  the running app). The image is public, which is fine for this codebase
  (the repository is already public) but makes the "no secrets at build
  time" rule load-bearing rather than merely tidy. A schema migration is
  still not reverted by rolling the image back; that path is a restore
  from backup, documented in
  [`docs/project/deployment-plan.md`](../project/deployment-plan.md).
- **Reversibility:** cheap. `compose.prod.yaml` keeps its `build:` stanza,
  so building on the server remains a supported fallback — drop
  `IMAGES_PREFIX` and run `build` instead of `pull`. The workflows are
  two files.

## Alternatives considered

### 1. Build on the server (`git pull` + `docker compose build`)

**Rejected.** It is the obvious minimum-moving-parts option, and it was
tempting because it needs no registry and no CI. But it forces the VPS to
carry a build toolchain: roughly 4 GB RAM and 20 GB of disk against the
1 GB the app actually needs to *run*, which is a real recurring cost for a
small NGO's internal tool. It also puts source, Composer, and a build
cache on the internet-facing production machine, and — decisively — it
leaves the production build unexercised between deployments, which is
precisely how the `tailwind:build` failure survived unnoticed. A deploy
would still be the first time anyone found out.

### 2. Deploy with a git push and rebuild in place, or a PaaS

**Rejected.** Push-to-deploy platforms and PaaS runtimes assume they
control the web server and the process model. This app's whole runtime is
one FrankenPHP container that terminates its own TLS, and its database is
a file on a named volume — neither survives contact with a platform that
wants to inject its own router or treat the filesystem as ephemeral.
[ADR 0003](0003-adopt-docker-frankenphp-symfony-sqlite-tailwind-for-volunteer-manager.md)'s
SQLite choice makes a single persistent server the point, not a
limitation to work around.

### 3. Build the image locally and `docker save`/`scp` it to the server

**Rejected.** It removes the CI dependency, and it would work. But it
makes deployments depend on one developer's laptop being present and
correctly configured, produces no record of what was deployed beyond an
image ID, and keeps the "production build only happens when someone
deploys" failure mode this ADR exists to remove.
