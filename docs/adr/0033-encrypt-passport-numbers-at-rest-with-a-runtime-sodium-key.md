# 33. Encrypt passport numbers at rest with a runtime sodium key

Date: 2026-09-25

## Status

Accepted

## Context

A volunteer's profile records passport information: an expiry date and a
number. The expiry date is a plain optional field
([ADR 0032](0032-store-volunteer-profile-fields-as-optional-and-photos-as-re-encoded-jpeg-blobs-in-sqlite.md)).
The number needs its own decision, for these reasons:

- **It is the most identifying value the app holds.** A passport number
  names one person to any border or civil authority, and it is identifying
  data under the Kenya Data Protection Act 2019.
- **The repository and the production image are public.** Nothing
  secret can live in the code, in a committed configuration file or in the
  image.
- **A copy of the database file is the realistic leak.**
  `scripts/backup-db.sh` takes a hot `VACUUM INTO` copy of the whole SQLite
  file, and that copy is shipped off-site. Whoever holds a backup holds
  every column in it. The app's login and screens protect nothing once the
  file itself has left the server.
- **Hosting assumption.** This decision was framed under the assumption
  that production will be hosted in Kenya. Under Kenyan hosting there is
  no cross-border transfer question, but protection at rest still matters,
  because backups leave the server either way.
  [ADR 0017](0017-host-production-on-gandicloud-vps-in-france.md), which
  hosts production on GandiCloud in France, is not reopened here and stays
  in force; it is to be revisited separately. This decision holds whichever
  country hosts the server.

## Decision

**A volunteer's passport number is stored only as a libsodium
`secretbox` ciphertext, sealed with a key supplied at runtime that never
travels with the database or the image.**

**1. Storage.** `Volunteer::$passportNumberCiphertext` (TEXT, nullable)
holds `v1:` followed by base64(nonce ‖ `sodium_crypto_secretbox` output),
with a fresh random nonce for every write. There is no plaintext column.

**2. One reader and writer.** `App\Security\PassportNumberCipher` is the
only code that encrypts or decrypts the value. The traps:

- **The form field `passportNumber` is unmapped.** The plain number never
  reaches the entity; a mapped field would let a future change persist it
  as-is.
- **The controller normalises, then encrypts.** It uppercases the number
  and strips spaces, as printed on the passport's data page, then stores
  the ciphertext. A blank field removes the number (`null`).
- **Validation on the plaintext:** 20 characters at most, letters, digits
  and spaces only.
- **Decrypted only where staff see it:** on the volunteer profile, and
  prefilled into the edit form. Every route involved is behind login.

**3. The key is a runtime secret.** `PASSPORT_ENCRYPTION_KEY` is the base64
encoding of 32 random bytes (`openssl rand -base64 32`).

- It is a runtime environment variable, never a build argument — the same
  rule as `APP_SECRET`, because the image is public and
  `composer dump-env prod` would bake a build-time value into it.
  `compose.prod.yaml` passes it to the container.
- The keys in `.env.dev` and `.env.test` are public and are for dev and
  test only. `.env` leaves the variable empty.
- **The cipher rejects a missing or wrong-length key when it is
  constructed.** Every volunteer route depends on it, so a production
  container started without the key fails loudly on the first volunteer
  page, rather than silently storing numbers it could never read back.
  Don't soften that into a lazy check or a fallback key.

**4. Key lifecycle.** The production key lives in the password manager.
Losing it loses every stored passport number: nothing can decrypt them.
**It must never travel with the off-site database copy** — a backup and
its key stored together are a plaintext backup. The `v1:` prefix names the
key generation and leaves room for rotation; no rotation command exists,
so rotating means writing one that decrypts under `v1` and re-encrypts
under `v2`.

**5. Never exported, never in fixtures.** The number stays out of the
volunteers export
([ADR 0029](0029-export-every-list-view-to-csv-or-xlsx-with-openspout-open-to-all-signed-in-staff.md)),
as ADR 0032 already rules for the profile fields, and fixtures leave it
`null` ([ADR 0012](0012-seed-fixtures-from-the-real-whatsapp-roster-archive.md)).

## Consequences

- **Positive:** A leaked `.db` file or backup carries no usable passport
  numbers. The protection costs one small class and needs no extension
  beyond PHP's bundled libsodium. A deployment that forgot the key fails
  at once instead of corrupting data.
- **Negative / trade-offs:** The app cannot search, sort or deduplicate by
  passport number, because every write produces a different ciphertext.
  There is one more secret to manage, and its loss is unrecoverable. The
  expiry date stays in plain text; on its own it does not identify anyone.
- **Reversibility:** Moderate. Dropping the encryption would take a
  one-off command that decrypts every row into a plain column while the
  key is still available. Rotating the key needs the same kind of command,
  which the version prefix already allows for.

## Alternatives considered

### 1. A plain passport-number column

**Rejected.** Every leaked backup would carry every number, and the
off-site copy is exactly the file most likely to leak.

### 2. A plain column, masked on the profile

**Rejected.** Masking protects the screen, not the file. The screen is
already behind login; the backup is what needs protecting.

### 3. A scanned passport on the documents card instead of a number

**Rejected.** The documents card
([`volunteer-document-attachments.md`](../project/backlog/volunteer-document-attachments.md))
is deferred, and a scan is more personal data, not less: it carries the
photo, the date of birth and the machine-readable zone on top of the
number.

### 4. A Doctrine custom type that encrypts transparently

**Rejected.** Doctrine types are instantiated statically and cannot
receive services, so the key would have to be read from a static or a
global, which escapes the container and its tests.

### 5. An entity listener that decrypts on `postLoad`

**Rejected.** Writing the plaintext back onto a loaded entity makes
Doctrine's change tracking see every loaded volunteer as modified, so each
flush would re-encrypt and rewrite rows nobody edited.
