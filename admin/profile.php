<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
if (file_exists('../config.php')) { require_once '../config.php'; } else { die("出现错误！配置文件丢失。"); }
require_once __DIR__ . '/../common/avatar.php';
require_once __DIR__ . '/../common/push.php';
$username = htmlspecialchars($_SESSION['admin_username']); $admin_id = $_SESSION['admin_id'];
$admin_qq = '';
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->prepare("SELECT qq, nickname, email FROM huli_admins WHERE id = ?"); $stmt->execute([$admin_id]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    $admin_qq = $admin['qq'] ?? '';
    $admin_email = $admin['email'] ?? '';
    $nickname = htmlspecialchars($admin['nickname'] ?? $username);
} catch (PDOException $e) { $nickname = $username; $admin_email = ''; }
$feedback_msg = ''; $feedback_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $type = $_POST['form_type'] ?? '';
        if ($type === 'password') {
        $current_password = $_POST['current_password']; $new_password = $_POST['new_password']; $confirm_password = $_POST['confirm_password'];
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $feedback_msg = '所有字段均为必填项。'; $feedback_type = 'error';
        } elseif ($new_password !== $confirm_password) {
            $feedback_msg = '新密码和确认密码不匹配。'; $feedback_type = 'error';
        } else {
            try {
                $stmt_pw = $pdo->prepare("SELECT password FROM huli_admins WHERE id = ?"); $stmt_pw->execute([$admin_id]);
                $admin_data = $stmt_pw->fetch();
                if ($admin_data && password_verify($current_password, $admin_data['password'])) {
                    $update_stmt = $pdo->prepare("UPDATE huli_admins SET password = ? WHERE id = ?");
                    $update_stmt->execute([password_hash($new_password, PASSWORD_DEFAULT), $admin_id]);
                    $feedback_msg = '密码已成功更新。'; $feedback_type = 'success';
                } else { $feedback_msg = '当前密码不正确。'; $feedback_type = 'error'; }
            } catch (PDOException $e) { $feedback_msg = '出现错误！数据库操作失败。'; $feedback_type = 'error'; }
        }
    } elseif ($type === 'qq') {
        $new_qq = trim($_POST['qq'] ?? '');
        if ($new_qq !== '' && !preg_match('/^\d{5,11}$/', $new_qq)) {
            $feedback_msg = '请输入有效的QQ号（5-11位数字）。'; $feedback_type = 'error';
        } else {
            try {
                $update_stmt = $pdo->prepare("UPDATE huli_admins SET qq = ? WHERE id = ?");
                $update_stmt->execute([$new_qq, $admin_id]);
                $admin_qq = $new_qq;
                $feedback_msg = 'QQ号已更新，头像已刷新。'; $feedback_type = 'success';
            } catch (PDOException $e) { $feedback_msg = '出现错误！数据库操作失败。'; $feedback_type = 'error'; }
        }
    } elseif ($type === 'profile') {
        $new_nickname = trim($_POST['nickname'] ?? '');
        $new_email = trim($_POST['email'] ?? '');
        if ($new_nickname === '') {
            $feedback_msg = '管理员昵称不能为空。'; $feedback_type = 'error';
        } elseif ($new_email !== '' && !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $feedback_msg = '请输入有效的邮箱地址。'; $feedback_type = 'error';
        } else {
            try {
                $update_stmt = $pdo->prepare("UPDATE huli_admins SET nickname = ?, email = ? WHERE id = ?");
                $update_stmt->execute([$new_nickname, $new_email, $admin_id]);
                $nickname = htmlspecialchars($new_nickname);
                $admin_email = $new_email;
                $feedback_msg = '个人资料已更新。'; $feedback_type = 'success';
            } catch (PDOException $e) { $feedback_msg = '出现错误！数据库操作失败。'; $feedback_type = 'error'; }
        }
    } elseif ($type === 'username') {
        $new_username = trim($_POST['new_username'] ?? '');
        $current_pw = $_POST['current_password_for_username'] ?? '';
        if ($new_username === '' || $current_pw === '') {
            $feedback_msg = '新用户名与当前密码均为必填。'; $feedback_type = 'error';
        } elseif (!preg_match('/^[A-Za-z0-9_]{2,32}$/', $new_username)) {
            $feedback_msg = '新用户名需要 2-32 位字母、数字或下划线。'; $feedback_type = 'error';
        } else {
            try {
                $stmt_chk = $pdo->prepare("SELECT password FROM huli_admins WHERE id = ?"); $stmt_chk->execute([$admin_id]);
                $admin_pw_row = $stmt_chk->fetch();
                if (!$admin_pw_row || !password_verify($current_pw, $admin_pw_row['password'])) {
                    $feedback_msg = '当前密码不正确。'; $feedback_type = 'error';
                } else {
                    $stmt_exist = $pdo->prepare("SELECT id FROM huli_admins WHERE username = ? AND id <> ?"); $stmt_exist->execute([$new_username, $admin_id]);
                    if ($stmt_exist->fetch()) {
                        $feedback_msg = '该用户名已被占用，请换一个。'; $feedback_type = 'error';
                    } else {
                        $pdo->prepare("UPDATE huli_admins SET username = ? WHERE id = ?")->execute([$new_username, $admin_id]);
                        $_SESSION['admin_username'] = $new_username;
                        $username = htmlspecialchars($new_username);
                        $feedback_msg = '管理员用户名已更新，下次请使用新用户名登录。'; $feedback_type = 'success';
                    }
                }
            } catch (PDOException $e) { $feedback_msg = '出现错误！数据库操作失败。'; $feedback_type = 'error'; }
        }
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
          <div class="text-center mb-4">
            <img src="<?php echo htmlspecialchars(huli_avatar_url($admin_qq)); ?>" alt="头像" class="rounded-circle" style="width:96px;height:96px;object-fit:cover;">
            <h5 class="mt-2"><?php echo $nickname; ?></h5>
          </div>
          <?php if ($feedback_msg): ?>
          <div class="alert alert-<?php echo $feedback_type === 'success' ? 'success' : 'danger'; ?> mb-3">
            <?php echo htmlspecialchars($feedback_msg); ?>
          </div>
          <?php endif; ?>
          <form method="POST" action="profile.php" class="site-form mb-4">
            <input type="hidden" name="form_type" value="profile">
            <div class="mb-3">
              <label for="nickname">管理员昵称</label>
              <input type="text" class="form-control" name="nickname" id="nickname" value="<?php echo $nickname; ?>" required>
            </div>
            <div class="mb-3">
              <label for="email">管理员邮箱</label>
              <input type="email" class="form-control" name="email" id="email" value="<?php echo htmlspecialchars($admin_email); ?>" placeholder="用于找回密码等系统通知">
            </div>
            <button type="submit" class="btn btn-primary">保存</button>
          </form>
          <form method="POST" action="profile.php" class="site-form mb-4">
            <input type="hidden" name="form_type" value="qq">
            <div class="mb-3">
              <label for="qq">QQ号</label>
              <input type="text" class="form-control" name="qq" id="qq" value="<?php echo htmlspecialchars($admin_qq); ?>" placeholder="填写QQ号后自动加载头像">
              <small class="text-muted">头像使用 QQ 官方 API 自动获取，不填则显示默认头像</small>
            </div>
            <button type="submit" class="btn btn-primary">保存</button>
          </form>
          <hr class="my-4">
          <header class="card-header mb-3" style="background:transparent;padding:0;border:none;"><div class="card-title">账号信息</div></header>
          <p class="text-muted small mb-3">管理管理员登录用户名与密码</p>
          <form method="POST" action="profile.php" class="site-form mb-4">
            <input type="hidden" name="form_type" value="username">
            <div class="mb-3">
              <label for="new_username">管理员用户名</label>
              <input type="text" class="form-control" name="new_username" id="new_username" value="<?php echo $username; ?>" pattern="[A-Za-z0-9_]{2,32}" title="2-32 位字母、数字或下划线" required>
              <small class="text-muted">2-32 位字母、数字或下划线。修改后需使用新用户名重新登录。</small>
            </div>
            <div class="mb-3">
              <label for="current_password_for_username">当前密码（验证身份）</label>
              <input type="password" class="form-control" name="current_password_for_username" id="current_password_for_username" required>
            </div>
            <button type="submit" class="btn btn-primary"><i class="mdi mdi-account-edit-outline"></i> 更新用户名</button>
          </form>
          <form method="POST" action="profile.php" class="site-form mb-4">
            <input type="hidden" name="form_type" value="password">
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
          <hr class="my-4">
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
