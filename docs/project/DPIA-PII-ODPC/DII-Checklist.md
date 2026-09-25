# Data Protection Act 2019 (DPA) and its 2021 regulations Checklists

Below is a checklist built from the Data Protection Act 2019 (DPA) and its 2021 regulations: General, Registration, and Complaints Handling. Section numbers refer to the Act unless marked "Reg." I'm not a lawyer, so have a Kenyan advocate or data protection practitioner validate the final version, especially the DPIA and any cross-border transfers.

---

## Part A — Organisation-level compliance (the NGO)

### 1. Registration with the ODPC (s.18–19, Registration Regs 2021)

- [ ] **Register as a data controller.** The small-entity exemption does not apply to NGOs. Public entities and non-profit making entities such as charities and religious entities are required to register regardless of revenue or turnover.
- [ ] **Also register as a data processor** if the NGO processes data on behalf of another organisation, such as a donor, a partner NGO, or a government programme. Controller and processor registrations are separate, each with its own fee.
- [ ] **Pay the non-profit fee.** Per ODPC guidance, non-profit data controllers and processors pay a standard registration fee of KES 4,000 and a renewal fee of KES 2,000.
- [ ] **Prepare the registration content in advance.** It covers the purposes of processing, categories of data subjects and data, sensitive data, transfers outside Kenya, and security measures.
- [ ] **Track the renewal date.** The certificate is valid for 24 months from issuance. The ODPC is actively enforcing this. On 28 August 2026 it issued a 14-day notice to controllers and processors with expired certificates, published a list of affected organisations, and warned of enforcement action. If already registered, check the NGO isn't on that list.
- [ ] **Notify the ODPC of changes** to registered particulars, such as new purposes, new data categories, or new transfer destinations.

### 2. Governance and accountability

- [ ] **Assign board-level and management accountability** for data protection.
- [ ] **Decide on a Data Protection Officer (s.24).** A DPO is mandatory when core activities involve large-scale regular and systematic monitoring, or large-scale processing of sensitive data. NGOs handling beneficiary health, refugee, GBV, or children's data usually meet this test. The DPO can be a staff member or external, and can be shared. Publish the DPO's contact details.
- [ ] **Adopt a Data Protection Policy** covering principles, roles, rights handling, breaches, retention, and transfers.
- [ ] **Keep evidence of compliance.** The controller must be able to demonstrate compliance, not merely claim it.

### 3. Data inventory and records

- [ ] **Map all processing activities** across beneficiaries, donors, staff, volunteers, website visitors, partners, and CCTV. Record the purpose, lawful basis, data categories, source, recipients, storage location, retention period, and security measures for each.
- [ ] **Flag sensitive personal data (s.2, s.44–46).** Under the Act this includes race, health, ethnic/social origin, conscience, belief, genetic and biometric data, property details, marital status, family details (names of children, parents, spouse), and sex or sexual orientation. NGO beneficiary data frequently falls in these categories.

### 4. Principles (s.25) and lawful basis (s.30)

- [ ] **Document a lawful basis for each purpose.** Options are consent, contract, legal obligation, vital interests, public interest, or legitimate interests.
- [ ] **Apply data minimisation, purpose limitation, accuracy, and storage limitation** to each processing activity.
- [ ] **Ensure consent meets s.32 requirements** wherever consent is the basis. It must be informed, specific, freely given, and withdrawable at any time. The NGO bears the burden of proving consent, so log it. Treat consent from vulnerable beneficiaries with extra care, particularly where aid depends on it, because such consent may not be "freely given."
- [ ] **Meet the stricter conditions for sensitive data (s.45)**, and for health data (s.46), which must be processed by or under the responsibility of a healthcare provider or someone bound by confidentiality.

### 5. Transparency (s.29)

- [ ] **Inform data subjects before or at collection.** Tell them their rights, that data is being collected, the purpose, the recipients, whether transfers abroad occur, the security safeguards, the retention period, the consequences of not providing data, and the controller's contact details.
- [ ] **Use accessible formats.** Provide notices in English and Kiswahili, plus local languages or verbal explanation for low-literacy beneficiaries.

### 6. Data subject rights (s.26, s.31–38, General Regs)

- [ ] **Set up a documented procedure and channel** for requests to access, rectify, erase, object, restrict, or port data, and to not be subject to automated decisions (s.35).
- [ ] **Meet the regulatory response deadlines.** The General Regulations set roughly 7 days for access and 14 days for rectification or erasure, and s.38 sets 30 days for portability. Confirm the exact figures with counsel.
- [ ] **Verify identity** before fulfilling a request, and log every request and response.

### 7. Children (s.33)

- [ ] **Obtain parental or guardian consent** before processing data of anyone under 18.
- [ ] **Implement age verification** appropriate to the risk.
- [ ] **Act in the best interests of the child** throughout.

### 8. Fundraising and direct marketing (s.37)

- [ ] **Get consent before using personal data for commercial purposes, or anonymise the data.** Donor solicitation emails and SMS may fall under this.
- [ ] **Provide a simple opt-out** in every message.

### 9. Processors and third parties (s.42)

- [ ] **Sign written data processing agreements** with every vendor that touches personal data: hosting, email, CRM, payment and M-Pesa integrators, analytics, SMS gateways, and developers.
- [ ] **Verify vendors are ODPC-registered as processors** where they are required to be.
- [ ] **Require processors to notify the NGO of breaches within 48 hours (s.43(2)).**
- [ ] **Agree data-sharing terms with partners**, including implementing partners, donors, and government.

