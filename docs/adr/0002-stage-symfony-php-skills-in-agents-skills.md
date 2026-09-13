# 2. Keep portable Symfony/PHP Agent Skills in `.agents/skills/`

Date: 2026-08-24

## Status

Accepted

## Context

AI agents work in this repo from Claude Code, Gemini CLI and Codex. Without
stack-specific guidance they give generic or invented Symfony/PHP advice.
Three community packages cover the stack: `superpowers-symfony` (Doctrine,
API Platform, Messenger, security, TDD), `symfony-ux-skills` (Stimulus,
Turbo, TwigComponent, LiveComponent, UX Icons, UX Map) and
`php-modernization-skill` (PHP 8.4/8.5, PHPStan, Rector, PHP-CS-Fixer).

Imported as-is they are not portable: they carry vendor frontmatter fields
(`allowed-tools`, `license`, `metadata`, `compatibility`) and colon-namespaced
`name:` values that don't match their folders, which breaks discovery outside
Claude Code. The narrative is in
[`docs/brainstorm/01-symfony-php-skills-context.md`](../brainstorm/01-symfony-php-skills-context.md).

## Decision

**`.agents/skills/` is the single source of truth for Agent Skills, shared by
all three agents through committed symlinks (`.claude/skills`,
`.gemini/skills`, `.codex/skills`).**

- Only each package's `skills/` subfolder is imported, from the verified
  upstream location.
- Every `SKILL.md` frontmatter is normalized to exactly `name` +
  `description`, with `name` matching its folder, by
  `scripts/normalize-imported-skill-frontmatter.py` — never by hand.
- The import is pinned to exact commit SHAs in `.agents/skills/VERSIONS.md`,
  which also holds the re-import checklist.

## Consequences

- **Positive:** accurate Symfony/PHP guidance, discoverable identically from
  every agent, reproducible from a fresh clone with no setup step.
- **Negative / trade-offs:** dozens of third-party files to keep in sync;
  the normalization script must be re-run on every re-import, and upstream
  renames or removals have to be caught by hand via the checklist.
- **Reversibility:** cheap — delete the skill folders and their
  `VERSIONS.md`/README rows.

## Alternatives considered

### 1. Personal Claude Code plugins instead of project skills

**Rejected.** Not shared, doesn't reach Gemini CLI or Codex, not
reproducible from a clone.

### 2. Keep the imported frontmatter and names as-is

**Rejected.** Breaks discovery outside Claude Code and contradicts the
portability rule in `.agents/skills/README.md`.

### 3. Gitignore the agent symlinks

**Rejected.** Committing them means a fresh clone needs no setup step.
