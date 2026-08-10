<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
if (file_exists('../config.php')) { require_once '../config.php'; } else { die("出现错误！配置文件丢失。"); }
$username = htmlspecialchars($_SESSION['admin_username']);
$feedback_msg = ''; $feedback_type = '';
$edit_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$broadcast = ['title'=>'','content'=>'','scheduled_at'=>'','status'=>'draft'];
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if ($edit_id) {
        $stmt = $pdo->prepare("SELECT * FROM huli_email_broadcasts WHERE id = ?");
        $stmt->execute([$edit_id]); $broadcast = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$broadcast) { $feedback_msg = '群发任务不存在'; $feedback_type = 'error'; $edit_id = 0; }
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $title = trim($_POST['title'] ?? '');
        $content = $_POST['content'] ?? '';
        $schedule = trim($_POST['scheduled_at'] ?? '');
        if (empty($title) || empty($content)) throw new Exception('标题和内容不能为空');
        $status = !empty($schedule) ? 'scheduled' : 'draft';
        $scheduled_at = !empty($schedule) ? $schedule : null;
        if ($edit_id) {
            $stmt = $pdo->prepare("UPDATE huli_email_broadcasts SET title=?, content=?, status=?, scheduled_at=? WHERE id=?");
            $stmt->execute([$title, $content, $status, $scheduled_at, $edit_id]);
            $feedback_msg = '群发任务已更新！';
        } else {
            $stmt = $pdo->prepare("INSERT INTO huli_email_broadcasts (title, content, status, scheduled_at) VALUES (?, ?, ?, ?)");
            $stmt->execute([$title, $content, $status, $scheduled_at]);
            $feedback_msg = '群发任务已创建！';
        }
        $feedback_type = 'success';
        if (!$edit_id) { $broadcast = ['title'=>'','content'=>'','scheduled_at'=>'','status'=>'draft']; }
        else { $broadcast['title'] = $title; $broadcast['content'] = $content; $broadcast['scheduled_at'] = $scheduled_at; $broadcast['status'] = $status; }
    }
} catch (Exception $e) { $feedback_msg = $e->getMessage(); $feedback_type = 'error'; }
?>
<!DOCTYPE html>
<html lang="zh">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../assets/css/materialdesignicons.min.css">
<link rel="stylesheet" href="../assets/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/css/style.min.css">
<style>.email-preview{border:1px solid #dee2e6;border-radius:8px;padding:20px;background:#f8f9fa;min-height:200px}</style>
</head>
<body>
<div class="container-fluid"><div class="row"><div class="col-lg-12"><div class="card">
<header class="card-header"><div class="card-title"><i class="mdi mdi-<?= $edit_id ? 'pencil' : 'plus' ?> me-2"></i><?= $edit_id ? '编辑群发' : '新建群发' ?></div></header>
<div class="card-body">
<?php if ($feedback_msg): ?><div class="alert alert-<?= $feedback_type==='success'?'success':'danger' ?> alert-dismissible fade show mb-3"><?= htmlspecialchars($feedback_msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<form method="POST">
<div class="mb-3"><label class="form-label">邮件标题（主题）</label><input class="form-control" type="text" name="title" value="<?= htmlspecialchars($broadcast['title']) ?>" placeholder="邮件主题" required></div>
<div class="mb-3"><label class="form-label">邮件内容 (HTML)</label><textarea class="form-control" name="content" rows="18" placeholder="支持HTML格式，可使用 {{email}} 和 {{site_name}} 占位符" required><?= htmlspecialchars($broadcast['content'] ?: '') ?></textarea>
<small class="form-text text-muted">可用占位符：{{email}} - 用户邮箱地址，{{site_name}} - 站点名称</small></div>
<div class="mb-3"><label class="form-label">定时发送（选填）</label><input class="form-control" type="datetime-local" name="scheduled_at" value="<?= $broadcast['scheduled_at'] ? date('Y-m-d\TH:i', strtotime($broadcast['scheduled_at'])) : '' ?>" style="max-width:300px;">
<small class="form-text text-muted">不填则仅保存为草稿，需手动点击发送</small></div>
<div class="d-flex gap-2">
<button type="submit" class="btn btn-primary"><i class="mdi mdi-content-save"></i> 保存</button>
<a href="email_broadcasts.php" class="btn btn-outline-secondary"><i class="mdi mdi-arrow-left"></i> 返回列表</a>
</div>
</form>
</div></div></div></div></div>
</body></html>
