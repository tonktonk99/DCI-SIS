#!/usr/bin/env bash
# =============================================================================
# DCI-SIS Non-Destructive Public Smoke Check
# =============================================================================
# Checks HTTP status of public pages, unauthenticated redirects, sensitive file
# blocking (.htaccess), and security headers.
#
# DOES NOT:
#   - Login with any credentials
#   - Modify any data
#   - Write to the database
#   - Test authenticated pages (those require manual testing — see docs/production-smoke-checklist.md)
#
# Required environment variables:
#   BASE_URL — base URL of the DCI-SIS installation (no trailing slash)
#              Example: http://localhost/dci-sis
#              Example: https://staging.example.ac.th/dci-sis
#
# Usage:
#   BASE_URL=http://localhost/dci-sis bash scripts/smoke_check.sh
#
# See docs/production-smoke-checklist.md for the full manual smoke checklist.
# =============================================================================

set -euo pipefail

# --- Require BASE_URL ---
: "${BASE_URL:?BASE_URL is required. Example: BASE_URL=http://localhost/dci-sis bash scripts/smoke_check.sh}"
BASE_URL="${BASE_URL%/}"   # strip trailing slash

PASS=0
FAIL=0
WARN=0

# --- Helpers ---
pass() { echo "  [PASS] $*"; PASS=$((PASS + 1)); }
fail() { echo "  [FAIL] $*" >&2; FAIL=$((FAIL + 1)); }
warn() { echo "  [WARN] $*"; WARN=$((WARN + 1)); }

check_status() {
    local desc="$1" url="$2" expected="$3"
    local actual
    actual=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 --location-trusted "${url}" 2>/dev/null || echo "000")
    if [ "${actual}" = "${expected}" ]; then
        pass "${desc} → HTTP ${actual}"
    else
        fail "${desc} → expected HTTP ${expected}, got HTTP ${actual} (URL: ${url})"
    fi
}

check_status_no_follow() {
    local desc="$1" url="$2" expected="$3"
    local actual
    actual=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "${url}" 2>/dev/null || echo "000")
    if [ "${actual}" = "${expected}" ]; then
        pass "${desc} → HTTP ${actual}"
    else
        fail "${desc} → expected HTTP ${expected}, got HTTP ${actual} (URL: ${url})"
    fi
}

check_header() {
    local desc="$1" url="$2" header="$3"
    local response actual
    response=$(curl -s -I --max-time 10 "${url}" 2>/dev/null || echo "")
    actual=$(echo "${response}" | grep -i "^${header}:" | head -1 | tr -d '\r')
    if [ -n "${actual}" ]; then
        pass "${desc} → ${actual}"
    else
        fail "${desc} → header '${header}' not found in response from ${url}"
    fi
}

check_header_warn() {
    local desc="$1" url="$2" header="$3"
    local response actual
    response=$(curl -s -I --max-time 10 "${url}" 2>/dev/null || echo "")
    actual=$(echo "${response}" | grep -i "^${header}:" | head -1 | tr -d '\r')
    if [ -n "${actual}" ]; then
        pass "${desc} → ${actual}"
    else
        warn "${desc} → header '${header}' not found (may not be critical for HTTP)"
    fi
}

# --- Main ---
echo ""
echo "============================================================"
echo "  DCI-SIS Smoke Check — $(date '+%Y-%m-%d %H:%M:%S')"
echo "  Target: ${BASE_URL}"
echo "============================================================"
echo ""

echo "=== 1. Public Pages ==="
check_status          "Login page loads"         "${BASE_URL}/login.php"  200
check_status_no_follow "Root redirects (302)"    "${BASE_URL}/"           302

echo ""
echo "=== 2. Authenticated Pages (unauthenticated → expect redirect) ==="
# These should redirect to login (302) because no session is present
check_status_no_follow "index.php redirects unauthenticated"              "${BASE_URL}/index.php"                    302
check_status_no_follow "admin/dashboard.php redirects unauthenticated"    "${BASE_URL}/admin/dashboard.php"          302
check_status_no_follow "registrar/students.php redirects unauthenticated" "${BASE_URL}/registrar/students.php"       302
check_status_no_follow "professor/gradebook.php redirects unauthenticated" "${BASE_URL}/professor/gradebook.php"    302
check_status_no_follow "student/enrollment.php redirects unauthenticated" "${BASE_URL}/student/enrollment.php"      302
check_status_no_follow "alumni/dashboard.php redirects unauthenticated"   "${BASE_URL}/alumni/dashboard.php"        302

echo ""
echo "=== 3. Sensitive File Blocking (.htaccess / Nginx) ==="
check_status_no_follow "config/database.php blocked"            "${BASE_URL}/config/database.php"               403
check_status_no_follow "config/session.php blocked"             "${BASE_URL}/config/session.php"                403
check_status_no_follow "includes/auth.php blocked"              "${BASE_URL}/includes/auth.php"                 403
check_status_no_follow "includes/csrf.php blocked"              "${BASE_URL}/includes/csrf.php"                 403
check_status_no_follow "scripts/ dir blocked"                   "${BASE_URL}/scripts/"                          403
check_status_no_follow "database/ dir blocked"                  "${BASE_URL}/database/"                         403
check_status_no_follow ".sql file blocked (task29 in web root)" "${BASE_URL}/task29_duplicate_protection_FULL.sql" 403

echo ""
echo "=== 4. Security Headers (on login page) ==="
LOGIN_URL="${BASE_URL}/login.php"
check_header      "X-Frame-Options set"          "${LOGIN_URL}" "X-Frame-Options"
check_header      "X-Content-Type-Options set"   "${LOGIN_URL}" "X-Content-Type-Options"
check_header      "Referrer-Policy set"          "${LOGIN_URL}" "Referrer-Policy"
check_header      "Permissions-Policy set"       "${LOGIN_URL}" "Permissions-Policy"
check_header_warn "HTTPS: Strict-Transport-Security (optional — requires HTTPS)" "${LOGIN_URL}" "Strict-Transport-Security"

echo ""
echo "=== 5. No Directory Listing ==="
# Should return 403 (forbidden) or 404, not 200 with a directory listing
DIRS_RESP=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "${BASE_URL}/assets/" 2>/dev/null || echo "000")
if [ "${DIRS_RESP}" != "200" ]; then
    pass "assets/ directory listing disabled (HTTP ${DIRS_RESP})"
else
    # 200 on assets/ might be OK if index exists, but warn for review
    warn "assets/ returned HTTP 200 — verify directory listing is disabled in web server config"
fi

echo ""
echo "============================================================"
echo "  Results: ${PASS} passed, ${WARN} warnings, ${FAIL} failed"
echo "============================================================"
echo ""

if [ "${FAIL}" -gt 0 ]; then
    echo "  ✗  ${FAIL} check(s) failed. Review output above before proceeding."
    echo ""
    echo "  Common fixes:"
    echo "    - HTTP 403 for public pages: check .htaccess / Nginx config"
    echo "    - Missing security headers: check config/session.php APP_ENV"
    echo "    - config/ not blocked: ensure .htaccess has RewriteRule for config/"
    echo ""
    echo "  For authenticated page testing, see:"
    echo "    docs/production-smoke-checklist.md (manual checklist)"
    exit 1
else
    echo "  ✓  All automated checks passed."
    if [ "${WARN}" -gt 0 ]; then
        echo "  ⚠  ${WARN} warning(s) — review recommended but not blocking."
    fi
    echo ""
    echo "  Next: run the manual authenticated checks in:"
    echo "    docs/production-smoke-checklist.md Section 2"
    exit 0
fi
