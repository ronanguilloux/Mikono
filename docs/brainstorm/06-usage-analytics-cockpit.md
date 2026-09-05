# Brainstorm — Usage analytics: an observability cockpit for the app

**Date:** 2026-09-05
**Author:** ronan.guilloux@gmail.com
**Related:** [`AGENTS.md`](../../AGENTS.md),
[`docs/project/hosting-plan.md`](../project/hosting-plan.md),
[`docs/project/next-steps.md`](../project/next-steps.md),
[`docs/adr/`](../adr/)

---

## Primary audience

Whoever is about to paste a `gtag` snippet into
`templates/base.html.twig` — and the ADR that should exist before they
do.

## Desired impact

Knowing how the app is actually used rather than how it was imagined,
without quietly turning a login-protected NGO app into a behavioural
data export about named Kenyan staff. Success at this scale is a short,
deliberate list of events — not a dashboard nobody chose.

## The research (2026-09-05)

The want is real and currently unmet: **which screens get used, which
actions actually get performed, and how the app is used in practice**
rather than how it was imagined. Nothing in the app records this today.
The ask was specifically Google Analytics with JavaScript instrumentation
"here and there". Four things to settle before writing that snippet.

**1. Half of it is already being collected, for free.** Caddy writes a
JSON access log for every request
([`frankenphp/Caddyfile`](../../frankenphp/Caddyfile)), and Docker now
rotates it at 10 MB × 5 files. So "most frequent URLs" is a `docker
compose logs php | jq` away — no code, no third party, no consent
question. **Do that first.** At one or two users on a five-area CRUD app,
it may answer the whole question, and it costs an afternoon of `jq`
rather than a permanent dependency. What it genuinely cannot tell you is
what happens *inside* a page: which filter got used, whether the batch
form is preferred over the single one, where someone abandoned a form.
That gap is the honest case for instrumentation.

**2. Turbo Drive breaks the default GA snippet, silently.** This app uses
Turbo Drive, so navigations replace the body without a full page load.
The standard `gtag` snippet fires `page_view` **once**, on the first
load, and never again — the cockpit would show one pageview per session
and look broken for reasons that have nothing to do with the
configuration. Page views must be sent on the `turbo:load` event, and
custom events wired through Stimulus controllers rather than inline
`onclick`. Anyone implementing this without knowing that will lose an
afternoon to it.

**3. It needs an ADR, because it is a data-protection decision as much as
a technical one.** Every page in this app is behind a login, so every
event is *an identified staff member's behaviour*, and a URL like
`/volunteers/12/edit` carries a record identifier. Sending that stream to
Google means transferring behavioural data about named Kenyan staff to a
US provider — which reopens precisely the question
[`hosting-plan.md`](../project/hosting-plan.md) §5 spent the whole hosting decision
closing. State it at its real size, as §5 does: this is not a blocker, it
is an obligation to document, and at one or two users **consent is a
conversation with Edna, not a cookie banner**. But it should be a
decision on the record rather than a side effect of a `<script>` tag.

That ADR should weigh the lighter options rather than assume GA4:
Plausible or a self-hosted Matomo are cookieless, keep the data out of
the US, and answer "which screens, which actions" perfectly well at this
scale — while GA4's strength (funnels, audiences, attribution over large
traffic) is precisely what a one-user app has no use for.

**4. Implementation notes for whoever does it.**

- The snippet belongs in `templates/base.html.twig`, inside
  `{% block javascripts %}`, and must be **gated on an environment
  variable** holding the measurement ID. Unset in dev, test and CI: a
  local click should never reach the production dashboard, and the
  functional tests assert on rendered page content.
- There is **no Content-Security-Policy** on this app today, so nothing
  needs allowlisting — but if one is ever added, third-party analytics is
  the first thing it will break.
- Events are worth naming deliberately rather than instrumenting
  everything: activity logged, batch form used, report viewed, roster
  copied. A cockpit showing twenty events nobody chose is noise, and the
  interesting question here is a short list.

## The "Options Not Taken"

- **GA4 with an inline `gtag` snippet, as originally asked for.** Not
  rejected, but it is no longer the default: its strengths (funnels,
  audiences, attribution across large traffic) are exactly what a
  one-user app has no use for, and it carries the heaviest
  data-protection cost of the three. If it wins anyway, it wins on the
  record, in an ADR.
- **Plausible or a self-hosted Matomo.** Cookieless, data out of the US,
  and sufficient for "which screens, which actions" at this scale. The
  cost is either a subscription or a second thing to run and back up
  beside the SQLite file.
- **Nothing at all beyond the Caddy access log.** The genuinely lazy
  option, and it must be tried first: `docker compose logs php | jq`
  answers "most frequent URLs" today for zero code, zero third parties
  and zero consent question. It cannot answer what happens *inside* a
  page — and that gap, not the URL counts, is the only honest case for
  instrumenting anything.

## Constraints

- **Turbo Drive**, which silently breaks the default snippet — page views
  must fire on `turbo:load`, custom events through Stimulus controllers.
- **Every page is behind a login**, so every event is an identified staff
  member's behaviour and a URL like `/volunteers/12/edit` carries a
  record identifier. At one or two users, consent is a conversation with
  Edna, not a cookie banner — but it is a conversation that has to
  happen.
- The measurement ID must be an **environment variable**, unset in dev,
  test and CI; the functional tests assert on rendered page content.
- There is **no Content-Security-Policy** on this app today. If one is
  ever added, third-party analytics is the first thing it breaks.
