# Brainstorm — Volunteer profile and photo

**Date:** 2026-09-17
**Author:** <ronan.guilloux@gmail.com>
**Related:** [`CLAUDE.md`](../../CLAUDE.md),
[ADR 0032](../adr/0032-store-volunteer-profile-fields-as-optional-and-photos-as-re-encoded-jpeg-blobs-in-sqlite.md),
[documents card](../project/backlog/volunteer-document-attachments.md),
[ADR 0012](../adr/0012-seed-fixtures-from-the-real-whatsapp-roster-archive.md),
[ADR 0014](../adr/0014-make-a-volunteers-last-name-optional.md),
[ADR 0017](../adr/0017-host-production-on-gandicloud-vps-in-france.md),
[ADR 0026](../adr/0026-attach-volunteers-to-branches-through-dated-stays-and-derive-active-from-them.md),
[ADR 0029](../adr/0029-export-every-list-view-to-csv-or-xlsx-with-openspout-open-to-all-signed-in-staff.md),
[ADR 0030](../adr/0030-insert-programs-between-projects-and-activities.md),
[`docs/adr/`](../adr/)

---

## Primary audience

The Volunteer Manager (Edna) and UCESCO staff, who often use the app on a
phone. Source: Ronan, working session.

## Desired impact

This is slice 1 of the backlog card "Add profile fields and file
attachments to volunteers" (`volunteer-profile-fields-and-attachments.md`,
deleted once this slice shipped; see git history).
A volunteer's page becomes a real profile. Volunteers come from many
countries, most of them through Volunteer World, so the profile records:

- nationality and country of residence (Ronan wants both);
- full date of birth;
- profession, as free text;
- skills, interests and emergency contacts, as textareas;
- one photo.

It also shows the volunteer's branch of attachment, derived from stays: the
current stay, or else the most recent one. There is no new column for it.

In hindsight, success looks like this:

- Edna can take a photo on her phone and upload it straight from the
  profile, with no resizing first.
- A profile with gaps says so, with an "Incomplete profile" pill and
  "Not on file · + Add" links (Phone already works this way), and saving
  still works.
- A stored photo has no GPS or other metadata, and it displays the right
  way up.
- List pages load no photo bytes.
- None of the new fields shows up in the volunteers CSV/xlsx export, in the
  fixtures or in the repo.

Document attachments (about 20 files per volunteer) are **out of scope**
and move to a separate card.

## The shape this landed on

1. **Fields are optional, with a nudge.** The card asked for mandatory
   fields. Instead, gaps are shown and never block a save. Making fields
   required later needs no migration.
2. **The photo is a BLOB in SQLite,** in its own `volunteer_photo` table.
   `Volunteer` holds an owning, lazy one-to-one link to it, so lists never
   load the bytes. At about 100 volunteers × 150 KB, that's around 15 MB in
   total.
3. **Uploads are re-encoded on the server with GD** as a JPEG of at most
   800 px, quality about 82. The image gains two PHP extensions, `gd` and
   `exif`. `exif` reads the Orientation tag so the rotation can be applied
   before the metadata is dropped. Sources over 25 megapixels are refused.
   Accepted formats are JPEG, PNG and WebP. The input's `accept` attribute
   makes iOS convert HEIC photos to JPEG before upload.
4. **Privacy.** The new fields stay out of the list export. Fixtures never
   hold real contact details or real photos, and the test photo is a JPEG
   drawn with GD. The full date of birth is kept, because paperwork needs
   it. Photos are served only through an authenticated controller route,
   with `Cache-Control: private`.
5. **Programs.** The card was written before Programs
   ([ADR 0030](../adr/0030-insert-programs-between-projects-and-activities.md)).
   The profile timeline now shows each activity's program next to its
   project and branch.

## The "Options Not Taken"

- **Every field required.** This clashes with
  [ADR 0014](../adr/0014-make-a-volunteers-last-name-optional.md): Edna must
  be able to record someone she has just met by first name only. The dev
  fixtures come from the real roster archive
  ([ADR 0012](../adr/0012-seed-fixtures-from-the-real-whatsapp-roster-archive.md)),
  which holds none of these facts, and inventing them is forbidden. Existing
  rows can't be backfilled with true values either.
- **Fields required on edit only.** Fixing a typo on an old record would
  then mean filling in facts nobody has.
- **Photos as files on the `db_data` volume** (`var/data/photos/{id}.jpg`).
  `scripts/backup-db.sh` only snapshots the `.db` file with `VACUUM INTO`.
  The photo folder would need its own archive step in the backup, in the
  restore drill and in the planned off-site encrypted copy
  ([brainstorm 08](08-off-site-encrypted-backups.md)). Deleting a volunteer
  would also have to clean up the file. At 15 MB, that isn't worth it.
  Revisit this for documents, which are larger and more numerous.
- **Storing the photo as uploaded.** Phone photos weigh 3–8 MB, more than
  PHP's default 2 MB upload limit, so most would be refused. They also keep
  their EXIF data, including GPS, so a photo taken in Kibera would reveal
  where it was taken.
- **Birth year instead of full date of birth.** It would be less sensitive,
  but Ronan chose the full date because UCESCO's paperwork asks for it.
- **Matching skills or profession to a program's suggested roles.** This
  could come later. For now both stay free text, like suggested roles in
  [ADR 0030](../adr/0030-insert-programs-between-projects-and-activities.md).

## Constraints

- **Personal data under two laws.** The server is in France
  ([ADR 0017](../adr/0017-host-production-on-gandicloud-vps-in-france.md)),
  and Kenya's Data Protection Act 2019 also applies. Emergency contacts and
  dates of birth are sensitive, and
  [ADR 0029](../adr/0029-export-every-list-view-to-csv-or-xlsx-with-openspout-open-to-all-signed-in-staff.md)
  already flags bulk export of personal data as a risk.
- **The repo is public.** No real contact details, dates of birth or photos
  in fixtures, tests or docs.
- **A 1 GB VPS.** GD decodes the whole bitmap in memory, hence the
  25-megapixel cap.
- **Phones first.** Uploads come straight from a phone camera: large files,
  HEIC on iOS, and an EXIF orientation tag instead of rotated pixels.
- **Derive, don't store.** The branch of attachment follows
  [ADR 0026](../adr/0026-attach-volunteers-to-branches-through-dated-stays-and-derive-active-from-them.md),
  like "active".
- **The image changes.** Adding `gd` and `exif` means rebuilding the image,
  both locally and in CI.

## Open for later

- **The document attachments card** needs its own storage design: BLOBs or
  files, allowed types and size limits, the soft cap of about 20 files,
  what happens on delete, and the effect on backups.
