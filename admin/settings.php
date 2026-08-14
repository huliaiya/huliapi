<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
if (file_exists('../config.php')) { require_once '../config.php'; } else { die("出现错误！配置文件丢失。"); }
require_once __DIR__ . '/../common/push.php';
require_once __DIR__ . '/../common/turnstile.php';
$username = htmlspecialchars($_SESSION['admin_username']);
$feedback_msg = ''; $feedback_type = ''; $page_title = '系统设置';
$settings_keys = [
    'site_name', 'site_description', 'copyright_info', 'allow_registration', 'allow_temp_key',
    'temp_key_duration', 'temp_key_limit',
    'mail_smtp_host', 'mail_smtp_port', 'mail_smtp_secure', 'mail_smtp_user', 'mail_smtp_pass',
    'mail_reg_enabled', 'mail_forgot_enabled', 'turnstile_enabled', 'turnstile_site_key', 'turnstile_secret_key', 'qps_mode', 'redis_host', 'redis_port', 'redis_password', 'redis_database', 'enable_free_qps_limit', 'free_qps_seconds', 'free_qps_limit', 'enable_member_qps_limit', 'member_qps_seconds', 'member_qps_limit', 'warn_points_threshold', 'warn_balance_threshold', 'enable_warn_notification', 'enable_daily_points', 'daily_free_points', 'enable_daily_points_notification', 'icp_record_number', 'police_record_number', 'favicon_url'
];
$defaults = [
    'site_name' => 'huliapi', 'site_description' => 'huliapi致力于为用户提供稳定、高效的API接口服务，包含随机一言、工具类API等多种接口', 'copyright_info' => 'Copyright © 2025-2026 huliapi 版权所有',
    'allow_registration' => 1, 'allow_temp_key' => 1, 'temp_key_duration' => 24, 'temp_key_limit' => 100,
    'mail_smtp_host' => '', 'mail_smtp_port' => '465', 'mail_smtp_secure' => 'ssl', 'mail_smtp_user' => '', 'mail_smtp_pass' => '',
    'mail_reg_enabled' => 0, 'mail_forgot_enabled' => 0, 'turnstile_enabled' => 0,
    'turnstile_site_key' => '3x00000000000000000000FF', 'turnstile_secret_key' => '1x0000000000000000000000000000000AA',
    'qps_mode' => 'database', 'redis_host' => '127.0.0.1', 'redis_port' => 6379, 'redis_password' => '', 'redis_database' => 0,
    'enable_free_qps_limit' => 1, 'free_qps_seconds' => 1, 'free_qps_limit' => 10, 'enable_member_qps_limit' => 1, 'member_qps_seconds' => 1, 'member_qps_limit' => 20,
    'warn_points_threshold' => 5, 'warn_balance_threshold' => 0.01, 'enable_warn_notification' => 1, 'enable_daily_points' => 0, 'daily_free_points' => 100, 'enable_daily_points_notification' => 1,
    'icp_record_number' => '', 'police_record_number' => '', 'favicon_url' => ''
];
if (empty($defaults['icp_record_number'])) {
    $icp_provinces = ['京','津','沪','渝','冀','豫','云','辽','黑','湘','皖','鲁','新','苏','浙','赣','鄂','甘','晋','蒙','陕','吉','闽','贵','粤','青','藏','川','宁','琼'];
    $icp_p = $icp_provinces[array_rand($icp_provinces)];
    $icp_y = date('Y');
    $icp_n = mt_rand(1000000, 99999999);
    $defaults['icp_record_number'] = $icp_p . 'ICP备' . $icp_y . str_pad((string)$icp_n, 8, '0', STR_PAD_LEFT) . '号';
}
if (empty($defaults['police_record_number'])) {
    $police_provinces = ['京','津','沪','渝','冀','豫','云','辽','黑','湘','皖','鲁','新','苏','浙','赣','鄂','甘','晋','蒙','陕','吉','闽','贵','粤','青','藏','川','宁','琼'];
    $police_p = $police_provinces[array_rand($police_provinces)];
    $police_a = mt_rand(10, 99);
    $police_b = mt_rand(100000, 999999);
    $defaults['police_record_number'] = $police_p . '公网安备 ' . $police_a . str_pad((string)$police_b, 6, '0', STR_PAD_LEFT) . '号';
}
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $init_sql = "INSERT IGNORE INTO huli_settings (setting_key, setting_value) VALUES ('site_name', 'huliapi'), ('site_description', 'huliapi致力于为用户提供稳定、高效的API接口服务，包含随机一言、工具类API等多种接口'), ('copyright_info', 'Copyright © 2025-2026 huliapi 版权所有'), ('allow_registration', '1'), ('allow_temp_key', '1'), ('temp_key_duration', '24'), ('temp_key_limit', '100'), ('mail_smtp_host', ''), ('mail_smtp_port', '465'), ('mail_smtp_secure', 'ssl'), ('mail_smtp_user', ''), ('mail_smtp_pass', ''), ('mail_reg_enabled', '0'), ('mail_forgot_enabled', '0'), ('turnstile_enabled', '0'), ('turnstile_site_key', '3x00000000000000000000FF'), ('turnstile_secret_key', '1x0000000000000000000000000000000AA'), ('qps_mode', 'database'), ('redis_host', '127.0.0.1'), ('redis_port', '6379'), ('redis_password', ''), ('redis_database', '0'), ('enable_free_qps_limit', '1'), ('free_qps_seconds', '1'), ('free_qps_limit', '10'), ('enable_member_qps_limit', '1'), ('member_qps_seconds', '1'), ('member_qps_limit', '20'), ('warn_points_threshold', '5'), ('warn_balance_threshold', '0.01'), ('enable_warn_notification', '1'), ('enable_daily_points', '0'), ('daily_free_points', '100'), ('enable_daily_points_notification', '1'), ('icp_record_number', ''), ('police_record_number', ''), ('favicon_url', '');";
    $pdo->exec($init_sql);
    $push_feedback_msg = '';
    $push_feedback_type = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['test_channel'])) {
            $valid_channels = ['email', 'wecom', 'dingtalk', 'feishu', 'bark', 'webhook'];
            $test_channel = $_POST['test_channel'];
            $test_recipient = trim($_POST['test_recipient'] ?? '');
            $title = '【huliapi 推送测试】';
            $fields = ['测试通道' => $test_channel, '测试时间' => date('Y-m-d H:i:s'), '用途' => '验证推送通道是否配置正确'];
            $content = "这是一条来自 huliapi 的测试消息，用于验证「" . $test_channel . "」推送通道是否配置正确。";
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
                            $r = huli_push_email($cfg, $test_recipient, $title, $content, $fields); break;
                        case 'wecom':    $r = huli_push_wecom($cfg, $title, $content, $fields); break;
                        case 'dingtalk': $r = huli_push_dingtalk($cfg, $title, $content, $fields); break;
                        case 'feishu':   $r = huli_push_feishu($cfg, $title, $content, $fields); break;
                        case 'bark':     $r = huli_push_bark($cfg, $title, $content, $fields); break;
                        case 'webhook':  $r = huli_push_webhook($cfg, $title, $content, $fields); break;
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
        } elseif (isset($_POST['channel'])) {
            $channel = $_POST['channel'];
            $valid_channels = ['email', 'wecom', 'dingtalk', 'feishu', 'bark', 'webhook'];
            if (in_array($channel, $valid_channels, true)) {
                $config_input = $_POST['config'] ?? [];
                $enabled = isset($_POST['enabled']) ? 1 : 0;
                $events = isset($_POST['events']) && is_array($_POST['events']) ? array_values(array_intersect($_POST['events'], ['login.notify'])) : [];
                $cfg = [];
                switch ($channel) {
                    case 'email': $cfg = []; break;
                    case 'wecom': $cfg = ['webhook' => trim($config_input['webhook'] ?? '')]; break;
                    case 'dingtalk': $cfg = ['webhook' => trim($config_input['webhook'] ?? ''), 'secret' => trim($config_input['secret'] ?? '')]; break;
                    case 'feishu': $cfg = ['webhook' => trim($config_input['webhook'] ?? ''), 'secret' => trim($config_input['secret'] ?? '')]; break;
                    case 'bark': $cfg = ['server' => trim($config_input['server'] ?? 'https://api.day.app'), 'device_key' => trim($config_input['device_key'] ?? '')]; break;
                    case 'webhook': $cfg = ['url' => trim($config_input['url'] ?? ''), 'method' => in_array(strtoupper($config_input['method'] ?? 'POST'), ['POST','GET','PUT'], true) ? strtoupper($config_input['method']) : 'POST', 'headers' => trim($config_input['headers'] ?? '')]; break;
                }
                $stmt = $pdo->prepare("INSERT INTO huli_push_settings (channel, enabled, config, events) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), config = VALUES(config), events = VALUES(events)");
                $stmt->execute([$channel, $enabled, json_encode($cfg, JSON_UNESCAPED_UNICODE), json_encode($events, JSON_UNESCAPED_UNICODE)]);
                $push_feedback_msg = '推送通道已保存。';
                $push_feedback_type = 'success';
            }
        } else {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE huli_settings SET setting_value = ? WHERE setting_key = ?");
        $bool_keys = ['allow_registration', 'allow_temp_key', 'mail_reg_enabled', 'mail_forgot_enabled', 'turnstile_enabled', 'enable_free_qps_limit', 'enable_member_qps_limit', 'enable_warn_notification', 'enable_daily_points', 'enable_daily_points_notification'];
        // 未勾选的 checkbox 不会出现在 $_POST 中，只靠 array_key_exists 判断会导致开关永远关不掉。
        // 各表单通过隐藏字段 checkbox_keys[] 声明本次提交包含哪些开关，未声明的开关不做改动。
        $posted_checkbox_keys = (isset($_POST['checkbox_keys']) && is_array($_POST['checkbox_keys'])) ? $_POST['checkbox_keys'] : [];
        foreach ($settings_keys as $key) {
            if (in_array($key, $bool_keys, true)) {
                if (!in_array($key, $posted_checkbox_keys, true)) continue;
                $value = isset($_POST[$key]) ? '1' : '0';
            } else {
                if (!array_key_exists($key, $_POST)) continue;
                $value = trim($_POST[$key]);
            }
            $stmt->execute([$value, $key]);
        }
        $pdo->commit();
        $feedback_msg = '设置已成功保存。';
        $feedback_type = 'success';
        }
    }
    $stmt_get = $pdo->query("SELECT setting_key, setting_value FROM huli_settings");
    $db_settings = $stmt_get->fetchAll(PDO::FETCH_KEY_PAIR);
    $settings = array_merge($defaults, $db_settings);
    $channels = [];
    try {
        $channels = $pdo->query("SELECT channel, name, enabled, config, events FROM huli_push_settings ORDER BY id ASC")->fetchAll();
    } catch (Throwable $e) {}
    $sys_mail = huli_load_system_mail_config($pdo);
    $mail_cfg_ok = !empty($sys_mail['smtp_host']) && !empty($sys_mail['smtp_user']) && !empty($sys_mail['smtp_pass']);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    $feedback_msg = '操作失败: ' . $e->getMessage(); $feedback_type = 'error';
}
$current_page = basename($_SERVER['PHP_SELF']);

