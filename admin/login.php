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
$favicon_url = '';
try { $fp = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET,DB_USER,DB_PASS); $favicon_url = $fp->query("SELECT setting_value FROM huli_settings WHERE setting_key='favicon_url'")->fetchColumn()?:''; } catch(Exception $e) {}
$error_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $has_turnstile = huli_turnstile_enabled();
    if ($has_turnstile) {
        if (!huli_turnstile_verify()) {
            $error_msg = '人机验证失败，请完成 Cloudflare 验证后重试';
        }
    } else {
        $captcha = strtolower(trim($_POST['captcha'] ?? ''));
        if (empty($_SESSION['captcha_code']) || $captcha !== strtolower($_SESSION['captcha_code'])) {
            $error_msg = '验证码不正确，请重新输入';
        }
    }
    if (!$error_msg && (empty($username) || empty($password))) {
        $error_msg = '账号或密码不能为空';
    }
    if (!$error_msg) {
        try {
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $stmt = $pdo->prepare("SELECT id, password, nickname, status FROM huli_admins WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($admin && (password_verify($password, $admin['password']) || hash_equals($admin['password'], $password))) {
                if ($admin['status'] == 1) {
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_username'] = defined('ADMIN_NICKNAME') ? ADMIN_NICKNAME : ($admin['nickname'] ?: $username);
                    $updateStmt = $pdo->prepare("UPDATE huli_admins SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
                    $updateStmt->execute([$admin['id']]);
                    header('Location: index.php');
                    exit;
                } else {
                    $error_msg = '该账户已被禁用';
                }
            } else {
                $error_msg = '账号或密码不正确';
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
.signin-form .has-feedback {
    position: relative;
}
.signin-form .has-feedback .form-control {
    padding-left: 36px;
}
.signin-form .has-feedback .mdi {
    position: absolute;
    top: 0;
    left: 0;
    right: auto;
    width: 36px;
    height: 36px;
    line-height: 36px;
    z-index: 4;
    color: #dcdcdc;
    display: block;
    text-align: center;
    pointer-events: none;
}
.signin-form .has-feedback.row .mdi {
    left: 15px;
}
body {
    background-image: url(images/login-bg-2.jpg);
    background-size: cover;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
}
.card {
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    border-radius: 10px;
    border: none;
}
.error-message {
    padding: 10px 15px;
    border-radius: 4px;
}
.captcha-img {
    cursor: pointer;
    height: 38px;
    border-radius: 4px;
    border: 1px solid #dee2e6;
}
</style>
</head>
<body>
<div class="card card-shadowed p-5 mb-0 mr-2 ml-2" style="max-width: 450px;">
  <div class="text-center mb-4">
    <a href="./"> <img alt="light year admin" src="../assets/images/logo-sidebar.png"> </a>
  </div>
  <form method="POST" action="login.php" class="signin-form needs-validation" novalidate>
    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger error-message mb-3"><?php echo htmlspecialchars($error_msg); ?></div>
    <?php endif; ?>
    <div class="mb-3 has-feedback">
      <span class="mdi mdi-account" aria-hidden="true"></span>
      <input type="text" class="form-control" id="username" name="username" placeholder="管理员账号" required>
    </div>
    <div class="mb-3 has-feedback">
      <span class="mdi mdi-lock" aria-hidden="true"></span>
      <input type="password" class="form-control" id="password" name="password" placeholder="密码" required>
    </div>
    <?php if (huli_turnstile_enabled()): ?>
    <?php echo huli_turnstile_widget_html(); ?>
    <?php else: ?>
    <div class="mb-3 has-feedback row">
      <div class="col-7">
        <span class="mdi mdi-shield-check" aria-hidden="true"></span>
        <input type="text" name="captcha" class="form-control" placeholder="验证码" required>
      </div>
      <div class="col-5 text-right">
        <img src="../common/ajax/captcha.php?r=<?php echo time(); ?>" class="captcha-img" id="captcha" title="点击刷新" alt="captcha">
      </div>
    </div>
    <?php endif; ?>
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
  <p class="text-center text-muted mb-0 small">Copyright © 2025-2026 huliapi 版权所有</p>
</div>
<script type="text/javascript" src="../assets/js/jquery.min.js"></script>
<script type="text/javascript" src="../assets/js/bootstrap.bundle.min.js"></script>
<script type="text/javascript">
$(document).ready(function() {
    $('.signin-form').on('submit', function(e) {
        if (this.checkValidity() === false) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('was-validated');
            return false;
        }
    function refreshCaptcha() {
        $('#captcha').attr('src', '../common/ajax/captcha.php?r=' + Math.random());
    }
    $('#captcha').on('click', refreshCaptcha);
});
});
</script>
</body>
</html>
