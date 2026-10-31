# 0050. Expose a read-only API and MCP server to Claude behind OAuth, admin only

Date: 2026-10-31

## Status

Proposed

## Context

The maintainer wants to ask Claude questions about UCESCO's records from
claude.ai, Desktop, mobile and Claude Code, on live data and without
opening an export. The narrative is in
[`docs/brainstorm/17`](../brainstorm/17-read-only-api-and-mcp-server.md).

The forces:

- **Hosted connectors can't send a custom header**, so a static token
  reaches Claude Code and nothing else. Reaching claude.ai and mobile takes
  a real OAuth sign-in.
- **The rules live in the app.** A volunteer's status is derived, an
  activity's project comes through its program, and report figures come
  from `src/Report/`. A second reading of the data would drift from the
  screens.
- **Every write path carries checks the schema doesn't enforce**, and those
  checks sit in controllers and forms
  ([ADR 0026](0026-attach-volunteers-to-branches-through-dated-stays-and-derive-active-from-them.md),
  [ADR 0027](0027-tie-projects-to-a-branch-and-require-an-activitys-project-to-share-its-stays-branch.md),
  [ADR 0030](0030-insert-programs-between-projects-and-activities.md)).
- **What Claude reads leaves Kenya.** It is personal data, sensitive data
  included, processed by Anthropic
  ([ADR 0034](0034-comply-with-kenyas-data-protection-act-2019.md), rules 3
  and 8). The surface has to be bounded, attributed and revocable.
- **The access log can't say what was read.** Every MCP call is
  `POST /mcp` to Caddy.

## Decision

**Mikono serves its data read-only at `/api` and `/mcp`, to OAuth tokens
it issues itself, and only for a person who holds `ROLE_ADMIN`.**

**1. REST.** API Platform 5 serves 14 Doctrine resources, `Get` and
`GetCollection` only, so a write answers 405. Reports and the volunteer
photo aren't resources, and `App\Controller\ApiController` serves them.
The format is JSON-LD, and the OpenAPI document sits behind the token, with
no Swagger UI. `GET /api/volunteers` runs the query behind `/volunteers`,
so `status` keeps the meaning `VolunteerRepository` gives it.

**2. MCP.** `symfony/mcp-bundle` serves four native tools: `list_records`,
`get_record`, `get_report`, `get_volunteer_photo`. Each sub-requests
`/api`, so a tool can't expose, filter or audit differently from REST.

- **Sub-requests skip the firewall.** It has already run for `POST /mcp`,
  and `access_control` holds both paths to one rule.
- **A record path comes from a server-side list and ints.** The enums in a
  tool's schema are a hint to Claude, not the guard. A report's name is the
  one caller string in a path: it is URL-encoded into one segment, and
  `ApiController`'s `match` answers 404 to anything it doesn't list.
- **Every record carries `appUrl`**, the screen a person opens to check an
  answer, at any depth, so an embedded relation and a report row have one too.
  A report also carries `screen`, for figures that name no record.
- **`allowed_hosts: false` stays.** That check protects unauthenticated
  localhost servers from a browser, and every call here needs a token.

**3. Field exposure is default-deny.** Each resource has its own group
(`volunteer:read`, ...), and `api:ref` holds one label for a relation, so
a relation shows a label and not a whole record, and a new column stays
private until someone adds it to a group. Stay and Activity have no label:
a relation to a stay is its IRI.

- **Exposed:** every volunteer field except the passport number, including
  emergency contacts, gender, notes, the legacy record, date of birth,
  passport expiry and the photo. Staff email, roles and active state are
  exposed on `users`.
- **Never exposed:** the passport number and its ciphertext, password
  hashes, the OAuth tables, `api_access`, `login_attempt`, `usage_event`,
  and the Caddy and error logs.
- **Reports reduce entities through an `instanceof` list** in
  `ApiController`: a volunteer, program, project, branch, skill or activity
  type becomes its id and label, a stay its id. An entity missing from the
  list comes out empty, never whole.

**4. OAuth.** `league/oauth2-server-bundle`, inside the app, issues the
tokens.

- **Authorization code and refresh grants only.** Client credentials are
  off, because every token must belong to a person, to check `ROLE_ADMIN`
  against and to attribute reads to.
- **One pre-registered confidential client**, with no dynamic registration
  or CIMD. The `read` scope is the only one.
- **`/authorize` stays on the `main` firewall**, so a person signs in
  through the throttled login form. Only `/token` and the `.well-known`
  documents are public.
- **A consent screen** names the client, where it returns to, and that it
  can read every record and send it to that service. Its form carries
  `data-turbo="false"`, because Turbo's fetch can't follow the redirect
  off-site. A non-admin is refused here, with an OAuth error.
