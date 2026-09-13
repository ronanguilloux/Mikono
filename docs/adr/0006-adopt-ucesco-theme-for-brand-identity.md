# 6. Adopt "ucesco-theme" for the Volunteer Manager's brand identity

Date: 2026-08-26

## Status

Accepted

## Context

The app is UCESCO's own internal tool, not a product with a separate brand,
yet it shipped visually anonymous: an emoji favicon, no logo, and Tailwind's
default palette with generic `slate` everywhere. There was no named colour
token to reach for on buttons, focus states or the header.

## Decision

**The app's visual identity comes from ucesco.org itself, through a named
Tailwind v4 `@theme` block, "ucesco-theme", in `assets/styles/app.css`.**

- Two colours taken from ucesco.org's logo SVGs: primary `#00aed9`
  (globe/wordmark cyan) and secondary `#279b48` (laurel green).
- Two full 50–950 scales, `--color-brand-*` and `--color-accent-*`, built by
  HSL tint/shade at fixed lightness stops with **`-500` pinned exactly to the
  brand hex**. The method is documented in a comment in the file.
- Brand tokens go where identity shows: header (`bg-brand-900`) and logo
  mark, nav hover, every primary button (`bg-brand-600 hover:bg-brand-700`),
  form focus borders (via `templates/form/tailwind_theme.html.twig`),
  `theme-color`.
- `slate` stays for neutral structure (borders, muted text, table rows);
  red and green stay for flash messages, because those are functional, not
  brand.
- Logos and favicon are copies of ucesco.org's own, in `public/brand/`
  (full lockup, cropped mark, white mark for dark backgrounds) and
  `public/favicon.ico`.

## Consequences

- **Positive:** brand colours are named utilities (`bg-brand-600`,
  `text-accent-500`) with one documented, regenerable home. The app reads as
  UCESCO's own tool.
- **Negative / trade-offs:** CSS changes need `tailwind:build`. The scales
  are hand-computed, and the brand assets are static snapshots — a UCESCO
  rebrand means re-downloading and re-deriving by hand.
- **Reversibility:** cheap — change two hexes, rebuild.

## Alternatives considered

### 1. Keep generic Tailwind colours

**Rejected.** Misrepresents the app's relationship to UCESCO for no benefit.

### 2. Hand-pick a compatible accent colour

**Rejected.** The ask was to match UCESCO's real identity, not approximate it.

### 3. OKLCH-generated scales

**Rejected for a two-colour palette.** More tooling for no visible gain;
revisit if more brand colours are added and hand-tuning stops scaling.
