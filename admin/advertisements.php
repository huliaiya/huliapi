<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (!isset($_SESSION['admin_id'])) { 
    header('Location: login.php'); 
    exit; 
}
if (file_exists('../config.php')) { 
    require_once '../config.php'; 
} else { 
    die("出现错误！配置文件丢失。"); 
}
$username = htmlspecialchars($_SESSION['admin_username']);
$feedback_msg = '';
$feedback_type = '';
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if (isset($_GET['delete'])) {
        $id = intval($_GET['delete']);
        $stmt = $pdo->prepare("DELETE FROM sl_advertisements WHERE id = ?");
        $stmt->execute([$id]);
        $feedback_msg = "广告删除成功！";
        $feedback_type = "success";
    }
    if (isset($_GET['toggle_status'])) {
        $id = intval($_GET['toggle_status']);
        $stmt = $pdo->prepare("UPDATE sl_advertisements SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ?");
        $stmt->execute([$id]);
        $feedback_msg = "广告状态已更新！";
        $feedback_type = "success";
    }
    $stmt = $pdo->query("SELECT * FROM sl_advertisements ORDER BY sort_order DESC, created_at DESC");
    $ads = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $feedback_msg = "数据库错误: " . $e->getMessage();
    $feedback_type = "error";
}
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
<style>
.status-badge {
    font-size: 0.8rem;
    padding: 0.3rem 0.6rem;
}
</style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <header class="card-header">
                    <div class="card-title">
                        <i class="mdi mdi-advertisements me-2"></i>广告位管理
                    </div>
                    <div class="card-action">
                        <a href="add_advertisement.php" class="btn btn-primary btn-sm">
                            <i class="mdi mdi-plus"></i> 添加广告
                        </a>
                    </div>
                </header>
                <div class="card-body">
                    <?php if ($feedback_msg): ?>
                    <div class="alert alert-<?= $feedback_type === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show mb-3">
                        <?= htmlspecialchars($feedback_msg) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>标题</th>
                                    <th>链接</th>
                                    <th>站长联系方式</th>
                                    <th width="80">排序</th>
                                    <th width="100">状态</th>
                                    <th width="150">创建时间</th>
                                    <th width="150">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($ads)): ?>
                                    <?php foreach ($ads as $ad): ?>
                                    <tr>
                                        <td><?= $ad['id'] ?></td>
                                        <td><?= htmlspecialchars($ad['title']) ?></td>
                                        <td>
                                            <a href="<?= htmlspecialchars($ad['link_url']) ?>" target="_blank" class="text-truncate d-inline-block" style="max-width: 200px;">
                                                <?= htmlspecialchars($ad['link_url']) ?>
                                            </a>
                                        </td>
                                        <td><?= htmlspecialchars($ad['contact']) ?></td>
                                        <td><?= $ad['sort_order'] ?></td>
                                        <td>
                                            <span class="badge status-badge bg-<?= $ad['status'] === 'active' ? 'success' : 'secondary' ?>">
                                                <?= $ad['status'] === 'active' ? '启用' : '禁用' ?>
                                            </span>
                                        </td>
                                        <td><?= date('Y-m-d H:i', strtotime($ad['created_at'])) ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="edit_advertisement.php?id=<?= $ad['id'] ?>" class="btn btn-outline-primary">
                                                    <i class="mdi mdi-pencil"></i>
                                                </a>
                                                <a href="?toggle_status=<?= $ad['id'] ?>" class="btn btn-outline-<?= $ad['status'] === 'active' ? 'warning' : 'success' ?>">
                                                    <i class="mdi mdi-<?= $ad['status'] === 'active' ? 'eye-off' : 'eye' ?>"></i>
                                                </a>
                                                <a href="?delete=<?= $ad['id'] ?>" class="btn btn-outline-danger" onclick="return confirm('确定要删除这个广告吗？此操作不可恢复。')">
                                                    <i class="mdi mdi-delete"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">
                                            <i class="mdi mdi-information-outline me-2"></i>暂无广告数据
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
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