function huli_render_channel_card_settings($row) {
    $cfg = json_decode($row['config'], true) ?: [];
    $events = json_decode($row['events'], true) ?: [];
    $enabled = (int)$row['enabled'] === 1;
    $ch = $row['channel'];
    $h = '<div class="card shadow-sm mb-3"><div class="card-body">';
    $h .= '<div class="d-flex align-items-center justify-content-between mb-3">';
    $h .= '<div class="d-flex align-items-center">';
    $icons = [
        'email' => '<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M22 6c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6zm-2 0l-8 5-8-5h16zm0 12H4V8l8 5 8-5v10z"/></svg>',
        'wecom' => '<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M12 1c6.075 0 11 4.925 11 11s-4.925 11-11 11S1 18.075 1 12S5.925 1 12 1m0 3.75c-2.117 0-4.264.77-5.75 2.31C4.111 8.246 3.5 9.72 3.5 11.24c0 1.06.3 2.12.88 3.06c.47.695.993 1.371 1.66 1.89l-.384 1.624a.6.6 0 0 0 .856.673L8.64 17.41c.53.166 1.08.234 1.63.3a8.3 8.3 0 0 0 1.7-.03l.38-.05a3.5 3.5 0 0 1-.36-1.45c0-2.15 1.77-3.9 3.95-3.9c.15 0 .3.01.45.03c-.135-2.99-2.7-5.32-6.21-5.32m6.6 6.27a2.64 2.64 0 0 0-.6 5.21c-.036.44.006.887.125 1.31a.6.6 0 0 0 .865.38l.5-.24c.39.13.8.25 1.27.25c1.73 0 3.13-1.21 3.13-2.7s-1.4-2.7-3.13-2.7c-.37 0-.72.07-1.16.23m-1.72 1.4c0-.42.36-.77.8-.77s.8.35.8.77s-.36.77-.8.77s-.8-.35-.8-.77m4.63 0c-.44 0-.8.35-.8.77s.36.77.8.77s.8-.35.8-.77s-.36-.77-.8-.77"/></svg>',
        'dingtalk' => '<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path fill-rule="evenodd" d="M6.802 2.02a1 1 0 0 1 .849.22l9.751 8.359a2 2 0 0 1 .235 2.799l-1.06 1.272l.87.436a1 1 0 0 1 .134 1.708l-7 5a1 1 0 0 1-1.539-1.101l1.21-4.034c-2.363-.9-3.747-3.055-4.233-5.483A1 1 0 0 1 7.01 10c-.474-.703-.86-1.42-1.134-2.149c-.649-1.73-.658-3.523.23-5.298a1 1 0 0 1 .696-.533"/></svg>',
        'feishu' => '<svg viewBox="0 0 48 48" width="24" height="24" fill="currentColor"><path fill-rule="evenodd" d="M41.072 5.994L3.31 16.52l9.075 9.294l8.414.146l9.683-9.44q-.384-.787-.384-1.318c0-.794.311-1.422.796-1.868q1.244-1.145 2.994-.342zm1.03.734L31.578 44.49l-9.294-9.075L22.137 27l9.375-9.518a2.54 2.54 0 0 0 1.664.495c.902-.05 1.485-.596 1.759-.917a2.35 2.35 0 0 0 .567-1.649a2.57 2.57 0 0 0-.52-1.464z"/></svg>',
        'bark' => '<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>',
        'webhook' => '<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M17 7h-4v2h4c1.65 0 3 1.35 3 3s-1.35 3-3 3h-4v2h4c2.76 0 5-2.24 5-5s-2.24-5-5-5zm-6 8H7c-1.65 0-3-1.35-3-3s1.35-3 3-3h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-2zm-3-4h8v2H8v-2z"/></svg>',
    ];
    $h .= '<div class="me-3 d-flex align-items-center justify-content-center rounded" style="width:48px;height:48px;background:rgba(108,182,255,.16);color:#2879ba;">' . ($icons[$ch] ?? $icons['webhook']) . '</div>';
    $h .= '<div><div class="fw-bold fs-5">' . htmlspecialchars($row['name']) . ' <small class="text-muted">(' . $ch . ')</small></div>';
    $h .= '<small class="text-muted">' . ($enabled ? '<span class="badge bg-success">已启用</span>' : '<span class="badge bg-secondary">未启用</span>') . '</small></div>';
    $h .= '</div></div>';
    $h .= '<form method="post" class="row g-3">';
    $h .= '<input type="hidden" name="channel" value="' . htmlspecialchars($ch) . '">';
    if ($ch === 'email') {
        $h .= '<div class="col-md-12"><div class="alert alert-info mb-0 py-2"><i class="mdi mdi-information-outline me-1"></i>邮件通道使用系统统一邮件配置（系统设置 → 邮件设置），无需在此填写 SMTP 信息。启用本通道后，登录提醒将发送到管理员邮箱。';
        $h .= empty($GLOBALS['mail_cfg_ok_settings']) ? '<br><span class="text-warning"><i class="mdi mdi-alert-outline"></i> 系统邮件 SMTP 尚未配置，请先前往【邮件设置】完善。</span>' : '<br><span class="text-success"><i class="mdi mdi-check-circle-outline"></i> 系统邮件已配置</span>';
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
    $h .= '<div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="events[]" value="login.notify" id="evt_s_' . $ch . '_login"' . (in_array('login.notify', $events) ? ' checked' : '') . '><label class="form-check-label" for="evt_s_' . $ch . '_login">登录提醒（管理员 / 用户登录时触发）</label></div>';
    $h .= '</div>';
    $h .= '<div class="col-md-12 d-flex gap-2 align-items-center">';
    $h .= '<div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="enabled" id="en_s_' . $ch . '"' . ($enabled ? ' checked' : '') . '><label class="form-check-label" for="en_s_' . $ch . '">启用此通道</label></div>';
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
$GLOBALS['mail_cfg_ok_settings'] = $mail_cfg_ok ?? false;
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
        <header class="card-header"><div class="card-title">系统设置</div></header>
        <div class="card-body">
          <ul class="nav nav-tabs">
            <li class="nav-item">
              <button class="nav-link active" id="basic-config" data-bs-toggle="tab" data-bs-target="#config" type="button">基本设置</button>
            </li>
            <li class="nav-item">
              <button class="nav-link" id="basic-function" data-bs-toggle="tab" data-bs-target="#function" type="button">功能设置</button>
            </li>
            <li class="nav-item">
              <button class="nav-link" id="basic-mail" data-bs-toggle="tab" data-bs-target="#mail" type="button">邮件设置</button>
            </li>
            <li class="nav-item">
              <button class="nav-link" id="basic-turnstile" data-bs-toggle="tab" data-bs-target="#turnstile" type="button">人机验证</button>
            </li>
          </ul>
            <div class="tab-content">
              <div class="tab-pane fade show active" id="config" aria-labelledby="basic-config">
                <form method="POST" action="settings.php" class="edit-form">
                <?php if ($feedback_msg): ?>
                <div class="alert alert-<?php echo $feedback_type === 'success' ? 'success' : 'danger'; ?> mb-3">
                  <?php echo htmlspecialchars($feedback_msg); ?>
                </div>
                <?php endif; ?>
                <div class="mb-3">
                  <label for="site_name" class="form-label">网站标题</label>
                  <input class="form-control" type="text" id="site_name" name="site_name" value="<?php echo htmlspecialchars($settings['site_name']); ?>" placeholder="请输入站点标题">
                </div>
                <div class="mb-3">
                  <label for="site_description" class="form-label">网站描述</label>
                  <textarea class="form-control" id="site_description" rows="5" name="site_description" placeholder="请输入站点描述"><?php echo htmlspecialchars($settings['site_description']); ?></textarea>
                </div>
                <div class="mb-3">
                  <label for="copyright_info" class="form-label">版权信息</label>
                  <input class="form-control" type="text" id="copyright_info" name="copyright_info" value="<?php echo htmlspecialchars($settings['copyright_info']); ?>" placeholder="请输入版权信息">
                </div>
                <div class="mb-3">
                  <label for="icp_record_number" class="form-label">ICP备案号</label>
                  <input class="form-control" type="text" id="icp_record_number" name="icp_record_number" value="<?php echo htmlspecialchars($settings['icp_record_number'] ?? ''); ?>" placeholder="例如：沪ICP备2023019171号-6">
                </div>
                <div class="mb-3">
                  <label for="police_record_number" class="form-label">公安备案号</label>
                  <input class="form-control" type="text" id="police_record_number" name="police_record_number" value="<?php echo htmlspecialchars($settings['police_record_number'] ?? ''); ?>" placeholder="例如：京公网安备 11010102000000号">
                  <small class="form-text text-muted">留空则使用默认随机生成的备案号</small>
                </div>
                <div class="mb-3">
                  <label for="favicon_url" class="form-label">网站图标 (Favicon)</label>
                  <input class="form-control" type="url" id="favicon_url" name="favicon_url" value="<?php echo htmlspecialchars($settings['favicon_url'] ?? ''); ?>" placeholder="https://example.com/favicon.ico">
                  <small class="form-text text-muted">留空则使用浏览器默认图标</small>
                </div>
                <div>
                  <button type="submit" class="btn btn-primary me-1">保存设置</button>
                </div>
                </form>
                <hr class="my-4">
                <header class="mb-3"><h5 class="fw-bold"><i class="mdi mdi-bell-ring-outline me-1"></i>推送通知（管理员）</h5><p class="text-muted small mb-0">配置邮件 / 企业微信 / 钉钉 / 飞书 / Bark / 自定义 Webhook 通道，在登录提醒等事件触发时推送通知到管理员。用户可在前台各自独立配置自己的推送通道。</p></header>
                <?php if ($push_feedback_msg): ?>
                <div class="alert alert-<?php echo $push_feedback_type === 'success' ? 'success' : ($push_feedback_type === 'warning' ? 'warning' : 'danger'); ?> mb-3">
                  <?php echo htmlspecialchars($push_feedback_msg); ?>
                </div>
                <?php endif; ?>
                <?php foreach ($channels as $c) { echo huli_render_channel_card_settings($c); } ?>
              </div>
              <div class="tab-pane fade" id="function" aria-labelledby="basic-function">
                <form method="POST" action="settings.php" class="edit-form">
                <input type="hidden" name="checkbox_keys[]" value="allow_registration">
                <input type="hidden" name="checkbox_keys[]" value="allow_temp_key">
                <input type="hidden" name="checkbox_keys[]" value="enable_free_qps_limit">
                <input type="hidden" name="checkbox_keys[]" value="enable_member_qps_limit">
                <input type="hidden" name="checkbox_keys[]" value="enable_warn_notification">
                <input type="hidden" name="checkbox_keys[]" value="enable_daily_points">
                <input type="hidden" name="checkbox_keys[]" value="enable_daily_points_notification">
                <div class="mb-3">
                  <label class="form-label">允许用户注册</label>
                  <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input" id="allow_registration" name="allow_registration" value="1" <?php echo $settings['allow_registration'] ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="allow_registration"></label>
                  </div>
                  <small class="form-text">关闭后，前台将注册入口禁止访问</small>
                </div>
                <div class="mb-3">
                  <label class="form-label">允许申请临时密钥</label>
                  <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input" id="allow_temp_key" name="allow_temp_key" value="1" <?php echo $settings['allow_temp_key'] ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="allow_temp_key"></label>
                  </div>
                  <small class="form-text">关闭后，前台将隐藏申请临时密钥的入口</small>
                </div>
                <div class="mb-3">
                  <label for="temp_key_duration" class="form-label">临时密钥有效时间 (小时)</label>
                  <input class="form-control" type="number" id="temp_key_duration" name="temp_key_duration" value="<?php echo htmlspecialchars($settings['temp_key_duration']); ?>" placeholder="请输入有效时间">
                </div>
                <div class="mb-3">
    <label class="form-label">启用无会员QPS限制</label>
    <div class="form-check form-switch">
        <input type="checkbox" class="form-check-input" id="enable_free_qps_limit" name="enable_free_qps_limit" value="1" <?php echo $settings['enable_free_qps_limit'] ? 'checked' : ''; ?>>
        <label class="form-check-label" for="enable_free_qps_limit"></label>
    </div>
    <small class="form-text">开启后，系统将对未登录用户实施QPS限制</small>
</div>
                <div class="mb-3">
                  <label for="free_qps_seconds" class="form-label">无会员QPS限制秒数</label>
                  <input class="form-control" type="number" id="free_qps_seconds" name="free_qps_seconds" value="<?php echo htmlspecialchars($settings['free_qps_seconds']); ?>" placeholder="请输入无会员QPS限制秒数">
                  <small class="form-text">未登录用户的QPS限制时间窗口（秒）</small>
                </div>
                <div class="mb-3">
                  <label for="free_qps_limit" class="form-label">无会员QPS限制请求数</label>
                  <input class="form-control" type="number" id="free_qps_limit" name="free_qps_limit" value="<?php echo htmlspecialchars($settings['free_qps_limit']); ?>" placeholder="请输入无会员QPS限制请求数">
                  <small class="form-text">未登录用户在限制时间窗口内的最大请求数</small>
                </div>
                <div class="mb-3">
    <label class="form-label">启用普通会员QPS限制</label>
    <div class="form-check form-switch">
        <input type="checkbox" class="form-check-input" id="enable_member_qps_limit" name="enable_member_qps_limit" value="1" <?php echo $settings['enable_member_qps_limit'] ? 'checked' : ''; ?>>
        <label class="form-check-label" for="enable_member_qps_limit"></label>
    </div>
                 <small class="form-text">开启后，系统将对普通会员实施QPS限制</small>
</div>
                <div class="mb-3">
                  <label for="qps_mode" class="form-label">接口限速模式</label>
                  <select class="form-select" id="qps_mode" name="qps_mode">
                    <option value="database" <?php echo ($settings['qps_mode'] ?? 'database') === 'database' ? 'selected' : ''; ?>>系统自带（数据库）</option>
                    <option value="redis" <?php echo ($settings['qps_mode'] ?? 'database') === 'redis' ? 'selected' : ''; ?>>Redis</option>
                  </select>
                  <small class="form-text">Redis 模式需要 PHP Redis 扩展和可用的 Redis 服务，连接失败时自动回退到数据库模式。</small>
                </div>
                <div class="mb-3">
                  <label for="redis_host" class="form-label">Redis 地址</label>
                  <input class="form-control" type="text" id="redis_host" name="redis_host" value="<?php echo htmlspecialchars($settings['redis_host'] ?? '127.0.0.1'); ?>">
                </div>
                <div class="mb-3">
                  <label for="redis_port" class="form-label">Redis 端口</label>
                  <input class="form-control" type="number" id="redis_port" name="redis_port" value="<?php echo htmlspecialchars($settings['redis_port'] ?? 6379); ?>">
                </div>
                <div class="mb-3">
                  <label for="redis_password" class="form-label">Redis 密码</label>
                  <input class="form-control" type="password" id="redis_password" name="redis_password" value="<?php echo htmlspecialchars($settings['redis_password'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                  <label for="redis_database" class="form-label">Redis 数据库编号</label>
                  <input class="form-control" type="number" min="0" id="redis_database" name="redis_database" value="<?php echo htmlspecialchars($settings['redis_database'] ?? 0); ?>">
                </div>
                <div class="mb-3">
                  <label for="member_qps_seconds" class="form-label">普通会员QPS限制秒数</label>
                  <input class="form-control" type="number" id="member_qps_seconds" name="member_qps_seconds" value="<?php echo htmlspecialchars($settings['member_qps_seconds']); ?>" placeholder="请输入普通会员QPS限制秒数">
                  <small class="form-text">普通会员的QPS限制时间窗口（秒）</small>
                </div>
                <div class="mb-3">
                  <label for="member_qps_limit" class="form-label">普通会员QPS限制请求数</label>
                  <input class="form-control" type="number" id="member_qps_limit" name="member_qps_limit" value="<?php echo htmlspecialchars($settings['member_qps_limit']); ?>" placeholder="请输入普通会员QPS限制请求数">
                  <small class="form-text">普通会员在限制时间窗口内的最大请求数</small>
                </div>
                <div class="mb-3">
                  <label for="temp_key_limit" class="form-label">临时密钥调用次数限制</label>
                  <input class="form-control" type="number" id="temp_key_limit" name="temp_key_limit" value="<?php echo htmlspecialchars($settings['temp_key_limit']); ?>" placeholder="请输入调用次数限制">
                </div>
                <div class="mb-3">
    <label class="form-label">启用余额/点数预警提醒</label>
    <div class="form-check form-switch">
        <input type="checkbox" class="form-check-input" id="enable_warn_notification" name="enable_warn_notification" value="1" <?php echo $settings['enable_warn_notification'] ? 'checked' : ''; ?>>
        <label class="form-check-label" for="enable_warn_notification"></label>
    </div>
    <small class="form-text">开启后，当用户余额或点数低于预警阈值时发送邮件提醒</small>
</div>
                <div class="mb-3">
                  <label for="warn_points_threshold" class="form-label">点数预警阈值</label>
                  <input class="form-control" type="number" id="warn_points_threshold" name="warn_points_threshold" value="<?php echo htmlspecialchars($settings['warn_points_threshold']); ?>" placeholder="请输入点数预警阈值">
                  <small class="form-text">当用户点数低于此值时发送邮件提醒（设为0或负数则不提醒）</small>
                </div>
                <div class="mb-3">
                  <label for="warn_balance_threshold" class="form-label">余额预警阈值（元）</label>
                  <input class="form-control" type="number" step="0.01" id="warn_balance_threshold" name="warn_balance_threshold" value="<?php echo htmlspecialchars($settings['warn_balance_threshold']); ?>" placeholder="请输入余额预警阈值">
                  <small class="form-text">当用户余额低于此值时发送邮件提醒（设为0或负数则不提醒）</small>
                </div>
                <div class="mb-3">
    <label class="form-label">启用每日赠送点数</label>
    <div class="form-check form-switch">
        <input type="checkbox" class="form-check-input" id="enable_daily_points" name="enable_daily_points" value="1" <?php echo $settings['enable_daily_points'] ? 'checked' : ''; ?>>
        <label class="form-check-label" for="enable_daily_points"></label>
    </div>
    <small class="form-text">开启后，系统每天自动赠送点数给普通会员</small>
</div>
                <div class="mb-3">
                  <label for="daily_free_points" class="form-label">每日赠送点数数量</label>
                  <input class="form-control" type="number" id="daily_free_points" name="daily_free_points" value="<?php echo htmlspecialchars($settings['daily_free_points']); ?>" placeholder="请输入每日赠送点数数量">
                 <small class="form-text">每天自动赠送给普通会员的点数数量</small>
                </div>
                <div class="mb-3">
                  <label class="form-label">每日赠送点数邮件通知</label>
                  <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input" id="enable_daily_points_notification" name="enable_daily_points_notification" value="1" <?php echo ($settings['enable_daily_points_notification'] ?? 1) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="enable_daily_points_notification"></label>
                  </div>
                  <small class="form-text">赠送成功后向用户邮箱发送到账通知，SMTP 配置有效时生效。</small>
                </div>
                <div>
                  <button type="submit" class="btn btn-primary me-1">保存设置</button>
                </div>
                </form>
              </div>
              <div class="tab-pane fade" id="mail" aria-labelledby="basic-mail">
                <form method="POST" action="settings.php" class="edit-form">
                <input type="hidden" name="checkbox_keys[]" value="mail_reg_enabled">
                <input type="hidden" name="checkbox_keys[]" value="mail_forgot_enabled">
                <div class="mb-3">
                  <label class="form-label">开启邮件验证码注册</label>
                  <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input" id="mail_reg_enabled" name="mail_reg_enabled" value="1" <?php echo $settings['mail_reg_enabled'] ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="mail_reg_enabled"></label>
                  </div>
                  <small class="form-text">开启后，用户注册时必须通过邮箱验证码</small>
                </div>
                <div class="mb-3">
                  <label class="form-label">开启邮件找回密码</label>
                  <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input" id="mail_forgot_enabled" name="mail_forgot_enabled" value="1" <?php echo $settings['mail_forgot_enabled'] ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="mail_forgot_enabled"></label>
                  </div>
                  <small class="form-text">开启后，用户可以通过邮箱重置密码</small>
                </div>
                <div class="mb-3">
                  <label for="mail_smtp_host" class="form-label">SMTP 服务器</label>
                  <input class="form-control" type="text" id="mail_smtp_host" name="mail_smtp_host" value="<?php echo htmlspecialchars($settings['mail_smtp_host']); ?>" placeholder="例如：smtp.qq.com">
                </div>
                <div class="mb-3">
                  <label for="mail_smtp_port" class="form-label">端口</label>
                  <input class="form-control" type="number" id="mail_smtp_port" name="mail_smtp_port" value="<?php echo htmlspecialchars($settings['mail_smtp_port']); ?>" placeholder="例如：465">
                </div>
                <div class="mb-3">
                  <label for="mail_smtp_secure" class="form-label">加密方式</label>
                  <select class="form-select" id="mail_smtp_secure" name="mail_smtp_secure">
                    <option value="ssl" <?php echo $settings['mail_smtp_secure'] === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                    <option value="tls" <?php echo $settings['mail_smtp_secure'] === 'tls' ? 'selected' : ''; ?>>TLS</option>
                  </select>
                </div>
                <div class="mb-3">
                  <label for="mail_smtp_user" class="form-label">发信邮箱账户</label>
                  <input class="form-control" type="email" id="mail_smtp_user" name="mail_smtp_user" value="<?php echo htmlspecialchars($settings['mail_smtp_user']); ?>" placeholder="例如：yourname@qq.com">
                </div>
                <div class="mb-3">
                  <label for="mail_smtp_pass" class="form-label">发信邮箱密码/授权码</label>
                  <input class="form-control" type="password" id="mail_smtp_pass" name="mail_smtp_pass" value="<?php echo htmlspecialchars($settings['mail_smtp_pass']); ?>" placeholder="请输入密码或授权码">
                </div>
                <div class="d-flex justify-content-between align-items-center">
                  <a href="mail_test.php" class="text-primary">发送测试邮件...</a>
                  <button type="submit" class="btn btn-primary me-1">保存设置</button>
                </div>
                </form>
              </div>
              <div class="tab-pane fade" id="turnstile" aria-labelledby="basic-turnstile">
                <form method="POST" action="settings.php" class="edit-form">
                <input type="hidden" name="checkbox_keys[]" value="turnstile_enabled">
                <?php
                $ts_site = trim((string)($settings['turnstile_site_key'] ?? ''));
                $ts_secret = trim((string)($settings['turnstile_secret_key'] ?? ''));
                $ts_site_is_test = $ts_site !== '' && strpos(HULI_TURNSTILE_TEST_SITE_KEYS, '|' . $ts_site . '|') !== false;
                $ts_secret_is_test = $ts_secret !== '' && strpos(HULI_TURNSTILE_TEST_SECRET_KEYS, '|' . $ts_secret . '|') !== false;
                ?>
                <?php if ($ts_site !== '' && $ts_secret !== '' && $ts_site_is_test !== $ts_secret_is_test): ?>
                <div class="alert alert-danger mb-3">
                  当前 Site Key 与 Secret Key 一个是 Cloudflare 测试密钥、一个是正式密钥。两者必须成对使用，否则校验必然返回 invalid-input-response。
                </div>
                <?php elseif ($ts_site_is_test && $ts_secret_is_test): ?>
                <div class="alert alert-warning mb-3">
                  当前使用的是 Cloudflare 官方测试密钥，仅用于本地联调，不具备任何防护能力。上线前请替换为自己的正式密钥。
                </div>
                <?php endif; ?>
                <div class="mb-3 form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="turnstile_enabled" name="turnstile_enabled" value="1" <?php echo $settings['turnstile_enabled'] ? 'checked' : ''; ?>>
                  <label class="form-check-label" for="turnstile_enabled">启用人机验证 (Cloudflare Turnstile)</label>
                  <div class="form-text">开启后需填写下方两个 Key，并使用 Cloudflare Turnstile 校验。</div>
                </div>
                <div class="mb-3">
                  <label for="turnstile_site_key" class="form-label">Turnstile Site Key</label>
                  <input class="form-control" type="text" id="turnstile_site_key" name="turnstile_site_key" value="<?php echo htmlspecialchars($settings['turnstile_site_key'] ?? ''); ?>" placeholder="0x4AAAAAA...">
                </div>
                <div class="mb-3">
                  <label for="turnstile_secret_key" class="form-label">Turnstile Secret Key</label>
                  <input class="form-control" type="text" id="turnstile_secret_key" name="turnstile_secret_key" value="<?php echo htmlspecialchars($settings['turnstile_secret_key'] ?? ''); ?>" placeholder="0x4AAAAAA...">
                </div>
                <div class="alert alert-info mb-3">
                  <div class="fw-bold mb-2">配置要点</div>
                  <ol class="mb-0 ps-3">
                    <li>在 https://dash.cloudflare.com 的 Turnstile 中创建站点，Site Key 与 Secret Key 必须来自同一个站点。</li>
                    <li>在该站点的 Hostname Management 中添加本站实际访问域名，否则前端会报错误码 110200。仅通过 IP 或未授权域名访问时验证一定失败。</li>
                    <li>两个 Key 请直接粘贴，不要带空格或换行；保存后会自动去除首尾空白。</li>
                    <li>校验令牌有效期 300 秒且只能使用一次，页面停留过久时组件会自动刷新，无需手动处理。</li>
                    <li>失败时前端会显示 Cloudflare 返回的具体原因，服务端同时写入 PHP error_log，可据此排查。</li>
                  </ol>
                </div>
                <small class="form-text text-muted d-block mb-3">需先勾选启用开关并填写两个 Key，后台与用户端登录/注册/重置密码/申请临时密钥才会使用 Turnstile 校验。</small>
                <div class="d-flex align-items-center mb-2">
                  <button type="submit" class="btn btn-primary me-1">保存设置</button>
                  <button type="button" id="ts-test-keys-btn" class="btn btn-outline-info ms-2">
                    <i class="mdi mdi-shield-check-outline"></i> 检测密钥配置
                  </button>
                </div>
                <div class="mb-3" id="ts-test-result"></div>
                <div class="form-text mb-3">检测项：密钥是否成对、格式是否合法、是否仍为 Cloudflare 官方测试密钥。检测使用上方表单中当前填写的密钥（无需先保存），结果直接显示在下方。</div>
                </form>
                <hr class="my-4">
                <div class="mb-2 fw-bold"><i class="mdi mdi-flask-outline"></i> 端到端测试</div>
                <p class="text-muted small mb-2">完成下方 Cloudflare 验证后点击「提交验证」，服务端会用上方表单中当前填写的真实密钥调用 Cloudflare siteverify 接口并展示完整响应。这是最准确的诊断工具，能直接定位域名授权、密钥配对、token 生命周期等问题。</p>
                <div class="mb-2" id="ts-e2e-widget"></div>
                <div class="mb-2" id="ts-e2e-status"></div>
                <input type="hidden" id="ts-e2e-token" value="">
                <div class="d-flex align-items-center mb-3">
                  <button type="button" id="ts-e2e-reload-btn" class="btn btn-outline-secondary me-2">
                    <i class="mdi mdi-refresh"></i> 按上方表单 Site Key 重载组件
                  </button>
                  <button type="button" id="ts-e2e-submit-btn" class="btn btn-outline-primary">
                    <i class="mdi mdi-send-check-outline"></i> 提交验证
                  </button>
                </div>
                <div class="mb-3" id="ts-e2e-result"></div>
                <?php echo huli_turnstile_assets_html(); ?>
              </div>
            </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script type="text/javascript" src="../assets/js/jquery.min.js"></script>
<script type="text/javascript" src="../assets/js/popper.min.js"></script>
<script type="text/javascript" src="../assets/js/bootstrap.min.js"></script>
<script type="text/javascript" src="../assets/js/main.min.js"></script>
<script type="text/javascript">
(function () {
    var AJAX_URL = '../common/ajax/turnstile_test.php';
    function esc(s) {
        return $('<div>').text(s == null ? '' : String(s)).html();
    }
    function showResult(selector, ok, message, raw) {
        var html = '<div class="alert ' + (ok ? 'alert-success' : 'alert-danger') + ' mb-2">' + esc(message) + '</div>';
        if (raw) {
            html += '<div class="text-muted small mb-1">Cloudflare 原始响应：</div>';
            html += '<pre class="bg-light p-2 rounded small" style="max-height: 220px; overflow:auto;">' + esc(JSON.stringify(raw, null, 2)) + '</pre>';
        }
        $(selector).html(html);
    }
    function loadE2EWidget() {
        var siteKey = $.trim($('#turnstile_site_key').val());
        if (!siteKey) {
            $('#ts-e2e-status').html('<div class="alert alert-warning py-1 px-2 mb-0 small">请先在上方填写 Turnstile Site Key 再加载测试组件。</div>');
            return;
        }
        $('#ts-e2e-status').html('<div class="alert alert-secondary py-1 px-2 mb-0 small">正在加载验证组件...</div>');
        var loadingTimer = setTimeout(function () {
            $('#ts-e2e-status').html('<div class="alert alert-danger py-1 px-2 mb-0 small">Cloudflare 组件脚本加载超时，请确认服务器网络可访问 challenges.cloudflare.com，然后重新点击「按上方表单 Site Key 重载组件」。</div>');
        }, 20000);
        window.huliTurnstileReady(function () {
            clearTimeout(loadingTimer);
            if (window.__huliE2EWidget) {
                try { window.turnstile.remove(window.__huliE2EWidget); } catch (e) {}
                window.__huliE2EWidget = null;
            }
            $('#ts-e2e-widget').html('<div class="huli-turnstile"></div>');
            $('#ts-e2e-token').val('');
            var el = $('#ts-e2e-widget .huli-turnstile').get(0);
            try {
                window.__huliE2EWidget = window.turnstile.render(el, {
                    sitekey: siteKey,
                    callback: function (token) {
                        $('#ts-e2e-token').val(token);
                        $('#ts-e2e-status').html('<div class="alert alert-success py-1 px-2 mb-0 small">验证组件已完成，可点击「提交验证」。</div>');
                    },
                    'expired-callback': function () {
                        $('#ts-e2e-token').val('');
                        $('#ts-e2e-status').html('<div class="alert alert-secondary py-1 px-2 mb-0 small">验证已过期，请重新完成验证。</div>');
                    },
                    'error-callback': function (code) {
                        $('#ts-e2e-token').val('');
                        $('#ts-e2e-status').html('<div class="alert alert-danger py-1 px-2 mb-0 small">验证组件错误：' + esc(code) + '。常见错误码 110200 表示当前域名未在 Cloudflare Hostname Management 中授权。</div>');
                    }
                });
            } catch (err) {
                $('#ts-e2e-status').html('<div class="alert alert-danger py-1 px-2 mb-0 small">组件渲染失败：' + esc(err && err.message ? err.message : err) + '</div>');
                return;
            }
            $('#ts-e2e-status').html('<div class="alert alert-secondary py-1 px-2 mb-0 small">组件已加载，请完成验证。</div>');
        });
    }
    $('#ts-test-keys-btn').on('click', function () {
        var siteKey = $.trim($('#turnstile_site_key').val());
        var secretKey = $.trim($('#turnstile_secret_key').val());
        $('#ts-test-result').html('<div class="alert alert-secondary py-1 px-2 mb-0 small">正在检测...</div>');
        $.post(AJAX_URL, { mode: 'keys', site_key: siteKey, secret_key: secretKey }, function (res) {
            if (res && res.success) {
                showResult('#ts-test-result', true, res.message, null);
            } else {
                showResult('#ts-test-result', false, (res && res.message) ? res.message : '检测失败，请重试。', null);
            }
        }).fail(function () {
            $('#ts-test-result').html('<div class="alert alert-danger py-1 px-2 mb-0 small">请求失败，请检查网络连接后重试。</div>');
        });
    });
    $('#ts-e2e-reload-btn').on('click', loadE2EWidget);
    $('#ts-e2e-submit-btn').on('click', function () {
        var token = $.trim($('#ts-e2e-token').val());
        var siteKey = $.trim($('#turnstile_site_key').val());
        var secretKey = $.trim($('#turnstile_secret_key').val());
        if (!token) {
            $('#ts-e2e-result').html('<div class="alert alert-warning py-1 px-2 mb-0 small">请先完成上方验证组件再提交。</div>');
            return;
        }
        $('#ts-e2e-result').html('<div class="alert alert-secondary py-1 px-2 mb-0 small">正在向 Cloudflare 验证令牌...</div>');
        $.post(AJAX_URL, { mode: 'e2e', site_key: siteKey, secret_key: secretKey, 'cf-turnstile-response': token }, function (res) {
            if (res && res.success) {
                showResult('#ts-e2e-result', true, res.message, res.raw || null);
            } else {
                showResult('#ts-e2e-result', false, (res && res.message) ? res.message : '验证失败，请重试。', (res && res.raw) ? res.raw : null);
            }
            loadE2EWidget();
        }).fail(function () {
            $('#ts-e2e-result').html('<div class="alert alert-danger py-1 px-2 mb-0 small">请求失败，请检查网络连接后重试。</div>');
        });
    });
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(function (el) {
        el.addEventListener('shown.bs.tab', function (ev) {
            var target = ev.target.getAttribute('data-bs-target');
            if (target && target.charAt(0) === '#') { history.replaceState(null, '', target); }
        });
    });
    if (location.hash === '#turnstile') {
        var turnstileTab = document.getElementById('basic-turnstile');
        if (turnstileTab) {
            var tab = new bootstrap.Tab(turnstileTab);
            tab.show();
        }
    }
    loadE2EWidget();
})();
</script>
</body>
</html>
