# 0048. Draw charts as server-rendered HTML and CSS from Twig components

Date: 2026-10-11

## Status

Accepted

## Context

The reports are KPI tiles and tables. Questions like "what did we
achieve, month by month?" or "are we growing?" mean reading a table row
by row. Achievements now carry a quantity and an indicator
([ADR 0047](0047-count-achievements-with-an-optional-indicator-and-quantity.md)),
so there are numbers worth drawing. The
[dataviz-first-charts](../project/backlog/dataviz-first-charts.md) card
lists more charts to come on data the schema already holds.

The charts have to work under this project's constraints. The stack is
Symfony UX with Turbo Drive
([ADR 0003](0003-adopt-docker-frankenphp-symfony-sqlite-tailwind-for-volunteer-manager.md)).
Contrib recipes are off, so every new package is wired by hand. The
Reports pages print. The Volunteer Manager reads them on a phone as often
as on a desk, and a phone has no hover. Every list already exports from
its table
([ADR 0029](0029-export-every-list-view-to-csv-or-xlsx-with-openspout-open-to-all-signed-in-staff.md)),
and screen readers read that table. The options weighed are in
[`docs/brainstorm/14-dataviz-for-vm-and-management.md`](../brainstorm/14-dataviz-for-vm-and-management.md).

## Decision

**Charts are drawn as server-rendered HTML and CSS from a Twig
component, with no chart library and no JavaScript.**

- **One component per chart form.** The column chart is the anonymous
  TwigComponent `templates/components/ColumnChart.html.twig`,
  `<twig:ColumnChart :title summary :columns limit />`. It draws one
  series: `columns` is a list of `{label, title, value}`, oldest first.
  Each column is a flex item whose bar height is an inline
  `style="height: N%"` of the larger of the series' peak and the limit.
  A non-zero column gets at least 3% so it stays visible. Bars are
  `bg-brand-600`, 2px apart, rounded on top and standing on a slate
  baseline, with labels underneath; `sparseLabels` hides every other
  label when labels are longer than a month's three letters, and every
  label and column is `min-w-0`, so a long label never widens the chart
  past its column.
- **A limit line.** An optional `limit` (a branch's capacity,
  [ADR 0049](0049-record-branch-capacity-and-program-volunteers-needed-as-optional-targets.md))
  is a dashed slate line labelled at its right end. Columns above it turn
  `bg-amber-700` (`#bb4d00`), and the caller's summary says so in words,
  so it is never colour alone.
- **Scale without an axis.** The caller's `summary` gives the scale in
  words — a total and a peak, or a capacity and the first week over it.
  That works on a phone, where there is no hover. A CSS-only tooltip per
  column (`group-hover`) shows the value on a desktop. Its hit target is
  the whole column, not just the bar.
- **Accessible numbers.** The bars are `aria-hidden`. The same numbers go
  in a visually hidden table. **The `sr-only` class sits on a wrapping
  `div`, not on the `table`:** a table keeps its content width whatever
  its own width says, so an `sr-only` table pushed the page sideways at
  375px. Don't move the class onto the table.
- **First use: "Impact by month"** at the top of `/reports/achievements`.
  It shows one chart per indicator, never summed across indicators
  ([ADR 0047](0047-count-achievements-with-an-optional-indicator-and-quantity.md)).
  The window is the last 12 Nairobi calendar months
  ([ADR 0024](0024-treat-dates-as-calendar-days-in-nairobi-time.md)),
  zero months included so the charts line up, and the largest total comes
  first. When nothing was counted, the section shows a hint pointing to
  Settings → Indicators. The data comes from
  `App\Report\ImpactByMonthCalculator` over
  `AchievementRepository::findCountedBetween()`. Months are grouped in
  PHP, not with SQLite date functions in DQL, so the query stays portable
  off SQLite.
- **Colour.** One series uses `brand-600` (`#0085a6`), and columns over a
  limit `amber-700` (`#bb4d00`). Together they passed the dataviz palette
  validator: both fall inside the lightness band, have enough chroma and
  at least 3:1 contrast against the surface, and stay apart under every
  colour-vision deficiency. The
  app has no dark mode, so one validated colour is enough. A future
  multi-series chart runs the validator on its categorical palette and
  assigns hues in a fixed order. No dual axes.
- **The table stays the source.** Each chart sits above the table it
  summarises. The table and its export are unchanged
  ([ADR 0029](0029-export-every-list-view-to-csv-or-xlsx-with-openspout-open-to-all-signed-in-staff.md)).
  Charts print with the page, and each chart has `break-inside-avoid`.

## Consequences

- **Positive:**
  - No new dependency, so nothing to wire by hand and nothing to audit.
  - A chart renders, navigates under Turbo and prints like the rest of
    the page.
  - Text keeps its size on a phone.
  - Charts are tested by asserting markup
    (`tests/Functional/AchievementControllerTest.php`). The numbers are
    tested in the calculator
    (`tests/Integration/Report/ImpactByMonthCalculatorTest.php`).
- **Negative / trade-offs:**
  - Every new chart form (stacked bars, a timeline) means a new
    component, built by hand.
  - Tailwind only sees classes it finds spelled out in templates. The
    component's classes must stay literal, never assembled from strings,
    and `tailwind:build` must run after a change.
  - Heights are inline `style` attributes. If a Content-Security-Policy
    with `style-src` is ever added, it must allow them.
  - Interactivity is limited to a CSS hover. There is no zoom, brushing
    or toggling of series.
- **Reversibility:** cheap. A chart is one template plus a calculator,
  and the table below it carries the same data. Swapping the component's
  internals for SVG or a library leaves its callers unchanged.

## Alternatives considered

### 1. `symfony/ux-chartjs`

**Rejected.** It is a new dependency, and under `allow-contrib: false`
it would be wired by hand
([ADR 0003](0003-adopt-docker-frankenphp-symfony-sqlite-tailwind-for-volunteer-manager.md)).
Its canvas doesn't print well on the Reports pages. It runs JavaScript to
draw data that never changes once the page is rendered, and its charts
have to be set up and torn down around Turbo navigation.

### 2. Inline SVG with a `viewBox`

**Rejected.** This was the brainstorm's proposal and was dropped while
building the first chart. A `viewBox` scales text down along with the
chart, so labels shrink to about 6px on a phone, while HTML text keeps
its size. Bar geometry needs nothing SVG provides. Revisit SVG for a form
HTML can't draw well, such as a line chart. A Gantt-style look-ahead with
a today line may still work in HTML.

### 3. A JavaScript library such as D3 or ECharts

**Rejected.** It has the same objections as `ux-chartjs`, and it is
heavier. Revisit a library only if someone asks for interactivity beyond
hover, such as zoom or brushing.
