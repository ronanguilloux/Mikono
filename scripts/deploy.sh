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

echo "==> Deployed"
compose ps
compose images php
