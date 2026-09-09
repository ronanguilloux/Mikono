---
title: Finish the batch form's typeahead for screen readers
created: 2026-09-09
source: ponytail-audit
status: ready
size: S
priority: next
labels: [a11y, ux]
---

# Finish the batch form's typeahead for screen readers

## Why

`#volunteer-suggestions` has no `role="listbox"`, its rows no
`aria-selected`, and the combobox no `aria-activedescendant` — so the
highlight is a background colour that nothing announces.

**This is not the ponytail audit's original bullet**, which proposed
replacing the hand-rolled listbox in `batch_activity_form_controller.js`
with a label filter. Those 168 lines buy real keyboard behaviour
(Up/Down/Enter/Escape, the mousedown-vs-blur race, `aria-expanded`) and
deleting them would be a regression. The gap runs the other direction:
the widget behaves correctly and says nothing about it.

## Done when

A screen reader announces the active suggestion as the arrow keys move
through the list. A few lines in
`templates/activity/new_batch.html.twig` and the controller's
`highlightRows()`.

## Notes & links

- [`../../brainstorm/07-ponytail-audit.md`](../../brainstorm/07-ponytail-audit.md)
  — the audit this came out of, including the correction above.
- `assets/controllers/batch_activity_form_controller.js`
