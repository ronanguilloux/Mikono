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
- **Gemini app:** [Google's help page](https://support.google.com/gemini/answer/17209137)
  requires 18 or over, in the US, a personal account (no work or school
  account), English, and Keep Activity on. The maintainer tried it from Europe
  on 2026-11-01: it doesn't work there, so this was dropped.
- **Its data terms:** with Keep Activity on, Google may train on the activity
  and people may review it, kept 18 months by default and 3 years once reviewed
  ([Privacy Hub](https://support.google.com/gemini/answer/13594961)). The setting
  is required, so there is no opt-out.
- **Mac app 1.138:** the help page names only the web app, but the app's own
  strings show a "Cloud MCP" tab (URL, standard OAuth, client ID and secret under
  "Advanced Settings" when the server has no DCR) and a "Local MCP" tab. Windows
  not checked.
- **If the region opens:** Google's redirect URI isn't documented, in the help
  page or the app. Read it from the access log on the first `/authorize`. A public
  Mikono client ignores any secret it's sent, so the secret field isn't a blocker.
- The bundle's client id column is 32 characters, so a CIMD URL doesn't fit
  without a custom client class.
