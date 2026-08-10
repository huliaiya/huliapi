<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
if (file_exists('../config.php')) { require_once '../config.php'; } else { die("出现错误！配置文件丢失。"); }
$username = htmlspecialchars($_SESSION['admin_username']);
$feedback_msg = ''; $feedback_type = ''; $page_title = '编辑临时密钥';
$key_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$key_id) { header('Location: temp_keys.php'); exit; }
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $call_limit = trim($_POST['call_limit']);
        $expires_at = trim($_POST['expires_at']);
        if (!is_numeric($call_limit)) { throw new Exception('调用次数必须是数字。'); }
        $sql = "UPDATE sl_users SET call_limit = ?, expires_at = ? WHERE id = ? AND username LIKE 'temp_%'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$call_limit, $expires_at, $key_id]);
        $_SESSION['feedback_msg'] = '临时密钥信息已成功更新。';
        $_SESSION['feedback_type'] = 'success';
        header('Location: temp_keys.php'); exit;
    }
    $stmt_get = $pdo->prepare("SELECT * FROM sl_users WHERE id = ? AND username LIKE 'temp_%'");
    $stmt_get->execute([$key_id]);
    $key_data = $stmt_get->fetch(PDO::FETCH_ASSOC);
    if (!$key_data) { header('Location: temp_keys.php'); exit; }
} catch (Exception $e) { $feedback_msg = '操作失败: ' . $e->getMessage(); $feedback_type = 'error'; }
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="zh">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <link rel="stylesheet" type="text/css" href="../assets/css/materialdesignicons.min.css">
    <link rel="stylesheet" type="text/css" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="../assets/css/style.min.css">
</head>
<body>
<div class="container-fluid">
  <div class="row">
    <div class="col-lg-12">
      <div class="card">
        <header class="card-header"><div class="card-title"><?php echo $page_title; ?></div></header>
        <div class="card-body">
          <form method="POST" action="temp_key_edit.php?id=<?php echo $key_id; ?>" class="row">
            <?php if ($feedback_msg): ?>
            <div class="mb-3 col-md-12">
              <div class="alert alert-danger"><?php echo htmlspecialchars($feedback_msg); ?></div>
            </div>
            <?php endif; ?>
            <div class="mb-3 col-md-12">
              <label for="api_key" class="form-label">API Key</label>
              <input type="text" class="form-control" id="api_key" value="<?php echo htmlspecialchars($key_data['api_key']); ?>" readonly>
            </div>
            <div class="mb-3 col-md-12">
              <label for="call_limit" class="form-label">剩余调用次数</label>
              <input type="number" class="form-control" id="call_limit" name="call_limit" value="<?php echo htmlspecialchars($key_data['call_limit']); ?>" required>
            </div>
            <div class="mb-3 col-md-12">
              <label for="expires_at" class="form-label">到期时间</label>
              <input type="datetime-local" class="form-control" id="expires_at" name="expires_at" value="<?php echo date('Y-m-d\TH:i', strtotime($key_data['expires_at'])); ?>" required>
            </div>
            <div class="mb-3 col-md-12">
              <button type="submit" class="btn btn-primary">更新密钥</button>
              <button type="button" class="btn btn-default" onclick="javascript:history.back(-1);return false;">返 回</button>
            </div>
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