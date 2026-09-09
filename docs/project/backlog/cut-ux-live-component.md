---
title: Cut symfony/ux-live-component
created: 2026-09-09
source: ponytail-audit
status: needs-decision
size: S
priority: later
labels: [perf]
epic: simplification
---

# Cut symfony/ux-live-component

## Why

Zero `AsLiveComponent`, zero `data-live` — but `live_controller.js` and
`live.min.css` ship eagerly on every page load.

## Done when

Either the package is removed, or the decision to keep it is written down
with what it is being kept *for*.

Removing it touches `composer.json`, `config/bundles.php`,
`config/routes/ux_live_component.yaml`, the importmap and
`assets/controllers.json`.

## Notes & links

[ADR 0003](../../adr/0003-adopt-docker-frankenphp-symfony-sqlite-tailwind-for-volunteer-manager.md)
records it as "installed, not yet used", so **removing it revises that
record rather than merely deleting code** — that is what makes this
`needs-decision` and not `ready`. A superseding ADR, or an amendment
pointer, comes with the diff.

[`../../brainstorm/07-ponytail-audit.md`](../../brainstorm/07-ponytail-audit.md)
has the line counts.
