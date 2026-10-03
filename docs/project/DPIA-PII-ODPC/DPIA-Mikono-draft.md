# Data Protection Impact Assessment: Mikono (UCESCO Volunteer Manager)

**Status:** DRAFT for UCESCO review, 2 October 2026. Not yet submitted.

This draft follows the ODPC template: the Third Schedule of the General
Regulations 2021, with the detail of the ODPC Guidance Note. Text in
`[UCESCO to fill]` needs an answer from UCESCO before signature. The plan
that leads to submission is
[`DPA-Compliance-Plan.md`](DPA-Compliance-Plan.md).

This is not legal advice. Have it reviewed before submission.

---

## Data controller

| Item | Response |
| --- | --- |
| Name of data controller | UCESCO `[UCESCO to fill: full registered name]` |
| Postal address | `[UCESCO to fill]` |
| Email address | `[UCESCO to fill: Data Protection Contact's email]` |
| Telephone number | `[UCESCO to fill]` |
| ODPC registration number | `[UCESCO to fill, or "application pending"]` |
| Data processors | The maintainer, Ronan Guilloux, who develops and operates the app under a processor contract (Reg. 24). Gandi SAS, Paris, France, who hosts the server as an authorised sub-processor (Reg. 25). |

---

## Part 1: Description of the processing operations

### 1. Project name

Mikono, the UCESCO Volunteer Manager.

### 2. Need for a DPIA

A DPIA is required. The processing meets **one** of the Regulation 49(1)
criteria:

- **(e) Sensitive personal data.** A volunteer's emergency contacts
  usually name their parents or spouse, which s.2 counts as "family
  details".

The processing does **not** involve:

- automated decision-making (a);
- biometric or genetic data (c): photos are stored but never analysed;
- children (e): under-18s are refused;
- combining datasets (f);
- large-scale processing (g);
- monitoring of public areas (h);
- innovative technology (i);
- preventing anyone from exercising a right (j).

### 3. Project outline

UCESCO hosts international and local volunteers at its community
projects in Kibera (Nairobi) and Mombasa. Mikono replaces the WhatsApp
messages and spreadsheets that the Volunteer Manager used to track:

- who each volunteer is and how to reach them in an emergency;
- when each volunteer stays, and at which UCESCO branch;
- what each volunteer did each day, on which project and programme, and
  with which escorts;
- daily rosters, and reports of volunteer days per project for UCESCO
  and its partners.

The processing is: collection by staff, recording, storage, retrieval,
reporting, export to spreadsheet files, backup, and erasure or
anonymisation.

### 4. Personal data and data subjects

**(a) Classes of data.**

| Data subject | Data | Sensitive? |
| --- | --- | --- |
| Volunteer | First name, optional last name, email, phone, nationality, country of residence, date of birth, profession, skills, interests, accommodation preference, pickup airport, social media link, supervisor, free-text notes, photo | No |
| Volunteer | Passport number (encrypted) and expiry date | No, but high-impact if leaked |
| Volunteer | Stays (branch, dates) and activities (date, project, programme, activity type, duration, escorts) | No |
| Volunteer's relatives | Emergency contacts: usually the name and phone number of a parent or spouse | **Yes:** family details (s.2) |
| UCESCO staff (users) | Email, full name, roles, password hash; sign-in attempts (email and IP address, 90 days) | No |
| UCESCO staff (users) | Server access log: IP address, browser, pages visited, search terms typed | No |
| Escorts (UCESCO local staff) | Name, active flag | No |

The free-text fields are `notes`, `skills`, `interests`,
`accommodationPreference`, `supervisor` and the activity notes. Staff
are told never to record health, religion, ethnicity or any other
sensitive category in them.

**(b) Classes of data subjects.** Adult volunteers, Kenyan and
international. Their emergency contacts. UCESCO staff. No children: the
app refuses a date of birth under 18 years. No vulnerable groups are
targeted. Volunteers are in a mild imbalance of power with their host
organisation, and the consent design takes this into account (Part 2).

### 5. Information flow

