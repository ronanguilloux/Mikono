# Brainstorm — Matches planning screen

**Date:** 2026-10-03
**Author:** <ronan.guilloux@gmail.com>
**Related:** [`CLAUDE.md`](../../CLAUDE.md),
[ADR 0036](../adr/0036-manage-skills-as-a-seeded-list-shared-by-volunteers-and-programs.md),
[ADR 0026](../adr/0026-attach-volunteers-to-branches-through-dated-stays-and-derive-active-from-them.md),
[ADR 0030](../adr/0030-insert-programs-between-projects-and-activities.md),
[ADR 0029](../adr/0029-export-every-list-view-to-csv-or-xlsx-with-openspout-open-to-all-signed-in-staff.md),
[ADR 0034](../adr/0034-comply-with-kenyas-data-protection-act-2019.md),
[ADR 0042](../adr/0042-match-available-volunteers-to-programs-by-skills-or-past-activity-type-at-their-branch.md),
[`docs/adr/`](../adr/)

---

## Primary audience

The Volunteer Manager (VM) at UCESCO, when she plans and sets up the next
activities. This is a planning screen, not a report on the past: Reports
covers what already happened. Source: Ronan, working session, 2026-10-03.

## Desired impact

The VM can answer two questions on one screen: "which programs need people
now?" and "who can I put there?". Each answer shows the data behind it, and
she still makes the call herself.

In hindsight, success looks like this:

- `/matches` lists nobody whose matching stay has ended, is at another
  branch, or misses the program's dates.
- Every candidate row says why the volunteer is there: which skills match
  and which are missing, or which past activities count as experience.
- A program with a gap says so in its header, by naming the needed skills
  that no available volunteer holds.
- `/programs/{id}/matches` is gone. The Programs "Matches" action opens
  `/matches?program={id}`.
- The page writes nothing. If the VM agrees a volunteer has a skill, she
  ticks it herself on the volunteer's edit page.

## Where things stand (2026-10-03)

`/programs/{id}/matches`
([ADR 0036](../adr/0036-manage-skills-as-a-seeded-list-shared-by-volunteers-and-programs.md))
shows one program at a time. It lists the volunteers holding **any** of the
program's skills, ranked by how many they hold. It has two gaps:

- **It ignores branch and dates.** It lists volunteers who left months ago,
  and volunteers staying at another branch.
- **It only knows the skills ticked on a profile.** Ronan raised the case it
  misses. Volunteers are sometimes put on an activity without holding the
  program's skill, and it goes fine. That past experience is a real match,
  ranked below a skill match.

To plan with it, the VM has to open each program in turn and work out by
hand who is actually around.

## The shape this landed on

Decided with Ronan on 2026-10-03:

1. **One page, `/matches`, with its own "Matches" nav entry.** It is grouped
   by program and filtered by branch, program and "Who" (Present, Upcoming,
   or both, the default). The per-program page is folded into it.
2. **Stays decide availability, so there is no date picker.** A volunteer
   counts for a program only through one of their stays that:
   - is at the program's branch;
   - has not ended;
   - overlaps the program's dates, an open bound being open.

   Ended programs, and programs of inactive projects, are left out. "Who"
   applies to the matching stay, not to the volunteer's overall status. A
   volunteer present at one branch with an upcoming stay at another is
   Upcoming for the second branch's programs.
3. **Two match bases.**
   - *Skills:* the volunteer holds at least one of the program's recommended
     skills.
   - *Experience:* the volunteer has logged an activity of a type the program
     offers, in any program at any branch. `Activity` has no outcome field,
     so a logged activity is taken to mean it went fine. The page says so.
4. **A plain lexicographic order, with no score.**
   - Candidates: matched skills (most first), then experience activities of
     the program's types (most first), then present before upcoming, then
     name. Experience-only candidates therefore always sit below every skill
     match.
   - Programs: the longest since their last activity first, with never-run
     programs at the top. Programs that haven't started yet come last,
     soonest first.
5. **Each data point sits where its decision is made.**
   - Program header: the needed skills; the last activity, or the start and
     end dates; "Nobody available for: X", the recruitment gap; the
     candidate count.
   - Candidate row: the matching stay's status, its dates and days left;
     "2 of 3" matched; the matching and the missing skills; experience by
     type, with "N in this program" and the last date.
   - Experience-only rows: a "By experience" badge, and a quiet "Not on
     profile" hint linking to the volunteer's edit page so the VM can tick
     the skill. Nothing is changed automatically.
6. **The page explains its reasoning in plain words.** A one-line lead,
   plus a native `<details>` block, "How matches are found". That text is
   part of the rule: change one, change both.

## The "Options Not Taken"

- **A flat list of program × volunteer pairs.** It is the simplest to build,
  but the VM plans one program at a time. In a flat list the program header
  (needed skills, last activity, the gap) would either repeat on every row
  or disappear. This shape is kept for the export only, where a spreadsheet
  wants one row per pair.
- **Grouping by volunteer.** That answers "where do I put this arriving
  volunteer?", which is a different question from "which programs need
  people now?". Deferred: a "volunteers with no matching program" panel
  could come later from the same data.
- **A date picker ("available on date X") or a next-N-days window.** Either
  one adds a control the VM has to set. Stays are dated
  ([ADR 0026](../adr/0026-attach-volunteers-to-branches-through-dated-stays-and-derive-active-from-them.md))
  and programs carry their own optional dates
  ([ADR 0030](../adr/0030-insert-programs-between-projects-and-activities.md)),
  so where the two overlap already says who is available for what, with no
  control at all.
- **Keeping `/programs/{id}/matches` next to `/matches`.** That means two
  matching rules to keep in step. The old one already disagrees with the new
  one on branch and dates.
- **A weighted composite score.** A single number hides the reasoning the VM
  needs to see to make the call herself. ADR 0036 already made the order the
  answer. A lexicographic order keeps each step of it readable on the row.
- **Experience from the same program only.** That is narrower than how
  UCESCO actually reuses people across projects and branches. Activity types
  are one global list (ADR 0030), so the type is what carries over from one
  program to the next.
- **Experience from any activity in the program, whatever its type.** That
  would count one-off help as experience.
- **Requiring all of the program's skills.** ADR 0036 already rejected
  this: there are too few volunteers per branch, so most programs would show
  nobody.

## Constraints

- **No new personal data
  ([ADR 0034](../adr/0034-comply-with-kenyas-data-protection-act-2019.md)).**
  The page shows names, skills, stay dates and activity history, which are
  already listed and exported elsewhere. Gender is never shown
  ([ADR 0037](../adr/0037-record-a-volunteers-gender-for-accommodation-pairing.md)).
- **Every list exports
  ([ADR 0029](../adr/0029-export-every-list-view-to-csv-or-xlsx-with-openspout-open-to-all-signed-in-staff.md)).**
  The export is flat, one row per program × volunteer pair, and it is fed by
  the same finder call as the page, never a second query.
- **No entity change, no migration.** Everything is read from stays,
  programs, skills and activities as they are today.
- **No outcome on `Activity`.** Experience can only mean "logged". The page
  states that assumption rather than hiding it.
- **"Today" is a Nairobi calendar day**
  ([ADR 0024](../adr/0024-treat-dates-as-calendar-days-in-nairobi-time.md)).
  "Has not ended", Present and Upcoming all use it.
- **Deferred:** grouping by volunteer, and the "volunteers with no matching
  program" panel.
