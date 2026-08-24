# 2. Stage Symfony/PHP Agent Skills in `.agents/skills/`

Date: 2026-08-24

## Status

Accepted

## Context

A brief handed to the agent asked for three community Agent Skill
packages — `superpowers-symfony` (Doctrine, API Platform, Messenger,
security/voters, TDD, DDD), `symfony-ux-skills` (Stimulus, Turbo,
TwigComponent, LiveComponent, UX Icons, UX Map), and
`php-modernization-skill` (PHP 8.4/8.5 typing, PHPStan/Rector/PHP-CS-
Fixer) — to be installed under `.agents/skills/`, the source-of-truth
directory built in the prior session and bridged to Claude Code, Gemini
CLI, and Codex via symlinks.

Mikono has no Symfony/PHP application yet (no `composer.json`, no
`src/`), so this is staging AI tooling ahead of that stack being chosen,
not the stack decision itself. See
[`docs/brainstorm/01-symfony-php-skills-context.md`](../brainstorm/01-symfony-php-skills-context.md)
for the full narrative, including the options considered and rejected.

Read-only verification against the live repos before importing anything
found two of the brief's assumptions were stale: `MakFly/superpowers-
symfony` had moved to `dev-toolings/superpowers-symfony`, and
`php-modernization-skill`'s real skill lives at
`skills/php-modernization/SKILL.md`, not a root-level `SKILL.md` as
assumed. It also found all 52 skill files carried vendor frontmatter
fields this repo's own convention forbids, and `superpowers-symfony`'s
names were colon-namespaced rather than matching their folders.

## Decision

Import the three packages' `skills/` subfolders into `.agents/skills/`
(52 skill folders total: 44 + 7 + 1), sourced from corrected upstream
locations, with every `SKILL.md`'s frontmatter normalized down to
exactly `name` + `description` and every namespaced `name:` value
rewritten to match its folder — via a reusable script
(`scripts/normalize-imported-skill-frontmatter.py`), not by hand. Add a
`.codex/skills -> ../.agents/skills` symlink alongside the existing
`.claude/skills` and `.gemini/skills` bridges. Pin the import to exact
commit SHAs in `.agents/skills/VERSIONS.md`.

The actual `git clone` of the three source repos was run by the user in
their own terminal; the agent only copied, normalized, and wired the
result after the clone existed on disk.

## Consequences

- **Positive:** an AI agent working in this repo already has accurate,
  vocabulary-triggered Symfony/PHP guidance the moment an actual
  Symfony application gets scaffolded here, instead of generic or
  invented advice. All 52 skills are portable — same frontmatter shape
  as `code-review`, discoverable identically from Claude Code, Gemini
  CLI, and Codex.
- **Negative / trade-offs:** 52 more files to keep in sync with
  upstream; `scripts/normalize-imported-skill-frontmatter.py` needs
  re-running on every future re-import, and upstream skill folders can
  be renamed or removed between imports, which the re-import checklist
  in `VERSIONS.md` has to catch by hand.
- **Reversibility:** cheap. Removing `.agents/skills/<package skills>`
  and their `VERSIONS.md`/README rows fully reverts this; nothing else
  in the repo depends on them existing yet, since no Symfony code exists
  to reference them.

## Alternatives considered

### 1. Install as personal Claude Code plugins instead of project skills

**Rejected.** Not shared with collaborators, doesn't reach Gemini CLI or
Codex, not reproducible from a fresh clone — defeats the point of the
`.agents/skills/` architecture this repo already committed to.

### 2. Run the brief's clone/copy script exactly as written

**Rejected.** It would have cloned `php-modernization-skill`'s entire
repository (CI config, `composer.json`, `evals/`, `fixtures/`, `Build/`)
into the skill folder instead of the clean `skills/php-modernization/`
subfolder, because the brief's assumption about that repo's layout was
stale. Verifying first caught this before it happened.

### 3. Trust the imported frontmatter and namespaced names as-is

**Rejected.** `allowed-tools`, `license`, `metadata`, and `compatibility`
fields, plus `symfony:`-prefixed names not matching their folders, would
have broken discovery in Gemini CLI/Codex or at minimum contradicted
this repo's own `.agents/skills/README.md` rule that exists specifically
to guarantee cross-agent portability.

### 4. Gitignore the `.claude/skills` / `.codex/skills` symlinks, per the brief

**Rejected**, by explicit choice: this repo already committed
`.claude/skills` and `.gemini/skills` deliberately in the prior session
so a fresh clone needs no setup step. `.codex/skills` follows the same
convention rather than diverging from it.