- **Access tokens last 1 hour, refresh tokens 30 days, rotated on use.**
- **Keys are runtime variables, never build arguments**, because the image
  is public: the private and public keys as base64 of the PEM, plus an
  encryption key. Losing them only signs connections out. A Flex recipe can
  write generated values into the committed `.env`, so a recipe run needs
  that check.
- **Discovery comes from `DEFAULT_URI`, not the request.** `resource` must
  equal the URL entered in Claude, and a 401 on either path points at
  discovery through `WWW-Authenticate`, which is how Claude starts sign-in.

**5. Admin only.** `access_control` requires `ROLE_ADMIN` and
`ROLE_OAUTH2_READ` through `allow_if`, because a `roles:` list passes on
any one of them. Roles load from the database on every request, so a
demotion or a deactivation ends a person's tokens at once. A browser
session can't reach the API.

**6. Audit.** Each successful read of a data route writes an `api_access`
row: who, the path, and whether it came through MCP. It never stores the
query string, because a search term is often someone's name. Rows are
pruned at 90 days and shown on `/usage`. The row is written on the
response, since `kernel.terminate` fires for the main request only and a
tool's reads are sub-requests.

**7. Malformed input** is refused with a 4xx, as everywhere in the app.
[ADR 0023](0023-degrade-malformed-query-input-to-a-default.md)
holds the API's mechanisms for it.

**8. A vision model sees the photo.** `get_volunteer_photo` hands it to
Claude, so face matching stays out of how it's used, or the photo becomes
biometric data ([ADR 0034](0034-comply-with-kenyas-data-protection-act-2019.md),
rule 2).

## Consequences

- **Positive:**
  - Reports reuse `src/Report/`, so their numbers match the screens.
  - REST and MCP share one allowlist and one audit trail.
  - Ending a connection is ordinary account work: deactivate the person or
    remove `ROLE_ADMIN`.
- **Negative / trade-offs:**
  - Anthropic becomes a processor outside Kenya for whatever is read, and
    any admin token reads every volunteer. Nothing here can exclude one
    person (ADR 0034, rule 8).
  - The OAuth tables hold client secrets and tokens, so the database export
    skips `oauth2_*` by prefix
    ([ADR 0039](0039-export-the-whole-database-as-an-admin-only-zip-of-per-table-csvs.md)).
  - `RouteSmokeTest` skips `api_` and `oauth` routes, and
    `tests/Functional/Api/` walks them instead. It reads the routes from
    the router and fails on a count, so a new Doctrine resource needs its
    own groups and a seeded row in that walk.
  - `mcp/sdk` and `symfony/mcp-bundle` are pre-1.0, so an upgrade can
    change what the tools rely on.
  - A connector needs public HTTPS reachable from Anthropic's
    `160.79.104.0/21`.
  - `/api/volunteers?search=`, called directly, puts the term in the Caddy
    access log like any query string (ADR 0034, rule 1). A tool's search
    travels in the `POST` body and doesn't.
  - A write through this surface is a new decision: the checks would have
    to leave the forms, and `AuthorStamper` needs `touch()`.
- **Reversibility:** the code is cheap to remove: the bundles, the
  attributes and the tables. Data Claude has already read can't be
  recalled.

## Alternatives considered

### 1. `api-platform/mcp`

**Rejected.** It is `@experimental`, pins `mcp/sdk` to `^0.8`, and needs a
provider and input DTOs per resource because its tools don't reuse the
Doctrine provider's filters.

### 2. One shared serialization group

**Rejected.** API Platform embeds related records recursively, so an
activity would carry a whole volunteer.

### 3. Static tokens only

**Rejected.** Hosted connectors can't send them, so claude.ai and mobile
couldn't connect.

### 4. An external identity provider

**Rejected.** It would be one more service receiving staff identities under
ADR 0034 rule 8, and the `user` table, the throttled login and `ROLE_ADMIN`
decide access here anyway.

### 5. MCP tools with no REST

**Rejected.** The maintainer asked for API routes for all meaningful data,
and the REST layer brings pagination, filters and OpenAPI that the tools
would otherwise reimplement.

### 6. Hand-written JSON controllers

**Rejected.** API Platform gives pagination, filters, OpenAPI and error
responses across 14 resources that this app would otherwise write and
test itself.

### 7. Read and write from the start

**Rejected.** The checks a write needs sit in controllers and forms, and
free text in a record could carry prompt injection into a session that
holds write tools.

### 8. A narrower volunteer field set

**Rejected.** The owner chose every field except the decrypted passport
number. ADR 0034 carries what that choice costs.

### 9. Dynamic client registration or CIMD

**Rejected.** One pre-registered client needs neither. Other organisations'
Claude workspaces connecting would reopen it.
