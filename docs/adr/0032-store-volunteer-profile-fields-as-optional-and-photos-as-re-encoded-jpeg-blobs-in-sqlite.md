# 32. Store volunteer profile fields as optional and photos as re-encoded JPEG blobs in SQLite

Date: 2026-09-17

## Status

Accepted

## Context

The Volunteer Manager needs more than a name on a volunteer's record:
nationality, country of residence, date of birth, profession, skills,
interests, emergency contacts and a photo. The request asked for these
fields to be mandatory and for about twenty attachments per volunteer. The
narrative is in
[`docs/brainstorm/12-volunteer-profile-and-photo.md`](../brainstorm/12-volunteer-profile-and-photo.md).

These facts drive the decision:

- **Existing records cannot be truthfully backfilled.** The volunteers
  already on file come from the WhatsApp roster archive
  ([ADR 0012](0012-seed-fixtures-from-the-real-whatsapp-roster-archive.md)),
  which has no birth dates, nationalities or contacts.
  [ADR 0014](0014-make-a-volunteers-last-name-optional.md) already made the
  last name optional for the same reason.
- **This is personal data about people in Kenya, hosted in France.**
  Production runs in France
  ([ADR 0017](0017-host-production-on-gandicloud-vps-in-france.md)). The
  Kenya Data Protection Act 2019 and the GDPR both apply. A phone photo
  carries EXIF metadata, including GPS coordinates.
- **The repository is public.** No real photo or contact detail can ever
  reach a fixture or a test.
- **Backups copy one file.** `scripts/backup-db.sh` takes a hot
  `VACUUM INTO` copy of the SQLite database, and the off-site copy ships
  that file only.
- **Doctrine ORM 3 has no lazy-loaded columns.** A blob on `Volunteer`
  would be read by every volunteer list.

## Decision

**Every new volunteer profile field is optional, and a volunteer has at
most one photo, stored as a re-encoded JPEG blob in its own SQLite table
and served only to signed-in staff.**

**1. Optional profile fields.** `Volunteer` gains these nullable fields:

- `nationality` and `countryOfResidence`: ISO 3166-1 alpha-2 codes, entered
  through Symfony's `CountryType` (hence `symfony/intl`).
- `dateOfBirth`: `date_immutable`, which must be in the past. It is a full
  date, not a birth year, because the paperwork needs it.
- `profession`: string, 255 characters at most.
- `skills`, `interests`, `emergencyContacts`: text.

An empty submission is stored as `null`, never `''`, following the
optional-field convention of ADR 0014. Existing records are not
backfilled. The profile page shows an "Incomplete profile" pill and a
"Not on file · + Add" link for each missing value, so the gaps are visible
without blocking an edit.

**2. Branch of attachment is derived.** The profile shows the volunteer's
branch through `Volunteer::getBranchOfAttachment()`: the branch of the
current stay, else of the latest stay by `startDate`, per
[ADR 0026](0026-attach-volunteers-to-branches-through-dated-stays-and-derive-active-from-them.md).
There is no column for it.

**3. The photo lives in its own entity, on the owning side.**
`VolunteerPhoto` maps the `volunteer_photo` table (`id`, `bytes` BLOB,
`updated_at`). `Volunteer::$photo` is the owning, nullable `OneToOne`, with
cascade persist and remove, `orphanRemoval`, and `onDelete: SET NULL` on
the foreign key. Traps a future change must not undo:

- **Keep the foreign key on `volunteer`.** Doctrine always loads the
  inverse side of a `OneToOne` eagerly. Moving the key to
  `volunteer_photo` would make every volunteer list load the bytes.
- **Never move the bytes onto `Volunteer`.** There are no lazy columns.
- Deleting a volunteer deletes the photo.

**4. Uploads are re-encoded, never stored as sent.** `VolunteerFormType`
has an unmapped `FileType` with an `Image` constraint (JPEG, PNG or WebP,
10 MB at most, 25 megapixels at most), plus a `removePhoto` checkbox on
edit. The controller decodes the image with GD, applies the EXIF
Orientation tag, scales it to fit within 800 px and re-encodes it as a
JPEG at quality 82. Re-encoding strips all metadata, GPS included; a
shortcut that stores the upload as-is reopens that leak. This requires:

- the `gd` and `exif` PHP extensions in the Dockerfile's base stage;
- `upload_max_filesize=10M` and `post_max_size=12M` in
  `frankenphp/conf.d/10-app.ini`. The 2 MB PHP default rejects most phone
  photos.

**5. One authenticated route serves the photo.**
`GET /volunteers/{id}/photo` (`volunteer_photo`) returns `image/jpeg` with
`Cache-Control: private` and `Last-Modified` from `updatedAt`, and 404 when
there is no photo. The photo is never exposed as a public asset.

**6. Not exported.** The new fields and the photo stay out of the
volunteers export
([ADR 0029](0029-export-every-list-view-to-csv-or-xlsx-with-openspout-open-to-all-signed-in-staff.md)).
A spreadsheet leaves the app's access control the moment it is downloaded.

**7. Fixtures hold no personal data.** Fixtures leave the new fields
`null`. Tests use the synthetic image `tests/fixtures/volunteer-photo.jpg`.
Never commit a real photo or a real contact detail (ADR 0012).

**Out of scope:** document attachments. They are tracked on their own card,
[`volunteer-document-attachments.md`](../project/backlog/volunteer-document-attachments.md),
where the storage question is open again, since twenty documents per
volunteer is not the size of one 800 px photo.

## Consequences

- **Positive:** Old records stay editable, and the gaps show on the
  profile. The photo sits in the database file, so the existing backup and
  off-site copy include it with no change, and a volunteer delete cleans it
  up. List pages never read image bytes. Uploaded photos carry no location
  or device metadata.
- **Negative / trade-offs:** Profiles will stay incomplete until someone
  fills them in. The database file grows with photos: about 15 MB at the
  current volunteer count, which is small, but it is paid on every backup.
  The image needs two more PHP extensions and higher upload limits. The
  re-encoded photo is lossy and capped at 800 px.
- **Reversibility:** Making a field required later is a validation change
  once the data is filled in. Moving photos to files would take a
  migration that writes out each blob plus a backup change. The table
  boundary keeps that change away from `Volunteer`.

## Alternatives considered

### 1. Mandatory fields, as requested

**Rejected.** It contradicts ADR 0014 and ADR 0012: the roster archive has
no such data, so existing records could only be backfilled with invented
values.

### 2. Required on edit only

**Rejected.** Every old record would become uneditable until someone found
its missing data, which blocks routine corrections.

### 3. Photos as files on the `db_data` volume

**Rejected.** `backup-db.sh` (`VACUUM INTO`) and the off-site copy would
miss them, a volunteer delete would need its own file cleanup, and there
are more moving parts. At about 15 MB in total, blobs cost nothing
significant.

### 4. Photo as a column on `Volunteer`

**Rejected.** Doctrine 3 has no lazy columns, so every volunteer list
would load the bytes.

### 5. Inverse-side `OneToOne`, with the foreign key on the photo

**Rejected.** Doctrine always loads the inverse side of a `OneToOne`
eagerly, which is the same problem as a column.

### 6. Store uploads as sent

**Rejected.** The EXIF GPS coordinates would leak, and the 2 MB PHP
default would reject most phone photos.

### 7. Birth year instead of a full date of birth

**Rejected.** The paperwork the Volunteer Manager fills in needs the full
date.
