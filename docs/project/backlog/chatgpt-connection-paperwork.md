---
title: File UCESCO's authority and OpenAI's terms for the ChatGPT connection
created: 2026-10-31
source: ronan
status: blocked
size: S
priority: now
labels: [security, data, docs]
---

## Why

Reading Mikono through ChatGPT sends volunteers' personal data, sensitive data
included, to OpenAI outside Kenya. The maintainer reports that UCESCO agreed,
but nothing on file shows who or when. The connection is built for ChatGPT
Free, a consumer plan, and ADR 0034 rule 8 and s.42(2) require a written
processor contract, which a Free account is not expected to provide. Check
OpenAI's current terms.

## Done when

- UCESCO's written authorisation for OpenAI is filed, with its date and
  signatory recorded in
  [ADR 0051](../../adr/0051-connect-chatgpt-through-a-seeded-public-oauth-client.md).
- OpenAI's plan and terms are named there, with a data processing agreement in
  place, or the connection moves to a plan that has one, or the `chatgpt` client
  is deactivated.
- UCESCO's consents cover OpenAI for emergency contacts and gender (s.49(1)).

## Notes & links

- [claude-connection-paperwork](claude-connection-paperwork.md) is the same
  work for Anthropic.
