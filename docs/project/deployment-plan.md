# Deployment plan

**Last updated:** 2026-09-03

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

The `export COMPOSE="docker compose …"` / `$COMPOSE …` shorthand used
from §5 onward is a **bash** idiom — it depends on word splitting of an
unquoted expansion. The deploy user's shell on the server is bash, so it
is fine there, but it silently fails under zsh (`command not found:
docker compose -p …`), which is what §10's local dry run runs in on
macOS. Use a shell function if in doubt; it behaves the same in both.

## 3. Server bootstrap (once)

Run as root on a fresh VPS meeting the requirements in
[`hosting-plan.md`](hosting-plan.md).

**On a cloud image you do not log in as root.** Gandi's Debian 13 image
(verified on `srv-mikono`, 2026-09-04) admits you as **`debian`** with
passwordless sudo; Ubuntu images use `ubuntu`. Root's own
`authorized_keys` holds your key wrapped in a forced command that prints
*"Please login as the user debian"* and exits. So:

```bash
ssh debian@<server-ip>
sudo -i                            # now root, for everything below
```

**This matters beyond the first login, and it is a trap:** copying
*root's* `authorized_keys` to the `deploy` user — which is what an
earlier version of step 1 below did — copies that forced command with
it, and `deploy` is then locked out with the same message. Take the key
from the admin user's file, which is clean.

