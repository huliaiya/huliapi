<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
if (file_exists('../config.php')) { require_once '../config.php'; } else { die("出现错误！配置文件丢失。"); }
$username = htmlspecialchars($_SESSION['admin_username']);
$page_title = '推送通知';
$current_page = basename($_SERVER['PHP_SELF']);
require_once __DIR__ . '/../common/push.php';
$feedback_msg = '';
$feedback_type = '';
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $channel = $_POST['channel'] ?? '';
        $valid_channels = ['email', 'wecom', 'dingtalk', 'feishu', 'bark', 'webhook'];
        if (in_array($channel, $valid_channels, true)) {
            $config_input = $_POST['config'] ?? [];
            $enabled = isset($_POST['enabled']) ? 1 : 0;
            $events = isset($_POST['events']) && is_array($_POST['events']) ? array_values(array_intersect($_POST['events'], ['login.notify'])) : [];
            $cfg = [];
            switch ($channel) {
                case 'email':
                    $cfg = [
                        'smtp_host' => trim($config_input['smtp_host'] ?? ''),
                        'smtp_port' => (int)($config_input['smtp_port'] ?? 465),
                        'smtp_secure' => in_array($config_input['smtp_secure'] ?? '', ['ssl', 'tls'], true) ? $config_input['smtp_secure'] : 'ssl',
                        'smtp_user' => trim($config_input['smtp_user'] ?? ''),
                        'smtp_pass' => $config_input['smtp_pass'] ?? '',
                        'from_name' => trim($config_input['from_name'] ?? 'huliapi'),
                    ];
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
            $json_cfg = json_encode($cfg, JSON_UNESCAPED_UNICODE);
            $json_events = json_encode($events, JSON_UNESCAPED_UNICODE);
            $stmt = $pdo->prepare("INSERT INTO huli_push_settings (channel, enabled, config, events) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), config = VALUES(config), events = VALUES(events)");
            $stmt->execute([$channel, $enabled, $json_cfg, $json_events]);
            $feedback_msg = '推送通道已保存。';
            $feedback_type = 'success';
        }
        if (isset($_POST['test_channel']) && in_array($_POST['test_channel'], $valid_channels, true)) {
            $test_channel = $_POST['test_channel'];
            $test_recipient = trim($_POST['test_recipient'] ?? '');
            $title = '【huliapi 推送测试】这是一条测试消息';
            $content = "**通道**：" . $test_channel . "\n**时间**：" . date('Y-m-d H:i:s') . "\n如果您收到此消息，说明该推送通道已配置正确。";
            huli_load_push_settings($pdo);
            try {
                $set = huli_load_push_settings($pdo);
                if (empty($set[$test_channel]) || !$set[$test_channel]['enabled']) {
                    $feedback_msg = '请先启用并保存此通道后再测试。';
                    $feedback_type = 'warning';
                } else {
                    $cfg = $set[$test_channel]['config'];
                    $r = ['ok' => false];
                    switch ($test_channel) {
                        case 'email':
                            if (!$test_recipient) { $feedback_msg = '邮件测试需要填写收件人邮箱。'; $feedback_type = 'warning'; break; }
                            $r = huli_push_email($cfg, $test_recipient, $title, $content); break;
                        case 'wecom':    $r = huli_push_wecom($cfg, $title, $content); break;
                        case 'dingtalk': $r = huli_push_dingtalk($cfg, $title, $content); break;
                        case 'feishu':   $r = huli_push_feishu($cfg, $title, $content); break;
                        case 'bark':     $r = huli_push_bark($cfg, $title, $content); break;
                        case 'webhook':  $r = huli_push_webhook($cfg, $title, $content); break;
                    }
                    if (empty($feedback_msg)) {
                        $feedback_msg = $r['ok'] ? ('测试发送成功（HTTP ' . ($r['code'] ?? '200') . '）') : ('测试发送失败：' . ($r['err'] ?? 'HTTP ' . ($r['code'] ?? 'N/A')));
                        $feedback_type = $r['ok'] ? 'success' : 'danger';
                    }
                }
            } catch (Throwable $e) {
                $feedback_msg = '测试异常: ' . $e->getMessage();
                $feedback_type = 'danger';
            }
        }
    }
    $channels = $pdo->query("SELECT channel, name, enabled, config, events FROM huli_push_settings ORDER BY id ASC")->fetchAll();
} catch (Exception $e) {
    $feedback_msg = '加载失败: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    $feedback_type = 'error';
    $channels = [];
}
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
    $h .= '<input type="hidden" name="channel" value="' . htmlspecialchars($ch) . '">';
    if ($ch === 'email') {
        $h .= '<div class="col-md-6"><label class="form-label">SMTP 服务器</label><input type="text" name="config[smtp_host]" class="form-control" value="' . htmlspecialchars($cfg['smtp_host'] ?? '') . '" placeholder="smtp.qq.com"></div>';
        $h .= '<div class="col-md-2"><label class="form-label">端口</label><input type="number" name="config[smtp_port]" class="form-control" value="' . (int)($cfg['smtp_port'] ?? 465) . '"></div>';
        $h .= '<div class="col-md-2"><label class="form-label">加密</label><select name="config[smtp_secure]" class="form-select"><option value="ssl" ' . (($cfg['smtp_secure'] ?? 'ssl') === 'ssl' ? 'selected' : '') . '>SSL</option><option value="tls" ' . (($cfg['smtp_secure'] ?? '') === 'tls' ? 'selected' : '') . '>TLS</option></select></div>';
        $h .= '<div class="col-md-6"><label class="form-label">发信邮箱</label><input type="email" name="config[smtp_user]" class="form-control" value="' . htmlspecialchars($cfg['smtp_user'] ?? '') . '"></div>';
        $h .= '<div class="col-md-6"><label class="form-label">密码/授权码</label><input type="password" name="config[smtp_pass]" class="form-control" value="' . htmlspecialchars($cfg['smtp_pass'] ?? '') . '"></div>';
        $h .= '<div class="col-md-6"><label class="form-label">发信人名称</label><input type="text" name="config[from_name]" class="form-control" value="' . htmlspecialchars($cfg['from_name'] ?? 'huliapi') . '"></div>';
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
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
<link rel="stylesheet" type="text/css" href="../assets/css/materialdesignicons.min.css">
<link rel="stylesheet" type="text/css" href="../assets/css/bootstrap.min.css">
<link rel="stylesheet" type="text/css" href="../assets/css/style.min.css">
</head>
<body>
<div class="container-fluid">
  <div class="row">
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-1">推送通知</h2>
                <p class="text-muted mb-0">配置邮件 / 企业微信 / 钉钉 / 飞书 / Bark / 自定义 Webhook 通道，在登录、订单、异常等事件触发时推送通知</p>
            </div>
        </div>
        <?php if ($feedback_msg): ?>
            <div class="alert alert-<?php echo $feedback_type === 'success' ? 'success' : ($feedback_type === 'warning' ? 'warning' : 'danger'); ?>"><?php echo $feedback_msg; ?></div>
        <?php endif; ?>
        <?php
        $row1 = []; $row2 = [];
        foreach ($channels as $i => $c) {
            if ($i % 2 === 0) { $row1[] = $c; } else { $row2[] = $c; }
        }
        foreach ($channels as $c) { echo huli_render_channel_card($c); }
        ?>
    </div>
  </div>
</div>
<script type="text/javascript" src="../assets/js/jquery.min.js"></script>
<script type="text/javascript" src="../assets/js/popper.min.js"></script>
<script type="text/javascript" src="../assets/js/bootstrap.min.js"></script>
</body>
</html>
