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
$advertisement = null;
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    die("参数错误：广告ID不存在");
}
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->prepare("SELECT * FROM sl_advertisements WHERE id = ?");
    $stmt->execute([$id]);
    $advertisement = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$advertisement) {
        die("未找到该广告信息");
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
        $deleteStmt = $pdo->prepare("DELETE FROM sl_advertisements WHERE id = ?");
        $deleteStmt->execute([$id]);
        if ($deleteStmt->rowCount() > 0) {
            $feedback_msg = "广告删除成功！";
            $feedback_type = "success";
            header("Refresh: 2; URL=advertisements.php");
        } else {
            $feedback_msg = "广告删除失败，请重试";
            $feedback_type = "error";
        }
    }
} catch (Exception $e) {
    $feedback_msg = $e->getMessage();
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
.delete-confirm-box {
    max-width: 600px;
    margin: 2rem auto;
    padding: 2rem;
    border: 1px solid #e9ecef;
    border-radius: 0.5rem;
    background: #fff;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}
.ad-preview {
    width: 100%;
    max-width: 300px;
    height: auto;
    border-radius: 0.5rem;
    margin: 1rem 0;
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
                        <i class="mdi mdi-delete-alert me-2"></i>删除广告
                    </div>
                    <div class="card-action">
                        <a href="advertisements.php" class="btn btn-outline-secondary btn-sm">
                            <i class="mdi mdi-arrow-left"></i> 返回列表
                        </a>
                    </div>
                </header>
                <div class="card-body">
                    <?php if ($feedback_msg): ?>
                    <div class="alert alert-<?= $feedback_type === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show mb-3">
                        <?= htmlspecialchars($feedback_msg) ?>
                        <?php if ($feedback_type === 'success'): ?>
                            <div class="mt-2">页面将在2秒后自动跳转...</div>
                        <?php endif; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    <?php if ($advertisement && $feedback_type !== 'success'): ?>
                    <div class="delete-confirm-box text-center">
                        <div class="mb-4">
                            <i class="mdi mdi-alert-circle-outline text-danger" style="font-size: 4rem;"></i>
                        </div>
                        <h4 class="text-danger mb-4">确认删除广告？</h4>
                        <div class="mb-4">
                            <?php if (!empty($advertisement['image_url'])): ?>
                                <img src="<?= htmlspecialchars($advertisement['image_url']) ?>" 
                                     class="ad-preview" 
                                     alt="广告预览" 
                                     onError="this.style.display='none'">
                            <?php endif; ?>
                            <h5 class="mt-3"><?= htmlspecialchars($advertisement['title']) ?></h5>
                            <p class="text-muted"><?= htmlspecialchars($advertisement['description'] ?? '无描述') ?></p>
                            <p class="text-muted small">链接: <?= htmlspecialchars($advertisement['link_url']) ?></p>
                        </div>
                        <p class="text-muted mb-4">
                            此操作不可逆，请确认您真的要删除这个广告。
                        </p>
                        <form method="post">
                            <div class="d-flex justify-content-center gap-3">
                                <button type="submit" name="confirm_delete" class="btn btn-danger">
                                    <i class="mdi mdi-delete"></i> 确认删除
                                </button>
                                <a href="advertisements.php" class="btn btn-outline-secondary">
                                    <i class="mdi mdi-cancel"></i> 取消
                                </a>
                            </div>
                        </form>
                    </div>
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
</body>
</html>