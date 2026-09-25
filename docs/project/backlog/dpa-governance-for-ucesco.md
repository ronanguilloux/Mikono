---
title: UCESCO's data-protection paperwork under the Kenya DPA
created: 2026-09-25
source: ronan
status: blocked
size: M
priority: now
labels: [docs, security]
epic: production-readiness
---

## Why

UCESCO is the data controller for everything Mikono holds. Under
[ADR 0034](../../adr/0034-comply-with-kenyas-data-protection-act-2019.md) rule 11, a handful of the Act's duties are UCESCO's to
meet, and no code change meets them. Without them, UCESCO could be
running a registrable processing operation unregistered, which is an
offence under s.19(7). It would also have no proof of safeguards for
hosting in France (s.48), and nobody named for a data subject or the
Data Commissioner to contact. **Blocked on UCESCO**: someone there has to
own this. The maintainer can prepare the drafts.

## Done when

- **Registration:** UCESCO has checked, against the Data Protection
  (Registration of Data Controllers and Data Processors) Regulations 2021,
  whether it must register (s.18–19). The answer and its basis are written
  here. If it must, the certificate is held.
- **Contact point:** a named data-protection contact at UCESCO, with an
  email address. It is used in the privacy notice (s.29(e)) and in any
  breach notice (s.43(5)(e)). A formal DPO is optional under s.24, and the
  card records whether UCESCO appoints one.
- **Processor terms:** written terms exist with each processor (s.42(2)):
  - the maintainer, who operates the server;
  - Gandi, who hosts it (its standard DPA is enough).
- **Transfer file:** UCESCO keeps a one-page note on the transfer to
  France, covering the safeguards: EU law and the GDPR, encryption and
  access control. This is the "proof" in s.48(a).
- **DPIA screening:** a short written check of whether the processing is
  "likely to result in high risk" (s.31). If it is, the DPIA goes to the
  Data Commissioner 60 days before processing (s.31(5)).
- **Account ownership:** domain, DNS and server ownership move to UCESCO,
  or a dated decision to keep them where they are is written here
  ([ADR 0017](../../adr/0017-host-production-on-gandicloud-vps-in-france.md)).

## Notes & links

- The rules and section references: [ADR 0034](../../adr/0034-comply-with-kenyas-data-protection-act-2019.md).
- The Nickson meeting is the natural place to open this:
  [nickson-meeting-dns-and-hosting](nickson-meeting-dns-and-hosting.md).
- None of the four sources behind ADR 0034 gives the registration
  thresholds. Check them in the gazetted Regulations or with the ODPC
  (odpc.go.ke), not from a summary site.
- Not legal advice. If UCESCO has counsel, show counsel ADR 0034.
