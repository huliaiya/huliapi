<?php
require_once __DIR__ . '/../../../common/session_boot.php';
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$rootPath = dirname(__DIR__, 3);
define('ROOT_PATH', $rootPath . '/');
if (!file_exists(ROOT_PATH . 'config.php')) { die("系统错误：配置文件丢失。"); }
require_once ROOT_PATH . 'config.php';
require_once ROOT_PATH . 'common/push.php';

$user_id = (int)$_SESSION['user_id'];

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
        $title = '【huliapi 推送测试】';
        $fields = [
            '测试通道' => $ch,
            '测试时间' => date('Y-m-d H:i:s'),
            '用途' => '验证推送通道是否配置正确',
        ];
        $content = "这是一条来自 huliapi 的测试消息，用于验证「" . $ch . "」推送通道是否配置正确。";
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
                        $r = huli_push_email($cfg, $recipient, $title, $content, $fields); break;
                    case 'wecom':    $r = huli_push_wecom($cfg, $title, $content, $fields); break;
                    case 'dingtalk': $r = huli_push_dingtalk($cfg, $title, $content, $fields); break;
                    case 'feishu':   $r = huli_push_feishu($cfg, $title, $content, $fields); break;
                    case 'bark':     $r = huli_push_bark($cfg, $title, $content, $fields); break;
                    case 'webhook':  $r = huli_push_webhook($cfg, $title, $content, $fields); break;
                }
                if (empty($feedback_msg)) {
                    $feedback_msg = $r['ok'] ? ('测试发送成功（HTTP ' . ($r['code'] ?? '200') . '）') : ('测试发送失败：' . ($r['err'] ?? 'HTTP ' . ($r['code'] ?? 'N/A')));
                    $feedback_type = $r['ok'] ? 'success' : 'danger';
                }
            }
        } catch (Throwable $e) {
            error_log('[push_settings.php] 测试推送异常: ' . $e->getMessage());
            $feedback_msg = '测试异常，请稍后重试。'; $feedback_type = 'danger';
        }
    } else {
        $channel = $_POST['channel'] ?? '';
        $valid = ['email','wecom','dingtalk','feishu','bark','webhook'];
        if (in_array($channel, $valid, true)) {
            $sys_channels = huli_load_push_settings($pdo);
            if (!isset($sys_channels[$channel]) || !$sys_channels[$channel]['enabled']) {
                $feedback_msg = '该通道尚未被管理员启用，无法配置。'; $feedback_type = 'warning';
            } else {
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
}

$sys_channels = huli_load_push_settings($pdo);
$stmt = $pdo->prepare("SELECT channel, name, enabled, config, events FROM huli_user_push_settings WHERE user_id = ? ORDER BY id ASC");
$stmt->execute([$user_id]);
$user_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$channels = [];
foreach ($user_rows as $r) {
    if (isset($sys_channels[$r['channel']]) && $sys_channels[$r['channel']]['enabled']) {
        $channels[] = $r;
    }
}
$sys_mail = huli_load_system_mail_config($pdo);
$mail_cfg_ok = !empty($sys_mail['smtp_host']) && !empty($sys_mail['smtp_user']) && !empty($sys_mail['smtp_pass']);

function user_channel_card($row, $mail_cfg_ok) {
    $cfg = json_decode($row['config'], true) ?: [];
    $events = json_decode($row['events'], true) ?: [];
    $enabled = (int)$row['enabled'] === 1;
    $ch = $row['channel'];
    $h = '<div class="push-card">';
    $h .= '<div class="push-card-head"><div class="push-icon">';
    $icons = [
        'email' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M22 6c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6zm-2 0l-8 5-8-5h16zm0 12H4V8l8 5 8-5v10z"/></svg>',
        'wecom' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M12 1c6.075 0 11 4.925 11 11s-4.925 11-11 11S1 18.075 1 12S5.925 1 12 1m3.52 15.49a.35.35 0 0 0-.24.1c-.14.13-.16.34.02.53l.07.07c.44.44.74.99.85 1.57c0 .02.04.23.04.23c.05.19.15.37.29.5c.21.21.51.34.82.34c.3 0 .59-.12.8-.33c.44-.44.44-1.16 0-1.61c-.15-.15-.34-.26-.53-.3l-.15-.03c-.61-.11-1.17-.41-1.62-.86c-.03-.03-.07-.07-.1-.11c-.06-.074-.16-.1-.25-.1M11 4.75c-2.117 0-4.264.77-5.75 2.31C4.111 8.246 3.5 9.72 3.5 11.24c0 1.06.3 2.12.88 3.06c.47.695.993 1.371 1.66 1.89l-.384 1.624a.6.6 0 0 0 .856.673L8.64 17.41c.53.166 1.08.234 1.63.3a8.3 8.3 0 0 0 1.7-.03l.38-.05q.283-.046.564-.112a2.33 2.33 0 0 1-.92-1.605l-.254.037c-.62.067-1.232.03-1.85-.04c-.43-.057-.838-.185-1.25-.31l-1.02.5l.23-.67l-.74-.6c-.513-.401-.917-.934-1.28-1.47c-.4-.65-.61-1.38-.61-2.11c0-1.08.456-2.119 1.26-2.97c1.158-1.198 2.854-1.78 4.5-1.78c1.54 0 3.108.513 4.24 1.58c.365.365.707.75.95 1.21c.177.354.338.722.424 1.107a2.34 2.34 0 0 1 1.811.123c-.075-.716-.33-1.4-.665-2.04c-.329-.62-.776-1.155-1.27-1.65c-1.468-1.38-3.471-2.08-5.47-2.08m9.37 9.77a1.136 1.136 0 0 0-1.1.86l-.03.15a3.1 3.1 0 0 1-.86 1.63c-.04.03-.07.07-.11.1c-.14.13-.14.35 0 .49c.07.06.17.1.26.1h.01c.07 0 .15-.02.26-.13l.07-.07c.44-.44.99-.74 1.57-.85c.023 0 .227-.04.23-.04c.2-.06.37-.16.5-.3c.44-.44.44-1.17 0-1.61c-.21-.21-.5-.33-.8-.33m-4.21-1.07c-.08 0-.16.03-.27.14l-.07.07c-.44.44-.99.74-1.57.85c-.02 0-.23.04-.23.04c-.2.06-.37.16-.5.3c-.44.44-.44 1.17 0 1.61c.21.21.51.34.82.34c.3 0 .59-.12.8-.33c.15-.16.25-.34.29-.53a.4.4 0 0 0 .03-.16c.11-.61.41-1.18.86-1.63c.03-.03.06-.06.1-.09c.146-.115.13-.36 0-.49a.34.34 0 0 0-.26-.12m1.18-1.97c-.3 0-.59.12-.8.33c-.44.44-.44 1.16 0 1.61c.15.15.34.26.53.3c.054.006.144.029.15.03c.61.12 1.17.41 1.62.86c.03.03.07.07.1.11c.08.08.16.1.25.1c.1 0 .16-.04.23-.11c.12-.13.14-.32-.02-.52l-.08-.08c-.44-.44-.74-.99-.85-1.57c0-.02-.04-.23-.04-.23c-.05-.19-.15-.37-.29-.5c-.21-.21-.5-.33-.8-.33"/></svg>',
        'dingtalk' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path fill-rule="evenodd" d="M6.802 2.02a1 1 0 0 1 .849.22l9.751 8.359a2 2 0 0 1 .235 2.799l-1.06 1.272l.87.436a1 1 0 0 1 .134 1.708l-7 5a1 1 0 0 1-1.539-1.101l1.21-4.034c-2.363-.9-3.747-3.055-4.233-5.483A1 1 0 0 1 7.01 10c-.474-.703-.86-1.42-1.134-2.149c-.649-1.73-.658-3.523.23-5.298a1 1 0 0 1 .696-.533"/></svg>',
        'feishu' => '<svg viewBox="0 0 48 48" width="20" height="20" fill="currentColor"><path fill-rule="evenodd" d="M41.072 5.994L3.31 16.52l9.075 9.294l8.414.146l9.683-9.44q-.384-.787-.384-1.318c0-.794.311-1.422.796-1.868q1.244-1.145 2.994-.342zm1.03.734L31.578 44.49l-9.294-9.075L22.137 27l9.375-9.518a2.54 2.54 0 0 0 1.664.495c.902-.05 1.485-.596 1.759-.917a2.35 2.35 0 0 0 .567-1.649a2.57 2.57 0 0 0-.52-1.464z"/></svg>',
        'bark' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>',
        'webhook' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M17 7h-4v2h4c1.65 0 3 1.35 3 3s-1.35 3-3 3h-4v2h4c2.76 0 5-2.24 5-5s-2.24-5-5-5zm-6 8H7c-1.65 0-3-1.35-3-3s1.35-3 3-3h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-2zm-3-4h8v2H8v-2z"/></svg>',
    ];
    $h .= ($icons[$ch] ?? $icons['webhook']) . '</div>';
    $h .= '<div class="push-info"><div class="push-name">' . htmlspecialchars($row['name']) . '</div>';
    $h .= '<div class="push-meta">' . ($enabled ? '<span class="push-badge on">已启用</span>' : '<span class="push-badge off">未启用</span>') . '<span class="push-key">' . $ch . '</span></div></div></div>';
    $h .= '<div class="push-card-body">';
    $h .= '<form method="post" class="push-form">';
    $h .= '<input type="hidden" name="channel" value="' . htmlspecialchars($ch) . '">';
    if ($ch === 'email') {
        $h .= '<div class="alert alert-info small mb-2">邮件通道使用系统统一邮件配置（由站点管理员在系统设置中配置），无需在此填写 SMTP 信息。启用后登录提醒会发送至您账号的注册邮箱。';
        $h .= $mail_cfg_ok ? '<br><span class="text-success">系统邮件已配置</span>' : '<br><span class="text-warning">系统邮件 SMTP 尚未配置，请联系管理员。</span>';
        $h .= '</div>';
    } elseif ($ch === 'wecom') {
        $h .= '<label>机器人 Webhook URL</label><input type="text" name="config[webhook]" value="' . htmlspecialchars($cfg['webhook'] ?? '') . '" placeholder="https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=xxx">';
    } elseif ($ch === 'dingtalk') {
        $h .= '<div class="form-row">';
        $h .= '<div><label>机器人 Webhook URL</label><input type="text" name="config[webhook]" value="' . htmlspecialchars($cfg['webhook'] ?? '') . '" placeholder="https://oapi.dingtalk.com/robot/send?access_token=xxx"></div>';
        $h .= '<div><label>加签密钥（可选）</label><input type="text" name="config[secret]" value="' . htmlspecialchars($cfg['secret'] ?? '') . '" placeholder="SEC..."></div>';
        $h .= '</div>';
    } elseif ($ch === 'feishu') {
        $h .= '<div class="form-row">';
        $h .= '<div><label>机器人 Webhook URL</label><input type="text" name="config[webhook]" value="' . htmlspecialchars($cfg['webhook'] ?? '') . '" placeholder="https://open.feishu.cn/open-apis/bot/v2/hook/xxx"></div>';
        $h .= '<div><label>签名校验（可选）</label><input type="text" name="config[secret]" value="' . htmlspecialchars($cfg['secret'] ?? '') . '"></div>';
        $h .= '</div>';
    } elseif ($ch === 'bark') {
        $h .= '<div class="form-row">';
        $h .= '<div><label>Bark 服务地址</label><input type="text" name="config[server]" value="' . htmlspecialchars($cfg['server'] ?? 'https://api.day.app') . '"></div>';
        $h .= '<div><label>Device Key</label><input type="text" name="config[device_key]" value="' . htmlspecialchars($cfg['device_key'] ?? '') . '"></div>';
        $h .= '</div>';
    } elseif ($ch === 'webhook') {
        $h .= '<div class="form-row triple">';
        $h .= '<div><label>回调地址 URL</label><input type="text" name="config[url]" value="' . htmlspecialchars($cfg['url'] ?? '') . '" placeholder="https://example.com/notify"></div>';
        $h .= '<div><label>请求方法</label><select name="config[method]"><option ' . (($cfg['method'] ?? 'POST') === 'POST' ? 'selected' : '') . '>POST</option><option ' . (($cfg['method'] ?? '') === 'GET' ? 'selected' : '') . '>GET</option><option ' . (($cfg['method'] ?? '') === 'PUT' ? 'selected' : '') . '>PUT</option></select></div>';
        $h .= '</div>';
        $h .= '<label>自定义请求头（每行一个）</label><textarea name="config[headers]" rows="2">' . htmlspecialchars($cfg['headers'] ?? '') . '</textarea>';
    }
    $h .= '<div class="events-row"><span class="evt-label">触发事件</span>';
    $h .= '<label class="evt-check"><input type="checkbox" name="events[]" value="login.notify"' . (in_array('login.notify', $events) ? ' checked' : '') . '> 登录提醒</label></div>';
    $h .= '<div class="push-actions">';
    $h .= '<label class="evt-check"><input type="checkbox" name="enabled"' . ($enabled ? ' checked' : '') . '> 启用此通道</label>';
    $h .= '<button type="submit" class="btn-save ms-auto">保存配置</button>';
    $h .= '</div></form></div>';
    if ($ch !== 'email') {
        $h .= '<div class="test-form"><form method="post" style="display:flex;gap:8px;flex:1;align-items:center;"><input type="hidden" name="test_channel" value="' . htmlspecialchars($ch) . '">';
        $h .= '<button type="submit" class="btn-test">发送测试消息</button></form></div>';
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
:root{--primary:#4f6ef7;--primary-light:#eef1ff;--primary-bg:linear-gradient(135deg,#4f6ef7,#6c8cff);--bg:#f4f6fb;--card-bg:rgba(255,255,255,.82);--text:#1e293b;--text-secondary:#64748b;--text-muted:#94a3b8;--border:#e2e8f0;--radius:14px;--shadow:0 1px 3px rgba(0,0,0,.04),0 4px 12px rgba(0,0,0,.04);}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:Inter,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;line-height:1.5;padding:0;}
.content-wrapper{padding:24px 28px;max-width:1100px;margin:0 auto;width:100%;}
.page-header{margin-bottom:20px;}
.page-header h1{font-size:24px;font-weight:700;color:var(--text);margin:0;}
.page-header .subtitle{color:var(--text-muted);font-size:13px;margin-top:4px;}
.feedback{padding:12px 16px;border-radius:12px;margin-bottom:18px;font-size:14px;font-weight:500;display:flex;align-items:center;gap:8px;}
.feedback.success{background:#ecfdf5;color:#059669;border:1px solid #a7f3d0;}
.feedback.warning{background:#fffbeb;color:#d97706;border:1px solid #fde68a;}
.feedback.danger{background:#fef2f2;color:#dc2626;border:1px solid #fecaca;}
.push-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
@media(max-width:900px){.push-grid{grid-template-columns:1fr;}}
.push-card{background:var(--card-bg);backdrop-filter:blur(12px);border-radius:var(--radius);border:1px solid rgba(255,255,255,.5);box-shadow:var(--shadow);padding:0;overflow:hidden;transition:box-shadow .2s;}
.push-card:hover{box-shadow:0 4px 16px rgba(0,0,0,.06);}
.push-card-head{display:flex;align-items:center;gap:12px;padding:16px 18px;border-bottom:1px solid var(--border);}
.push-icon{width:40px;height:40px;border-radius:10px;background:var(--primary-bg);color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.push-info{flex:1;min-width:0;}
.push-name{font-size:15px;font-weight:600;color:var(--text);}
.push-meta{display:flex;align-items:center;gap:8px;margin-top:2px;}
.push-badge{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600;}
.push-badge.on{background:#ecfdf5;color:#059669;}
.push-badge.off{background:#f1f5f9;color:#94a3b8;}
.push-key{font-size:11px;color:var(--text-muted);font-weight:500;}
.push-card-body{padding:16px 18px;}
.push-form label{display:block;font-size:13px;font-weight:600;color:var(--text);margin:10px 0 5px;}
.push-form input[type=text],.push-form input[type=email],.push-form select,.push-form textarea{width:100%;padding:8px 11px;border:1px solid var(--border);border-radius:9px;font-size:13px;font-family:inherit;background:#fff;transition:border-color .2s,box-shadow .2s;color:var(--text);}
.push-form input:focus,.push-form select:focus,.push-form textarea:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(79,110,247,.12);}
.push-form .form-row{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
.push-form .form-row.triple{grid-template-columns:1fr 1fr 1fr;}
@media(max-width:600px){.push-form .form-row,.push-form .form-row.triple{grid-template-columns:1fr;}}
.push-form .events-row{display:flex;align-items:center;gap:12px;padding-top:12px;margin-top:12px;border-top:1px dashed var(--border);}
.evt-label{font-size:13px;font-weight:600;color:var(--text);}
.evt-check{display:inline-flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;color:var(--text-secondary);}
.evt-check input[type=checkbox]{width:15px;height:15px;accent-color:var(--primary);}
.push-actions{display:flex;align-items:center;gap:10px;margin-top:14px;padding-top:12px;border-top:1px solid var(--border);}
.btn-save{padding:8px 18px;border:none;border-radius:9px;background:var(--primary-bg);color:#fff;font-weight:600;font-size:13px;cursor:pointer;transition:opacity .2s,transform .1s;}
.btn-save:hover{opacity:.9;}
.btn-save:active{transform:scale(.97);}
.ms-auto{margin-left:auto;}
.test-form{display:flex;gap:8px;align-items:center;padding:12px 18px 16px;border-top:1px solid var(--border);}
.test-form input[type=email]{flex:1;padding:7px 11px;border:1px solid var(--border);border-radius:8px;font-size:13px;background:#fff;transition:border-color .2s;}
.test-form input[type=email]:focus{outline:none;border-color:var(--primary);}
.btn-test{padding:7px 14px;border:1px solid var(--border);border-radius:8px;background:#fff;color:var(--text-secondary);font-weight:600;font-size:13px;cursor:pointer;transition:all .2s;white-space:nowrap;}
.btn-test:hover{border-color:var(--primary);color:var(--primary);background:var(--primary-light);}
.push-card .alert{border-radius:9px;font-size:13px;line-height:1.5;}
.push-card .alert-info{background:var(--primary-light);color:#4f6ef7;border:1px solid rgba(79,110,247,.15);padding:10px 12px;}
.push-card .text-success{color:#059669;font-weight:500;}
.push-card .text-warning{color:#d97706;font-weight:500;}
</style>
</head>
<body>
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
</body>
</html>
