#!/usr/bin/env bash
# =============================================================================
# DCI-SIS Database Backup Script
# =============================================================================
# Creates a timestamped, gzip-compressed mysqldump of the dci_sis database.
#
# Required environment variables:
#   DB_NAME      — database name (e.g. dci_sis)
#   DB_USER      — database user
#   DB_PASS      — database password (NEVER hardcode here)
#
# Optional environment variables (with defaults):
#   DB_HOST      — default: 127.0.0.1
#   DB_PORT      — default: 3306
#   BACKUP_DIR   — default: ./backups
#   MYSQLDUMP_BIN — full path to mysqldump binary (default: mysqldump from PATH)
#                  MAMP example: /Applications/MAMP/Library/bin/mysql80/bin/mysqldump
#
# Usage:
#   DB_NAME=dci_sis DB_USER=dci_backup DB_PASS=secret bash scripts/backup_database.sh
#
# See docs/backup-restore-plan.md for full backup strategy and cron examples.
# =============================================================================

set -euo pipefail

# --- Resolve mysqldump binary ---
MYSQLDUMP_BIN="${MYSQLDUMP_BIN:-mysqldump}"
if ! command -v "${MYSQLDUMP_BIN}" &>/dev/null; then
    echo "[ERROR] mysqldump not found in PATH. Set MYSQLDUMP_BIN to the full path." >&2
    echo "  MAMP: export MYSQLDUMP_BIN=/Applications/MAMP/Library/bin/mysql80/bin/mysqldump" >&2
    exit 1
fi

# --- Required variables ---
: "${DB_NAME:?DB_NAME is required}"
: "${DB_USER:?DB_USER is required}"
: "${DB_PASS:?DB_PASS is required}"

# --- Optional variables with defaults ---
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
BACKUP_DIR="${BACKUP_DIR:-./backups}"

# --- Prepare output path ---
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="${BACKUP_DIR}/dci_sis_${TIMESTAMP}.sql.gz"

# Create backup directory with restricted permissions
mkdir -p "${BACKUP_DIR}"
chmod 700 "${BACKUP_DIR}"

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Starting backup"
echo "  Database : ${DB_NAME}"
echo "  Host     : ${DB_HOST}:${DB_PORT}"
echo "  Output   : ${BACKUP_FILE}"

# --- Write temporary credentials file (avoids password in process list) ---
MYSQL_CONF=$(mktemp)
chmod 600 "${MYSQL_CONF}"
# Ensure temp file is removed on exit (success or failure)
trap 'rm -f "${MYSQL_CONF}"' EXIT

printf '[mysqldump]\npassword=%s\n' "${DB_PASS}" > "${MYSQL_CONF}"

# --- Run mysqldump ---
# --single-transaction : consistent snapshot for InnoDB without table locks
# --routines           : include stored procedures and functions
# --triggers           : include triggers
# --events             : include scheduled events
# --set-gtid-purged=OFF: safe for most setups; suppress GTID warnings
"${MYSQLDUMP_BIN}" \
    --defaults-extra-file="${MYSQL_CONF}" \
    --host="${DB_HOST}" \
    --port="${DB_PORT}" \
    --user="${DB_USER}" \
    --single-transaction \
    --routines \
    --triggers \
    --events \
    --set-gtid-purged=OFF \
    "${DB_NAME}" \
    | gzip -9 > "${BACKUP_FILE}"

# --- Validate output ---
if [ ! -s "${BACKUP_FILE}" ]; then
    echo "[ERROR] Backup file is empty or was not created: ${BACKUP_FILE}" >&2
    exit 1
fi

BACKUP_SIZE=$(du -sh "${BACKUP_FILE}" | cut -f1)

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Backup completed successfully"
echo "  File     : ${BACKUP_FILE}"
echo "  Size     : ${BACKUP_SIZE}"
echo ""
echo "  Verify   : gunzip -t ${BACKUP_FILE} && echo OK"
echo "  Restore  : RESTORE_CONFIRM=YES bash scripts/restore_database.sh ${BACKUP_FILE}"
