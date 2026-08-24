# Brainstorm — UCESCO Volunteer Manager (VM) v0.1

**Date:** 2026-08-24
**Author:** ronan.guilloux@gmail.com
**Related:** [`AGENTS.md`](../../AGENTS.md), [`docs/adr/`](../adr/)

---

## Primary audience

UCESCO's Volunteer Manager (the VM) — the person who will actually run
this app day to day, log activities, and pull the summary report — and,
per this repo's own convention, future-self or a future agent session
picking up this codebase cold, the same pairing already established in
`docs/brainstorm/README.md` and
[`01-symfony-php-skills-context.md`](01-symfony-php-skills-context.md).
The VM needs the app to make sense without a developer standing over
their shoulder; future-self needs to know why the entity shape and stack
choices below are what they are.

## Desired impact

This is Mikono's first real feature: a small internal Symfony web app so
UCESCO's Volunteer Manager can track volunteers working at UCESCO's
projects in Kibera (Nairobi) and Mombasa. Five entities carry the domain:
`User` (login accounts), `Volunteer` (CRM-style people who never log in),
`Project` (a location plus its ownership), `ActivityType` (a lookup
table), and `Activity` (the actual log entries — linking a volunteer, a
project, an activity type, a date, a duration, and who logged it).

The worked example that defines "done": on Monday 11 August 2026, Ronan
spent a day at Bright Achievers, a partner high school of UCESCO in
Kibera, delivering computer lessons to students. Success for v0.1 is the
VM being able to log that example end-to-end through the UI — create the
volunteer, the project, and the activity type if they don't already
exist, then log the activity itself — and see it reflected in a basic
per-volunteer / per-project summary report.

Concretely, success is obvious in hindsight if: a non-technical VM, on
both desktop and phone browsers, can manage Volunteers, Projects,
Activity Types, and Users, and log Activities, without asking a
developer for help; and a mistake (wrong date, wrong project) is
self-correctable through an edit or delete in the UI, never something
that needs a database fix.

This is explicitly **v0.1**: a single VM user, no API, no native mobile
app — just a responsive web app. Full `User` CRUD is being built now even
though only one person uses it today, because other UCESCO employees are
expected to get accounts later; that inclusion is a deliberate scope
decision made up front, not scope creep discovered mid-build.

## The "Options Not Taken"

### 1. API Platform plus a separate frontend (SPA)

**Rejected.** No API consumer exists — there is no mobile app, and no
third-party integration has been requested. A server-rendered
Symfony/Twig app is simpler to build and operate for a single
non-technical user than standing up a REST/GraphQL surface via API
Platform and a second frontend project to consume it. API Platform
skills are already staged in `.agents/skills/` in anticipation of a
future need, but they are deliberately not used for this feature —
building the API surface now would be unused complexity with no consumer
to justify it.

### 2. PostgreSQL or MySQL from day one instead of SQLite

**Rejected for v0.1.** A single VM user with a few hundred volunteers
and activities does not need a client-server database. SQLite is one
file, trivial to back up — copy the file — and requires no separate
service to run or operate inside the Docker setup. The five entities are
deliberately designed to avoid SQLite-only column types, so a later swap
to Postgres or MySQL remains a normal Doctrine migration if the app ever
grows beyond one user or one machine, rather than a rewrite.

### 3. Pest for testing

**Reversed**, not merely rejected — Pest was the tentative choice
earlier in this same planning conversation, before the plan was
finalized. PHPUnit 13 with attribute-based tests,
`symfony/phpunit-bridge`, PHPat for architecture rules, Infection for
mutation testing, and Panther/BrowserKit for E2E were chosen instead,
because the developer explicitly preferred that toolchain over Pest's
syntax. No ADR was ever written committing to Pest, so this reversal is
recorded here for the historical record; it is not something a later ADR
needs to mark "Superseded" — the earlier choice never reached ADR status.

### 4. LiveComponent-driven dependent selects on the Activity form

**Rejected for v0.1.** The three selects on the Activity creation form —
Volunteer, Project, Activity Type — are genuinely independent: a Project
doesn't filter which Volunteers are valid, and neither filters Activity
Type. A plain Symfony `EntityType` form with standard HTML selects is
simpler and fully testable with `WebTestCase`, with no JavaScript
round-trip to verify. LiveComponent is installed, as part of the
already-staged Symfony UX skill set, but is deliberately not forced into
this form. A real future use for it — a "recent activities for this
volunteer" side panel — is deferred to a later version, once there's an
actual dependent-data interaction to justify it.

## Constraints

- No PHP or Composer is installed on the development machine — both
  `php -v` and `composer --version` fail as "command not found" — so the
  entire app must run through Docker. Docker plus FrankenPHP, Symfony's
  official Docker pattern, was chosen so nothing PHP-related needs to
  exist on the host at all.
- No git repository exists yet in Mikono at the time this decision was
  made. Git gets initialized as part of building this app, not as a
  separate step beforehand.
- No production hosting target has been chosen yet. Where this actually
  runs for UCESCO — deployment specifics — is explicitly out of scope
  for v0.1 and deferred to a later decision.