### 10. Cross-border transfers (s.48–50, General Regs Part VI)

- [ ] **Identify every transfer outside Kenya.** These are often invisible. Common examples:
  - reporting beneficiary data to international HQ or to foreign donors
  - Google Workspace or Microsoft 365
  - foreign SaaS tools
  - offshore backups
  - analytics scripts
- [ ] **For each transfer, document appropriate safeguards** and be able to provide proof to the ODPC (s.48). Safeguards include an adequate destination, contractual clauses, necessity, or consent.
- [ ] **For sensitive data transferred abroad, obtain the data subject's consent** in addition to safeguards (s.49).
- [ ] **Check whether any processing falls under localisation rules (s.50, General Regs)**, which cover processing tied to the state's strategic interests, for example processing done under a government contract.

### 11. Security and breaches (s.41, s.43)

- [ ] **Implement technical and organisational measures proportionate to the risk.** Examples include access control, encryption, pseudonymisation, backups, and staff confidentiality undertakings.
- [ ] **Write an incident response plan.** Breach notification to the ODPC runs 72 hours. Also notify affected data subjects without undue delay when there is real risk of harm.
- [ ] **Keep a breach register**, including incidents you decide were not notifiable.

### 12. Retention and disposal (s.39)

- [ ] **Adopt a retention schedule** per data category, with no retention longer than necessary for the purpose. Note that donor and grant contracts may impose their own minimums.
- [ ] **Delete, destroy, or anonymise securely** at the end of retention, and log it.

### 13. People and premises

- [ ] **Train staff and volunteers**, especially field data collectors, and keep training records.
- [ ] **Issue an employee privacy notice** and handle HR data properly.
- [ ] **Put CCTV signage and a policy in place** if the office has cameras.

---

## Part B — Project-level compliance (the web app hosted in Kenya)

### 14. Data Protection Impact Assessment (s.31, General Regs)

- [ ] **Run a DPIA before development is finalised.** It is required wherever processing is likely to result in high risk, which is almost certain for NGO beneficiary platforms.
- [ ] **Submit the DPIA to the ODPC at least 60 days before processing begins.** Use the ODPC's DPIA template and guidance note.
- [ ] **Update the DPIA** whenever features, data types, or vendors change significantly.

### 15. Privacy by design and by default (s.41)

- [ ] **Collect only the fields needed**, with no "nice to have" fields.
- [ ] **Default to the most privacy-protective settings.**
- [ ] **Pseudonymise or anonymise data for reporting and dashboards**, especially data sent to donors.
- [ ] **Use no real personal data in dev, test, or staging environments.**

### 16. Front-end compliance

- [ ] **Publish a privacy notice** covering all s.29 items, linked on every data collection form.
- [ ] **Add granular, unticked consent checkboxes** where consent is the basis, with timestamped consent logs and an easy withdrawal mechanism.
- [ ] **Get consent for non-essential cookies and trackers** before they load. Foreign analytics and pixels also count as cross-border transfers.
- [ ] **Add an age gate and parental consent flow** if minors can use the app.
- [ ] **Provide an in-app or published channel for data subject requests**, ideally with self-service access, correction, and deletion.

### 17. Hosting and infrastructure

- [ ] **Sign a hosting contract with a data processing agreement.** Confirm the host's ODPC registration and that data really stays in Kenya, including backups, DR sites, logs, CDN, email relay, and monitoring tools.
- [ ] **List every third-party API or SDK** the app calls and map where each sends data. Examples include maps, SMS, payments, error tracking, and AI or LLM APIs.
- [ ] **Encrypt data in transit and at rest**, using TLS and encrypted databases and backups.
- [ ] **Use strong authentication and access control:** MFA for admins, role-based access, least privilege, and a joiners/movers/leavers process.
- [ ] **Keep audit logs** of access to personal data, especially sensitive records.
- [ ] **Scan for vulnerabilities, pen-test before launch, and have a patching process.**
- [ ] **Back up regularly and test restores.**

### 18. Lifecycle

- [ ] **Automate retention**, with scheduled deletion or anonymisation per the retention schedule.
- [ ] **Plan the project's end:** what happens to the data when the grant or project closes, and the handover or deletion terms with the donor.
- [ ] **Assess automated decision-making (s.35).** If the app scores or prioritises beneficiaries automatically, for example for eligibility, provide human review and the right to contest.

### 19. Launch gate (sign-off before go-live)

- [ ] ODPC registration is valid and covers this project's purposes
- [ ] The DPIA was submitted at least 60 days earlier and its mitigations are implemented
- [ ] Privacy notice, consent flows, and data subject request channel are live
- [ ] Data processing agreements are signed with all processors
- [ ] Transfer safeguards are documented, or no transfers were confirmed
- [ ] Security testing is done and the breach response plan is tested
- [ ] Staff operating the app are trained

---

## Part C — Ongoing

- [ ] Review the data inventory, DPIA, and policies annually
- [ ] Renew registration before the 24-month expiry
- [ ] Watch ODPC guidance notes, including the sector notes for health and education, and new regulations

**Why it matters:** administrative penalties reach KES 5 million, plus criminal liability of up to KES 3 million and 10 years' imprisonment. The ODPC also compensates complainants directly. It has issued 184 compensation orders and 20 penalty notices to date.

Would you like me to turn this into a shareable, tickable doc your team and developers can work through together?