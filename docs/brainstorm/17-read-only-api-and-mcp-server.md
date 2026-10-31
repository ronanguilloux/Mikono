# Brainstorm — Read-only API and MCP server for asking Claude about UCESCO's data

**Date:** 2026-10-31
**Author:** <ronan.guilloux@gmail.com>
**Related:** [`CLAUDE.md`](../../CLAUDE.md),
[ask-ucesco-ai-assistant card](../project/backlog/ask-ucesco-ai-assistant.md),
[API and MCP server card](../project/backlog/api-platform-api-and-mcp-server.md),
[ADR 0003](../adr/0003-adopt-docker-frankenphp-symfony-sqlite-tailwind-for-volunteer-manager.md),
[ADR 0026](../adr/0026-attach-volunteers-to-branches-through-dated-stays-and-derive-active-from-them.md),
[ADR 0027](../adr/0027-tie-projects-to-a-branch-and-require-an-activitys-project-to-share-its-stays-branch.md),
[ADR 0029](../adr/0029-export-every-list-view-to-csv-or-xlsx-with-openspout-open-to-all-signed-in-staff.md),
[ADR 0030](../adr/0030-insert-programs-between-projects-and-activities.md),
[ADR 0033](../adr/0033-encrypt-passport-numbers-at-rest-with-a-runtime-sodium-key.md),
[ADR 0034](../adr/0034-comply-with-kenyas-data-protection-act-2019.md),
[ADR 0037](../adr/0037-record-a-volunteers-gender-for-accommodation-pairing.md),
[`../adr/`](../adr/)

## Primary audience

Ronan, the maintainer, who asks Claude questions about UCESCO's data from
claude.ai, Claude Desktop, mobile and Claude Code. He is also the reader
who comes back to this file wondering why the surface is this wide and
why a whole REST API sits under four tools. The sample questions in the
[ask-ucesco-ai-assistant card](../project/backlog/ask-ucesco-ai-assistant.md)
drove the wish.

## Desired impact

Ronan connects each of the four Claude clients once, signs in through
Mikono's own login form, and gets answers from live data without opening
an export. The milestone has worked if all of this holds:

- claude.ai, Desktop, mobile and Claude Code each complete the OAuth sign-in
  and answer a question from production data.
- Every record in an answer carries its `appUrl`, so a person can check it
  on the screen it came from. The card makes these links mandatory: they
  are what keeps a wrong answer from reading as fact.
- The three sample questions the card found answerable get answered: total
  volunteers, one volunteer's work on one project, and a per-volunteer
  summary.
- The other four stay unanswerable, because the card established that they
  have no data behind them, and this milestone adds none:
  - **Hours:** `ActivityDuration` records half day, full day or other.
  - **Children supported:** no beneficiary entity exists.
  - **Contact logs:** no contact log exists. The card also rejects the
    lapsed-volunteer premise behind this question.
  - **Deadlines:** no deadline field exists.

  A figure Claude gives for any of them is an inference, and counts as a
  wrong answer.
- A decrypted passport number never appears in a response, and every write
  through the API is refused. Both are asserted in
  `tests/Functional/Api/ApiResourceTest.php`.
- Only a person holding `ROLE_ADMIN` and the `read` scope gets in. Demoting
  an admin ends their tokens at once.

## The "Options Not Taken"

- **Claude Code only, with static tokens.** This was the recommendation in
  the session, and it would have avoided an authorization server. Ronan
  chose claude.ai, Desktop and mobile as well, and hosted connectors can't
  send a custom header, so a static token can't reach them. That choice is
  what forced real OAuth.
- **Read and write from the start.** Rejected for now. A writable resource
  would need the checks inside `ActivityController::resolveStays()` and its
  siblings extracted from the forms (ADRs 0026, 0027 and 0030). Free text
  in a record could also carry prompt injection into a session that holds
  write tools.
- **A narrower volunteer field set.** Asked which fields to expose, Ronan
  answered "100% all data", beyond the options offered. Asked to confirm,
  he chose every field except the decrypted passport number. Emergency
  contacts, gender, notes, the legacy record, date of birth, passport
  expiry and the photo are therefore all exposed.
- **Build then sign off, or gate the sensitive fields.** Both were offered
  for go-live. Ronan took neither, stating that UCESCO has already
  authorised this and holds the consents (see Constraints).
- **A small set of MCP tools with no REST surface.** The API card raised
  this for the case where the assistant is the only consumer. Rejected:
  Ronan asked for API routes for all meaningful data.
- **Hand-written JSON controllers.** Rejected: API Platform gives
  pagination, filters, OpenAPI and problem+json, all of which this app
  would otherwise write and test itself across fourteen resources.
- **`api-platform/mcp`.** Tried in the spike and dropped, for three
  reasons:
  - it is marked `@experimental`;
  - it pins `mcp/sdk` to `^0.8`, which holds `symfony/mcp-bundle` at 0.14.1;
  - its tools don't reuse the Doctrine provider's filters, so each resource
    would need a custom provider and input DTOs.

  Four native tools in `src/Mcp/MikonoTools.php` replace it. Each one
  sub-requests `/api`, so REST and MCP share one allowlist of what can be
  seen.
- **One serialization group across resources.** Rejected: API Platform
  would embed related records recursively, so an activity would carry a
  whole volunteer. Each resource has its own group instead, plus `api:ref`
  holding one label for a relation.
- **Dynamic client registration or CIMD.** Unnecessary for one
  pre-registered client. Add it if other organisations' Claude workspaces
  connect.
- **OAuth keys as mounted files.** Rejected in favour of base64 env vars:
  league's `CryptKey` accepts key content, and `deploy.env` already holds
  the other secrets.

## Constraints

**The go-live authorisation is a statement, not a document.** Ronan states
that UCESCO has already authorised this exposure and holds the consents.
That is recorded here as his statement. The written reference has not been
supplied.

**ADR 0034 sets what that reference has to cover.** The card treats a model
run abroad as a transfer under rule 3:

- Emergency contacts and gender are sensitive personal data, so each
  volunteer's consent is needed before they are processed out of Kenya,
  and UCESCO bears the burden of proving it.
- UCESCO keeps the proof of safeguards, including the recipient's processor
  terms.
- A volunteer may withdraw consent, and their data must then leave that
  processing. Nothing in this build handles that: the API has no
  per-volunteer exclusion.
- The photo stays non-biometric only while nothing processes it to
  characterise the person. `get_volunteer_photo` hands it to a vision model,
  so face matching must stay out of how it's used.
- Rule 8 requires an ADR naming any new AI assistant service, its contract
  terms and its transfer basis. That ADR is the place for the reference.

**Writes are deferred debt.** The checks a writable resource needs still
live inside controllers and forms, so read-only is also what the code can
safely carry today.

**The MCP packages are pre-1.0.** The lock holds `mcp/sdk` 0.9.0 and
`symfony/mcp-bundle` 0.15.0, so an upgrade can change the behaviour the
findings below depend on.

**The repo is public.** No key or real volunteer name goes in it, which is
why the OAuth keys are runtime variables.

**The spike and live testing** found four things worth keeping:

- `GET /mcp` answers 405 at once, so no stream holds a FrankenPHP worker.
- The consent form needed `data-turbo="false"`: Turbo's fetch can't follow
  the redirect to claude.ai.
- The `filters` argument needed an explicit object schema, or every
  filtered call failed.
- The Flex recipe wrote a generated passphrase and encryption key into the
  committed `.env`. They were removed before any commit, so a later recipe
  run needs the same check.
