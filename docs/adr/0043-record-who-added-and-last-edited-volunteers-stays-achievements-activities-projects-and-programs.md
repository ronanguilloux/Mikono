# 0043. Record who added and last edited volunteers, stays, achievements, activities, projects and programs

Date: 2026-10-04

## Status

Accepted

## Context

The admin wants a page per staff account that lists what that account
did. The database could tie very little to a user:

- **Sign-ins**, in `login_attempt`, matched by email and kept for 90 days
  ([ADR 0028](0028-record-login-attempts-with-identifier-and-ip-for-90-days-admin-only.md)).
- **`Activity::$loggedBy`**, the staff member who logged an activity.
- **The account's own `createdAt` and `updatedAt`.**

Nothing recorded who added or changed a volunteer, a stay, an
achievement, a project or a program. When a volunteer's record held a
mistake, nobody could tell who had entered it or who had changed it last.

The Caddy access log can't fill the gap. It carries no identity on
purpose, and `usage_event` has no user column for the same reason
([ADR 0021](0021-read-usage-from-an-in-app-usage-screen-over-the-caddy-access-log.md)).

A staff member's id stamped on a record is personal data about that staff
member. [ADR 0034](0034-comply-with-kenyas-data-protection-act-2019.md)
therefore requires this ADR to state its purpose, sensitivity, retention
and transfer basis.

## Decision

**Volunteers, stays, achievements, projects and programs record which
signed-in user added them and who last edited them, and activities record
who last edited them. One Doctrine `onFlush` listener stamps both, and an
admin-only page per user lists the results.**

### What is stamped

- **`createdBy` and `updatedBy`**, both nullable `ManyToOne` to `User`, on
  `Volunteer`, `Stay`, `Achievement`, `Project` and `Program`. They come
  from `App\Entity\AuthoredTrait`. The entity implements
  `App\Entity\Authored`, whose one method is
  `recordAuthor(User $user, bool $isNew)`.
- **`Activity` has `updatedBy` only.** `loggedBy` already records who
  created it. Don't add a `createdBy` that would duplicate it.
- **Not covered, on purpose:** the reference lists (`ActivityType`,
  `Skill`, `Source`, `BeneficiaryGroup`, `Escort`, `Branch`) and `User`.
  They are rarely edited and hold no volunteer data.

### How it is stamped

- **One listener, `App\Security\AuthorStamper`, on `onFlush`.** Nothing
  else calls `recordAuthor()`. Trap: don't move it to `preUpdate`, because
  `PreUpdateEventArgs` can't add a field that isn't already in the change
  set, and `updatedBy` usually isn't.
- **An insert sets both columns** to the signed-in user.
- **An update is stamped only when `updatedAt` is in its change set**,
  which means a controller called `touch()`. So `updatedBy` and
  `updatedAt` always describe the same edit. **A new edit path that
  doesn't call `touch()` is not attributed.**
- **With no signed-in user, the columns stay null.** This covers console
  commands, fixtures and migrations.
- **The columns are never form fields.**

### Only the last editor is known

Each record holds who added it and who edited it last, nothing more. No
earlier editor and no deletion is recorded. Records written before the
columns existed, on 2026-10-04, have null authors.

### Deleting a user

SQLite runs here without foreign keys enforced
([ADR 0038](0038-record-achievements-on-a-volunteers-stay.md)), so an
`ON DELETE SET NULL` would never fire, and a deleted user would leave
dangling ids behind. `UserRepository::detachAuthorship()` nulls every
`createdBy` and `updatedBy` that points at the user, inside the same
transaction as the delete. It finds the columns from Doctrine metadata,
across every `Authored` class, so a new one is covered without changing
the method.

The delete-guard on `loggedBy` is unchanged: a user who logged activities
can't be deleted, and the screen says "Deactivate instead". The records a
deleted user added or edited survive, unattributed.

### Where it is shown

`App\Usage\UserTimeline` merges four sources, newest first, capped at 50
entries:

- sign-ins, matched to the user's current email as on `/usage`
  (ADR 0028);
