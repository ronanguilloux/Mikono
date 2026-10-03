---
title: Record where each volunteer came from
created: 2026-10-03
source: edna
status: needs-decision
size: M
priority: now
labels: [data, ux]
---

## Why

Edna wants to know how volunteers find UCESCO, so she can tell which channels
are worth the effort. The answer can be more than one: someone can see a
TikTok, then apply through Volunteer World. Nothing in the app records this.

Her list, verbatim:

- Volunteer World
- Website/Email
- WhatsApp
- TikTok
- Instagram
- Facebook
- YouTube

## Decision needed first

This is a new personal-data field, so under
[ADR 0034](../../adr/0034-comply-with-kenyas-data-protection-act-2019.md) it
needs its own ADR (via `adr-scribe`) before any code. The ADR states:

- **Purpose**: measuring which recruitment channels work.
- **Sensitivity**: not sensitive under s.2.
- **Retention**: the volunteer record's.
- **Transfer basis**: none.
- **Storage shape**.

Recommended shape: a fourth global list, following
[ADR 0036](../../adr/0036-manage-skills-as-a-seeded-list-shared-by-volunteers-and-programs.md)
(`Skill`):

- a `Source` entity seeded by its migration with Edna's seven rows;
- a many-to-many `Volunteer::$sources`;
- a `/sources` CRUD area with the same shape as `/skills`.

This lets the VM add a channel (say LinkedIn) without a deploy, and the
`MEMBER OF` filter already works. A PHP enum would mean a code change per new
channel and a JSON column that the filter can't query cleanly.

## Done when

- The ADR above exists.
- `Source` (unique `name`) and its migration seed Edna's seven rows. Tests start
  with them, like `Branch` and `Skill`.
- `/sources` has the index/new/edit/delete/export shape of `/skills`. Delete is
  blocked while a volunteer holds the source (a `countReferencingVolunteers()`
  guard plus the DataTable `disabledReason`). `RouteSmokeTest`'s prefix map
  gets the new id.
- The volunteer form gets an optional "Sources" multi-choice field (`EntityType`,
  `multiple`, `expanded`), rendered like Skills. Optional on purpose: existing
  records can't be truthfully backfilled
  ([ADR 0032](../../adr/0032-store-volunteer-profile-fields-as-optional-and-photos-as-re-encoded-jpeg-blobs-in-sqlite.md)).
- The volunteer page lists the sources.
- `/volunteers` gets a `?source=<id>` filter, read the way `requestedSkill()`
  reads its parameter. It combines with the other filters and reaches the
  export.
- The Admin → Export database zip picks the new tables up automatically
  ([ADR 0039](../../adr/0039-export-the-whole-database-as-an-admin-only-zip-of-per-table-csvs.md)).
  Neither holds a secret, so neither goes on a drop list.
- Functional tests cover saving several sources, the filter, and the
  delete-guard.

## Questions for Edna

- Is an **"Other"** or **"Word of mouth / referral"** option needed? Friends
  and returning volunteers are common channels that the list leaves out.
- Should a **returning** volunteer's source be recorded per stay, or once per
  volunteer? This card assumes once per volunteer.
- Does she want **counts per source** on `/reports`? A `?tab=source` breakdown
  would follow the existing tabs, but it is not in this card's scope.

## Notes & links

- Pattern to copy: `src/Entity/Skill.php`, `src/Controller/SkillController.php`,
  `src/Form/VolunteerFormType.php:58` (the skills field), and
  `VolunteerRepository::createOrderedByNameQueryBuilder()` (`:skill MEMBER OF`).
- The roster archive has no source data, so the dev fixtures stay without it
  ([ADR 0012](../../adr/0012-seed-fixtures-from-the-real-whatsapp-roster-archive.md)).
