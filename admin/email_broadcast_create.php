<?php
require_once __DIR__ . '/../common/session_boot.php';
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
if (file_exists('../config.php')) { require_once '../config.php'; } else { die("出现错误！配置文件丢失。"); }
$username = htmlspecialchars($_SESSION['admin_username']);
$feedback_msg = ''; $feedback_type = '';
$edit_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$broadcast = ['title'=>'','content'=>'','scheduled_at'=>'','status'=>'draft','send_type'=>'once'];
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $favicon_url = $pdo->query("SELECT setting_value FROM huli_settings WHERE setting_key='favicon_url'")->fetchColumn()?:'';
    require_once '../common/email_broadcast_dispatcher.php';
    huli_ensure_broadcast_columns($pdo);
    if ($edit_id) {
        $stmt = $pdo->prepare("SELECT * FROM huli_email_broadcasts WHERE id = ?");
        $stmt->execute([$edit_id]); $broadcast = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$broadcast) { $feedback_msg = '群发任务不存在'; $feedback_type = 'error'; $edit_id = 0; }
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $title = trim($_POST['title'] ?? '');
        $content = $_POST['content'] ?? '';
        $schedule = trim($_POST['scheduled_at'] ?? '');
        $send_type = ($_POST['send_type'] ?? 'once') === 'daily' ? 'daily' : 'once';
        if (empty($title) || empty($content)) throw new Exception('标题和内容不能为空');
        $status = !empty($schedule) ? 'scheduled' : 'draft';
        $scheduled_at = !empty($schedule) ? $schedule : null;
        if ($send_type === 'daily' && $status === 'scheduled') {
            $scheduled_at = date('Y-m-d H:i:s', strtotime(date('Y-m-d') . ' ' . date('H:i:s', strtotime($schedule))));
        }
        if ($edit_id) {
            $stmt = $pdo->prepare("UPDATE huli_email_broadcasts SET title=?, content=?, status=?, scheduled_at=?, send_type=? WHERE id=?");
            $stmt->execute([$title, $content, $status, $scheduled_at, $send_type, $edit_id]);
            $feedback_msg = '群发任务已更新！';
        } else {
            $stmt = $pdo->prepare("INSERT INTO huli_email_broadcasts (title, content, status, scheduled_at, send_type) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$title, $content, $status, $scheduled_at, $send_type]);
            $feedback_msg = '群发任务已创建！';
        }
        $feedback_type = 'success';
        if (!$edit_id) { $broadcast = ['title'=>'','content'=>'','scheduled_at'=>'','status'=>'draft','send_type'=>$send_type]; }
        else { $broadcast['title'] = $title; $broadcast['content'] = $content; $broadcast['scheduled_at'] = $scheduled_at; $broadcast['status'] = $status; $broadcast['send_type'] = $send_type; }
    }
} catch (PDOException $e) { error_log('[email_broadcast_create.php] ' . $e->getMessage()); $feedback_msg = '操作失败，请稍后重试。'; $feedback_type = 'error'; }
catch (Exception $e) { $feedback_msg = $e->getMessage(); $feedback_type = 'error'; }
?>
<!DOCTYPE html>
<html lang="zh">
 <head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php if($favicon_url):?><link rel="shortcut icon" type="image/x-icon" href="<?php echo htmlspecialchars($favicon_url);?>"><?php endif;?>
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
<div class="mb-3"><label class="form-label">邮件内容 (HTML)</label><textarea class="form-control" name="content" rows="18" placeholder="支持HTML格式，可使用 {{email}}、{{username}}、{{site_name}}、{{send_time}} 等占位符" required><?= htmlspecialchars($broadcast['content'] ?: '') ?></textarea>
<small class="form-text text-muted">可用占位符：{{email}} 用户邮箱，{{site_name}} 站点名称，{{user_id}} 用户ID，{{username}} 用户名，{{nickname}} 昵称，{{qq}} QQ，{{points}} 点数，{{balance}} 余额，{{vip_expire_time}} 会员到期时间，{{register_time}} 注册时间，{{broadcast_title}} 群发标题，{{send_time}} 发送时间，{{today}} 当前日期，{{year}} 年，{{month}} 月，{{day}} 日</small></div>
<div class="mb-3"><label class="form-label">发送模式</label>
<select class="form-select" name="send_type" id="send_type" style="max-width:300px;">
<option value="once" <?= $broadcast['send_type']==='daily'?'':'selected' ?>>仅发送一次</option>
<option value="daily" <?= $broadcast['send_type']==='daily'?'selected':'' ?>>每天定时发送</option>
</select>
<small class="form-text text-muted">「仅发送一次」发送完成后任务结束；「每天定时发送」每天到点自动重复发送。</small></div>
<div class="mb-3"><label class="form-label" id="schedule_label">定时发送（选填）</label><input class="form-control" type="datetime-local" name="scheduled_at" id="scheduled_at" value="<?= $broadcast['scheduled_at'] ? date('Y-m-d\TH:i', strtotime($broadcast['scheduled_at'])) : '' ?>" style="max-width:300px;">
<small class="form-text text-muted" id="schedule_hint">不填则仅保存为草稿，需手动点击发送</small></div>
<div class="d-flex gap-2">
<button type="submit" class="btn btn-primary"><i class="mdi mdi-content-save"></i> 保存</button>
<a href="email_broadcasts.php" class="btn btn-outline-secondary"><i class="mdi mdi-arrow-left"></i> 返回列表</a>
</div>
</form>
<script>
document.getElementById('send_type').addEventListener('change', function() {
    var daily = this.value === 'daily';
    document.getElementById('schedule_label').textContent = daily ? '每日发送时间（必填）' : '定时发送（选填）';
    document.getElementById('schedule_hint').textContent = daily ? '每天在该时刻自动发送一次，日期部分忽略' : '不填则仅保存为草稿，需手动点击发送';
});
</script>
</div></div></div></div></div>
</body></html>
