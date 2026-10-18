<?php
// GitHub OAuth callback. Shared by user and admin scopes.
// Modes:
//   login (default): find the account bound to this GitHub id and sign in
//   bind: bind the GitHub id to the currently signed-in account
require_once __DIR__ . '/session_boot.php';
require_once __DIR__ . '/url_helper.php';
require_once __DIR__ . '/login_helper.php';
require_once __DIR__ . '/gh_connect.php';
require_once __DIR__ . '/turnstile.php';

$root = dirname(__DIR__);
if (!file_exists($root . '/config.php')) {
    die('配置文件丢失');
}
require_once $root . '/config.php';

$scope = gh_connect_take_scope();
$login_path = $scope === 'admin' ? '/admin/login.php' : '/template/user/huli/login.php';
$profile_path = $scope === 'admin' ? '/admin/profile.php' : '/template/user/huli/main.php';

$GLOBALS['gh_scope'] = $scope;
$GLOBALS['gh_bind_mode'] = false;

$fail = function ($msg) use ($login_path, $profile_path) {
    $_SESSION['gh_connect_error'] = $msg;
    $target = !empty($GLOBALS['gh_bind_mode']) ? $profile_path : $login_path;
    header('Location: ' . $target);
    exit;
};

if (!gh_connect_enabled()) {
    $fail('GitHub 登录未配置，请联系管理员。');
}
if (!empty($_GET['error'])) {
    $fail('GitHub 授权被取消或失败：' . (string)($_GET['error_description'] ?? $_GET['error']));
}

$code  = trim((string)($_GET['code'] ?? ''));
$state = trim((string)($_GET['state'] ?? ''));
if ($code === '' || $state === '') {
    $fail('GitHub 授权回调参数缺失。');
}
if (!gh_connect_check_state($state)) {
    $fail('GitHub 授权状态校验失败，请重新操作。');
}

$action = gh_connect_take_action();
$GLOBALS['gh_bind_mode'] = ($action === 'bind');
if ($action === 'bind') {
    $signed_in = $scope === 'admin' ? !empty($_SESSION['admin_id']) : !empty($_SESSION['user_id']);
    if (!$signed_in) {
        $fail('请先登录再进行 GitHub 绑定。');
    }
    // Binding escalates an account to GitHub sign-in, so it needs the same
    // human check that guards the password login form.
    $ts_reason = '';
    if (!huli_turnstile_verify_token(trim((string)($_POST['cf-turnstile-response'] ?? '')), $ts_reason)) {
        $_SESSION['gh_bind_error'] = $ts_reason !== '' ? $ts_reason : '人机验证未通过，请完成验证后重试。';
        header('Location: ' . $profile_path);
        exit;
    }
}

$tok = gh_connect_access_token($code, $scope);
if (!$tok['ok']) {
    $fail($tok['error']);
}
$gh = gh_connect_user($tok['access_token']);
if (!$gh['ok']) {
    $fail($gh['error']);
}
$gh_id = $gh['id'];

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . (defined('DB_PORT') ? DB_PORT : 3306)
        . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $table = $scope === 'admin' ? 'huli_admins' : 'huli_users';

    if ($action === 'bind') {
        $account_id = (int)($scope === 'admin' ? $_SESSION['admin_id'] : $_SESSION['user_id']);
        $dup = $pdo->prepare("SELECT id FROM $table WHERE gh_id = ? AND id <> ? LIMIT 1");
        $dup->execute([$gh_id, $account_id]);
        if ($dup->fetch()) {
            $fail('该 GitHub 账号已绑定其他账号，请先解绑后再试。');
        }
        $upd = $pdo->prepare("UPDATE $table SET gh_id = ? WHERE id = ?");
        $upd->execute([$gh_id, $account_id]);
        $label = $gh['login'] !== '' ? $gh['login'] : $gh_id;
        $_SESSION['gh_connect_notice'] = 'GitHub 绑定成功（' . $label . '）。';
        header('Location: ' . $profile_path);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM $table WHERE gh_id = ? LIMIT 1");
    $stmt->execute([$gh_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $fail('该 GitHub 账号尚未绑定本站账号，请先登录后在个人资料中绑定 GitHub。');
    }

    if ($scope === 'admin') {
        if ((int)$row['status'] !== 1) {
            $fail('该 GitHub 绑定的管理员账号已被禁用。');
        }
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $row['id'];
        $_SESSION['admin_username'] = defined('ADMIN_NICKNAME') ? ADMIN_NICKNAME : ($row['nickname'] ?: $row['username']);
        gh_connect_flash('admin', 'github');
        $pdo->prepare("UPDATE huli_admins SET last_login = CURRENT_TIMESTAMP WHERE id = ?")->execute([$row['id']]);
        try { huli_record_login($pdo, 'admin', (int)$row['id'], $_SESSION['admin_username'], 'success', ['email' => $row['email'], 'notify' => true, 'method' => 'github']); } catch (Throwable $e) {}
        header('Location: /admin/index.php');
        exit;
    }

    if ($row['status'] !== 'active') {
        $fail('该 GitHub 绑定的账号已被禁用或待审核。');
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = $row['id'];
    $_SESSION['user_username'] = $row['username'];
    $_SESSION['user_email'] = $row['email'];
    gh_connect_flash('user', 'github');
    try { huli_record_login($pdo, 'user', (int)$row['id'], $row['username'], 'success', ['email' => $row['email'], 'notify' => true, 'method' => 'github']); } catch (Throwable $e) {}
    header('Location: /index.php');
    exit;
} catch (PDOException $e) {
    error_log('[gh_callback] ' . $e->getMessage());
    $fail('GitHub 登录服务暂时不可用，请稍后重试。');
}
