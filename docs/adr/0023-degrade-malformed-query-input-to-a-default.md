# 23. Treat malformed query input as a regular error

Date: 2026-10-31

## Status

Accepted

## Context

A query string can arrive malformed: a truncated or stale bookmark, a
hand-edited URL, a client's wrong call. Absorbing that takes a guard, a
fallback and a regression test on every parameter, for a case the app has no
need to handle.

Symfony already answers it, with one exception that matters:

- `InputBag::get()` throws `BadRequestException` on a non-scalar
  (`?sort[]=x`), which is a 400.
- `InputBag::getInt()` throws it on non-numeric input (`?page=abc`), also a
  400.
- `app.request.query.get()` in a template throws during rendering, which is
  a 500, a server fault for what is a bad request.

`/api` and `/mcp`
([ADR 0050](0050-expose-a-read-only-api-and-mcp-server-to-claude-behind-oauth.md))
add a force of their own. A client that can't see a screen gets only the
answer, so a silently ignored filter reads as a true result. API Platform
doesn't refuse everything by default: `DateFilter` ignores a date it can't
parse.

## Decision

**A malformed query string is a regular error, on the screens as on `/api`
and `/mcp`: one rule, with no split between them.**

- **Malformed input is a 4xx.** New screen code adds no fallback for it and
  no malformed-value test. A new API filter does get that test: a person sees
  a filter that wasn't applied, Claude doesn't. Symfony's typed readers (`InputBag::getInt()` and so
  on) are acceptable in new controller code, since a 400 is the wanted
  answer.
- **A record id that no longer exists is a 404 on a screen.**
- **A missing optional parameter uses its normal default.** That is ordinary
  behaviour, not error handling.

**The allowlists stay.** `ListPaginator::PER_PAGE_OPTIONS`, each controller's
`SORT_MAP`
([ADR 0011](0011-resolve-list-sorting-in-listpaginator-rather-than-knp-sortable.md))
and `UsageDateRange::PRESETS` keep what the reader types out of DQL and date
calculations. They are the trust boundary between the query string and the
database, so dropping the fallback never means dropping the check. A value
outside the list is refused in new code.

**The Twig ban stays.** Never `app.request.query.get()`: it gives a 500, not
a 400. Read through `app.request.query.all['x']|default(...)`, and test
`value is not iterable` when carrying params through a form.

**The API's mechanisms stay**, because the rule holds there only if something
enforces it. Under `/api`, and through it `/mcp`:

- **A value a parameter can't take is a 400:** an unknown enum value, an
  unparseable date, a non-scalar. So is a required parameter that is missing,
  and a parameter the route doesn't know, so `statuss=present` fails:
  `strict_query_parameter_validation` refuses undeclared names. An unknown
  report is a 404.
- **A date filter carries an `Assert\Date` constraint**, and a date a
  `DateFilter` receives is a 422 from it. Without the constraint the filter
  ignores the value and answers 200.
- **A report's parameters are listed in `ApiController::REPORT_PARAMETERS`.**
  A new one goes there.
- **An id that can't resolve never means "no filter",** whether it is
  malformed or points at nothing. It matches nobody, or it is refused.
- **`itemsPerPage` is the one exception:** a bad value falls back to the
  default page size. That changes how much comes back, never which records
  match, and `totalItems` stays true.
- **Refuse explicitly.** Throw `BadRequestHttpException` with what was
  expected, so the client can correct the call.
- **An MCP tool hands the refusal back as a tool error carrying the HTTP
  status**, so Claude reads why and doesn't answer from it.

**Existing screens keep their fallbacks until someone changes that code.**
Nothing is refactored for this rule. Those readers go through
`$request->query->all()` guarded by `is_scalar()` or `is_string()`, and each
owns its fallback:

- `ListPaginator`: an unknown `perPage` becomes 25; `page` below 1 becomes 1;
  a page past the end serves the last page (Knp's
  `page_out_of_range: fix`); an unknown `sort` or `direction` leaves the
  view's own order.
- `/reports/volunteers`: an unknown `?tab=` lands on the default tab.
- `/activities`, `/volunteers` and `/matches`: a filter value that doesn't
  resolve means no filter.
- `/activities/new`: a `?project=` or `?date=` prefill that doesn't resolve
  is not applied, and the date defaults to today.
- `/usage`: an unknown `range`, an unparseable date (including overflow like
  `2026-13-45`) or a non-scalar falls back to the **default preset, not to
  all time**. The screen opens filtered, so an unbounded fallback would show
  more than the control says. A reversed `from`/`to` pair is swapped.

While a fallback lives, the screen shows the resolved value, not the URL: a
filter control ticks what was actually applied.

## Consequences

- **Positive:**
  - A new parameter costs less: no guard, no fallback, no test for a bad
    value.
  - The rule is the same everywhere.
  - On the API, a misspelt filter fails where Claude can see it, and can't
    come back as "every volunteer".
- **Negative / trade-offs:**
  - A refused URL shows Symfony's generic error page, with no navigation,
    because the app has no custom error templates.
  - Two styles coexist, and a reader has to look at a parameter's code to
    know which one it follows. A fallback is silent, too: a typo shows the
    default without saying so.
  - The app's own links fall under the same rule when changed, for example
    home's `/activities/new?project=<id>&date=<Y-m-d>`: a link to a deleted
    record becomes a regular error once its reader is rewritten.
  - Only convention holds the Twig ban: `RouteSmokeTest` requests clean URLs,
    so it would not catch a `query.get()`. A date filter missing its
    constraint is a silent 200, which is what the API's malformed-value test
    is for.
- **Reversibility:** cheap. The rule binds new code only, so going back means
  adding a fallback to the parameters written under it.

## Alternatives considered

### 1. Keep degrading on the screens

**Rejected.** It pays on every parameter, in a guard, a fallback and a test,
for a case we don't want to handle.

### 2. Convert every existing reader now

**Rejected.** That is about 23 readers, 14 Twig spots and their tests, for
little code saved. Those fallbacks work, so a reader moves when someone has
another reason to change it.

### 3. Keep the rule, justified by the app's own links going stale

**Rejected.** It has the same cost as alternative 1, paid on every parameter,
under a different justification.

### 4. Degrade on the API too

**Rejected.** A person can notice a fallback on a screen, by seeing the
resolved value. A client reading JSON has no such control, and would report
an unfiltered list as the filtered answer.
