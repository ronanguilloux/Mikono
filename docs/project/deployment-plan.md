# Deployment plan

**Last updated:** 2026-09-01

The runbook for getting Mikono onto a server and keeping it there. A
living document, edited in place. What the server itself must provide —
sizing, ports, where in the world it should sit — is in
[`hosting-plan.md`](hosting-plan.md). The decision behind this shape is
[ADR 0010](../adr/0010-build-in-ci-and-deploy-by-image-pull.md).

## 1. The model

CI builds the production image and pushes it to GitHub Container
Registry. The server holds a checkout of this repository **only for the
compose files**, and runs `pull` + `up -d`. No build, no Composer, no
PHP, no Node on the host — just Docker.

```
git push → GitHub Actions → ghcr.io/ronanguilloux/app-php-prod:<sha>
                                        ↓
                          server: docker compose pull && up -d
                                        ↓
                    entrypoint runs migrations, FrankenPHP serves
```

## 2. The one command that must never be wrong

```bash
docker compose -f compose.yaml -f compose.prod.yaml <anything>
```

A bare `docker compose up -d` **silently loads
[`compose.override.yaml`](../../compose.override.yaml)** — Docker Compose
picks it up automatically — and would run the production server in
`APP_ENV=dev`, with Xdebug enabled, worker file-watching on, and a bind
mount over `/app`. It would appear to work.

Guard against typing it wrong by putting this in the deploy user's
`~/.bashrc` on the server:

```bash
alias mikono='docker compose --env-file /opt/mikono/deploy.env -f compose.yaml -f compose.prod.yaml'
```

Every command below is written out in full; `mikono …` is the short form.

## 3. Server bootstrap (once)

Run as root on a fresh VPS meeting the requirements in
[`hosting-plan.md`](hosting-plan.md):

```bash
# 1. A non-root user that owns the deployment
adduser --disabled-password --gecos '' deploy
usermod -aG docker deploy          # after Docker is installed, below
mkdir -p /home/deploy/.ssh && cp ~/.ssh/authorized_keys /home/deploy/.ssh/
chown -R deploy:deploy /home/deploy/.ssh && chmod 700 /home/deploy/.ssh

# 2. SSH: keys only
sed -i 's/^#\?PasswordAuthentication.*/PasswordAuthentication no/' /etc/ssh/sshd_config
sed -i 's/^#\?PermitRootLogin.*/PermitRootLogin prohibit-password/' /etc/ssh/sshd_config
systemctl reload ssh

# 3. Firewall — 443/udp is HTTP/3, 80/tcp is required for ACME
ufw allow 22/tcp && ufw allow 80/tcp && ufw allow 443/tcp && ufw allow 443/udp
ufw --force enable

# 4. Unattended security updates
apt-get install -y unattended-upgrades && dpkg-reconfigure -plow unattended-upgrades

# 5. Docker Engine + Compose plugin (see docs.docker.com for the current
#    apt repository steps for the distribution in use)

# 6. Docker log rotation — the default json-file driver grows without
#    bound, and this app logs to stderr in production
cat > /etc/docker/daemon.json <<'JSON'
{
  "log-driver": "json-file",
  "log-opts": { "max-size": "10m", "max-file": "5" }
}
JSON
systemctl restart docker

# 7. The deployment directory
mkdir -p /opt/mikono && chown deploy:deploy /opt/mikono
```

Then, as `deploy`:

```bash
git clone https://github.com/ronanguilloux/Mikono.git /opt/mikono
cd /opt/mikono
```

## 4. Configuration and secrets

Create `/opt/mikono/deploy.env`, readable only by the deploy user. This
is what `--env-file` feeds to Compose for variable substitution; it is
**not** the application's `.env`.

```bash
umask 077
cat > /opt/mikono/deploy.env <<ENV
SERVER_NAME=vm.example.org
APP_SECRET=$(openssl rand -hex 16)
DEFAULT_URI=https://vm.example.org
IMAGES_PREFIX=ghcr.io/ronanguilloux/
IMAGE_TAG=latest
ENV
chmod 600 /opt/mikono/deploy.env
```

Two rules that matter:

- **`APP_SECRET` is a runtime variable and must never become a build
  argument.** The image is published publicly on GHCR, and
  `composer dump-env prod` bakes build-time environment into
  `.env.local.php` inside the image. A build-time secret would be world
  readable. Passing it at runtime, as
  [`compose.prod.yaml`](../../compose.prod.yaml) does, keeps it on the
  server; real environment variables take precedence over the dumped
  values.
- **`SERVER_NAME` must be the real domain**, or Caddy never issues a
  certificate (see [`hosting-plan.md`](hosting-plan.md) §3).

Never commit `deploy.env`; it lives only on the server. Keep a copy of
`APP_SECRET` in whatever password manager the project uses — losing it
invalidates every existing session and any signed URL.

## 5. First deployment

