# Mikono

UCESCO Volunteer Manager (VM) — a Symfony 8.1 web app so UCESCO's
Volunteer Manager can track volunteers working at UCESCO's projects in
Kibera (Nairobi) and Mombasa. v0.1 scope: login-protected CRUD for
Volunteers, Projects, Activity Types, Users, and Activities (the log
entries), plus a basic per-volunteer/per-project report.

## Quick start

```bash
docker compose up -d --wait   # start (first run auto-bootstraps the app)
docker compose down           # stop — the SQLite data survives (named volume)
```

You can interact with Symfony CLI and the SQLite DB:

```bash
docker compose exec php bin/console
docker compose exec php bin/console dbal:run-sql "SELECT * FROM user"
```

App: `https://localhost` (self-signed cert — accept the browser
warning). No host PHP/Composer install is needed — everything runs
through Docker + FrankenPHP. A seeded login is created via
`app:user:create` — see [`AGENTS.md`](AGENTS.md) rather than looking
credentials up here; the command is idempotent, so re-run it to reset
the password.

## Contributing

### Running tests

```bash
docker compose exec php php bin/phpunit
```

### Quality checks

Before committing, run the full toolchain — static analysis, style,
and dependency audit:

```bash
docker compose exec php composer quality    # cs-check + phpstan + phpat + security-audit
docker compose exec php composer cs-fix     # auto-fix style issues
docker compose exec php composer rector     # preview refactors (dry-run only — review before applying)
```

- **PHPStan** runs at level `max` against `src/` and `tests/`.
- **PHP-CS-Fixer** enforces `@Symfony` + `@PER-CS2.0`.
- **PHPat** enforces the one architecture rule from ADR 0004
  (`Entity` must not depend on `Controller`/`Twig`).
- **Rector** is preview-only — never apply its output without
  reviewing the diff first; it can produce technically-valid but wrong
  refactors.
- **`composer audit`** checks the dependency tree for known
  vulnerabilities.

A local pre-commit hook enforces this automatically. Enable it once
per checkout:

```bash
git config core.hooksPath .githooks
```

It runs `composer quality` before every commit and blocks on failure.
Bypass deliberately, when you know what you're doing, with
`git commit --no-verify`.

See [ADR 0005](docs/adr/0005-adopt-phpstan-php-cs-fixer-rector-composer-audit.md)
for the full rationale behind this toolchain.

### Full reference

[`AGENTS.md`](AGENTS.md) is the complete day-to-day reference (used by
both human contributors and AI coding agents working in this repo):
directory map, migrations workflow, testing conventions, and known
gotchas. [`docs/project/next-steps.md`](docs/project/next-steps.md)
tracks what's next; [`docs/project/done.md`](docs/project/done.md)
logs completed work. [`docs/adr/`](docs/adr/) records every
architectural decision.
