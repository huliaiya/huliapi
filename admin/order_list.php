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
    $pdo->exec("CREATE TABLE IF NOT EXISTS `sl_orders` (
        `id` INT AUTO_INCREMENT PRIMARY KEY, `order_id` VARCHAR(64) NOT NULL UNIQUE, `user_id` INT NOT NULL, `plan_id` INT NOT NULL,
        `amount` DECIMAL(10, 2) NOT NULL, `status` ENUM('pending', 'paid', 'failed') NOT NULL DEFAULT 'pending',
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, `paid_at` TIMESTAMP NULL DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    if (isset($_GET['action']) && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        switch ($_GET['action']) {
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM sl_orders WHERE id = ?"); $stmt->execute([$id]);
                $_SESSION['feedback_msg'] = '订单已成功删除。'; $_SESSION['feedback_type'] = 'success';
                break;
            case 'mark_failed':
                $stmt = $pdo->prepare("UPDATE sl_orders SET status = 'failed' WHERE id = ? AND status = 'pending'"); $stmt->execute([$id]);
                $_SESSION['feedback_msg'] = '订单已成功标记为失败。'; $_SESSION['feedback_type'] = 'success';
                break;
            case 'mark_paid':
                $pdo->beginTransaction();
                $stmt_order = $pdo->prepare("SELECT * FROM sl_orders WHERE id = ? AND status = 'pending' FOR UPDATE");
                $stmt_order->execute([$id]); $order = $stmt_order->fetch(PDO::FETCH_ASSOC);
                if ($order) {
                    $stmt_plan = $pdo->prepare("SELECT balance_to_add FROM sl_billing_plans WHERE id = ?");
                    $stmt_plan->execute([$order['plan_id']]); $balance_to_add = $stmt_plan->fetchColumn();
                    if ($balance_to_add) {
                        $stmt_update_user = $pdo->prepare("UPDATE sl_users SET balance = balance + ? WHERE id = ?");
                        $stmt_update_user->execute([$balance_to_add, $order['user_id']]);
                        $stmt_update_order = $pdo->prepare("UPDATE sl_orders SET status = 'paid', paid_at = CURRENT_TIMESTAMP WHERE id = ?");
                        $stmt_update_order->execute([$order['id']]);
                        $pdo->commit();
                        $_SESSION['feedback_msg'] = '订单已成功标记为已支付，并已为用户增加余额。'; $_SESSION['feedback_type'] = 'success';
                    } else { $pdo->rollBack(); throw new Exception('找不到对应的计费方案。'); }
                } else { $pdo->rollBack(); throw new Exception('订单不存在或已处理。'); }
                break;
        }
        header('Location: order_list.php'); exit;
    }
    if(isset($_SESSION['feedback_msg'])){
        $feedback_msg = $_SESSION['feedback_msg']; $feedback_type = $_SESSION['feedback_type'];
        unset($_SESSION['feedback_msg'], $_SESSION['feedback_type']);
    }
    $results_per_page = 15;
    $total_results = $pdo->query("SELECT count(*) FROM sl_orders")->fetchColumn();
    $total_pages = ceil($total_results / $results_per_page);
    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
    $page = max(1, min($page, $total_pages));
    $offset = ($page - 1) * $results_per_page;
    $stmt_list = $pdo->prepare("SELECT o.*, u.username, p.name as plan_name FROM sl_orders o LEFT JOIN sl_users u ON o.user_id = u.id LEFT JOIN sl_billing_plans p ON o.plan_id = p.id ORDER BY o.created_at DESC LIMIT :limit OFFSET :offset");
    $stmt_list->bindValue(':limit', $results_per_page, PDO::PARAM_INT);
    $stmt_list->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt_list->execute();
    $orders = $stmt_list->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $feedback_msg = '数据库操作失败: ' . $e->getMessage(); $feedback_type = 'error'; $orders = []; }

