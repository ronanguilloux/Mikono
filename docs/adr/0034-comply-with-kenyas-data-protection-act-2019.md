# 0034. Comply with Kenya's Data Protection Act 2019 across every personal-data field, store and processor

Date: 2026-09-25

## Status

Accepted

## Context

Mikono holds personal data about people in Kenya: UCESCO's volunteers,
the staff who log in, and the relatives a volunteer names as emergency
contacts. The Data Protection Act 2019 (No. 24 of 2019) applies, because
UCESCO is established in Kenya and processes the data there (s.4(b)(i)).
The GDPR also applies, because production is hosted in France
([ADR 0017](0017-host-production-on-gandicloud-vps-in-france.md),
[ADR 0032](0032-store-volunteer-profile-fields-as-optional-and-photos-as-re-encoded-jpeg-blobs-in-sqlite.md)).
This ADR covers the Kenyan Act only.

These facts drive the decision:

- **The Act's duties were spread across feature ADRs.** ADR 0017 names
  Part VI, ADR 0028 bounds sign-ins to 90 days, ADR 0032 strips photo
  metadata. No record said which rules every field and store must meet,
  so a duty that sits across features could go unseen. One did: s.49(1)
  requires the data subject's consent before sensitive personal data is
  processed out of Kenya, and emergency contacts are sensitive personal
  data processed in France.
- **What Mikono holds.** A volunteer has a first name, an optional last
  name, email, phone, free-text notes, nationality, country of residence,
  date of birth, profession, skills, interests, free-text emergency
  contacts (usually the names of relatives), a photo, an accommodation
  preference, a pickup airport, a social media link, a supervisor, and a
  passport number and expiry
  ([ADR 0032](0032-store-volunteer-profile-fields-as-optional-and-photos-as-re-encoded-jpeg-blobs-in-sqlite.md),
  [ADR 0033](0033-encrypt-passport-numbers-at-rest-with-a-runtime-sodium-key.md)).
  Stays and activities record where a volunteer was and when. Staff
  accounts hold an email, a full name, roles and a password hash.
  Sign-ins, the Caddy access log, the error log, sessions, backups and
  exports each hold copies of some of it.
- **Volunteers never log in.** Only UCESCO staff do, as `ROLE_USER` or
  `ROLE_ADMIN`. Anything a volunteer must be told or asked happens
  outside the app, at onboarding.
- **The data leaves Kenya.** Production runs on a GandiCloud VPS in Paris,
  and the domain, DNS and server sit in the maintainer's personal Gandi
  account (ADR 0017). The off-site backup is planned to be encrypted with
  `age`
  ([`docs/brainstorm/08`](../brainstorm/08-off-site-encrypted-backups.md)).
- **The repository is public**, and the raw WhatsApp exports that name
  sponsored children and donors stay out of it
  ([ADR 0012](0012-seed-fixtures-from-the-real-whatsapp-roster-archive.md)).
- **Some duties are UCESCO's, not the code's.** Registration, the privacy
  notice, consent and breach notification are acts of the data controller.
  The code can only make them possible.

## Decision

**Mikono is built and operated so that UCESCO, as data controller, can
meet Kenya's Data Protection Act 2019: a personal-data field, store,
export or processor is admitted only under the rules below.**

**Roles.** UCESCO determines the purpose and means of the processing, so
it is the **data controller** (s.2). The maintainer, who operates the app,
and Gandi, which hosts it, process personal data on UCESCO's behalf, so
they are **data processors**. Each needs a written contract with UCESCO
under which it acts only on UCESCO's instructions and is bound by
UCESCO's obligations (s.42(2)(b)). A processor that processes personal
data other than as instructed becomes a data controller for that
processing (s.42(3)).

### 1. Every field and store has a purpose, a minimum and a retention period

Personal data is collected for explicit, specified and legitimate
purposes, limited to what those purposes need, and kept in an identifying
form no longer than they need (s.25(c), (d), (g); s.28(3)). By default,
only the data needed for each purpose is processed (s.41(3)). Each purpose
rests on one of the lawful grounds in s.30(1).

**A new field, store, export or processor states its purpose, whether it
is sensitive (rule 2) and its retention period (rule 6) in its own ADR.**
A change that cannot state them does not add the data.

The existing personal-data stores, and the ADR that bounds each one:

