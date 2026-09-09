# Next steps

**Last updated:** 2026-09-09

An **index**, not a list of items. Every open item is a card in
[`backlog/`](backlog/); this file holds only links to them, ordered, in
three buckets. If you find yourself writing a sentence *about* an item
here, it belongs in the card instead — two mutable lists describing the
same work is how this file reached 449 lines once.

Finished work leaves the backlog entirely: an architectural decision
becomes an ADR in [`../adr/`](../adr/), anything else a dated entry in
[`done.md`](done.md). See [`README.md`](README.md) for that rule and
[`backlog/README.md`](backlog/README.md) for the card format. Conventions
and commands are in [`AGENTS.md`](../../AGENTS.md), not here.

Status flags below: **D** needs a decision · **G** needs a design pass ·
**B** blocked on someone · **Z** deferred behind a trigger. No flag means
ready to open and work.

## Now

Three things gate real volunteer data landing on a server, and the
hostname is the one that isn't ours to solve.

1. [An encrypted off-site copy of the backups](backlog/off-site-encrypted-backups.md)
2. [Meet Nickson about the production hostname and DNS](backlog/nickson-meeting-dns-and-hosting.md)
3. [Decide whether UAT and production share one box](backlog/uat-and-production-one-box-or-two.md) — **D**
4. [Size production off the 1 GB plan](backlog/production-vps-sizing.md) — **B**
5. [A production hostname that resolves](backlog/production-hostname-dns.md) — **B**

## Next

1. [Report login attempts on /usage](backlog/usage-login-audit-trail.md) — **D**
2. [Finish the batch form's typeahead for screen readers](backlog/batch-typeahead-screen-reader.md)
3. [Where escort should be read back out](backlog/escort-display-and-reporting.md) — **G**
4. [Two open questions for Edna about the roster archive](backlog/roster-archive-open-questions.md) — **B**

## Later

Simplification, from
[the ponytail audit](../brainstorm/07-ponytail-audit.md) — independent of
each other, take them in any order or none:

- [Cut symfony/ux-live-component](backlog/cut-ux-live-component.md) — **D**
- [Drop /activities/new](backlog/drop-single-volunteer-activity-form.md) — **D**
- [Decide what Escort::$isActive is for](backlog/decide-escort-is-active.md) — **D**
- [/reports walks every activity three times](backlog/reports-triple-walk-performance.md) — **G**
- [Reconsider knplabs/knp-paginator-bundle](backlog/reconsider-knp-paginator.md) — **D**

Deferred behind a trigger — each card names the event that wakes it:

- [SQLite journal mode (WAL)](backlog/sqlite-wal-journal-mode.md) — **Z**
- [Task/assignment hand-offs](backlog/task-assignment-handoffs.md) — **Z**
- [Uganda rosters and a third ProjectLocation](backlog/uganda-project-location.md) — **Z**
- [Automated outbound reminders](backlog/automated-outbound-reminders.md) — **Z**
- [Scheduled donor digest emails](backlog/scheduled-donor-digest-emails.md) — **Z**
- [WhatsApp Business API / roster sending](backlog/whatsapp-roster-sending.md) — **Z**
