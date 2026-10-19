#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${BASE_URL:-http://127.0.0.1:8080}"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
failed=0

check_blocked() {
    local path="$1"
    local code
    code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "${BASE_URL}${path}")"
    if [[ "$code" == "403" || "$code" == "404" ]]; then
        echo "OK   ${code} ${path}"
    else
        echo "FAIL ${code} ${path}"
        failed=1
    fi
}

check_ok() {
    local path="$1"
    local code
    code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "${BASE_URL}${path}")"
    if [[ "$code" == "200" || "$code" == "302" ]]; then
        echo "OK   ${code} ${path}"
    else
        echo "FAIL ${code} ${path}"
        failed=1
    fi
}

check_blocked "/.git/HEAD"
check_blocked "/.git/config"
check_blocked "/logs/update_status_abc123.json"
check_blocked "/install/install.sql"
check_blocked "/install/install.lock"
check_blocked "/install/upgrade_updater_db_tables.sql"
check_ok "/"
check_ok "/admin/login.php"

php -r '
require $argv[1] . "/config.php";
require $argv[1] . "/common/mcp/mcp_lib.php";
$cases = [
    ["https://example.com", true],
    ["https://example.com:443", true],
    ["https://example.com:8443", false],
    ["http://127.0.0.1:6379", false],
    ["http://169.254.169.254/latest/meta-data", false],
    ["http://localhost:8080", false],
];
$bad = 0;
foreach ($cases as [$url, $want]) {
    $got = huli_mcp_safe_outbound_url($url);
    if ($got !== $want) { fwrite(STDERR, "FAIL ssrf {$url}\n"); $bad = 1; }
    else { echo "OK   ssrf {$url}\n"; }
}
exit($bad);
' "$ROOT_DIR" || failed=1

if [[ "$failed" -ne 0 ]]; then
    echo "security smoke failed"
    exit 1
fi

echo "security smoke passed"
