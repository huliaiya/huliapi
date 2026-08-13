<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$rootPath = dirname(__DIR__, 3);
define('ROOT_PATH', $rootPath . '/');
if (!file_exists(ROOT_PATH . 'config.php')) { die("系统错误：配置文件丢失。"); }
require_once ROOT_PATH . 'config.php';
require_once ROOT_PATH . 'common/push.php';

$user_id = (int)$_SESSION['user_id'];
$username = $_SESSION['user_username'] ?? '';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) { die("数据库连接失败"); }
$settings = $pdo->query("SELECT setting_key, setting_value FROM huli_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$site_name = $settings['site_name'] ?? 'huliapi';

huli_ensure_user_push_defaults($pdo, $user_id);

$feedback_msg = '';
$feedback_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['test_channel'])) {
        $valid = ['email','wecom','dingtalk','feishu','bark','webhook'];
        $ch = $_POST['test_channel'];
        $recipient = trim($_POST['test_recipient'] ?? '');
        $title = '【huliapi 推送测试】这是一条测试消息';
        $content = "**通道**：" . $ch . "\n**时间**：" . date('Y-m-d H:i:s') . "\n如果您收到此消息，说明该推送通道已配置正确。";
        try {
            $set = huli_load_user_push_settings($pdo, $user_id);
            if (!in_array($ch, $valid, true) || empty($set[$ch]) || !$set[$ch]['enabled']) {
                $feedback_msg = '请先启用并保存此通道后再测试。'; $feedback_type = 'warning';
            } else {
                $cfg = $set[$ch]['config'];
                if ($ch === 'email') { $cfg = huli_load_system_mail_config($pdo); }
                $r = ['ok' => false];
                switch ($ch) {
                    case 'email':
                        if (!$recipient) { $feedback_msg = '邮件测试需要填写收件人邮箱。'; $feedback_type = 'warning'; break; }
                        if (empty($cfg['smtp_host']) || empty($cfg['smtp_user']) || empty($cfg['smtp_pass'])) { $feedback_msg = '系统邮件服务未配置，请联系管理员。'; $feedback_type = 'warning'; break; }
                        $r = huli_push_email($cfg, $recipient, $title, $content); break;
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
            $feedback_msg = '测试异常: ' . $e->getMessage(); $feedback_type = 'danger';
        }
    } else {
        $channel = $_POST['channel'] ?? '';
        $valid = ['email','wecom','dingtalk','feishu','bark','webhook'];
        if (in_array($channel, $valid, true)) {
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
            $stmt = $pdo->prepare("UPDATE huli_user_push_settings SET enabled = ?, config = ?, events = ? WHERE user_id = ? AND channel = ?");
            $stmt->execute([$enabled, json_encode($cfg, JSON_UNESCAPED_UNICODE), json_encode($events, JSON_UNESCAPED_UNICODE), $user_id, $channel]);
            $feedback_msg = '推送通道已保存。'; $feedback_type = 'success';
        }
    }
}

$channels = $pdo->prepare("SELECT channel, name, enabled, config, events FROM huli_user_push_settings WHERE user_id = ? ORDER BY id ASC");
$channels->execute([$user_id]);
$channels = $channels->fetchAll(PDO::FETCH_ASSOC);
$sys_mail = huli_load_system_mail_config($pdo);
$mail_cfg_ok = !empty($sys_mail['smtp_host']) && !empty($sys_mail['smtp_user']) && !empty($sys_mail['smtp_pass']);

function user_channel_card($row, $mail_cfg_ok) {
    $cfg = json_decode($row['config'], true) ?: [];
    $events = json_decode($row['events'], true) ?: [];
    $enabled = (int)$row['enabled'] === 1;
    $ch = $row['channel'];
    $h = '<div class="push-card">';
    $h .= '<div class="push-card-head"><div class="push-icon">';
    $icons = ['email'=>'M22 6c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6zm-2 0l-8 5-8-5h16zm0 12H4V8l8 5 8-5v10z','wecom'=>'M9.5 4C5.36 4 2 6.69 2 10c0 1.85 1.05 3.49 2.69 4.55L4 17l2.86-1.43c.81.21 1.69.34 2.61.34.2 0 .39-.01.58-.02-.18-.61-.3-1.25-.3-1.89 0-3.87 3.58-7 8-7 .34 0 .67.02 1 .05C17.71 5.13 13.97 4 9.5 4zM22 14c0-2.76-3.13-5-7-5s-7 2.24-7 5 3.13 5 7 5c.74 0 1.45-.1 2.11-.28L18 20l-.5-1.62C20.36 17.62 22 15.93 22 14z','dingtalk'=>'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm5 11h-4v4h-2v-4H7v-2h4V7h2v4h4v2z','feishu'=>'M19.5 3.5L18 2l-1.5 1.5L15 2l-1.5 1.5L12 2l-1.5 1.5L9 2 7.5 3.5 6 2v14H4v2h2v3h2v-3h8v3h2v-3h2v-2h-2V2l-1.5 1.5zM18 16H6V5h12v11z','bark'=>'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z','webhook'=>'M17 7h-4v2h4c1.65 0 3 1.35 3 3s-1.35 3-3 3h-4v2h4c2.76 0 5-2.24 5-5s-2.24-5-5-5zm-6 8H7c-1.65 0-3-1.35-3-3s1.35-3 3-3h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-2zm-3-4h8v2H8v-2z'];
    $svg = $icons[$ch] ?? $icons['webhook'];
    $h .= '<svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor"><path d="' . $svg . '"/></svg></div>';
    $h .= '<div><div class="push-name">' . htmlspecialchars($row['name']) . '</div>';
    $h .= '<div class="push-status">' . ($enabled ? '<span class="badge-on">已启用</span>' : '<span class="badge-off">未启用</span>') . '</div></div></div>';
    $h .= '<form method="post" class="push-form">';
    $h .= '<input type="hidden" name="channel" value="' . htmlspecialchars($ch) . '">';
    if ($ch === 'email') {
        $h .= '<div class="alert alert-info small mb-2">邮件通道使用系统统一邮件配置（由站点管理员在系统设置中配置），无需在此填写 SMTP 信息。启用后登录提醒会发送至您账号的注册邮箱。';
        $h .= $mail_cfg_ok ? '<br><span class="text-success">系统邮件已配置</span>' : '<br><span class="text-warning">系统邮件 SMTP 尚未配置，请联系管理员。</span>';
        $h .= '</div>';
    } elseif ($ch === 'wecom') {
        $h .= '<label>机器人 Webhook URL</label><input type="text" name="config[webhook]" value="' . htmlspecialchars($cfg['webhook'] ?? '') . '" placeholder="https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=xxx">';
    } elseif ($ch === 'dingtalk') {
        $h .= '<label>机器人 Webhook URL</label><input type="text" name="config[webhook]" value="' . htmlspecialchars($cfg['webhook'] ?? '') . '" placeholder="https://oapi.dingtalk.com/robot/send?access_token=xxx">';
        $h .= '<label>加签密钥（可选）</label><input type="text" name="config[secret]" value="' . htmlspecialchars($cfg['secret'] ?? '') . '" placeholder="SEC...">';
    } elseif ($ch === 'feishu') {
        $h .= '<label>机器人 Webhook URL</label><input type="text" name="config[webhook]" value="' . htmlspecialchars($cfg['webhook'] ?? '') . '" placeholder="https://open.feishu.cn/open-apis/bot/v2/hook/xxx">';
        $h .= '<label>签名校验（可选）</label><input type="text" name="config[secret]" value="' . htmlspecialchars($cfg['secret'] ?? '') . '">';
    } elseif ($ch === 'bark') {
        $h .= '<label>Bark 服务地址</label><input type="text" name="config[server]" value="' . htmlspecialchars($cfg['server'] ?? 'https://api.day.app') . '">';
        $h .= '<label>Device Key</label><input type="text" name="config[device_key]" value="' . htmlspecialchars($cfg['device_key'] ?? '') . '">';
    } elseif ($ch === 'webhook') {
        $h .= '<label>回调地址 URL</label><input type="text" name="config[url]" value="' . htmlspecialchars($cfg['url'] ?? '') . '" placeholder="https://example.com/notify">';
        $h .= '<label>请求方法</label><select name="config[method]"><option ' . (($cfg['method'] ?? 'POST') === 'POST' ? 'selected' : '') . '>POST</option><option ' . (($cfg['method'] ?? '') === 'GET' ? 'selected' : '') . '>GET</option><option ' . (($cfg['method'] ?? '') === 'PUT' ? 'selected' : '') . '>PUT</option></select>';
        $h .= '<label>自定义请求头（每行一个）</label><textarea name="config[headers]" rows="2">' . htmlspecialchars($cfg['headers'] ?? '') . '</textarea>';
    }
    $h .= '<div class="events-row"><label class="evt-label">订阅事件</label>';
    $h .= '<label class="evt-check"><input type="checkbox" name="events[]" value="login.notify"' . (in_array('login.notify', $events) ? ' checked' : '') . '> 登录提醒</label></div>';
    $h .= '<div class="d-flex gap-2 align-items-center mt-2">';
    $h .= '<label class="toggle"><input type="checkbox" name="enabled"' . ($enabled ? ' checked' : '') . '><span>启用此通道</span></label>';
    $h .= '<button type="submit" class="btn-save ms-auto">保存配置</button>';
    $h .= '</div></form>';
    if ($ch === 'email') {
        $h .= '<hr><form method="post" class="test-form"><input type="hidden" name="test_channel" value="email">';
        $h .= '<input type="email" name="test_recipient" placeholder="测试收件邮箱（默认发送到您的注册邮箱）">';
        $h .= '<button type="submit" class="btn-test">发送测试邮件</button></form>';
    } else {
        $h .= '<hr><form method="post" class="test-form"><input type="hidden" name="test_channel" value="' . htmlspecialchars($ch) . '">';
        $h .= '<button type="submit" class="btn-test">发送测试消息</button></form>';
    }
    $h .= '</div>';
    return $h;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>推送通知 - <?php echo htmlspecialchars($site_name); ?></title>
<?php if (!empty($settings['favicon_url'])): ?><link rel="shortcut icon" type="image/x-icon" href="<?php echo htmlspecialchars($settings['favicon_url']); ?>"><?php endif; ?>
<style>
:root{--primary-color:#3b82f6;--primary-light:#eff6ff;--bg-color:#eef5fb;--text-dark:#1f2937;--text-normal:#374151;--text-light:#6b7280;--border-color:#e5e7eb;}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:radial-gradient(circle at 18% 12%,rgba(186,224,255,.45),transparent 28rem),radial-gradient(circle at 82% 88%,rgba(196,232,240,.38),transparent 30rem),linear-gradient(135deg,#eef5fb,#f5fafd 50%,#eaf3fb);background-attachment:fixed;color:var(--text-normal);min-height:100vh;line-height:1.6;}
#page-container{display:flex;min-height:100vh;}
#sidebar{width:240px;background:rgba(255,255,255,.7);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);border-right:1px solid var(--border-color);display:flex;flex-direction:column;flex-shrink:0;}
.sidebar-header{padding:24px;border-bottom:1px solid var(--border-color);}
.sidebar-logo{font-size:22px;font-weight:700;color:var(--text-dark);text-decoration:none;}
.user-info-panel{padding:20px;text-align:center;border-bottom:1px solid var(--border-color);}
.user-info-panel .avatar{width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,#3b82f6,#60a5fa);color:#fff;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;margin:0 auto 10px;}
.user-info-panel .username{font-size:16px;font-weight:600;color:var(--text-dark);}
.sidebar-nav{padding:16px;flex-grow:1;}
.nav-link{display:flex;align-items:center;padding:12px 14px;border-radius:10px;text-decoration:none;color:var(--text-normal);font-weight:500;margin-bottom:6px;transition:all .2s;}
.nav-link:hover{background:var(--primary-light);color:var(--primary-color);transform:translateX(2px);}
.nav-link.active{background:linear-gradient(135deg,var(--primary-color),#60a5fa);color:#fff;}
.nav-link svg{margin-right:10px;flex-shrink:0;}
.sidebar-footer{padding:20px;border-top:1px solid var(--border-color);}
.btn-logout{display:block;width:100%;text-align:center;padding:12px;border-radius:10px;background:linear-gradient(135deg,#ef4444,#f87171);color:#fff;text-decoration:none;font-weight:600;transition:all .2s;}
.btn-logout:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(239,68,68,.25);}
#main-content{flex:1;display:flex;flex-direction:column;min-width:0;}
.main-header{display:flex;justify-content:space-between;align-items:center;padding:16px 32px;background:rgba(255,255,255,.85);backdrop-filter:blur(12px);border-bottom:1px solid var(--border-color);}
.content-wrapper{padding:28px 32px;max-width:1100px;margin:0 auto;width:100%;}
.page-header{margin-bottom:24px;}
.page-header h1{font-size:28px;font-weight:800;color:var(--text-dark);margin:0;}
.page-header .subtitle{color:var(--text-light);font-size:14px;margin-top:6px;}
.feedback{padding:14px 18px;border-radius:12px;margin-bottom:20px;font-size:14px;font-weight:500;}
.feedback.success{background:rgba(16,185,129,.12);color:#10b981;border:1px solid rgba(16,185,129,.25);}
.feedback.warning{background:rgba(245,158,11,.12);color:#f59e0b;border:1px solid rgba(245,158,11,.25);}
.feedback.danger{background:rgba(239,68,68,.12);color:#ef4444;border:1px solid rgba(239,68,68,.25);}
.push-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:18px;}
@media(max-width:900px){.push-grid{grid-template-columns:1fr;}}
.push-card{background:rgba(255,255,255,.78);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);border-radius:18px;border:1px solid rgba(255,255,255,.6);box-shadow:0 8px 28px rgba(64,120,180,.12);padding:22px;transition:all .3s;}
.push-card:hover{transform:translateY(-2px);box-shadow:0 14px 40px rgba(64,120,180,.18);}
.push-card-head{display:flex;align-items:center;gap:14px;margin-bottom:16px;padding-bottom:14px;border-bottom:1px solid var(--border-color);}
.push-icon{width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,#3b82f6,#60a5fa);color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.push-name{font-size:17px;font-weight:700;color:var(--text-dark);}
.push-status{margin-top:4px;font-size:12px;}
.badge-on{display:inline-block;padding:3px 9px;border-radius:8px;background:rgba(16,185,129,.12);color:#10b981;font-weight:600;}
.badge-off{display:inline-block;padding:3px 9px;border-radius:8px;background:rgba(107,114,128,.12);color:#6b7280;font-weight:600;}
.push-form label{display:block;font-size:13px;font-weight:600;color:var(--text-dark);margin:10px 0 6px;}
.push-form input[type=text],.push-form input[type=email],.push-form select,.push-form textarea{width:100%;padding:9px 12px;border:1px solid var(--border-color);border-radius:10px;font-size:14px;font-family:inherit;background:rgba(255,255,255,.85);transition:all .2s;}
.push-form input:focus,.push-form select:focus,.push-form textarea:focus{outline:none;border-color:var(--primary-color);box-shadow:0 0 0 3px rgba(59,130,246,.15);}
.push-form .events-row{display:flex;align-items:center;gap:14px;margin-top:12px;padding-top:12px;border-top:1px dashed var(--border-color);}
.evt-label{font-size:13px;font-weight:600;color:var(--text-dark);margin:0;}
.evt-check{display:flex;align-items:center;gap:6px;font-size:14px;cursor:pointer;}
.evt-check input{width:16px;height:16px;}
.toggle{display:flex;align-items:center;gap:8px;font-size:14px;font-weight:600;cursor:pointer;}
.toggle input{width:18px;height:18px;}
.btn-save{padding:9px 20px;border:none;border-radius:10px;background:linear-gradient(135deg,var(--primary-color),#60a5fa);color:#fff;font-weight:600;cursor:pointer;transition:all .2s;}
.btn-save:hover{transform:translateY(-1px);box-shadow:0 6px 16px rgba(59,130,246,.35);}
.test-form{display:flex;gap:10px;align-items:center;margin-top:14px;padding-top:14px;border-top:1px solid var(--border-color);}
.test-form input[type=email]{flex:1;padding:8px 12px;border:1px solid var(--border-color);border-radius:10px;font-size:13px;background:rgba(255,255,255,.85);}
.btn-test{padding:8px 16px;border:1px solid var(--primary-color);border-radius:10px;background:rgba(59,130,246,.1);color:var(--primary-color);font-weight:600;cursor:pointer;font-size:13px;transition:all .2s;}
.btn-test:hover{background:var(--primary-color);color:#fff;}
.alert{padding:10px 14px;border-radius:8px;font-size:13px;margin:10px 0;}
.alert-info{background:rgba(59,130,246,.1);color:#1e40af;border:1px solid rgba(59,130,246,.2);}
.text-success{color:#10b981;}
.text-warning{color:#f59e0b;}
.mobile-toggle{display:none;background:none;border:none;font-size:24px;color:var(--text-dark);cursor:pointer;}
@media(max-width:900px){#sidebar{position:fixed;left:0;top:0;bottom:0;transform:translateX(-100%);transition:transform .3s;z-index:99;}#sidebar.open{transform:translateX(0);}.mobile-toggle{display:block;}.push-grid{grid-template-columns:1fr;}}
</style>
</head>
<body>
<div id="page-container">
<aside id="sidebar">
    <div class="sidebar-header"><a href="index.php" class="sidebar-logo"><?php echo htmlspecialchars($site_name); ?></a></div>
    <div class="user-info-panel">
        <div class="avatar"><?php echo mb_strtoupper(mb_substr($username ?: 'U', 0, 1)); ?></div>
        <div class="username"><?php echo htmlspecialchars($username ?: '用户'); ?></div>
    </div>
    <nav class="sidebar-nav">
        <a href="index.php" class="nav-link"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7A1 1 0 003 11h1v6a2 2 0 002 2h2a1 1 0 001-1v-3a1 1 0 011-1h2a1 1 0 011 1v3a1 1 0 001 1h2a2 2 0 002-2v-6h1a1 1 0 00.707-1.707l-7-7z"/></svg>用户中心</a>
        <a href="login_logs.php" class="nav-link"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z"/></svg>我的登录日志</a>
        <a href="push_settings.php" class="nav-link active"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z"/></svg>推送通知</a>
    </nav>
    <div class="sidebar-footer"><a href="logout.php" class="btn-logout">安全退出</a></div>
</aside>
<div id="main-content">
    <header class="main-header">
        <button class="mobile-toggle" id="mobile-menu-btn" aria-label="menu">☰</button>
        <div style="font-size:14px;color:var(--text-light);">推送通知 / 安全提醒</div>
    </header>
    <div class="content-wrapper">
        <div class="page-header">
            <h1>推送通知</h1>
            <div class="subtitle">配置登录提醒等事件的通知渠道，启用后将在对应事件触发时推送通知</div>
        </div>
        <?php if ($feedback_msg): ?>
            <div class="feedback <?php echo htmlspecialchars($feedback_type); ?>"><?php echo htmlspecialchars($feedback_msg); ?></div>
        <?php endif; ?>
        <div class="push-grid">
            <?php foreach ($channels as $c) { echo user_channel_card($c, $mail_cfg_ok); } ?>
        </div>
    </div>
</div>
</div>
<script>
document.getElementById('mobile-menu-btn').addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('open');
});
</script>
</body>
</html>
