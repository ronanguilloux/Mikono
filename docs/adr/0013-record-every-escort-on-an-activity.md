# 0013. Record every escort on an activity, not just one

Date: 2026-09-16

## Status

Accepted

## Context

`Activity::$accompaniedBy` is a nullable `ManyToOne` to `Escort`: an
activity is accompanied by one staff member, or by nobody. That shape
came from reading the VM's roster messages, where almost every site
block ends in a single "Accompanied by Mr Maeba" line.

Building the fixtures from the real archive
([ADR 0012](0012-seed-fixtures-from-the-real-whatsapp-roster-archive.md))
turned up the exception. The rosters for 30/08/2026 and 31/08/2026 both
read:

```text
Peggy Lucas school
-Daphne
-Marco
Accompanied by Edna and Sam
```

Two escorts, one group. Under the current model the fixture has to drop
one of them, and the app has no way to record the second — which also
means the home screen's roster panel, whose entire job is to reproduce
those messages so the VM can copy them back into WhatsApp, would
silently print a roster that differs from the one she actually sent.

The rest of the read path was already built for more than one.
`RosterBuilder` collects `escortNames` as a `list<string>` and
`RosterGroup` renders them as a list, because a project group can
aggregate several activity rows. So the limitation is only in the write
path and the storage: the display has been waiting for this since the
home screen shipped.

There is also a workload dimension: every escort row is a staff workload
figure, and `/reports` reads it per escort. A model that attributes a
session to only one of the two people who ran it would make that report
wrong from the start.

## Decision

**An activity records all of its escorts: `Activity::$accompaniedBy`
becomes `Activity::$escorts`, a `ManyToMany` to `Escort`.**

- The property is a `Collection<int, Escort>`, ordered by name, with
  `addEscort()` / `removeEscort()` / `getEscorts()`. The plural property
  name is what lets Symfony's PropertyAccess find those adders; the
  user-facing label stays **"Accompanied by"** on both forms, because
  that is the VM's own wording.
- Both write paths take multiple: `ActivityFormType` and
  `BatchActivityFormType` expose the escort field as a `multiple`
  `EntityType`, and `BatchActivityInput::$escort` becomes
  `$escorts` (a list), applied to every activity the batch fans out.
- "Nobody accompanied this group" is now an **empty collection**, not
  `null`. Templates test emptiness rather than null.
- `EscortRepository::countReferencingActivities()` — the delete-guard —
  switches from `a.accompaniedBy = :escort` to
  `:escort MEMBER OF a.escorts`. The guard's behaviour is unchanged: an
  escort that appears on any activity still cannot be deleted.
- A migration moves the existing `accompanied_by_id` values into the new
  `activity_escort` join table before dropping the column, so no
  already-logged activity loses its escort.

**The read path counts escorts from what the rosters actually record.**

- `/reports` has a **By escort** tab (also in the print panel), built by
  `ActivitySummaryCalculator::summarizeByEscort()` over
  `ActivityRepository::findAllWithEscorts()`, which fetch-joins escorts
  and so carries no `LIMIT`. Columns: Escort, Days on duty, Site visits,
  Activities, Most recent; sorted by days on duty, then site visits,
  descending.
- **Days on duty** is the number of distinct dates the escort appears on
  — never a sum of durations. An activity row is one volunteer, and a
  volunteer's half or full day says nothing about how long the escort
  stayed; the app records no escort time, so it cannot be derived. If
  half-days per escort are ever needed, escort time has to be recorded on
  the batch form (a model change), not inferred.
- **Site visits** is distinct date + project, kept apart from days
  because one escort covers two or three sites on one day in the real
  archive (Hassan on 11, 12 and 14 August 2026; Mr Maeba on 21 August).
- **Activities** is the number of volunteer rows accompanied. An activity
  with several escorts counts for each of them.