```bash
cd /opt/mikono
export COMPOSE="docker compose --env-file deploy.env -f compose.yaml -f compose.prod.yaml"

$COMPOSE pull
$COMPOSE up -d --wait          # blocks until the healthcheck passes
$COMPOSE ps                    # STATUS must read "healthy"
$COMPOSE logs php | head -40   # confirm migrations ran, no stack traces
```

The entrypoint applies migrations `--all-or-nothing` on start, so the
database is created and up to date by the time the healthcheck passes.

Create the Volunteer Manager's account — there is no self-registration
and no password-reset email:

```bash
$COMPOSE exec php bin/console app:user:create \
    --email=vm@example.org --full-name="…" --admin
```

Omit `--password` so the command prompts, rather than leaving the
password in shell history.

Then verify by hand:

- `https://vm.example.org` serves a valid certificate (not self-signed).
- The login page is **styled** — unstyled CSS means the Tailwind build
  step did not run (see §8).
- Log in, create an activity, check it appears in the list and in
  `/reports`.
- `curl -sI --http3 https://vm.example.org` returns a response (HTTP/3
  reachable, i.e. 443/udp is genuinely open).

## 6. Routine deployment

CI publishes an image per commit on `main`. To roll forward:

```bash
cd /opt/mikono
git pull                       # compose files only
$COMPOSE pull
$COMPOSE up -d --wait
```

Pin a specific build by setting `IMAGE_TAG` to the commit's short SHA in
`deploy.env` instead of `latest` — worth doing in general, so a deploy is
an explicit act rather than whatever `latest` points at.

**Rollback:** set `IMAGE_TAG` to the previous SHA and repeat. This is
safe for application code, but **a migration is not reverted by rolling
the image back**. If the bad deploy changed the schema, restore the
database from the backup taken before it (§7) and then roll the image
back. Take a backup immediately before any deploy carrying a migration.

## 7. Backups

Run [`scripts/backup-db.sh`](../../scripts/backup-db.sh) from the
deployment directory. It snapshots the live database with `VACUUM INTO`
(no downtime), verifies it with `PRAGMA integrity_check`, copies it to
the host, and prunes old local copies.

```bash
crontab -e -u deploy
# Daily at 02:15 EAT, keeping 30 days locally
15 2 * * * cd /opt/mikono && COMPOSE_FILES="--env-file deploy.env -f compose.yaml -f compose.prod.yaml" KEEP_DAYS=30 scripts/backup-db.sh /opt/mikono/backups >> /opt/mikono/backups/backup.log 2>&1
```

**A local backup is not a backup.** Copy the directory off the machine —
`rclone`/`rsync` to object storage or another host, on the same schedule.
Choose the destination with the same data-residency reasoning as the
server itself ([`hosting-plan.md`](hosting-plan.md) §5).

### Restore drill

Run this once before the app carries real data, and again after any
change to the storage setup. It is the only way to know the backups work.

```bash
# 1. Take a backup and note the file
scripts/backup-db.sh ./backups

# 2. Stop the app so nothing writes during the swap
$COMPOSE down

# 3. Replace the live database with the backup, inside the volume
docker run --rm -v mikono_db_data:/data -v "$PWD/backups:/backups" \
    debian:13-slim \
    cp /backups/<chosen-backup>.db /data/data_prod.db

# 4. Restore ownership expected by the image, then start
docker run --rm -v mikono_db_data:/data debian:13-slim \
    chown 33:0 /data/data_prod.db     # 33 = www-data
$COMPOSE up -d --wait

# 5. Log in and confirm the data is there
```

Check the volume's real name first with `docker volume ls` — Compose
prefixes it with the project directory name.

## 8. Monitoring and logs

- **Health:** the image ships a `HEALTHCHECK` polling Caddy's metrics
  endpoint; `$COMPOSE ps` shows its state, and `restart: unless-stopped`
  brings the container back after a crash or reboot.
- **External uptime check** against `https://vm.example.org/login` from a
  third-party monitor. The healthcheck only knows the container is alive;
  it cannot tell you the certificate expired or DNS broke.
- **Logs:** the application logs to `stderr` in production
  (`config/packages/monolog.yaml`, JSON formatted), so
  `$COMPOSE logs -f php` carries both Caddy's access log and the app's
  errors. Rotation is the Docker daemon's job — configured in §3.

## 9. Pre-flight checklist

- [ ] Domain resolves to the server (A, and AAAA if IPv6).
- [ ] 80/tcp, 443/tcp, 443/udp open; nothing else bound to 80/443.
- [ ] Docker Engine and Compose v2 ≥ 2.30 installed; daemon log rotation set.
- [ ] SSH keys only, non-root deploy user, unattended-upgrades on.
- [ ] `deploy.env` present, `chmod 600`, with a real `SERVER_NAME` and a
      freshly generated `APP_SECRET`.
- [ ] Deploy commands always pass **both** compose files.
- [ ] `$COMPOSE ps` reports healthy; migrations applied.
- [ ] The VM's account exists and can log in.
- [ ] Login page renders **styled**.
- [ ] Backup cron installed, off-site copy configured, **restore drill
      performed at least once**.
- [ ] External uptime monitor configured.
