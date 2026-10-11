#!/usr/bin/env bash
#
# Fetches the latest production database from the remote server
# and replaces the local development database with it.
#
# It uses the remote `scripts/backup-db.sh` to safely snapshot the live
# production database without downtime, downloads the snapshot, and replaces
# the local `data_dev.db` inside the Docker volume.
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

echo "6. Restarting the local PHP container to clear file handles..."
# As noted in AGENTS.md, FrankenPHP workers might hold connections to the replaced file
# causing "database disk image is malformed" errors until restarted.
docker compose restart php

echo ""
echo "✅ Done! The local development environment is now using the latest production database."
echo ""
echo "⚠️  NOTE: Your local dev accounts were overwritten by production data."
echo "If you need a local admin account to log in, recreate/reset your test user:"
echo "  docker compose exec php bin/console app:user:create --email=TEST_ACCOUNT@gmail.com --password=<new-password> --admin"
echo ""
