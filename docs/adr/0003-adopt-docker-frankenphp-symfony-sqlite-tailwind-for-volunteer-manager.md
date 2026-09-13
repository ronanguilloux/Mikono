# 3. Adopt Docker+FrankenPHP, Symfony 8.1, SQLite, and Tailwind+Symfony UX for the Volunteer Manager app

Date: 2026-08-24

## Status

Accepted

## Context

UCESCO needs a small internal web app so its Volunteer Manager (the VM) can
track volunteers working at UCESCO's projects in Kibera (Nairobi) and
Mombasa: login accounts, volunteers (who never log in), projects, activity
types and the activity log. One non-technical daily user, a few hundred
records. See
[`docs/brainstorm/02-volunteer-manager-v0.1-context.md`](../brainstorm/02-volunteer-manager-v0.1-context.md).

The development machine has no PHP or Composer installed. The choices below
were made together as one stack, so they share one ADR. Deployment is
[ADR 0010](0010-build-in-ci-and-deploy-by-image-pull.md) and
[ADR 0017](0017-host-production-on-gandicloud-vps-in-france.md).

## Decision

**Docker + FrankenPHP running Symfony 8.1 on PHP 8.5, SQLite through
Doctrine on a dedicated named volume, Tailwind CSS through AssetMapper, and
Symfony UX for interactivity.**

- **Runtime:** Docker + FrankenPHP, Symfony's official Docker pattern.
  Everything — console, Composer, tests — runs through the container.
- **Database:** SQLite via Doctrine. Entities avoid SQLite-only column types
  (enums are mapped as plain strings), so moving to Postgres or MySQL stays
  a normal migration. The file lives on an explicit named volume
  (`db_data:/app/var/data`), not under the anonymous `var/` volume the
  FrankenPHP dev pattern uses: an anonymous volume can vanish on
  `down -v`, a rebuild or a compose change, and this file is all of the
  VM's data.
- **Frontend:** Tailwind via `symfonycasts/tailwind-bundle` and
  AssetMapper, so the frontend build needs no Node toolchain
  ([ADR 0016](0016-admit-nodejs-as-a-test-dependency-not-as-application-code.md)).
  Turbo, Stimulus and TwigComponent for interactivity; LiveComponent is
  installed but unused.
- **Data model rules:** fixed small value sets are backed enums on the
  entity (`ProjectLocation`, `ProjectOwnership`, `ActivityDuration`), not
  lookup entities with their own CRUD screen. Foreign keys are `RESTRICT`,
  and every delete action runs an app-level count first so a
  non-technical user sees a message rather than a constraint error.
  `Activity::$loggedBy` is set server-side on create only. Later shape
  changes: [0008](0008-add-other-activity-duration-with-free-text-companion-field.md),
  [0013](0013-record-every-escort-on-an-activity.md),
  [0014](0014-make-a-volunteers-last-name-optional.md).
- **Composer recipes:** `allow-contrib: false` in `composer.json`,
  deliberately. Contrib Flex recipes never run, so a contrib package
  (`dama/doctrine-test-bundle`, `knplabs/knp-paginator-bundle`) is wired by
  hand in `config/bundles.php` and `config/packages/`. A skipped recipe also
  skips whatever else it would have switched on — Knp's would have enabled a
  translator — which is the point: what lands in `config/` is written and
  reviewed here. Wire a future contrib package the same way; never flip the
  flag project-wide.
- **Not adopted:** API Platform (no API consumer), a `Location` entity (two
  values), LiveComponent dependent selects on the activity form (the fields
  are independent; a plain `EntityType` form is simpler and testable).

## Consequences

- **Positive:** a responsive UI with no JavaScript framework to maintain;
  a one-file database whose backup is a file copy; an entity design that
  does not lock the app to SQLite.
- **Negative / trade-offs:** SQLite handles concurrent writers poorly —
  fine for one VM, to revisit before several people edit at once.
- **Reversibility:** the database swap is a contained migration by design.
  Leaving Docker/FrankenPHP would be a bigger redo, but it is a standard,
  low-risk pattern.

## Alternatives considered

### 1. API Platform plus a separate SPA

**Rejected.** No API consumer exists; a REST surface and a second frontend
project are unnecessary for one non-technical user.

### 2. PostgreSQL or MySQL from day one

**Rejected.** One user and a few hundred rows don't need a client-server
database; SQLite is simpler to run and back up, and the entities keep the
door open.

### 3. Host PHP instead of Docker

**Rejected.** Not available — no PHP or Composer on the machine.

### 4. DDEV

**Rejected.** Heavier and more opinionated than a single-developer app
needs; raw Docker+FrankenPHP has fewer moving parts.
