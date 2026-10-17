<?php
// QQ Connect OAuth callback. Shared by user and admin scopes.
// Usage:
//   /template/user/huli/qq_callback.php  (scope=user)
//   /admin/qq_callback.php              (scope=admin)
// Modes:
//   login (default): find the account bound to this openid and sign in
//   bind: bind the openid to the currently signed-in account
require_once __DIR__ . '/session_boot.php';
require_once __DIR__ . '/url_helper.php';
require_once __DIR__ . '/login_helper.php';
require_once __DIR__ . '/qq_connect.php';

$root = dirname(__DIR__);
if (!file_exists($root . '/config.php')) {
    die('配置文件丢失');
}
require_once $root . '/config.php';

$scope = isset($_GET['scope']) && $_GET['scope'] === 'admin' ? 'admin' : 'user';
$login_path = $scope === 'admin' ? '/admin/login.php' : '/template/user/huli/login.php';
$profile_path = $scope === 'admin' ? '/admin/profile.php' : '/user/index.php';

$fail = function ($msg) use ($login_path, $profile_path) {
    $_SESSION['qq_connect_error'] = $msg;
    $_SESSION['qq_connect_error_scope'] = $GLOBALS['scope'];
    $target = !empty($GLOBALS['bind_mode']) ? $profile_path : $login_path;
    header('Location: ' . $target);
    exit;
};

if (!qq_connect_enabled()) {
    $fail('QQ 互联未配置，请联系管理员。');
}
if (!empty($_GET['error'])) {
    $fail('QQ 授权被取消或失败：' . (string)($_GET['error_description'] ?? $_GET['error']));
}

$code  = trim((string)($_GET['code'] ?? ''));
$state = trim((string)($_GET['state'] ?? ''));
if ($code === '' || $state === '') {
    $fail('QQ 授权回调参数缺失。');
}
if (!qq_connect_check_state($state)) {
    $fail('QQ 授权状态校验失败，请重新操作。');
}

$action = qq_connect_take_action();
$GLOBALS['bind_mode'] = ($action === 'bind');
if ($action === 'bind') {
    $signed_in = $scope === 'admin' ? !empty($_SESSION['admin_id']) : !empty($_SESSION['user_id']);
    if (!$signed_in) {
        $fail('请先登录再进行 QQ 绑定。');
    }
}

$tok = qq_connect_access_token($code, $scope);
if (!$tok['ok']) {
    $fail($tok['error']);
}
$oid = qq_connect_openid($tok['access_token']);
if (!$oid['ok']) {
    $fail($oid['error']);
}
$openid = $oid['openid'];
$info = qq_connect_userinfo($tok['access_token'], $openid);

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
        $dup = $pdo->prepare("SELECT id FROM $table WHERE qq_openid = ? AND id <> ? LIMIT 1");
        $dup->execute([$openid, $account_id]);
        if ($dup->fetch()) {
            $fail('该 QQ 已绑定其他账号，请先解绑后再试。');
        }
        $upd = $pdo->prepare("UPDATE $table SET qq_openid = ? WHERE id = ?");
        $upd->execute([$openid, $account_id]);
        $_SESSION['qq_connect_notice'] = 'QQ 互联绑定成功' . (!empty($info['nickname']) ? '（' . $info['nickname'] . '）' : '') . '。';
        header('Location: ' . $profile_path);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM $table WHERE qq_openid = ? LIMIT 1");
    $stmt->execute([$openid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $fail('该 QQ 尚未绑定账号，请先登录后在个人资料中绑定 QQ 互联。');
    }

    if ($scope === 'admin') {
        if ((int)$row['status'] !== 1) {
            $fail('该 QQ 绑定的管理员账号已被禁用。');
        }
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $row['id'];
        $_SESSION['admin_username'] = defined('ADMIN_NICKNAME') ? ADMIN_NICKNAME : ($row['nickname'] ?: $row['username']);
        $pdo->prepare("UPDATE huli_admins SET last_login = CURRENT_TIMESTAMP WHERE id = ?")->execute([$row['id']]);
        try { huli_record_login($pdo, 'admin', (int)$row['id'], $_SESSION['admin_username'], 'success', ['email' => $row['email'], 'notify' => true, 'method' => 'qqconnect']); } catch (Throwable $e) {}
        header('Location: /admin/index.php');
        exit;
    }

    if ($row['status'] !== 'active') {
        $fail('该 QQ 绑定的账号已被禁用或待审核。');
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = $row['id'];
    $_SESSION['user_username'] = $row['username'];
    $_SESSION['user_email'] = $row['email'];
    try { huli_record_login($pdo, 'user', (int)$row['id'], $row['username'], 'success', ['email' => $row['email'], 'notify' => true, 'method' => 'qqconnect']); } catch (Throwable $e) {}
    header('Location: /index.php');
    exit;
} catch (PDOException $e) {
    error_log('[qq_callback] ' . $e->getMessage());
    $fail('QQ 登录服务暂时不可用，请稍后重试。');
}
