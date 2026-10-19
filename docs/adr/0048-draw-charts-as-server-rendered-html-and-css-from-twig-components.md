# 0048. Draw charts as server-rendered HTML and CSS from Twig components, with two client-side exceptions

Date: 2026-10-19

## Status

Accepted

## Context

The reports are KPI tiles and tables. Questions like "what did we
achieve, month by month?", "are we growing?" or "where do our volunteers
come from?" mean reading a table row by row. Achievements carry a
quantity and an indicator
([ADR 0047](0047-count-achievements-with-an-optional-indicator-and-quantity.md)),
and volunteers a country of residence, so there are numbers worth
drawing. The
[dataviz-first-charts](../project/backlog/dataviz-first-charts.md) card
lists more charts to come on data the schema already holds.

The charts have to work under this project's constraints. The stack is
Symfony UX with Turbo Drive
([ADR 0003](0003-adopt-docker-frankenphp-symfony-sqlite-tailwind-for-volunteer-manager.md)).
Contrib recipes are off, so every new package is wired by hand. The
Reports pages print. The Volunteer Manager reads them on a phone as often
as on a desk, and a phone has no hover. Every list exports
([ADR 0029](0029-export-every-list-view-to-csv-or-xlsx-with-openspout-open-to-all-signed-in-staff.md)),
and screen readers need every number as text. A third party that would
receive personal data, such as a map tile server seeing staff IPs, needs
an ADR of its own
([ADR 0034](0034-comply-with-kenyas-data-protection-act-2019.md), rule 8).

Country of residence is the one geographic field worth mapping: it is
filled for about 395 of 428 volunteers and spans about fifty countries,
most of them in Europe; nationality is filled for 13. Reading Europe on a
world map needs pan and zoom. The options weighed are in
[`docs/brainstorm/14-dataviz-for-vm-and-management.md`](../brainstorm/14-dataviz-for-vm-and-management.md).

The stay page (`/stays/{id}`) asks a part-to-whole question: where did a
volunteer's days go, across a handful of programs or activity types?
That needs several categories told apart by colour, so a validated
categorical palette, and a form the owner reads at a glance; eight
variants were rendered side by side on that page to choose one.

## Decision

**Charts are drawn as server-rendered HTML and CSS from a Twig
component, with no chart library and no JavaScript — except the world map
on `/reports/origins` (jsvectormap) and the pies on the stay page
(Chart.js), which a lazy Stimulus controller draws client-side from
values the server computes, with every number also printed as text.**

- **One component per chart form.** Each is an anonymous TwigComponent in
  `templates/components/`. Its classes stay literal, never assembled from
  strings, so Tailwind finds them.
- **Column chart.** `<twig:ColumnChart :title summary :columns limit />`
  draws one series: `columns` is a list of `{label, title, value}`, oldest
  first. Each column is a flex item whose bar height is an inline
  `style="height: N%"` of the larger of the series' peak and the limit.
  A non-zero column gets at least 3% so it stays visible. Bars are
  `bg-brand-600`, 2px apart, rounded on top and standing on a slate
  baseline, with labels underneath; `sparseLabels` hides every other
  label when labels are longer than a month's three letters, and every
  label and column is `min-w-0`, so a long label never widens the chart
  past its column.
- **A limit line.** ColumnChart's optional `limit` (a branch's capacity,
  [ADR 0049](0049-record-branch-capacity-and-program-volunteers-needed-as-optional-targets.md))
  is a dashed slate line labelled at its right end. Columns above it turn
  `bg-amber-700` (`#bb4d00`), and the caller's summary says so in words,
  so it is never colour alone.
- **Bar chart.** `<twig:BarChart :title summary :rows />` draws one
  series as horizontal bars, for many named categories whose labels
  wouldn't fit under columns on a phone. `rows` is a list of
  `{label, value, detail?}`, largest first; `detail` is an optional share
  shown after the value. The label column is `min-w-0` and truncates.
  **Value and detail have fixed widths, not their content's:** each row is
  its own grid, so bars only compare if every row's bar track is equally
  wide. Don't let those cells size to content.
- **Scale without an axis.** The caller's `summary` gives the scale in
  words — a total and a peak, or a capacity and the first week over it.
  That works on a phone, where there is no hover. On ColumnChart a
  CSS-only tooltip per column (`group-hover`) shows the value on a
  desktop; its hit target is the whole column, not just the bar.
- **Accessible numbers.** Bars are `aria-hidden`. BarChart's labels and
  values are visible text, so it needs nothing more. ColumnChart puts its
  numbers in a visually hidden table. **The `sr-only` class sits on a
  wrapping `div`, not on the `table`:** a table keeps its content width
  whatever its own width says, so an `sr-only` table pushed the page
  sideways at 375px. Don't move the class onto the table.
