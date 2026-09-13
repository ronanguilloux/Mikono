# 10. Build the production image in CI and deploy by pulling it

Date: 2026-09-01

## Status

Accepted

## Context

When v0.1 became feature-complete, the production path had never been
exercised outside a laptop, and it showed: the production image could not
build at all (`tailwind:build` was missing before `asset-map:compile`, and
the Tailwind bundle throws outside `test` when no built CSS exists), and the
builder stage downloaded Chromium it never shipped. The only quality gate
was a bypassable local hook.

SQLite means one machine and one container, so this is a single-server
deployment with no orchestration
([`hosting-plan.md`](../project/hosting-plan.md)). The open question is where
the image is built and how it reaches the server. The provider is
[ADR 0017](0017-host-production-on-gandicloud-vps-in-france.md).

## Decision

**CI builds the production image and publishes it to GitHub Container
Registry; the server deploys by pulling it. The pair of compose files is the
deployment unit.**

- `.github/workflows/ci.yml` builds `frankenphp_dev` on every push and pull
  request and runs `composer quality` and `bin/phpunit` inside it.
- `.github/workflows/build-image.yml` builds `frankenphp_prod` on `main` and
  pushes `ghcr.io/ronanguilloux/app-php-prod`, tagged with the commit SHA and
  `latest`. A production build that cannot complete is a red pipeline, not a
  deploy-day discovery.
- The server holds a checkout **only for the compose files** and runs
  `docker compose -f compose.yaml -f compose.prod.yaml pull && … up -d`. No
  Composer, PHP, Node or build toolchain on the host. Both compose files are
  always passed: a bare `up` would load `compose.override.yaml` and run
  production in dev mode.
- Runtime configuration lives in a root-only `deploy.env` passed with
  `--env-file`. **`APP_SECRET` is a runtime variable, never a build
  argument:** the image is public, and `composer dump-env prod` bakes
  build-time environment into it.
- `IMAGES_PREFIX` in `compose.prod.yaml` doubles as the registry prefix, so
  one file covers a local production build and a registry pull.

## Consequences

- **Positive:** the production image is built on every merge and cannot rot
  silently. Deploy is `pull` + `up`, which a 1–2 GB VPS can do; building on
  the server would need about four times the RAM. No source, credentials or
  build cache on the production host. Rollback is pulling an earlier tag.
- **Negative / trade-offs:** deploys depend on GitHub Actions and GHCR
  being reachable (the running app does not). The public image makes "no
  secrets at build time" load-bearing. Rolling back the image does not roll
  back a migration; that path is a restore from backup
  ([`deployment-plan.md`](../project/deployment-plan.md)).
- **Reversibility:** cheap. `compose.prod.yaml` keeps its `build:` stanza,
  so building on the server remains a fallback.

## Alternatives considered

### 1. Build on the server (`git pull` + `docker compose build`)

**Rejected.** Needs roughly 4 GB RAM and 20 GB disk against the ~1 GB the
app needs to run, puts source and a build toolchain on the internet-facing
host, and leaves the production build unexercised between deploys — the
exact failure that motivated this.

### 2. Push-to-deploy or a PaaS

**Rejected.** They assume they own the web server and treat the filesystem
as ephemeral; this app is one FrankenPHP container terminating its own TLS
over a database file on a persistent volume.

### 3. Build locally and `docker save`/`scp` the image

**Rejected.** Depends on one laptop, leaves no record of what was deployed,
and keeps "the production build only runs when someone deploys".
