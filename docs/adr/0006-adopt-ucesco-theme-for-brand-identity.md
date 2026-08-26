# 6. Adopt "ucesco-theme" for the Volunteer Manager's brand identity

Date: 2026-08-26

## Status

Accepted

## Context

The Volunteer Manager is a Symfony 8.1 app styled with Tailwind CSS v4
([ADR 0003](0003-adopt-docker-frankenphp-symfony-sqlite-tailwind-for-volunteer-manager.md)),
and `templates/base.html.twig` already lists "UCESCO Africa" as the
`publisher` meta tag — this app already IS UCESCO's internal tool, not a
generic product with its own separate brand.

Despite that, the app had no real branding. The favicon was an inline
`🌍` emoji data-URI SVG, no logo appeared anywhere in the UI, and
`assets/styles/app.css` had no `@theme` block at all — Tailwind v4's
CSS-first config was still the framework default. Every template used
generic `slate` grays for structural chrome, plus semantic `red`/`green`
for flash messages only; there was no named design-token layer to draw
on for buttons, focus states, or the header.

This left the app visually anonymous, with nothing tying it to UCESCO's
actual public identity at ucesco.org.

## Decision

Tie the app's visual identity directly to UCESCO's real brand, sourced
from ucesco.org itself, via a named Tailwind v4 `@theme` block called
**"ucesco-theme"**.

1. Pulled real brand colors directly from ucesco.org's logo SVGs
   (verified via `curl` plus SVG fill-class inspection): primary
   `#00aed9` (cyan-blue, the globe/wordmark color) and secondary
   `#279b48` (green, the laurel-ring color).
2. Defined `ucesco-theme` as a named `@theme` block in
   `assets/styles/app.css` — two full 50–950 color scales
   (`--color-brand-*` and `--color-accent-*`), generated via HSL
   tint/shade at fixed lightness stops with the `-500` step pinned
   exactly to the real brand hex, documented in-file with a comment
   explaining the source and the regeneration method.
3. Downloaded ucesco.org's actual logo SVGs and `favicon.ico`, and
   derived three new asset files under `public/brand/`:
   `ucesco-logo.svg` (full-color stacked lockup), `ucesco-mark.svg`
   (cropped colored globe emblem via a tightened `viewBox`), and
   `ucesco-mark-white.svg` (the same crop from ucesco.org's
   white/reversed logo variant, for dark backgrounds) — plus
   `public/favicon.ico`, an exact copy of ucesco.org's own
   `favicon.ico`.
4. Applied `ucesco-theme` tokens everywhere they read as brand
   identity: favicon links and the `theme-color` meta tag, the header
   background (`bg-brand-900`) and its new logo mark, nav-link hover
   color, every primary CTA button app-wide (`bg-brand-600
   hover:bg-brand-700`, replacing `bg-slate-900 hover:bg-slate-800`
   identically across 11 templates — login plus the index/`_form`
   pairs for activity, activity_type, project, user, and volunteer),
   and every form field's focus border (`focus:border-brand-500`, via
   the global `templates/form/tailwind_theme.html.twig`). `slate` was
   deliberately kept for neutral structural chrome (borders, muted
   text, table rows), and the `red`/`green` flash-message colors were
   left untouched, since those are functional, not brand.
5. Added the full-color logo to the login page above the heading.

## Consequences

- **Positive:** the brand palette now has one documented, regenerable
  home (`assets/styles/app.css`) instead of scattered hex literals.
  `bg-brand-600`, `text-brand-300`, `bg-accent-500`, and so on now work
  like any other built-in Tailwind color scale, so future UI work can
  reach for brand colors by name instead of guessing a hex. The app
  visually reads as UCESCO's own tool rather than an anonymous
  Tailwind starter — real favicon, real logo, brand-colored primary
  actions and focus states throughout.
- **Negative / trade-offs:** the compiled Tailwind CSS must be
  rebuilt (`docker compose exec php bin/console tailwind:build`)
  whenever `app.css` changes — already a documented step in this
  project's CLAUDE.md, not a new burden, but one more thing a
  template-only change won't trigger automatically. The `-500` pin
  plus hand-computed HSL tint/shade steps for the rest of each scale
  is a manual, auditable process rather than a generated one — see
  Alternative 3 below. Brand asset files (`public/brand/*.svg`,
  `public/favicon.ico`) are static snapshots of ucesco.org's current
  logo; if UCESCO rebrands, these need to be re-downloaded and the
  `@theme` hexes re-derived by hand, there's no automated sync.
- **Reversibility:** cheap. The whole palette lives in one `@theme`
  block, and every consuming class is a plain, greppable Tailwind
  utility (`bg-brand-*`, `text-brand-*`, `border-brand-*`,
  `bg-accent-*`) — swapping the two pinned hexes and rebuilding
  Tailwind restyles the entire app consistently. The brand asset files
  under `public/brand/` and `public/favicon.ico` are simple
  replacements if UCESCO's own branding changes.

## Alternatives considered

### 1. Keep generic Tailwind slate/blue, no brand tie-in

**Rejected.** This app already is UCESCO's internal tool — its own
`base.html.twig` lists "UCESCO Africa" as publisher — so shipping it
with a generic, unbranded look misrepresents that relationship for no
benefit. There was no competing product identity to protect by staying
neutral.

### 2. Hand-pick a single accent color instead of deriving from the real logo

**Rejected.** The ask was explicitly to match ucesco.org's actual
brand, not to invent a compatible-looking color. Picking an
arbitrary accent would have been faster but would drift from
UCESCO's real visual identity the moment someone compared the two
sites side by side.

### 3. Tailwind's default color-scale generation (e.g. OKLCH-based) instead of hand-computed HSL stops

**Rejected**, for this pass. Tailwind v4 ships OKLCH-based default
scales, and a perceptually-uniform generator would be a reasonable
long-term approach, but it adds tooling and conceptual overhead for a
two-color palette. The simpler, fully auditable HSL tint/shade method
— fixed hue/saturation, varying lightness per step, `-500` pinned to
the real brand hex, documented inline — was kept instead. Revisit if
more brand colors are added and manual HSL tuning stops scaling.
