<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
if (file_exists('../config.php')) { require_once '../config.php'; } else { die("出现错误！配置文件丢失。"); }
$username = htmlspecialchars($_SESSION['admin_username']);
$feedback_msg = ''; $feedback_type = '';
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $columns = $pdo->query("SHOW COLUMNS FROM `sl_users`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('expires_at', $columns)) $pdo->exec("ALTER TABLE `sl_users` ADD `expires_at` TIMESTAMP NULL DEFAULT NULL AFTER `status`;");
    if (!in_array('call_limit', $columns)) $pdo->exec("ALTER TABLE `sl_users` ADD `call_limit` INT NULL DEFAULT NULL AFTER `expires_at`;");
    if (isset($_GET['action'])) {
        if ($_GET['action'] === 'cleanup') {
            $stmt = $pdo->prepare("DELETE FROM sl_users WHERE username LIKE 'temp_%' AND (expires_at < NOW() OR call_limit <= 0)");
            $deleted_count = $stmt->execute() ? $stmt->rowCount() : 0;
            $_SESSION['feedback_msg'] = "成功清理了 {$deleted_count} 个过期或无效的临时密钥。";
        } else if (isset($_GET['id'])) {
            $id = intval($_GET['id']);
            switch ($_GET['action']) {
                case 'delete':
                    $stmt = $pdo->prepare("DELETE FROM sl_users WHERE id = ? AND username LIKE 'temp_%'"); $stmt->execute([$id]);
                    $_SESSION['feedback_msg'] = '临时密钥已成功删除。'; break;
                case 'ban':
                    $stmt = $pdo->prepare("UPDATE sl_users SET status = 'banned' WHERE id = ? AND username LIKE 'temp_%'"); $stmt->execute([$id]);
                    $_SESSION['feedback_msg'] = '临时密钥已成功封禁。'; break;
                case 'unban':
                    $stmt = $pdo->prepare("UPDATE sl_users SET status = 'active' WHERE id = ? AND username LIKE 'temp_%'"); $stmt->execute([$id]);
                    $_SESSION['feedback_msg'] = '临时密钥已成功解封。'; break;
            }
        }
        $_SESSION['feedback_type'] = 'success';
        header('Location: temp_keys.php'); exit;
    }
    if(isset($_SESSION['feedback_msg'])){
        $feedback_msg = $_SESSION['feedback_msg']; $feedback_type = $_SESSION['feedback_type'];
        unset($_SESSION['feedback_msg'], $_SESSION['feedback_type']);
    }
    $keys = $pdo->query("SELECT * FROM sl_users WHERE username LIKE 'temp_%' ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $feedback_msg = '数据库操作失败: ' . $e->getMessage(); $feedback_type = 'error'; $keys = []; }
$current_page = basename($_SERVER['PHP_SELF']);
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
        <header class="card-header">
            <div class="card-title">临时密钥管理</div>
<div class="card-action">
  <a href="?action=cleanup" onclick="return confirm('确定要清理所有已过期或已用尽的临时密钥吗？');" class="btn btn-danger btn-sm">一键清理过期密钥</a>
</div>
        </header>
        <div class="card-body">
          <?php if ($feedback_msg): ?>
          <div class="alert alert-<?php echo $feedback_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
              <?php echo htmlspecialchars($feedback_msg); ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
          <?php endif; ?>
          <div class="table-responsive">
            <table class="table table-bordered">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>API Key</th>
                  <th>剩余次数</th>
                  <th>到期时间</th>
                  <th>状态</th>
                  <th>创建时间</th>
                  <th>操作</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($keys)): ?>
                <tr>
                    <td colspan="7" style="text-align:center; padding: 40px; color: #6c757d;">暂无临时密钥数据。</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($keys as $key): ?>
                    <tr>
                        <td><?php echo $key['id']; ?></td>
                        <td><code><?php echo htmlspecialchars($key['api_key']); ?></code></td>
                        <td><?php echo $key['call_limit'] !== null ? number_format($key['call_limit']) : '无限'; ?></td>
                        <td><?php echo $key['expires_at'] ?: '永不'; ?></td>
                        <td>
                            <span class="badge <?php 
                                echo $key['status'] === 'active' ? 'badge-green' : 
                                    ($key['status'] === 'banned' ? 'badge-red' : 'badge-yellow'); ?>">
                                <?php echo htmlspecialchars($key['status']); ?>
                            </span>
                        </td>
                        <td><?php echo $key['created_at']; ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a class="btn btn-default" href="temp_key_edit.php?id=<?php echo $key['id']; ?>" data-bs-toggle="tooltip" title="编辑">
                                    <i class="mdi mdi-pencil"></i>
                                </a>
                                <?php if ($key['status'] === 'active'): ?>
                                    <a class="btn btn-default" href="?action=ban&id=<?php echo $key['id']; ?>" data-bs-toggle="tooltip" title="封禁" style="color: #f59e0b;">
                                        <i class="mdi mdi-block-helper"></i>
                                    </a>
                                <?php else: ?>
                                    <a class="btn btn-default" href="?action=unban&id=<?php echo $key['id']; ?>" data-bs-toggle="tooltip" title="解封" style="color: #16a34a;">
                                        <i class="mdi mdi-check"></i>
                                    </a>
                                <?php endif; ?>
                                <a class="btn btn-default" href="?action=delete&id=<?php echo $key['id']; ?>" onclick="return confirm('确定要删除这个临时密钥吗？');" data-bs-toggle="tooltip" title="删除" style="color: #dc2626;">
                                    <i class="mdi mdi-delete"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <ul class="pagination">
            <li class="page-item disabled"><span class="page-link">上一页</span></li>
            <li class="page-item active"><span class="page-link">1</span></li>
            <li class="page-item"><a class="page-link" href="#1">2</a></li>
            <li class="page-item"><a class="page-link" href="#1">3</a></li>
            <li class="page-item"><a class="page-link" href="#1">4</a></li>
            <li class="page-item"><a class="page-link" href="#1">5</a></li>
            <li class="page-item"><a class="page-link" href="#!">下一页</a></li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>
<script type="text/javascript" src="../assets/js/jquery.min.js"></script>
<script type="text/javascript" src="../assets/js/popper.min.js"></script>
<script type="text/javascript" src="../assets/js/bootstrap.min.js"></script>
<script type="text/javascript" src="../assets/js/main.min.js"></script>
<script>
$(document).ready(function() {
    $('[data-bs-toggle="tooltip"]').tooltip();
    <?php if ($feedback_msg): ?>
    setTimeout(function() {
        $('.alert').alert('close');
    }, 3000);
    <?php endif; ?>
});
</script>
</body>
</html>