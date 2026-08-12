<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
if (file_exists('../config.php')) { require_once '../config.php'; } else { die("出现错误！配置文件丢失。"); }
$username = htmlspecialchars($_SESSION['admin_username']);
require_once '../common/email_broadcast_dispatcher.php';
$feedback_msg = ''; $feedback_type = '';
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $favicon_url = $pdo->query("SELECT setting_value FROM huli_settings WHERE setting_key='favicon_url'")->fetchColumn()?:'';
    if (isset($_GET['delete'])) {
        $id = intval($_GET['delete']);
        $pdo->prepare("DELETE FROM huli_email_broadcasts WHERE id = ?")->execute([$id]);
        $feedback_msg = "群发任务已删除！"; $feedback_type = "success";
    }
    if (isset($_GET['send_now'])) {
        $id = intval($_GET['send_now']);
        $r = huli_broadcast_send_one($pdo, $id, $err);
        if ($r === false) { $feedback_msg = $err; $feedback_type = "error"; }
        else { $feedback_msg = "发送完成！共发送 {$r['sent']}/{$r['total']} 封邮件。"; $feedback_type = "success"; }
    }
    $tick_err = huli_broadcast_web_tick($pdo);
    if ($tick_err && empty($feedback_msg)) { $feedback_msg = "调度提示：" . $tick_err; $feedback_type = "info"; }
    $stmt = $pdo->query("SELECT * FROM huli_email_broadcasts ORDER BY created_at DESC");
    $broadcasts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $feedback_msg = $e->getMessage(); $feedback_type = "error";
}
?><!DOCTYPE html>
<html lang="zh">
 <head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php if($favicon_url):?><link rel="shortcut icon" type="image/x-icon" href="<?php echo htmlspecialchars($favicon_url);?>"><?php endif;?>
<link rel="stylesheet" href="../assets/css/materialdesignicons.min.css">
<link rel="stylesheet" href="../assets/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/css/style.min.css">
<style>.status-badge{font-size:.8rem;padding:.3rem .6rem}</style>
</head>
<body>
<div class="container-fluid"><div class="row"><div class="col-lg-12"><div class="card">
<header class="card-header"><div class="card-title"><i class="mdi mdi-email-send me-2"></i>邮件群发管理</div>
<div class="card-action"><a href="email_broadcast_create.php" class="btn btn-primary btn-sm"><i class="mdi mdi-plus"></i> 新建群发</a></div></header>
<div class="card-body">
<?php if ($feedback_msg): ?><div class="alert alert-<?= $feedback_type==='success'?'success':'danger' ?> alert-dismissible fade show mb-3"><?= htmlspecialchars($feedback_msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<div class="table-responsive"><table class="table table-hover">
<thead><tr><th>ID</th><th>标题</th><th>状态</th><th>进度</th><th>计划时间</th><th>创建时间</th><th width="200">操作</th></tr></thead>
<tbody>
<?php if (!empty($broadcasts)): foreach ($broadcasts as $b):
$statusLabels=['draft'=>'草稿','scheduled'=>'已预约','sending'=>'发送中','sent'=>'已发送'];
$statusColors=['draft'=>'secondary','scheduled'=>'info','sending'=>'warning','sent'=>'success'];
?>
<tr>
<td><?= $b['id'] ?></td>
<td><?= htmlspecialchars($b['title']) ?></td>
<td><span class="badge status-badge bg-<?= $statusColors[$b['status']] ?>"><?= $statusLabels[$b['status']] ?></span></td>
<td><?= $b['sent_count'] ?>/<?= $b['total_count'] ?: '?' ?></td>
<td><?= $b['scheduled_at'] ?: '-' ?></td>
<td><?= date('Y-m-d H:i', strtotime($b['created_at'])) ?></td>
<td><div class="btn-group btn-group-sm">
<a href="email_broadcast_create.php?id=<?= $b['id'] ?>" class="btn btn-outline-primary"><i class="mdi mdi-pencil"></i></a>
<?php if (in_array($b['status'],['draft','scheduled'])): ?>
<a href="?send_now=<?= $b['id'] ?>" class="btn btn-outline-success" onclick="return confirm('确定立即发送？系统会向所有注册用户发送邮件。')"><i class="mdi mdi-send"></i> 发送</a>
<?php endif; ?>
<a href="?delete=<?= $b['id'] ?>" class="btn btn-outline-danger" onclick="return confirm('确定删除？')"><i class="mdi mdi-delete"></i></a>
</div></td>
</tr>
<?php endforeach; else: ?>
<tr><td colspan="7" class="text-center text-muted py-4">暂无群发任务</td></tr>
<?php endif; ?>
</tbody></table></div>
</div></div></div></div></div>
</body></html>
