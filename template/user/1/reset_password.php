<?php
session_start();
error_reporting(0);
ini_set('display_errors', 'Off');
if (isset($_SESSION['user_id'])) { header('Location: ../'); exit; }
$rootPath = dirname(__DIR__, 3);
define('ROOT_PATH', $rootPath . '/');
if (!file_exists(ROOT_PATH . 'config.php')) {
    die("系统错误：配置文件丢失。路径: " . ROOT_PATH . 'config.php');
}
require_once ROOT_PATH . 'config.php';
require_once ROOT_PATH . 'common/turnstile.php';
$favicon_url = ''; try{$fp=new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET,DB_USER,DB_PASS);$favicon_url=$fp->query("SELECT setting_value FROM huli_settings WHERE setting_key='favicon_url'")->fetchColumn()?:'';}catch(Exception $e){}
$email_from_get = isset($_GET['email']) ? trim($_GET['email']) : '';
$error_msg = '';
$success_msg = '';
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = trim($_POST['email']);
        $code = trim($_POST['code']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        if (!huli_turnstile_verify()) {
            throw new Exception('人机验证失败，请完成 Cloudflare 验证后重试。');
        }
        if (empty($email) || empty($code) || empty($password)) {
            throw new Exception('所有字段均为必填项。');
        }
        if ($password !== $confirm_password) {
            throw new Exception('两次输入的密码不一致。');
        }
        if (!isset($_SESSION['reset_code']) || !isset($_SESSION['reset_code_expire']) || time() > $_SESSION['reset_code_expire'] || strtolower($code) != strtolower($_SESSION['reset_code']) || strtolower($email) != strtolower($_SESSION['reset_email'])) {
            throw new Exception('邮箱验证码不正确或已过期。');
        }
        $stmt = $pdo->prepare("UPDATE huli_users SET password = ? WHERE email = ?");
        $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $email]);
        unset($_SESSION['reset_code'], $_SESSION['reset_email'], $_SESSION['reset_code_expire']);
        $success_msg = '密码重置成功！请使用新密码登录。';
    }
} catch (Exception $e) {
    $error_msg = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>重置密码 - huliapi</title>
    <?php if($favicon_url):?><link rel="shortcut icon" type="image/x-icon" href="<?php echo htmlspecialchars($favicon_url);?>"><?php endif;?>
    <style>
        :root {
            --bg-color: #f8f9fa; --form-bg-color: #ffffff; --primary-color: #4a69bd;
            --text-color-dark: #212529; --text-color-light: #6c757d; --border-color: #dee2e6;
            --error-bg: #f8d7da; --error-text: #721c24; --success-bg: #d1e7dd; --success-text: #0f5132;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; background-color: var(--bg-color); }
        .auth-wrapper { width: 100%; max-width: 420px; padding: 20px; }
        .auth-box { background-color: var(--form-bg-color); padding: 40px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); border: 1px solid var(--border-color); }
        h1 { font-size: 24px; text-align: center; margin-bottom: 24px; }
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; margin-bottom: 8px; font-weight: 500; }
        .form-control { width: 100%; height: 48px; padding: 0 16px; border-radius: 8px; border: 1px solid var(--border-color); }
        .input-group { display: flex; }
        .input-group .form-control { border-radius: 8px 0 0 8px; }
        .btn-send-code { height: 48px; border: 1px solid var(--border-color); border-left: none; background-color: #f1f3f5; padding: 0 16px; border-radius: 0 8px 8px 0; cursor: pointer; }
        .btn-send-code:disabled { cursor: not-allowed; }
        .btn-submit { width: 100%; padding: 14px; border: none; border-radius: 8px; background-color: var(--primary-color); color: #fff; font-size: 16px; cursor: pointer; }
        .feedback-container { min-height: 40px; }
        .feedback-message { width: 100%; padding: 10px 16px; border-radius: 8px; border: 1px solid; text-align: center; font-size: 14px; margin-bottom: 20px; }
        .feedback-message.error { background-color: var(--error-bg); color: var(--error-text); }
        .feedback-message.success { background-color: var(--success-bg); color: var(--success-text); }
        .auth-footer a { color: var(--primary-color); font-weight: 600; text-decoration: none; }
    </style>
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-box">
            <h1>重置您的密码</h1>
            <div class="feedback-container">
                <?php if (!empty($error_msg)): ?><div class="feedback-message error"><?php echo htmlspecialchars($error_msg); ?></div><?php endif; ?>
                <?php if (!empty($success_msg)): ?><div class="feedback-message success"><?php echo htmlspecialchars($success_msg); ?></div><?php endif; ?>
            </div>
            <?php if (empty($success_msg)): ?>
            <form method="POST" action="reset_password.php">
                <div class="form-group"><label for="email" class="form-label">邮箱地址</label><input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email_from_get); ?>" required></div>
                <div class="form-group"><label for="code" class="form-label">邮箱验证码</label><div class="input-group"><input type="text" id="code" name="code" class="form-control" required><button type="button" id="send-code-btn" class="btn-send-code">获取验证码</button></div></div>
                <div class="form-group"><label for="password" class="form-label">新密码</label><input type="password" id="password" name="password" class="form-control" required></div>
                <div class="form-group"><label for="confirm_password" class="form-label">确认新密码</label><input type="password" id="confirm_password" name="confirm_password" class="form-control" required></div>
                <?php echo huli_turnstile_widget_html(); ?>
                <button type="submit" class="btn-submit">确认重置</button>
            </form>
            <?php else: ?>
            <div class="auth-footer" style="margin-top:0;"><a href="login.php" class="btn-submit">返回登录</a></div>
            <?php endif; ?>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const sendCodeBtn = document.getElementById('send-code-btn');
        if(sendCodeBtn) {
            sendCodeBtn.addEventListener('click', function() {
                const email = document.getElementById('email').value.trim();
                if (!email) { alert('请先输入您的邮箱地址。'); return; }
                this.disabled = true; let countdown = 60; this.innerText = countdown + 's';
                const interval = setInterval(() => { countdown--; this.innerText = countdown + 's'; if (countdown <= 0) { clearInterval(interval); this.disabled = false; this.innerText = '获取验证码'; } }, 1000);
                fetch('../../../common/ajax/send_code.php', { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: 'email=' + encodeURIComponent(email) + '&type=reset' })
                .then(res => res.json()).then(data => { if (!data.success) { alert(data.message); clearInterval(interval); sendCodeBtn.disabled = false; sendCodeBtn.innerText = '获取验证码'; } else { alert('验证码已发送。'); } });
            });
        }
    });
    </script>
</body>
</html>
