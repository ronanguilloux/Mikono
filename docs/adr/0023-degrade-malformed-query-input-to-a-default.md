# 23. Degrade malformed query input to a default, never to an error

Date: 2026-09-13

## Status

Accepted

## Context

The app has one non-technical daily user who works from bookmarks, and it
hands out parameterised links itself: the home screen links to
`/activities/new-batch?project=<id>&date=<Y-m-d>`. A truncated or stale
bookmark is therefore the realistic bad URL, not a hand-crafted attack.

Symfony's own readers turn such a URL into an error page:

- `InputBag::get()` throws `BadRequestException` on a non-scalar
  (`?sort[]=x`), so the user gets a 400.
- `InputBag::getInt()` throws on non-numeric input in Symfony 8
  (`?page=abc`), also a 400.
- `app.request.query.get()` in a template throws during rendering, which
  is a 500.

On 2026-09-07, six readers still answered malformed URLs that way while the
rest of the app promised otherwise (`done.md`, 2026-09-07).

## Decision

**Every query parameter the app reads falls back to a defined default when
it is missing, malformed or unknown. A malformed URL never produces a 4xx or
a 5xx.**

- **Read in PHP through `$request->query->all()['x'] ?? null`, then guard
  with `is_scalar()` or `is_string()`** before casting or matching. Never
  `InputBag::get()`, `getInt()`, `getString()` or `getBoolean()`.
- **Read in Twig through `app.request.query.all['x']|default(...)`**, and
  test `value is not iterable` when carrying params through a form. Never
  `app.request.query.get()`.
- **Validate against a server-side whitelist.** Nothing the reader types
  reaches DQL or a date calculation: `ListPaginator::PER_PAGE_OPTIONS`, each
  controller's `SORT_MAP`
  ([ADR 0011](0011-resolve-list-sorting-in-listpaginator-rather-than-knp-sortable.md)),
  `UsageDateRange::PRESETS`, `UsageEventName`.
- **Each reader owns its fallback:**
  - `ListPaginator`: an unknown `perPage` becomes 25; `page` below 1 becomes
    1; a page past the end serves the last page (Knp's
    `page_out_of_range: fix`); an unknown `sort` or `direction` leaves the
    view's own order.
  - `/reports`: an unknown `?tab=` lands on the default tab.
  - `/activities`: a `?volunteer=` filter that doesn't resolve shows the
    unfiltered list.
  - `/activities/new-batch`: a `?project=` or `?date=` prefill that doesn't
    resolve is simply not applied (the date defaults to today).
  - `/usage`: an unknown `range`, an unparseable date (including overflow
    like `2026-13-45`) or a non-scalar falls back to the **default preset,
    not to all time** — the screen opens filtered, so degrading to
    unbounded would show more than the control says. A reversed `from`/`to`
    pair is swapped.
- **The screen shows the resolved value**, not the URL: a filter control
  ticks what was actually applied.

## Consequences

- **Positive:** a stale bookmark or a truncated link always lands on a
  working screen. The whitelists double as the trust boundary between the
  query string and the database.
- **Negative / trade-offs:** fallback is silent — a typo shows the default
  without saying so, which is why the resolved value must be visible. Every
  new parameter needs the same pattern, and only convention and per-reader
  regression tests enforce it: `RouteSmokeTest` requests clean URLs and
  would not catch a new throwing reader.
- **Reversibility:** cheap per parameter, but moving to erroring readers
  would bring back the error pages this ADR exists to remove.

## Alternatives considered

### 1. Symfony's `InputBag` typed getters

**Rejected.** They are the idiomatic choice, and they answer a malformed
bookmark with a 400 — an error page for a non-technical user, often from a
link the app itself produced.

### 2. `#[MapQueryParameter]` or a validated query DTO

**Rejected.** More machinery to reach the same whitelist check, and its
failure mode is still a failed request rather than a default.
