# Mikono and Kenya's Data Protection Act: compliance plan for UCESCO management

**Prepared:** 2 October 2026 · **For:** UCESCO top management ·
**Covers:** Mikono, the Volunteer Manager app, only

This is not legal advice. Have a Kenyan advocate or data protection
practitioner check it before UCESCO relies on it. Most of all, they
should check the transfer to France (§4) and the DPIA (§3).

---

## 1. Summary

**What Mikono is.** Mikono is a web app that UCESCO staff use to track
the volunteers who work at UCESCO's projects in Kibera and Mombasa. It
records who each volunteer is, when they stay, and what they do.
Volunteers never log in. Only UCESCO staff do.

**Why UCESCO has to act.** Mikono holds personal data about people in
Kenya, so the Data Protection Act 2019 ("the Act") applies. UCESCO decides
what the data is for, which makes UCESCO the **data controller**. The
legal duties fall on UCESCO, not on the developer.

**Why a DPIA is needed.** A Data Protection Impact Assessment (DPIA) is
required before "high-risk" processing starts (s.31, Regulation 49).
Mikono meets that test on one point:

- **It holds sensitive personal data.** A volunteer's emergency contacts
  usually name their parents or spouse. The Act counts "family details"
  as sensitive (s.2), and Regulation 49(1)(e) makes processing sensitive
  data high-risk.

The data also leaves Kenya, because the server is in France. That is not
a DPIA trigger in itself, but it adds its own duties (§4).

**The critical path is the ODPC's 60 days.** The DPIA goes to the Office
of the Data Protection Commissioner (ODPC) **60 days before processing
starts**. If the ODPC says nothing within 60 days, the DPIA counts as
approved (Reg. 52(3)). Real volunteer data is not on the production
server yet, so the clock can start cleanly now.

| Milestone | Target date |
| --- | --- |
| Management decisions taken (§2) | **16 October 2026** |
| DPIA signed and submitted to the ODPC | **30 October 2026** |
| Earliest go-live with real volunteer data, if the ODPC is silent | **29 December 2026** |

Each week the DPIA submission slips moves go-live back by a week. The
draft DPIA is ready for review:
[`DPIA-Mikono-draft.md`](DPIA-Mikono-draft.md).

**Most of the technical work is done.** Every page needs a login.
Passwords are hashed, traffic is encrypted, and passport numbers are
encrypted in the database. Photos are stripped of location data. No
analytics or tracking service is used (§7). What is missing is mostly
paperwork and decisions that only UCESCO can make.

---

## 2. Decisions only management can take

These six decisions block everything else. Take them by **16 October
2026**.

| # | Decision | Why it matters | Recommended answer |
| --- | --- | --- | --- |
| D1 | **Who owns data protection at UCESCO?** Name one person and give them an email address. | The privacy notice must give UCESCO's contact details (s.29(e)). A breach notice needs a contact point (s.43(5)(e)). The DPIA needs a signatory. | A senior staff member as "Data Protection Contact". A formal Data Protection Officer is optional for Mikono's scale (s.24). |
| D2 | **Does UCESCO register with the ODPC?** | Acting as an unregistered controller when registration is required is an offence (s.19(7)). | Check the Registration Regulations 2021 against UCESCO's situation. The earlier checklist said charities must register at any size, for a KES 4,000 fee and a KES 2,000 renewal. That claim and those figures need confirming on [odpc.go.ke](https://www.odpc.go.ke). If registration is required, apply now: certificates last 24 months. |
| D3 | **Keep the server in France, or move it to Kenya?** | France means a cross-border transfer: written proof of safeguards for all data (s.48), plus **each volunteer's consent** for sensitive data (s.49(1)). Kenya removes the consent step. | Stay in France ([ADR 0017](../../adr/0017-host-production-on-gandicloud-vps-in-france.md)): it is proven and costs about a third as much. Revisit if collecting consent at onboarding proves impractical. Moving later is cheap. |
| D4 | **How long are a volunteer's records kept after their last stay?** | Data may be kept only as long as its purpose needs (s.39). The retention schedule must state a period (Reg. 19). | A fixed period, for example 2 years after the last stay. After that, the record is anonymised: activity counts stay, and the name and contact details go. |
| D5 | **Do we keep emergency contacts at all, and in what form?** | This field alone makes Mikono "high-risk". It needs consent under s.49(1), and the relatives must be told within 14 days that UCESCO holds their details (Reg. 6(3)). | Keep it, but limited to one name and one phone number, filled in only once the volunteer's consent is recorded. |
| D6 | **Go live early with a reduced app?** | Without emergency contacts, and with no other sensitive data, Mikono may meet none of the Reg. 49 high-risk criteria. If so, no DPIA is needed and go-live need not wait 60 days. | Ask counsel. If they agree, write down the screening (Guidance Note §9.A), go live without the emergency-contacts field, and switch it on once the DPIA is approved. |