| Step | Response |
| --- | --- |
| a) Where the data comes from | From the volunteer, at onboarding with UCESCO staff (s.28(1)). Emergency contacts come indirectly, through the volunteer (s.28(2)(f)), and the contacts are told within 14 days (Reg. 6(3)). Activities are recorded by staff. |
| b) How it is collected | UCESCO staff type it into Mikono through a web browser, over HTTPS, after logging in. Volunteers never log in. |
| c) How much data | About `[UCESCO to fill: N]` volunteers a year, each with one profile, a few stays and a few dozen activity rows. About `[UCESCO to fill: N]` staff accounts. |
| d) Where it is stored | One SQLite database on a virtual private server (GandiCloud VPS, Paris, France). A local backup is kept on the same server. An encrypted off-site backup goes to a destination named in its own decision, and the destination never holds the decryption key. |
| e) How long it is stored | Volunteer records: `[UCESCO to fill: D4, e.g. 2 years]` after the last stay, then anonymised. Activity counts are kept for statistics, with no identity (s.39(1)(d)). Sign-in attempts: 90 days. Sessions: 8 hours idle. Local backups: 30 days. Off-site backups: `[to fill]`. Access log: `[to fill: time limit]`. |
| f) Extent of processing | Viewing, editing and reporting by staff. Spreadsheet export by staff. No profiling, no automated decisions, no marketing, no sale or sharing with third parties. |
| g) Where it may be transferred | France (the hosting). Nowhere else. No analytics, advertising or error-reporting services are used. |
| h) Number of people affected | `[UCESCO to fill: volunteers + their emergency contacts + staff]` |

**Flow:** onboarding conversation → privacy notice given → consent
recorded (sensitive data, transfer) → staff enter the data in Mikono →
daily use (rosters, activities, reports, exports) → end of the last
stay → retention period → anonymisation → backups age out.

### 6. Compliance with the data protection principles (s.25)

| Principle | How Mikono complies |
| --- | --- |
| Lawfulness, fairness, transparency | One lawful basis per purpose (Part 2, Q1). A written privacy notice in English and Kiswahili is given before collection (s.29). No hidden processing. |
| Purpose limitation | Used only to manage volunteer placements and report on volunteer work. A new purpose needs a review of this DPIA. |
| Data minimisation | Every profile field is optional except the first name. The emergency contact is limited to one name and phone number. Free-text fields carry a warning against sensitive data. |
| Accuracy | Staff correct any field at the volunteer's request (within 14 days, Reg. 10). The volunteer reviews their profile at onboarding. |
| Storage limitation | A retention period per store (Part 1 §5e), enforced by code or by the runbook. |
| Integrity and confidentiality | Login on every page, admin role for admin screens, hashed passwords, login throttling, HTTPS only, passport numbers encrypted, photos stripped of metadata, encrypted off-site backup, restore drill (Part 2, Q10). |
| Accountability | ADR 0034 records the rules every change to the app must follow. This DPIA, the policy, the processor contracts and the transfer file are kept and reviewed yearly. |

---

## Part 2: Necessity and proportionality

| Question | Response |
| --- | --- |
| **1. Lawful basis** (s.30, one per purpose, Reg. 5) | **Volunteer profile, stays and activities:** performance of the volunteering arrangement between UCESCO and the volunteer (s.30(1)(b)(i)) `[counsel to confirm; the alternative is legitimate interests]`. **Emergency contacts:** vital interests of the volunteer (s.45(c), sensitive data), plus explicit consent to processing outside Kenya (s.49(1)). **Passport number:** `[UCESCO to fill: the purpose, e.g. airport pickup or registration with authorities; drop the field if no purpose holds]`. **Staff accounts and sign-ins:** UCESCO's legitimate interest in securing the app. |
| **2. How consent is obtained** | At onboarding, in person, on a written form. The volunteer is told the risks of the data being held in France (Reg. 46(1)(b)) and that consent can be withdrawn at any time. The date is recorded in Mikono. Refusing has no effect on the placement (Reg. 4(4)(c)): the emergency-contacts field simply stays empty. Withdrawal empties it. |
| **3. Does the processing achieve the purpose?** | Yes. It gives one reliable record of who is where and doing what, which UCESCO needs for volunteer safety, daily rosters and partner reporting. |
| **4. Is there another way?** | Paper or WhatsApp: less secure, with no access control and no retention. Hosting in Kenya would remove the transfer, and was considered and rejected on cost and proven reliability ([ADR 0017](../../adr/0017-host-production-on-gandicloud-vps-in-france.md)). It stays the fallback if consent proves impractical. |
| **5. Data quality and minimisation** | Optional fields. Validation on email, country and URL formats. Dates of birth under 18 are refused. One emergency contact only. Help text on free-text fields. |
| **6. Information given to individuals** | A written privacy notice at onboarding covering s.29(a)–(h): rights, purpose, recipients, transfer to France and its safeguards, security, voluntary or mandatory fields, and the consequences of not providing data. Emergency contacts get a short note (Reg. 6(3)). |
| **7. Support for their rights** | A named Data Protection Contact. The ODPC's DPG 1–5 forms are accepted. In the app: a one-volunteer data export, correction of any field, anonymisation, and restriction. Deadlines: 7 days for access, 14 days for correction, erasure, restriction and objection, 30 days for portability. |
| **8. Measures to ensure compliance by the controller and processors** | A written processor contract with the maintainer (Reg. 24). Gandi authorised as a sub-processor under its DPA (Reg. 25). ADR 0034 makes every new field, store or service state its purpose, sensitivity and retention before it is built. A yearly review. |
| **9. Parties and roles** | UCESCO: data controller. The maintainer: processor (development, operation, backups). Gandi SAS: sub-processor (server hosting in France). No other recipients. |
| **10. Safeguards for the processing** | Authentication on every page, and role-based access with an admin role. Password hashing and login throttling. TLS (HTTPS, HTTP/3). Passport numbers encrypted with a runtime key held off the database. Photos re-encoded. No third-party scripts. Daily backups with a 30-day local window, an encrypted off-site copy, and a restore drill. Sign-in records for 90 days, visible to admins. A breach runbook (s.43). |
| **11. Safeguards for international transfers** | France applies the GDPR. UCESCO's own assessment of the safeguards is documented (Reg. 41(1)(b), 41(2)). Gandi's contract bars onward transfer (Reg. 47). Volunteers give explicit consent for sensitive data (s.49(1), Reg. 46). The data is encrypted in transit, and passport numbers and the off-site backup are encrypted at rest. |

