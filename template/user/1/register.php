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
$error_msg = '';
$success_msg = '';
$settings = [];
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt_settings = $pdo->query("SELECT setting_key, setting_value FROM huli_settings WHERE setting_key IN ('allow_registration', 'mail_reg_enabled')");
    $settings = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR);
    $registration_allowed = $settings['allow_registration'] ?? 1;
    $mail_reg_enabled = $settings['mail_reg_enabled'] ?? 0;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $registration_allowed) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $code = trim($_POST['code']);

        if (!huli_turnstile_verify()) {
            throw new Exception('人机验证失败，请完成 Cloudflare 验证后重试。');
        }
        if (empty($username) || empty($email) || empty($password)) {
            throw new Exception('所有字段均为必填项。');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('邮箱格式不正确。');
        }
        if (strlen($password) < 6) {
            throw new Exception('密码长度不能少于6位。');
        }
        if ($password !== $confirm_password) {
            throw new Exception('两次输入的密码不一致。');
        }
        if ($mail_reg_enabled) {
            if (empty($code) || !isset($_SESSION['reg_code']) || strtolower($code) != strtolower($_SESSION['reg_code']) || strtolower($email) != strtolower($_SESSION['reg_email'])) {
                throw new Exception('邮箱验证码不正确或已过期。');
            }
        }
        $stmt_check_user = $pdo->prepare("SELECT id FROM huli_users WHERE username = ?");
        $stmt_check_user->execute([$username]);
        if ($stmt_check_user->fetch()) {
            throw new Exception('该用户名已被注册。');
        }
        $stmt_check_email = $pdo->prepare("SELECT id FROM huli_users WHERE email = ?");
        $stmt_check_email->execute([$email]);
        if ($stmt_check_email->fetch()) {
            throw new Exception('该邮箱已被注册。');
        }
        $api_key = bin2hex(random_bytes(32));
        $sql = "INSERT INTO huli_users (username, email, password, api_key, status) VALUES (?, ?, ?, ?, 'active')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $api_key]);
        unset($_SESSION['reg_code'], $_SESSION['reg_email']);
        $success_msg = '注册成功！您现在可以使用您的账户登录了。';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !$registration_allowed) {
        $error_msg = '注册失败，管理员已关闭注册功能。';
    }
} catch (Exception $e) {
    $error_msg = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户注册 - huliapi</title>
    <?php if (!empty($settings['favicon_url'])): ?><link rel="shortcut icon" type="image/x-icon" href="<?php echo htmlspecialchars($settings['favicon_url']); ?>"><?php endif; ?>
    <style>
        :root {
            --bg-color: #f8f9fa; --form-bg-color: #ffffff; --primary-color: #4a69bd;
            --text-color-dark: #212529; --text-color-light: #6c757d; --border-color: #dee2e6;
            --shadow-color: rgba(0, 0, 0, 0.05); --error-bg: #f8d7da; --error-text: #721c24; --error-border: #f5c6cb;
            --success-bg: #d1e7dd; --success-text: #0f5132; --success-border: #badbcc;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
        html, body { height: 100%; }
        body { font-family: 'Inter', sans-serif; display: flex; justify-content: center; align-items: center; background-color: var(--bg-color); color: var(--text-color-dark); }
        .auth-wrapper { width: 100%; max-width: 420px; animation: fadeIn 0.7s ease-out forwards; padding: 20px; }
        .auth-box { background-color: var(--form-bg-color); padding: 40px; border-radius: 16px; box-shadow: 0 10px 30px var(--shadow-color); border: 1px solid var(--border-color); }
        .auth-header { text-align: center; margin-bottom: 32px; }
        .auth-header .logo { display: inline-block; background-color: var(--primary-color); color: #fff; width: 50px; height: 50px; border-radius: 12px; font-size: 24px; font-weight: 700; line-height: 50px; margin-bottom: 16px; text-decoration: none; }
        .auth-header h1 { font-size: 24px; font-weight: 600; margin-bottom: 4px; }
        .auth-header p { font-size: 14px; color: var(--text-color-light); }
        .form-group { position: relative; margin-bottom: 24px; }
        .form-label { font-size: 14px; font-weight: 500; margin-bottom: 8px; display: block; }
        .form-control { width: 100%; height: 48px; padding: 0 16px; background-color: #f1f3f5; border: 1px solid transparent; border-radius: 8px; font-size: 16px; color: var(--text-color-dark); transition: all 0.3s ease; }
        .form-control:focus { outline: none; border-color: var(--primary-color); background-color: var(--form-bg-color); box-shadow: 0 0 0 3px rgba(74, 105, 189, 0.2); }
        .input-group { display: flex; }
        .input-group .form-control { border-radius: 8px 0 0 8px; }
        .btn-send-code { height: 48px; border: 1px solid var(--border-color); border-left: none; background-color: #f1f3f5; padding: 0 16px; border-radius: 0 8px 8px 0; white-space: nowrap; cursor: pointer; }
        .btn-send-code:disabled { cursor: not-allowed; background-color: #e9ecef; }
        .btn-submit { width: 100%; padding: 14px; border: none; border-radius: 8px; background-color: var(--primary-color); color: #fff; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; margin-top: 8px; }
        .btn-submit:hover { background-color: #3a539b; }
        .feedback-container { min-height: 40px; display: flex; align-items: center; justify-content: center; }
        .feedback-message { width: 100%; padding: 10px 16px; border-radius: 8px; border: 1px solid; text-align: center; font-size: 14px; font-weight: 500; animation: fadeIn 0.3s ease; }
        .feedback-message.error { background-color: var(--error-bg); color: var(--error-text); border-color: var(--error-border); }
        .feedback-message.success { background-color: var(--success-bg); color: var(--success-text); border-color: var(--success-border); }
        .auth-footer { text-align: center; margin-top: 24px; font-size: 14px; color: var(--text-color-light); }
        .auth-footer a { color: var(--primary-color); text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-box">
            <header class="auth-header"><a href="../" class="logo">S</a><h1>创建您的账户</h1><p>加入我们，开启您的API之旅</p></header>
            <div class="feedback-container">
                <?php if (!$registration_allowed): ?><div class="feedback-message error">管理员已暂时关闭注册功能。</div>
                <?php else: ?>
                    <?php if (!empty($error_msg)): ?><div class="feedback-message error"><?php echo htmlspecialchars($error_msg); ?></div><?php endif; ?>
                    <?php if (!empty($success_msg)): ?><div class="feedback-message success"><?php echo htmlspecialchars($success_msg); ?></div><?php endif; ?>
                <?php endif; ?>
            </div>
            <?php if (empty($success_msg) && $registration_allowed): ?>
            <form method="POST" action="register.php" novalidate>
                <div class="form-group"><label for="username" class="form-label">用户名</label><input type="text" id="username" name="username" class="form-control" placeholder="创建您的用户名" required></div>
                <div class="form-group"><label for="email" class="form-label">邮箱地址</label><input type="email" id="email" name="email" class="form-control" placeholder="请输入您的邮箱" required></div>
                <?php if ($mail_reg_enabled): ?>
                <div class="form-group"><label for="code" class="form-label">邮箱验证码</label><div class="input-group"><input type="text" id="code" name="code" class="form-control" placeholder="请输入6位验证码" required><button type="button" id="send-code-btn" class="btn-send-code">获取验证码</button></div></div>
                <?php endif; ?>
                <div class="form-group"><label for="password" class="form-label">密码</label><input type="password" id="password" name="password" class="form-control" placeholder="设置您的密码 (至少6位)" required></div>
                <div class="form-group"><label for="confirm_password" class="form-label">确认密码</label><input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="请再次输入密码" required></div>
                <?php echo huli_turnstile_widget_html(); ?>
                <button type="submit" class="btn-submit">注 册</button>
            </form>
            <?php endif; ?>
        </div>
        <footer class="auth-footer">已有账户？ <a href="login.php">直接登录</a></footer>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const sendCodeBtn = document.getElementById('send-code-btn');
        if (sendCodeBtn) {
            sendCodeBtn.addEventListener('click', function() {
                const emailInput = document.getElementById('email');
                const email = emailInput.value.trim();
                if (!email) { alert('请先输入您的邮箱地址。'); return; }
                this.disabled = true; let countdown = 60; this.innerText = countdown + 's 后重试';
                const interval = setInterval(() => { countdown--; this.innerText = countdown + 's 后重试'; if (countdown <= 0) { clearInterval(interval); this.disabled = false; this.innerText = '获取验证码'; } }, 1000);
                fetch('../../../common/ajax/send_code.php', { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: 'email=' + encodeURIComponent(email) + '&type=register' })
                .then(res => res.json()).then(data => { if (!data.success) { alert(data.message); clearInterval(interval); sendCodeBtn.disabled = false; sendCodeBtn.innerText = '获取验证码'; } else { alert('验证码已发送，请注意查收。'); } });
            });
        }
    });
    </script>
</body>
</html>
