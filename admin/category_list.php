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
    if (isset($_GET['action'])) {
        if ($_GET['action'] === 'delete' && isset($_GET['id'])) {
            $id_to_delete = intval($_GET['id']);
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM sl_apis WHERE category_id = ?");
            $stmt_check->execute([$id_to_delete]);
            $api_count = $stmt_check->fetchColumn();
            if ($api_count > 0) {
                throw new Exception('无法删除此分类，因为有 '.$api_count.' 个API正在使用它。请先修改这些API的分类。');
            }
            $stmt_delete = $pdo->prepare("DELETE FROM sl_api_categories WHERE id = ?");
            $stmt_delete->execute([$id_to_delete]);
            $_SESSION['feedback_msg'] = '分类已成功删除。';
            $_SESSION['feedback_type'] = 'success';
            header('Location: category_list.php');
            exit;
        }
    }
    if(isset($_SESSION['feedback_msg'])){
        $feedback_msg = $_SESSION['feedback_msg'];
        $feedback_type = $_SESSION['feedback_type'];
        unset($_SESSION['feedback_msg'], $_SESSION['feedback_type']);
    }
    $stmt_list = $pdo->query("SELECT c.*, COUNT(a.id) as api_count 
                             FROM sl_api_categories c 
                             LEFT JOIN sl_apis a ON a.category_id = c.id 
                             GROUP BY c.id 
                             ORDER BY c.id DESC");
    $categories = $stmt_list->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $feedback_msg = '操作失败: ' . $e->getMessage();
    $feedback_type = 'error';
    $categories = [];
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
</head>
<body>
<div class="container-fluid">
    <div class="card">
        <header class="card-header">
            <div class="card-title">API 分类管理</div>
            <div class="card-action">
                <a href="category_edit.php" class="btn btn-primary btn-sm"><i class="mdi mdi-plus"></i> 添加新分类</a>
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
                            <th>分类名称</th>
                            <th>描述</th>
                            <th width="100">API数量</th>
                            <th width="120">创建时间</th>
                            <th width="120">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($categories)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="mdi mdi-information-outline me-1"></i> 暂无分类，请先添加一个
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($categories as $cat): ?>
                            <tr>
                                <td><?php echo $cat['id']; ?></td>
                                <td><?php echo htmlspecialchars($cat['name']); ?></td>
                                <td><?php echo htmlspecialchars($cat['description']); ?></td>
                                <td><?php echo $cat['api_count']; ?></td>
                                <td><?php echo date('Y-m-d', strtotime($cat['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="category_edit.php?id=<?php echo $cat['id']; ?>" class="btn btn-default" data-bs-toggle="tooltip" title="编辑">
                                            <i class="mdi mdi-pencil"></i>
                                        </a>
                                        <a href="category_list.php?action=delete&id=<?php echo $cat['id']; ?>" 
                                           class="btn btn-default" 
                                           data-bs-toggle="tooltip" 
                                           title="删除"
                                           onclick="return confirm('确定要删除这个分类吗？');">
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
        </div>
    </div>
</div>
<script type="text/javascript" src="../assets/js/jquery.min.js"></script>
<script type="text/javascript" src="../assets/js/popper.min.js"></script>
<script type="text/javascript" src="../assets/js/bootstrap.min.js"></script>
<script>
$(document).ready(function() {
    $('[data-bs-toggle="tooltip"]').tooltip();
    <?php if ($feedback_msg): ?>
    setTimeout(function() {
        $('.alert').fadeTo(500, 0).slideUp(500, function(){
            $(this).remove(); 
        });
    }, 3000);
    <?php endif; ?>
});
</script>
</body>
</html>