function getOrderStatusBadge($status) {
    switch ($status) {
        case 'paid': return '<span class="badge badge-green">已支付</span>';
        case 'pending': return '<span class="badge badge-yellow">待支付</span>';
        case 'failed': return '<span class="badge badge-red">失败</span>';
        default: return '<span class="badge badge-gray">未知</span>';
    }
}
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
    <style>
        .badge-pending { background-color: #fef3c7; color: #92400e; }
        .badge-paid { background-color: #d1fae5; color: #065f46; }
        .badge-failed { background-color: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
<div class="container-fluid">
  <div class="row">
    <div class="col-lg-12">
      <div class="card">
        <header class="card-header">
            <div class="card-title">订单列表</div>
        </header>
        <div class="card-body">
          <?php if ($feedback_msg): ?>
          <div class="alert alert-<?php echo $feedback_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show mb-3">
              <?php echo htmlspecialchars($feedback_msg); ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
          <?php endif; ?>
          <div class="card-search mb-3">
            <form class="search-form" method="get" action="order_list.php" role="form">
              <div class="row">
                <div class="col-md-4">
                  <div class="row">
                    <label class="col-sm-4 col-form-label">订单号</label>
                    <div class="col-sm-8">
                      <input type="text" class="form-control" name="order_id" value="<?php echo htmlspecialchars($_GET['order_id'] ?? ''); ?>" placeholder="请输入订单号" />
                    </div>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="row">
                    <label class="col-sm-4 col-form-label">用户名</label>
                    <div class="col-sm-8">
                      <input type="text" class="form-control" name="username" value="<?php echo htmlspecialchars($_GET['username'] ?? ''); ?>" placeholder="请输入用户名" />
                    </div>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="row">
                    <label class="col-sm-4 col-form-label">状态</label>
                    <div class="col-sm-8">
                      <select name="status" class="form-select">
                        <option value="">全部</option>
                        <option value="pending" <?php echo ($_GET['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>待支付</option>
                        <option value="paid" <?php echo ($_GET['status'] ?? '') === 'paid' ? 'selected' : ''; ?>>已支付</option>
                        <option value="failed" <?php echo ($_GET['status'] ?? '') === 'failed' ? 'selected' : ''; ?>>已失败</option>
                      </select>
                    </div>
                  </div>
                </div>
              </div>
              <div class="row mt-2">
                <div class="col-md-4">
                  <div class="row">
                    <label class="col-sm-4 col-form-label">开始日期</label>
                    <div class="col-sm-8">
                      <input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($_GET['start_date'] ?? ''); ?>" />
                    </div>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="row">
                    <label class="col-sm-4 col-form-label">结束日期</label>
                    <div class="col-sm-8">
                      <input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($_GET['end_date'] ?? ''); ?>" />
                    </div>
                  </div>
                </div>
                <div class="col-md-4">
                  <button type="submit" class="btn btn-primary me-1">搜索</button>
                  <button type="reset" class="btn btn-default">重置</button>
                </div>
              </div>
            </form>
          </div>
          <div class="table-responsive">
            <table class="table table-bordered">
              <thead>
                <tr>
                  <th>编号</th>
                  <th>订单号</th>
                  <th>用户</th>
                  <th>购买方案</th>
                  <th>金额</th>
                  <th>状态</th>
                  <th>创建时间</th>
                  <th>操作</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($orders)): ?>
                <tr>
                  <td colspan="8" class="text-center py-4 text-muted">暂无订单数据</td>
                </tr>
                <?php else: ?>
                  <?php foreach ($orders as $index => $order): ?>
                  <tr>
                    <td><?php echo ($page - 1) * $results_per_page + $index + 1; ?></td>
                    <td><code><?php echo htmlspecialchars($order['order_id']); ?></code></td>
                    <td><?php echo htmlspecialchars($order['username'] ?: '未知用户'); ?></td>
                    <td><?php echo htmlspecialchars($order['plan_name'] ?: '未知方案'); ?></td>
                    <td>¥ <?php echo number_format($order['amount'], 2); ?></td>
                    <td>
                      <?php if ($order['status'] === 'pending'): ?>
                        <span class="badge badge-pending">待支付</span>
                      <?php elseif ($order['status'] === 'paid'): ?>
                        <span class="badge badge-paid">已支付</span>
                      <?php else: ?>
                        <span class="badge badge-failed">已失败</span>
                      <?php endif; ?>
                    </td>
                    <td><?php echo $order['created_at']; ?></td>
                    <td>
                      <div class="btn-group btn-group-sm">
                        <?php if ($order['status'] === 'pending'): ?>
                          <a class="btn btn-success" href="?action=mark_paid&id=<?php echo $order['id']; ?>" onclick="return confirm('确定要手动将此订单标记为已支付吗？系统将为用户增加相应余额。')" data-bs-toggle="tooltip" title="标记为已支付">
                            <i class="mdi mdi-check"></i>
                          </a>
                          <a class="btn btn-warning" href="?action=mark_failed&id=<?php echo $order['id']; ?>" onclick="return confirm('确定要将此订单标记为失败吗？')" data-bs-toggle="tooltip" title="标记为失败">
                            <i class="mdi mdi-close"></i>
                          </a>
                        <?php endif; ?>
                        <a class="btn btn-danger" href="?action=delete&id=<?php echo $order['id']; ?>" onclick="return confirm('确定要删除这个订单吗？此操作不可恢复。')" data-bs-toggle="tooltip" title="删除">
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
                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">上一页</a>
              </li>
            <?php else: ?>
              <li class="page-item disabled">
                <span class="page-link">上一页</span>
              </li>
            <?php endif; ?>
            <?php 
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            if ($start_page > 1): ?>
              <li class="page-item">
                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">1</a>
              </li>
              <?php if ($start_page > 2): ?>
                <li class="page-item disabled">
                  <span class="page-link">...</span>
                </li>
              <?php endif; ?>
            <?php endif; ?>
            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
              <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                <?php if ($i == $page): ?>
                  <span class="page-link"><?php echo $i; ?></span>
                <?php else: ?>
                  <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                <?php endif; ?>
              </li>
            <?php endfor; ?>
            <?php if ($end_page < $total_pages): ?>
              <?php if ($end_page < $total_pages - 1): ?>
                <li class="page-item disabled">
                  <span class="page-link">...</span>
                </li>
              <?php endif; ?>
              <li class="page-item">
                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>"><?php echo $total_pages; ?></a>
              </li>
            <?php endif; ?>
            <?php if ($page < $total_pages): ?>
              <li class="page-item">
                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">下一页</a>
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