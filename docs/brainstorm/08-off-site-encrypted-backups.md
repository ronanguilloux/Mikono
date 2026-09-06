# Brainstorm — Off-site encrypted backups: who can decrypt, who can delete

**Date:** 2026-09-06
**Author:** ronan.guilloux@gmail.com
**Related:** [`CLAUDE.md`](../../CLAUDE.md),
[`scripts/backup-db.sh`](../../scripts/backup-db.sh),
[`docs/project/deployment-plan.md`](../project/deployment-plan.md) §7,
[`docs/project/hosting-plan.md`](../project/hosting-plan.md) §4,
[`docs/project/next-steps.md`](../project/next-steps.md),
[ADR 0017](../adr/0017-host-production-on-gandicloud-vps-in-france.md),
[`docs/adr/`](../adr/)

---

## Primary audience

Whoever is about to run `rclone config`, and the ADR that has to exist
before the *production* copy of the volunteer database leaves the server.

## Desired impact

A nightly copy of the database that survives the machine it came from, that
a compromise of that machine cannot read, and that somebody can actually
restore from a year later at 3am. Success is not "a bucket exists" — it is a
drill that pulled a file down, decrypted it, restored it, and proved a
marker created after the backup came back **gone**.

## Where things stand (2026-09-06)

[`scripts/backup-db.sh`](../../scripts/backup-db.sh) runs nightly at 02:15
Africa/Nairobi on `srv-mikono`. It snapshots the live database with
`VACUUM INTO` through `pdo_sqlite` (no downtime, no `sqlite3` binary),
verifies it with `PRAGMA integrity_check`, copies it out to
`/opt/mikono/backups`, and prunes past `KEEP_DAYS=30`. The restore drill in
[`deployment-plan.md`](../project/deployment-plan.md) §7 was re-run on the
real box on 2026-09-05 and passed.

Every one of those copies is on the same disk as the database it protects.
The script's own last line says so:

> Reminder: this is still on the same machine as the app. Copy it off-site.

[`next-steps.md`](../project/next-steps.md) makes this the **first** of
three things gating real volunteer data on a server, and
[`hosting-plan.md`](../project/hosting-plan.md) §4 says why it deserves the
care: that one file is the entire volunteer database — every name, contact
detail and work record — concentrated in the single artifact most worth
protecting.

## The research: three decisions, not one

`next-steps.md` currently prescribes a mechanism — "`rclone` with a crypt
remote, key held off the server" — and calls what remains "mechanical".
Working the question properly turned up three independent decisions
underneath it, and the prescribed tool answers the first one differently
than that sentence claims.

### 1. Who can decrypt?

The cron runs unattended, so *something* on the box must be able to encrypt.
The question is whether that same thing can decrypt.

**Symmetric tools cannot separate the two.** rclone crypt, restic and borg
all use one secret for both directions, so it lives in a config file on the
server. rclone's `obscure` is explicitly not encryption — it is reversible
by design, and documented as such. Root on that box therefore reads every
backup ever taken, including the ones already off-site.

**Asymmetric encryption separates them.** `age -R recipients.txt` encrypts
to public keys; the private key is never installed on the server. Same cron,
same automation, no secret on the box to rotate — and a full compromise of
the VPS yields a pile of ciphertext it cannot open.

That distinction is worth recording rather than assuming, because
`next-steps.md`'s own phrase — *"key held off the server"* — is only
literally achievable in the asymmetric branch. With rclone crypt the key is
on the server by construction; what is held off it is a *copy* of the key,
for the restore. Both are defensible postures. They are not the same
posture, and the file currently names the one that cannot deliver the
property the same sentence asks for.

### 2. Who can delete?

Whatever credential the cron holds can also destroy what it wrote. This is
the failure mode that turns a backup strategy into a single point of
failure, and it is invisible until the day it matters. Mitigations,
cheapest first:

- **Versioning plus lifecycle expiry** on the destination, so deletes become
  tombstones rather than losses.
- **An append-only credential** — a Backblaze application key without delete
  permission, restic's `--append-only` behind a rest-server.
