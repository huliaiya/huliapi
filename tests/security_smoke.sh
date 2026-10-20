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

check_login_lock() {
    local page="$1"
    local label="$2"
    local ip="198.51.100.$((RANDOM % 200 + 2))"
    local jar token i body=""
    jar="$(mktemp)"
    token="$(curl -s --max-time 10 -A "${label}-locktest" -H "X-Forwarded-For: ${ip}" -c "$jar" "${BASE_URL}${page}" | grep -o 'var t="[a-f0-9]*"' | head -1 | cut -d'"' -f2)"
    if [[ -z "$token" ]]; then
        echo "FAIL login lock token ${page}"
        failed=1
        return
    fi
    for i in 1 2 3 4 5 6; do
        body="$(curl -s --max-time 10 -A "${label}-locktest" -H "X-Forwarded-For: ${ip}" -b "$jar" -c "$jar" \
            -d "_csrf=${token}" --data-urlencode "username=${label}_lock_${ip}" --data-urlencode "password=wrongpass" \
            "${BASE_URL}${page}")"
    done
    if [[ "$body" == *"登录尝试过于频繁"* ]]; then
        echo "OK   login lock ${page}"
    else
        echo "FAIL login lock ${page}"
        failed=1
    fi
}

check_blocked "/.git/HEAD"
check_blocked "/.git/config"
check_blocked "/logs/update_status_abc123.json"
check_blocked "/install/install.sql"
check_blocked "/install/install.lock"
check_blocked "/install/upgrade_updater_db_tables.sql"
check_blocked "/cli/cron.php"
check_blocked "/cli/update_scheduler.php"
check_blocked "/tests/security_smoke.sh"
check_blocked "/deploy/nginx.conf.example"
check_blocked "/README.md"
check_blocked "/common/mcp/mcp_prompt_admin.md"
check_ok "/"
check_ok "/admin/login.php"
check_ok "/gh_callback.php"
check_ok "/admin/gh_callback.php"

# 已安装状态下，安装器只允许查看完成页 GET，任何 POST 必须被拒绝。
install_post_body="$(curl -s --max-time 10 -X POST -d 'step=4' "${BASE_URL}/install/index.php" || true)"
if [[ "$install_post_body" == *"系统已安装"* ]]; then
    echo "OK   install POST blocked"
else
    echo "FAIL install POST not blocked"
    failed=1
fi

# 登录 POST 缺少 CSRF token 必须返回 403。
login_csrf_code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 -X POST -d 'username=csrf_probe&password=csrf_probe' "${BASE_URL}/template/user/huli/login.php" || true)"
if [[ "$login_csrf_code" == "403" ]]; then
    echo "OK   403 login without CSRF"
else
    echo "FAIL ${login_csrf_code} login without CSRF"
    failed=1
fi

check_login_lock "/template/user/huli/login.php" "user"
check_login_lock "/admin/login.php" "admin"

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

php -r '
require $argv[1] . "/common/url_helper.php";
$cases = [
    ["javascript://%0aalert(1)", false],
    ["data:text/html,<script>alert(1)</script>", false],
    ["http://example.com", true],
    ["https://example.com/path", true],
];
$bad = 0;
foreach ($cases as [$url, $want]) {
    $got = huli_is_safe_http_url($url);
    if ($got !== $want) { fwrite(STDERR, "FAIL friend url {$url}\n"); $bad = 1; }
    else { echo "OK   friend url {$url}\n"; }
}
exit($bad);
' "$ROOT_DIR" || failed=1

php -r '
require $argv[1] . "/common/updater.php";
$cases = [
    ["app/config.php", true],
    ["dir/file.php", true],
    ["../evil.php", false],
    ["a/../../evil.php", false],
    ["/etc/passwd", false],
    ["C:\\\\evil.php", false],
    ["a\\\\..\\\\evil.php", false],
];
$bad = 0;
foreach ($cases as [$name, $want]) {
    $got = huli_updater_zip_entry_is_safe($name);
    if ($got !== $want) { fwrite(STDERR, "FAIL zip entry {$name}\n"); $bad = 1; }
    else { echo "OK   zip entry {$name}\n"; }
}
exit($bad);
' "$ROOT_DIR" || failed=1

php -r '
require $argv[1] . "/config.php";
require $argv[1] . "/common/client_ip.php";
$cases = [
    ["8.8.8.8", "1.2.3.4", "8.8.8.8"],
    ["127.0.0.1", "8.8.4.4", "8.8.4.4"],
    ["10.0.0.5", "8.8.8.8", "10.0.0.5"],
];
$bad = 0;
foreach ($cases as [$remote, $xff, $want]) {
    $_SERVER["REMOTE_ADDR"] = $remote;
    $_SERVER["HTTP_X_FORWARDED_FOR"] = $xff;
    $got = huli_client_ip();
    if ($got !== $want) { fwrite(STDERR, "FAIL client ip {$remote} {$xff} => {$got}\n"); $bad = 1; }
    else { echo "OK   client ip {$remote} xff={$xff} => {$got}\n"; }
}
exit($bad);
' "$ROOT_DIR" || failed=1

php -r '
require $argv[1] . "/common/security/api_owner.php";
$cases = [
    [["visibility" => "private", "admin_id" => 0, "endpoint" => "u5_ip_abcd1234"], 5],
    [["visibility" => "private", "admin_id" => 0, "endpoint" => "u12_x"], 12],
    [["visibility" => "public", "admin_id" => 0, "endpoint" => "u5_ip"], null],
    [["visibility" => "private", "admin_id" => 1, "endpoint" => "u5_ip"], null],
    [["visibility" => "private", "admin_id" => 0, "endpoint" => "ip"], null],
    [["visibility" => "private", "admin_id" => 0, "endpoint" => "xu5_ip"], null],
];
$bad = 0;
foreach ($cases as [$api, $want]) {
    $got = huli_api_owner_user_id($api);
    if ($got !== $want) { fwrite(STDERR, "FAIL api owner " . json_encode($api) . " => " . var_export($got, true) . "\n"); $bad = 1; }
    else { echo "OK   api owner " . $api["endpoint"] . " => " . var_export($got, true) . "\n"; }
}
exit($bad);
' "$ROOT_DIR" || failed=1

php -r '
require $argv[1] . "/config.php";
require $argv[1] . "/common/gh_connect.php";
$u = gh_connect_redirect_uri();
if (preg_match("#/gh_callback\\.php$#", $u)) { echo "OK   oauth callback {$u}\n"; }
else { fwrite(STDERR, "FAIL oauth callback {$u}\n"); exit(1); }
' "$ROOT_DIR" || failed=1

if [[ "$failed" -ne 0 ]]; then
    echo "security smoke failed"
    exit 1
fi

echo "security smoke passed"
