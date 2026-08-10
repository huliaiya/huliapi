<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
if (file_exists('../config.php')) { require_once '../config.php'; } else { die("出现错误！配置文件丢失。"); }
$admin_id = $_SESSION['admin_id'];
$username = htmlspecialchars($_SESSION['admin_username']);
$feedback_msg = ''; $feedback_type = ''; $page_title = '添加新分类';
$category = ['id' => null, 'name' => '', 'description' => ''];
$edit_mode = isset($_GET['id']);
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if ($edit_mode) {
        $page_title = '编辑分类';
        $id_to_edit = intval($_GET['id']);
        $stmt_get = $pdo->prepare("SELECT * FROM sl_api_categories WHERE id = ?"); 
        $stmt_get->execute([$id_to_edit]);
        $category = $stmt_get->fetch(PDO::FETCH_ASSOC);
        if (!$category) { header('Location: category_list.php'); exit; }
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $category_id = isset($_POST['id']) ? intval($_POST['id']) : null;
        $name = trim($_POST['name']);
        if (empty($name)) { 
            throw new Exception('分类名称不能为空。'); 
        }
        if ($category_id) {
            $sql = "UPDATE sl_api_categories SET name=?, description=? WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, trim($_POST['description']), $category_id]);
            $_SESSION['feedback_msg'] = '分类已成功更新。';
        } else {
            $sql = "INSERT INTO sl_api_categories (name, description) VALUES (?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, trim($_POST['description'])]);
            $_SESSION['feedback_msg'] = '分类已成功添加。';
        }
        $_SESSION['feedback_type'] = 'success';
        header('Location: category_list.php');
        exit;
    }
} catch (Exception $e) { 
    $feedback_msg = '操作失败: ' . $e->getMessage(); 
    $feedback_type = 'error'; 
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
            <div class="card-title"><?php echo $page_title; ?></div>
        </header>
        <div class="card-body">
            <?php if ($feedback_msg): ?>
            <div class="alert alert-<?php echo $feedback_type === 'error' ? 'danger' : 'success'; ?> mb-3">
                <?php echo htmlspecialchars($feedback_msg); ?>
            </div>
            <?php endif; ?>
            <form method="POST" action="category_edit.php<?php echo $edit_mode ? '?id='.$category['id'] : ''; ?>">
                <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
                <div class="mb-3">
                    <label for="name" class="form-label">分类名称</label>
                    <input type="text" id="name" name="name" class="form-control" 
                           placeholder="例如：用户接口" value="<?php echo htmlspecialchars($category['name']); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="description" class="form-label">分类描述</label>
                    <textarea id="description" name="description" class="form-control" rows="3"
                              placeholder="简单描述这个分类的用途"><?php echo htmlspecialchars($category['description']); ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary w-100"><?php echo $edit_mode ? '更新分类' : '立即添加'; ?></button>
            </form>
        </div>
    </div>
</div>
<script type="text/javascript" src="../assets/js/jquery.min.js"></script>
<script type="text/javascript" src="../assets/js/bootstrap.min.js"></script>
</body>
</html>