- **Invert the direction**: an off-site machine *pulls* over SSH and the
  server holds no credential at all. Strongest, and it needs a second
  always-on machine this project does not have.

One concrete trap belongs here regardless of destination: **`rclone sync` is
the wrong verb.** Sync mirrors deletions, so a wiped `/opt/mikono/backups`
— exactly the event a backup exists for — propagates to the only copy that
survived it, on the next scheduled run. `copy` is add-only. At megabyte
scale, never pruning the remote at all is simpler than any retention policy
and removes the need for a delete-capable credential entirely.

### 3. What does a restore cost when it is actually needed?

The off-site copy adds *fetch* and *decrypt* in front of the drill that
already exists. A single encrypted file needs one static binary and a key
file. A restic or borg **repository** needs the tool, the passphrase, a
healthy repo, and enough familiarity to find the right snapshot — recalled
under pressure, a year after it was set up.

Deduplication, the main reason to reach for those tools, buys nothing here:
the database is ~94 KB today (the size the 2026-09-05 drill recorded) and
megabytes at its worst. Thirty daily copies is a rounding error on any
destination.

## The shape this landed on

**`age` for encryption, plain `rclone copy` for transport, Google Drive as
the destination, UAT first.**

- `age -R` takes a file of public keys and **any** matching private key
  decrypts. Use **two recipients**: the maintainer's, and a UCESCO escrow
  key. That is a one-line answer to the governance concentration
  [ADR 0017](../adr/0017-host-production-on-gandicloud-vps-in-france.md)
  already names in its Consequences — as things stand, if the maintainer
  disappears, UCESCO cannot decrypt their own volunteer database. With Drive
  as the destination the risk *stacks* rather than diversifying, because the
  account holder and the key holder are the same person. Adding the second
  recipient later re-encrypts nothing already uploaded, so a missing UCESCO
  key is not a reason to wait.
- **`--drive-scope drive.file`** limits rclone to files it created itself.
  Without it, the OAuth refresh token sitting on the VPS grants read/write
  to the maintainer's entire Google Drive — a real regression against an S3
  key scoped to one bucket, and the single most important flag in this
  design.
- **Encrypt `deploy.env` alongside the database.**
  [`deployment-plan.md`](../project/deployment-plan.md) §4 currently relies
  on someone having remembered to paste `APP_SECRET` into a password
  manager; losing it invalidates every session and signed URL. One extra
  `age` invocation makes the off-site copy a self-contained restore instead.
- **Prune the local `.age` files on the same clock as `KEEP_DAYS`.**
  `backup-db.sh` prunes `mikono-*.db`; their `.age` siblings are a different
  glob and would otherwise accumulate on the server forever. The *remote*
  keeps everything.
- **Chain the push to the snapshot with `&&`** in the existing single cron
  line rather than adding a second entry, so an upload can never run against
  a snapshot that failed.
- Verification is a hash compare — `rclone check --one-way --checksum`.
  Drive exposes an MD5 for binary files, and because the uploaded object
  *is* the local `.age` file byte for byte, the hashes are directly
  comparable. (An rclone **crypt** remote cannot offer this: it does not
  provide hashes for encrypted data.)

## The honest tension: Drive versus ADR 0017

[ADR 0017](../adr/0017-host-production-on-gandicloud-vps-in-france.md)
states that the off-site backup destination *"follows the server into
Europe rather than waiting on the region question — that question is now
answered."* A personal Google Drive account offers no region guarantee.
Choosing it is a deviation from a recorded decision, and this file exists
partly so that deviation is not made silently.

It is a defensible deviation, and `age` is the whole reason: Google holds
ciphertext for which it has no key, and the exporter holds the keys
exclusively. That is precisely the shape — strong encryption with keys
retained outside the recipient's reach — that makes such a transfer
arguable rather than a straightforward problem.
[`hosting-plan.md`](../project/hosting-plan.md) §4 already reasons this way
about the destination inheriting §5's residency question, and
`next-steps.md` already notes that the ciphertext layer "is what keeps the
destination cheaply changeable later".

