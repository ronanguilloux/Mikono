---
title: Second slice of volunteer profile fields
created: 2026-09-23
source: kingsley
status: needs-design
size: M
priority: next
labels: [data, ux]
---

## Why

Kingsley listed eleven things the volunteer editing form should hold. Seven
of them already exist, so this card is only the remainder — written down
explicitly, because the fastest way to waste a day here is to rebuild what
[ADR 0032](../../adr/0032-store-volunteer-profile-fields-as-optional-and-photos-as-re-encoded-jpeg-blobs-in-sqlite.md)
shipped last week.

**Already in the app — do not rebuild:**

| Asked for | Where it already is |
| --- | --- |
| Volunteer dates (start → end) | `Stay`, [ADR 0026](../../adr/0026-attach-volunteers-to-branches-through-dated-stays-and-derive-active-from-them.md) |
| Location | derived, `Volunteer::getBranchOfAttachment()` (ADR 0026) |
| Project placement | `Activity` → `Program` → `Project`, [ADR 0030](../../adr/0030-insert-programs-between-projects-and-activities.md) |
| Nationality, country, date of birth, profession, skills, interests, emergency contacts | ADR 0032 |
| Documents upload | [volunteer-document-attachments](volunteer-document-attachments.md) |

What is left is below.

## Done when

Each new field follows ADR 0032's rules without exception — nullable, stored
as `null` and never `''` ([ADR 0014](../../adr/0014-make-a-volunteers-last-name-optional.md)),
shown on the profile as "Not on file · + Add", left out of the volunteers
export ([ADR 0029](../../adr/0029-export-every-list-view-to-csv-or-xlsx-with-openspout-open-to-all-signed-in-staff.md)),
`null` in fixtures — and `VolunteerControllerTest` covers the new form
fields.

The two open questions below are answered **before** any column is added.
That is what `needs-design` means here.

- **Accommodation preference** — nullable string, or a small enum if the
  choices are known. Cheapest item on the card.
- **Airport for pickup** — same shape.
- **Social media link** — nullable string with a `Url` constraint.
- **Passport information** — number and expiry. See the warning below.
- **Supervisor** — open question 1.
- **Previous volunteering history** — open question 2.

## Notes & links

- **Passport numbers are the most sensitive field the app would hold.**
  Identifying data under Kenya's DPA 2019, on a server in France
  ([ADR 0017](../../adr/0017-host-production-on-gandicloud-vps-in-france.md)),
  in an app whose repo is public. It must stay out of the export and out of
  every fixture. Consider whether a scanned passport on the documents card
  covers the real need better than a searchable number column does — the ask
  says "passport information", not "passport number".
- **Open question 1 — what is a supervisor?** A `User`, an `Escort`, or free
  text. `Escort` already exists, and its `isActive` now filters the
  activity pickers (`done.md`, 2026-09-24), so a supervisor built on
  `Escort` would inherit that retirement flag. Free text is the lazy answer and
  may well be the right one until someone needs to list a supervisor's
  volunteers.
- **Open question 2 — history of what?** Past stints at UCESCO are already
  derivable from `Stay` rows and want no column at all. Stints at other
  organisations are not, and want free text. The ask doesn't say which; it
  is one question to Kingsley, not a design session.
- ADR 0032 §1 is the template for the whole slice, down to the
  "Incomplete profile" pill.
