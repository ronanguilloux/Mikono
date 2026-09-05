# Done

Append-only, growing log of completed work that isn't itself an
architectural decision (those live in [`docs/adr/`](../adr/) instead —
see that folder's README for the rule). Newest entries first. Add a
dated entry here whenever an item in
[`next-steps.md`](next-steps.md) is completed and isn't ADR-worthy.

## 2026-09-05 — First real deployment: the pilot is live on GandiCloud VPS

`srv-mikono` — GandiCloud VPS V-R1, Debian 13 trixie, Paris SD6 — was
provisioned on 2026-09-04 and deployed on 2026-09-05, serving
`https://deploy.mikono.guilloux.org` with **no real data on it**. Machine
specifics are in the gitignored `server.local.md`; this entry records
what it settled.

**All five things [`next-steps.md`](next-steps.md) item 0 said could only
be tested on a real machine now pass**, and none of them needed a second
attempt:

- **ACME certificate issuance** — Let's Encrypt, valid 2026-09-05 to
  2026-12-04. Which also proves **port 80 is reachable from outside**,
  since HTTP-01 could not have completed otherwise.
- **HTTP/3 on 443/udp** — Caddy advertises `alt-svc: h3=":443"`, and
  Chrome negotiated `h3` on the second load. So **Gandi does not filter
  UDP 443** — question 4 of the five in `hosting-plan.md` §5, answered by
  measurement rather than by a sales desk.
- **DNS** and **the Docker/UFW interaction** — the latter harmless
  exactly as `deployment-plan.md` §3 predicted.

The image pulled and started clean: `linux/amd64`, 583 MB, healthy in
7.9 s, migrations applied by the entrypoint. The login page renders
**styled** (`tailwindcss v4.3.3`, 27 KB, versioned by AssetMapper), which
is the check that catches a missing `tailwind:build` in the image.

**Three runbook defects the real machine exposed**, all fixed in
[`deployment-plan.md`](deployment-plan.md) §3:

1. **You do not log in as root on a cloud image.** Gandi's Debian admits
   `debian` with passwordless sudo, and root's `authorized_keys` wraps
   the key in a forced command. §3's step 1 copied *root's*
   `authorized_keys` to the `deploy` user — which would have carried that
   forced command across and locked `deploy` out, on a box whose password
   authentication the next step disables. The key now comes from the
   admin user's file, with a verification line after it.
2. **Never `sed` `/etc/ssh/sshd_config`.** It begins with
   `Include /etc/ssh/sshd_config.d/*.conf`, and OpenSSH keeps the *first*
   value it obtains — so a cloud image's `50-cloud-init.conf` wins and the
   sed silently changes nothing. Replaced by a `01-mikono.conf` drop-in
   plus `sshd -T` to assert the effective config rather than the edit.
   (This image ships no cloud-init drop-in, so the hazard did not bite
   here — the fix stands anyway.)
3. **Step ordering.** `usermod -aG docker deploy` in step 1 needs a group
   that step 5 creates. Recorded rather than renumbered.

Two additions the 1 GB plan forced: a **2 GB swapfile** (951 MB usable
and no swap at all — FrankenPHP in worker mode with a 256 MB opcache
leaves nothing for an image pull, and those get OOM-killed rather than
slowed), and the concrete Docker apt-repository steps in place of a
pointer to docs.docker.com.

**[`scripts/deploy.sh`](../../scripts/deploy.sh) is new**, and passed its
first run by being that run. It is §6's three commands plus the two
things a human gets wrong: it always passes both compose files and
`--env-file`, and it **backs up before pulling** — §6 says to back up
before any deploy carrying a migration, and nobody reliably remembers
which those are. It also creates the first admin account, but **only when
the database holds no users at all**, so it can never reset a password
changed later from inside the app. It deliberately does not roll back on
failure: a migration is not reverted by rolling the image back, so an
automatic rollback would sometimes leave a new schema under old code.

Deploying from CI over SSH was considered and rejected — it needs a
GitHub secret that is root-equivalent on the server, and would let any
commit to `main` reach production unattended, on the machine holding the
only copy of the volunteer database. At one maintainer, a deploy is a
decision, not a trigger.

**Backups and the restore drill, same day.** Three more things the
minimal cloud image did not provide, all fixed in §7: `cron` was not
installed at all; `/opt/mikono/backups` did not exist, so cron's own
`>> backup.log` redirect would have failed *before* the script that
creates the directory ever ran; and the clock was `Etc/UTC`, making the
crontab's "02:15 EAT" comment wrong by three hours — the host timezone
is now `Africa/Nairobi`, matching the `date.timezone` the app already
uses.

**The drill passed**, with the marker test §7 insists on: an account
created *after* the backup was gone after the restore, which is the only
thing that distinguishes a real drill from a restore that changed
nothing. The entrypoint logged `Already at the latest version` rather
than replaying migrations. Two findings went back into §7: the restore
used `debian:13-slim`, which **had to be pulled mid-restore** — a
restore must not depend on a reachable registry, so it now uses the
app's own image, already on the machine; and `docker compose exec -T`
**consumes stdin**, so a drill script piped into `ssh bash -s` had the
console command silently swallow the rest of the script and skip every
remaining step with no error.

**What this deliberately does not settle:** the box is in Paris, and the
Kenya-versus-France question stays open until real data goes on it
(`next-steps.md` item 1). Every backup still sits on the same disk as
the database it protects — the encrypted off-site copy is the one piece
of §7 still missing.

## 2026-09-04 — Domain settled: `mikono.guilloux.org`, no purchase

`hosting-plan.md` §3 had DuckDNS as the way to get a real certificate
without paying a registrar, and §6 carried the domain as an open
decision. Both are resolved more cheaply than either assumed: the
maintainer already owns **`guilloux.org`**, DNS at Gandi.net, so the
name is two A records and no money.

- `deploy.mikono.guilloux.org` — the throwaway the production box is
  *brought up* on, so Let's Encrypt's five-duplicate-certs-per-week
  budget is not spent on the real name while the first deploy is still
  error-prone ([`next-steps.md`](next-steps.md) item 0).
- `mikono.guilloux.org` — the real one. The cutover is an *addition*,
  not a re-point: same IP, so no propagation window and no TTL to lower
  first. Create the record, change `SERVER_NAME`, redeploy, delete the
  `deploy.` record.

DuckDNS is kept in §3 as a labelled fallback rather than deleted. What
this does **not** settle is whose name it is: `guilloux.org` is the
maintainer's, not UCESCO's, which is a governance dependency invisible
until the day it matters. §6 now says so, and a UCESCO-held `.co.ke` —
a few hundred shillings against the ~$275/year hosting bill — is the
coherent end state once the app is theirs rather than a pilot.

## 2026-09-04 — Hosting shortlist verified; the cheap candidate is probably not in Kenya

[`hosting-plan.md`](hosting-plan.md) §5 carried prices that had "not been
verified against the providers' own pages". They are now, and the
verification changed the decision rather than confirming it.

**HostPinnacle SM-VPS 1 — 1,100 KSh/month for 4 vCPU / 6 GB — does not
say where the machine is**, and that price is five to ten times cheaper
per GB of RAM than every provider that does name Nairobi. The providers
selling both locations are explicit about the gap: Truehost charges
788 KSh for 2 GB in Europe/USA against 2,800 KSh for 2 GB in Nairobi;
Hostnali, 1,480 KSh for 8 GB international against 4,360 KSh for 2 GB in
Nairobi. §5 had used HostPinnacle's spec as evidence that in-country
hosting is cheap enough to skip the 1 GB minimum. It is not evidence of
that. "Which datacentre is this plan physically in?" is now question 1
of five, because it is the same question as §5's data-residency
argument asked about a price.

**Two providers were added to the shortlist**: Lineserve (Nairobi
`ke-1a`, per-unit pricing so the box can be sized to §2 exactly,
on-demand snapshots and scheduled backups published) and Hostnali
(Nairobi, KIXP-peered, KVM and Docker support stated on the page). Both
answer more of the five questions on their own pages than either
original candidate does.

**The real Nairobi cost of the §2 recommended 2 vCPU / 2 GB box is
2,600–3,000 KSh/month plus VAT — roughly $260–290/year**, not the
~$100/year assumed elsewhere in these documents. Affordable, but worth
naming to UCESCO before committing; it also makes the `.co.ke` domain
argument stronger, since a few hundred shillings against $275 is
rounding error.

The five questions are now drafted as one sendable email in
[`provider-questions.md`](provider-questions.md). Nothing has been sent
and no provider is chosen — that is still
[`next-steps.md`](next-steps.md) item 1.

## 2026-09-04 — CI is green, for the first time since it was added

[ADR 0010](../adr/0010-build-in-ci-and-deploy-by-image-pull.md) made
`ci.yml` the quality gate "somewhere it cannot be skipped"; it had been
red on every run since. It now passes: quality clean, 194 tests, 844
assertions. The deploy is no longer gated on it.

Found by replicating CI's exact `docker run -v "$PWD:/app"` against a
clone with no `var/` and no `assets/vendor/` — none of it reproduces
under `docker compose exec`. Three causes, plus one real bug they hid:

1. **`composer install --no-scripts` skipped `importmap:install`**, so
   `assets/vendor/` was never populated. The flag's comment reasoned that
   "the image already ran post-install-cmd" — true, and irrelevant: the
   bind mount lands *on top of* the image's own `/app`, hiding everything
   that build wrote outside `vendor/`. Dropping the flag fixes it.
2. **`var/tailwind/` held no built stylesheet**, so AssetMapper could not
   resolve `@import "tailwindcss"` out of `assets/styles/app.css`. A
   `tailwind:build` step now runs before the tests, for the same reason
   the prod builder stage in the `Dockerfile` already has one.
3. **`var/data/` did not exist**, so SQLite could not create the database
   file and the entrypoint's readiness check failed. `mkdir -p var/data`.

**The memory limit was a symptom, and `next-steps.md` was right to
forbid raising it.** Either of the first two causes makes every page
rendering `base.html.twig` return a 500, and Symfony's exception pages
are big enough that `dom-crawler` exhausts 128 MB partway through the
suite — surfacing as `Premature end of PHP process` in whichever test
happened to be running (hence the wandering culprit: `ReportControllerTest`
one run, `UserControllerTest` the next). With the assets present the
suite peaks at 95 MB. `memory_limit` was never touched.

**The bug underneath.** With the suite finally running end to end, one
genuine failure remained: `ActivityFactory` dated activities with
faker's time component, but `Activity::$date` is a `date_immutable`
column that drops it on the way to SQLite. A just-created entity
therefore disagreed with its own hydrated row — and since `/reports`
computes `Planned` as `mostRecent > today` against midnight, any
activity faker happened to date *today* got badged `Planned` on the
first request and not on the second. 4 of 15 runs failed before,
0 of 25 after normalising the factory to `->setTime(0, 0)`. Not a CI
problem at all; CI just ran often enough to catch it.

## 2026-09-03 — The partner-organization field appears only when it applies

`next-steps.md` had this down as "needs light JS or a LiveComponent". It
is light JS: `assets/controllers/partner_field_controller.js` hides the
partner-organization row unless Ownership is Partner, and sets `required`
on it while it is shown. A LiveComponent would be a server round-trip to
show and hide a div.

**Nothing about the rule moved.** `Project::validatePartnerOrganizationName()`
already enforced it and its test already existed; this is the hint, not
the guard. That is also why the field ships *visible* and the controller
hides it on connect rather than the markup shipping it hidden — with
JavaScript off the form still works, the field is simply always there and
its help text ("Required if this is a partner project") is exactly right.
For the same reason the form option stays `required: false`: making it
required server-side would give the label its asterisk, but would also
stop a no-JS reader saving a UCESCO-owned project.

Two supporting changes:

- The theme's `form_row` now honours `row_attr`, which it had been
  discarding along with the label's `for`. That is what lets a field carry
  a Stimulus target without `_form.html.twig` rendering every row by hand.
- The value the controller compares against is passed from
  `ProjectOwnership::Partner->value` through the form's `attr`, so the
  enum's backing string is not repeated in JavaScript. The form's width
  class moved there too, since `form_start(form, {attr: …})` would
  otherwise replace the wiring.

The controller also clears the input when it hides it: a name typed
before switching to UCESCO-owned would otherwise be saved on a project
with no partner, and the server rule only checks the partner case.

One functional test holds the wiring — controller attached, select
reporting changes, row findable, value coming from the enum. The show and
hide themselves are Stimulus, so they were verified in a real browser
instead.

## 2026-09-03 — Expanded choices go inline, and every label gets its `for` back

`next-steps.md` had the five stacked escort checkboxes down as a styling
annoyance. It was, but the cause was a real defect one level up.

The theme's `form_label` override had dropped the base theme's
`label_attr.for`, so **no label anywhere in the app was associated with
its input** — none clickable, none announced. It also emitted a second
`class` attribute after the caller's and relied on browsers honouring the
first, which worked but made the hardcoded `block` impossible to remove
for the choice children that need to sit inline. Fixing `form_label`
fixes every field, not just Escort; compound fields (the Duration and
Escort groups themselves) still get no `for`, since they label a
container rather than an input.

With that fixed, `choice_widget_expanded` renders each option as an
input/label pair inside its own flex item, wrapping horizontally. Both
activity forms lose four rows of height: Duration and Escort are one line
each. `radio_widget` also picked up the `h-4 w-4` sizing the checkboxes
already had — radios were rendering with no class at all.

## 2026-09-03 — The delete guard is visible before it fires

`next-steps.md` asked for the volunteer/project delete-guard rule to be
surfaced *before* a blocked delete, not only as a flash afterwards. Both
indexes now render Delete as inert on rows with logged activity, with the
guard's own sentence as the tooltip, plus a note above the table saying
the rule in words.

- **The server guard did not move.** `delete()` still counts and still
  refuses; the index only reads the same rule ahead of time. The two
  existing "delete is blocked" tests now reach it the way a real reader
  would — a page rendered *before* the activity existed, then submitted —
  which is a better test than the old one and documents why the
  controller check stays.
- **One sentence, one place.** Each controller grew a private
  `guardReason()` used by both the tooltip and the flash, so the warning
  and the refusal cannot drift apart.
- **One query, not twenty-five.** `countReferencingActivitiesFor()` on
  both repositories returns counts keyed by id for a whole page.
  Entities with no activities are absent rather than zero, so callers
  read it with `?? 0`.
- `RowActions` gained `disabledReason`: an action carrying it renders as
  an `aria-disabled` span with the reason in `title` *and* in `sr-only`
  text, since a non-interactive element takes no focus and a tooltip
  alone reaches nobody else. The note above the table is what actually
  explains it to a keyboard user — that is why both exist.

The note's count is page-scoped and says so ("on this page"), because it
comes from the same single query that builds the rows; a roster-wide
figure would be a number nothing measured.

## 2026-09-03 — The Reports breakdowns tag what hasn't happened yet

The last of the five mockup-5 findings: a future-dated "Most recent"
cell now carries a `Planned` pill, the way the Activities cards, the
home screen's tomorrow roster and the "incl. N planned" tile already do.

**`ActivitySummaryCalculator` did not need to change**, contrary to what
`next-steps.md` predicted — and that is the finding worth keeping. The
calculator already reports each bucket's latest date; comparing it to
today adds no domain knowledge, only a label this one view draws. So the
boolean is derived in `ReportController::toRows()`, which now takes the
`$today` the KPI tiles were already computing, and the class inside the
Infection scope is untouched. The planned integration tests were not
written for the same reason: there is nothing new in `src/Report/` to
test, so the coverage is functional, in `ReportControllerTest`.

The one shared-component change is
[`DataTable`](../../src/Twig/Components/DataTable.php), which grew an
optional per-row `badges` map — column key => badge label, drawn as a
pill after that cell's text. Keyed by column so a badge lands on the
cell it qualifies, and deliberately *not* concatenated into the cell
string: `cells` stay the plain formatted value, so sorting,
`number_format()` and the tests that match a cell by its text are all
unaffected. Additive and null-safe — the other six lists pass no
`badges` and render exactly as before. This is the seam `next-steps.md`
names for cross-cutting list behaviour, so the next list-wide tag
(Inactive, say) has somewhere to go.

Two details that are easy to get wrong:

- **The badge carries print variants** (`print:border print:text-slate-700`).
  Browsers drop background colours on paper by default, so without a
  border the pill would simply vanish from the print panel — which
  renders both breakdowns in full and therefore gets badges too.
- **Today is not planned.** The boundary is `> $today`, matching the
  Activities index and the KPI tile, and a test pins it.

Seven functional tests in
[`ReportControllerTest`](../../tests/Functional/ReportControllerTest.php)
cover both tabs, both print tables, the today boundary, a bucket mixing
past and future dates (the badge follows "Most recent", not the bucket),
and that unbadged rows on the same page stay unbadged. Verified visually
against the dev fixtures as well: four badged rows, matching the
"incl. 4 planned" tile above them.

## 2026-09-03 — A test now holds the Nairobi day boundary

`next-steps.md` hosting item 5: `date.timezone = Africa/Nairobi` in
[`frankenphp/conf.d/10-app.ini`](../../frankenphp/conf.d/10-app.ini) was
the only thing making the home screen's rosters resolve
`new \DateTimeImmutable('today')` to the Kenyan calendar date, and
nothing failed if it reverted to UTC — the app would keep passing every
test while showing *yesterday's* roster between 00:00 and 03:00 EAT,
which is exactly when a VM checks the schedule before an early start.
[`tests/Integration/Report/RosterDayBoundaryTest.php`](../../tests/Integration/Report/RosterDayBoundaryTest.php)
now holds it: five tests, three of which fail under
`php -d date.timezone=UTC` (verified).

The design point worth keeping. None of the four `'today'` call sites
takes an injectable clock, and adding one was more change than this
warranted, so the test uses a private `todayAt(instant)` stand-in —
wall-clock date in `date_default_timezone_get()`, truncated to midnight,
which is what `'today'` does. Two things keep that from being circular:
it reads the ambient default timezone rather than naming Nairobi, so it
moves when the setting moves; and a separate test asserts the stand-in
agrees with a real `new \DateTimeImmutable('today')`, so it fails if the
two ever diverge. The rosters are then built through the real
`RosterBuilder` against seeded activities on both adjacent days, so the
assertion is on the schedule the VM would actually see, not on a date
string.

One caveat is recorded in the test's docblock: `10-app.ini` is copied
into the image rather than bind-mounted, so an edit to it only reaches
this test after a `docker compose build php`.

## 2026-09-03 — The pre-production rehearsal drops its second server

`next-steps.md` item 0 required the runbook to be exercised end to end on
a disposable Hetzner box before UCESCO was contacted. Reviewed and
narrowed: the box bought a *rehearsal* and a *control variable* — a
server known-good, so any failure had to be the runbook's — and only the
first is worth a second server.

- The rehearsal is now a **local dry run of the production stack**,
  [`deployment-plan.md`](deployment-plan.md) §10: both compose files, a
  separate `-p mikono-dryrun` project name, `SERVER_NAME=localhost`. It
  covers the GHCR pull, migrations onto an empty volume, whether the
  image really contains a built Tailwind bundle, and — the reason it
  exists — the §7 restore drill, whose Compose volume name and
  `chown 33:0` step had never been observed to be right.
- What needs a real server (ACME issuance, port 80 from outside, HTTP/3
  on 443/udp, DNS, the Docker/UFW iptables interaction) happens on the
  production box, brought up on **a DuckDNS hostname, not the real
  domain**, with no real data. That protects Let's Encrypt's
  duplicate-certificate budget — five per week per hostname — while
  mistakes are still likely, which is the one failure in the sequence
  that trying again does not fix.
- **The control variable is genuinely lost**, and it is written into
  `hosting-plan.md` §6 rather than glossed: the first real deploy now
  debugs the runbook and the provider at once. Judged acceptable because
  every provider-specific surprise behind §5's four questions surfaces on
  the Nairobi box either way, so the second server only ever separated
  those two unknowns for the failures the local dry run already catches.

The `-p` flag is not incidental: [`compose.yaml`](../../compose.yaml)
declares its volumes unqualified, so a dry run without it attaches the
production container to the dev `mikono_db_data` volume and the restore
drill overwrites the development database.

### And then it was actually run, the same day

The dry run above is not a plan — it was executed on 2026-09-03, on an
arm64 macOS host, against the CI image of 2026-09-02. **The runbook was
substantially right**, which is the useful finding: nothing about the
deployment shape needed rethinking. Confirmed for the first time, all of
it previously only asserted:

- The **GHCR pull is anonymous** (no credentials cached), so the package
  is genuinely public — the one prerequisite `deployment-plan.md` §5
  does not set up a token for.
- **Seven migrations applied to an empty `db_data` volume** and the
  container reported healthy on first boot, in `env: prod`.
- **The image really contains a built Tailwind bundle** — ~27 KB of CSS
  served 200, not a `<link>` pointing at a 404. This is the exact defect
  [ADR 0010](../adr/0010-build-in-ci-and-deploy-by-image-pull.md) exists
  to prevent, now observed from outside the image rather than trusted.
- **A freshly pulled production image is empty**: zero volunteers,
  projects and activities. Foundry being `require-dev` is what stops the
  August roster reaching a public server, and that now has evidence.
- **The whole §7 restore drill worked as written** — volume name,
  `chown 33:0`, restart. Proved properly, by creating a user *after* the
  backup and confirming it was gone afterwards. The entrypoint reported
  "Already at the latest version" rather than replaying migration 1 onto
  live tables, which is the 2026-09-01 failure further down this file.

Four corrections went back into `deployment-plan.md`:

1. **The `export COMPOSE="…"; $COMPOSE pull` shorthand is a bash-ism.**
   It relies on word splitting of an unquoted expansion, which zsh does
   not do — and zsh is the default shell on macOS, where §10 runs. It
   fails as `command not found: docker compose -p …`. Harmless on the
   server (bash), so §2 flags it and §10 uses a function instead.
2. **An arm64 host needs `DOCKER_DEFAULT_PLATFORM=linux/amd64`**, since
   the published image is amd64-only. It runs fine emulated — which
   proves nothing about a server, and `next-steps.md` item 0 says so.
3. **Login cannot be scripted with `curl`.** Stateless CSRF
   (`config/packages/csrf.yaml`) ships the form with the literal
   placeholder `value="csrf-token"` for Stimulus to replace, so a
   scripted POST gets 400 and burns one of the five attempts
   `login_throttling` allows per 15 minutes. The production image has no
   Panther or Chromium either. The workaround, now in §10: run the *dev*
   image as a sibling container on the dry-run network and point
   `scripts/panther-screenshot.php --base-url=http://php` at it, using
   the `php:80` site `compose.yaml` already defines.
4. **The restore drill did not remove stale SQLite sidecar files.** A
   clean `down` leaves none, so the drill passed — but a crashed
   container can leave a `-journal`, which SQLite would then replay onto
   the file just restored. §7 grew a step.

`.gitignore` also gained `/deploy.env*`, outside the Flex-managed
recipe block: §4 and §10 both write a file holding a real `APP_SECRET`
into the working directory, and this repository is public.

## 2026-09-03 — The test suite is repeatable again: login throttling isolated in test

`next-steps.md` recorded that running the suite three or four times over
started failing
`SecurityControllerTest::correctCredentialsAuthenticateAndLandOnTheHomeScreen`,
with `cache:pool:clear --all --env=test` as the workaround. Fixed in one
line of configuration, but the mechanism is worth keeping so it isn't
rediscovered:

- `login_throttling` (5 attempts / 15 minutes, `security.yaml`) stores its
  counters in `cache.rate_limiter`, which the framework defines as a child
  of `cache.app` — real files under `var/cache/test/pools`. `cache.yaml`
  was untouched boilerplate, so that default stood, and nothing in
  `tests/bootstrap.php`, `phpunit.dist.xml`, or any test reset it.
- The key is `hash_hmac('sha256', $username.'-'.$clientIp, %container.build_hash%)`,
  so it is **stable across runs** unless the container is rebuilt, and
  every functional test shares `127.0.0.1`.
- `AbstractRequestRateLimiter` is *peekable*, so `LoginThrottlingListener`
  consumes a token only on **failed** login and **never resets on
  success**. `wrongPasswordShowsAnErrorAndDoesNotAuthenticate` therefore
  added exactly one token per run to the `vm@example.org` bucket, and the
  fifth run inside the window threw before
  `correctCredentialsAuthenticateAndLandOnTheHomeScreen` — declared right
  after it, same username — ever reached a password check. The per-IP
  global bucket (limit 25, +3 per run) was the same bug on a longer fuse.
  Reproduced exactly: by run 7 the suite showed three failures, including
  "Too many failed login attempts, please try again in 13 minutes."

The fix is a `when@test` block in
[`config/packages/cache.yaml`](../../config/packages/cache.yaml) pointing
`cache.rate_limiter` at `cache.adapter.array`. Overriding the name works
because `FrameworkExtension::load()` registers cache pools *after* the rate
limiter's own definition and ends with `setDefinition()`. Verified with
`debug:container cache.rate_limiter --env=test` (now `ArrayAdapter`) and
five consecutive full-suite runs, all `OK (175 tests, 767 assertions)`.

**Known consequence, deliberately accepted:** throttling is now *inert*
in the test environment, not merely isolated. Every cache pool is tagged
`kernel.reset` and `ArrayAdapter::reset()` clears the array, so the
services resetter empties the counters at each request boundary of the
test client — `$client->disableReboot()` does not prevent it. A
behavioural throttling test was written, failed for exactly this reason,
and was dropped rather than papered over; the alternative (a filesystem
pool cleared once per run from the bootstrap) was rejected because it
reintroduces the same intra-run coupling in miniature. Throttling is
unchanged in dev and prod. Don't write a test asserting login throttling
fires — it can't, and the reason is commented in `cache.yaml`.

Unrelated but found while verifying, and worth knowing: the dev container
had been crash-looping. `foundry:load-fixtures` resets the schema with the
schema tool, which empties `doctrine_migration_versions`, while the
entrypoint runs `doctrine:migrations:migrate --all-or-nothing` on every
boot — so it replayed migration 1 onto live tables and failed with `table
"user" already exists`. Repaired non-destructively with
`doctrine:migrations:version --add --all` after `doctrine:schema:validate`
confirmed the schema was already in sync. If it recurs after loading dev
fixtures, that is the fix — not a rebuild.

## 2026-09-01 — Hosting docs: three gaps closed, one recommendation held

A second opinion on [`hosting-plan.md`](hosting-plan.md) arrived arguing
for Vultr Johannesburg, Hetzner, AWS Cape Town, or DigitalOcean. It was
reasoning from latency and had not read §5, where latency is explicitly
the *weaker* argument and Kenya's Data Protection Act 2019 is the
deciding one — so every option it ranked first is one the plan already
ranks second or third. **The recommendation stands unchanged:** a KVM VPS
in Nairobi, shortlist HostPinnacle and Truehost, pending the measurements
§5 still asks for. What it did surface was three things the docs had not
written down:

- **The published image is amd64-only** — §2 claimed "x86_64 or arm64",
  true of the source but not of the artifact. `build-image.yml` passes no
  `platforms:` to `docker/build-push-action`, so an AWS `t4g.*` Graviton
  instance — exactly the cost-efficient choice someone would reach for —
  cannot start the container. §2 now says x86_64 and explains what an
  arm64 host would cost to enable.
- **An off-site backup inherits the residency question.** The free-egress
  defaults (R2, B2) have no Kenyan region, and that copy is the entire
  volunteer database in one file. `deployment-plan.md` §7 already said to
  choose the destination on residency grounds; §4 of the hosting plan now
  says so too, where the backup design is actually described.
- **UFW does not gate the container.** Docker publishes ports through its
  own iptables chain, consulted before UFW's INPUT rules, so the firewall
  block in `deployment-plan.md` §3 was not doing what its position in the
  runbook implies. Harmless as configured — 80 and 443 are meant to be
  open — and now annotated rather than silently misleading.

The §5 ranking gained named providers for its second choice (Vultr
Johannesburg, AWS `af-south-1`), so the fallback is actionable if neither
Kenyan candidate clears the four questions.

## 2026-09-01 — The dev dataset is the VM's real August

`AppStory` no longer generates anything. The whole dev and demo dataset
— 15 volunteers, 5 escorts, 13 sites, 90 activities — is transcribed
from a month of Edna's own WhatsApp roster messages (03/08 → 01/09/2026)
into [`docs/fixtures/rosters.yaml`](../fixtures/rosters.yaml), read by
`App\Fixture\RosterArchive`. The rule and its privacy boundary are
[ADR 0012](../adr/0012-seed-fixtures-from-the-real-whatsapp-roster-archive.md);
this entry records what the ADRs don't.

- **The real data caught two model gaps in the first hour.** The rosters
  of 30 and 31/08 read "Accompanied by Edna and Sam", which a single
  `ManyToOne` escort could not hold —
  [ADR 0013](../adr/0013-record-every-escort-on-an-activity.md), now a
  `ManyToMany` with a migration that carries existing escorts across.
  And every volunteer in every roster is a first name only, which a
  `NotBlank` surname could not hold —
  [ADR 0014](../adr/0014-make-a-volunteers-last-name-optional.md). Both
  had survived to "v0.1 feature-complete" behind generated fixtures that
  could not contradict them.
- **The raw exports are gitignored** (`docs/fixtures/*_dumps.txt`) and
  the committed extract carries no sponsored child, donor, or contact
  detail. This repo is public; the exports name minors with their school
  and grade.
- **The extract is maintained by hand, and a test guards it.**
  `tests/Integration/Fixture/RosterArchiveTest.php` fails if a roster
  names someone not declared at the top of the file, if a site has no
  volunteers, if the today/tomorrow anchors go missing, or if the
  two-escort roster behind ADR 0013 ever disappears from the archive.
  The transcription rules it can't check are written down in
  [`docs/fixtures/README.md`](../fixtures/README.md).
- **Three fields are documented defaults, not archive data**: duration
  (always a half day — the rosters never state one), `loggedBy` (the one
  admin account), and the anchoring of the last two archive days onto
  today/tomorrow so the home screen's roster panels aren't empty. Said
  out loud in the ADR and the fixtures README rather than hidden in the
  story class.
- **The dev database's migration history was broken** — the schema
  existed but `doctrine_migration_versions` was empty, so
  `doctrine:migrations:migrate` failed on "table user already exists".
  Fixed by marking the six existing migrations as applied
  (`doctrine:migrations:version --add`) before running the new one; the
  schema then validated clean. Worth knowing if another checkout does
  the same.
- **The roster's escort line now reads the way Edna writes it** —
  "Accompanied by Edna and Sam", not "Edna, Sam". `RosterGroup::escortLine()`
  joins the last pair with "and" and feeds both the home screen panel and
  the WhatsApp preview text, which exist to be copied into the group
  verbatim.
- Verified end to end: 175 tests green (7 of them new), `composer
  quality` clean (PHPStan max, PHPat, cs-fixer, audit), and the home
  screen screenshot shows the real roster — "Peggy Lucas school ·
  Daphne, Marco · Accompanied by Edna and Sam".

## 2026-09-01 — Sortable columns on all seven list views

Every column header on the six CRUD indexes and both `/reports`
breakdowns is now a link: ascending on the first click, descending on
the second, with the active column showing its direction. The mechanism
is
[ADR 0011](../adr/0011-resolve-list-sorting-in-listpaginator-rather-than-knp-sortable.md)
— our own sort resolution in `App\Pagination\ListPaginator`, with Knp's
sortable support switched off — so this entry records only what the ADR
doesn't.

Four things worth knowing:

- **The design in `next-steps.md` was followed with two deviations**,
  both found by checking the vendor code rather than its README.
  Sortability lives *only* in each controller's `SORT_MAP`, not also as
  a flag on the column definition, so the two can't drift; and Knp's
  sorting is disabled by passing `SORT_FIELD_PARAMETER_NAME => null`
  rather than an unused-looking parameter name, which would still be
  forgeable by hand in the URL bar.
- **The boolean column is `isActive`, not `active`.** Every Status
  column maps to `v.isActive` / `p.isActive` / `e.isActive` /
  `u.isActive`. `v.active` would have been a silent DQL error on four
  views at once.
- **Volunteer's `name` maps to two fields.** `getFullName()` has no
  single column behind it, so the map holds `['v.lastName',
  'v.firstName']` and both flip together — which is why `applySort()`
  takes a list of fields per key rather than one.
- **Doctrine echoes the ORDER BY direction verbatim**, so `applySort()`
  uppercases it on the way into DQL. Without that the generated query
  reads `ORDER BY v.isActive asc, v.lastName ASC` — valid, but a
  needless inconsistency with every repository's own ordering.

Verified beyond the 51 new tests: `/volunteers` sorted by Status pages
without repeating a row (the tie-break), the Activities mobile card list
sorts through its own select, `/reports` sorted by Most recent orders
the whole list with empty cells last, and the print panel ignores the
sort entirely.

## 2026-09-01 — Reports tabs + unified pagination across every list view

Slice 2 of mockup 5, and the last of the five validated 2026-08-28
screen designs to ship. The mechanism was decided ahead of the code in
[ADR 0009](../adr/0009-adopt-knppaginatorbundle-for-list-pagination.md)
— KnpPaginatorBundle for the windowing math, our own Tailwind markup,
rendered through `DataTable` — so this entry records only what the ADR
doesn't.

What landed: `/reports`' two breakdowns became tabs over one shared
table region (`?tab=volunteer|project`), and all seven list views (six
CRUD indexes plus Reports) gained a page-size selector and windowed
`‹ 1 … 32 33 34 … 66 67 ›` controls. `App\Pagination\ListPaginator` is
the single place `page` and `perPage` are parsed; `DataTable` grew
`pagination` and `withActions` props; the Reports template lost its
hand-rolled `summary_table` macro in favour of `DataTable`.

Five things worth knowing that aren't in the ADR:

- **ADR 0009 predates the Escort CRUD area** (commit `62f608f`). Read
  its "five CRUD lists / six list views" as "six CRUD lists / seven
  list views". The decision itself is unchanged, so this is recorded
  here rather than as a superseding ADR.
- **`perPage=all` maps to a finite ceiling** (`ListPaginator::ALL_PER_PAGE`,
  100 000), not 0 or `PHP_INT_MAX`: the bundle divides by the limit to
  get the page count, so 0 is a division by zero, and a bigint `LIMIT`
  is dialect-fragile.
- **Bad input never 404s.** An unknown `perPage` falls back to 25, a
  `page` below 1 clamps to 1, a `page` past the end serves the last
  page. Note that `InputBag::getInt()` could not be used for this — in
  Symfony 8 it *throws* on non-numeric input, which would have made
  `?page=abc` a 400.
- **Print did not regress.** The print-friendly view has always put both
  breakdowns on paper in full, so tabbing the screen would have halved
  it. A `hidden print:block` panel renders both complete tables
  alongside the paginated screen panel, at no extra query — both
  summaries come from one in-memory pass either way. The consequence is
  in `tests/E2E/VolunteerManagerSmokeTest.php`: Panther reads only
  *visible* text, so its `Bright Achievers` assertion now requests
  `/reports?tab=project`.
- **The bundle's `knp_pagination_render()` is not used.** Its render
  helper requires a `TranslatorInterface` this single-locale app doesn't
  have (its skipped contrib recipe would have enabled it), and it only
  contributes `route`/`query` to the template context — both public on
  the pagination object. `PaginationBar` includes
  `templates/pagination/tailwind.html.twig` directly instead, so no
  translation subsystem was added to serve a template with no
  translatable strings.

The windowing math is pinned by
`tests/Integration/Pagination/ListPaginatorTest.php`, which renders the
controls template against a synthetic 67-page pagination and asserts the
exact reviewed sequence at both ends and the middle — including the
empty-list case, where the bundle's page range computes to `[1, 0]` and
would otherwise render a link to page "0". Suite went from 73 tests to
116.

## 2026-09-01 — Production readiness: hosting review and its fixes

A review of `Dockerfile`, `compose*.yaml`, `frankenphp/`, and the app
config, prompted by [ADR 0003](../adr/0003-adopt-docker-frankenphp-symfony-sqlite-tailwind-for-volunteer-manager.md)
having deferred every hosting question out of v0.1. It produced
[`hosting-plan.md`](hosting-plan.md), [`deployment-plan.md`](deployment-plan.md),
and [ADR 0010](../adr/0010-build-in-ci-and-deploy-by-image-pull.md) — and
found two defects that a first deployment would have hit head-on:

- **The production image could not be built at all.** The `Dockerfile`
  ran `asset-map:compile` without ever running `tailwind:build`, and
  `TailwindBuilder::getOutputCssContent()` *throws* when the built CSS is
  missing (strict mode is on outside the `test` env) — a hard build
  failure, not degraded styling. Nobody had seen it because the
  `frankenphp_prod` target had never been built. Fixed by running
  `tailwind:build --minify` before `asset-map:compile`; the production
  image now builds and serves 26 KB of minified CSS.
- **The server ran in UTC while every user is in Kenya (EAT, UTC+3).**
  The home screen's "Today's roster"/"Tomorrow's roster" and the greeting
  resolve `new \DateTimeImmutable('today')` against PHP's `date.timezone`,
  so between 00:00 and 03:00 local time the app showed the *previous*
  day's roster. `date.timezone` is now `Africa/Nairobi` in
  `frankenphp/conf.d/10-app.ini`. PHP bundles its own timezone database,
  so the slim production image needs no `tzdata`.

Also in this pass:

- Panther's Chromium install moved from `frankenphp_base` to
  `frankenphp_dev`. The production *builder* stage inherits from the
  base, so every production build was downloading a browser the final
  image never ships. Both Panther entry points run in the dev container,
  so nothing else changed.
- `symfony/monolog-bundle` added — the app had no logging at all. The
  official recipe's Docker variant already writes production logs to
  `php://stderr` as JSON, which is what `docker compose logs` wants.
- `login_throttling` (5 attempts / 15 minutes) on the `main` firewall,
  via `symfony/rate-limiter`. The login form is the app's only
  internet-facing entry point and had no brute-force protection. Symfony
  surfaces the limit as an ordinary authentication error ("Too many
  failed login attempts…"), not an HTTP 429.
- `scripts/backup-db.sh` — hot backup via SQLite's `VACUUM INTO` through
  `pdo_sqlite`, verified with `PRAGMA integrity_check` before it leaves
  the container. No downtime, and no `sqlite3` binary (the slim
  production image has none).
- `.github/workflows/` — the repository had no CI whatsoever; the only
  quality gate was the local, bypassable pre-commit hook. `ci.yml` runs
  `composer quality` and `bin/phpunit`, and separately proves the
  production target still builds on every pull request. `build-image.yml`
  publishes it to GHCR on `main`.
- `compose.prod.yaml` now carries `DEFAULT_URI`, an `IMAGE_TAG`, and the
  comment explaining why both compose files must always be passed.

**One-off for anyone with an existing checkout:** `10-app.ini` is copied
into the image rather than bind-mounted, so the timezone fix needs
`docker compose build php && docker compose up -d --wait` to take effect
locally.

## 2026-08-31 — Reports dashboard, slice 1 (mockup 5, minus tabs/pagination)

`/reports` was two tables and nothing else. It now opens with four KPI
tiles (Volunteers, Projects, Activities logged, Total days contributed),
a "Top volunteers" recognition card, and a print-friendly view. The two
existing tables are untouched below it — this was purely additive.

Mockup 5 was deliberately split. The tabs, the 25/50/100/All page-size
selector, and the windowed `« 1 2 3 … 66 67 »` controls are **slice 2**:
they pull in KnpPaginatorBundle per
[ADR 0009](../adr/0009-adopt-knppaginatorbundle-for-list-pagination.md),
touch all six index views, and extend `DataTable` — a different blast
radius, worth landing on its own.

New domain code, both under `src/Report/` and therefore inside Infection's
scope (`infection.json.dist` lists the directory):

- `ReportMetrics` — readonly VO carrying the four tile figures plus the
  sub-line figures under them.
- `ReportMetricsCalculator` — counts in PHP from the finders that already
  exist (`findAllOrderedByName()` × 2, `findAllOrderedByDateDesc()`).
  **No new repository methods on purpose:** `src/Repository` is in
  Infection's scope too, so a `countActive()` there would need its own
  mutation-killing tests for nothing —
  `ActivitySummaryCalculator` already walks every activity the same way,
  and this app serves one user over hundreds of rows.

Two domain rules, both reused rather than invented:

- **Planned = future-dated**, the same rule the Activities mobile cards
  ship with, so the word means one thing app-wide.
- **`ActivityDuration::Other` contributes 0.0 days** by design
  ([ADR 0008](../adr/0008-add-other-activity-duration-with-free-text-companion-field.md)
  — its free-text value is never parsed). A total-days tile that silently
  under-reports is a trap, so `ReportMetrics::hasUncounted()` exists and
  the sub-line reads `all-time · N not counted` whenever such rows exist.

The top-5 list is `byVolunteer|slice(0, 5)` in the template rather than a
second service: `summarizeByVolunteer()` already sorts by total days
descending, so there's no second sort and no second pass over the data.

Printing needed no CSS file — Tailwind's built-in `print:` variant does
it, with `print:hidden` on the base template's `<header>` and on the
button itself. Verified twice over: a functional test asserts both
elements carry the class, and the compiled `var/tailwind/app.built.css`
really contains the `@media print` rule.

Two fixes came out of screenshotting the result rather than trusting the
markup: the mockup's 🖨 emoji rendered as a tofu box (no emoji font in the
container, and no reason to gamble on the viewer's), so the button is
plain text; and at 375px the volunteer names — the entire point of a
recognition card — were truncating to `Stewart M…`, so the stat now drops
to its own line below `sm`.

## 2026-08-31 — Mobile card layout for the Activities index (mockup 4)

Below `md` (768px), `templates/activity/index.html.twig` now renders one
card per activity instead of the horizontally-scrolling table: date (plus
a `· Planned` tag on future-dated rows, in brand colour) and the duration
pill on the top line, volunteer name prominent, `Project · Activity type`
as a secondary line, Edit/Delete as plain text links. **The desktop table
is unchanged** — this is a mobile-only swap, exactly as the 2026-08-28
mockup review validated it. Both renderings ship in every response and
CSS picks one (`hidden md:block` / `md:hidden`), so only one is ever in
the accessibility tree.

The breakpoint is `md` (768px), not the `sm` the nav hamburger uses: the
two swaps answer different questions — the nav swaps when the header runs
out of room, the table swaps when six columns stop fitting. Measured in
headless Chrome against the seeded worst case ("UCESCO Mombasa Youth
Centre" × "Vocational training support"), the table's own width:

| viewport | table behaviour              |
| -------- | ---------------------------- |
| 414px    | scrolls sideways (+236px)    |
| 640px    | scrolls sideways (+10px)     |
| 700px    | fits, cells wrap to 3 lines  |
| 768px    | fits, cells wrap             |
| 900px    | fits, cells wrap to 2 lines  |
| ≥1024px  | one line per row             |

Those widths come from a four-row sample; a screenshot of the full seeded
list at 1280px still shows a few two-line rows (the long project names,
and the `Wed 26 Aug 2026` date column). Desktop wrapping was left alone —
the table is deliberately untouched here — but tightening the date column
is an easy future win if it ever grates.

Sideways scroll — the thing the cards exist to kill — stops at roughly
650px, so `sm` (640px) sits just on the wrong side of it and has no margin
for a project name longer than today's longest. `md` clears it with ~120px
to spare. Above `md` the table degrades gracefully (it wraps, it doesn't
scroll), so the wrapped 768–1023px band is an acceptable middle rather than
a second failure. If that band ever feels cramped, the honest next move is
`lg` plus a two-column card grid — but that goes beyond what the mockup
review validated, so it wasn't done pre-emptively.

Supporting changes:

- `templates/components/RowActions.html.twig` — new anonymous
  TwigComponent holding the Edit link and the CSRF-protected,
  `data-turbo-confirm`-guarded Delete form. `DataTable` and the card list
  both render it (only the classes differ, via props), so the delete
  path can't drift between the two.
- `ActivityController::index()` adds a `planned` boolean per row,
  deliberately outside `DataTable`'s own `cells`/`actions` row shape,
  which ignores it. That keeps the "planned" tag card-only rather than
  changing the desktop date column.
- Two functional tests: one asserting both renderings coexist (table rows
  *and* cards, each card carrying a real POST delete token), one
  asserting only the future-dated card is tagged `Planned`.

Escort is still absent from both the table and the cards — *where* escort
should be read back out remains open in
[`next-steps.md`](next-steps.md), and this change deliberately didn't
pre-empt it.

## 2026-08-31 — Activity forms only offer active volunteers

Both activity-logging forms listed every volunteer, active or not, and
merely annotated the departed ones — `ActivityFormType` appended
"(inactive)" to the label, `BatchActivityFormType` passed a
`data-inactive` attribute the Stimulus controller used to grey out chips
and suggestions. Since UCESCO's volunteers come for a few weeks and then
leave, that list only grows, and none of it is a valid answer to "who
attended?". Both `query_builder` closures now filter on
`v.isActive = true`, and the dead greying-out branches came out of
`batch_activity_form_controller.js` with the `data-inactive` attribute.

**One deliberate exception:** `ActivityFormType` also backs
`/activities/{id}/edit`, so its query adds an `orWhere` for the
activity's *current* volunteer, read from `$options['data']`. Without it,
fixing a typo on a historical entry would have found its volunteer
missing from the dropdown and forced reassigning the activity to someone
who wasn't there. `editingAnOldActivityKeepsItsSinceDeactivatedVolunteerSelectable`
covers exactly that.

Typing those two closures' `$repo` parameters as `VolunteerRepository`
(instead of leaving them untyped, as the remaining ones still are)
resolved six baselined `method.nonObject` findings, so
`phpstan-baseline.neon` shrank from 62 to 56 — regenerated after
confirming the run reported nothing but over-counted entries, so no new
error was absorbed.

## 2026-08-31 — Work-focused home screen (mockup 1)

`DashboardController`'s `app_home` route stopped being a bare redirect to
`/reports` and now renders `templates/dashboard/index.html.twig`: Today's
roster and "Projects needing volunteers" in the centre column, Tomorrow's
roster in the side panel, per the 2026-08-28 mockup review. The firewall's
`default_target_path` moved from `report_index` to `app_home` at the same
time — Reports was only ever the landing page because `app_home` had
nothing of its own to show.

Three new pieces of domain code, all under `src/Report/` so the existing
Infection scope picks them up:

- `ActivityRepository::findByDate()` — one day's rows, oldest id first,
  with the escort left-joined in. Insertion order, not project name, is
  what orders the roster: it's the closest proxy for the order the VM
  actually worked through the day.
- `RosterBuilder` + `Roster`/`RosterGroup`/`RosterSlot` readonly value
  objects — groups a day by project *and* activity type (the WhatsApp
  format's `📍 Site (Activity type)` heading), dedupes escorts per group,
  and marks a volunteer's second site of the same day `(later)`.
  `Roster::toWhatsAppText()` renders the schedule body only, with no
  greeting: every real message opens in the VM's own voice, so that half
  is deliberately left to her.
- `QuietProjectFinder` + `QuietProject`/`QuietProjectSeverity`, backed by
  a new `ProjectRepository::findActiveWithLastActivityDate()`.

**The one real product decision here:** the mockup's "Needs a check-in"
listed stale volunteers *and* stale projects. It shipped as **"Projects
needing volunteers"** — projects only, never volunteers. UCESCO's
volunteers come for a few weeks and then leave, often for good, so a
volunteer who has stopped appearing has usually finished rather than
lapsed; most won't be back, and a list of people who are never returning
would bury the signal that is actionable. A quiet project always is: it's
somewhere the next arriving volunteer can be assigned, which is what the
VM actually does with this list. A project with nothing ever logged
against it therefore stays on the list and is aged from its `createdAt`
rather than being skipped — that's the site most in need of attention.
Amber at 30+ days, red at 50+, quietest first; anything dated today or
later counts as zero days, so work already planned ahead never reads as
stale. Each row carries an "Assign volunteers" link into the batch form
pre-filled with that project. **Do not "complete" this by adding
volunteers back in** — their absence is the decision.

This is the first read path for `Activity::$accompaniedBy`: both rosters
render an "Accompanied by ..." line per project group, closing the loop
with the message format the VM already sends by hand.

Supporting changes: a `clipboard` Stimulus controller (clipboard API,
falling back to selecting the always-selectable textarea when the browser
refuses access); `/activities/new-batch` now honours a `?date=Y-m-d`
query param (and a `?project=` one, for "Assign volunteers") so
"+ Plan activity" opens already dated tomorrow;
`AppStory` seeds a today/tomorrow roster relative to load time plus the
three named escorts (nothing seeded escorts before, and fixed fixture
dates would have left the new screen looking empty).

Tests: `DashboardControllerTest` (6 functional cases) plus a new
`tests/Integration/` directory holding `RosterBuilderTest` and
`QuietProjectFinderTest`, which exercise the date/severity rules directly by
passing a fixed "today" into the finder rather than manipulating
fixtures' `createdAt`. The E2E smoke test and two functional tests that
asserted a post-login redirect to `/reports` were updated to `/`.

## 2026-08-31 — Escort as a field on the single-activity forms

Write-path parity for `Activity::$accompaniedBy`, which until now could
only be set from the batch form: `ActivityFormType` (used by both
`/activities/new` and `/activities/{id}/edit`) gained the same optional
`accompaniedBy` `EntityType` — escorts ordered by name, `— No escort
recorded —` placeholder, label "Accompanied by" — placed between the
duration fields and `notes`. It binds to the `accompaniedBy` property
directly, where the batch form's equivalent field is called `escort`
because it's backed by `BatchActivityInput` rather than the entity. No
entity, migration, controller or template change was needed; the shared
Tailwind form theme renders it.

Two `ActivityControllerTest` cases cover it: the Bright Achievers worked
example now logs an escort and asserts it persisted, and a new
`escortCanBeSetAndClearedFromTheSingleActivityEditForm` walks the edit
round-trip — logged with no escort, corrected to one, then cleared back
to none. That second case needed a `reloadActivity()` helper that clears
the entity manager first: the manager the test holds keeps the
pre-submission object in its identity map, so a plain `find()` after a
form submission silently returned the stale escort and the clear step
looked like it had failed. The new `query_builder` closure adds one more
occurrence of the untyped-`$repo` PHPStan pattern already baselined for
this file (and for `BatchActivityFormType`), so two baseline counts went
3 → 4.

Reading escort back out — an Activities-index column, mobile-card line,
or per-escort report — remains deliberately unbuilt and undesigned; see
next-steps.md's "Escort display and reporting".

## 2026-08-31 — Batch/group activity logging form (mockup 2)

Second half of next-steps.md's item 1, now the batch/group activity
logging form itself is done too: `/activities/new-batch`
(`ActivityController::newBatch()`), backed by an unbound
`BatchActivityFormType` (`data_class` → the new `App\Dto\BatchActivityInput`,
not an entity — one submission fans out into one `Activity` per selected
volunteer, all sharing the same date/project/type/duration/escort/notes).
Matches the validated mockup: native date input with "Today"/"Tomorrow"
quick-set buttons, the same expanded duration radio group as the
single-activity form with the "Other" free-text field enabled/disabled
live, an optional single-select `accompaniedBy` (Escort) field, and the
deliberate scope increase — a chips + search-autocomplete attendee picker
(keyboard ↑/↓/Enter, muted "Inactive" pill for inactive volunteers) built
on a real `EntityType` multiple/expanded checkbox group progressively
enhanced by a new Stimulus controller
(`assets/controllers/batch_activity_form_controller.js`), so the raw
checkboxes stay the actual form-submission mechanism. "Save" and "Save
and add another" are separate submit buttons (`name="save_action"`)
read in the controller to decide the redirect target. A cross-field
"Other" duration requires free text) validation is wired as a
`Assert\Callback` on the DTO form, same message as the single-activity
form's entity-level callback. Five functional tests added to
`ActivityControllerTest`. The Activities index page also gained a "Log
group activity" button next to the existing "Log activity" one.

## 2026-08-31 — Escort entity, migration, and 6th CRUD area

First half of next-steps.md's item 1: the `Escort` lookup entity
(id/name/`isActive`, same shape as `ActivityType`) plus a nullable
`Activity::$accompaniedBy` (`ManyToOne` → `Escort`) — schema captured
in `migrations/Version20260831150157.php`. Added the `EscortController`/
`EscortFormType`/`EscortRepository` (with the same
`countReferencingActivities()` delete-guard as the other four lookup
repositories) and `templates/escort/` (index/new/edit/_form, following
the `ActivityType` CRUD area's shape exactly), an `EscortFactory` for
tests and fixtures, an "Escorts" link in the Settings nav, and five
functional tests (`EscortControllerTest`). The batch/group activity
logging form (mockup 2) that consumes `accompaniedBy` is done too —
see the entry above.

**Dev-environment note (unrelated to this feature):** the local SQLite
database's `doctrine_migration_versions` table had been reset to empty
while the actual schema from all five prior migrations was still
present, so `doctrine:migrations:migrate` failed on
`Version20260824204146` with "table user already exists". Fixed by
backfilling version tracking for the five already-applied migrations
via `doctrine:migrations:version --add --range-from=... --range-to=...`
before running the new one normally. `doctrine:schema:validate`
confirms the schema is in sync after.

## 2026-08-31 — Volunteer timeline "show" page

Implemented mockup 3 from the 2026-08-28 validated screen designs
([`next-steps.md`](next-steps.md)): the one CRUD area still missing a
show view. New `volunteer_show` route (`GET /volunteers/{id}`) renders
`templates/volunteer/show.html.twig` — header with Active/Inactive
status pill and "Volunteer since" date, an "At a glance" card (email,
phone with a non-blocking "+ Add" nudge when missing, activities
logged, total days, most-recent-activity date tagged "Planned" when
future-dated), a Notes card with an inline Edit link, and a
reverse-chronological activity timeline (hollow marker on planned/
future entries, solid on past ones). `ActivityRepository` gained
`findByVolunteerOrderedByDateDesc()`, same join/order shape as
`findAllOrderedByDateDesc()`; the "at a glance" stats are computed
directly in the controller from that per-volunteer list rather than
touching `ActivitySummaryCalculator` (which aggregates across all
volunteers). Added a "View" action ahead of Edit/Delete in the
Volunteers index `DataTable`, and two functional tests
(`VolunteerControllerTest`).

## 2026-08-28 — Five System-of-Work mockups reviewed and validated

Before touching any Twig for the System of Work initiatives and the P1/P2
UX-review findings in [`next-steps.md`](next-steps.md), five key screens
were mocked up as interactive HTML Artifacts and reviewed: the
work-focused home screen, the batch/group activity logging form, the
volunteer detail/timeline page, a card-based mobile layout for the
Activities index, and a Reports dashboard (KPI tiles, top-volunteers
recognition, tabbed + paginated tables, print-friendly view). All five
are now validated; the resulting design decisions are recorded directly
in `next-steps.md` as the implementation-ready spec. No application code
changed — mockups only.

## 2026-08-28 — Activity duration "Other" option

Decision and implementation recorded in
[ADR 0008](../adr/0008-add-other-activity-duration-with-free-text-companion-field.md).

## 2026-08-27 — Dev fixtures: real UCESCO project/activity-type breadth

`src/Story/AppStory.php` gained the projects, activity types, and
activities observed in the Volunteer Manager's real nightly WhatsApp
roster messages (clinics, schools, MVETI, orphanage, beach clean-ups,
home visits, orientation, etc. — see
[`docs/brainstorm/04-system-of-work-for-the-volunteer-manager.md`](../brainstorm/04-system-of-work-for-the-volunteer-manager.md#evidence-from-real-roster-messages-2026-08-27)),
so dev fixtures and future mockups stop looking like a two-project toy
dataset ("Bright Achievers" / "Mombasa Youth Centre" only).

Commit: `4b07740`.

## 2026-08-27 — P0 UX review fixes

Implemented the six P0 (quick, low-risk) findings from the 2026-08-26
UX review in [`next-steps.md`](next-steps.md):

- Required-field indicator (`*`) in `templates/form/tailwind_theme.html.twig`'s
  `form_label` block, driven off the form view's existing `required` var.
  Fixed a follow-on regression this surfaced: the block bypassed
  Symfony's `form_label_content` fallback (humanizing the field name
  when no explicit `label` option is set), so `Activity`'s `date`,
  `volunteer`, and `project` fields — none of which set a `label`
  option — rendered with no label text at all, leaving a lone floating
  `*`. Fixed by delegating to `parent()` in a `form_label_content`
  override instead of reimplementing label rendering, and by excluding
  individual `radio`/`checkbox` choice options (via `block_prefixes`)
  from the asterisk so expanded fields like `Duration` don't repeat it
  on every option ("Half day \*", "Full day \*").
- Flash messages gained `role="alert"`/`aria-live="polite"` and an
  explicit `error`/`warning`/`success` style map in `base.html.twig`,
  replacing the old binary `label == 'error'` check.
- Visible focus ring (`focus:ring-2 focus:ring-brand-500`) restored on
  all text/select/textarea inputs, alongside the existing border-color
  swap — helps both keyboard users and outdoor/bright-sunlight phone use.
- Copy consistency: `user/index.html.twig`'s empty state now matches the
  other 4 CRUD areas' call-to-action phrasing; Activity's edit page
  title/heading and delete-confirm string now name the date and
  volunteer instead of the generic "Edit activity"/"Delete this
  activity entry?", avoiding a wrong-day accidental delete.
- Nav accessibility: `aria-expanded` on both nav toggles (wired through
  `nav_controller.js`), `aria-current="page"` on the active nav link,
  and Escape-to-close on the Settings dropdown.
- Removed the dead, redundant `sm:hidden` on the mobile nav panel in
  `base.html.twig` (already always-`hidden`, toggled by JS).

## 2026-08-26 — Top nav reorder, Panther-based ad-hoc UI screenshots

Reordered the top nav to lead with the frequently-used items
(Activities, Volunteers, Reports) and moved Projects, Activity Types,
and Users into a Settings dropdown (desktop) / grouped mobile section.

Also added `scripts/panther-screenshot.php`, a standalone CLI script
for ad-hoc visual verification during UI work — logs in, navigates,
resizes the viewport, waits out Turbo Drive, clicks, and screenshots
the already-running dev app via Panther's `Client::createChromeClient()`,
no PHPUnit/Playwright/Node needed. The tool choice itself is recorded
in [ADR 0007](../adr/0007-adopt-panther-for-adhoc-visual-verification.md);
this entry is the implementation, plus two related fixes it surfaced:

- `init: true` on the `php` service (`compose.yaml`) so Docker's
  built-in `tini` reaps orphaned Chrome subprocesses left behind by
  Panther, instead of letting them accumulate as zombies.
- Granted the `Skill` tool to the `adr-scribe` and `context-capturer`
  subagents — both instructed themselves to "run the
  `ronan-markdown-lint` skill" but had no way to actually invoke it,
  silently falling back to a manual lint pass every time.

Commit: `3cd5afa`.

## 2026-08-26 — ucesco-theme brand identity, app:user:create CLI flags

UCESCO mark/logo, favicon, and brand color palette applied across
templates. The identity decision itself is recorded in
[ADR 0006](../adr/0006-adopt-ucesco-theme-for-brand-identity.md); this
entry is the implementation. Also: `app:user:create` gained
`--email`/`--full-name`/`--password` options so seeding a dev user is
a one-liner instead of interactive prompts (dev/local convenience
only — the password then lands in plaintext in shell history).

Commit: `5f23469`.

## 2026-08-26 — Panther E2E test, scoped Infection, Foundry dev fixtures

Closed out the rest of phase 11 and all of phase 12 from the original
build plan (`docs/brainstorm/02-volunteer-manager-v0.1-context.md`).
The tooling choice itself (Panther for one smoke test, Infection scoped
to the app's real logic) was already decided in
[ADR 0004](../adr/0004-adopt-phpunit-phpat-infection-panther-for-volunteer-manager-tests.md) —
this entry is the implementation:

- `tests/E2E/VolunteerManagerSmokeTest.php` — one real-browser test:
  login, create a Volunteer, create an Activity, see it in the list and
  `/reports`, plus a mobile-nav check. Runs Panther's own built-in
  webserver against `public/`, not a second Docker service.
- `infection.json.dist` — mutation testing scoped to
  `src/Report/ActivitySummaryCalculator.php` and the delete-guard
  methods in the four repositories that have one. Baseline: 100%
  Mutation Code Coverage, 100% Covered Code MSI (59/59 killed).
- `src/Story/AppStory.php` — Foundry `DemoDataStory` seeding the Bright
  Achievers worked example plus extra volunteers/activities. Load with
  `docker compose exec php bin/console foundry:load-fixtures --no-interaction`.
- `AGENTS.md` sanity pass — removed the "not yet wired" caveat, documented
  the new `tests/E2E/` suite and `composer infection`.

Commits: `47a6f42`, `5f24564`.

## 2026-08-24 — Quality tooling adopted

PHPStan (level `max`), PHP-CS-Fixer, Rector (dry-run only), and
`composer audit`, aggregated into `composer quality` and enforced by a
local pre-commit hook. The tool choices themselves are recorded in
[ADR 0005](../adr/0005-adopt-phpstan-php-cs-fixer-rector-composer-audit.md);
this entry just marks the implementation done.

Commit: `7320cf1`.

## 2026-08-24 — v0.1 feature build: CRUD, auth, reports, tests

The initial build, per
[ADR 0003](../adr/0003-adopt-docker-frankenphp-symfony-sqlite-tailwind-for-volunteer-manager.md):
Docker+FrankenPHP scaffolding, Doctrine/SQLite, authentication, all five
CRUD areas (Volunteers, Projects, Activity Types, Users, Activities),
the Reports view, and the 25-test functional suite (PHPUnit + Foundry +
DAMA, per [ADR 0004](../adr/0004-adopt-phpunit-phpat-infection-panther-for-volunteer-manager-tests.md)).

Commits: `53f2a88`..`1ff9748` (see `git log` for the full list).
