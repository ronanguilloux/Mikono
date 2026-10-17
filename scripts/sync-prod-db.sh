#!/usr/bin/env bash
#
# Fetches the latest production database from the remote server
# and replaces the local development database with it.
#
# It uses the remote `scripts/backup-db.sh` to safely snapshot the live
# production database without downtime, downloads the snapshot, and replaces
# the local `data_dev.db` inside the Docker volume. It also copies the
# production PASSPORT_ENCRYPTION_KEY into the gitignored .env.dev.local, or
# every volunteer with a passport number 500s on decrypt (ADR 0033, 0044).
# The copy is the standing local database; re-run this when production is
# fresher.
#
# Usage:
#   scripts/sync-prod-db.sh
#
set -euo pipefail

REMOTE_HOST="deploy@deploy.mikono.guilloux.org"
REMOTE_DIR="/opt/mikono"
LOCAL_TMP_DIR="./var/tmp"
DB_ENV="dev"

echo "1. Triggering remote backup on ${REMOTE_HOST}..."
ssh "${REMOTE_HOST}" "cd ${REMOTE_DIR} && ./scripts/backup-db.sh"

echo "2. Finding the latest backup file..."
LATEST_BACKUP=$(ssh "${REMOTE_HOST}" "ls -t ${REMOTE_DIR}/backups/mikono-prod-*.db | head -n 1")

if [ -z "${LATEST_BACKUP}" ]; then
    echo "Error: Could not find a backup file on the remote server."
    exit 1
fi

LOCAL_TMP_DB="${LOCAL_TMP_DIR}/$(basename "${LATEST_BACKUP}")"
mkdir -p "${LOCAL_TMP_DIR}"

echo "3. Downloading snapshot to local temporary directory..."
scp "${REMOTE_HOST}:${LATEST_BACKUP}" "${LOCAL_TMP_DB}"

echo "4. Copying backup into the local container as data_${DB_ENV}.db..."
docker compose cp "${LOCAL_TMP_DB}" "php:/app/var/data/data_${DB_ENV}.db"

echo "5. Cleaning up local temporary file..."
rm -f "${LOCAL_TMP_DB}"

echo "6. Fetching the production passport key into .env.dev.local..."
KEY_LINE=$(ssh "${REMOTE_HOST}" "grep '^PASSPORT_ENCRYPTION_KEY=.' ${REMOTE_DIR}/deploy.env")
(
    umask 077
    { grep -v '^PASSPORT_ENCRYPTION_KEY=' .env.dev.local 2>/dev/null || true; echo "${KEY_LINE}"; } > .env.tmp.local
    mv .env.tmp.local .env.dev.local
)

echo "7. Restarting the local PHP container to clear file handles..."
# As noted in AGENTS.md, FrankenPHP workers might hold connections to the replaced file
# causing "database disk image is malformed" errors until restarted.
docker compose restart php

echo ""
echo "✅ Done! The local development environment is now using the latest production database."
echo ""
echo "⚠️  Log in with your own production account. Never create or reset a login"
echo "   in this copy: it could travel back on an upload, and resetting an existing"
echo "   email changes that person's production password (ADR 0044)."
echo ""
echo "⚠️  This machine now holds production personal data and readable passport"
echo "   numbers (prod key in .env.dev.local). Keep the disk encrypted."
echo ""