- An activity with no escort lands in an id-less **"No escort recorded"**
  row — never "Unaccompanied". In the archive most empty lists are
  WhatsApp messages cut off before the escort line (`truncated: true`), so
  empty means "not written down", not "nobody went". The row is a
  follow-up list for safeguarding.
- The Activities index shows an unsortable **Accompanied by** column after
  Project (names joined by ", ", "—" when empty). It is in `COLUMNS`, so
  the CSV/xlsx export carries it
  ([ADR 0029](0029-export-every-list-view-to-csv-or-xlsx-with-openspout-open-to-all-signed-in-staff.md)),
  and never in `SORT_MAP`. The mobile card shows an "Accompanied by …"
  line only when escorts are set.

## Consequences

- **Positive:** the roster panel can reproduce the VM's real messages
  exactly, including the two-escort evenings, instead of approximating
  them. The fixtures no longer have to discard real data to fit the
  schema.
- **Positive:** the By escort report starts from an honest attribution —
  both people who ran a session are credited with it — and its "No escort
  recorded" row surfaces the gaps instead of hiding them.
- **Negative / trade-offs:** the escort is no longer a to-one join, so
  `ActivityRepository::findByDate()`'s eager `leftJoin` on it can
  multiply rows and no longer composes with a `LIMIT`. That method has
  no limit today (it loads one day), but the constraint is now real and
  is called out in its docblock; the paginated index query deliberately
  does not join escorts at all.
- **Negative / trade-offs:** the Activities index's Accompanied by column
  renders a list per row and cannot be sorted by a plain DQL field. The
  paginated query still does not join escorts (it carries a `LIMIT`), so
  they lazy-load per row — an N+1 accepted at this data volume. That is a
  genuine cost, and it is the truthful one: the data is a list.
- **Negative / trade-offs:** the By escort report cannot say how long an
  escort was out, only on how many days and at how many sites.
- **Negative / trade-offs:** one more table, and a `multiple` select is
  a slightly heavier control than a dropdown on a phone.
- **Reversibility:** moderate. Collapsing back to one escort per
  activity is a migration that has to decide which escort to keep — a
  lossy choice — plus reverting both forms. Nothing else in the app
  depends on the collection shape.

## Alternatives considered

### 1. Keep one escort and record the second in the activity's notes

**Rejected.** `notes` is free text the VM writes for herself; parsing a
person out of it for a roster line or a workload figure is not something
this app should ever do. It also makes the fixtures store a real fact in
a place no query can reach, which is the same problem as dropping it.

### 2. Keep one escort and add a "co-escort" field

**Rejected.** It fixes the two-escort case and breaks on three, and it
gives two staff members doing identical work different database
positions — which would quietly bias exactly the per-escort workload
report this change is meant to keep honest.

### 3. Model escorting as its own entity between `Activity` and `Escort`

**Rejected.** An `ActivityEscort` row would only ever carry the two
foreign keys it already has; nothing in the rosters distinguishes a
lead escort from an accompanying one, or records a role, or a partial
day. Adding the join entity now would be building for a requirement
nobody has expressed, against a model this app is deliberately keeping
small.

### 4. Leave the model alone and drop the second escort in the fixtures

**Rejected.** It was the cheap option, and it is the one
[ADR 0012](0012-seed-fixtures-from-the-real-whatsapp-roster-archive.md)
exists to refuse: when the real data does not fit the model, the finding
is about the model. This is the first thing the real archive caught, and
quietly discarding it would have made the fixtures no more trustworthy
than the generated ones they replace.

### 5. Report escort time in days derived from the volunteers' durations

**Rejected.** An activity row is one volunteer, so summing durations
would credit an escort who took four volunteers out for a day with four
days. And a volunteer's half or full day is the volunteer's time, not the
escort's: the app records no escort time, so any figure derived this way
would be invented.

### 6. Count site visits as days

**Rejected.** The archive has escorts covering two or three sites on one
day, so counting date + project as days would double- or triple-count
exactly the busiest days on duty.