**For UAT the argument is not needed at all.** `deploy.mikono.guilloux.org`
holds no real data by design, so there is no personal data to transfer and
nothing to document. That is what makes Drive an easy yes for the first
pass, and it is why this file scopes the work to UAT.

**When production picks a destination, that is an ADR** — either one that
chooses Drive on the ciphertext argument and supersedes ADR 0017's sentence,
or one that picks an EU bucket and leaves ADR 0017 intact. The thing to
avoid is the UAT choice quietly becoming production's by default, which is
how a deviation becomes a decision nobody made.

## The "Options Not Taken"

- **rclone crypt** — the mechanism `next-steps.md` currently prescribes.
  Rejected for the asymmetric design: it puts the decryption secret on the
  box for no gain over `age`, its `obscure` step is reversible rather than
  encrypting, and crypt remotes cannot hash-verify what was uploaded. It is
  one tool instead of two, which is a real advantage, but not one worth
  paying for with "a compromised server reads every backup".
- **restic or borg** — a proper repository, with built-in retention
  (`forget --keep-daily`), integrity verification (`restic check`) and
  append-only mode as ransomware defence. Rejected on size and on restore
  ergonomics: deduplication is the draw and it buys nothing on a file
  measured in megabytes, the passphrase is symmetric and back on the box,
  and a repository format is worse than one encrypted file to restore from
  under pressure. Worth revisiting only if the data ever stops being small.
- **Pull-based backup over SSH** — an off-site machine fetches nightly with
  a restricted key, and the server holds no credential to the archive at
  all. The strongest answer to *who can delete*, and rejected only because
  it needs a second always-on machine that does not exist. A laptop that is
  usually on is not a backup schedule, and it fails silently.
- **Provider snapshots** — already ruled out twice, in
  [`hosting-plan.md`](../project/hosting-plan.md) §4 and in ADR 0017's
  Consequences, which records that Gandi's own documentation contradicts
  itself about whether they exist and that nobody has restored one. They
  also back up a *machine*, not the data, and they live at the same provider
  as the thing they protect.
- **An EU S3 bucket** (Scaleway Paris, OVH, Backblaze B2 in Amsterdam) —
  **not rejected, deferred.** It keeps ADR 0017 intact with no argument
  required, costs effectively nothing at this data volume, sits in a
  different failure domain from Gandi, and offers object lock and lifecycle
  rules that Drive does not. It is the live alternative for production's
  destination decision, and the reason that decision is worth taking
  deliberately rather than inheriting.

## Constraints

- **UAT only for now.** Production does not exist yet; it is gated on the
  September 2026 meeting with Nickson about a UCESCO subdomain, per
  [`next-steps.md`](../project/next-steps.md). Doing this on UAT first is a
  free rehearsal of exactly the procedure, in the spirit of
  [`deployment-plan.md`](../project/deployment-plan.md) §10 — and production
  gets its own key pair and its own folder, never UAT's.
- **Drive has no object lock and no lifecycle rules.** Its trash is a
  30-day recovery window, which is weak deletion resistance, not none. Never
  pruning the remote is the mitigation that fits the data size.
- **A Drive service account is not the easy path on a personal Gmail.**
  Service accounts have no storage quota of their own, so uploads into a
  folder shared by a personal account fail; that route effectively needs
  Workspace. Hence `--drive-scope drive.file` on a dedicated folder as the
  practical answer — and "does UCESCO have Workspace?" as one more question
  for Nickson, since it would also give the archive a UCESCO-owned home
  rather than a personal one.
- **The drill that counts restores from the off-site copy**, not the local
  one. Per [`deployment-plan.md`](../project/deployment-plan.md) §7, it only
  proves anything if something is lost: take the backup, create a
  `drill@example.invalid` marker the backup cannot contain, restore, and
  confirm the marker is **gone**. A restore that leaves the database looking
  identical has demonstrated nothing.
- **`age` and `rclone` are new packages on the server.** The minimal Debian
  cloud image gave `srv-mikono` neither, the same way it gave it no `cron`
  (§7). Both are in Debian trixie.
- Nothing here changes the application, the image, or CI. It is host-side
  operations only, which is also why it can be rehearsed without a deploy.
