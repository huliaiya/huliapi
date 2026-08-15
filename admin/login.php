<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}
if (file_exists('../config.php')) {
    require_once '../config.php';
} else {
    die("出现错误！配置文件丢失，请先完成安装。");
}
require_once __DIR__ . '/../common/turnstile.php';
require_once __DIR__ . '/../common/login_helper.php';
    $settings = ['site_name' => 'huliapi'];
    $favicon_url = '';
try { $fp = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET,DB_USER,DB_PASS); $settings['site_name'] = $fp->query("SELECT setting_value FROM huli_settings WHERE setting_key='site_name'")->fetchColumn() ?: 'huliapi'; $favicon_url = $fp->query("SELECT setting_value FROM huli_settings WHERE setting_key='favicon_url'")->fetchColumn()?:''; $mail_forgot_enabled = ((int)$fp->query("SELECT setting_value FROM huli_settings WHERE setting_key='mail_admin_forgot_enabled'")->fetchColumn() === 1); } catch(Exception $e) {}
$error_msg = '';
$turnstile_reason = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    if (!huli_turnstile_verify($turnstile_reason)) {
        $error_msg = $turnstile_reason ?: '人机验证失败，请完成 Cloudflare 验证后重试';
    }
    if (!$error_msg && (empty($username) || empty($password))) {
        $error_msg = '账号或密码不能为空';
    }
    if (!$error_msg) {
        try {
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $stmt = $pdo->prepare("SELECT id, username, email, password, nickname, status FROM huli_admins WHERE email = ? OR username = ? ORDER BY (email = ?) DESC LIMIT 1");
            $stmt->execute([$username, $username, $username]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($admin && password_verify($password, $admin['password'])) {
                if ($admin['status'] == 1) {
                    session_regenerate_id(true);
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_username'] = defined('ADMIN_NICKNAME') ? ADMIN_NICKNAME : ($admin['nickname'] ?: $username);
                    $updateStmt = $pdo->prepare("UPDATE huli_admins SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
                    $updateStmt->execute([$admin['id']]);
                    try { huli_record_login($pdo, 'admin', (int)$admin['id'], $_SESSION['admin_username'], 'success', ['email' => $admin['email'], 'notify' => true]); } catch (Throwable $e) {}
                    header('Location: index.php');
                    exit;
                } else {
                    $error_msg = '该账户已被禁用';
                    try { huli_record_login($pdo, 'admin', 0, $username, 'failed'); } catch (Throwable $e) {}
                }
            } else {
                $error_msg = '账号或密码不正确';
                try { huli_record_login($pdo, 'admin', 0, $username, 'failed'); } catch (Throwable $e) {}
            }
        } catch (PDOException $e) {
            $error_msg = '系统服务暂时不可用，请稍后重试。';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
<title>登录 - <?php echo htmlspecialchars($settings['site_name'] ?? 'huliapi'); ?></title>
    <?php if ($favicon_url): ?><link rel="shortcut icon" type="image/x-icon" href="<?php echo htmlspecialchars($favicon_url); ?>"><?php endif; ?>
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-touch-fullscreen" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<link rel="stylesheet" type="text/css" href="../assets/css/materialdesignicons.min.css">
<link rel="stylesheet" type="text/css" href="../assets/css/bootstrap.min.css">
<link rel="stylesheet" type="text/css" href="../assets/css/animate.min.css">
<link rel="stylesheet" type="text/css" href="../assets/css/style.min.css">
<style>
body {
    background: 
        radial-gradient(circle at 10% 20%, rgba(93, 177, 255, 0.52), transparent 45%),
        radial-gradient(circle at 90% 80%, rgba(38, 208, 194, 0.38), transparent 48%),
        radial-gradient(circle at 50% 50%, rgba(113, 132, 255, 0.28), transparent 50%),
        linear-gradient(135deg, #f5f8fc 0%, #eef3fa 100%);
    background-attachment: fixed;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}
.glass-card {
    background: rgba(255, 255, 255, 0.45);
    backdrop-filter: blur(25px) saturate(200%);
    -webkit-backdrop-filter: blur(25px) saturate(200%);
    border: 1px solid rgba(255, 255, 255, 0.35);
    border-radius: 24px;
    box-shadow: 0 28px 75px rgba(10, 25, 50, 0.12), inset 0 1px 0 rgba(255, 255, 255, 0.45);
    padding: 3.5rem !important;
}
.signin-form .has-feedback {
    position: relative;
}
.signin-form .has-feedback .form-control {
    padding-left: 38px;
}
.signin-form .has-feedback .mdi {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    left: 12px;
    z-index: 4;
    color: #61718c;
    pointer-events: none;
}
.form-control {
    background: rgba(255, 255, 255, 0.55) !important;
    border: 1px solid rgba(255, 255, 255, 0.45) !important;
    backdrop-filter: blur(5px);
    border-radius: 12px;
    color: #2c3e50 !important;
    min-height: 46px;
    transition: all 0.3s ease;
}
.form-control:focus {
    background: rgba(255, 255, 255, 0.85) !important;
    border-color: #2879ba !important;
    box-shadow: 0 0 15px rgba(40, 121, 186, 0.2) !important;
}
.btn-primary {
    background: linear-gradient(135deg, #2879ba 0%, #2cb4e1 100%) !important;
    border: none !important;
    border-radius: 12px !important;
    box-shadow: 0 8px 25px rgba(40, 121, 186, 0.3) !important;
    transition: all 0.3s ease !important;
    font-weight: 600 !important;
    min-height: 46px;
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 30px rgba(40, 121, 186, 0.4) !important;
}
.btn-primary:active {
    transform: translateY(0);
}
.form-link {
    font-size: 13px;
    color: #2879ba;
    text-decoration: none;
    font-weight: 500;
}
.error-message {
    padding: 10px 15px;
    border-radius: 8px;
    background: rgba(220, 84, 117, 0.15) !important;
    border: 1px solid rgba(220, 84, 117, 0.25) !important;
    color: #dc5475 !important;
}
</style>
</head>
<body>
<div class="glass-card mb-0 mr-2 ml-2" style="max-width: 450px; width: 100%;">
  <div class="text-center mb-4">
    <a href="./"> <img alt="huli admin" src="../assets/images/logo-sidebar.png"> </a>
    <h4 class="mt-3 mb-1"><?php echo htmlspecialchars($settings['site_name'] ?? 'huliapi'); ?></h4>
    <p class="text-muted mb-0">管理控制台 · 请登录以继续</p>
  </div>
  <form method="POST" action="login.php" class="signin-form needs-validation" novalidate>
    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger error-message mb-3"><?php echo htmlspecialchars($error_msg); ?></div>
    <?php endif; ?>
    <div class="mb-3 has-feedback">
      <span class="mdi mdi-account" aria-hidden="true"></span>
      <input type="text" class="form-control" id="username" name="username" placeholder="管理员账号 / 邮箱" required>
    </div>
    <div class="mb-3 has-feedback">
      <span class="mdi mdi-lock" aria-hidden="true"></span>
      <input type="password" class="form-control" id="password" name="password" placeholder="密码" required>
    </div>
    <?php echo huli_turnstile_widget_html(); ?>
    <div class="mb-3">
      <div class="form-check">
        <input type="checkbox" class="form-check-input" id="remember" name="remember">
        <label class="form-check-label not-user-select" for="remember">7天内自动登录</label>
      </div>
    </div>
    <div class="mb-3 d-grid">
      <button class="btn btn-primary" type="submit">安全登录</button>
    </div>
  </form>
  <?php if (!empty($mail_forgot_enabled)): ?>
    <p class="text-center mb-0 small"><a href="forgot_password.php">忘记密码？</a></p>
  <?php endif; ?>
  <p class="text-center text-muted mb-0 small mt-2">Copyright © 2025-2026 huliapi 版权所有</p>
</div>
<script type="text/javascript" src="../assets/js/jquery.min.js"></script>
<script type="text/javascript" src="../assets/js/bootstrap.bundle.min.js"></script>
<script type="text/javascript">
$(document).ready(function() {
    var turnstileSubmitting = false;
    $('.signin-form').on('submit', function(e) {
        if (this.checkValidity() === false) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('was-validated');
            return false;
        }
        if (turnstileSubmitting || typeof window.huliTurnstileEnsureToken !== 'function') {
            return;
        }
        if (!$('.huli-turnstile').length) {
            return;
        }
        e.preventDefault();
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        var original = $btn.text();
        $btn.prop('disabled', true).text('正在验证...');
        window.huliTurnstileEnsureToken(function() {
            turnstileSubmitting = true;
            $btn.prop('disabled', false).text(original);
            $form[0].submit();
        }, function(message) {
            $btn.prop('disabled', false).text(original);
            var $alert = $('.error-message');
            if (!$alert.length) {
                $alert = $('<div class="alert alert-danger error-message mb-3"></div>').prependTo($form);
            }
            $alert.text(message);
        });
    });
});
</script>
</body>
</html>
