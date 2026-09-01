#!/usr/bin/env bash
#
# Hot backup of the SQLite database, run from the host, without stopping the
# app and without a sqlite3 binary (the slim production image has none — see
# docs/project/hosting-plan.md).
#
# Uses SQLite's own `VACUUM INTO`, which takes a consistent snapshot of a
# live database through pdo_sqlite. It refuses to overwrite an existing
# file, hence the timestamped name.
#
# Usage (from the deployment directory, e.g. /opt/mikono):
#   scripts/backup-db.sh [destination-dir]
#
# Environment:
#   COMPOSE_FILES  compose files to use (default: the production pair)
#   KEEP_DAYS      prune local backups older than this (default: 30)
#   APP_ENV_NAME   which database file to back up (default: prod)
#
set -euo pipefail

DEST_DIR="${1:-./backups}"
KEEP_DAYS="${KEEP_DAYS:-30}"
APP_ENV_NAME="${APP_ENV_NAME:-prod}"
COMPOSE_FILES="${COMPOSE_FILES:--f compose.yaml -f compose.prod.yaml}"

STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
NAME="mikono-${APP_ENV_NAME}-${STAMP}.db"
SOURCE="/app/var/data/data_${APP_ENV_NAME}.db"
IN_CONTAINER="/app/var/data/${NAME}"

# shellcheck disable=SC2086
compose() { docker compose ${COMPOSE_FILES} "$@"; }

mkdir -p "${DEST_DIR}"

echo "Snapshotting ${SOURCE} ..."
compose exec -T php php -r '
    $source = $argv[1];
    $target = $argv[2];
    if (!is_file($source)) {
        fwrite(STDERR, "No database at {$source}\n");
        exit(1);
    }
    $pdo = new PDO("sqlite:" . $source, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec(sprintf("VACUUM INTO %s", $pdo->quote($target)));
' -- "${SOURCE}" "${IN_CONTAINER}"

echo "Verifying the snapshot ..."
compose exec -T php php -r '
    $pdo = new PDO("sqlite:" . $argv[1], null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $result = $pdo->query("PRAGMA integrity_check")->fetchColumn();
    if ("ok" !== $result) {
        fwrite(STDERR, "Integrity check failed: {$result}\n");
        exit(1);
    }
' -- "${IN_CONTAINER}"

echo "Copying to ${DEST_DIR}/${NAME} ..."
compose cp "php:${IN_CONTAINER}" "${DEST_DIR}/${NAME}"
compose exec -T php rm -f "${IN_CONTAINER}"

if [ "${KEEP_DAYS}" -gt 0 ]; then
    find "${DEST_DIR}" -name 'mikono-*.db' -type f -mtime "+${KEEP_DAYS}" -delete
fi

echo "OK: ${DEST_DIR}/${NAME}"
echo "Reminder: this is still on the same machine as the app. Copy it off-site."