- **The table stays the source, unless the chart is one.** A chart sits
  above the table it summarises. A chart whose every label and value is
  visible text (BarChart) may stand in for that table; the list's export
  stays regardless
  ([ADR 0029](0029-export-every-list-view-to-csv-or-xlsx-with-openspout-open-to-all-signed-in-staff.md)).
  Charts print with the page, and each has `break-inside-avoid`.
- **Part-to-whole charts share one legend.**
  `<twig:ChartLegend :rows />` prints each row's swatch, label
  (truncating), value and share as visible text, so identity is never
  colour alone and no hidden table is needed. ChartJsPie and WaffleChart
  both use it; `rows` is a list of `{label, value}`, largest first, and
  swatches take `chart_palette` in row order, the order the chart paints
  in.
- **Waffle chart.** `<twig:WaffleChart :title summary :rows />` draws one
  square per unit of value (on the stay page, one square is one day),
  coloured by category, with ChartLegend below. Plain HTML and CSS; the
  squares are `aria-hidden`.
- **Grid cells.** A chart placed in a CSS grid cell needs `min-w-0` on the
  cell or the component, or a long label or legend widens the page on a
  phone.
- **The one exception: the world map.** `/reports/origins` ("Volunteers'
  Origins", Reports menu) shows a choropleth of country of residence,
  drawn by jsvectormap 1.7.0 (MIT). Its regions are keyed by ISO 3166-1
  alpha-2 codes and it uses no tile provider, so no request leaves the app.
  - **Importmap only:** three entries in `importmap.php` — `jsvectormap`,
    `jsvectormap/dist/jsvectormap.min.css`,
    `jsvectormap/dist/maps/world.js` — loaded by the Stimulus controller
    `assets/controllers/world_map_controller.js`, marked
    `stimulusFetch: 'lazy'` so no other page downloads it.
  - **The server decides, the JS only draws.** `OriginReportController`
    passes `fills` and `titles` keyed by ISO code as Stimulus values. No
    binning or wording happens in JavaScript.
  - **Traps.** The map file registers itself on a global, so
    `window.jsVectorMap` must be set before its dynamic import.
    jsvectormap measures its container once, and the stylesheet arrives
    after the map is built, so a `ResizeObserver` calls `updateSize()`.
    `destroy()` leaves the SVG behind, so `disconnect()` empties the
    element, or Turbo's cached copy gets a second map.
  - **Accessibility.** The map is `aria-hidden`. The BarChart below it,
    "Volunteers per country of residence", carries every country, count
    and share (of volunteers with a country recorded) as text, so the
    page has no table under it. The export (`report_origins_export`) has
    the columns Country of residence, Code, Volunteers, Share; the
    "Not recorded" row has no share.
  - **Countries too small to draw.** jsvectormap's world map has no
    region for Monaco, Hong Kong, Singapore, Mauritius and the like.
    Those are named in words under the map. The drawable codes are the
    `DRAWN` constant in `src/Controller/OriginReportController.php`.
  - The data is `VolunteerRepository::countByCountryOfResidence()` —
    residence, not nationality.
- **The second exception: pies on the stay page.** "Where the days went"
  on `/stays/{id}` shows two pies, days by program and days by activity
  type, with a waffle of days by program below. A pie read best of the
  eight forms compared for a handful of categories.
  - **Importmap only:** `chart.js` 4.5.1 and its dependency
    `@kurkle/color` in `importmap.php`, no Node build. The Stimulus
    controller `assets/controllers/dispatch_pie_controller.js` is marked
    `stimulusFetch: 'lazy'` and registers only `PieController`,
    `ArcElement` and `Tooltip`.
  - **The server decides, the JS only draws.**
    `<twig:ChartJsPie :title summary :rows unit='days' />` passes labels,
    values and colours as Stimulus values; the controller draws the pie
    and adds a hover tooltip. `disconnect()` destroys the chart, so
    Turbo's cached copy doesn't keep it.
  - **Trap.** A lone slice gets no border, or Chart.js draws a white seam
    at twelve o'clock.
  - **Accessibility.** The canvas is `aria-hidden`; the ChartLegend beside
    it carries every label, value and share as text.
  - **The measure.** Every activity in the stay, planned ones included,
    counts as one day whatever its duration, because the VM counts days
    given, not hours spent, and a stay should show its expected outcome
    before it ends (`App\Report\VolunteerImpactCalculator`).
- **First use of ColumnChart: "Impact by month"** at the top of
  `/reports/achievements`. It shows one chart per indicator, never summed
  across indicators
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
  colour-vision deficiency. The map uses a five-step, single-hue brand
  ramp — 1, 2–4, 5–9, 10–24, 25+ volunteers → `#00aed9`, `#0085a6`,
  `#00647d`, `#004a5c`, `#002129`; none → slate-200 `#e2e8f0` — which
  passed the ordinal validator: lightness falls monotonically, and the
  lightest step has 2.61:1 contrast on white. The app has no dark mode,
  so one validated set is enough. Categories take the Twig global
  `chart_palette` (`config/packages/twig.yaml`): `#0085a6`, `#eb6834`,
  `#1baf7a`, `#eda100`, `#e87ba4`, `#008300`, `#6250d6`, `#e34948` —
  brand-600 first, then the dataviz skill's reference hues, assigned in
  that fixed order and never cycled. It passed the categorical validator
  on a light surface; three slots fall under 3:1 contrast, which is why
  every chart using them prints its labels. Beyond eight categories the
  smallest fold into "Other" (`VolunteerImpactCalculator::MAX_SHARES`).
  No dual axes.

## Consequences

- **Positive:**
  - Every chart but the map and the pies adds no dependency, renders,
    navigates under Turbo and prints like the rest of the page, and keeps
    its text size on a phone.
  - The map gives pan and zoom where a static map leaves Europe
    unreadable, at no cost to other pages: it is lazy-loaded, and sends
    nothing to a third party.
  - The pies follow the map's pattern, so neither exception carries a
    number only JavaScript knows; Chart.js loads on the stay page only.
  - Charts are tested by asserting markup
    (`tests/Functional/AchievementControllerTest.php`,
    `tests/Functional/OriginReportControllerTest.php`). The numbers are
    tested in the calculator
    (`tests/Integration/Report/ImpactByMonthCalculatorTest.php`).
- **Negative / trade-offs:**
  - Every new chart form (stacked bars, a timeline) means a new
    component, built by hand, and `tailwind:build` must run after a
    change.
  - Heights and widths are inline `style` attributes. If a
    Content-Security-Policy with `style-src` is ever added, it must allow
    them.
  - Outside the map, interactivity is limited to a CSS hover. There is no
    zoom, brushing or toggling of series.
  - The map and Chart.js are two JavaScript dependencies to keep
    current, and neither carries numbers a screen reader, a phone without
    hover or a printout can rely on; the bar chart below the map and the
    legend beside each pie must stay.
  - A ninth category can't get its own colour; it folds into "Other".
  - A country absent from jsvectormap's world map can't be coloured; the
    `DRAWN` list must follow the library if it is upgraded.
- **Reversibility:** cheap. A chart is one template plus its data, and
  the table or text chart beside it carries the same numbers. Swapping a
  component's internals for SVG or a library leaves its callers
  unchanged. Dropping the map means deleting its controller, three
  importmap entries and a block of the template; the bar chart already
  carries the page. Dropping the pies means deleting their controller,
  two importmap entries and ChartJsPie; the waffle and legends already
  carry the numbers.

## Alternatives considered

### 1. `symfony/ux-chartjs`, or Chart.js for every chart

**Rejected.** The bundle is a new dependency, and under
`allow-contrib: false` it would be wired by hand
([ADR 0003](0003-adopt-docker-frankenphp-symfony-sqlite-tailwind-for-volunteer-manager.md)).
A canvas doesn't print well on the Reports pages. Chart.js runs
JavaScript to draw data that never changes once the page is rendered,
and its charts have to be set up and torn down around Turbo navigation.
The stay-page pies accept those costs for one form, from the importmap
directly, because the legend beside them carries every number.

### 2. Inline SVG with a `viewBox` for bar and column charts

**Rejected.** A `viewBox` scales text down along with the chart, so
labels shrink to about 6px on a phone, while HTML text keeps its size.
Bar geometry needs nothing SVG provides. Revisit SVG for a form HTML
can't draw well, such as a line chart. A Gantt-style look-ahead with a
today line may still work in HTML.

### 3. A JavaScript library such as D3 or ECharts

**Rejected.** It has the same objections as `ux-chartjs`, and it is
heavier. Revisit a library only if someone asks for interactivity beyond
hover, such as zoom or brushing — the reason the world map is the
exception.

### 4. No world map, bars only

**Rejected.** The brainstorm judged a map tempting but bars better at
around ten countries. Residence turned out to span about fifty, and the
owner wanted the spread visible at a glance. The bars stay, below the
map, as the readable and accessible form.

### 5. A server-rendered inline-SVG world map

**Rejected.** It was built and compared side by side with the
jsvectormap version on `/reports/origins`, and the owner chose the
interactive one: without pan and zoom, Europe, where most volunteers
live, is too small to read.

### 6. Leaflet or `symfony/ux-map` over map tiles

**Rejected.** Tiles come from a tile provider, which would see every
staff member's IP on each view of the page. A choropleth would still need
country geometry on top, so tiles add a third party without removing the
work.

### 7. Other forms for "where the days went"

**Rejected.** Each was rendered on `/stays/{id}` beside the pie and the
waffle, and the owner kept those two after comparing them on screen:
horizontal bars with share (BarChart), a 100% stacked strip, a CSS
conic-gradient donut, a Chart.js doughnut, nested project → program
bars, a programs × activity-types heat table, and weekly columns over
the stay.
