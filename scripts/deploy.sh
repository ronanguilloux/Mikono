#!/usr/bin/env bash
#
# Roll the production deployment forward. Run from the deployment
# directory on the server (e.g. /opt/mikono), as the deploy user:
#
#   scripts/deploy.sh              # deploy whatever IMAGE_TAG points at
#   IMAGE_TAG=abc1234 scripts/deploy.sh
#
# What it buys over typing the three commands in
# docs/project/deployment-plan.md §6: it always passes both compose files
# and --env-file (§2's "one command that must never be wrong"), and it
# takes a backup BEFORE pulling. That ordering is the whole point — §6
# says to back up before any deploy carrying a migration, and a human
# reliably forgets which deploys those are.
#
# It deliberately does NOT roll back on failure. A migration is not
# reverted by rolling the image back (§6), so an automatic rollback would
# sometimes leave a new schema under old code. It prints what to do
# instead.
#
set -euo pipefail

cd "$(dirname "$0")/.."

# Deploy as the user that OWNS this checkout — `deploy`, per
# deployment-plan.md §3. As anyone else (`debian` is the tempting one: it
# is the Gandi image's login and has passwordless sudo) two things go
# wrong and neither says so clearly: git refuses the pull with "dubious
# ownership", and `docker compose` is unreachable without the docker
# group, which the PREVIOUS lookup below swallows into "nothing running
# yet" — i.e. a deploy that skips its own backup. Compare against the
# directory's owner rather than a hard-coded name, so a second server
# with a different deploy user needs no edit here.
OWNER="$(stat -c '%U' .)"
if [ "$(id -un)" != "${OWNER}" ]; then
    echo "Run this as ${OWNER}, not $(id -un):" >&2
    echo "  ssh ${OWNER}@<server> 'cd $(pwd) && ./scripts/deploy.sh'" >&2
    exit 1
fi

ENV_FILE="${ENV_FILE:-deploy.env}"
COMPOSE_FILES="--env-file ${ENV_FILE} -f compose.yaml -f compose.prod.yaml"
WAIT_TIMEOUT="${WAIT_TIMEOUT:-120}"

[ -f "${ENV_FILE}" ] || { echo "No ${ENV_FILE} here — see deployment-plan.md §4." >&2; exit 1; }

# shellcheck disable=SC2086
compose() { docker compose ${COMPOSE_FILES} "$@"; }

PREVIOUS="$(compose images -q php 2>/dev/null | head -1 || true)"

# 1. Back up first — unless there is nothing running yet (first deploy).
if [ -n "${PREVIOUS}" ] && [ "$(compose ps -q php | wc -l)" -gt 0 ]; then
    echo "==> Backing up before deploying"
    COMPOSE_FILES="${COMPOSE_FILES}" scripts/backup-db.sh
else
    echo "==> Nothing running yet; skipping the pre-deploy backup"
fi

# 2. Compose files only — the app itself ships as an image.
echo "==> Updating the checkout"
git pull --ff-only

echo "==> Pulling ${IMAGE_TAG:-the configured tag}"
compose pull

echo "==> Starting"
if ! compose up -d --wait --wait-timeout "${WAIT_TIMEOUT}"; then
    echo >&2
    echo "DEPLOY FAILED. The previous image was: ${PREVIOUS:-unknown}" >&2
    echo "Logs:      docker compose ${COMPOSE_FILES} logs --tail=50 php" >&2
    echo "Roll back: set IMAGE_TAG to the previous SHA in ${ENV_FILE}, re-run." >&2
    echo "If the failed deploy applied a migration, restore the backup" >&2
    echo "taken above FIRST — rolling the image back does not undo it." >&2
    exit 1
fi

# 4. Bootstrap the first admin — ONLY when the database holds no users
#    at all. That is the case worth protecting against: a new machine, a
#    wiped volume, a restore from a backup that predates the account. On
#    every other deploy this does nothing, which is precisely why it can
#    never reset a password changed later from inside the app.
#
#    Set ADMIN_EMAIL (and optionally ADMIN_FULL_NAME, ADMIN_PASSWORD) in
#    deploy.env. Without ADMIN_EMAIL the whole block is skipped.
envval() { sed -n "s/^$1=//p" "${ENV_FILE}" | tail -1; }
ADMIN_EMAIL="$(envval ADMIN_EMAIL)"

