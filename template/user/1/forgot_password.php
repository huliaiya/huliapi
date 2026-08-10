<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (isset($_SESSION['user_id'])) { header('Location: ../'); exit; }
$rootPath = dirname(__DIR__, 3); 
define('ROOT_PATH', $rootPath . '/');
if (!file_exists(ROOT_PATH . 'config.php')) { 
    die("系统错误：配置文件丢失。路径: " . ROOT_PATH . 'config.php'); 
}
require_once ROOT_PATH . 'config.php';
$mail_forgot_enabled = false;
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->query("SELECT setting_value FROM sl_settings WHERE setting_key = 'mail_forgot_enabled'");
    $mail_forgot_enabled = ($stmt->fetchColumn() == 1);
} catch (Exception $e) { /* silent fail */ }
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>找回密码 - 小齐API</title>
    <style>
        :root {
            --bg-color: #f8f9fa; --form-bg-color: #ffffff; --primary-color: #4a69bd;
            --text-color-dark: #212529; --text-color-light: #6c757d; --border-color: #dee2e6;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; background-color: var(--bg-color); }
        .auth-wrapper { width: 100%; max-width: 420px; padding: 20px; }
        .auth-box { background-color: var(--form-bg-color); padding: 40px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); border: 1px solid var(--border-color); }
        h1 { font-size: 24px; text-align: center; margin-bottom: 8px; }
        p { text-align: center; color: var(--text-color-light); margin-bottom: 24px; }
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; margin-bottom: 8px; font-weight: 500; }
        .form-control { width: 100%; height: 48px; padding: 0 16px; border-radius: 8px; border: 1px solid var(--border-color); }
        .btn-submit { width: 100%; padding: 14px; border: none; border-radius: 8px; background-color: var(--primary-color); color: #fff; font-size: 16px; cursor: pointer; }
        .auth-footer { text-align: center; margin-top: 24px; }
        .auth-footer a { color: var(--primary-color); font-weight: 600; text-decoration: none; }
    </style>
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-box">
            <h1>找回密码</h1><p>请输入您的注册邮箱以接收验证码</p>
            <?php if (!$mail_forgot_enabled): ?>
                <p style="color: #c00;">管理员已关闭此功能。</p>
            <?php else: ?>
            <form action="reset_password.php" method="GET">
                <div class="form-group"><label for="email" class="form-label">邮箱地址</label><input type="email" id="email" name="email" class="form-control" required></div>
                <button type="submit" class="btn-submit">下一步</button>
            </form>
            <?php endif; ?>
        </div>
        <footer class="auth-footer">记起密码了？ <a href="login.php">返回登录</a></footer>
    </div>
</body>
</html>