---
title: Connect ChatGPT or Gemini
created: 2026-10-31
source: ronan
status: needs-decision
size: M
priority: later
labels: [security, data]
---

## Why

Admin → Connect an AI has pages for ChatGPT and Gemini saying why they aren't
available. UCESCO asked for Claude. Each other vendor is a new company receiving
volunteers' data.

## Done when

An ADR under
[ADR 0034](../../adr/0034-comply-with-kenyas-data-protection-act-2019.md) rule 8
names the vendor, its contract terms and its transfer basis, with UCESCO's
agreement. Then Mikono gets what that vendor needs, the vendor's page gets its
steps, and `app:oauth:prune` and Disconnect are checked against it.

## Notes & links

- **ChatGPT:** custom MCP needs a Business or Enterprise workspace with developer
  mode, S256 PKCE, and CIMD or Dynamic Client Registration. It sends a `resource`
  parameter and expects a matching token audience, and it takes no API keys:
  <https://developers.openai.com/plugins/build/auth>.
- **Gemini Enterprise:** takes a client ID and secret, with redirect
  `https://vertexaisearch.cloud.google.com/oauth-redirect`:
  <https://support.google.com/g/answer/17106276?hl=en>.
- **Gemini app:** no official documentation found on 2026-10-31. Community reports
  mention DCR and eligibility limits. Check before relying on either.
- `api_access` records that a read came through MCP, not which client made it,
  so with a second vendor it needs the client id
  (`OAuth2Token::getOAuthClientId()`) for `/usage` and the user page to name
  the vendor. Every row from before that is Claude.
- The bundle's client id column is 32 characters, so a CIMD URL doesn't fit
  without a custom client class.
