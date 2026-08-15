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
$mail_forgot_enabled = false;
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->query("SELECT setting_value FROM huli_settings WHERE setting_key = 'mail_forgot_enabled'");
    $mail_forgot_enabled = ($stmt->fetchColumn() == 1);
} catch (Exception $e) {   }
?>

<!DOCTYPE html>
<html lang="zh">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
<title>找回密码 - huliapi</title>
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
    transform: translateY(-2px);
    box-shadow: 0 12px 30px rgba(40, 121, 186, 0.4) !important;
}
.btn-primary:active {
    transform: translateY(0);
}
</style>
</head>
<body>
<div class="glass-card mb-0 mr-2 ml-2" style="max-width: 450px; width: 100%;">
  <div class="text-center mb-3">
    <a href="./"> <img alt="找回密码页面" src="../../../assets/images/logo-sidebar.png"> </a>
  </div>
  <h4 class="text-center mb-3">找回密码</h4>
  <p class="text-center text-muted mb-4">请输入您的注册邮箱</p>
  <?php if (!$mail_forgot_enabled): ?>
    <div class="alert alert-danger text-center">管理员已关闭此功能。</div>
  <?php else: ?>
    <form action="reset_password.php" method="GET" class="signin-form">
      <div class="mb-3 has-feedback">
        <span class="mdi mdi-email" aria-hidden="true"></span>
        <input type="email" class="form-control" id="email" name="email" placeholder="邮箱地址" required>
      </div>
      <div class="mb-3 d-grid">
        <button class="btn btn-primary" type="submit">下一步</button>
      </div>
    </form>
  <?php endif; ?>
  <p class="text-center text-muted mb-0">记起密码了？ <a href="login.php">返回登录</a></p>
</div>
<script type="text/javascript" src="../../../assets/js/jquery.min.js"></script>
<script type="text/javascript" src="../../../assets/js/bootstrap.min.js"></script>
</body>
</html>
