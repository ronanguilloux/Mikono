# Agent Skills

Source of truth for every Agent Skill used in this repo, following the
open Agent Skills standard. The same skills work across Claude Code,
Gemini CLI, and Codex from this one location.

## Why the symlinks

Gemini CLI and Codex discover skills natively from `.agents/skills/`.
Claude Code only discovers `.claude/skills/`. Rather than duplicate
content, `.claude/skills` and `.gemini/skills` are symlinks back to this
directory, so there is exactly one copy of every skill on disk.

## Adding a new skill

1. Create `.agents/skills/<skill-name>/SKILL.md`. `<skill-name>` must be
   kebab-case and match the `name:` frontmatter field exactly.
2. Frontmatter must contain **only** these two fields:

   ```yaml
   ---
   name: <skill-name>
   description: <what it does AND when to trigger it, in the vocabulary a
     developer would actually use>
   ---
   ```

   The `description` is the trigger, not documentation — write it the
   way a developer would actually phrase the request. No vendor
   extensions (`allowed-tools`, `disable-model-invocation`,
   `argument-hint`, etc.) — they break portability across agents.
3. Write the body in imperative voice. State constraints explicitly
   ("never delete a failing test", "ask before running e2e", "never
   commit").
4. Keep `SKILL.md` under about two pages. Push detail into
   `references/*.md` and link to it by relative path — the agent loads
   it only if needed (progressive disclosure).
5. Anything that must run deterministically, rather than be re-derived by
   the model each time, goes in `scripts/` as an executable; `SKILL.md`
   just says to run it.
6. Optional per-skill subdirectories: `scripts/`, `references/`,
   `assets/`.

## Skills in this repo

| Skill | Purpose |
| --- | --- |
| [`code-review`](code-review/SKILL.md) | Review a diff or PR before merging |

### Symfony/PHP, staged ahead of the stack

Imported from three external packages in anticipation of a Symfony/PHP
stack — see `VERSIONS.md` for exact source commits and
[`AGENTS.md`](../../AGENTS.md)'s "Stack" section for what's still not
actually chosen. Frontmatter was normalized to this repo's two-field
convention with `scripts/normalize-imported-skill-frontmatter.py`
(repo root) — re-run it after any future re-import.

**From `superpowers-symfony`** (Doctrine, API Platform, Messenger,
security/voters, TDD, DDD):

| Skill | Purpose |
| --- | --- |
| `api-platform-dto-resources` | Map entities to API DTOs with the Object Mapper |
| `api-platform-filters` | API Platform v4 Parameters API filters |
| `api-platform-resources` | Configure API Platform v4 resources, operations, pagination |
| `api-platform-security` | Secure resources with security expressions and voters |
| `api-platform-serialization` | Control serialization groups |
| `api-platform-state-providers` | State Providers/Processors, decoupled from entities |
| `api-platform-tests` | Test API Platform resources with ApiTestCase |
| `api-platform-versioning` | Evolve APIs via deprecation instead of versioning |
| `bootstrap-check` | Verify Symfony project configuration |
| `brainstorming` | Structured brainstorming for Symfony architecture |
| `config-env-parameters` | `.env`, parameters, secrets vault configuration |
| `controller-cleanup` | Refactor fat controllers into services/handlers |
| `cqrs-and-handlers` | CQRS with Messenger command/query buses |
| `daily-workflow` | Common day-to-day Symfony development tasks |
| `doctrine-batch-processing` | Large datasets with `toIterable`, flush+clear |
| `doctrine-events` | Entity lifecycle via attribute listeners |
| `doctrine-fetch-modes` | DTO hydration, lazy loading, query hints |
| `doctrine-fixtures-foundry` | Test data with Zenstruck Foundry v2 factories |
| `doctrine-migrations` | Schema versioning, rollbacks, deploy handling |
| `doctrine-relations` | Entity relationships, cascade, N+1 prevention |
| `doctrine-transactions` | Transaction boundaries, locking, flush strategies |
| `e2e-panther-playwright` | End-to-end browser tests |
| `effective-context` | Feed Claude the right Symfony-specific context |
| `executing-plans` | Execute implementation plans with TDD, incremental commits |
| `form-types-validation` | Custom Form Types, validation, HTTP 422 |
| `functional-tests` | Controller/HTTP tests with `WebTestCase` |
| `interfaces-and-autowiring` | Dependency injection, autowiring |
| `messenger-retry-failures` | Retry strategies, failure transport |
| `ports-and-adapters` | Hexagonal architecture in Symfony |
| `quality-checks` | PHP-CS-Fixer, PHPStan, type safety |
| `rate-limiting` | Symfony RateLimiter strategies |
| `runner-selection` | Detect Docker/DDEV/host before running commands |
| `strategy-pattern` | Strategy pattern via tagged services |
| `symfony-cache` | Cache pools, tag-based invalidation |
| `symfony-messenger` | Async transports, handlers, middleware |
| `symfony-scheduler` | Recurring tasks via the Scheduler component |
| `symfony-voters` | Granular authorization decoupled from controllers |
| `tdd-with-pest` | RED-GREEN-REFACTOR with Pest v4 |
| `tdd-with-phpunit` | RED-GREEN-REFACTOR with PHPUnit 10/11 |
| `test-doubles-mocking` | PHPUnit mocks for unit test isolation |
| `twig-components` | Reusable UI components (props, slots, CVA) |
| `using-symfony-superpowers` | Entry point / command map for this package |
| `value-objects-and-dtos` | Immutable Value Objects and DTOs |
| `writing-plans` | Structured implementation plans for Symfony features |

**From `symfony-ux-skills`** (Stimulus, Turbo, TwigComponent,
LiveComponent, UX Icons, UX Map):

| Skill | Purpose |
| --- | --- |
| `symfony-ux` | Decision tree across the Symfony UX toolset |
| `live-component` | Reactive server-rendered UI, zero JS |
| `stimulus` | Client-side behavior via HTML data attributes |
| `turbo` | Drive/Frames/Streams — SPA-like navigation, no JS |
| `twig-component` | Reusable server-rendered UI building blocks |
| `ux-icons` | SVG icons from 200k+ Iconify sets in Twig |
| `ux-map` | Interactive maps (Leaflet/Google Maps) |

**From `php-modernization-skill`**:

| Skill | Purpose |
| --- | --- |
| `php-modernization` | PHP 8.4/8.5 typing, PSR/PER-CS, PHPStan/Rector/PHP-CS-Fixer |

Skills tied to a specific package manager or test runner invocation
(`run-tests`) still wait on an actual chosen stack — see
[`AGENTS.md`](../../AGENTS.md)'s "Stack" section.
