<?php
if (!function_exists('huli_honored_forwarded_headers') && file_exists(__DIR__ . '/client_ip.php')) {
    require_once __DIR__ . '/client_ip.php';
}
if (session_status() !== PHP_SESSION_ACTIVE) {
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }
    @ini_set('session.use_strict_mode', '1');
    @ini_set('session.use_only_cookies', '1');
    @ini_set('session.cookie_httponly', '1');
    @ini_set('session.cookie_samesite', 'Lax');
    @ini_set('session.use_trans_sid', '0');
    $huli_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
    // 仅当请求来自受信任代理时才允许 X-Forwarded-* 头覆盖协议判断，防止直连部署下伪造
    if (!$huli_https && huli_honored_forwarded_headers()) {
        $huli_fw = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
        $huli_https = ($huli_fw === 'https');
        if (!$huli_https && !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_SSL']) !== 'off') {
            $huli_https = true;
        }
    }
    if ($huli_https) {
        @ini_set('session.cookie_secure', '1');
    }
    @session_start();
}

if (file_exists(__DIR__ . '/updater_schedule.php')) {
    require_once __DIR__ . '/updater_schedule.php';
    huli_updater_maybe_run_schedule();
}

if (empty($_SESSION['admin_id']) && !empty($_COOKIE['huli_admin_remember'])) {
    $huli_root = dirname(__DIR__);
    if (file_exists($huli_root . '/config.php')) {
        if (!defined('DB_HOST')) { require_once $huli_root . '/config.php'; }
        if (!function_exists('huli_get_client_ip') && file_exists(__DIR__ . '/login_helper.php')) {
            require_once __DIR__ . '/login_helper.php';
        }
        require_once __DIR__ . '/admin_remember.php';
        huli_admin_remember_login();
    }
}
