---
title: Reconsider knplabs/knp-paginator-bundle
created: 2026-09-09
source: ponytail-audit
status: needs-decision
size: M
priority: later
labels: [perf]
epic: simplification
---

# Reconsider knplabs/knp-paginator-bundle

## Why

The honest caveat first: this is
[ADR 0009](../../adr/0009-adopt-knppaginatorbundle-for-list-pagination.md)
and [ADR 0011](../../adr/0011-resolve-list-sorting-in-listpaginator-rather-than-knp-sortable.md),
and cutting it **adds** roughly 40 lines.

But `ListPaginator` already parses `page`/`perPage`/`sort`/`direction`
itself, switches the bundle's sorting off with
`SORT_FIELD_PARAMETER_NAME => null`, and `PaginationBar` bypasses
`knp_pagination_render()` for want of a translator. What is left of the
bundle is a count, a slice and a page window.

## Done when

Decided. **Only worth doing behind a superseding ADR** — and it is the
lowest priority in this folder, so "decided to keep it" is a perfectly
good outcome to write down and close.

## Notes & links

The bundle is also one of the two packages wired by hand because
`composer.json` sets `allow-contrib: false` (`config/bundles.php` +
`config/packages/knp_paginator.yaml`). Removing it removes that
special case too.

[`../../brainstorm/07-ponytail-audit.md`](../../brainstorm/07-ponytail-audit.md)