---

## Part 3: Risks to the rights and freedoms of data subjects

### Assessment questions

| Question | Yes / No, with explanation |
| --- | --- |
| 1. Will the project collect new identifiable data about data subjects? | **Yes.** Volunteer profiles and emergency contacts, which UCESCO formerly kept only in WhatsApp messages. |
| 2. Will it compel data subjects to provide information with little awareness or choice? | **No.** The notice is given first, the fields are optional, and the sensitive data needs consent. |
| 3. Will identifiable data be shared with organisations or people without previous routine access? | **Yes, limited.** The hosting provider (Gandi) holds the server. No one else. Partner reports use counts, not names `[UCESCO to confirm]`. |
| 4. Is data used for a new purpose or in a new way? | **No.** It is the same purpose as before (volunteer coordination), in a more secure tool. |
| 5. Could the data raise privacy concerns (health, criminal records or other sensitive data)? | **Yes.** Family details in emergency contacts. Passport numbers. Health and religion are banned from free text, but staff could enter them by mistake (risk R5). |
| 6. Will data subjects be contacted in ways they may find intrusive? | **No.** There is no marketing. Emergency contacts are only called in an emergency, and receive one information note. |
| 7. Will decisions be made that significantly affect data subjects? | **No.** No automated decisions. Placement decisions are made by people, as before. |
| 8. Is new, potentially privacy-intrusive technology used (biometrics, facial recognition, automated decisions)? | **No.** Photos are stored as plain images, with no recognition. |
| 9. Is a service being transferred to a new supplier? | **No.** |
| 10. Is processing being moved to a new organisation? | **No.** |

---

## Part 4: Risk register and measures

**Score = Impact + Likelihood**, each from 1 to 5, as in the Third
Schedule. Scores of 7 and above are high.

**Name of data controller / project:** UCESCO, Mikono. **Risk register
owner:** `[UCESCO to fill: Data Protection Contact]`.