```bash
# 1. A non-root user that owns the deployment
adduser --disabled-password --gecos '' deploy
usermod -aG docker deploy          # after Docker is installed, below

# Copy the key from the cloud image's admin user (debian/ubuntu), NOT
# from /root/.ssh — see the trap above.
install -d -m 700 -o deploy -g deploy /home/deploy/.ssh
cp /home/"${SUDO_USER:-debian}"/.ssh/authorized_keys /home/deploy/.ssh/
chown deploy:deploy /home/deploy/.ssh/authorized_keys
chmod 600 /home/deploy/.ssh/authorized_keys

# Verify: this must print your key with NO command="..." prefix.
cat /home/deploy/.ssh/authorized_keys

# 2. SSH: keys only.
#    Do NOT sed /etc/ssh/sshd_config on a modern Debian/Ubuntu image: it
#    begins with `Include /etc/ssh/sshd_config.d/*.conf`, and OpenSSH
#    keeps the FIRST value it obtains for a keyword. A cloud image's
#    50-cloud-init.conf is therefore read before the main file and wins,
#    so the sed appears to work and changes nothing. Use a drop-in that
#    sorts ahead of it, then assert the effective config rather than
#    trusting the edit.
cat > /etc/ssh/sshd_config.d/01-mikono.conf <<'SSHD'
PasswordAuthentication no
PermitRootLogin prohibit-password
SSHD
sshd -t && systemctl reload ssh
sshd -T | grep -E '^(passwordauthentication|permitrootlogin) '   # must read: no / prohibit-password

# Keep this root session open until a NEW terminal can log in as deploy.
# Everything below assumes you have not locked yourself out.

# 3. Firewall — 443/udp is HTTP/3, 80/tcp is required for ACME.
#    Note what this does NOT do: Docker publishes a container port by
#    writing its own iptables chain, which is consulted before UFW's
#    INPUT rules, so a published port is reachable whether or not UFW
#    allows it. Harmless here — the only published ports are 80 and 443,
#    which are meant to be open — but do not read these rules as if they
#    were gating the container. Anything that must actually be blocked
#    belongs in the provider's own firewall, or must not be published in
#    compose.prod.yaml in the first place.
apt-get install -y ufw            # not present on a minimal Debian image
ufw allow 22/tcp && ufw allow 80/tcp && ufw allow 443/tcp && ufw allow 443/udp
ufw --force enable

# 4. Unattended security updates. dpkg-reconfigure asks a debconf
#    question, which is awkward in a pasted block; this is the same
#    thing written directly.
apt-get install -y unattended-upgrades
cat > /etc/apt/apt.conf.d/20auto-upgrades <<'APT'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
APT

# 5. Docker Engine + Compose plugin, from Docker's own apt repository
#    (the distribution's docker.io package is too old for the Compose v2
#    2.30+ that compose.yaml's long-form `ports:` syntax needs).
apt-get update && apt-get install -y ca-certificates curl
install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/debian/gpg -o /etc/apt/keyrings/docker.asc
chmod a+r /etc/apt/keyrings/docker.asc
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] \
https://download.docker.com/linux/debian $(. /etc/os-release && echo "$VERSION_CODENAME") stable" \
  > /etc/apt/sources.list.d/docker.list
apt-get update
apt-get install -y docker-ce docker-ce-cli containerd.io \
                   docker-buildx-plugin docker-compose-plugin
docker compose version             # must be 2.30 or newer

# NOTE ON ORDER: `usermod -aG docker deploy` in step 1 needs the docker
# group, which only exists once this step has run. On a fresh paste,
# run this step before step 1 — or simply re-run the usermod after.

# 6. Docker log rotation — the default json-file driver grows without
#    bound, and this app logs to stderr in production
cat > /etc/docker/daemon.json <<'JSON'
{
  "log-driver": "json-file",
  "log-opts": { "max-size": "10m", "max-file": "5" }
}
JSON
systemctl restart docker

# 7. Swap — required on a 1 GB plan, harmless on a larger one.
#    srv-mikono ships 951 MB usable and no swap at all. FrankenPHP in
#    worker mode with opcache.memory_consumption=256 sits at 300-600 MB
#    (hosting-plan.md §2), which leaves nothing for an image pull or a
#    console command. Without swap those do not run slowly, they get
#    OOM-killed.
fallocate -l 2G /swapfile && chmod 600 /swapfile
mkswap /swapfile && swapon /swapfile
echo '/swapfile none swap sw 0 0' >> /etc/fstab
free -m                            # confirm Swap is no longer 0

# 8. The deployment directory
mkdir -p /opt/mikono && chown deploy:deploy /opt/mikono
```

Before moving on, confirm the two things this bootstrap assumes and
[§9](#9-pre-flight-checklist) requires: `uname -m` reports `x86_64`, and
`ss -tulpn` shows nothing but sshd on port 22 — anything already bound to
80 or 443 fights Caddy for them ([`hosting-plan.md`](hosting-plan.md) §1).

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

Do §10 first. The dry run rehearses everything below that does not need a
real server, on the dev machine, for nothing — including the restore
drill in §7, whose volume-name and ownership steps have never been
observed to be correct.

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
scripts/deploy.sh
```

That is [`scripts/deploy.sh`](../../scripts/deploy.sh), and it is the
same three commands with two things a human gets wrong:

```bash
git pull                       # compose files only
$COMPOSE pull
$COMPOSE up -d --wait
```

It always passes both compose files and `--env-file` (§2), and it
**takes a backup before pulling** — this section says to back up before
any deploy carrying a migration, and nobody reliably remembers which
deploys those are. It skips the backup only when nothing is running yet.

It does **not** roll back on failure, deliberately: a migration is not
reverted by rolling the image back, so an automatic rollback would
sometimes leave a new schema under old code. On failure it prints the
previous image id, the log command, and the restore-first warning.

**Deliberately not automated further.** Deploying from CI over SSH would
need a GitHub secret that is root-equivalent on the server, and would
make any commit to `main` reach production unattended — on the machine
holding the only copy of the volunteer database. Watchtower-style
auto-pull is the same trade with less visibility. At one maintainer and
one user, a deploy is a decision, not a trigger.

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

# 4. Remove any stale SQLite sidecar files. A clean `down` leaves none,
#    but a crashed container can leave a journal (or -wal/-shm if WAL is
#    ever enabled), and SQLite would try to replay it onto the file you
#    just restored.
docker run --rm -v mikono_db_data:/data debian:13-slim \
    sh -c 'rm -f /data/data_prod.db-journal /data/data_prod.db-wal /data/data_prod.db-shm'

# 5. Restore ownership expected by the image, then start
docker run --rm -v mikono_db_data:/data debian:13-slim \
    chown 33:0 /data/data_prod.db     # 33 = www-data
$COMPOSE up -d --wait

# 6. Log in and confirm the data is there
```

Check the volume's real name first with `docker volume ls` — Compose
prefixes it with the project directory name.

**A drill only proves anything if something is lost.** Take the backup,
then make a change the backup cannot contain — a second
`app:user:create` is the cheapest — then restore and confirm that change
is *gone*. A restore that leaves the database looking identical has
demonstrated nothing. Confirm too that the entrypoint logs
*"Already at the latest version"* on the restart rather than trying to
replay migrations; the alternative is the `table "user" already exists`
failure recorded in [`done.md`](done.md) for 2026-09-01.

These steps were executed for the first time on 2026-09-03 against the
dry-run stack in §10 and worked as written, apart from the journal step
above, which was missing.

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

- [ ] **`uname -m` on the fresh server reports `x86_64`.** First command
      after the first SSH, before anything else is installed: the
      published image is amd64-only
      ([`hosting-plan.md`](hosting-plan.md) §2), so an arm64 box cannot
      start the container at all. Cheap to check, expensive to discover
      at `docker compose up`. Gandi in particular does not document its
      architecture anywhere (§5), so the flavour must be confirmed rather
      than assumed.
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
- [ ] The §10 local dry run was run first, and this file was corrected
      wherever it turned out to be wrong.

## 10. Rehearsal: a local dry run of the production stack

Everything above had never been executed anywhere when it was written.
This section is how most of it gets exercised before a server is
involved, at no cost — the substitution that replaced the disposable
test box originally planned in
[`next-steps.md`](next-steps.md) item 0.

**It exercises:** the GHCR pull, the entrypoint's migrations against an
empty `db_data` volume, whether a built Tailwind bundle is actually in
the published image, `app:user:create`, and the whole restore drill in
§7 including the two things nobody has verified — the Compose volume
name and the `chown 33:0` step.

**It cannot exercise:** ACME certificate issuance, port 80 reachability
from outside, HTTP/3 on 443/udp, DNS, or the Docker/UFW iptables
interaction. Those need a real server, which is why the first days on
the production box are still run on a throwaway hostname (again,
`next-steps.md` item 0).

**Use a separate Compose project name.** The volumes in
[`compose.yaml`](../../compose.yaml) are declared unqualified, so Compose
prefixes them with the project name — which defaults to the directory,
`mikono`. Run this without `-p` and the production container attaches to
the **dev** `mikono_db_data` volume, and the restore drill in §7 then
overwrites the development database. Stop the dev stack first, too, or
ports 80/443 collide.

**Use a function, not `export COMPOSE=`.** §2's and §5's
`export COMPOSE="docker compose …"` then `$COMPOSE pull` is a *bash*
idiom: it relies on word splitting of an unquoted expansion, which **zsh
does not do**, and zsh is the default shell on macOS where this dry run
is run. There it fails with `command not found: docker compose -p …`.
A function behaves the same in both shells:

```bash
docker compose down                     # free 80/443 and avoid confusion

umask 077
cat > deploy.env.dryrun <<ENV
SERVER_NAME=localhost
APP_SECRET=$(openssl rand -hex 16)
DEFAULT_URI=https://localhost
IMAGES_PREFIX=ghcr.io/ronanguilloux/
IMAGE_TAG=latest
ENV

dryrun() {
    docker compose -p mikono-dryrun --env-file deploy.env.dryrun \
        -f compose.yaml -f compose.prod.yaml "$@"
}

# On an arm64 host (any Apple-silicon Mac), the published image is
# amd64-only — hosting-plan.md §2 — so ask for it explicitly and let
# Docker Desktop emulate. It runs correctly; it is only slower, and
# nothing this dry run checks is timing-sensitive.
export DOCKER_DEFAULT_PLATFORM=linux/amd64

dryrun pull                    # proves the GHCR package is public
dryrun up -d --wait
dryrun ps                      # STATUS must read "healthy"
dryrun logs php | head -40     # migrations ran, no stack traces
dryrun exec php bin/console app:user:create \
    --email=dryrun@example.org --full-name="Dry Run" --admin
```

Then, at `https://localhost` (Caddy serves its own internal certificate
here — the browser warning is expected, and is *not* the §5 check for a
real one):

- The login page renders **styled**. Unstyled means the image was built
  without `tailwind:build`. Check the stylesheet actually *resolves*,
  not merely that the `<link>` is in the HTML — a missing bundle still
  emits the tag:
  `curl -sk https://localhost/login | grep -o '/assets/styles/[^"]*'`,
  then fetch that path and expect a couple of dozen KB, not a 404.
- Log in, create an activity, see it in the list and in `/reports`.

**Logging in cannot be scripted with `curl`.** This app uses stateless
CSRF ([`config/packages/csrf.yaml`](../../config/packages/csrf.yaml):
`stateless_token_ids` for `submit`, `authenticate`, `logout`), so the
login form ships the literal placeholder `value="csrf-token"` and a
Stimulus controller replaces it in the browser. Posting the form without
a browser gives 400, and each attempt spends one of the five that
`login_throttling` allows per 15 minutes. The production image has no
Panther or Chromium either — both are `require-dev`. What does work is
driving the dry-run stack from the *dev* image as a sibling container on
its network, reaching it by service name over plain HTTP (`compose.yaml`
gives Caddy a `php:80` site for exactly this):

```bash
docker run --rm --network mikono-dryrun_default \
    -v "$PWD":/app -w /app --entrypoint php app-php-dev \
    scripts/panther-screenshot.php --base-url=http://php \
    --login --email=dryrun@example.org --password=<the-one-you-set> \
    --path=/reports --wait-selector='header' --out=dryrun-reports.png
```

The bind mount puts the screenshot straight on the host under
`var/screenshots/`, no `docker compose cp` needed.

Now run the §7 restore drill against this stack, substituting
`mikono-dryrun_db_data` for the volume name. That is the point of the
exercise. A restore is only proved if something is *lost*: back up, then
make a change (a second `app:user:create` is enough), then restore and
confirm the change is gone. Then tear it down completely:

```bash
dryrun down -v                 # -v: drop the dry-run volumes
rm deploy.env.dryrun
```

**Correct this file wherever the drill proved it wrong.** That is the
only output of the exercise worth keeping.

### Result of the first run, 2026-09-03

Run on an arm64 macOS host against `ghcr.io/ronanguilloux/app-php-prod:latest`
(CI build of 2026-09-02). **The runbook was substantially correct.**
Confirmed working, none of it previously observed: the GHCR pull is
anonymous, so the package is genuinely public; seven migrations applied
to an empty `db_data` volume and the container reported healthy on first
boot; the image really does contain a built Tailwind bundle (~27 KB of
CSS, served 200); `app:user:create` works in the production image; the
`-p` guard kept the dev `mikono_db_data` volume untouched; and the whole
§7 restore drill — volume name, `chown 33:0`, restart — worked exactly as
written, with the post-backup user correctly absent afterwards and the
entrypoint reporting *"Already at the latest version"* rather than
replaying migration 1 onto live tables. Four things it corrected are
written into §7 and this section above: the zsh word-splitting failure,
the arm64 platform flag, the impossibility of a `curl` login and the
sibling-container workaround, and the stale-journal step in §7.
