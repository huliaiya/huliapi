<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }
    @ini_set('session.use_strict_mode', '1');
    @ini_set('session.use_only_cookies', '1');
    @ini_set('session.cookie_httponly', '1');
    @ini_set('session.cookie_samesite', 'Lax');
    @ini_set('session.use_trans_sid', '0');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        @ini_set('session.cookie_secure', '1');
    }
    @session_start();
}

if (file_exists(__DIR__ . '/updater_schedule.php')) {
    require_once __DIR__ . '/updater_schedule.php';
    huli_updater_maybe_run_schedule();
}
