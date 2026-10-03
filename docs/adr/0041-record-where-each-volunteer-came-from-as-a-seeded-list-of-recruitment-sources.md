# 0041. Record where each volunteer came from as a seeded list of recruitment sources

Date: 2026-10-03

## Status

Accepted

## Context

The Volunteer Manager wants to know how volunteers find UCESCO, so she can
tell which recruitment channels are worth the effort. One volunteer can
come through several: they see a TikTok, then apply through Volunteer
World. Nothing in the app records this.

A volunteer's recruitment channel is personal data, so
[ADR 0034](0034-comply-with-kenyas-data-protection-act-2019.md) admits it
only once its purpose, sensitivity, retention and transfer basis are
stated. The channels themselves change: when UCESCO starts recruiting on a
new platform, the VM should not have to wait for a deploy to record it.

## Decision

**A volunteer's recruitment sources are picked from a global `Source`
list, seeded by its migration, through an optional many-to-many link
recorded once per volunteer.**

- **Shape.** `Source` has a unique `name` and an optional `description`.
  It is the fourth global list, after Activity Types, Skills and
  Beneficiary Groups. `Volunteer::$sources` is a `ManyToMany` collection
  (join table `volunteer_source`). The volunteer form offers it as an
  optional "Sources" field with expanded multi-choice checkboxes, and the
  volunteer page lists the sources.
- **The migration that creates the table inserts the VM's seven channels,
  verbatim:** Volunteer World, Website/Email, WhatsApp, TikTok, Instagram,
  Facebook, YouTube. As with branches and skills
  ([ADR 0025](0025-model-ucesco-branches-as-a-standalone-reference-entity-seeded-by-migration.md),
  [ADR 0036](0036-manage-skills-as-a-seeded-list-shared-by-volunteers-and-programs.md)),
  production gets them on deploy, and tests and dev start with them
  because Foundry replays migrations.
- `/sources` has the index/new/edit/delete and export shape of `/skills`
  ([ADR 0029](0029-export-every-list-view-to-csv-or-xlsx-with-openspout-open-to-all-signed-in-staff.md)).
  Staff add, rename or delete channels there. An "Other" or "Word of
  mouth" row is one such addition, not a deploy.
- **Delete guard:** a source can't be deleted while any volunteer holds
  it. The index shows Delete inert on those rows through the DataTable's
  `disabledReason`, fed by one batched count per page rather than a query
  per row. The server-side guard in `delete()` stays regardless.
- **Optional, and never backfilled.** Existing volunteers' sources can't
  be truthfully reconstructed
  ([ADR 0032](0032-store-volunteer-profile-fields-as-optional-and-photos-as-re-encoded-jpeg-blobs-in-sqlite.md)).
  Unlike skills, a missing source does not count towards
  `Volunteer::isProfileIncomplete()`: it is a gap in the recruitment
  figures, not in the volunteer's profile.
- **Once per volunteer, not per stay.** A returning volunteer keeps their
  sources; that they came back is already visible from their stays
  ([ADR 0026](0026-attach-volunteers-to-branches-through-dated-stays-and-derive-active-from-them.md)).
- `/volunteers` takes a `?source=<id>` filter
  (`:source MEMBER OF v.sources`), applied in `listQueryBuilder()` so it
  combines with the other filters and reaches the export. Malformed
  input degrades to no filter, as `?skill=` does
  ([ADR 0023](0023-degrade-malformed-query-input-to-a-default.md)).
- **Dev fixtures carry none.** The roster archive records no source, and
  the dataset is never generated
  ([ADR 0012](0012-seed-fixtures-from-the-real-whatsapp-roster-archive.md)).
- **Personal data** (ADR 0034):
  - *Purpose:* measuring which recruitment channels bring volunteers.
  - *Sensitivity:* not an s.2 category. A marketing channel reveals
    nothing about health, family, belief, ethnicity or sex. Unlike gender
    ([ADR 0037](0037-record-a-volunteers-gender-for-accommodation-pairing.md)),
    it may appear in the volunteer list, its filter and the per-list
    exports, as skills do.
  - *Retention:* the volunteer record's (ADR 0034, rule 6). Doctrine
    deletes a volunteer's `volunteer_source` rows when it removes the
    volunteer; the schema's `ON DELETE CASCADE` does not fire, because
    SQLite runs here without foreign keys
    ([ADR 0038](0038-record-achievements-on-a-volunteers-stay.md)).
  - *Transfer basis:* no third party receives it. It sits in the
    production database on the same s.48 safeguards as the rest of the
    volunteer row (ADR 0034, rule 3) and, not being sensitive, needs no
    s.49(1) consent.
  - The admin whole-database zip picks up `source` and `volunteer_source`
    automatically. Neither holds a secret, so neither goes on a drop list
    ([ADR 0039](0039-export-the-whole-database-as-an-admin-only-zip-of-per-table-csvs.md)).
- **`/reports?tab=source` counts one Nairobi calendar year at a time**
  ([ADR 0024](0024-treat-dates-as-calendar-days-in-nairobi-time.md)),
  picked with `?year=` and defaulting to this one; a year outside the
  span of recorded stays falls back to this year. "Volunteers present" is
  the volunteers holding the source with a stay overlapping the year, the
  Present rule of ADR 0026 stretched over a period. The activity columns
  are that year's activities, each credited in full to every source of its
  volunteer, as `?tab=group` credits every group of a program, so the
  totals exceed the real ones and the tab says so. Every source has a row,
  zeros included, since a channel that brought nobody is the finding;
  volunteers with no source share a "Not recorded" row, shown only when
  someone is in it.

## Consequences

- **Positive:** the VM can filter, count and export volunteers by
  channel, with one spelling per channel. A new channel is a row, not a
  deploy. Several sources per volunteer capture the path from first
  contact to application.
- **Negative / trade-offs:** volunteers recorded before the field have no
  source, so early figures undercount. A returning volunteer brought back
  by a different channel gains a source on the same set, losing which
  stay it brought. The privacy notice must mention the field (ADR 0034,
  rule 4). The list can drift into near-duplicates if staff add them.
- **Reversibility:** cheap. One migration drops the entity and join table,
  and the recorded sources go with them. Moving to per-stay sources later
  would have to guess which stay each existing source belongs to.

## Alternatives considered

### 1. A PHP backed enum

**Rejected.** Every new channel would be a code change and a deploy.
Several per volunteer would need a JSON column, which DQL's `MEMBER OF`
can't filter, leaving `?source=` to string matching or SQLite-specific
JSON functions.

### 2. Free text

**Rejected.** "TikTok", "tiktok" and "saw a video" are three strings:
readable, but not countable, and counting is the purpose. Free text can
also drift into family details ("my cousin volunteered here"), which s.2
makes sensitive (ADR 0034, rule 2).

### 3. Sources per stay

**Rejected.** More faithful for a returning volunteer recruited again
through a different channel, but it doubles the entry burden for a
question the VM asks about people, not stays. Revisit if she asks for it.
