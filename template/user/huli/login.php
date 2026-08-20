<?php
require_once __DIR__ . '/../../../common/session_boot.php';
@error_reporting(0);
@ini_set('display_errors', 'Off');
$rootPath = dirname(__DIR__, 3);
define('ROOT_PATH', $rootPath . '/');
if (isset($_SESSION['user_id'])) {
    header('Location: ' . ROOT_PATH);
    exit;
}
if (!file_exists(ROOT_PATH . 'config.php')) {
    die("系统错误：配置文件丢失。路径: " . ROOT_PATH . 'config.php');
}
require_once ROOT_PATH . 'config.php';
require_once ROOT_PATH . 'common/turnstile.php';
require_once ROOT_PATH . 'common/login_helper.php';
$favicon_url = ''; try{$fp=new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET,DB_USER,DB_PASS);$favicon_url=$fp->query("SELECT setting_value FROM huli_settings WHERE setting_key='favicon_url'")->fetchColumn()?:'';}catch(Exception $e){}
$error_msg = '';
$turnstile_reason = '';
try {
    $pdo_check = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS
    );
    $stmt_settings = $pdo_check->query(
        "SELECT setting_value FROM huli_settings WHERE setting_key = 'mail_forgot_enabled'"
    );
    $mail_forgot_enabled = ($stmt_settings->fetchColumn() == 1);
} catch(Exception $e) {
    $mail_forgot_enabled = false;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    if (!huli_turnstile_verify($turnstile_reason)) {
        $error_msg = $turnstile_reason ?: '人机验证失败，请完成验证后重试';
    } elseif (empty($username) || empty($password)) {
        $error_msg = '用户名或密码不能为空。';
    } else {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
                DB_USER,
                DB_PASS
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $stmt = $pdo->prepare(
                "SELECT id, username, email, password, status
                 FROM huli_users
                 WHERE email = ? OR username = ?
                 ORDER BY (email = ?) DESC
                 LIMIT 1"
            );
            $stmt->execute([$username, $username, $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $login_ok = false;
                if (password_verify($password, $user['password'])) {
                    $login_ok = true;
                } elseif ($password === $user['password']) {
                    $pdo->prepare("UPDATE huli_users SET password = ? WHERE id = ?")->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
                    $login_ok = true;
                }
                if ($login_ok) {
                if ($user['status'] === 'active') {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_username'] = $user['username'];
                    $_SESSION['user_email'] = $user['email'];
                    try { huli_record_login($pdo, 'user', (int)$user['id'], $user['username'], 'success', ['email' => $user['email'], 'notify' => true]); } catch (Throwable $e) {}
                    try {
                        $ip_api = $pdo->prepare("SELECT id FROM huli_apis WHERE endpoint = 'ip' LIMIT 1");
                        $ip_api->execute();
                        $ip_api_id = $ip_api->fetchColumn();
                        if ($ip_api_id) {
                            $login_ip = huli_get_client_ip();
                            $pdo->prepare("UPDATE huli_apis SET total_calls = total_calls + 1 WHERE id = ?")->execute([$ip_api_id]);
                            $pdo->prepare("UPDATE huli_users SET call_count = call_count + 1 WHERE id = ?")->execute([$user['id']]);
                            $pdo->prepare("INSERT INTO huli_api_logs (api_id, user_id, ip_address, response_code, is_success, billing_type, billing_amount) VALUES (?, ?, ?, 200, 1, 'free', 0)")->execute([$ip_api_id, $user['id'], $login_ip]);
                        }
                    } catch (Throwable $e) {}
                    header('Location: index.php'); exit;
                } else {
                    $error_msg = '您的账户已被封禁或正在审核中。';
                    try { huli_record_login($pdo, 'user', (int)$user['id'], $user['username'], 'failed'); } catch (Throwable $e) {}
                }
            }
        } else {
                $error_msg = '用户名或密码不正确。';
                try { huli_record_login($pdo, 'user', 0, $username, 'failed'); } catch (Throwable $e) {}
            }
        } catch (PDOException $e) {
            $error_msg = '系统服务暂时不可用。';
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
<title>用户登录 - huliapi</title>
<?php if($favicon_url):?><link rel="shortcut icon" type="image/x-icon" href="<?php echo htmlspecialchars($favicon_url);?>"><?php endif;?>
<link rel="shortcut icon" type="image/x-icon" href="https://picui.ogmua.cn/s1/2026/05/26/6a156ea77f458.webp">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-touch-fullscreen" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<link rel="stylesheet" type="text/css" href="../../../assets/css/materialdesignicons.min.css">
<link rel="stylesheet" type="text/css" href="../../../assets/css/bootstrap.min.css">
<link rel="stylesheet" type="text/css" href="../../../assets/css/animate.min.css">
<link rel="stylesheet" type="text/css" href="../../../assets/css/style.min.css">
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
    box-shadow: 0 12px 30px rgba(40, 121, 186, 0.4) !important;
}
.btn-primary:active {
    box-shadow: 0 6px 16px rgba(40, 121, 186, 0.3) !important;
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
  <div class="text-center mb-3">
    <a href="./"> <img alt="logo" src="../../../assets/images/logo-sidebar.png"> </a>
  </div>
  <h4 class="text-center mb-3">欢迎回来</h4>
  <p class="text-center text-muted mb-4">登录以继续使用我们的服务</p>
  <?php if (!empty($error_msg)): ?>
    <div class="alert alert-danger text-center"><?php echo htmlspecialchars($error_msg); ?></div>
  <?php endif; ?>
  <form method="POST" action="login.php" class="signin-form">
    <div class="mb-3 has-feedback">
      <span class="mdi mdi-account" aria-hidden="true"></span>
      <input type="text" id="username" name="username" class="form-control" placeholder="用户名 / 邮箱" required>
    </div>
    <div class="mb-3 has-feedback">
      <span class="mdi mdi-lock" aria-hidden="true"></span>
      <input type="password" id="password" name="password" class="form-control" placeholder="密码" required>
      <?php if ($mail_forgot_enabled): ?>
      <div class="text-right mt-2">
        <a href="forgot_password.php" class="form-link">忘记密码？</a>
      </div>
      <?php endif; ?>
    </div>
    <?php echo huli_turnstile_widget_html(); ?>
    <div class="mb-3 d-grid">
      <button class="btn btn-primary" type="submit">登 录</button>
    </div>
  </form>
  <p class="text-center text-muted mb-0">还没有账户？ <a href="register.php">立即注册</a></p>
</div>
<script type="text/javascript" src="../../../assets/js/jquery.min.js"></script>
<script type="text/javascript" src="../../../assets/js/bootstrap.min.js"></script>
<script type="text/javascript">
$(document).ready(function() {
    var turnstileSubmitting = false;
    $('.signin-form').on('submit', function(e) {
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
            var $alert = $('.turnstile-alert');
            if (!$alert.length) {
                $alert = $('<div class="alert alert-danger text-center turnstile-alert"></div>').insertBefore($form);
            }
            $alert.text(message);
        });
    });
});
</script>
</body>
</html>