---

## 3. The action plan

Owners: **UCESCO** means management or the Data Protection Contact (DPC).
**Maintainer** means the developer who operates the server. Links point
to the backlog card that holds the detail.

### Phase 0: owner, contracts and registration (by 16 October 2026)

| ☐ | Action | Owner | Legal basis | Done when |
| --- | --- | --- | --- | --- |
| ☐ | Take decisions D1–D6 (§2). | UCESCO | — | Answers written in [dpa-governance-for-ucesco](../backlog/dpa-governance-for-ucesco.md). |
| ☐ | Register with the ODPC if D2 says so. Declare the purposes, the data categories, the sensitive data, and the transfer to France. | DPC | s.18–19 | Certificate held, with its renewal date in the calendar. |
| ☐ | Sign a **processor contract with the maintainer**. It covers the subject, duration, purpose, data types and data subjects. It also binds the maintainer to UCESCO's instructions, confidentiality, security, deletion or return at the end, and UCESCO's right to audit. | UCESCO + maintainer | s.42, Reg. 24 | Signed. |
| ☐ | **Authorise Gandi as a sub-processor** and keep Gandi's standard Data Processing Agreement on file. The maintainer stays liable to UCESCO for Gandi. | UCESCO + maintainer | Reg. 25 | UCESCO's written authorisation and Gandi's DPA filed. |
| ☐ | Decide who owns the **domain, DNS and server**. Today they sit in the maintainer's personal Gandi account. | UCESCO | s.41 (accountability) | Moved to a UCESCO account, or a dated decision to leave them. See [nickson-meeting-dns-and-hosting](../backlog/nickson-meeting-dns-and-hosting.md). |

### Phase 1: finish and submit the DPIA (by 30 October 2026)

| ☐ | Action | Owner | Legal basis | Done when |
| --- | --- | --- | --- | --- |
| ☐ | Review [the draft DPIA](DPIA-Mikono-draft.md). Fill in the `[UCESCO to fill]` gaps: contact details, expected volunteer numbers, and the D1–D6 answers. | DPC + maintainer | s.31(2), Reg. 50 | No gaps left. |
| ☐ | Approve the risk register and accept the residual risks. | UCESCO management | Guidance Note, Part 5 | Part 5 signed. |
| ☐ | Write the **transfer file** for France: what is sent, to whom, why, and with which safeguards (§4). | DPC | s.48, Reg. 41(2) | One page, filed with the DPIA. |
| ☐ | **Submit the DPIA to the ODPC.** Keep the proof of submission. | DPC | s.31, Reg. 51–52 | Submission receipt dated. The 60 days start. |

### Phase 2: during the 60 days (November to December 2026)

| ☐ | Action | Owner | Legal basis | Done when |
| --- | --- | --- | --- | --- |
| ☐ | Write and adopt a short **data protection policy**: what Mikono holds, why, how people use their rights, the retention schedule, the transfer to France, and complaints. Publish it, for example on ucesco.org. | DPC | Reg. 23, Reg. 19(6) | Published. |
| ☐ | Write the **privacy notice** for volunteers, in English and Kiswahili, covering all of s.29(a)–(h). It is handed over at onboarding, because volunteers never log in. | DPC + maintainer | s.29, Reg. 30 | Agreed text. See [volunteer-privacy-notice-and-consent](../backlog/volunteer-privacy-notice-and-consent.md). |
| ☐ | Build the **consent step** for sensitive data processed in France. Tell volunteers the risks, record the date, and make withdrawal easy: withdrawing empties the field. | DPC + maintainer | s.32, s.49(1), Reg. 4, Reg. 46 | Consent date on the volunteer record. The field is locked without it. |
| ☐ | Write a short note telling **emergency contacts** that UCESCO holds their name and number. The volunteer passes it on, or UCESCO sends it. Counsel to confirm which. | DPC | Reg. 6(3) | Note agreed, and the step added to onboarding. |
| ☐ | Put the **rights procedure** in place: who answers, how identity is checked, the deadlines in §5, and the ODPC's DPG 1–5 forms. In the app: a one-volunteer export, anonymise, and restrict. | DPC + maintainer | s.26, s.34–40, Reg. 7–13 | Procedure written, app actions live. See [volunteer-data-subject-requests](../backlog/volunteer-data-subject-requests.md). |
| ☐ | Put a time limit on every copy: volunteer records (D4), the server's access log, and the backups. | Maintainer | s.39, Reg. 19, Reg. 35 | Each store has a limit the code or the runbook enforces. See [personal-data-retention](../backlog/personal-data-retention.md). |
| ☐ | Write the **breach runbook**: what counts, how to contain it, and who tells whom by when (§6). | Maintainer + DPC | s.43, Reg. 37–38 | A runbook section with a notification template. See [breach-response-runbook](../backlog/breach-response-runbook.md). |
| ☐ | Make the app **refuse volunteers under 18**, so that no child's data enters Mikono. | Maintainer | s.33 | Validation live. See [refuse-minors-as-volunteers](../backlog/refuse-minors-as-volunteers.md). |
| ☐ | Set up an **encrypted off-site backup**, with the key held away from the server, and test a restore from it. | Maintainer | s.41(4)(d), Reg. 32(g) | Restore from the off-site copy drilled. See [off-site-encrypted-backups](../backlog/off-site-encrypted-backups.md). |
| ☐ | **Brief the staff** who use Mikono. Never put health, religion or ethnicity in free-text fields. Delete exported files after use and never forward them on WhatsApp. Report anything odd. | DPC | s.42(4), Reg. 32 | Attendance list kept. |

