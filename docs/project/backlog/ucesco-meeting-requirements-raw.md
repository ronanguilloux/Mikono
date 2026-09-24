---
title: Requirements from the UCESCO meeting, raw
created: 2026-09-23
source: nickson
status: needs-decision
size: XL
priority: next
labels: [docs]
---

## Why

Nickson sent back the key points from the last meeting as one block. It is
kept here **verbatim and untriaged on purpose**: splitting it into
individual cards and reconciling the parts that conflict with decisions
already in force is its own session's work, and doing it halfway would lose
the original wording.

`size: XL` is not an estimate. Per [`README.md`](README.md) it means *split
this card* — which is exactly the next action.

## Done when

This card is split into individual cards and deleted. Every line below is
either a new card, a line added to an existing card, or explicitly dropped
with a reason. Nothing is left implicit.

Start from the coverage table under **Notes & links**: roughly half of the
recap is already shipped, and the split is cheap if that half is set aside
first.

## Raw input

> Hi Ronan, Currently going through the app, based on the last meeting we
> had, below are the key points we went through regarding the volunteer
> management system:
>
> **Volunteer Profiles**
> Country, date of birth, occupation, skills, interests, branch/location,
> emergency contact.
> Volunteer start/end dates and automatically calculated days contributed.
> Uploading relevant documents and birthday reminders.
>
> **Programs & Activities**
> Structure the system into Programs → Activities.
> Capture activity type, description, date/time, location, volunteers
> required/assigned, roles and notes.
>
> **Attendance & Volunteer Hours**
> Track assigned vs. actual attendance.
> Record volunteer hours and automatically calculate total hours/days.
> View each volunteer's activities, days and hours contributed.
>
> **Impact & Reporting**
> Capture activity impact and indicators such as volunteers engaged, hours,
> activities supported and beneficiaries reached.
> Generate volunteer/activity reports.
>
> **Search & Filtering**
> Search the volunteer database and filter by program, activity, location,
> skills, interests, branch and volunteer status.
> Ability to combine multiple filters.
>
> **Data Export**
> Export volunteer, activity, attendance and hours data to Excel/CSV.
> Ability to export the complete database when required.
>
> **User Accounts & Access**
> Different access levels for Admin, Volunteer Coordinator, Program/Project
> Managers and Volunteers.
> Volunteers should only access their own information, assigned activities
> and relevant announcements.
> Admin should be able to deactivate accounts.
>
> **Communication**
> Share activity schedules through WhatsApp.
> Volunteers should be able to view upcoming and assigned activities.
>
> **Data Ownership**
> UCESCO should retain ownership and full access to its volunteer/activity
> data, including the ability to access and export it independently of the
> hosting arrangement.
>
> **Hosting, Security & Backup**
> Clarify the two-year hosting arrangement, maintenance/support and what
> happens after the hosting period.
> Confirm data security, access restrictions, backups, restoration and
> recovery procedures.

## Notes & links

Where each section stands today. This is the only thing added to the raw
text, and it exists so the split does not have to re-derive it.

| Recap section | Status |
| --- | --- |
| Programs → Activities | Shipped, [ADR 0030](../../adr/0030-insert-programs-between-projects-and-activities.md) |
| Profile: country, DOB, occupation, skills, interests, emergency contact | Shipped, [ADR 0032](../../adr/0032-store-volunteer-profile-fields-as-optional-and-photos-as-re-encoded-jpeg-blobs-in-sqlite.md) — optional, not mandatory, and that was deliberate |
| Branch / location | Shipped, derived from stays, [ADR 0026](../../adr/0026-attach-volunteers-to-branches-through-dated-stays-and-derive-active-from-them.md) |
| Volunteer start/end dates, days contributed | Shipped: `Stay` (ADR 0026), days via `src/Report/ActivitySummaryCalculator` |
| Document upload, birthday reminders | [volunteer-document-attachments](volunteer-document-attachments.md), [automated-outbound-reminders](automated-outbound-reminders.md) |
| Search & filtering | Volunteer search and the activities branch filter are shipped (see [`done.md`](../done.md)). Filtering by skills, interests and status is **not** covered — new cards. |
| Export to Excel/CSV | Shipped for every list view, [ADR 0029](../../adr/0029-export-every-list-view-to-csv-or-xlsx-with-openspout-open-to-all-signed-in-staff.md). "Export the complete database" is not the same thing and needs its own card. |
| Data ownership, hosting term, backup and restore | [`hosting-plan.md`](../hosting-plan.md), [`deployment-plan.md`](../deployment-plan.md), [off-site-encrypted-backups](off-site-encrypted-backups.md), [ADR 0010](../../adr/0010-build-in-ci-and-deploy-by-image-pull.md), [ADR 0017](../../adr/0017-host-production-on-gandicloud-vps-in-france.md). Mostly a question to answer in writing, not code. |
| WhatsApp schedule sharing | [whatsapp-roster-sending](whatsapp-roster-sending.md), deferred behind a trigger |
| **Volunteer hours** | **Not stored.** `App\Enum\ActivityDuration` is half day / full day / other. Recording hours changes the reporting model, not just a form. |
| **Attendance, assigned vs. actual** | **Not modelled.** An activity records who was there, not who was expected. |
| **Beneficiaries reached, impact indicators** | **No entity exists.** |
| **Roles beyond Admin / VM; volunteers signing in** | **Not built.** Two roles today, `ROLE_USER` and `ROLE_ADMIN`, and no volunteer has ever had an account. This is the largest single item in the recap: volunteer self-service means authentication for people outside the office, per-record authorization, and an account lifecycle. Related: [task-assignment-handoffs](task-assignment-handoffs.md). |

**Conflicts to resolve during the split, not after:**

- The recap implies mandatory profile fields. ADR 0032 made them optional
  on purpose — the roster archive has no such data and existing records
  cannot be truthfully backfilled
  ([ADR 0012](../../adr/0012-seed-fixtures-from-the-real-whatsapp-roster-archive.md),
  [ADR 0014](../../adr/0014-make-a-volunteers-last-name-optional.md)).
- "Volunteers should only access their own information" and "export the
  complete database when required" pull in opposite directions from ADR
  0029, which opens every export to any signed-in staff member on the
  grounds that only staff sign in. That premise ends the day a volunteer
  gets an account.
