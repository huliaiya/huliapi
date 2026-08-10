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
    $pdo->exec("CREATE TABLE IF NOT EXISTS `sl_billing_plans` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `description` TEXT NULL,
        `price` DECIMAL(10, 2) NOT NULL,
        `billing_type` ENUM('balance', 'points', 'membership') NOT NULL DEFAULT 'balance',
        `balance_to_add` DECIMAL(10, 2) NOT NULL DEFAULT 0,
        `points_to_add` INT NOT NULL DEFAULT 0,
        `membership_days` INT NOT NULL DEFAULT 0,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `is_card` TINYINT(1) NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    $columns = $pdo->query("SHOW COLUMNS FROM `sl_billing_plans`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('membership_days', $columns)) {
        $pdo->exec("ALTER TABLE `sl_billing_plans` ADD `membership_days` INT NOT NULL DEFAULT 0 AFTER `points_to_add`;");
    }
    if (isset($_GET['action']) && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        switch ($_GET['action']) {
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM sl_billing_plans WHERE id = ?"); 
                $stmt->execute([$id]);
                $_SESSION['feedback_msg'] = '计费方案已成功删除。'; 
                break;
            case 'toggle':
                $stmt = $pdo->prepare("UPDATE sl_billing_plans SET is_active = 1 - is_active WHERE id = ?"); 
                $stmt->execute([$id]);
                $_SESSION['feedback_msg'] = '方案状态已成功切换。'; 
                break;
            case 'toggle_card':
                $stmt = $pdo->prepare("UPDATE sl_billing_plans SET is_card = 1 - is_card WHERE id = ?"); 
                $stmt->execute([$id]);
                $_SESSION['feedback_msg'] = '卡密属性已成功切换。'; 
                break;
        }
        $_SESSION['feedback_type'] = 'success';
        header('Location: billing_plans.php'); 
        exit;
    }
    if(isset($_SESSION['feedback_msg'])){
        $feedback_msg = $_SESSION['feedback_msg']; 
        $feedback_type = $_SESSION['feedback_type'];
        unset($_SESSION['feedback_msg'], $_SESSION['feedback_type']);
    }
    $plans = $pdo->query("SELECT * FROM sl_billing_plans ORDER BY price ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { 
    $feedback_msg = '数据库操作失败: ' . $e->getMessage(); 
    $feedback_type = 'error'; 
    $plans = []; 
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
        .badge-balance { background-color: #d1fae5; color: #065f46; }
        .badge-points { background-color: #dbeafe; color: #1e40af; }
        .badge-card { background-color: #fef3c7; color: #92400e; }
        .badge-normal { background-color: #eff6ff; color: #2563eb; }
    </style>
</head>
<body>
<div class="container-fluid">
  <div class="row">
    <div class="col-lg-12">
      <div class="card">
        <header class="card-header">
            <div class="card-title">计费方案管理</div>
            <div class="card-actions">
                <a href="billing_plan_edit.php" class="btn btn-primary btn-sm"><i class="mdi mdi-plus"></i> 添加新方案</a>
            </div>
        </header>
        <div class="card-body">
          <?php if ($feedback_msg): ?>
          <div class="alert alert-<?php echo $feedback_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show mb-3">
            <?php echo htmlspecialchars($feedback_msg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
          <?php endif; ?>
          <div class="table-responsive">
            <table class="table table-bordered">
              <thead>
                <tr>
                  <th width="50">ID</th>
                  <th>方案名称</th>
                  <th>售价</th>
                  <th>计费类型</th>
                  <th>获得数量</th>
                  <th>类型</th>
                  <th width="100">状态</th>
                  <th width="220">操作</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($plans)): ?>
                <tr>
                    <td colspan="8" class="text-center py-4 text-muted">暂无计费方案，请先添加一个。</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($plans as $plan): ?>
                    <tr>
                        <td><?php echo $plan['id']; ?></td>
                        <td><?php echo htmlspecialchars($plan['name']); ?></td>
                        <td>¥ <?php echo number_format($plan['price'], 2); ?></td>
                        <td>
                            <?php if ($plan['billing_type'] === 'balance'): ?>
                                <span class="badge badge-balance">余额方案</span>
                            <?php elseif ($plan['billing_type'] === 'points'): ?>
                                <span class="badge badge-points">点数方案</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">会员方案</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($plan['billing_type'] === 'balance'): ?>
                                ¥ <?php echo number_format($plan['balance_to_add'], 2); ?>
                            <?php elseif ($plan['billing_type'] === 'points'): ?>
                                <?php echo number_format($plan['points_to_add']); ?> 点
                            <?php else: ?>
                                <?php echo number_format($plan['membership_days']); ?> 天
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($plan['is_card']): ?>
                                <span class="badge badge-card">卡密方案</span>
                            <?php else: ?>
                                <span class="badge badge-normal">直接充值</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($plan['is_active']): ?>
                                <span class="badge bg-success">上架</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">下架</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="billing_plan_edit.php?id=<?php echo $plan['id']; ?>" class="btn btn-default" data-bs-toggle="tooltip" title="编辑">
                                    <i class="mdi mdi-pencil"></i>
                                </a>
                                <a href="?action=toggle&id=<?php echo $plan['id']; ?>" class="btn btn-default" data-bs-toggle="tooltip" title="<?php echo $plan['is_active'] ? '下架' : '上架'; ?>">
                                    <i class="mdi mdi-<?php echo $plan['is_active'] ? 'eye-off' : 'eye'; ?>"></i>
                                </a>
                                <a href="?action=toggle_card&id=<?php echo $plan['id']; ?>" class="btn btn-default" data-bs-toggle="tooltip" title="<?php echo $plan['is_card'] ? '切换为直接充值' : '切换为卡密方案'; ?>">
                                    <i class="mdi mdi-<?php echo $plan['is_card'] ? 'credit-card-off' : 'credit-card'; ?>"></i>
                                </a>
                                <a href="?action=delete&id=<?php echo $plan['id']; ?>" class="btn btn-default" data-bs-toggle="tooltip" title="删除" onclick="return confirm('确定要删除这个方案吗？');">
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
          <?php if (!empty($plans)): ?>
          <ul class="pagination mt-3">
            <li class="page-item disabled"><span class="page-link">上一页</span></li>
            <li class="page-item active"><span class="page-link">1</span></li>
            <li class="page-item"><a class="page-link" href="#1">2</a></li>
            <li class="page-item"><a class="page-link" href="#1">3</a></li>
            <li class="page-item"><a class="page-link" href="#1">下一页</a></li>
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