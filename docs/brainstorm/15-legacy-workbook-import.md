# Brainstorm — Import the legacy volunteer workbook into production

**Date:** 2026-10-17
**Author:** <ronan.guilloux@gmail.com>
**Related:** [`CLAUDE.md`](../../CLAUDE.md),
[`done.md`, 2026-10-17](../project/done.md),
[ADR 0029](../adr/0029-export-every-list-view-to-csv-or-xlsx-with-openspout-open-to-all-signed-in-staff.md),
[ADR 0034](../adr/0034-comply-with-kenyas-data-protection-act-2019.md),
[ADR 0044](../adr/0044-allow-syncing-production-database-to-local-development.md),
[`deployment-plan.md`](../project/deployment-plan.md)

## Primary audience

Future-self and any agent resuming the import mid-way: the step tracker is
the backlog card; this file holds the reasoning and the detailed plan.

## Desired impact

Every volunteer, stay, roster line and ACK clinic attendance day from
UCESCO's pre-Mikono Google Sheet lands in production — as directed by
UCESCO top management, with all personal data the workbook holds — without
breaking an invariant of the app (derived status, non-overlapping stays,
branch on every stay, activity/stay/project branch rule) and without
overwriting anything the VM entered in production.

## The "Options Not Taken"

- **Run the importer on the production server.** Rejected: the workbook
  would have to travel to the server, and the run would happen live. A
  local import into a fresh prod copy is verified in the app before the
  file swap, and the swap path is the restore drill already proven on this
  server.
- **Import from the CSV extracts.** Rejected: they were a partial extract
  (3 of 7 tabs) and turned date cells into locale-dependent text. The
  `.xlsx` read with OpenSpout gives real date cells.
- **One stay per ACK attendance day.** Rejected: dozens of one-day stays
  per volunteer. Days are grouped into runs separated by more than a
  working week; weekends sit inside a run.
- **Estimated stays from tab year + duration** for people with no dates.
  Rejected: invented dates would feed every report and capacity figure.
  They become a manual exceptions pass, the duration appended to notes.

## Constraints

- Weekend freeze (2026-10-17/18): the VM doesn't use the app, so the file
  swap loses nothing — checked by a per-table fingerprint before the swap.
- The schema change (new volunteer columns) must be deployed **before**
  the sync, or the dev entrypoint migrates the prod copy past what prod
  runs.
- The repo is public: the workbook and every importer output stay in the
  gitignored `data-to-import/`.
- ODPC compliance of the imported data is reviewed **after** the import,
  in its own card; out of scope here.

## Context

Before Mikono, UCESCO tracked volunteers in one Google Sheet, "VOLUNTEER
INTRODUCTION FORM (Responses)". UCESCO top management has asked for **all
of it** in production, keeping as much personal data as possible.
**Source of truth: the `.xlsx`, every tab** (the CSVs were a partial
extract and are no longer used).

| Tab | Rows | Dates | Gives |
|---|---|---|---|
| Volunteer Details (Google Form) | 276, 2023 → 2026-10-14 | Start/End, or Arrival/Departure (never both) | full profile, ~30 questions |
| 2023 / 2024 / 2025 volunteers | 131 / ~225 / ~198 | **none**: tab year + DURATION ("2 WEEKS") | branch, project, activity, source, country, birthday |
| 2026 Volunteers | 30 | none | branch, project, activity, source |
| DIRECT VOLUNTEERS | 16 | start/end (mixed formats) | gender, country, branch, status |
| ACK Clinic attendance | 65 rows, 18 people | **one per day** | becomes real `Activity` rows (separate pass) |

Roster layout: NAME, BRANCH, PROJECT, ACTIVITY, *(blank column E)*,
SOURCE, DURATION, COUNTRY, BIRTHDAY. The 2024/2025 tabs repeat their
header row 22/14 times, with no date label above it: skip those rows,
they are not people.

Production already holds Sept–Oct 2026 volunteers Edna entered, so some
people exist on both sides.

**Decisions (2026-10-17):**
- **Write-back = swap the DB file** in a weekend freeze. The workbook never
  leaves this machine.
- **Scope = every row, 2023–2026, every form answer.**
- **UCESCO top management's decision: import all personal data the
  workbook holds.** No value is dropped. Every unmapped form answer gets
  **a new column** (a schema change shipped *before* the sync), and any
  value that can't be typed goes verbatim into `legacyRecord`. The ODPC
  compliance review comes **after** the import, as its own backlog card.
  It is out of scope here.
- **Branch/project/activity** come from every roster tab (2023–2026) and
  DIRECT, matched to production's own lists. A new branch/source is only
  proposed, then added in the app UI.
- **Duplicates: prod wins, fill blanks.** Only an email match applies
  automatically. A name-only match is always reviewed.
