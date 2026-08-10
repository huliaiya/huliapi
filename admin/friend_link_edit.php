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
$link = null;
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    die("参数错误：友链ID不存在");
}
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->prepare("SELECT * FROM sl_friend_links WHERE id = ?");
    $stmt->execute([$id]);
    $link = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$link) {
        die("未找到该友链信息");
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = [
            'site_name' => trim($_POST['site_name'] ?? ''),
            'url' => trim($_POST['url'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'logo' => trim($_POST['logo'] ?? ''),
            'status' => $_POST['status'] ?? 'approved',
            'is_hidden' => isset($_POST['is_hidden']) ? 1 : 0,
            'sort_order' => intval($_POST['sort_order'] ?? 0)
        ];
        if (empty($data['site_name'])) {
            throw new Exception("网站名称不能为空");
        }
        if (mb_strlen($data['site_name']) > 50) {
            throw new Exception("网站名称不能超过50个字符");
        }
        if (empty($data['url']) || !filter_var($data['url'], FILTER_VALIDATE_URL)) {
            throw new Exception("请输入有效的URL（以http://或https://开头）");
        }
if (!empty($data['logo'])) {
    $logo_url = trim($data['logo']);
    if (!preg_match('/^https?:\/\/.+/i', $logo_url)) {
        throw new Exception("LOGO链接格式无效，请输入有效的URL");
    }
}
        if (mb_strlen($data['description']) > 200) {
            throw new Exception("网站描述不能超过200个字符");
        }
        $updateStmt = $pdo->prepare("UPDATE sl_friend_links SET 
            site_name = ?, url = ?, description = ?, logo = ?, 
            status = ?, is_hidden = ?, sort_order = ?, updated_at = NOW() 
            WHERE id = ?");
        $updateStmt->execute([
            $data['site_name'],
            $data['url'],
            $data['description'],
            $data['logo'],
            $data['status'],
            $data['is_hidden'],
            $data['sort_order'],
            $id
        ]);
        if ($updateStmt->rowCount() > 0) {
            $selectStmt = $pdo->prepare("SELECT * FROM sl_friend_links WHERE id = ?");
            $selectStmt->execute([$id]);
            $link = $selectStmt->fetch(PDO::FETCH_ASSOC);
            $feedback_msg = "友链更新成功！";
            $feedback_type = "success";
        } else {
            $feedback_msg = "友链未发生任何更改";
            $feedback_type = "info";
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
.logo-thumbnail {
    width: 80px;
    height: 80px;
    object-fit: contain;
    border: 1px solid #eee;
    border-radius: 4px;
    margin-top: 10px;
}
.form-group { margin-bottom: 1rem; }
</style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <header class="card-header">
                    <div class="card-title">
                        <i class="mdi mdi-pencil me-2"></i>修改友链
                    </div>
                    <div class="card-action">
                        <a href="friend_links.php" class="btn btn-outline-secondary btn-sm">
                            <i class="mdi mdi-arrow-left"></i> 返回列表
                        </a>
                    </div>
                </header>
                <div class="card-body">
                    <?php if ($feedback_msg): ?>
                    <div class="alert alert-<?= $feedback_type === 'success' ? 'success' : ($feedback_type === 'info' ? 'info' : 'danger') ?> alert-dismissible fade show mb-3">
                        <?= htmlspecialchars($feedback_msg) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    <?php if ($link): ?>
                    <form method="post" class="needs-validation" novalidate>
                        <input type="hidden" name="id" value="<?= $link['id'] ?>">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">网站名称 <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="site_name" 
                                       value="<?= htmlspecialchars($_POST['site_name'] ?? $link['site_name']) ?>" 
                                       placeholder="请输入网站名称" required maxlength="50">
                                <div class="invalid-feedback">请输入有效的网站名称（不超过50字符）</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">网站URL <span class="text-danger">*</span></label>
                                <input type="url" class="form-control" name="url" 
                                       value="<?= htmlspecialchars($_POST['url'] ?? $link['url']) ?>" 
                                       placeholder="请输入http://或https://开头的网址" required>
                                <div class="invalid-feedback">请输入有效的URL（以http://或https://开头）</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">LOGO链接</label>
                                <input type="url" class="form-control" name="logo" 
                                       value="<?= htmlspecialchars($_POST['logo'] ?? $link['logo']) ?>" 
                                       placeholder="请输入LOGO图片的网络链接（选填）"
                                       oninput="previewLogo(this.value)">
                                <div class="invalid-feedback">请输入有效的LOGO链接</div>
                                <div id="logo-preview" class="mt-2">
                                    <?php $logoUrl = $_POST['logo'] ?? $link['logo']; ?>
                                    <?php if ($logoUrl): ?>
                                        <img src="<?= htmlspecialchars($logoUrl) ?>" class="logo-thumbnail" 
                                             alt="LOGO预览" onError="this.src='../assets/images/default-logo.png'">
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">排序值</label>
                                <input type="number" class="form-control" name="sort_order" 
                                       value="<?= htmlspecialchars($_POST['sort_order'] ?? $link['sort_order'] ?? 0) ?>" 
                                       placeholder="数字越大越靠前，默认0" min="0" max="999">
                                <small class="text-muted">范围：0-999，数字越大排序越靠前</small>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">网站描述</label>
                                <textarea class="form-control" name="description" rows="3" 
                                          placeholder="请简要描述网站内容" maxlength="200"><?= htmlspecialchars($_POST['description'] ?? $link['description']) ?></textarea>
                                <small class="text-muted">最多200个字符</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">状态</label>
                                <select name="status" class="form-select">
                                    <option value="approved" <?= (($_POST['status'] ?? $link['status']) === 'approved') ? 'selected' : '' ?>>已通过</option>
                                    <option value="pending" <?= (($_POST['status'] ?? $link['status']) === 'pending') ? 'selected' : '' ?>>待审核</option>
                                    <option value="rejected" <?= (($_POST['status'] ?? $link['status']) === 'rejected') ? 'selected' : '' ?>>已拒绝</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">是否隐藏</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_hidden" 
                                           id="is_hidden" <?= (isset($_POST['is_hidden']) ? $_POST['is_hidden'] : $link['is_hidden']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="is_hidden">勾选后前台不显示此友链</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="mdi mdi-save"></i> 更新友链
                                </button>
                                <a href="friend_links.php" class="btn btn-outline-secondary ms-2">
                                    <i class="mdi mdi-cancel"></i> 取消
                                </a>
                            </div>
                        </div>
                    </form>
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
(function() {
    'use strict';
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
})();

function previewLogo(url) {
    const container = document.getElementById('logo-preview');
    if (url) {
        container.innerHTML = `<img src="${url}" class="logo-thumbnail" alt="LOGO预览" onError="this.src='../assets/images/default-logo.png'">`;
    } else {
        container.innerHTML = '';
    }
}
</script>
</body>
</html>