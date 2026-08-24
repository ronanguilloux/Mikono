# Import versions

Every skill in this directory that came from an external package is
pinned to a specific commit at import time, not a moving branch. Re-check
these on the cadence in `docs/adr/0002-stage-symfony-php-skills-in-agents-skills.md`
(every 3 months, or whenever Symfony/PHP ships a release) — the source
repos are community-maintained (Symfony UX partially excepted) and can
drift ahead of what these skills describe.

| Package | Source | Imported at | Date |
| --- | --- | --- | --- |
| `superpowers-symfony` | <https://github.com/dev-toolings/superpowers-symfony> (moved from `MakFly/superpowers-symfony`) | `29d7fff67173360328cc1f81a9c522f151f8a12c` | 2026-08-24 |
| `symfony-ux-skills` | <https://github.com/smnandre/symfony-ux-skills> | `1e99301a6255724eca9c49ce9cdb8c241771ab05` | 2026-08-24 |
| `php-modernization-skill` | <https://github.com/netresearch/php-modernization-skill> | `378947f9ee0ccfcaa647158335887b25a85f1ae2` | 2026-08-24 |

## Re-importing

1. Re-clone each source repo at its current `HEAD`.
2. Diff the new `skills/` tree against what's here before overwriting —
   upstream skill folders can be renamed or removed between imports.
3. Copy the updated skill folders into `.agents/skills/` (same mapping
   as the original import: `superpowers-symfony`'s `skills/*`,
   `symfony-ux-skills`'s `skills/*`, `php-modernization-skill`'s
   `skills/php-modernization/`).
4. Run `python3 scripts/normalize-imported-skill-frontmatter.py` from
   the repo root to strip vendor frontmatter fields and fix any
   namespaced `name:` values again.
5. Update this file's commit hashes and date.