- **People with no dates anywhere** form an exceptions pass at the end,
  checked by hand.
- **ACK attendance** is its own pass: reconcile names, propose inferred
  stays, then create activities.

## Step 0: stop the PII leaking (first commit)

`data-to-import/` is untracked but **not gitignored**, and the repo is
public. Add `/data-to-import/` to `.gitignore` next to the
`docs/fixtures/*_dumps.txt` block. All importer output goes in
`data-to-import/out/` (bind-mounted at `/app/data-to-import`).

## Step 1: docs and schema (shipped to prod before anything else)

### 1a. Remove the personal-data limits from the docs (adr-scribe, in place)

Remove the minimisation and admission limits so the docs don't
contradict the import, and add no new reasoning:
- **ADR 0034**: the admission gate, rule 1's per-field purpose/minimum
  and "its own ADR" requirement, and rule 2's free-text and
  dedicated-field limits. The Decision records management's direction to
  import every value.
- **ADR 0037**: the gender "profile page only" limit.
- **ADR 0028** line 21 and **ADR 0044** line 76: the same wording.
- **AGENTS.md** (CLAUDE.md is a symlink to it) lines 135–141.

### 1b. New nullable `Volunteer` columns (one migration)

| Column | Type | Form question |
|---|---|---|
| `instagram`, `facebook`, `twitter`, `tiktok`, `linkedin` | string 255, **no URL assert** (mostly handles) | social accounts |
| `hobbies` | text | What are your hobbies |
| `freeTimeActivities` | text | Free-time activities wished |
| `motivation` | text | Why UCESCO-AFRICA |
| `skillsDescription` | text | Which skills and abilities… |
| `experienceWithChildren` | bool | Previous volunteering with children |
| `employer` | string | Name of employer |
| `education` | string | University/college/school |
| `favouriteFood` | string | Favourite food |
| `accommodationAddress` | string | Address of place of accommodation |
| `legacyRecord` | text | one line per roster appearance (`2024 · Nairobi · Peggy Lucas · Childcare · 2 WEEKS`), plus **every value that couldn't be typed**, kept verbatim (`Form submitted: 2024-12-09`, `DOB: 11/06`, `Gender: Male?`, `Country: <text>`, DIRECT `STATUS`, form column AG), so nothing in the workbook is lost |

The existing fields take the rest (table in step 3). Each new column
needs:
- an optional field in `VolunteerFormType`: `required: false`, no
  `empty_data`, the `lastName` pattern;
- a row on `templates/volunteer/show.html.twig`;
- a line in `UserTimeline::describe()` only if it adds an `Authored`
  entity, which it doesn't.

`DatabaseExport` picks the columns up on its own. Run `make test` and
`make quality`, then commit, wait for the CI image (`gh run list`),
`make deploy`, and check the entrypoint logs the migration on prod.

### 1c. Pin the schema

The local checkout must equal the deployed commit, and
`doctrine:migrations:status` must match on both sides. The dev
entrypoint runs `doctrine:migrations:migrate` on every restart, so a
migration that exists locally but isn't deployed would be applied to the
prod copy and uploaded with it.

## Step 2: fresh production copy

1. Edna: no edits until the swap is done.
2. `make db_update`. **I'll ask before running it.** It overwrites the
   local dev DB and puts the prod passport key in `.env.dev.local`.
3. Baseline `out/baseline.json`: **sha256 of every table's rows ordered
   by rowid**, except `login_attempt` and `usage_event`. The same script
   runs again in step 7.
4. **Don't create the agent account in this copy**: it would be uploaded.
   Log in locally with your own prod account.
5. Keep the pristine copy as `out/S0.db`, so a retry needs no re-sync.

## Step 3: the importer, `app:import:legacy-workbook <xlsx>`

`src/Command/ImportLegacyWorkbookCommand.php`. It reads the `.xlsx` with
**OpenSpout** (already installed, ADR 0029), so dates arrive as real
dates. It writes through Doctrine entities and the Validator (setters'
`nullIfBlank`, `Assert\Country`, `Assert\Email`, `Gender`, `Stay`'s
asserts, `Stay::overlaps()`). Modes:

