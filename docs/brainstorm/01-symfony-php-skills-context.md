# Brainstorm — Staging Symfony/PHP Agent Skills

**Date:** 2026-08-24
**Author:** ronan.guilloux@gmail.com
**Related:** [`AGENTS.md`](../../AGENTS.md),
[`docs/adr/0002-stage-symfony-php-skills-in-agents-skills.md`](../adr/0002-stage-symfony-php-skills-in-agents-skills.md),
[`.agents/skills/`](../../.agents/skills/README.md)

---

## Primary audience

**Future-self**, working solo on Mikono, plus any future agent session
picking up this repo cold — the same pairing `docs/brainstorm/README.md`
already assumes. Whoever scaffolds the eventual Symfony application here
needs to know these skills exist, where they came from, and why their
frontmatter looks different from upstream.

## Desired impact

When Mikono's actual Symfony/PHP application gets scaffolded — no
`composer.json`, no `src/` exist yet, so that hasn't happened — an AI
agent working in this repo should immediately have accurate,
vocabulary-triggered skill guidance for Doctrine, API Platform,
Messenger, Symfony UX, and PHP modernization, instead of generic or
invented advice.

Success looks like: on that day, `/skills` in Claude Code (or a fresh
Gemini CLI session) lists all 52 imported skills correctly, each with
working frontmatter — `name` matching its folder, exactly two
frontmatter keys — and none of them contradicting this repo's own
conventions.

## The "Options Not Taken"

### 1. Install the three packages as personal Claude Code plugins

**Rejected.** A native `.claude-plugin/` install is per-machine and
per-tool — it isn't shared with collaborators, doesn't reach Gemini CLI
or Codex, and isn't reproducible from a fresh clone. The entire point of
this repo's `.agents/skills/` architecture, built in the prior session,
is one committed, cross-agent source of truth. A personal plugin install
would have quietly defeated that.

### 2. Run the brief's clone/copy script exactly as written

**Rejected.** Verifying the brief against the live repos (via the GitHub
API, read-only, before touching anything) found it was stale in two
ways: `MakFly/superpowers-symfony` had moved to
`dev-toolings/superpowers-symfony`, and `php-modernization-skill`'s real
skill now lives at `skills/php-modernization/SKILL.md` rather than a
root-level `SKILL.md` as the brief assumed. Blind execution of the
brief's step 3 would have cloned the *entire* `php-modernization-skill`
repo — its CI config, `composer.json`, `evals/`, `fixtures/`, `Build/` —
into the skill folder instead of a clean skill. Checking first caught
this before it happened.

### 3. Trust the imported `SKILL.md` frontmatter as-is

**Rejected.** All 52 imported files carried vendor-specific frontmatter
fields (`allowed-tools`, `license`, `metadata`, `compatibility`) that
this repo's own `.agents/skills/README.md` explicitly says to strip, and
`superpowers-symfony`'s skill names were colon-namespaced
(`symfony:skill-name`) rather than matching their folder. Importing
either as-is would have broken skill discovery in Gemini CLI/Codex, or
at minimum defeated the portability this whole architecture exists to
guarantee. `scripts/normalize-imported-skill-frontmatter.py` was written
and run instead, and is meant to be re-run on every future re-import.

## Constraints

- No git repository exists yet in Mikono — nothing described here is
  committed; this is all working-tree state.
- No Symfony/PHP application exists yet — this narrative documents
  staging AI tooling *ahead of* the actual stack decision, not the stack
  decision itself. `AGENTS.md`'s "Stack" section still says "not yet
  chosen," deliberately.
- The actual `git clone` of the three source repos ran in the user's own
  terminal, not the agent's. Cloning unaudited community repos and
  copying their content into a location every future agent session
  loads as trusted instructions was treated as a download-from-an-
  unverified-source action reserved for the user to run themselves —
  everything downstream of the clone (copying the right subfolders,
  normalizing frontmatter, wiring the bridge, pinning versions) was the
  agent's job.
