# Mikono

UCESCO Volunteer Manager (VM) — a Symfony 8.1 web app so UCESCO's
Volunteer Manager can track volunteers working at UCESCO's projects in
Kibera (Nairobi) and Mombasa. v0.1 scope: login-protected CRUD for
Volunteers, Projects, Activity Types, Users, and Activities (the log
entries), plus a basic per-volunteer/per-project report.

## Quick start

```bash
make up   # start (first run auto-bootstraps the app)
```

The first run takes a few minutes and needs network access: the entrypoint
installs Composer dependencies, replays the migrations, and downloads the
standalone Tailwind binary to build the CSS. None of that output is
committed — `var/` and `vendor/` are gitignored — so it happens once per
fresh checkout, and the container only reports healthy once it is done.

## Quick stop

```bash
make down           # stop — the SQLite data survives (named volume)
```

You can interact with Symfony CLI and the SQLite DB:

```bash
docker compose exec php bin/console dbal:run-sql "SELECT * FROM user"
docker compose exec php bin/console app:user:create \
  --email=SOMEONE@gmail.com --full-name="Some One" --password=SOMEONE --admin
```

App: `https://localhost` (self-signed cert — accept the browser
warning). No host PHP/Composer install is needed — everything runs
through Docker + FrankenPHP. A seeded login is created via
`app:user:create` — see [`AGENTS.md`](AGENTS.md) rather than looking
credentials up here; the command is idempotent, so re-run it to reset
the password.

### Optional: a trusted local certificate (mkcert)

To get rid of the browser warning in local dev only, serve a certificate
signed by a local CA your OS trusts. From the repo root:

```bash
brew install mkcert            # macOS; see mkcert's README for other OSes
mkcert -install                # create the local CA and trust it (asks for sudo)
mkdir -p frankenphp/certs
mkcert -cert-file frankenphp/certs/tls.pem -key-file frankenphp/certs/tls.key localhost
```

Then create `compose.local.yaml` (the `certs` folder is already inside the
dev bind mount, at `/app/frankenphp/certs`):

```yaml
services:
  php:
    environment:
      # The default also lists the plain-HTTP `php:80` site, where Caddy
      # refuses a `tls` directive.
      SERVER_NAME: localhost
      CADDY_SERVER_EXTRA_DIRECTIVES: tls /app/frankenphp/certs/tls.pem /app/frankenphp/certs/tls.key
```

and include it in every local compose command (or use `make up` after setting this environment variable):

```bash
export COMPOSE_FILE=compose.yaml:compose.override.yaml:compose.local.yaml
make up
```

That `export` is per-shell, and forgetting it fails quietly: `docker compose`
then loads only `compose.yaml` + `compose.override.yaml` and recreates the
container **without** the `tls` directive, so Caddy falls back to its own
untrusted certificate and the browser blocks the page again. To check which
one is being served:

```bash
echo | openssl s_client -connect localhost:443 -servername localhost 2>/dev/null \
  | openssl x509 -noout -issuer
```

`issuer=...mkcert development CA...` is right; `issuer=CN=Caddy Local
Authority` means the override was not loaded.

Restart the browser once. Both `frankenphp/certs/` and
`compose.local.yaml` are gitignored — never commit the key. The certificate
lasts about two years; re-run the last `mkcert` line to renew it. Never use
this in production, which gets a real certificate from Caddy.

## Connecting Claude Code locally

Claude Code can read the local app through its MCP server at `/mcp`. Access
is read-only and needs an admin account
([ADR 0050](docs/adr/0050-expose-a-read-only-api-and-mcp-server-to-claude-behind-oauth.md)).

**Check which data you are exposing first.** After `make db_update` the local
database is a production copy, and whatever Claude reads goes to Anthropic. Use
fixture data unless that data is meant to be read this way.

1. Generate the local OAuth keys once per checkout, and restart so the app
   loads them. They go in the gitignored `.env.dev.local`.

   ```bash
   export COMPOSE_FILE=compose.yaml:compose.override.yaml:compose.local.yaml
   make oauth_keys
   docker compose restart php
   ```

2. Add the server. There is no secret to create: a migration seeds the OAuth
   client.

   ```bash
   claude mcp add --transport http --client-id claude --callback-port 8765 mikono https://localhost/mcp
   ```

3. Start a new Claude Code session and run `/mcp`, then authenticate `mikono`.
   Sign in with an admin account in the browser and click **Allow**.

4. Try a few questions:

   - "How many volunteers are present right now, and at which branches?"
   - "Show activity totals by branch, with links."
   - "What's the passport number for [a volunteer]?" Claude should say it isn't
     available.

5. Open `https://localhost/usage`: the **API access** table should list those
   reads as "AI assistant (MCP)".

When you're done, run `claude mcp remove mikono`.

If Claude Code rejects the local certificate, start it with:

```bash
NODE_EXTRA_CA_CERTS="$(mkcert -CAROOT)/rootCA.pem" claude
```

claude.ai, Desktop and mobile need a server on public HTTPS. Any admin connects
them from **Admin → Connect an AI** in the app.

## Contributing

### Running tests

```bash
make test
make e2e-test # End-to-End browser tests with Panther
```

### Quality checks

Before committing, run the full toolchain — static analysis, style,
and dependency audit:

```bash
make quality    # cs-check + phpstan + phpat + security-audit
make cs-fix     # auto-fix style issues
make rector     # preview refactors (dry-run only — review before applying)
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
gotchas. [`docs/project/backlog/`](docs/project/backlog/) holds one card
per open item, indexed in order by
[`docs/project/next-steps.md`](docs/project/next-steps.md);
[`docs/project/done.md`](docs/project/done.md)
logs completed work. [`docs/adr/`](docs/adr/) records every
architectural decision.
