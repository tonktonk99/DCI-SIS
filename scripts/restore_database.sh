#!/usr/bin/env bash
# =============================================================================
# DCI-SIS Database Restore Script
# =============================================================================
# WARNING: This script OVERWRITES the target database.
# ALWAYS restore to staging or local FIRST. Verify data before touching production.
# See docs/backup-restore-plan.md — Section 4 (Restore Procedure).
#
# Required environment variables:
#   DB_NAME         — target database name (use staging name for drills!)
#   DB_USER         — database user (must have DROP, CREATE, INSERT privileges)
#   DB_PASS         — database password (NEVER hardcode here)
#   RESTORE_CONFIRM — must be set to "YES" to proceed (safety gate)
#
# Optional environment variables:
#   DB_HOST         — default: 127.0.0.1
#   DB_PORT         — default: 3306
#   MYSQL_BIN       — full path to mysql binary (default: mysql from PATH)
#                     MAMP example: /Applications/MAMP/Library/bin/mysql80/bin/mysql
#
# Usage:
#   RESTORE_CONFIRM=YES DB_NAME=dci_sis_staging DB_USER=dci_app DB_PASS=secret \
#     bash scripts/restore_database.sh backups/dci_sis_20260628_020000.sql.gz
#
# =============================================================================

set -euo pipefail

# --- Safety gate: require explicit confirmation ---
if [ "${RESTORE_CONFIRM:-}" != "YES" ]; then
    echo "" >&2
    echo "  ╔══════════════════════════════════════════════════════════╗" >&2
    echo "  ║  ⚠️  DCI-SIS DATABASE RESTORE — CONFIRMATION REQUIRED  ║" >&2
    echo "  ╚══════════════════════════════════════════════════════════╝" >&2
    echo "" >&2
    echo "  This script will OVERWRITE the target database." >&2
    echo "  ALL existing data in that database will be REPLACED." >&2
    echo "" >&2
    echo "  To proceed, set RESTORE_CONFIRM=YES:" >&2
    echo "" >&2
    echo "    RESTORE_CONFIRM=YES \\" >&2
    echo "      DB_NAME=dci_sis_staging \\" >&2
    echo "      DB_USER=dci_app \\" >&2
    echo "      DB_PASS=your_password \\" >&2
    echo "      bash scripts/restore_database.sh <backup_file.sql.gz>" >&2
    echo "" >&2
    echo "  Recommendation: use a STAGING database name, not dci_sis (production)." >&2
    echo "" >&2
    exit 1
fi

# --- Backup file argument ---
BACKUP_FILE="${1:-}"
if [ -z "${BACKUP_FILE}" ]; then
    echo "[ERROR] No backup file specified." >&2
    echo "  Usage: bash scripts/restore_database.sh <backup_file.sql.gz>" >&2
    exit 1
fi

if [ ! -f "${BACKUP_FILE}" ]; then
    echo "[ERROR] Backup file not found: ${BACKUP_FILE}" >&2
    exit 1
fi

# --- Resolve mysql binary ---
MYSQL_BIN="${MYSQL_BIN:-mysql}"
if ! command -v "${MYSQL_BIN}" &>/dev/null; then
    echo "[ERROR] mysql client not found in PATH. Set MYSQL_BIN to the full path." >&2
    echo "  MAMP: export MYSQL_BIN=/Applications/MAMP/Library/bin/mysql80/bin/mysql" >&2
    exit 1
fi

# --- Required variables ---
: "${DB_NAME:?DB_NAME is required}"
: "${DB_USER:?DB_USER is required}"
: "${DB_PASS:?DB_PASS is required}"

# --- Optional variables with defaults ---
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"

# --- Write temporary credentials file ---
MYSQL_CONF=$(mktemp)
chmod 600 "${MYSQL_CONF}"
trap 'rm -f "${MYSQL_CONF}"' EXIT

printf '[client]\npassword=%s\n' "${DB_PASS}" > "${MYSQL_CONF}"

echo "[$(date '+%Y-%m-%d %H:%M:%S')] ⚠️  Starting database restore"
echo "  Source   : ${BACKUP_FILE}"
echo "  Target DB: ${DB_NAME}"
echo "  Host     : ${DB_HOST}:${DB_PORT}"
echo "  User     : ${DB_USER}"
echo ""

RESTORE_CMD="${MYSQL_BIN} --defaults-extra-file=${MYSQL_CONF} --host=${DB_HOST} --port=${DB_PORT} --user=${DB_USER} ${DB_NAME}"

# --- Decompress and restore ---
if [[ "${BACKUP_FILE}" == *.gz ]]; then
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Decompressing and restoring..."
    gunzip -c "${BACKUP_FILE}" | ${RESTORE_CMD}
else
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Restoring from plain SQL..."
    ${RESTORE_CMD} < "${BACKUP_FILE}"
fi

echo ""
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Restore completed."
echo ""
echo "  Next steps — verify the restored database:"
echo "  1. Check row counts:"
echo "       ${MYSQL_BIN} --defaults-extra-file=${MYSQL_CONF} -h${DB_HOST} -P${DB_PORT} -u${DB_USER} ${DB_NAME} \\"
echo "         -e \"SELECT 'users' AS t, COUNT(*) n FROM users UNION ALL SELECT 'students', COUNT(*) FROM students UNION ALL SELECT 'enrollments', COUNT(*) FROM enrollments UNION ALL SELECT 'final_grades', COUNT(*) FROM final_grades UNION ALL SELECT 'audit_logs', COUNT(*) FROM audit_logs;\""
echo ""
echo "  2. Point app config to ${DB_NAME} and test login as each role."
echo "  3. See docs/backup-restore-plan.md Section 4A for full checklist."