- **`--analyse`** (read-only) writes:
  - `out/mapping.yaml`: every distinct legacy value with a proposed
    target from **production's current lists**:
    - branches: Nairobi/Narobi → Nairobi (HQ), Kampala → Uganda?,
      Mombasa, Samburu;
    - projects: ~60 spellings across tabs ("PeggyLucas", "Peggy",
      "Bright Achiervers") → prod projects;
    - activities: ~80 values → `Skill`s;
    - sources: VW → Volunteer World, Email → Website/Email, WhatsApp,
      Referral → ?;
    - countries: free text → ISO-2 via Symfony Intl plus aliases.
  - `out/people.csv`: one line per **person**, built by joining every
    tab.
    - Join keys: form rows merged by email; roster/DIRECT rows joined to
      them by normalised name (accents, case, whitespace), with the roster
      year checked against the form's dates; then matched to prod
      volunteers.
    - Columns: proposed action (`create` / `fill <id>` / `review` /
      `exception`), match evidence, the stays to add with their branch and
      where each came from, and every parse problem.
    - Parse problems, each also kept verbatim in `legacyRecord`:
      partial DOB ("38", "11/06"); year typos `0024`; DIRECT dates with
      no year; `Male?`; one-word names; d/m vs m/d ambiguity in typed
      (non-date) cells.
  - A summary with counts per action. It fails while any `review` line is
    still unresolved.
- **`--apply --dry-run`**: the full apply in one transaction, **rolled
  back**. Lists violations and refused stays, and writes
  `out/expected-delta.json`.
- **`--apply`**: the same, committed all-or-nothing, and idempotent: a
  re-run matches by email/name and changes nothing.
  - Created volunteer: every field.
  - Existing volunteer: null fields only; skills/sources by set union; a
    stay only if no existing stay `overlaps()` it.
  - `touch()` on edits. `AuthorStamper` sees no user in the console, so
    imported rows get `createdBy = null`, and filled rows keep their last
    human `updatedBy`. `createdAt` is import day (the form's submission
    date is in `legacyRecord`).
  - The form timestamp is used in analysis: it checks a roster-year ↔
    form join (a 2024 roster row vs a form submitted in late 2023/2024),
    and in step 6 it's shown as the only date hint for an exception.

**Field mapping into existing columns:**
- full name → `firstName`/`lastName` (last token);
- DOB → `dateOfBirth`, full dates only, roster BIRTHDAY as a fallback;
- country → `countryOfResidence`;
- `email` (lowercase) and `phone` (≤50);
- program of interest + projects of interest → `interests`;
- occupation → `profession`;
- emergency contact + relationship → `emergencyContacts`;
- `gender`;
- the first valid URL among the socials → `socialMediaUrl`;
- dates → `Stay`.

**Test**: `tests/Integration/Command/ImportLegacyWorkbookCommandTest.php`,
a tiny synthetic `.xlsx` with fake names in `tests/fixtures/`. It covers:
- a date cell vs a typed date;
- repeated header row skipped;
- email match fills a blank without overwriting;
- name-only match → review;
- overlapping stay refused;
- second apply → zero changes;
- ACK day without a covering stay → reported, not created.

## Step 4: main pass (form + rosters + DIRECT)

Loop `--analyse` → edit `mapping.yaml` / `people.csv` → `--dry-run` until
there are 0 violations and 0 `review` lines. Add a missing branch, source
or project **in the app UI on the local copy**, so it goes up with the
file. Then `--apply`. Snapshot `out/S1.db`.

## Step 5: ACK clinic pass (`--ack`, after step 4)

1. **Names**: each of the 18 attendance names is matched to a volunteer
   that now exists (exact, then fuzzy), and proposed in `out/ack.csv` for
   you to confirm.
2. **Fix the dates first: swap day and month on every date cell of this
   tab.** Sheets parsed typed d/m text ("1/9/2025") as m/d, then stored
   it as a date (9 Jan). Text cells ("26/8/2025") survived only because
   day > 12. Proven on the real tab: after the swap there are 0 weekend
   days out of 54, and the rows are strictly chronological (0 inversions
   in 53). Before it, there were 9 weekend days and month-sized jumps.
   The command **re-checks both conditions and aborts** if either fails,
   so a different sheet can't be "fixed" blindly. Text cells are read
   d/m/Y as they are.
3. **Inferred stays: one per run of attendance, not one per day.**
   - Per volunteer, sort the days. Two consecutive days belong to the
     same run when the **working days (Mon–Fri) strictly between them**
     are ≤ `--max-gap` (default **5**, one working week). Weekends never
     count as a gap, so they're inside the run and the stay covers them.
     A gap of 1–4 is normal: volunteers spend other weekdays at other
     projects.
   - Stay = first attendance day → last attendance day of the run, at
     the ACK clinic project's branch.
   - On the real data this gives one stay each for P00–P04 and P06. P05
     (39 days, 8 Sep – 21 Nov 2025) has one 6-working-day gap, so it
     splits in two at the default and stays whole at `--max-gap=6`.
     `out/ack.csv` shows the split and you choose; that's what the knob
     is for.
   - Reconcile with stays the main pass created:
     - a run overlapping, or within `--max-gap` working days of, an
       existing stay of that volunteer at the same branch → **extend**
       that stay to cover it;
     - the same at a different branch → `review`, because the activity's
       project must share the stay's branch;
     - a stay Edna entered in production is never extended
       automatically → `review`;
     - otherwise → **create**, marked `inferred`.
   - Public holidays are counted as working days. That's a ceiling: a
     holiday inside a gap can inflate it by 1, which the knob absorbs.
   - Nothing is written until you accept `out/ack.csv`.
