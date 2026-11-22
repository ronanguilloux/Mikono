# Brainstorm — Impact indicator shortlist

**Date:** 2026-11-21
**Author:** <ronan.guilloux@gmail.com>
**Related:** [`CLAUDE.md`](../../CLAUDE.md),
[ADR 0047](../adr/0047-count-achievements-with-an-optional-indicator-and-quantity.md),
[ADR 0038](../adr/0038-record-achievements-on-a-volunteers-stay.md),
[ADR 0034](../adr/0034-comply-with-kenyas-data-protection-act-2019.md),
[ADR 0012](../adr/0012-seed-fixtures-from-the-real-whatsapp-roster-archive.md),
[brainstorm 14](14-dataviz-for-vm-and-management.md),
[`ucesco-meeting-requirements-raw.md`](../project/backlog/ucesco-meeting-requirements-raw.md),
[`dataviz-first-charts.md`](../project/backlog/dataviz-first-charts.md),
[`docs/adr/`](../adr/)

---

## Primary audience

- **The Volunteer Manager (VM)**, who records achievements and needs to
  know which indicator to pick and how to count. The counting rules below
  are written for them.
- **UCESCO top management**, who confirm or rename the list and decide
  whether they also want enrolment counts.
- **A future contributor** building a chart or report on these numbers
  (the [first-charts card](../project/backlog/dataviz-first-charts.md)),
  who needs to know what a total means before labelling it.

Source: working session, 2026-11-21.

## Desired impact

- The VM and management confirm, rename or drop each of the 19 indicators
  **before any achievement counts one**, while a rename is still free.
- Every total the app shows can be read without a footnote: no event
  counted once per volunteer, no attendance passed off as a head count,
  no number compared with ucesco.org's counters as if it measured the
  same thing.
- The list stays short. A new kind of work gets a description that fits
  an existing indicator, not a near-duplicate.

In hindsight, success means a donor report can quote an indicator total
straight from the achievements export, with the right unit
("pupil-sessions", not "pupils"), and nobody has to correct it by hand.

## Where things stand (2026-11-21)

[ADR 0047](../adr/0047-count-achievements-with-an-optional-indicator-and-quantity.md)
lets an achievement carry one `Indicator` and one positive integer
`quantity`, always as a pair. The list is unseeded: the VM builds it.
Brainstorm 14 and the meeting recap left "which indicators" open for
UCESCO.

On 2026-11-21 a starter list of 19 was proposed and loaded into
production with one `INSERT OR IGNORE` through
`bin/console dbal:run-sql`. That is data entry, the same as the VM typing them at
`/indicators`, not seeding: there is no migration and no fixture, both of
which ADR 0047 rules out. No achievement counts any of them yet.

### What the list was drawn from

- **ucesco.org.** The home page, `/impact`, `/projects`, and the program
  pages for medical camps, women empowerment, education, youth
  development and food security, plus `/volunteer`. The site's headline
  counts are of patients treated, women empowered, students supported,
  youth mentored, families supported, kitchen gardens and livestock. No
  figure from the site is quoted here.
- **The app's reference data** in `docs/fixtures/rosters.yaml`: 14
  projects, 40 programs and their activity types. Most programs sit on a
  hub project (UCESCO HQ, Mombasa Office). An `Achievement` records a stay
  and a project, but no program and no activity type, so on a hub project
  the indicator is the only thing that tells a health outcome from a
  teaching one.

## The 19, as entered

The description defines the unit. The last column is guidance on where
each one fits, not a rule the app enforces.

