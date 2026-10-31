---
title: Connect Gemini
created: 2026-10-31
source: ronan
status: needs-decision
size: M
priority: later
labels: [security, data]
---

## Why

Admin → Connect an AI Agent has a page for Gemini saying why it isn't
available. Each other vendor is a new company receiving
volunteers' data.

## Done when

An ADR under
[ADR 0034](../../adr/0034-comply-with-kenyas-data-protection-act-2019.md) rule 8
names the vendor, its contract terms and its transfer basis, with UCESCO's
agreement. Then Mikono gets what that vendor needs, the vendor's page gets its
steps, and `app:oauth:prune` and Disconnect are checked against it.

## Notes & links

- **Gemini Enterprise:** takes a client ID and secret, with redirect
  `https://vertexaisearch.cloud.google.com/oauth-redirect`:
  <https://support.google.com/g/answer/17106276?hl=en>.
- **Gemini app:** no official documentation found on 2026-10-31. Community reports
  mention DCR and eligibility limits. Check before relying on either.
- The bundle's client id column is 32 characters, so a CIMD URL doesn't fit
  without a custom client class.
