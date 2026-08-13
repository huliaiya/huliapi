<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
if (file_exists('../config.php')) { require_once '../config.php'; } else { die("出现错误！配置文件丢失。"); }
require_once __DIR__ . '/../common/avatar.php';
require_once __DIR__ . '/../common/push.php';
$username = htmlspecialchars($_SESSION['admin_username']); $admin_id = $_SESSION['admin_id'];
$admin_qq = '';
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->prepare("SELECT qq, nickname, email FROM huli_admins WHERE id = ?"); $stmt->execute([$admin_id]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    $admin_qq = $admin['qq'] ?? '';
    $admin_email = $admin['email'] ?? '';
    $nickname = htmlspecialchars($admin['nickname'] ?? $username);
} catch (PDOException $e) { $nickname = $username; $admin_email = ''; }
$channels = [];
try {
    $channels = $pdo->query("SELECT channel, name, enabled, config, events FROM huli_push_settings ORDER BY id ASC")->fetchAll();
} catch (Throwable $e) {}
$mail_cfg_hint = '';
try {
    $sys_mail = huli_load_system_mail_config($pdo);
    if (empty($sys_mail['smtp_host']) || empty($sys_mail['smtp_user']) || empty($sys_mail['smtp_pass'])) {
        $mail_cfg_hint = '<br><span class="text-warning small"><i class="mdi mdi-alert-outline"></i> 系统邮件 SMTP 当前尚未配置，启用通道前请先在【系统设置】中完善。</span>';
    } else {
        $mail_cfg_hint = '<br><span class="text-success small"><i class="mdi mdi-check-circle-outline"></i> 系统邮件已配置（' . htmlspecialchars($sys_mail['smtp_host']) . ' / ' . htmlspecialchars($sys_mail['smtp_user']) . '）</span>';
    }
} catch (Throwable $e) {}
$feedback_msg = ''; $feedback_type = '';
$push_feedback_msg = ''; $push_feedback_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['test_channel'])) {
        $valid_channels = ['email', 'wecom', 'dingtalk', 'feishu', 'bark', 'webhook'];
        $test_channel = $_POST['test_channel'];
        $test_recipient = trim($_POST['test_recipient'] ?? '');
        $title = '【huliapi 推送测试】这是一条测试消息';
        $content = "**通道**：" . $test_channel . "\n**时间**：" . date('Y-m-d H:i:s') . "\n如果您收到此消息，说明该推送通道已配置正确。";
        try {
            $set = huli_load_push_settings($pdo);
            if (!in_array($test_channel, $valid_channels, true) || empty($set[$test_channel]) || !$set[$test_channel]['enabled']) {
                $push_feedback_msg = '请先启用并保存此通道后再测试。';
                $push_feedback_type = 'warning';
            } else {
                $cfg = $set[$test_channel]['config'];
                if ($test_channel === 'email') { $cfg = huli_load_system_mail_config($pdo); }
                $r = ['ok' => false];
                switch ($test_channel) {
                    case 'email':
                        if (!$test_recipient) { $push_feedback_msg = '邮件测试需要填写收件人邮箱。'; $push_feedback_type = 'warning'; break; }
                        if (empty($cfg['smtp_host']) || empty($cfg['smtp_user']) || empty($cfg['smtp_pass'])) { $push_feedback_msg = '系统邮件服务未配置，请先在系统设置中填写 SMTP 信息。'; $push_feedback_type = 'warning'; break; }
                        $r = huli_push_email($cfg, $test_recipient, $title, $content); break;
                    case 'wecom':    $r = huli_push_wecom($cfg, $title, $content); break;
                    case 'dingtalk': $r = huli_push_dingtalk($cfg, $title, $content); break;
                    case 'feishu':   $r = huli_push_feishu($cfg, $title, $content); break;
                    case 'bark':     $r = huli_push_bark($cfg, $title, $content); break;
                    case 'webhook':  $r = huli_push_webhook($cfg, $title, $content); break;
                }
                if (empty($push_feedback_msg)) {
                    $push_feedback_msg = $r['ok'] ? ('测试发送成功（HTTP ' . ($r['code'] ?? '200') . '）') : ('测试发送失败：' . ($r['err'] ?? 'HTTP ' . ($r['code'] ?? 'N/A')));
                    $push_feedback_type = $r['ok'] ? 'success' : 'danger';
                }
            }
        } catch (Throwable $e) {
            $push_feedback_msg = '测试异常: ' . $e->getMessage();
            $push_feedback_type = 'danger';
        }
    } else {
        $type = $_POST['form_type'] ?? '';
        if ($type === 'push') {
            $channel = $_POST['channel'] ?? '';
                $valid_channels = ['email', 'wecom', 'dingtalk', 'feishu', 'bark', 'webhook'];
                if (in_array($channel, $valid_channels, true)) {
                    $config_input = $_POST['config'] ?? [];
                    $enabled = isset($_POST['enabled']) ? 1 : 0;
                    $events = isset($_POST['events']) && is_array($_POST['events']) ? array_values(array_intersect($_POST['events'], ['login.notify'])) : [];
                    $cfg = [];
                    switch ($channel) {
                        case 'email':
                            $cfg = [];
                            break;
                        case 'wecom':
                            $cfg = ['webhook' => trim($config_input['webhook'] ?? '')];
                            break;
                        case 'dingtalk':
                            $cfg = ['webhook' => trim($config_input['webhook'] ?? ''), 'secret' => trim($config_input['secret'] ?? '')];
                            break;
                        case 'feishu':
                            $cfg = ['webhook' => trim($config_input['webhook'] ?? ''), 'secret' => trim($config_input['secret'] ?? '')];
                            break;
                        case 'bark':
                            $cfg = ['server' => trim($config_input['server'] ?? 'https://api.day.app'), 'device_key' => trim($config_input['device_key'] ?? '')];
                            break;
                        case 'webhook':
                            $cfg = ['url' => trim($config_input['url'] ?? ''), 'method' => in_array(strtoupper($config_input['method'] ?? 'POST'), ['POST','GET','PUT'], true) ? strtoupper($config_input['method']) : 'POST', 'headers' => trim($config_input['headers'] ?? '')];
                            break;
                    }
                $stmt = $pdo->prepare("INSERT INTO huli_push_settings (channel, enabled, config, events) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), config = VALUES(config), events = VALUES(events)");
                $stmt->execute([$channel, $enabled, json_encode($cfg, JSON_UNESCAPED_UNICODE), json_encode($events, JSON_UNESCAPED_UNICODE)]);
                $push_feedback_msg = '推送通道已保存。';
                $push_feedback_type = 'success';
                $channels = $pdo->query("SELECT channel, name, enabled, config, events FROM huli_push_settings ORDER BY id ASC")->fetchAll();
            }
        } elseif ($type === 'password') {
        $current_password = $_POST['current_password']; $new_password = $_POST['new_password']; $confirm_password = $_POST['confirm_password'];
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $feedback_msg = '所有字段均为必填项。'; $feedback_type = 'error';
        } elseif ($new_password !== $confirm_password) {
            $feedback_msg = '新密码和确认密码不匹配。'; $feedback_type = 'error';
        } else {
            try {
                $stmt_pw = $pdo->prepare("SELECT password FROM huli_admins WHERE id = ?"); $stmt_pw->execute([$admin_id]);
                $admin_data = $stmt_pw->fetch();
                if ($admin_data && password_verify($current_password, $admin_data['password'])) {
                    $update_stmt = $pdo->prepare("UPDATE huli_admins SET password = ? WHERE id = ?");
                    $update_stmt->execute([password_hash($new_password, PASSWORD_DEFAULT), $admin_id]);
                    $feedback_msg = '密码已成功更新。'; $feedback_type = 'success';
                } else { $feedback_msg = '当前密码不正确。'; $feedback_type = 'error'; }
            } catch (PDOException $e) { $feedback_msg = '出现错误！数据库操作失败。'; $feedback_type = 'error'; }
        }
    } elseif ($type === 'qq') {
        $new_qq = trim($_POST['qq'] ?? '');
        if ($new_qq !== '' && !preg_match('/^\d{5,11}$/', $new_qq)) {
            $feedback_msg = '请输入有效的QQ号（5-11位数字）。'; $feedback_type = 'error';
        } else {
            try {
                $update_stmt = $pdo->prepare("UPDATE huli_admins SET qq = ? WHERE id = ?");
                $update_stmt->execute([$new_qq, $admin_id]);
                $admin_qq = $new_qq;
                $feedback_msg = 'QQ号已更新，头像已刷新。'; $feedback_type = 'success';
            } catch (PDOException $e) { $feedback_msg = '出现错误！数据库操作失败。'; $feedback_type = 'error'; }
        }
    } elseif ($type === 'profile') {
        $new_nickname = trim($_POST['nickname'] ?? '');
        $new_email = trim($_POST['email'] ?? '');
        if ($new_nickname === '') {
            $feedback_msg = '管理员昵称不能为空。'; $feedback_type = 'error';
        } elseif ($new_email !== '' && !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $feedback_msg = '请输入有效的邮箱地址。'; $feedback_type = 'error';
        } else {
            try {
                $update_stmt = $pdo->prepare("UPDATE huli_admins SET nickname = ?, email = ? WHERE id = ?");
                $update_stmt->execute([$new_nickname, $new_email, $admin_id]);
                $nickname = htmlspecialchars($new_nickname);
                $admin_email = $new_email;
                $feedback_msg = '个人资料已更新。'; $feedback_type = 'success';
            } catch (PDOException $e) { $feedback_msg = '出现错误！数据库操作失败。'; $feedback_type = 'error'; }
        }
    } elseif ($type === 'username') {
        $new_username = trim($_POST['new_username'] ?? '');
        $current_pw = $_POST['current_password_for_username'] ?? '';
        if ($new_username === '' || $current_pw === '') {
            $feedback_msg = '新用户名与当前密码均为必填。'; $feedback_type = 'error';
        } elseif (!preg_match('/^[A-Za-z0-9_]{2,32}$/', $new_username)) {
            $feedback_msg = '新用户名需要 2-32 位字母、数字或下划线。'; $feedback_type = 'error';
        } else {
            try {
                $stmt_chk = $pdo->prepare("SELECT password FROM huli_admins WHERE id = ?"); $stmt_chk->execute([$admin_id]);
                $admin_pw_row = $stmt_chk->fetch();
                if (!$admin_pw_row || !password_verify($current_pw, $admin_pw_row['password'])) {
                    $feedback_msg = '当前密码不正确。'; $feedback_type = 'error';
                } else {
                    $stmt_exist = $pdo->prepare("SELECT id FROM huli_admins WHERE username = ? AND id <> ?"); $stmt_exist->execute([$new_username, $admin_id]);
                    if ($stmt_exist->fetch()) {
                        $feedback_msg = '该用户名已被占用，请换一个。'; $feedback_type = 'error';
                    } else {
                        $pdo->prepare("UPDATE huli_admins SET username = ? WHERE id = ?")->execute([$new_username, $admin_id]);
                        $_SESSION['admin_username'] = $new_username;
                        $username = htmlspecialchars($new_username);
                        $feedback_msg = '管理员用户名已更新，下次请使用新用户名登录。'; $feedback_type = 'success';
                    }
                }
            } catch (PDOException $e) { $feedback_msg = '出现错误！数据库操作失败。'; $feedback_type = 'error'; }
        }
    }
    }
}
$current_page_script = basename($_SERVER['PHP_SELF']);

