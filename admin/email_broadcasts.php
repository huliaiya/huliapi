<?php
require_once __DIR__ . '/../common/session_boot.php';
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
    huli_ensure_broadcast_columns($pdo);
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $id = intval($_POST['id'] ?? 0);
        if ($_POST['action'] === 'delete') {
            $pdo->prepare("DELETE FROM huli_email_broadcasts WHERE id = ?")->execute([$id]);
            $feedback_msg = "群发任务已删除！"; $feedback_type = "success";
        } elseif ($_POST['action'] === 'toggle') {
            $stmt = $pdo->prepare("UPDATE huli_email_broadcasts SET status = IF(status = 'scheduled', 'draft', 'scheduled') WHERE id = ?");
            $stmt->execute([$id]);
            if ($stmt->rowCount() > 0) {
                $feedback_msg = "任务状态已切换！"; $feedback_type = "success";
            }
        } elseif ($_POST['action'] === 'send_now') {
            $r = huli_broadcast_send_one($pdo, $id, $err);
            if ($r === false) { $feedback_msg = $err; $feedback_type = "error"; }
            elseif (is_array($r)) { $feedback_msg = "发送完成！共发送 {$r['sent']}/{$r['total']} 封邮件。"; $feedback_type = "success"; }
        }
    }
    $tick_err = huli_broadcast_web_tick($pdo);
    if ($tick_err && empty($feedback_msg)) { $feedback_msg = "调度提示：" . $tick_err; $feedback_type = "info"; }
    $stmt = $pdo->query("SELECT * FROM huli_email_broadcasts ORDER BY created_at DESC");
    $broadcasts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('[email_broadcasts.php] ' . $e->getMessage());
    $feedback_msg = '操作失败，请稍后重试。'; $feedback_type = "error";
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
<style>.status-badge{font-size:.8rem;padding:.3rem .6rem}
.table-responsive{position:relative}
.table>thead>tr>th:last-child,
.table>tbody>tr>td:last-child{
    position:sticky;right:0;z-index:2;white-space:nowrap;
    --bs-table-bg:rgba(225,247,255,.86);
    --bs-table-accent-bg:rgba(225,247,255,.86);
    background-color:rgba(225,247,255,.86)!important;
    background-image:linear-gradient(135deg,rgba(221,246,255,.94),rgba(236,252,252,.9))!important;
    box-shadow:inset 1px 0 0 rgba(130,190,210,.18)}
.table-hover tbody tr:hover td:last-child{
    --bs-table-bg:rgba(215,244,252,.92);
    --bs-table-accent-bg:rgba(215,244,252,.92);
    background-color:rgba(215,244,252,.92)!important;
    background-image:linear-gradient(135deg,rgba(210,241,250,.96),rgba(229,251,251,.94))!important}
.table>thead>tr>th:last-child{z-index:3}
</style>
</head>
<body>
<div class="container-fluid"><div class="row"><div class="col-lg-12"><div class="card">
<header class="card-header"><div class="card-title"><i class="mdi mdi-email-send me-2"></i>邮件群发管理</div>
<div class="card-action"><a href="email_broadcast_create.php" class="btn btn-primary btn-sm"><i class="mdi mdi-plus"></i> 新建群发</a></div></header>
<div class="card-body">
<?php if ($feedback_msg): ?><div class="alert alert-<?= $feedback_type==='success'?'success':'danger' ?> alert-dismissible fade show mb-3"><?= htmlspecialchars($feedback_msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<div class="table-responsive"><table class="table table-hover">
<thead><tr><th>ID</th><th>标题</th><th>状态</th><th>模式</th><th>进度</th><th>计划/每日时刻</th><th>上次发送</th><th>创建时间</th><th width="280">操作</th></tr></thead>
<tbody>
<?php if (!empty($broadcasts)): foreach ($broadcasts as $b):
$statusLabels=['draft'=>'草稿','scheduled'=>'已预约','sending'=>'发送中','sent'=>'已发送'];
$statusColors=['draft'=>'secondary','scheduled'=>'info','sending'=>'warning','sent'=>'success'];
$typeLabels=['once'=>'仅一次','daily'=>'每天'];
?>
<tr>
<td><?= $b['id'] ?></td>
<td><a href="email_broadcast_create.php?id=<?= $b['id'] ?>" class="text-decoration-none text-dark" title="点击修改"><?= htmlspecialchars($b['title']) ?></a>
<?php if (!empty($b['last_error'])): ?><div class="text-danger small mt-1" title="<?= htmlspecialchars($b['last_error']) ?>"><i class="mdi mdi-alert-circle"></i> <?= htmlspecialchars(mb_substr($b['last_error'], 0, 60)) ?></div><?php endif; ?>
</td>
<td><span class="badge status-badge bg-<?= $statusColors[$b['status']] ?>"><?= $statusLabels[$b['status']] ?></span></td>
<td><span class="badge status-badge bg-<?= $b['send_type']==='daily'?'warning':'secondary' ?>"><?= $typeLabels[$b['send_type']] ?? $b['send_type'] ?></span></td>
<td><?= $b['sent_count'] ?>/<?= $b['total_count'] ?: '?' ?></td>
<td><?= $b['scheduled_at'] ?: '-' ?></td>
<td><?= $b['last_run_at'] ?: '-' ?></td>
<td><?= date('Y-m-d H:i', strtotime($b['created_at'])) ?></td>
<td><div class="d-flex flex-wrap gap-1">
<a href="email_broadcast_create.php?id=<?= $b['id'] ?>" class="btn btn-primary btn-sm"><i class="mdi mdi-pencil"></i> 修改</a>
<?php if (in_array($b['status'], ['draft', 'scheduled'])): ?>
<?php echo huli_post_action_button('email_broadcasts.php', ['action' => 'send_now', 'id' => $b['id']], '<i class="mdi mdi-send"></i> 发送', 'btn btn-success btn-sm', '确定立即发送？系统会向所有注册用户发送邮件。'); ?>
<?php endif; ?>
<?php if ($b['status'] === 'scheduled' || $b['status'] === 'draft'): ?>
<?php echo huli_post_action_button('email_broadcasts.php', ['action' => 'toggle', 'id' => $b['id']], ($b['status']==='scheduled'?'<i class="mdi mdi-pause"></i> 停用':'<i class="mdi mdi-play"></i> 启用'), 'btn btn-warning btn-sm'); ?>
<?php endif; ?>
<?php echo huli_post_action_button('email_broadcasts.php', ['action' => 'delete', 'id' => $b['id']], '<i class="mdi mdi-delete"></i> 删除', 'btn btn-danger btn-sm', '确定删除？'); ?>
</div></td>
</tr>
<?php endforeach; else: ?>
<tr><td colspan="9" class="text-center text-muted py-4">暂无群发任务</td></tr>
<?php endif; ?>
</tbody></table></div>
</div></div></div></div></div>
</body></html>