| Store | Personal data | Bounded by |
| --- | --- | --- |
| `volunteer` | Identity, contact, profile, notes, emergency contacts | [0014](0014-make-a-volunteers-last-name-optional.md), [0032](0032-store-volunteer-profile-fields-as-optional-and-photos-as-re-encoded-jpeg-blobs-in-sqlite.md), this ADR |
| Passport number | Encrypted at rest | [0033](0033-encrypt-passport-numbers-at-rest-with-a-runtime-sodium-key.md) |
| `volunteer_photo` | Re-encoded JPEG, no metadata | [0032](0032-store-volunteer-profile-fields-as-optional-and-photos-as-re-encoded-jpeg-blobs-in-sqlite.md) |
| `stay` | Where a volunteer is attached, and when | [0026](0026-attach-volunteers-to-branches-through-dated-stays-and-derive-active-from-them.md) |
| `activity`, `escort` | What a volunteer did, when, with whom | [0013](0013-record-every-escort-on-an-activity.md), [0030](0030-insert-programs-between-projects-and-activities.md) |
| `user` | Staff email, full name, roles, password hash | This ADR, rule 7 |
| `login_attempt` | Email-or-null and IP, 90 days | [0028](0028-record-login-attempts-with-identifier-and-ip-for-90-days-admin-only.md) |
| `usage_event` | None, by design | [0021](0021-read-usage-from-an-in-app-usage-screen-over-the-caddy-access-log.md) |
| Caddy access log | IP, user agent, full URI including search terms | [0021](0021-read-usage-from-an-in-app-usage-screen-over-the-caddy-access-log.md), rule 6 |
| Error log | Exception messages, which can carry bound SQL values | [0031](0031-show-production-errors-on-an-in-app-admin-screen-over-a-rotating-json-error-log.md) |
| Session files | Staff security token, 8 hours idle | [0020](0020-keep-sessions-on-the-database-volume-in-files.md) |
| Local and off-site backups | The whole database | [0017](0017-host-production-on-gandicloud-vps-in-france.md), rules 3, 6 and 7 |
| Exported files | Volunteer and staff names, emails, phones | [0029](0029-export-every-list-view-to-csv-or-xlsx-with-openspout-open-to-all-signed-in-staff.md) |
| Repository and fixtures | Volunteer first names as the rosters give them | [0012](0012-seed-fixtures-from-the-real-whatsapp-roster-archive.md) |

A query parameter is part of the full URI, so **personal data sent in a
GET parameter lands in the access log** and inherits its retention. The
volunteer search's `?q=` already does.

### 2. Sensitive personal data is named, ruled on, and kept out of free text

The Act's definition (s.2) is broader than the GDPR's:

> "sensitive personal data" means data revealing the natural person's
> race, health status, ethnic social origin, conscience, belief, genetic
> data, biometric data, property details, marital status, family details
> including names of the person's children, parents, spouse or spouses,
> sex or the sexual orientation of the data subject

Sensitive personal data is processed only where the s.25 principles apply
(s.44) and on one of the grounds in s.45.

- **`emergencyContacts` is sensitive.** It usually names a volunteer's
  parents or spouse, which are "family details". Its ground is s.45(c):
  the vital interests of the volunteer in an emergency, and UCESCO's
  obligations towards the people it places. Family details are collected
  only with a valid explanation (s.25(e)), which the privacy notice gives
  (rule 4). Because the field is processed in France, it also needs each
  volunteer's consent under s.49(1) (rule 3).
- **Free-text fields never hold health, belief, ethnicity or any other
  s.2 category.** `notes`, `skills`, `interests`, `supervisor`,
  `accommodationPreference` and activity notes say so in their form help
  text. A dietary need that reveals a religion, or an allergy, is
  sensitive personal data.
- **Mikono holds no health data.** Health data may be processed only by
  or under the responsibility of a health care provider, or by a person
  under a legal obligation of professional secrecy (s.46(1)). UCESCO's
  staff are neither in this role.
- **The photo is not biometric data** while nothing processes it
  technically to characterise the person (s.2, "biometric data"). Face
  matching or recognition on it would make it biometric, and sensitive.
- **A dedicated field for a sensitive category** (sex, marital status,
  religion, health, and the like) needs its own ADR, naming its s.45
  ground and the consent UCESCO records for it.

### 3. Transfer outside Kenya is proven and, for sensitive data, consented

Personal data leaves Kenya only with proof of adequate safeguards or the
data subject's consent (s.25(h)). A transfer needs proof given to the
Data Commissioner of appropriate safeguards (s.48(a)), which may rest on
the destination having commensurate data protection law (s.48(b)), or a
necessity listed in s.48(c). **Sensitive personal data is processed out
of Kenya only with the data subject's consent and confirmed safeguards**
(s.49(1)).

