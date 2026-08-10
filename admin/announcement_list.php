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
    if (isset($_GET['action']) && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        switch ($_GET['action']) {
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM sl_announcements WHERE id = ?"); $stmt->execute([$id]);
                $_SESSION['feedback_msg'] = '公告已成功删除。'; break;
            case 'toggle':
                $stmt = $pdo->prepare("UPDATE sl_announcements SET is_active = 1 - is_active WHERE id = ?"); $stmt->execute([$id]);
                $_SESSION['feedback_msg'] = '公告状态已成功切换。'; break;
        }
        $_SESSION['feedback_type'] = 'success';
        header('Location: announcement_list.php'); exit;
    }
    if(isset($_SESSION['feedback_msg'])){
        $feedback_msg = $_SESSION['feedback_msg']; $feedback_type = $_SESSION['feedback_type'];
        unset($_SESSION['feedback_msg'], $_SESSION['feedback_type']);
    }
    $results_per_page = 15;
    $total_results = $pdo->query("SELECT count(*) FROM sl_announcements")->fetchColumn();
    $total_pages = ceil($total_results / $results_per_page);
    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
    $page = max(1, min($page, $total_pages));
    $offset = ($page - 1) * $results_per_page;
    $stmt_list = $pdo->prepare("SELECT * FROM sl_announcements ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
    $stmt_list->bindValue(':limit', $results_per_page, PDO::PARAM_INT);
    $stmt_list->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt_list->execute();
    $announcements = $stmt_list->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $feedback_msg = '数据库操作失败: ' . $e->getMessage(); $feedback_type = 'error'; $announcements = []; }
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
          <div class="card-title">公告管理</div>
          <div class="card-action">
            <a href="announcement_edit.php" class="btn btn-primary btn-sm"><i class="mdi mdi-plus"></i> 添加新公告</a>
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
                  <th>标题</th>
                  <th>状态</th>
                  <th>创建时间</th>
                  <th>操作</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($announcements)): ?>
                <tr>
                  <td colspan="5" class="text-center text-muted py-4">暂无公告，请先添加一条。</td>
                </tr>
                <?php else: ?>
                  <?php foreach ($announcements as $item): ?>
                  <tr>
                    <td><?php echo $item['id']; ?></td>
                    <td><?php echo htmlspecialchars($item['title']); ?></td>
                    <td>
                      <?php if ($item['is_active']): ?>
                        <span class="badge bg-success">发布</span>
                      <?php else: ?>
                        <span class="badge bg-secondary">草稿</span>
                      <?php endif; ?>
                    </td>
                    <td><?php echo $item['created_at']; ?></td>
                    <td>
                      <div class="btn-group btn-group-sm">
                        <a href="announcement_edit.php?id=<?php echo $item['id']; ?>" class="btn btn-default" data-bs-toggle="tooltip" title="编辑">
                          <i class="mdi mdi-pencil"></i>
                        </a>
                        <a href="?action=toggle&id=<?php echo $item['id']; ?>" class="btn btn-warning" data-bs-toggle="tooltip" title="<?php echo $item['is_active'] ? '设为草稿' : '发布'; ?>">
                          <i class="mdi mdi-<?php echo $item['is_active'] ? 'eye-off' : 'eye'; ?>"></i>
                        </a>
                        <a href="?action=delete&id=<?php echo $item['id']; ?>" class="btn btn-danger" data-bs-toggle="tooltip" title="删除" onclick="return confirm('确定要删除这条公告吗？');">
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
          <?php if ($total_pages > 1): ?>
          <ul class="pagination">
            <?php if ($page > 1): ?>
              <li class="page-item">
                <a class="page-link" href="?page=<?php echo $page - 1; ?>">上一页</a>
              </li>
            <?php else: ?>
              <li class="page-item disabled">
                <span class="page-link">上一页</span>
              </li>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
              <?php if ($i == $page): ?>
                <li class="page-item active">
                  <span class="page-link"><?php echo $i; ?></span>
                </li>
              <?php else: ?>
                <li class="page-item">
                  <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                </li>
              <?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
              <li class="page-item">
                <a class="page-link" href="?page=<?php echo $page + 1; ?>">下一页</a>
              </li>
            <?php else: ?>
              <li class="page-item disabled">
                <span class="page-link">下一页</span>
              </li>
            <?php endif; ?>
          </ul>
          <?php endif; ?>
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
$(function () {
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