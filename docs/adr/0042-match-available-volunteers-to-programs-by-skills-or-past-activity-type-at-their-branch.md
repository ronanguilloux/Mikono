# 0042. Match available volunteers to programs by skills or past activity type at their branch

Date: 2026-10-03

## Status

Accepted

## Context

The Volunteer Manager plans who goes on which program. Her question
covers every program at once: which programs are short of people, and
who, among the volunteers who will be there, could take them on.

Three forces shape the answer:

- **Availability is set by stays and program dates.** A volunteer is at
  one branch for the length of a stay
  ([ADR 0026](0026-attach-volunteers-to-branches-through-dated-stays-and-derive-active-from-them.md)),
  and a program belongs to a project at a branch and may have start and
  end dates
  ([ADR 0030](0030-insert-programs-between-projects-and-activities.md)).
  A volunteer at Mombasa, or one whose stay ended last year, is no
  candidate for a Kibera program running this month, however well their
  skills fit.
- **Skill profiles are incomplete.** Skills are picked by hand from a
  global list
  ([ADR 0036](0036-manage-skills-as-a-seeded-list-shared-by-volunteers-and-programs.md)),
  so a volunteer who has run a type of activity many times may hold no
  skill on their profile. The activity log is the record of what each
  volunteer has done, and activity types are one global list shared by
  every program (ADR 0030).
- **The log records no outcome.** `Activity` has no field saying whether
  an activity went well, so the log shows experience, not quality.

The narrative behind this screen is in
[`docs/brainstorm/13-matches-planning-screen.md`](../brainstorm/13-matches-planning-screen.md).

## Decision

**`/matches` lists, program by program, the volunteers whose stay at the
program's branch overlaps its dates and who either hold one of its skills
or have logged an activity of a type it offers, ranked by a fixed
lexicographic order with no composite score.**

- **Screen.** A top-level "Matches" nav entry opens `/matches`
  (`MatchController`, routes `match_index` and `match_export`). It groups
  candidates by program and filters by `branch`, `program` and `who`
  (`present` or `upcoming`; both by default). Parameters are read through
  `$request->query->all()` guarded by `is_scalar()`, and any malformed
  value falls back to no filter
  ([ADR 0023](0023-degrade-malformed-query-input-to-a-default.md)). The
  filter controls re-render the same route, so they carry
  `data-turbo-action="replace"`
  ([ADR 0040](0040-keep-the-readers-scroll-position-when-a-control-re-renders-the-same-page.md)).
  The logic lives in `App\Report\ProgramMatchFinder`, which returns
  readonly `ProgramMatches` and `VolunteerMatch` value objects.
- **One page, not one per program.** `/programs/{id}/matches` and its
  export no longer exist. The Programs list's "Matches" row action links
  to `/matches?program={id}`.
- **Availability.** A volunteer counts for a program only through a stay
  of theirs that:
  - is at the program's branch;
  - has not ended (`endDate >= today`);
  - overlaps the program's dates, a missing program bound being open, as
    in `Program::covers()`.

  If several stays qualify, the earliest is used. Programs that have
  ended, and programs of inactive projects, are left out.
- **`who` applies to the matching stay, not to the volunteer's overall
  status.** Present means that stay covers today; upcoming means it
  starts after today. The status pill on each row is the matching stay's
  too. A volunteer present at Mombasa with an upcoming stay at Kibera is
  an upcoming candidate for a Kibera program.
- **Two match bases:**
  - *Skills:* the volunteer holds at least one of the program's skills.
  - *Experience:* the volunteer has logged an activity of a type the
    program offers, in any program at any branch.

  A candidate qualifies on either basis or both. **Trap:** a logged
  activity is taken as evidence that it went fine, because `Activity` has
  no outcome field. A future outcome field must decide whether it feeds
  this rule.
- **Candidate order**, applied key by key:
  1. matched skills, descending;
  2. experience count (activities of the program's offered types,
     anywhere), descending;
  3. present before upcoming;
  4. last name;
  5. first name.

  **Trap:** there is no composite score. Experience-only candidates
  therefore sit below every skill match. The count of activities in this
  program is shown on the row, never ranked.
- **Program order:**
  1. Programs that have started (no start date, or one on or before
     today) come first. Among them, never-run programs lead, then the
     longest since their latest activity. That latest date includes
     planned future activities, so a planned activity counts as covered.
  2. Programs not started yet come last, soonest start first.
  3. Name breaks any tie.
- **What each block shows.**
  - *Program header:* needed skills; last activity, start and end dates;
    uncovered skills (needed skills that no candidate holds, a recruitment
    gap); candidate count.
  - *Candidate row:* status pill; stay dates and days left; matched
    skills as "n of m"; matching skills; missing skills; experience by
    activity type, with its count in this program and its last date.
  - *Assign action:* each row links to `/activities/new` with the
    program, the volunteer and a date already filled in. The date is the
    first day from today that both the stay and the program cover, so an
    upcoming volunteer or a program not started yet still gets a date
    that saves. More volunteers are added through the form's search.
    Saving a planned activity makes the program count as covered here.
- **Experience-only rows** carry a "By experience" badge on Matched, and
  a "Not on profile" badge on missing skills that links to the
  volunteer's edit page. Nothing changes on the profile automatically; the
  VM decides. Both are DataTable `badges`, never text inside `cells`.
