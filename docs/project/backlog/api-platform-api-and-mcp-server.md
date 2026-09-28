---
title: A full API and an MCP server with API Platform, behind OAuth
created: 2026-09-28
source: ronan
status: needs-decision
size: L
priority: later
labels: [security, data]
---

## Why

Today every record is only reachable through the HTML screens and their
CSV/xlsx exports. Covering the app with an API, plus an MCP endpoint over
it, would let other tools and AI agents read (and possibly write) volunteers,
stays, projects, programs and activities without scraping pages. It is also
the route [ask-ucesco-ai-assistant](ask-ucesco-ai-assistant.md) already names
as its priority: answer questions through an agent calling the app, not by
shipping the database to a model.

## Done when

An ADR decides, before any code:

- **Read-only or read-write.** Every write path carries checks the schema
  doesn't enforce: stay resolution, the branch loop and the program's type
  and dates (`ActivityController::resolveStays()`, ADRs 0026, 0027 and
  0030). A writable resource has to route through the same checks. It can't
  have its own.
- **Which authorization server issues the tokens.** API Platform's MCP
  endpoint is an OAuth 2.0 *resource server* only (a stateless firewall
  with an access-token handler, RFC 9728 metadata at
  `/.well-known/oauth-protected-resource`). Something still has to issue
  the tokens: a bundle inside the app, or an external identity provider.
- **Who gets a token, and scoped to what.** The app has two roles today.
  An agent reading every volunteer is at least as sensitive as `/usage`
  (admin-only) and wider than the export rule in
  [ADR 0029](../../adr/0029-export-every-list-view-to-csv-or-xlsx-with-openspout-open-to-all-signed-in-staff.md),
  which assumes only signed-in staff.
- **What the API never exposes.** The passport number is never serialized
  ([ADR 0033](../../adr/0033-encrypt-passport-numbers-at-rest-with-a-runtime-sodium-key.md)).
  Every exposed personal-data field needs a purpose and a transfer basis
  under [ADR 0034](../../adr/0034-comply-with-kenyas-data-protection-act-2019.md),
  especially when the caller is a third-party AI model.

Then: the resources are exposed with explicit operations and serialization
groups, `/mcp` sits behind the chosen OAuth setup, and `RouteSmokeTest`
still passes (it walks every GET route, the API's included).

## Notes & links

- API Platform on Symfony: <https://api-platform.com/docs/symfony/>
- MCP: <https://api-platform.com/docs/core/mcp/>. It needs
  `api-platform/mcp` and `symfony/mcp-bundle`, and serves HTTP JSON-RPC on
  `/mcp`. Tools are declared with `#[McpTool]` plus a state processor,
  resources with `#[McpResource]` plus a state provider.
- `allow-contrib: false`: wire every new bundle by hand, never flip the
  flag ([ADR 0003](../../adr/0003-adopt-docker-frankenphp-symfony-sqlite-tailwind-for-volunteer-manager.md)).
- The app's derived values are not columns: a volunteer's "active"
  (a stay covers today) and an activity's project (via its program). The
  API has to expose them as computed fields.
- If the ADR concludes that the only consumer is the AI assistant, a small
  set of read-only MCP tools over `src/Report/` may be all that's needed,
  with no full REST surface. It should make that argument explicitly.
- May need splitting once decided (read API / write API / MCP / OAuth).