- activities they logged (`loggedBy`);
- records they added (`createdBy`);
- records they last edited (`updatedBy` where `updatedAt` is later than
  `createdAt`, because an insert stamps `updatedBy` too).

It is shown on `/users/{id}`, which inherits `UserController`'s
`ROLE_ADMIN` gate. The Sign-ins table on `/usage` links each known
account to that page.

A volunteer's own page also says "Added by … on …, last edited by … on
…", to every signed-in staff member who can open it. That is the same
audience that already sees the record. The names link to `/users/{id}`
for admins only. The line is left out when both columns are null, and
the edit half is left out until `updatedAt` is later than `createdAt`.
No other screen shows the columns.

Trap: `UserTimeline` finds `Authored` classes from metadata, but it labels
each one by hand. A new `Authored` entity needs its case there, or the
user's page throws.

### Personal data (ADR 0034)

- **Purpose:** accountability for changes to volunteer records. It shows
  who entered or changed a person's data, so a mistake can be traced to
  the staff member who made it.
- **Data and sensitivity:** a staff member's user id on a row. This is
  staff personal data, and not sensitive under s.2.
- **Retention:** the lifetime of the record it is stamped on. It is
  cleared when the user account is deleted.
- **Transfer:** none to a third party. The ids stay in the SQLite database
  and its backups, under ADR 0034's rule 3. They join the admin
  whole-database zip
  ([ADR 0039](0039-export-the-whole-database-as-an-admin-only-zip-of-per-table-csvs.md))
  automatically, as plain user ids. They are neither secrets nor file
  bytes, so the zip's drop lists don't change.

## Consequences

- **Positive:**
  - An admin can see what each account added and last changed, beside its
    sign-ins and logged activities, and can trace a wrong value on a
    volunteer's record to the staff member who entered it.
  - No new table, third-party service or scheduler. One listener covers
    every flush, so a new write path is attributed as long as it calls
    `touch()`.
  - Stamping and user deletion find `Authored` classes from metadata, so a
    new one needs no change in either.
- **Negative / trade-offs:**
  - Only the last editor is known. When a second user edits a record, the
    first one's edit disappears from both the record and the first user's
    timeline.
  - Deletions leave no trace, and a deleted user's records lose their
    attribution.
  - Records from before 2026-10-04, and anything written from the
    console, show no author.
  - An edit path that skips `touch()` is silently unattributed.
  - Every volunteer record now carries staff personal data. Admins see it
    on the user page. Every signed-in staff member sees who added and last
    edited a volunteer on that volunteer's page. It is also in the
    whole-database zip.
  - The timeline shows the newest 50 entries and has no pagination.
- **Reversibility:** cheap. Drop the columns in one migration, then remove
  the listener, the trait and interface, `detachAuthorship()` and the
  timeline's "Added" and "Edited" sources. Any attribution recorded so
  far is lost.

## Alternatives considered

### 1. An `audit_log` table written by an `onFlush` listener

**Rejected.** Rows of user, action (create, update or delete), entity
class, id and time would give history and deletions. They would also need
a retention design of their own, and they must store no labels: a deleted
volunteer's name kept in the log would outlive an erasure request under
ADR 0034. Revisit it if history or deletions become a need.

### 2. Stamp the user id into the Caddy access log

**Rejected.** It would make the access log personal, reversing ADR 0021,
which keeps it non-personal on purpose.

### 3. An explicit `touch($user)` in every controller

**Rejected.** Every new write path would have to remember it. The
listener catches every flush.

### 4. `createdBy` and `updatedBy` on every entity, reference lists included

**Rejected.** Activity types, skills, sources, beneficiary groups, escorts
and branches are rarely edited and hold no volunteer data. Stamping them
would add noise to the user page and columns to the schema, and answer no
question anyone asks.

### 5. Rely on the schema's `ON DELETE SET NULL`

**Rejected.** SQLite here runs without foreign keys enforced, so the
clause would never fire, and deleting a user would leave ids pointing at
nobody. `detachAuthorship()` clears them in the ORM whatever the
connection's settings.
