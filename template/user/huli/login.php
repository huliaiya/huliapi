<?php
@session_start();
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
.signin-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  background: radial-gradient(1200px 600px at 10% 10%, rgba(99, 102, 241, 0.18), transparent 60%),
              radial-gradient(900px 500px at 90% 90%, rgba(56, 189, 248, 0.18), transparent 60%),
              linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
  background-attachment: fixed;
  position: relative;
  overflow: hidden;
}
.signin-page::before {
  content: "";
  position: absolute;
  inset: 0;
  background-image: url(https://api.ipojie.com/API/ksxjj.php?apikey=31a3477d8396e9a6f606e2c09c386b9b0bb472e21a32cf0d35f2b1af0177ceff);
  background-size: cover;
  background-position: center;
  opacity: 0.18;
  filter: blur(6px) saturate(1.1);
  pointer-events: none;
}
.signin-card {
  width: 100%;
  max-width: 420px;
  background: rgba(255, 255, 255, 0.78);
  backdrop-filter: blur(18px) saturate(1.2);
  -webkit-backdrop-filter: blur(18px) saturate(1.2);
  border: 1px solid rgba(255, 255, 255, 0.6);
  border-radius: 18px;
  box-shadow: 0 18px 50px rgba(15, 23, 42, 0.28), 0 4px 12px rgba(15, 23, 42, 0.12);
  padding: 36px 32px 28px;
  position: relative;
  z-index: 1;
  animation: signin-fade-up 0.55s cubic-bezier(0.22, 1, 0.36, 1);
}
@keyframes signin-fade-up {
  from { opacity: 0; transform: translateY(14px); }
  to   { opacity: 1; transform: translateY(0); }
}
.signin-logo {
  width: 64px;
  height: 64px;
  border-radius: 16px;
  background: linear-gradient(135deg, #6366f1 0%, #38bdf8 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 30px;
  margin: 0 auto 16px;
  box-shadow: 0 10px 24px rgba(99, 102, 241, 0.35);
}
.signin-title {
  font-size: 22px;
  font-weight: 700;
  text-align: center;
  color: #0f172a;
  margin-bottom: 6px;
}
.signin-subtitle {
  font-size: 13px;
  text-align: center;
  color: #64748b;
  margin-bottom: 24px;
}
.signin-form .form-group {
  margin-bottom: 16px;
  position: relative;
}
.signin-form .form-group .mdi {
  position: absolute;
  top: 14px;
  left: 14px;
  color: #94a3b8;
  font-size: 18px;
  pointer-events: none;
  z-index: 2;
}
.signin-form .form-control {
  height: 46px;
  border-radius: 12px;
  border: 1px solid rgba(148, 163, 184, 0.35);
  padding: 0 14px 0 42px;
  font-size: 14px;
  background: rgba(255, 255, 255, 0.85);
  transition: border-color 0.18s, box-shadow 0.18s, background 0.18s;
}
.signin-form .form-control:focus {
  border-color: #6366f1;
  box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
  background: #fff;
  outline: none;
}
.signin-form .form-control::placeholder {
  color: #94a3b8;
}
.signin-form .form-link {
  font-size: 13px;
  color: #6366f1;
  text-decoration: none;
  font-weight: 500;
}
.signin-form .form-link:hover {
  color: #4f46e5;
  text-decoration: underline;
}
.signin-form .btn-primary {
  height: 46px;
  border-radius: 12px;
  font-size: 15px;
  font-weight: 600;
  letter-spacing: 0.05em;
  background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
  border: none;
  box-shadow: 0 8px 18px rgba(99, 102, 241, 0.35);
  transition: transform 0.15s, box-shadow 0.15s;
}
.signin-form .btn-primary:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 12px 22px rgba(99, 102, 241, 0.45);
}
.signin-form .btn-primary:active:not(:disabled) {
  transform: translateY(0);
  box-shadow: 0 6px 14px rgba(99, 102, 241, 0.35);
}
.signin-form .btn-primary:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}
.signin-form .huli-turnstile {
  margin-bottom: 16px;
}
.signin-form .alert {
  border-radius: 10px;
  font-size: 13px;
  padding: 10px 14px;
  border: none;
}
.signin-form .alert-danger {
  background: rgba(239, 68, 68, 0.1);
  color: #b91c1c;
}
.signin-footer {
  margin-top: 22px;
  padding-top: 18px;
  border-top: 1px dashed rgba(148, 163, 184, 0.4);
  text-align: center;
  font-size: 13px;
  color: #64748b;
}
.signin-footer a {
  color: #6366f1;
  text-decoration: none;
  font-weight: 500;
}
.signin-footer a:hover {
  text-decoration: underline;
}
@media (max-width: 480px) {
  .signin-card { padding: 28px 22px 22px; border-radius: 14px; }
  .signin-title { font-size: 20px; }
}
</style>
</head>
<body>
<div class="signin-page">
  <div class="signin-card">
    <div class="signin-logo"><span class="mdi mdi-shield-lock-outline"></span></div>
    <div class="signin-title">欢迎回来</div>
    <div class="signin-subtitle">登录以继续使用 huliapi 服务</div>
    <?php if (!empty($error_msg)): ?>
      <div class="alert alert-danger text-center mb-3"><?php echo htmlspecialchars($error_msg); ?></div>
    <?php endif; ?>
    <form method="POST" action="login.php" class="signin-form">
      <div class="form-group">
        <span class="mdi mdi-account-outline" aria-hidden="true"></span>
        <input type="text" id="username" name="username" class="form-control" placeholder="用户名 / 邮箱" autocomplete="username" required>
      </div>
      <div class="form-group">
        <span class="mdi mdi-lock-outline" aria-hidden="true"></span>
        <input type="password" id="password" name="password" class="form-control" placeholder="密码" autocomplete="current-password" required>
        <?php if ($mail_forgot_enabled): ?>
        <div class="text-right mt-1">
          <a href="forgot_password.php" class="form-link">忘记密码？</a>
        </div>
        <?php endif; ?>
      </div>
      <?php echo huli_turnstile_widget_html(); ?>
      <div class="d-grid mt-2">
        <button class="btn btn-primary" type="submit"><span class="mdi mdi-login-variant"></span> 登 录</button>
      </div>
      <div class="signin-footer">
        还没有账户？ <a href="register.php">立即注册</a>
      </div>
    </form>
  </div>
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
