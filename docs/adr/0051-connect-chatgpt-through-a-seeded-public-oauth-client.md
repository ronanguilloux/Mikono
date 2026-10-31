# 0051. Connect ChatGPT through a seeded public OAuth client

Date: 2026-10-31

## Status

Proposed

## Context

[ADR 0050](0050-expose-a-read-only-api-and-mcp-server-to-claude-behind-oauth.md)
serves Mikono's data read-only to OAuth tokens, for admins only. The maintainer
wants ChatGPT to read it as well.

Two things are open. The stable redirect below has not been verified against
the live ChatGPT form, and the connection is an accepted exception to
[ADR 0034](0034-comply-with-kenyas-data-protection-act-2019.md) rule 8.

The forces:

- **Where an admin connects.** ChatGPT takes a custom MCP server from
  chatgpt.com/plugins, under Add custom MCP server. The maintainer reports this
  is available on their ChatGPT Free account, and their screenshots show the
  form. [OpenAI's public docs](https://developers.openai.com/plugins/build/auth)
  found on 2026-10-31 list only Business, Enterprise/Edu and Pro, and the help
  article could not be read. So Free availability rests on the maintainer's
  observation.
- **The callback URL.** The form showed a per-connection callback,
  `https://chatgpt.com/connector/oauth/{callback_id}`, which one seeded exact
  list can't cover for several admins. OpenAI's docs say the stable URI
  `https://chatgpt.com/connector_platform_oauth_redirect` is used when the
  server supports RFC 9207.
- **Any admin connects themselves** (ADR 0050, rule 9), so connecting can't need
  a migration or a console step per admin.
- **Audience.** ChatGPT sends `resource`, and OpenAI's docs say the token's
  audience "should" match it. The OAuth bundle ignores the parameter.
- **What ChatGPT reads leaves Kenya.** It is personal data, sensitive data
  included, and OpenAI is a new processor under ADR 0034 rule 8. Free is a
  consumer plan.

## Decision

**An admin connects ChatGPT to Mikono as a user-defined OAuth client, through a
seeded public client `chatgpt` with one stable redirect, which `iss` on every
`/authorize` redirect makes possible.**

**1. The client.** A migration seeds `chatgpt`, on the pattern of ADR 0050 rule
4: no secret, `S256` PKCE, the `read` scope, and the single redirect
`https://chatgpt.com/connector_platform_oauth_redirect`.

**2. Connecting.** The admin selects "User-Defined OAuth Client", pastes
`chatgpt`, leaves the secret empty and sets the token auth method to none.
`/connect-ai/chatgpt` gives the steps.

**3. `iss` on every `/authorize` redirect (RFC 9207).**
`App\Security\OAuthIssuerListener` appends `iss` to every code or error redirect
from `/authorize`, and discovery sets
`authorization_response_iss_parameter_supported: true`. This applies to all
clients, Claude included.

- **Discovery promises it on errors too**, so the listener must see every code
  or error redirect. The redirect to `/login` carries neither and stays
  untouched.
- **The bundle has no option for this.** By OpenAI's docs, removing the listener
  or the discovery flag moves ChatGPT back to a per-connection callback, which
  the seeded redirect refuses.

**4. `resource` is ignored.** The token's `aud` stays the client id. Mikono has
one resource, so nothing is checked. This is unverified against ChatGPT. A
second resource would reopen it.

**5. Audit.** `api_access` gains a `client` column, taken from the OAuth token,
with `claude` as the default for older rows. `/usage` and the user page show it.
`ApiAccess` names the known assistants, and a client it doesn't name shows as
its id, so a new vendor adds its label there.

**6. Disconnect and pruning are not per vendor.** Disconnect revokes all the
admin's tokens, across both vendors, and the page says to reconnect any
assistant still wanted. `app:oauth:prune` is client-agnostic.

**7. Compliance: an accepted, open exception.** The maintainer reports UCESCO
agreed to OpenAI receiving this data. Who agreed and when are not on file.

Free is a consumer plan. As far as known, no data processing agreement applies,
and chats may train models unless the user opts out. Neither point was verified
against OpenAI's current terms on 2026-10-31.

That leaves two requirements of ADR 0034 unmet or unproven:

- **Rule 8 and s.42(2), the written processor contract: unmet.** This ADR can
  name no contract terms and no transfer basis under rule 3.
- **s.49(1), consent to process sensitive data outside Kenya: unproven for
  OpenAI.** This covers emergency contacts and gender among others.

This is an accepted, open exception by the maintainer's decision, not
compliance. It is tracked in
[`chatgpt-connection-paperwork`](../project/backlog/chatgpt-connection-paperwork.md).
`/connect-ai/chatgpt` warns about it and tells the admin to turn off "Improve
the model for everyone". Rule 8's duties on notice, withdrawal, correction and
impact assessment apply to OpenAI as they do to Anthropic.

## Consequences

- **Positive:**
  - An admin connects ChatGPT with no console step and no secret, as for Claude.
  - One seeded redirect covers every admin.
  - `/usage` and the user page name the assistant behind each read.
  - ChatGPT reads through the same allowlist, admin check and audit as Claude.
- **Negative / trade-offs:**
  - **This decision rests on an unverified behaviour.** After deploy, it must be
    checked that Callback URL in the ChatGPT form shows the stable URI. If it
    doesn't, the connection fails on the redirect and this decision needs
    revisiting.
  - **Free availability may not hold.** It rests on one maintainer's account,
    and OpenAI's docs don't list Free.
  - **ChatGPT's handling of `aud` is unverified.** It may refuse a token whose
    audience is the client id.
  - **Volunteers' data, sensitive data included, reaches OpenAI outside Kenya
    with no written processor contract and no proof of s.49(1) consent.** ADR
    0034's exposure applies for as long as the exception stands.
  - **The training opt-out sits in each admin's ChatGPT account.** Mikono can't
    see it or enforce it, and it is the only safeguard against model training
    named here.
  - An admin who disconnects one assistant loses both.
- **Reversibility:** deactivating the `chatgpt` client and revoking its tokens
  ends the connection, and the code is cheap to remove. `iss` is harmless to
  keep for other clients. Data OpenAI has already received can't be recalled.

## Alternatives considered

### 1. Client ID Metadata Documents

**Rejected.** This includes mapping ChatGPT's URL client id to `chatgpt` by
exact match. It saves one pasted value, but it advertises a capability Mikono
doesn't implement and takes more code.

### 2. Dynamic Client Registration

**Rejected.** It needs an open registration endpoint and creates one client per
connection.

### 3. Listing each admin's callback URL in the client

**Rejected.** Every new admin would need a migration or a console step, which
contradicts ADR 0050 rule 9.

### 4. Requiring a Business workspace

**Rejected.** This was the recommended option. The maintainer chose Free, and
rule 7 states what that costs. The paperwork card keeps a move to a plan with a
data processing agreement as one way to close the exception.