Hosting in France under ADR 0017 holds only while both are true:

- **UCESCO keeps the proof of safeguards** it can give the Data
  Commissioner: France applies the GDPR, which is the
  commensurate-law argument under s.48(b), and the measures in rule 7.
  The Data Commissioner may ask for this proof to be demonstrated, and may
  prohibit, suspend or condition the transfer (s.49(2), (3)).
- **UCESCO holds each volunteer's consent** to their emergency contacts,
  and any other sensitive personal data, being processed out of Kenya.
  UCESCO bears the burden of proving that consent (s.32(1)). A volunteer
  may withdraw it at any time (s.32(2)); the field is then emptied.

The off-site backup destination is a transfer on the same terms. An
`age`-encrypted file whose key the destination does not hold is the
safeguard, and the destination's country is named in the ADR that
chooses it.

The Cabinet Secretary may require some processing to run on a server in
Kenya (s.50). No such rule is known to cover this processing. If one is
prescribed, it decides the hosting question over ADR 0017.

### 4. Volunteers are told before their data is collected

Personal data is collected directly from the data subject (s.28(1)).
Before collecting it, UCESCO tells the volunteer, in so far as
practicable (s.29):

- (a) their rights under s.26;
- (b) that personal data is being collected;
- (c) the purpose;
- (d) the third parties it has been or will be transferred to, and the
  safeguards;
- (e) UCESCO's contact details, and whether any other entity may receive
  the data;
- (f) the technical and organisational security measures;
- (g) whether the collection is required by law, and whether it is
  voluntary or mandatory;
- (h) the consequences of not providing some or all of it.

**This is a written privacy notice, given by UCESCO at onboarding.**
Volunteers never log in, so it is not an in-app banner or checkbox. The
same conversation is where UCESCO gathers and records the s.49(1)
consent. The notice describes the data Mikono actually holds, so a new
field (rule 1) updates the notice.

Emergency contacts are data about the relatives, collected through the
volunteer rather than from the relatives themselves. That indirect
collection rests on s.28(2)(f)(iii): the protection of the interests of
the data subject or another person.

### 5. Staff can carry out every data-subject right from the app

A volunteer has the rights to be informed, to access their data, to
object, and to have false or misleading data corrected or deleted
(s.26). For a minor, or a person with a disability, a parent, guardian or
administrator exercises them; otherwise any person the data subject
authorises may (s.27). Staff must be able to:

- **Export one volunteer's data** in a structured, commonly used and
  machine-readable format (s.38(1)), within 30 days of the request
  (s.38(6)). Other people's data, such as a fellow volunteer on the same
  activity, stays out of it where it would affect their rights
  (s.38(5)(b)).
- **Correct any field** without undue delay (s.40(1)(a)).
- **Erase or anonymise a volunteer**, including one with activities,
  without undue delay (s.40(1)(b)). Anonymisation keeps the activity rows
  so reports still count them, and removes every identifier so the
  volunteer can no longer be identified (s.2, "anonymisation"). The
  delete-guard that refuses to delete a volunteer with activities is not
  a reason to keep their identity.
- **Restrict a volunteer's processing** (s.34): while accuracy is
  contested, when the data is no longer needed but a legal claim needs
  it, when the volunteer asks for restriction instead of erasure, or while
  an objection (s.36) is being assessed. Restricted data is only stored,
  or used with consent or for a legal claim (s.34(2)(a)), and UCESCO tells
  the volunteer before lifting the restriction (s.34(2)(b)). Where data
  that would otherwise be erased is needed as evidence, it is restricted
  instead (s.40(3)).
- **Pass a correction or erasure on** to any processor or third party that
  holds the data (s.40(2)).

The General Regulations 2021, as the Securiti summary reports them, set
shorter deadlines: 7 days for access and 14 days for rectification. They
must be confirmed against the gazetted Regulations.

### 6. Every store has a time bound, enforced in code or in the runbook

Personal data is kept only as long as reasonably necessary for its
purpose (s.39(1)), then deleted, erased, anonymised or pseudonymised
(s.39(2)). UCESCO needs mechanisms that make the time limits hold
(s.34(3)). So **every store in rule 1's table has a time bound, and the
code or the deployment runbook enforces it.** This includes:

