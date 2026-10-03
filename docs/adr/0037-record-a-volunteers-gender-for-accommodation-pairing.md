# 0037. Record a volunteer's gender for accommodation pairing

Date: 2026-09-28

## Status

Accepted

## Context

UCESCO houses some of its volunteers in shared accommodation, and the
Volunteer Manager pairs them into rooms. To do that she needs to know each
volunteer's gender. Without a field she guesses from a first name or a
photo, or keeps the answer in `notes`.

Sex is sensitive personal data under Kenya's Data Protection Act 2019
(s.2).
[ADR 0034](0034-comply-with-kenyas-data-protection-act-2019.md) admits a
dedicated field for a sensitive category only under its own ADR, naming
its s.45 ground and the consent UCESCO records for it (rule 2). It also
keeps sensitive categories out of free text, so `notes` is not an option.
Production runs in South Africa
([ADR 0035](0035-host-production-on-a-compute-engine-e2-small-in-johannesburg.md)),
so any sensitive field also needs each volunteer's consent under s.49(1).

## Decision

**A volunteer has an optional gender, drawn from a fixed four-value enum,
held only to pair volunteers for shared accommodation, shown only on the
volunteer's profile page and exported only in the admin whole-database
zip.**

- **Shape.** `Volunteer::$gender` is a nullable `App\Enum\Gender` (`female`,
  `male`, `other`, `prefer_not_to_say`), stored as a plain string column
  like the app's other enums. The volunteer form offers it as an optional
  select whose help text reads "Used only to pair volunteers for shared
  accommodation."
- **Null is not "Prefer not to say".** Null means nobody asked or nobody
  recorded the answer; `prefer_not_to_say` means the volunteer was asked
  and declined. Don't collapse them.
- **"Other" and "Prefer not to say" exist so the VM never has to guess.**
  Every answer a volunteer can give has a value.
- **A fixed enum, not an entity.** Unlike `Skill` and `Branch`, the list
  never changes, so it has no table and no CRUD screen.
- **Purpose: accommodation pairing only.** Nothing else reads the field.
- **Ground.** The obligations limb of s.45(c): UCESCO's obligations
  towards the volunteers it places and houses. Not vital interests, which
  a room allocation does not engage, and not s.45(a), which ADR 0034
  rejects for UCESCO.
- **Consent.** Because the field is processed in South Africa, UCESCO
  records each volunteer's s.49(1) consent the same way it does for
  emergency contacts: at onboarding, alongside the privacy notice (ADR
  0034, rules 3 and 4). A withdrawal empties the field.
- **Retention.** The same as the volunteer row (ADR 0034, rule 6): it goes
  when the volunteer is anonymised.
- **Where it appears.** The volunteer profile page (`[data-profile]`) and
  the edit form. The one bulk copy that carries it is the admin-only
  whole-database zip
  ([ADR 0039](0039-export-the-whole-database-as-an-admin-only-zip-of-per-table-csvs.md)),
  a complete copy of UCESCO's records rather than a staff-facing view.
  Deliberately excluded:
  - the volunteer list, as a column or a filter;
  - the per-list CSV and `.xlsx` exports
    ([ADR 0029](0029-export-every-list-view-to-csv-or-xlsx-with-openspout-open-to-all-signed-in-staff.md));
  - `/reports/volunteers`;
  - the dev fixtures (`VolunteerFactory` leaves it null);
  - `Volunteer::isProfileIncomplete()`, so a missing gender never nudges
    staff to ask for it.

  Adding it to any of these widens the processing beyond pairing, and
  needs this ADR rewritten first.

## Consequences

- **Positive:**
  - The VM pairs rooms from a recorded answer instead of a guess.
  - The field is filterable in code if a pairing screen ever needs it,
    which free text would not be.
  - Keeping it off lists, per-list exports and reports keeps the
    sensitive value to the one screen that needs it, plus the admin-only
    full copy.
- **Negative / trade-offs:**
  - One more s.49(1) consent for UCESCO staff to gather and keep proof of
    at onboarding, and the privacy notice must mention the field.
  - Staff who want gender in a list or an export can't have it without an
    ADR change.
- **Reversibility:** cheap. Dropping the column and the enum removes it;
  data already collected is removed with them.

## Alternatives considered

### 1. A free-text field

**Rejected.** Free text drifts into the kind of detail `notes` must not
hold (ADR 0034, rule 2), and "F", "female" and "woman" can't be filtered
or paired.

### 2. Female and Male only

**Rejected.** A volunteer who is neither, or who declines to say, would
force the VM to guess or leave the field empty, and an empty field would
no longer tell "not asked" from "declined".

### 3. A CRUD-managed entity, like `Skill`

**Rejected.** The list never changes, so a table, a seeding migration and
an admin screen would cost code for nothing.
