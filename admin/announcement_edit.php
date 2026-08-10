<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
if (file_exists('../config.php')) { require_once '../config.php'; } else { die("出现错误！配置文件丢失。"); }
$username = htmlspecialchars($_SESSION['admin_username']);
$feedback_msg = ''; $feedback_type = ''; $page_title = '添加新公告';
$announcement = ['id' => null, 'title' => '', 'content' => '', 'is_active' => 1];
$edit_mode = isset($_GET['id']);
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if ($edit_mode) {
        $page_title = '编辑公告';
        $id_to_edit = intval($_GET['id']);
        $stmt_get = $pdo->prepare("SELECT * FROM sl_announcements WHERE id = ?"); $stmt_get->execute([$id_to_edit]);
        $announcement = $stmt_get->fetch(PDO::FETCH_ASSOC);
        if (!$announcement) { header('Location: announcement_list.php'); exit; }
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : null;
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        $is_active = intval($_POST['is_active']);
        if (empty($title)) throw new Exception('公告标题不能为空。');
        if ($id) {
            $sql = "UPDATE sl_announcements SET title = ?, content = ?, is_active = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql); $stmt->execute([$title, $content, $is_active, $id]);
            $_SESSION['feedback_msg'] = '公告已成功更新。';
        } else {
            $sql = "INSERT INTO sl_announcements (title, content, is_active) VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($sql); $stmt->execute([$title, $content, $is_active]);
            $_SESSION['feedback_msg'] = '新公告已成功添加。';
        }
        $_SESSION['feedback_type'] = 'success';
        header('Location: announcement_list.php'); exit;
    }
} catch (Exception $e) { $feedback_msg = '操作失败: ' . $e->getMessage(); $feedback_type = 'error'; }
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
<link rel="stylesheet" type="text/css" href="../assets/css/animate.min.css">
<link rel="stylesheet" type="text/css" href="../assets/css/style.min.css">
<style>
.feedback-alert {
    padding: 10px 15px;
    margin-bottom: 20px;
    border-radius: 4px;
}
.feedback-alert.error {
    background-color: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
}
</style>
</head>
<body>
<div class="container-fluid">
  <div class="row">
    <div class="col-lg-12">
      <div class="card">
        <header class="card-header"><div class="card-title"><?php echo $page_title; ?></div></header>
        <div class="card-body">
          <form method="POST" action="announcement_edit.php<?php echo $edit_mode ? '?id='.$announcement['id'] : ''; ?>" class="row">
            <input type="hidden" name="id" value="<?php echo $announcement['id']; ?>">
            <?php if ($feedback_msg): ?>
            <div class="mb-3 col-md-12">
              <div class="feedback-alert error"><?php echo htmlspecialchars($feedback_msg); ?></div>
            </div>
            <?php endif; ?>
            <div class="mb-3 col-md-12">
              <label for="title" class="form-label">公告标题</label>
              <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($announcement['title']); ?>" placeholder="请输入公告标题" required />
            </div>
            <div class="mb-3 col-md-12">
              <label for="content" class="form-label">公告内容</label>
              <textarea class="form-control" id="content" name="content" rows="8" placeholder="请输入公告内容"><?php echo htmlspecialchars($announcement['content']); ?></textarea>
            </div>
            <div class="mb-3 col-md-12">
              <label for="is_active" class="form-label">状态</label>
              <div class="clearfix">
                <div class="form-check form-check-inline">
                  <input type="radio" id="statusActive" name="is_active" class="form-check-input" value="1" <?php echo $announcement['is_active'] == 1 ? 'checked' : ''; ?>>
                  <label class="form-check-label" for="statusActive">发布</label>
                </div>
                <div class="form-check form-check-inline">
                  <input type="radio" id="statusInactive" name="is_active" class="form-check-input" value="0" <?php echo $announcement['is_active'] == 0 ? 'checked' : ''; ?>>
                  <label class="form-check-label" for="statusInactive">存为草稿</label>
                </div>
              </div>
            </div>
            <div class="mb-3 col-md-12">
              <button type="submit" class="btn btn-primary"><?php echo $edit_mode ? '更新公告' : '添加公告'; ?></button>
              <button type="button" class="btn btn-default" onclick="javascript:history.back(-1);return false;">返回</button>
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