4. **Names with no dates** (11 of the 18): reconciled to a volunteer
   like the others, but they get no activity and no stay. They go to the
   exceptions pass (step 6).
5. **Activities** (one per attendance day, inside its inferred stay):
   - the program, activity type and duration come from `mapping.yaml`;
   - `--logged-by=<email>`, because `Activity::$loggedBy` is non-null;
   - `ActivityController::resolveStays()` rules: the type is offered by
     the program, the program's dates cover the day, the project's branch
     equals the stay's;
   - a same-day duplicate is reported, never created twice.
6. `--dry-run`, then apply, then snapshot `out/S2.db`.

The test gains three cases:
- serial cells swapped, and the check aborts on a sheet with weekend
  dates;
- days split into runs at `--max-gap`, with weekends not counted;
- a run next to an existing stay extends it.

## Step 6: exceptions pass (manual)

People with no dates in any tab get a volunteer record with no stay, so
their status is "no stay". **Their roster duration is appended to
`notes`**, one line per roster appearance, e.g. `2024 roster: 2 WEEKS`.
It is added after any existing notes, never replacing them, including on
a production volunteer, since that's additive and not a "fill blank". A
line that is already present isn't appended again, which keeps re-runs
idempotent. A person who does have stay dates gets no duration line,
because the stay already says how long they stayed. The rest of their
roster line (year, branch, project, activity) still goes to
`legacyRecord`. For
each, `people.csv` lists the roster year, branch and duration, and you
decide row by row: keep as-is, add a stay by hand in the UI, merge into
another person, or drop. Apply, then snapshot `out/S3.db`.

## Step 7: verify locally (the gate before upload)

1. `PRAGMA integrity_check` returns ok and `foreign_key_check` is empty;
   `doctrine:schema:validate` and `migrations:status` are in sync.
2. Per-table deltas equal the expected deltas. Tables not in scope
   (user, project, program, …) are unchanged unless you edited them.
3. The passport-ciphertext hash equals the baseline.
4. Invariants:
   - no end < start;
   - no overlapping stays per volunteer;
   - every stay has a branch;
   - no new email duplicates (none existed at baseline);
   - every non-null country is ISO-2;
   - every activity's date falls in its stay, and its project's branch
     equals the stay's.
5. `make test` and `make quality` pass.
6. Browser check:
   - `/volunteers` status counts;
   - 5 profiles: created, filled, untouched, an ACK one, an exception;
   - `/reports/volunteers` (every tab);
   - `/matches`;
   - home;
   - Admin → Export database.
7. Delete `login_attempt`/`usage_event` rows newer than S0 (your local
   logins), then `docker compose restart php` if anything shows
   "malformed".

## Step 8: swap production (weekend, as `deploy@`)

Follow deployment-plan.md "Restore drill" + "Seeding a server from a
local dataset":

1. `down` first, then copy the stopped `data_prod.db` out as **B1**
   (that's the rollback), fingerprint it, and compare with the baseline.
   **Any difference = abort**: `up`, re-sync, re-apply. The re-apply is
   cheap because the mapping files carry over.
2. Export the local DB with `scripts/backup-db.sh` (dev
   `COMPOSE_FILES`), scp it, copy it over with the app's own image,
   remove the sidecars, `rm -rf /data/sessions`, `chown 33:0`, then
   `up -d --wait`.
3. On prod:
   - the entrypoint logs "Already at the latest version";
   - the integrity check passes;
   - row counts equal the local ones;
   - log in and open a profile that has a passport number (the key still
     decrypts).
4. Rollback = restore B1. Keep B1 for a week.

## Step 9: clean up and record

- Remove the prod key line from `.env.dev.local`, `make fixtures`,
  `docker compose restart php`.
- Delete `data-to-import/` once prod is confirmed.
- Docs:
  - now: a card
    `docs/project/backlog/import-legacy-volunteer-workbook.md` and one
    line under **Now**;
  - ADR 0044 is rewritten in place to admit the one-off local → prod
    upload under a freeze with the fingerprint check;
  - when finished: a `done.md` entry with the counts, and the card
    deleted;
  - a new card, `odpc-compliance-review-of-imported-data.md` (flag **D**,
    under **Next**): the deferred compliance pass over what was imported.
    Title and link only, no analysis now.
- Delete the command after the import lands (one-off, the history is in
  git).
