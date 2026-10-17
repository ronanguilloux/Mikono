# 0046. Link each volunteer to their Google Drive folder

Date: 2026-10-11

## Status

Accepted

## Context

UCESCO staff already keep each volunteer's documents (passport scans,
agreements and the like) in a Google Drive folder, and want to reach it
from the volunteer's record. In-app document storage is a separate, still
open piece of work
([volunteer-document-attachments](../project/backlog/volunteer-document-attachments.md)).
A new personal-data field must state its purpose, sensitivity, retention
and transfer basis under
[ADR 0034](0034-comply-with-kenyas-data-protection-act-2019.md).

## Decision

A volunteer carries an optional `driveFolderUrl`: a link to their folder
that the app stores and renders but never follows.

- Nullable, 255 characters at most, `Assert\Url(requireTld: true)`,
  entered through a `UrlType` with `default_protocol: https`. A blank field
  is stored as `null`, never `''`
  ([ADR 0014](0014-make-a-volunteers-last-name-optional.md)).
- Labelled "GDrive folder URL" on the form, after the passport fields, and
  "GDrive folder" on the profile, after Passport and before Skills. It renders
  as a link with `target="_blank" rel="noopener noreferrer nofollow"`: the
  opened page gets no handle on the app's window, no referrer and no
  endorsement.
- It does not count towards `isProfileIncomplete()`. Like the passport, not
  every volunteer has one.
- Any valid URL is accepted; there is no `drive.google.com` host check.
  Add one if wrong links start appearing.
- **Purpose:** to find a volunteer's documents from their record.
- **Sensitivity:** the URL itself is low-sensitivity. It points to sensitive
  documents (IDs, possibly family or bank details), and access to those is
  governed by UCESCO's Drive sharing settings, not by this app. The app
  never fetches, embeds or proxies the folder and sends nothing to Google.
  Anyone signed in who can view the profile sees the link, so **the folder
  must never be shared as "anyone with the link"**.
- **Retention:** kept with the volunteer record and deleted with it.
- **Transfer:** none by the app. Holding the documents at Google is UCESCO's
  existing arrangement and outside this app's scope.
- **Exports:** not in any per-list export. It goes into the admin
  whole-database zip automatically
  ([ADR 0039](0039-export-the-whole-database-as-an-admin-only-zip-of-per-table-csvs.md)).
  It is not a secret, so no drop list changes.

## Consequences

- **Positive:** staff reach a volunteer's documents in one click, without
  the app storing any of them.
- **Negative / trade-offs:** protecting the documents depends on Drive
  sharing that the app can neither see nor enforce. A wrong or stale link
  goes unnoticed until someone clicks it.
- **Reversibility:** cheap. Dropping one nullable column and its form row
  loses nothing but the links.

## Alternatives considered

### 1. Wait for in-app document storage

**Rejected.** That card still needs its own storage decision (blobs or
files, video or not, on a small production box) and is sized large. The
link answers the staff's need now. It is a stopgap, not a replacement.

### 2. Accept only `drive.google.com` URLs

**Rejected.** No wrong links have shown up yet, and a host allow-list would
block a move to another sharing service for no proven gain.