### Phase 3: launch gate (before real volunteer data goes in)

Go live only when every box is ticked:

- [ ] The ODPC registration is valid and declares Mikono's purposes and
  the transfer to France (or D2 recorded that none is needed).
- [ ] The DPIA was submitted at least 60 days ago, or the ODPC has
  answered, and its actions are done or scheduled.
- [ ] The processor contract with the maintainer is signed, and Gandi's
  DPA is on file.
- [ ] The transfer file is written.
- [ ] The privacy notice, the consent step and the rights procedure are
  in use.
- [ ] The breach runbook exists, and the Data Protection Contact knows
  the 72-hour rule.
- [ ] The off-site backup works, and a restore has been tested.
- [ ] The staff who use Mikono have been briefed.

### Phase 4: ongoing

| When | What | Owner |
| --- | --- | --- |
| Every new volunteer | Hand over the privacy notice. Record consent before filling in emergency contacts. Send the note to the contacts. | Onboarding staff |
| Every rights request | Answer within the deadline (§5) and log the request and the answer. | DPC |
| Every incident | Follow the runbook. Record it even if it is not notifiable (s.43(8)). | Maintainer + DPC |
| Every new field, export or service | Update the DPIA, the notice and the registration. Changes that raise the risk need a new DPIA (Reg. 49(1)(d)). | Maintainer flags, DPC decides |
| Once a year | Review the DPIA, the policy, the retention schedule and the transfer file. Audit what is still stored (Reg. 19(4)). | DPC |
| Before 24 months | Renew the ODPC registration. | DPC |

---

## 4. The transfer to France, in plain terms

The server is a GandiCloud machine in Paris. Holding Kenyan volunteers'
data there is a **transfer outside Kenya** (Part VI of the Act, Part VII
of the Regulations). It is allowed, under these conditions:

1. **Proof of safeguards for all the data (s.48(a), Reg. 41).** France
   is not on any ODPC "safe country" list. None is published yet (Reg.
   44). France also meets none of the shortcuts in Reg. 42: it has not
   ratified the African Union convention, and it has no agreement with
   Kenya. So UCESCO relies on **its own written assessment** (Reg.
   41(1)(b)) that safeguards exist:
   - France applies the EU's GDPR, a data protection law at least as
     strict as Kenya's;
   - the data is encrypted in transit, and passport numbers are
     encrypted at rest;
   - only signed-in UCESCO staff can reach it;
   - Gandi is bound by its contract.

   The file names the recipient (Gandi, Paris), the justification, a
   description of the data, and the start date. UCESCO shows it to the
   ODPC on request (Reg. 41(2)).
2. **Consent for sensitive data (s.49(1), Reg. 46).** Each volunteer
   explicitly agrees to their emergency contacts being kept in France,
   after being told the risks. UCESCO must be able to prove it (s.32(1)).
3. **No onward transfer** (Reg. 47). The contracts say the data does not
   move to a third country without UCESCO's authorisation. This includes
   the off-site backup, whose destination is named in its own decision.
4. **No localisation rule applies** (s.50, Reg. 26). The processing
   that must stay in Kenya covers civil registration, elections, public
   finance, protected systems, basic education and health care.
   Volunteer management is none of these.

---

## 5. Deadlines for answering a volunteer's request

From the General Regulations 2021. All requests are **free**, except
portability, which may carry a reasonable fee. Check the requester's
identity before answering: the DPG forms ask for an ID number. Reg. 13
covers requests made on someone else's behalf.