| ID | Risk description | Consequence | Owner | Current controls | I | L | Score | Further actions (owner) |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| R1 | Emergency contacts (sensitive) held in France without the volunteer's consent | Unlawful transfer under s.49(1). Fine and complaints. | DPC | Real data not loaded yet | 3 | 5 | **8** | Privacy notice and consent step. The field is locked until consent is recorded. Withdrawal empties it. (DPC + maintainer) |
| R2 | A staff account is taken over (weak or shared password, phishing) | All volunteer data is exposed | Maintainer | Login on every page, hashed passwords, throttling, admin role, sign-in log | 4 | 2 | **6** | Password rules in the staff briefing. Remove accounts when people leave. Consider two-factor login for admins. (DPC, maintainer) |
| R3 | An exported spreadsheet leaks: a lost phone or laptop, or a file forwarded on WhatsApp | Names, emails and phones exposed outside any control | DPC | Export needs a login | 4 | 3 | **7** | Staff briefing: delete after use, never forward. Review whether exports need phone numbers. (DPC) |
| R4 | A backup copy leaks | The whole database is exposed | Maintainer | Backups on the server, behind SSH. Passport numbers encrypted inside them. | 5 | 2 | **7** | Encrypted off-site copy, with the key held off the server and away from the passport key. (Maintainer) |
| R5 | Staff type sensitive data (health, religion, ethnicity) into free-text fields | Unlawful sensitive data. Health data is barred (s.46). | DPC | Help-text warning | 4 | 3 | **7** | Staff briefing. A yearly review of the free-text fields during the retention audit. (DPC) |
| R6 | Data kept forever: volunteer records, access log, backups | Breach of storage limitation (s.39). A larger breach if one happens. | Maintainer | Sign-ins limited to 90 days, local backups to 30 days, sessions to 8 hours | 3 | 5 | **8** | Retention period (D4) with automatic anonymisation. A time limit on the access log and the off-site backups. (Maintainer) |
| R7 | UCESCO cannot honour a rights request: no one-volunteer export, and a volunteer with activities cannot be deleted | A missed deadline (7 or 14 days). A complaint to the ODPC. | DPC | Fields can be corrected | 3 | 3 | **6** | Export, anonymise and restrict actions in the app. A written procedure. (Maintainer, DPC) |
| R8 | A breach is not reported in time: no runbook, no named contact | Missing the 72-hour ODPC deadline (s.43) | DPC | None | 4 | 3 | **7** | Breach runbook with a template. A named contact. The 48-hour duty in the processor contract. (Maintainer, DPC) |
| R9 | Single-person dependency: the server, domain and DNS sit in the maintainer's personal account | UCESCO loses control or access. Delayed incident response. | UCESCO | Restore drill documented | 4 | 2 | **6** | Processor contract. Move the accounts to UCESCO or record a dated decision. A second person with access. (UCESCO) |
| R10 | A volunteer's whereabouts (stays, daily activities in Kibera or Mombasa) are learnt by someone who means them harm | Physical safety risk to the volunteer | DPC | Login only, no public pages | 5 | 1 | **6** | Rosters are not shared outside UCESCO. Exports are covered by the R3 actions. (DPC) |
| R11 | A passport number leaks, or the encryption key is lost | Identity fraud, or loss of the numbers | Maintainer | Encrypted at rest. The key is not in the image or backups. | 4 | 1 | **5** | Keep the key in a password manager. Drop the field if no purpose holds (Part 2, Q1). (Maintainer, UCESCO) |
| R12 | A minor is recorded as a volunteer | Child data without guardian consent (s.33) | Maintainer | The date of birth must be in the past | 4 | 1 | **5** | Refuse dates of birth under 18. (Maintainer) |
| R13 | Search terms (names, emails) typed by staff end up in the server's access log | Personal data in a log with no time limit | Maintainer | Admin-only usage screen | 2 | 4 | **6** | A time limit on the log, or drop search terms from it. (Maintainer) |
| R14 | Real names leak through the public code repository | Exposure of volunteers, children or donors | Maintainer | Raw WhatsApp exports are git-ignored. Fixtures hold first names only. | 4 | 1 | **5** | Keep the rule in [ADR 0012](../../adr/0012-seed-fixtures-from-the-real-whatsapp-roster-archive.md). Never use a production copy in development. (Maintainer) |

**Residual risk** once the actions are done: every risk falls to a score
of 6 or below. R1, R6 and R8 drop most, because their actions remove the
cause.

---

## Part 5: Sign-off and record of outcomes

| Item | Officer name / date | Notes / instructions |
| --- | --- | --- |
| Measures approved by | `[UCESCO to fill]` | Integrate the actions into the plan, with a date and an owner ([`DPA-Compliance-Plan.md`](DPA-Compliance-Plan.md) §3). |
| Residual risks approved by | `[UCESCO to fill]` | If any residual high risk is accepted, consult the ODPC before going ahead. |
| DPO advice provided | `[UCESCO to fill: DPO, or "no DPO appointed (s.24 optional); advice from the Data Protection Contact"]` | The DPO advises on compliance, on the Part 4 measures, and on whether processing can proceed. |
| Summary of DPO advice | | |
| DPO advice accepted or overruled by | | If overruled, give reasons. |
| Consultation responses reviewed by | `[UCESCO to fill: e.g. a sample of volunteers or the volunteer coordinator]` | If the decision departs from individuals' views, give reasons. |
| Consultation with the ODPC | Submitted on `[date]`. Response: `[date / none within 60 days, deemed approved under Reg. 52(3)]` | |
| This DPIA will be kept under review by | `[UCESCO to fill: Data Protection Contact]`, yearly and before any change to the data, the hosting or the processors | The DPO or contact also reviews ongoing compliance with the DPIA. |
