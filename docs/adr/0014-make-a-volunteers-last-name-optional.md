# 0014. Make a volunteer's last name optional

Date: 2026-09-01

## Status

Accepted

## Context

`Volunteer::$lastName` is a non-nullable `string` carrying
`#[Assert\NotBlank]`. Nothing motivated that beyond symmetry with
`$firstName`: a person has two names, so the form asks for two.

Seeding the fixtures from the real archive
([ADR 0012](0012-seed-fixtures-from-the-real-whatsapp-roster-archive.md))
made it a problem. The VM's rosters name volunteers the way she talks
about them:

```
Peggy Lucas school
-Daphne
-Marco
```

First names only, for every volunteer, in every one of the twenty-two
roster messages in the archive. Nowhere does a surname appear. So a
required `lastName` leaves exactly three options: invent one, leave the
column blank and ship a demo where saving an edited volunteer fails
validation, or accept that the field is optional. The first is the
thing ADR 0012 exists to forbid.

The wider point is the same one [ADR 0013](0013-record-every-escort-on-an-activity.md)
made about escorts: the constraint was inferred from a form layout, not
from how UCESCO actually identifies its volunteers. Volunteers arrive
for a few weeks, are known by first name, and leave. A surname is
useful when the VM has it — two Daphnes will eventually turn up — but it
is not something she always has at the moment she records someone.

## Decision

**`Volunteer::$lastName` becomes nullable and optional.**

- The column is `nullable: true`, the property is `?string`, and
  `#[Assert\NotBlank]` is removed from it. `$firstName` stays required:
  a volunteer with no name at all is not a record worth keeping.
- `VolunteerFormType` marks the field `required: false` and drops its
  `'empty_data' => ''`, so a blank submission stores `null` rather than
  an empty string — one representation of "not known", not two.
- `getFullName()` already returns `trim("{$firstName} {$lastName}")`,
  so a volunteer with no surname renders as "Daphne" everywhere without
  a single template change.
- The Volunteers index keeps sorting on `lastName` then `firstName`;
  rows without a surname simply fall together and are ordered by first
  name, which is how the rosters read anyway.

## Consequences

- **Positive:** the fixtures can record the fifteen real volunteers in
  the archive exactly as the rosters name them, and every one of them
  stays editable through the form.
- **Positive:** the form stops demanding information the VM often does
  not have at the moment she is entering someone, which is the moment
  that matters for whether the app gets used.
- **Negative / trade-offs:** two volunteers with the same first name are
  now indistinguishable in every picker, since `getFullName()` is what
  the activity forms show. The archive has a near-miss already ("Ellen"
  in early August, "Hellen" in September, recorded as written rather
  than merged). If this bites, the answer is a disambiguating hint in
  the picker, not a re-required surname.
- **Negative / trade-offs:** `null` and `''` both have to be treated as
  "no surname" for as long as any pre-existing row holds an empty
  string. The migration normalises existing empty strings to `null` so
  that stays a one-off rather than a permanent two-case check.
- **Reversibility:** cheap while the data is small — re-adding the
  constraint means filling in the missing surnames first, which is a
  data problem rather than a code one.

## Alternatives considered

### 1. Keep the constraint and seed an empty last name

**Rejected.** Display would have been fine — `getFullName()` trims — but
every seeded volunteer would then fail validation the first time the VM
opened one and pressed save. Shipping a demo dataset whose rows cannot
be re-saved undermines the point of demoing with real data.

### 2. Keep the constraint and ask Edna for the real surnames

**Rejected as a blocker, kept as a follow-up.** It is the right thing to
want, but it makes the fixtures wait on a WhatsApp reply, and it does
not change the underlying finding: at the moment a volunteer is first
recorded, the surname often is not known. Collecting surnames later
fills rows in; it does not justify a constraint that blocks recording
someone today.

### 3. Store the roster name in `firstName` and drop `lastName` entirely

**Rejected.** UCESCO does hold full names for volunteers elsewhere
(applications, the volunteer agreements), so the field has a real use —
it is only the *requirement* that is wrong. Removing the column would
throw away information the VM can supply, to fix a problem that
loosening the constraint already fixes.
