# 0045. Adopt a Makefile as the unified entry point for developer commands

Date: 2026-10-08

## Status

Accepted

## Context

The developer environment runs entirely through Docker and FrankenPHP (ADR
0003), meaning there is no host PHP or Composer requirement. Consequently,
interacting with the application requires remembering and typing verbose
commands like `docker compose exec php bin/console <command>`,
`docker compose exec php composer <task>`, or locating and running specific bash
scripts scattered inside the `scripts/` directory.

As the repository grew to include deployment scripts (`scripts/deploy.sh`),
database syncs (`scripts/sync-prod-db.sh`), static analysis runs, test suites
(unit, functional, E2E), and UI builds (`tailwind:build`), the mental overhead
for developers (and AI agents) increased. Developers needed to rely heavily on
tribal knowledge documented in `AGENTS.md` just to perform common, repetitive
tasks.

## Decision

We will adopt a `Makefile` in the project root as the unified entry point for
all developer commands.

Instead of running raw `docker compose exec php ...` strings or hunting for
scripts, developers will use simple `make <target>` commands (e.g., `make up`,
`make test`, `make sync-db`). The Makefile will encapsulate both Docker-agnostic
composer tasks and complex bash/Docker operations.

## Consequences

- **Positive:** Increased discoverability through the self-documenting
  `make help` target. Developers no longer need to remember long Docker
  incantations or where specific bash scripts live.
- **Positive:** Centralized configuration. The Makefile acts as a single pane of
  glass for all routine project tasks.
- **Negative / trade-offs:** Introduces a second way to run tasks. The raw
  commands and underlying bash scripts still exist, meaning developers might
  still use them directly. The ease of use and discoverability outweighs this
  duplication.
- **Reversibility:** High. If the Makefile becomes unmanageable, we can safely
  delete it and continue using the raw Docker compose and bash script commands
  as before without breaking the application logic or CI/CD pipelines.

## Alternatives considered

### 1. Documenting raw commands

**Rejected.** We heavily documented raw commands like
`docker compose exec php ...` in `AGENTS.md` and onboarding guides. However,
this didn't solve the friction of repeatedly typing verbose commands or the
discoverability issue when onboarding new developers (or agents).

### 2. Composer scripts

**Rejected.** While Composer scripts are great for defining PHP-specific tasks
(like `composer quality`), they run *inside* the PHP container. They cannot
easily orchestrate host-level actions like starting/stopping Docker containers
(`docker compose up`), copying files, or running complex bash scripts (like
`scripts/sync-prod-db.sh`) that interact with the host SSH environment or cloud
CLI tools.