if [ -n "${ADMIN_EMAIL}" ]; then
    USERS="$(compose exec -T php php -r '
        $db = "/app/var/data/data_prod.db";
        if (!is_file($db)) { echo "0"; exit; }
        $pdo = new PDO("sqlite:" . $db);
        echo (int) $pdo->query("SELECT COUNT(*) FROM \"user\"")->fetchColumn();
    ' 2>/dev/null | tr -dc '0-9')"

    if [ "${USERS:-0}" = "0" ]; then
        ADMIN_PASSWORD="$(envval ADMIN_PASSWORD)"
        GENERATED=""
        if [ -z "${ADMIN_PASSWORD}" ]; then
            ADMIN_PASSWORD="$(openssl rand -base64 18)"
            GENERATED=yes
        fi
        echo "==> No accounts exist; creating the first admin"
        compose exec -T php bin/console app:user:create \
            --email="${ADMIN_EMAIL}" \
            --full-name="$(envval ADMIN_FULL_NAME)" \
            --password="${ADMIN_PASSWORD}" \
            --admin
        if [ -n "${GENERATED}" ]; then
            echo
            echo "    Password for ${ADMIN_EMAIL} (shown once, not stored): ${ADMIN_PASSWORD}"
            echo "    Put it in the password manager now."
            echo
        fi
    fi
fi

# Every deploy pulls a ~500MB image and nothing else removes the old
# ones: 44 of them filled the 25G disk on 2026-10-18 and the next
# pre-deploy backup died with "database or disk is full". Only after a
# healthy start, and safe for rollback, which re-pulls by tag.
echo "==> Pruning unused images"
docker image prune -af >/dev/null

echo "==> Deployed"
compose ps
compose images php

# After the prune, so this is what the next deploy starts from. Information
# only: the deploy has already succeeded, so nothing here may fail it. The
# verdict answers the 2026-10-18 failure: is there room for the next image
# (~500MB, pulled before the old one is pruned) and the next backup? The
# history lives outside the checkout, one line per deploy, for the trend.
DISK_HISTORY="${DISK_HISTORY:-${HOME}/mikono-disk-history.tsv}"
DISK_MARGIN_MB=200
IMAGE_MB=500

gigabytes() { awk -v mb="$1" 'BEGIN { printf "%.1fG", mb / 1024 }'; }

disk_report() {
    local used_mb free_mb used_pct latest backup_mb backups_mb need_mb verdict now db_size log_size
    used_mb="$(df -BM --output=used / | tail -1 | tr -dc '0-9')"
    free_mb="$(df -BM --output=avail / | tail -1 | tr -dc '0-9')"
    used_pct="$(df --output=pcent / | tail -1 | tr -dc '0-9')"

    # By name, as backup-db.sh does: the UTC stamp sorts lexically.
    latest="$(ls -1 backups/mikono-prod-*.db 2>/dev/null | tail -1)"
    backup_mb=0
    [ -n "${latest}" ] && backup_mb="$(du -m "${latest}" | cut -f1)"
    backups_mb="$(du -sm backups 2>/dev/null | cut -f1)"
    need_mb=$((IMAGE_MB + backup_mb + DISK_MARGIN_MB))

    if [ "${free_mb}" -lt "${need_mb}" ]; then
        verdict="ACT NOW"
    elif [ "${used_pct}" -ge 90 ]; then
        verdict="CRITICAL"
    elif [ "${used_pct}" -ge 80 ]; then
        verdict="WARNING"
    else
        verdict="OK"
    fi
    echo "==> Disk: ${verdict}, $(gigabytes "${free_mb}") free (${used_pct}% used), next deploy needs ~$(gigabytes "${need_mb}")"

    if [ -n "${latest}" ]; then
        echo "Latest backup: $(((($(date +%s) - $(stat -c '%Y' "${latest}")) / 60))) min ago, ${backup_mb}M, $(ls -1 backups/mikono-prod-*.db | wc -l) kept"
    else
        echo "Latest backup: NONE in backups/"
    fi

    now="$(date +%s)"
    db_size="$(docker system df -v 2>/dev/null | awk '$1 ~ /_db_data$/ { print $3 }')"
    log_size="$(docker system df -v 2>/dev/null | awk '$1 ~ /_log_data$/ { print $3 }')"
    printf '%s\t%s\t%s\t%s\t%s\t%s\n' "${now}" "$(date -u +%F)" "${used_mb}" "${db_size:--}" "${log_size:--}" "${backups_mb:-0}" >>"${DISK_HISTORY}"

    # Over the last five deploys, the same window every time.
    tail -n 5 "${DISK_HISTORY}" | awk -F'\t' -v free="${free_mb}" '
        NR == 1 { first_at = $1; first_mb = $3 }
        { last_at = $1; last_mb = $3 }
        END {
            seconds = last_at - first_at
            if (NR < 2 || seconds < 86400) { print "Trend: needs a day of history"; exit }
            days = seconds / 86400
            growth = last_mb - first_mb
            printf "Disk used: %.1fG -> %.1fG over the last %d deploys (%d days), ", first_mb / 1024, last_mb / 1024, NR, days
            if (growth <= 0) { print "not growing"; exit }
            left = free * days / growth
            if (left >= 60) { printf "full in ~%d months\n", left / 30 } else { printf "full in ~%d days\n", left }
        }'
}

disk_report || true
echo
df -h / || true
echo
docker system df || true
echo
docker system df -v 2>/dev/null | sed -n '/^VOLUME NAME/,/^$/p' || true
du -sh ./* 2>/dev/null | sort -h | tail -5 || true
