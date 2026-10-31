---
title: File UCESCO's authority and Anthropic's terms for the Claude connection
created: 2026-10-31
source: ronan
status: blocked
size: S
priority: now
labels: [security, data, docs]
---

## Why

**Blocked on UCESCO**, with no date for their answer. Until it comes,
[ADR 0034](../../adr/0034-comply-with-kenyas-data-protection-act-2019.md) and
the ADRs that rely on its rules (0017, 0035, 0036, 0037, 0038, 0039, 0041,
0042, 0043, 0044, 0046, 0050) stay `Proposed`. The features they describe keep
running. Set all thirteen back to `Accepted` when this card closes.

Reading Mikono through Claude sends volunteers' personal data, sensitive data
included, to Anthropic outside Kenya. The maintainer reports that UCESCO has
authorised this and holds the consents, but nothing on file shows it yet, and
under the Act UCESCO carries the burden of proving consent (s.32(1)), must be
able to show safeguards to the Data Commissioner (s.48), and needs a written
processor contract (s.42(2)).
[ADR 0034](../../adr/0034-comply-with-kenyas-data-protection-act-2019.md) rules
3 and 8 require all of this.

## Done when

- UCESCO's written authorisation is filed, and its date and signatory are
  recorded in ADR 0034.
- The Anthropic plan and terms in use are named there, with a data processing
  agreement in place. Consumer and commercial plans have different terms, so
  check which applies to the account that connects.
- UCESCO's consents cover processing outside Kenya for emergency contacts and
  gender (s.49(1)), and UCESCO can produce them.

## Notes & links

- Connecting and revoking: `deployment-plan.md` §4.
- A real consent record:
  [volunteer-privacy-notice-and-consent](volunteer-privacy-notice-and-consent.md).
- [ADR 0050](../../adr/0050-expose-a-read-only-api-and-mcp-server-to-claude-behind-oauth.md).
