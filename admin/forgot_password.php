<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (isset($_SESSION['admin_id'])) { header('Location: index.php'); exit; }
if (file_exists('../config.php')) { require_once '../config.php'; } else { die("出现错误！配置文件丢失，请先完成安装。"); }
$mail_forgot_enabled = false;
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->query("SELECT setting_value FROM huli_settings WHERE setting_key = 'mail_admin_forgot_enabled'");
    $mail_forgot_enabled = ($stmt->fetchColumn() == 1);
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
<title>找回密码 - <?php echo htmlspecialchars($settings['site_name'] ?? 'huliapi'); ?></title>
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-touch-fullscreen" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<link rel="stylesheet" type="text/css" href="../assets/css/materialdesignicons.min.css">
<link rel="stylesheet" type="text/css" href="../assets/css/bootstrap.min.css">
<link rel="stylesheet" type="text/css" href="../assets/css/animate.min.css">
<link rel="stylesheet" type="text/css" href="../assets/css/style.min.css">
<style>
.signin-form .has-feedback { position: relative; }
.signin-form .has-feedback .form-control { padding-left: 36px; }
.signin-form .has-feedback .mdi {
    position: absolute; top: 0; left: 0; right: auto; width: 36px; height: 36px;
    line-height: 36px; z-index: 4; color: #dcdcdc; display: block;
    text-align: center; pointer-events: none;
}
</style>
</head>
<body class="center-vh" style="background-image: url(../assets/images/login-bg-2.jpg); background-size: cover;">
<div class="card card-shadowed p-5 mb-0 mr-2 ml-2" style="max-width: 450px;">
  <div class="text-center mb-3">
    <a href="./"> <img alt="找回密码" src="../assets/images/logo-sidebar.png"> </a>
  </div>
  <h4 class="text-center mb-3">找回密码</h4>
  <p class="text-center text-muted mb-4">请输入您管理员账号绑定的邮箱</p>
  <?php if (!$mail_forgot_enabled): ?>
    <div class="alert alert-danger text-center">管理员已关闭此功能。</div>
  <?php else: ?>
    <form action="reset_password.php" method="GET" class="signin-form">
      <div class="mb-3 has-feedback">
        <span class="mdi mdi-email" aria-hidden="true"></span>
        <input type="email" class="form-control" id="email" name="email" placeholder="管理员邮箱" required>
      </div>
      <div class="mb-3 d-grid">
        <button class="btn btn-primary" type="submit">下一步</button>
      </div>
    </form>
  <?php endif; ?>
  <p class="text-center text-muted mb-0">记起密码了？ <a href="login.php">返回登录</a></p>
</div>
<script type="text/javascript" src="../assets/js/jquery.min.js"></script>
<script type="text/javascript" src="../assets/js/bootstrap.min.js"></script>
</body>
</html>
