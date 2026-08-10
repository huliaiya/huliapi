<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
if (file_exists('../config.php')) { require_once '../config.php'; } else { die("出现错误！配置文件丢失。"); }
$username = htmlspecialchars($_SESSION['admin_username']); $admin_id = $_SESSION['admin_id'];
$feedback_msg = ''; $feedback_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password']; $new_password = $_POST['new_password']; $confirm_password = $_POST['confirm_password'];
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $feedback_msg = '所有字段均为必填项。'; $feedback_type = 'error';
    } elseif ($new_password !== $confirm_password) {
        $feedback_msg = '新密码和确认密码不匹配。'; $feedback_type = 'error';
    } else {
        try {
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $stmt = $pdo->prepare("SELECT password FROM sl_admins WHERE id = ?"); $stmt->execute([$admin_id]);
            $admin = $stmt->fetch();
            if ($admin && $current_password === $admin['password']) {
                $update_stmt = $pdo->prepare("UPDATE sl_admins SET password = ? WHERE id = ?");
                $update_stmt->execute([$new_password, $admin_id]);
                $feedback_msg = '密码已成功更新。'; $feedback_type = 'success';
            } else { $feedback_msg = '当前密码不正确。'; $feedback_type = 'error'; }
        } catch (PDOException $e) { $feedback_msg = '出现错误！数据库操作失败。'; $feedback_type = 'error'; }
    }
}
$current_page_script = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="zh">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-touch-fullscreen" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="stylesheet" type="text/css" href="../assets/css/materialdesignicons.min.css">
    <link rel="stylesheet" type="text/css" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="../assets/css/style.min.css">
</head>
<body>
<div class="container-fluid">
  <div class="row">
    <div class="col-lg-12">
      <div class="card">
        <header class="card-header"><div class="card-title">管理员个人资料</div></header>
        <div class="card-body">
          <?php if ($feedback_msg): ?>
          <div class="alert alert-<?php echo $feedback_type === 'success' ? 'success' : 'danger'; ?> mb-3">
            <?php echo htmlspecialchars($feedback_msg); ?>
          </div>
          <?php endif; ?>
          <form method="POST" action="profile.php" class="site-form">
            <div class="mb-3">
              <label for="username">用户名</label>
              <input type="text" class="form-control" name="username" id="username" value="<?php echo $username; ?>" disabled>
            </div>
            <div class="mb-3">
              <label for="current_password">当前密码</label>
              <input type="password" class="form-control" name="current_password" id="current_password" placeholder="请输入您当前的密码" required>
            </div>
            <div class="mb-3">
              <label for="new_password">新密码</label>
              <input type="password" class="form-control" name="new_password" id="new_password" placeholder="请输入您的新密码" required>
            </div>
            <div class="mb-3">
              <label for="confirm_password">确认新密码</label>
              <input type="password" class="form-control" name="confirm_password" id="confirm_password" placeholder="请再次输入新密码" required>
            </div>
            <button type="submit" class="btn btn-primary">更新密码</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<script type="text/javascript" src="../assets/js/jquery.min.js"></script>
<script type="text/javascript" src="../assets/js/popper.min.js"></script>
<script type="text/javascript" src="../assets/js/bootstrap.min.js"></script>
<script type="text/javascript" src="../assets/js/main.min.js"></script>
</body>
</html>