---
title: A searchable photo library with metadata
created: 2026-09-23
source: kingsley
status: needs-decision
size: L
priority: later
labels: [data, ux]
---

# A searchable photo library with metadata

## Why

Kingsley asked for photos tagged with date, location, project, activity,
people/program, photographer, description, volunteer and consent status, so
that a question like *"show me all photos of `<volunteer>` from the Bright
Achievers School project in September 2026"* has an answer. The stated use
is volunteer reports and social media, and on that evidence it would be the
most-used screen in the app.

**This does not extend the photo feature — it supersedes part of it.**
[ADR 0032](../../adr/0032-store-volunteer-profile-fields-as-optional-and-photos-as-re-encoded-jpeg-blobs-in-sqlite.md)
decided *one* photo per volunteer, an 800 px JPEG blob on a `OneToOne`,
re-encoded precisely so that **all metadata including EXIF GPS is stripped**.
This ask needs many photos, metadata kept, and a query surface over it. So
an ADR comes before any code.

## Done when

An ADR answers at least these, and only then does the library ship behind
it:

- **Many photos on what?** An activity, a project, or a volunteer. The tag
  list mixes all three, and the answer decides the schema.
- **Blobs or files?** ADR 0032 chose blobs on the argument that ~15 MB of
  800 px photos costs nothing. A library of report-quality photos is orders
  of magnitude more, and `scripts/backup-db.sh` copies the whole `.db` file
  on every backup. That argument does not carry over unexamined.
- **Is EXIF still stripped?** ADR 0032 strips it to stop GPS leaking. This
  card makes "location" a field people fill in deliberately. Those are not
  in conflict — recorded-on-purpose is not the same as leaked-by-default —
  but the ADR has to say so rather than quietly re-enable EXIF.
- **What does consent status mean operationally?** Who sets it, what it
  blocks, and what happens to a photo whose consent is withdrawn. This is
  the one field with legal weight: the stated purpose is publication, the
  subjects are in Kenya and the server is in France (ADR 0017, DPA 2019,
  GDPR).
- Whether the existing single profile photo becomes a row in the library or
  stays separate.

## Notes & links

- Shares its storage question with
  [volunteer-document-attachments](volunteer-document-attachments.md), which
  also has photos and video on its list. Decide both in one ADR, or say in
  that ADR why they differ.
- Size pressure is real and already tracked:
  [production-vps-sizing](production-vps-sizing.md) and
  [off-site-encrypted-backups](off-site-encrypted-backups.md) both assume a
  small database file.
- The search itself is the cheap half. `ListPaginator` plus a filtered
  `listQueryBuilder()` covers it the way `/activities` already does; the
  export comes free (ADR 0029) — though an export of photo *metadata*
  leaving the app is its own consent question.
- This repo is public. No real photo reaches a fixture; ADR 0032 already
  added the synthetic `tests/fixtures/volunteer-photo.jpg` for that reason.