function huli_render_channel_card($row) {
    $cfg = json_decode($row['config'], true) ?: [];
    $events = json_decode($row['events'], true) ?: [];
    $enabled = (int)$row['enabled'] === 1;
    $ch = $row['channel'];
    $h = '<div class="card shadow-sm mb-3"><div class="card-body">';
    $h .= '<div class="d-flex align-items-center justify-content-between mb-3">';
    $h .= '<div class="d-flex align-items-center">';
    $icons = ['email'=>'mdi-email-outline','wecom'=>'mdi-wechat','dingtalk'=>'mdi-message-text-outline','feishu'=>'mdi-cloud-outline','bark'=>'mdi-bell-ring-outline','webhook'=>'mdi-webhook'];
    $h .= '<div class="me-3 d-flex align-items-center justify-content-center rounded" style="width:48px;height:48px;background:rgba(108,182,255,.16);color:#2879ba;font-size:24px;"><i class="mdi ' . ($icons[$ch] ?? 'mdi-bell-outline') . '"></i></div>';
    $h .= '<div><div class="fw-bold fs-5">' . htmlspecialchars($row['name']) . ' <small class="text-muted">(' . $ch . ')</small></div>';
    $h .= '<small class="text-muted">' . ($enabled ? '<span class="badge bg-success">已启用</span>' : '<span class="badge bg-secondary">未启用</span>') . '</small></div>';
    $h .= '</div></div>';
    $h .= '<form method="post" class="row g-3">';
    $h .= '<input type="hidden" name="form_type" value="push">';
    $h .= '<input type="hidden" name="channel" value="' . htmlspecialchars($ch) . '">';
    if ($ch === 'email') {
        $h .= '<div class="col-md-12"><div class="alert alert-info mb-0 py-2"><i class="mdi mdi-information-outline me-1"></i>邮件通道使用系统统一邮件配置（管理员后台 → 系统设置 → 邮件设置），无需在此填写 SMTP 信息。启用本通道后，登录提醒将发送到管理员邮箱。';
        try {
            if (isset($GLOBALS['mail_cfg_hint'])) { $h .= $GLOBALS['mail_cfg_hint']; }
        } catch (Throwable $e) {}
        $h .= '</div></div>';
    } elseif ($ch === 'wecom') {
        $h .= '<div class="col-md-12"><label class="form-label">机器人 Webhook URL</label><input type="text" name="config[webhook]" class="form-control" value="' . htmlspecialchars($cfg['webhook'] ?? '') . '" placeholder="https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=xxx"></div>';
    } elseif ($ch === 'dingtalk') {
        $h .= '<div class="col-md-8"><label class="form-label">机器人 Webhook URL</label><input type="text" name="config[webhook]" class="form-control" value="' . htmlspecialchars($cfg['webhook'] ?? '') . '" placeholder="https://oapi.dingtalk.com/robot/send?access_token=xxx"></div>';
        $h .= '<div class="col-md-4"><label class="form-label">加签密钥（可选）</label><input type="text" name="config[secret]" class="form-control" value="' . htmlspecialchars($cfg['secret'] ?? '') . '" placeholder="SEC..."></div>';
    } elseif ($ch === 'feishu') {
        $h .= '<div class="col-md-8"><label class="form-label">机器人 Webhook URL</label><input type="text" name="config[webhook]" class="form-control" value="' . htmlspecialchars($cfg['webhook'] ?? '') . '" placeholder="https://open.feishu.cn/open-apis/bot/v2/hook/xxx"></div>';
        $h .= '<div class="col-md-4"><label class="form-label">签名校验（可选）</label><input type="text" name="config[secret]" class="form-control" value="' . htmlspecialchars($cfg['secret'] ?? '') . '"></div>';
    } elseif ($ch === 'bark') {
        $h .= '<div class="col-md-6"><label class="form-label">Bark 服务地址</label><input type="text" name="config[server]" class="form-control" value="' . htmlspecialchars($cfg['server'] ?? 'https://api.day.app') . '"></div>';
        $h .= '<div class="col-md-6"><label class="form-label">Device Key</label><input type="text" name="config[device_key]" class="form-control" value="' . htmlspecialchars($cfg['device_key'] ?? '') . '"></div>';
    } elseif ($ch === 'webhook') {
        $h .= '<div class="col-md-9"><label class="form-label">回调地址 URL</label><input type="text" name="config[url]" class="form-control" value="' . htmlspecialchars($cfg['url'] ?? '') . '" placeholder="https://example.com/notify"></div>';
        $h .= '<div class="col-md-3"><label class="form-label">请求方法</label><select name="config[method]" class="form-select"><option ' . (($cfg['method'] ?? 'POST') === 'POST' ? 'selected' : '') . '>POST</option><option ' . (($cfg['method'] ?? '') === 'GET' ? 'selected' : '') . '>GET</option><option ' . (($cfg['method'] ?? '') === 'PUT' ? 'selected' : '') . '>PUT</option></select></div>';
        $h .= '<div class="col-md-12"><label class="form-label">自定义请求头（每行一个，例如 Authorization: Bearer xxx）</label><textarea name="config[headers]" class="form-control" rows="2">' . htmlspecialchars($cfg['headers'] ?? '') . '</textarea></div>';
    }
    $h .= '<div class="col-md-12"><label class="form-label d-block">订阅事件</label>';
    $h .= '<div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="events[]" value="login.notify" id="evt_' . $ch . '_login"' . (in_array('login.notify', $events) ? ' checked' : '') . '><label class="form-check-label" for="evt_' . $ch . '_login">登录提醒（管理员 / 用户登录时触发）</label></div>';
    $h .= '</div>';
    $h .= '<div class="col-md-12 d-flex gap-2 align-items-center">';
    $h .= '<div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="enabled" id="en_' . $ch . '"' . ($enabled ? ' checked' : '') . '><label class="form-check-label" for="en_' . $ch . '">启用此通道</label></div>';
    $h .= '<button type="submit" class="btn btn-primary ms-auto"><i class="mdi mdi-content-save-outline"></i> 保存配置</button>';
    $h .= '</div>';
    $h .= '</form>';
    if ($ch === 'email') {
        $h .= '<hr><form method="post" class="row g-2 align-items-end"><input type="hidden" name="test_channel" value="email"><div class="col-md-7"><label class="form-label small">测试收件邮箱</label><input type="email" name="test_recipient" class="form-control form-control-sm" placeholder="to@example.com"></div><div class="col-md-3"><button class="btn btn-outline-primary btn-sm w-100" type="submit"><i class="mdi mdi-send-outline"></i> 发送测试邮件</button></div></form>';
    } else {
        $h .= '<hr><form method="post" class="row g-2 align-items-end"><input type="hidden" name="test_channel" value="' . htmlspecialchars($ch) . '"><div class="col-md-7"><small class="text-muted">已启用后即可发送测试消息到 ' . htmlspecialchars($ch) . ' 通道</small></div><div class="col-md-3"><button class="btn btn-outline-primary btn-sm w-100" type="submit"><i class="mdi mdi-send-outline"></i> 发送测试消息</button></div></form>';
    }
    $h .= '</div></div>';
    return $h;
}
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
    <link rel="stylesheet" type="text/css" href="../assets/css/style.min.css">