- **A volunteer's record after their last stay.** UCESCO sets the period;
  at its end the volunteer is anonymised as in rule 5.
- **The access log.** A rotation by size alone is not a time bound: at
  low traffic, 10 MiB × 3 can hold years.
- **Local and off-site backups.** An expired volunteer who is still in a
  backup is still retained. "Keep everything" is not allowed on any copy,
  the off-site one included; its retention is stated in the ADR that
  chooses the destination.

Reports may keep anonymised activity counts indefinitely. Retention for
statistical purposes is allowed (s.39(1)(d)), provided nothing is
published in a form that identifies anyone (s.53(1)).

### 7. Security measures are rules a change must keep

UCESCO and its processors implement technical and organisational measures
that apply the principles by design and by default, both when the means
are chosen and during the processing (s.41(1), (2)), with regard to the
state of technology, cost, risk and the nature of the data (s.42(1)). The
measures s.41(4) lists are in place, and a change must keep them:

- **Every page is behind a login**, and admin screens (`/usage`, users,
  sign-ins, errors) are behind `ROLE_ADMIN`.
- **Passwords are stored only as hashes**, and logins are throttled.
- **Traffic is TLS only.**
- **Uploaded photos are re-encoded**, which strips EXIF and GPS
  (ADR 0032).
- **Passport numbers are encrypted at rest** with a runtime key that never
  travels with the database or the image (ADR 0033; s.41(4)(c)).
- **The repository, fixtures and backlog cards hold no personal data**
  beyond what ADR 0012 admits: first names as the rosters give them, no
  contact details, no children, no donors.
- **The off-site backup is encrypted** so that the destination cannot
  read it.
- **The restore drill is run** (s.41(4)(d)): restoring availability and
  access after an incident is a duty, not a habit.
- **Everyone acting for UCESCO or a processor follows these measures**
  (s.42(4)). That includes an AI coding agent with access to a production
  copy.

A less-trusted role, such as volunteers logging in themselves, reopens
[ADR 0029](0029-export-every-list-view-to-csv-or-xlsx-with-openspout-open-to-all-signed-in-staff.md)'s
rule that anyone who can view a list may export it.

### 8. No new processor or third party without an ADR

A new service that receives personal data (analytics, error reporting,
mail, an AI assistant, a CDN, a backup destination) needs an ADR naming
the service, its contract terms under s.42(2), and its transfer basis
under rule 3. This is the same line
[ADR 0021](0021-read-usage-from-an-in-app-usage-screen-over-the-caddy-access-log.md)
and
[ADR 0031](0031-show-production-errors-on-an-in-app-admin-screen-over-a-rotating-json-error-log.md)
drew for analytics and error SaaS. A controller that discloses personal
data incompatibly with its purpose, or a processor that discloses it
without the controller's prior authority, commits an offence (s.72(1),
(2)).

### 9. A breach is notified on the Act's clock

Where personal data has been accessed or acquired by an unauthorised
person and there is a real risk of harm (s.43(1)):

- **The processor tells the controller** without delay and, where
  reasonably practicable, within 48 hours of becoming aware (s.43(3)).
  In practice, the maintainer or Gandi tells UCESCO.
- **UCESCO tells the Data Commissioner** without delay, within 72 hours
  of becoming aware (s.43(1)(a)). A later notification gives the reasons
  for the delay (s.43(2)).
- **UCESCO tells the affected volunteers in writing** within a reasonably
  practicable period (s.43(1)(b)), unless appropriate safeguards such as
  encryption protect the affected data (s.43(6)).
- **The notification covers** the nature of the breach, the measures
  taken or intended, what the data subject can do to limit the harm, the
  unauthorised person's identity where known, and a contact point
  (s.43(5)). It may be given in phases (s.43(7)).
- **UCESCO records** the facts, the effects and the remedial action
  (s.43(8)).

The procedure lives in the deployment runbook, where whoever finds a
breach will look.

### 10. Mikono does not record children as volunteers

Personal data about a child is processed only with a parent's or
guardian's consent, in a way that protects the child's best interests,
and with mechanisms for age verification and consent (s.33(1), (2)).
Mikono has neither, so **it does not record minors as volunteers**: a
date of birth under 18 is refused. Children named in UCESCO's source
material, such as sponsored children in the WhatsApp exports, never enter
git or the database (ADR 0012).

### 11. Governance stays with UCESCO

These duties belong to UCESCO as data controller. The code cannot perform
them:

