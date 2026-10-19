<?php
// GitHub 绑定入口：先在本站完成人机验证，再跳转到 GitHub 授权页。
// 绑定表单直接 POST 到 GitHub 会让 Turnstile token 丢失，因此改为先提交到这里。
require_once __DIR__ . '/common/session_boot.php';
@error_reporting(0);
@ini_set('display_errors', 'Off');

if (!file_exists(__DIR__ . '/config.php')) {
    die('配置文件丢失');
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/common/gh_connect.php';
require_once __DIR__ . '/common/turnstile.php';

$is_admin = !empty($_SESSION['admin_id']);
$is_user = !empty($_SESSION['user_id']);
$scope = $is_admin ? 'admin' : ($is_user ? 'user' : '');
$account_id = (int)($is_admin ? $_SESSION['admin_id'] : ($is_user ? $_SESSION['user_id'] : 0));
$profile_path = $is_admin ? '/admin/profile.php' : '/template/user/huli/main.php';
$login_path = $is_admin ? '/admin/login.php' : '/template/user/huli/login.php';

$back = function (string $message, string $target) {
    $_SESSION['gh_bind_error'] = $message;
    header('Location: ' . $target);
    exit;
};

if ($scope === '' || $account_id <= 0) {
    header('Location: ' . $login_path);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $profile_path);
    exit;
}
if (!gh_connect_enabled()) {
    $back('GitHub 登录未配置，请联系管理员。', $profile_path);
}

$expected_csrf = (string)($_SESSION['gh_bind_csrf'] ?? '');
$posted_csrf = (string)($_POST['gh_csrf'] ?? '');
if ($expected_csrf === '' || !hash_equals($expected_csrf, $posted_csrf)) {
    $back('页面已过期，请刷新后重试。', $profile_path);
}
unset($_SESSION['gh_bind_csrf']);

if (huli_turnstile_enabled()) {
    $reason = '';
    $result = huli_turnstile_verify_token(trim((string)($_POST['cf-turnstile-response'] ?? '')), $reason);
    if (empty($result['ok'])) {
        $back($reason !== '' ? $reason : '人机验证未通过，请重新验证后再试。', $profile_path);
    }
}

header('Location: ' . gh_connect_auth_url($scope, 'bind', $account_id));
exit;