| Request | Form | Answer within | If refused |
| --- | --- | --- | --- |
| Access to their data | DPG 2 | **7 days** (Reg. 9(4)) | — |
| Correction | DPG 3 | **14 days** (Reg. 10(4)) | Reasons in writing within 7 days (Reg. 10(5)) |
| Erasure | DPG 5 | **14 days** (Reg. 12(3)) | — |
| Restriction | DPG 1 | **14 days** (Reg. 7(3)) | Reasons in writing within 14 days (Reg. 7(6)) |
| Objection | DPG 1 | **14 days** (Reg. 8(3)) | Reasons, plus the right to complain to the ODPC (Reg. 8(6)) |
| Portability, a copy sent to someone else | DPG 4 | **30 days** (Reg. 11(3)) | Reasons in writing within 7 days (Reg. 11(6)) |

The forms are in the First Schedule of
[the Regulations](THE-DATA-PROTECTION-GENERAL-REGULATIONS-2021-1.md).

---

## 6. If data leaks: the clocks

| Who | Tells whom | By when | Basis |
| --- | --- | --- | --- |
| The maintainer or Gandi | UCESCO | Within **48 hours** of becoming aware | s.43 |
| UCESCO | The ODPC | Within **72 hours** of becoming aware, with reasons if later | s.43(1)(a), Reg. 38 |
| UCESCO | The affected volunteers, in writing | As soon as practicable, unless the data was protected, for example by encryption | s.43 |

Some breaches are always notifiable. One is a leak of a staff member's
**login identifier together with their password** (Reg. 37(1)(b)).
Another is a leak of a name with the categories in the Second Schedule.
Mikono holds none of those categories today. For any other breach, the
test is a "real risk of harm" (s.43). **When in doubt, notify.** Keep a
record of every incident, including those not notified (s.43). The
ODPC notification content is listed in Reg. 38. The runbook will hold a
template.

---

## 7. What is already in place

Most of the technical protections the Act asks for (s.41, Reg. 32) are
already built into Mikono:

- Every page requires a login. The admin screens require an admin role.
- Passwords are stored only as hashes, and repeated login attempts are
  throttled.
- All traffic is encrypted (HTTPS only).
- Passport numbers are encrypted in the database, with a key kept apart
  from it.
- Uploaded photos are re-encoded, which removes GPS and camera data.
- There is no Google Analytics, no tracking pixel and no third-party
  error service, so no hidden transfers.
- Sign-in records are deleted after 90 days.
- Daily backups run, and a restore has been tested from the local copy.
- The public code repository holds no contact details, children or
  donors.

The rules behind these protections are recorded in
[ADR 0034](../../adr/0034-comply-with-kenyas-data-protection-act-2019.md).

---

## 8. What is at stake

- **Administrative fine:** up to KES 5 million, or 1% of the previous
  year's turnover, **whichever is lower** (s.63).
- **Compensation** to a person who suffers damage, distress included
  (s.65).
- **Offences:** failing to register when required (s.19(7)). For an
  offence with no specific penalty, a fine of up to KES 3 million, up to
  10 years' imprisonment, or both (s.73). Using personal data for
  marketing without consent carries up to KES 20,000 or 6 months (Reg.
  15(4)). Mikono does no marketing.
- **Trust:** volunteers and their families give UCESCO their details,
  and a leak would harm UCESCO's standing with partners.

A DPIA submitted in good faith also counts in UCESCO's favour if a
penalty is ever considered (Guidance Note §9.B).

---

## 9. To confirm with counsel or the ODPC

1. Whether UCESCO must register, and the current fee (D2). The
   Registration Regulations are not among the texts this plan was built
   from.
2. Whether the GDPR argument plus UCESCO's own assessment is enough
   proof of safeguards for France (§4.1).
3. Who informs emergency contacts under Reg. 6(3): UCESCO directly, or
   the volunteer on UCESCO's behalf.
4. The 48-hour processor deadline. The earlier checklist cited s.43(2)
   for it, and ADR 0034 cites s.43(3). Check the Act's text.
5. Whether a reduced go-live without emergency contacts needs no DPIA
   (D6).

## Sources

- [The Data Protection (General) Regulations, 2021](THE-DATA-PROTECTION-GENERAL-REGULATIONS-2021-1.md),
  Legal Notice No. 263 (transcription of the gazetted text).
- [ODPC Guidance Note on Data Protection Impact Assessment](ODPC-Guidance-Note-on-Data-Protection-Impact-Assessment-1.md)
  (transcription).
- The Data Protection Act No. 24 of 2019, as cited in
  [ADR 0034](../../adr/0034-comply-with-kenyas-data-protection-act-2019.md).
- The ODPC: [odpc.go.ke](https://www.odpc.go.ke), <info@odpc.go.ke>,
  P.O. Box 30920-00100, Nairobi.