- **Registration.** No one may act as a data controller or processor
  unless registered with the Data Commissioner (s.18(1)), subject to the
  thresholds the Commissioner prescribes (s.18(2)), which are in the
  Registration Regulations 2021. UCESCO assesses whether it must
  register, and a controller that must and does not commits an offence
  (s.19(7)). A registered controller notifies changes to its particulars
  (s.19(5)).
- **A contact point.** A data protection officer is optional (s.24(1)),
  but the privacy notice needs UCESCO's contact details (s.29(e)) and a
  breach notification needs a contact point (s.43(5)(e)). UCESCO
  publishes one.
- **Impact assessment.** Processing likely to result in high risk needs
  a data protection impact assessment beforehand (s.31(1)), submitted 60
  days before the processing (s.31(5)). UCESCO screens Mikono's processing
  against that test and keeps the result.
- **Transfer proof and consents** (rule 3).
- **Ownership of the domain, DNS and server**, which sit in the
  maintainer's personal account (ADR 0017).

**Automated decisions and commercial use.** Mikono takes no decision based
solely on automated processing (s.35) and makes no commercial use of
personal data (s.37). A future AI assistant that ranks, scores or selects
volunteers reopens this ADR.

## Consequences

- **Positive:**
  - Every future field, store, export or processor meets one checklist
    with section numbers, instead of rediscovering the Act feature by
    feature.
  - The rules the app already follows (login-only, sign-ins bounded to 90
    days, EXIF stripping, passport encryption, no analytics SaaS) are
    tied to the sections that require them, so a change that undoes one
    shows what it breaks.
  - UCESCO can see which duties are its own and which the code supports.
- **Negative / trade-offs:**
  - **Notice and consent fall on UCESCO staff**, at every onboarding, on
    paper or equivalent, and they must keep the proof.
  - **Emergency contacts depend on a signed consent.** Under s.49(1),
    without it the field may not be filled while production is in
    France, and a withdrawal empties it.
  - **Anonymisation, restriction, per-volunteer export and time-bound
    retention cost code** that no screen needed before.
  - **The exposure is real.** An administrative fine of up to KES 5
    million or 1% of the preceding year's annual turnover, whichever is
    lower (s.63). Compensation to a data subject for damage, including
    distress (s.65). For an offence with no specific penalty, a fine of up
    to KES 3 million, up to 10 years' imprisonment, or both (s.73).
    Failing to register when required is an offence (s.19(7)).
- **Reversibility:** the rules are cheap to change, since each is a
  paragraph here and a check in code. Data already transferred, exported
  or disclosed cannot be recalled.

## Alternatives considered

### 1. Keep compliance scattered across feature ADRs

**Rejected.** No record held a checklist a new field could be checked
against, and that is how the s.49(1) consent requirement for emergency
contacts processed in France went unnoticed across ADR 0017 and ADR 0032.

### 2. Move hosting to Kenya to escape Part VI

**Rejected for now.** ADR 0017 chose France on proven hardware and cost,
and the Kenyan candidates never answered. It remains the fallback if
gathering s.49(1) consent proves impractical, and moving is cheap
(ADR 0017, Reversibility). Backups would still leave the server, so rules
6 and 7 would stand.

### 3. Rely on the s.45(a) not-for-profit ground for sensitive data

**Rejected.** s.45(a) covers a foundation, association or other
not-for-profit body only where it has a political, philosophical,
religious or trade union aim. UCESCO's aim is community development.

### 4. An in-app consent banner

**Rejected.** Volunteers never log in, so they would never see it. Consent
is gathered and recorded by UCESCO at onboarding; as
[`docs/brainstorm/06`](../brainstorm/06-usage-analytics-cockpit.md) put
it, consent here is a conversation, not a banner.

## Sources

- Data Protection Act No. 24 of 2019 (text):
  <https://www.kentrade.go.ke/wp-content/uploads/2022/09/Data-Protection-Act-1.pdf>
- ODPC, Data protection laws of Kenya (Act + General, Registration, and
  Complaints Handling and Enforcement Regulations 2021):
  <https://www.odpc.go.ke/data-protection-laws-kenya/>
- Securiti, Kenya DPA overview:
  <https://securiti.ai/kenya-data-protection-act-dpa/>
- Dimeri, Data Protection Act Kenya guide:
  <https://www.dimeri.ai/blog/guides/data-protection-act-kenya>

This is not legal advice. The Regulations' deadlines and registration
thresholds must be confirmed against the gazetted Regulations or with the
ODPC.