- **No placeholder where a basis is absent.** On a program with no skills
  the Matched cell is empty, not "—", so only "By experience" shows. A
  skill match with no experience has an empty Experience cell with a
  "New to it" badge. The export keeps those cells empty, and its Basis
  column says why each row is there.
- **Programs without candidates.** A program with skills but no
  candidate keeps its block, since an empty block is the recruiting
  signal. A program with no skills can still have experience candidates.
  A program with neither skills nor candidates gets no block and appears
  only in the no-skills list below. That is most of the list, so the list
  marks the exceptions: "also shown above".
- **What to tick for more matches.** Below the program blocks, always
  visible and never inside a note, are up to three lists. Each one follows
  the filters and links to the edit page where the gap is fixed:
  - available volunteers with no skill on their profile, meaning anyone
    with a current or upcoming stay. Each volunteer shows every such stay,
    earliest first, as its branch plus "here until" or "arrives". The list
    stays on screen when empty.
    These come from `ProgramMatchFinder::findStaysWithoutSkills()`: the
    same not-ended stays `find()` draws from, narrowed by Who and by the
    branch, or by the chosen program's branch;
  - open programs with no recommended skills, hidden when empty;
  - open programs with only one recommended skill, hidden when empty.
- **The page explains its rule in plain words:** a one-line lead, then
  two native `<details>`. "How matches are found" states the rule, and
  "How to get more matches" says what each list above is for. **That text
  is part of this decision.** A change to `ProgramMatchFinder` changes the
  text in the same commit.
- **Export**
  ([ADR 0029](0029-export-every-list-view-to-csv-or-xlsx-with-openspout-open-to-all-signed-in-staff.md)):
  flat, one row per program and volunteer pair, with columns Branch,
  Project, Program, Name, Status, Stay, Matched, Basis (`Skills`,
  `Skills + experience` or `Experience`), Matching skills, Missing
  skills and Experience. It is fed by the same finder call as the page,
  so the filters reach the file and there is no second query.
- **Pairing runs in PHP.** The finder makes four queries: open programs;
  not-ended stays with their volunteers and skills; activity counts
  grouped by volunteer, type and program; and the latest activity date
  per program. It pairs them in memory, a few hundred program and
  volunteer combinations at UCESCO's scale. The page runs the stays query
  a second time for its list of volunteers with no skills. This mirrors
  `Volunteer::getStatus()`, which keeps its rule in PHP.
- **Personal data**
  ([ADR 0034](0034-comply-with-kenyas-data-protection-act-2019.md)): no
  new field. Names, skills, stay dates and activity history are already
  listed and exported elsewhere, under their stated purposes. Gender is
  never shown, here or in the export
  ([ADR 0037](0037-record-a-volunteers-gender-for-accommodation-pairing.md)).

## Consequences

- **Positive:** one rule, aware of branch and dates, answers the planning
  question for every program on one screen. Experienced volunteers whose
  profile lacks the skill are found, and the "Not on profile" badge nudges
  the VM to complete their profile. Uncovered skills and empty blocks show
  where recruiting is needed.
- **Negative / trade-offs:** pairing in PHP has a ceiling. If `/usage`
  timings show the screen slowing, the pairing moves into DQL. "Logged
  means it went fine" is an assumption: a volunteer who ran sessions badly
  ranks like one who ran them well. The on-page explanation must be kept
  in step with the code by hand.
- **Reversibility:** cheap. The screen adds no table or column, so the
  finder, controller and template can be reworked or removed without a
  migration. A per-program view already exists as `?program=`.

## Alternatives considered

### 1. A flat list of program and volunteer pairs

**Rejected.** The VM plans program by program. A flat list repeats each
program's needed skills and last activity on every row, and drops the
programs with no candidate, which are the recruitment signal. The export
is flat because a spreadsheet filters better that way.

### 2. Grouped by volunteer

**Rejected.** It answers "where can this volunteer go", not "which
programs are short of people". It has no place for uncovered skills or for
programs nobody can take, and it loses the program order that puts the
programs longest without an activity first.

### 3. A date picker or a next-N-days window

**Rejected.** Stays and program dates already say who is available when,
so the overlap rule needs no parameter. A window adds a control whose
right value nobody knows, and it would hide an upcoming volunteer who
arrives a day past its edge. The `who` filter covers "who is here now".

### 4. Keeping `/programs/{id}/matches` beside `/matches`

**Rejected.** Two pages for one question drift apart, and the
per-program page matched on skills alone, ignoring branch and dates, so it
would contradict `/matches` for the same program. `/matches?program={id}`
gives the one-program view under the one rule.

### 5. A weighted composite score

**Rejected.** Any weights would be arbitrary, could lift an
experience-only candidate above a skill match, and would not fit the
page's plain-words explanation. The lexicographic order explains itself
in one sentence, and each row shows the facts that placed it.

### 6. Experience limited to the same program

**Rejected.** Most programs have few logged activities, and a new
program has none, so this would find almost nobody beyond the people the
VM already knows from that program. Someone who has given computer
tuition at another program or branch has the same experience. The count
in this program is still shown on the row.

### 7. Experience as any activity in the program, regardless of type

**Rejected.** It measures having been on the program, not having done
what it offers, and like alternative 6 it finds nobody for a new program.
The activity type is what records what a volunteer did, and it is the
one unit shared by every program.