| # | Name | Description | Fits |
| --- | --- | --- | --- |
| 1 | Patients seen | One person examined, screened or treated at a clinic, medical camp or dental outreach. | Clinic support. ACK clinic, Beyond Zero clinic, Local Medical & Clinical, Dental Outreach, Healthcare Assistant (Mombasa), Medical & Clinical Support (Uganda) |
| 2 | People reached by a health talk | One attendee at a health, hygiene, HIV-awareness or oral-health session. | Health outreach. HIV & AIDS Awareness, Dental Outreach, CHPs Assistant |
| 3 | Households visited | One home visited by an outreach or home-visit team. | Home visit. CHPs Assistant |
| 4 | Pupils taught | One child in a lesson or tutoring group a volunteer led or co-led, counted once per session. | School support, Language tuition. Peggy Lucas, Bright Achievers, Mt Hermon, Teaching & School Support, Chinese, French and Italian |
| 5 | Computer learners trained | One learner in a computer or ICT session, counted once per session. | Computer tuition. Computer Tuition, ICT & Web Design |
| 6 | Children cared for | One young child in an early-childhood, daycare or orphanage session. | Childcare, Orphanage support. Dreams of Hope, Minto, Childcare & ECD |
| 7 | People counselled | One person in a one-to-one or group counselling or life-skills session. | Counselling. Mental Health & Psychosocial, Life Skills |
| 8 | Women trained | One woman attending a skills, business or financial-literacy session, counted once per session. | Women's empowerment, Vocational training support. MVETI, Micro-Finance, Women's Entrepreneurial Empowerment, Tailoring |
| 9 | Trainees certified | One trainee who completed a course or received a certificate, recorded on the day the course ends. | Vocational training support, Computer tuition. MVETI, Tailoring, Computer Tuition |
| 10 | Businesses supported | One micro-business started or mentored. | Women's empowerment. Micro-Finance, Women's Entrepreneurial Empowerment |
| 11 | Young people coached | One participant in a sports, swim, yoga, dance, music or art session. | Sports, Yoga & fitness, Creative arts. Toi School Field |
| 12 | Events organised | One tournament, performance, donation drive or community day. | Sports, Creative arts, Community service |
| 13 | Meals served | One meal prepared or served to a beneficiary. | Cooking & nutrition, Community service |
| 14 | Households given supplies | One family that received food, clothing or a donation pack. | Community service |
| 15 | Kitchen gardens set up | One kitchen garden completed. | Farming. Climate-Smart Agriculture (Samburu), Eco-farm |
| 16 | Trees or mangroves planted | One seedling planted. | Environmental conservation, Farming |
| 17 | Bags of waste collected | One full bag collected at a clean-up. Bags, not kilograms: there is no scale on a beach. | Beach clean-up. Mtwapa Beach |
| 18 | Rooms built or renovated | One classroom or room built, repaired or repainted to completion. | Construction & renovation |
| 19 | Content pieces published | One post, video or article published for UCESCO. | Media & communications |

## Counting rules for the VM

These are the point of this file. The app can't enforce most of them, so
they hold only if the person entering achievements follows them.

- **One achievement per event, on the lead volunteer's stay.** An
  achievement belongs to one stay. If four volunteers run a camp day and
  each records "Patients seen", the day's patients are counted four
  times. That is the trap ADR 0047 rejected for activities; it comes back
  if every volunteer records the same outcome.
- **Totals are attendances, not unique people.** Twenty pupils taught on
  five days count as one hundred. The app keeps no beneficiary
  identities and shouldn't
  ([ADR 0034](../adr/0034-comply-with-kenyas-data-protection-act-2019.md);
  most beneficiaries are children). A report shows "pupil-sessions",
  never "pupils".
- **One indicator per achievement.** A camp day with patients seen and a
  health talk is two achievements, one per indicator.
- **App totals are not ucesco.org's counters.** The site counts
  enrolments across all of UCESCO's programs. Achievements cover only the
  part volunteers did. For completions, use "Trainees certified", not
  "Women trained".
- **Don't make an indicator of what activities already give.** Volunteers
  engaged, volunteer days and activities logged are derived from
  activities. Hours aren't stored. A quantity is a positive integer, so
  percentages, money and rates can't be recorded.
- **Rename, don't duplicate.** Once an achievement counts an indicator,
  correct its name or description in place rather than adding a
  near-duplicate beside it.

## The "Options Not Taken"

- **Seeding the list by migration or fixture.** ADR 0047 rules both out:
  UCESCO hasn't named its indicators, so a migration would make a
  proposal look like a decision, and the dev dataset is never generated
  ([ADR 0012](../adr/0012-seed-fixtures-from-the-real-whatsapp-roster-archive.md)).
  Entering the rows in production keeps them editable at `/indicators`
  like anything else the VM types.
- **Kilograms of waste.** The volunteers on Mtwapa Beach have no scale.
  A unit nobody can measure gets estimated, and estimates can't be summed
  honestly. Bags can be counted on the spot.
- **One indicator per program.** Forty programs would give a list of
  near-duplicates ("Pupils taught at Peggy Lucas", "Pupils taught in
  French") that can't be summed together. The description defines the
  unit instead, and one indicator serves every program doing that work.
- **Unique-beneficiary counts.** Counting distinct people means keeping
  who they are, and most beneficiaries are children. That is new personal
  data under ADR 0034, needing its own purpose and retention, for a
  number the attendance totals already approximate.

## Constraints

- **The repo is public.** No figures from ucesco.org, no real names, no
  real totals in this file. The site's pages and kinds of counts are
  named only.
- **An achievement knows a stay and a project, nothing finer.** On a hub
  project the indicator is the only signal of what kind of work it was,
  so the list has to separate health, teaching, care, training and
  environment at its own level.
- **Positive integer, as a pair**
  ([ADR 0047](../adr/0047-count-achievements-with-an-optional-indicator-and-quantity.md)).
  Every indicator has to be a count of whole things.
- **The rename window is now.** Renaming or deleting an indicator costs
  nothing while no achievement counts it. Afterwards the delete guard
  blocks removal, and a rename changes the label on every past total.
- **Unknowns only UCESCO can settle:**
  - Confirm or rename the 19.
  - Whether management wants enrolment counts ("Trainees enrolled")
    alongside completions.
  - Whether the counting rules above should appear on the achievement
    form as help text.
