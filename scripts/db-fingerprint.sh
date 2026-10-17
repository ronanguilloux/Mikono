#!/usr/bin/env bash
#
# Prints one line per table of a SQLite file: name, row count, sha256 of its
# rows in rowid order. Two files with identical output hold identical data.
# login_attempt and usage_event are left out: any sign-in writes them.
#
# Used to prove production didn't change between a sync and an upload
# (ADR 0044). Reads the file with the host's sqlite3; never the live app.
#
# Usage:
#   scripts/db-fingerprint.sh path/to/file.db > fingerprint.txt
#
set -euo pipefail

DB="${1:?usage: $0 file.db}"

sqlite3 -readonly "${DB}" \
    "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'
       AND name NOT IN ('login_attempt', 'usage_event') ORDER BY name" |
while read -r table; do
    count=$(sqlite3 -readonly "${DB}" "SELECT count(*) FROM \"${table}\"")
    hash=$(sqlite3 -readonly -csv "${DB}" "SELECT * FROM \"${table}\" ORDER BY rowid" | shasum -a 256 | cut -d' ' -f1)
    echo "${table} ${count} ${hash}"
done
