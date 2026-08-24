# 3. Adopt Docker+FrankenPHP, Symfony 8.1, SQLite, and Tailwind+Symfony UX for the Volunteer Manager app

Date: 2026-08-24

## Status

Accepted

## Context

UCESCO needs a small internal web app so its Volunteer Manager (the VM) can
track volunteers working at UCESCO's projects in Kibera (Nairobi) and
Mombasa. This is Mikono's first real application: CRUD for `User` (login
accounts), `Volunteer` (CRM-style people who never log in), `Project`,
`ActivityType`, and `Activity` (the log entries). See
[`docs/brainstorm/02-volunteer-manager-v0.1-context.md`](../brainstorm/02-volunteer-manager-v0.1-context.md)
for the full narrative, worked example, and audience.

No PHP or Composer is installed on the development machine — both `php -v`
and `composer --version` fail as "command not found" — so nothing PHP-related
can run directly on the host. No production hosting target has been chosen
yet; deployment specifics are explicitly out of scope for v0.1.

All of the choices below were decided together in one planning session, as
one coherent stack, not independently — they are recorded in a single ADR
for that reason.

## Decision

Build the Volunteer Manager v0.1 app on Docker + FrankenPHP running Symfony
8.1 on PHP 8.4, persisting to SQLite via Doctrine DBAL in a dedicated named
volume, with Tailwind CSS delivered through Symfony AssetMapper and the
already-staged Symfony UX packages for interactivity, and a five-entity data
model scoped tightly to v0.1's actual needs.

- **Runtime:** Docker + FrankenPHP, Symfony's official Docker pattern — the
  only viable choice given no host PHP/Composer.
- **Framework/language:** Symfony 8.1.x ("latest Symfony framework" per the
  product owner), PHP 8.4 inside the container.
- **Database:** SQLite via Doctrine DBAL. Entities are designed to avoid
  SQLite-only column types, so a later swap to Postgres or MySQL stays a
  normal Doctrine migration rather than a rewrite. Persistence uses an
  explicit named Docker volume (`db_data:/app/var/data`) holding just the
  SQLite file, kept separate from the ephemeral `var/cache`/`var/log`. This
  is deliberate: the default FrankenPHP dev-compose pattern excludes `var/`
  from the host bind mount for performance, leaving an anonymous volume or
  the container's writable layer as the default for anything under `var/` —
  which is not safe for the one file holding all of the VM's data. An
  anonymous volume can be lost on `docker compose down -v`, a rebuild, or a
  compose file change; a named volume survives all of those by default.
- **Frontend:** Tailwind CSS via `symfonycasts/tailwind-bundle` plus Symfony
  AssetMapper, so no Node.js toolchain is needed, together with the Symfony
  UX packages already staged as Agent Skills in this repo (Turbo, Stimulus,
  TwigComponent; LiveComponent is installed but its first real use is
  deferred past v0.1 — see the data model subsection below).
- **Explicitly not adopted:** API Platform — no API consumer exists for
  v0.1, and this repo's pre-staged API Platform skills stay unused here — a
  separate `Location` entity — two values don't justify a CRUD screen yet —
  and LiveComponent-driven dependent selects on the `Activity` form — the
  three FK fields are genuinely independent, so a plain Symfony `EntityType`
  form is simpler and fully `WebTestCase`-testable.

### Data model

Five entities carry the domain:

- **`User`** — login accounts, `ROLE_ADMIN` / `ROLE_USER`.
- **`Volunteer`** — CRM-style people, never log in.
- **`Project`** — name, a fixed `location` enum (`Kibera`, `Mombasa`) rather
  than a separate `Location` entity, and a fixed `ownership` enum (`Ucesco`,
  `Partner`).
- **`ActivityType`** — a simple lookup table.
- **`Activity`** — date, `volunteer`/`project`/`activityType` foreign keys,
  a `duration` enum (`HalfDay`, `FullDay`) rather than free-text hours,
  notes, and a `loggedBy` foreign key to `User` set server-side on create
  only.

Deletion guards: foreign keys default to `RESTRICT`, and each delete action
runs an app-level count check first, rather than surfacing a raw database
constraint error to a non-technical user.

## Consequences

- **Positive:** a non-technical VM gets a responsive UI without a
  JavaScript framework to maintain; SQLite gives a one-file backup story —
  copy the file; and the entity design avoids a future migration headache
  if the app ever needs a client-server database.
- **Negative / trade-offs:** SQLite does not support concurrent writers
  well. This is acceptable for one VM user in v0.1 but would need
  revisiting before the app supports multiple simultaneous editors.
- **Reversibility:** the database engine swap is a contained Doctrine
  migration by design, since entities already avoid SQLite-only column
  types. The Docker/FrankenPHP choice would be a bigger redo if it were
  ever needed, but it is a standard enough pattern to be low-risk.

## Alternatives considered

### 1. API Platform plus a separate frontend (SPA)

**Rejected.** No API consumer exists — no mobile app, no third-party
integration has been requested. Standing up a REST/GraphQL surface and a
second frontend project to consume it is unnecessary complexity for one
non-technical user.

### 2. PostgreSQL or MySQL from day one

**Rejected for v0.1.** A single VM user with a few hundred volunteers and
activities does not need a client-server database. SQLite is simpler to
operate and back up inside the Docker setup, and the entity design keeps
the door open to migrate later if the app ever grows beyond one user or one
machine.

### 3. Plain host PHP instead of Docker

**Rejected.** No PHP or Composer is installed on the development machine —
confirmed by both `php -v` and `composer --version` failing as "command not
found" — so this was not actually a viable option, not merely a
less-preferred one.

### 4. DDEV instead of raw Docker+FrankenPHP

**Rejected.** DDEV is heavier and more opinionated than needed for a
single-developer v0.1 build; raw Docker+FrankenPHP, Symfony's own official
Docker pattern, is a lighter fit with fewer moving parts to learn or debug.