</head>
<body>
<div class="container-fluid">
  <div class="row">
    <div class="col-lg-12">
      <div class="card">
        <header class="card-header"><div class="card-title">管理员个人资料</div></header>
        <div class="card-body">
          <div class="text-center mb-4">
            <img src="<?php echo htmlspecialchars(huli_avatar_url($admin_qq)); ?>" alt="头像" class="rounded-circle" style="width:96px;height:96px;object-fit:cover;">
            <h5 class="mt-2"><?php echo $nickname; ?></h5>
          </div>
          <?php if ($feedback_msg): ?>
          <div class="alert alert-<?php echo $feedback_type === 'success' ? 'success' : 'danger'; ?> mb-3">
            <?php echo htmlspecialchars($feedback_msg); ?>
          </div>
          <?php endif; ?>
          <form method="POST" action="profile.php" class="site-form mb-4">
            <input type="hidden" name="form_type" value="profile">
            <div class="mb-3">
              <label for="nickname">管理员昵称</label>
              <input type="text" class="form-control" name="nickname" id="nickname" value="<?php echo $nickname; ?>" required>
            </div>
            <div class="mb-3">
              <label for="email">管理员邮箱</label>
              <input type="email" class="form-control" name="email" id="email" value="<?php echo htmlspecialchars($admin_email); ?>" placeholder="用于找回密码等系统通知">
            </div>
            <button type="submit" class="btn btn-primary">保存</button>
          </form>
          <form method="POST" action="profile.php" class="site-form mb-4">
            <input type="hidden" name="form_type" value="qq">
            <div class="mb-3">
              <label for="qq">QQ号</label>
              <input type="text" class="form-control" name="qq" id="qq" value="<?php echo htmlspecialchars($admin_qq); ?>" placeholder="填写QQ号后自动加载头像">
              <small class="text-muted">头像使用 QQ 官方 API 自动获取，不填则显示默认头像</small>
            </div>
            <button type="submit" class="btn btn-primary">保存</button>
          </form>
          <form method="POST" action="profile.php" class="site-form mb-4">
            <input type="hidden" name="form_type" value="username">
            <div class="mb-3">
              <label for="new_username">管理员用户名</label>
              <input type="text" class="form-control" name="new_username" id="new_username" value="<?php echo $username; ?>" pattern="[A-Za-z0-9_]{2,32}" title="2-32 位字母、数字或下划线" required>
              <small class="text-muted">当前用户名：<code><?php echo $username; ?></code>　|　2-32 位字母、数字或下划线。修改后需使用新用户名重新登录。</small>
            </div>
            <div class="mb-3">
              <label for="current_password_for_username">当前密码（验证身份）</label>
              <input type="password" class="form-control" name="current_password_for_username" id="current_password_for_username" required>
            </div>
            <button type="submit" class="btn btn-primary"><i class="mdi mdi-account-edit-outline"></i> 更新用户名</button>
          </form>
          <hr class="my-4">
          <header class="card-header mb-3" style="background:transparent;padding:0;border:none;"><div class="card-title">推送通知</div></header>
          <p class="text-muted small mb-3">配置邮件 / 企业微信 / 钉钉 / 飞书 / Bark / 自定义 Webhook 通道，在登录提醒等事件触发时推送通知</p>
          <?php if ($push_feedback_msg): ?>
          <div class="alert alert-<?php echo $push_feedback_type === 'success' ? 'success' : ($push_feedback_type === 'warning' ? 'warning' : 'danger'); ?> mb-3">
            <?php echo htmlspecialchars($push_feedback_msg); ?>
          </div>
          <?php endif; ?>
          <?php foreach ($channels as $c) { echo huli_render_channel_card($c); } ?>
          <form method="POST" action="profile.php" class="site-form">
            <input type="hidden" name="form_type" value="password">
            <div class="mb-3">
              <label for="username">用户名</label>
              <input type="text" class="form-control" name="username" id="username" value="<?php echo $username; ?>" disabled>
            </div>
            <div class="mb-3">
              <label for="current_password">当前密码</label>
              <input type="password" class="form-control" name="current_password" id="current_password" placeholder="请输入您当前的密码" required>
            </div>
            <div class="mb-3">
              <label for="new_password">新密码</label>
              <input type="password" class="form-control" name="new_password" id="new_password" placeholder="请输入您的新密码" required>
            </div>
            <div class="mb-3">
              <label for="confirm_password">确认新密码</label>
              <input type="password" class="form-control" name="confirm_password" id="confirm_password" placeholder="请再次输入新密码" required>
            </div>
            <button type="submit" class="btn btn-primary">更新密码</button>
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
