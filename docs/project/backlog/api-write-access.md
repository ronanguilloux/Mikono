---
title: Write access through the API and MCP
created: 2026-10-31
source: ronan
status: needs-decision
size: L
priority: later
labels: [security, data]
---

## Why

[ADR 0050](../../adr/0050-expose-a-read-only-api-and-mcp-server-to-claude-behind-oauth.md)
made the API read-only. Logging activities or stays from Claude would be
useful, but every write path carries checks the schema doesn't enforce.

## Done when

An ADR decides which writes, and:

- The checks leave the form layer so a processor can call them:
  `ActivityController::resolveStays()`, `unconfirmedSameDay()`, and the branch
  and date rules on stay, project and achievement edits. A writable resource
  routes through the same checks and has none of its own.
- A write path calls `touch()`, or `AuthorStamper` won't attribute it.
- A separate `write` scope exists, with its own consent wording.
- The ADR weighs prompt injection: free text in records reaches a session
  that would then hold write tools.
