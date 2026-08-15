<?php
if (!defined('HULI_PUSH_LIB')) { define('HULI_PUSH_LIB', 1); }

 
 

function huli_md_fields(array $fields, $title = '') {
    $lines = [];
    if ($title !== '') {
        $lines[] = '### ' . $title;
        $lines[] = '';
    }
    foreach ($fields as $k => $v) {
        $v = (string)$v;
        $lines[] = $v !== '' ? '- **' . $k . '**：' . $v : '- **' . $k . '**';
    }
    return implode("\n", $lines);
}

function huli_email_html_rows(array $fields) {
    $rows = '';
    foreach ($fields as $k => $v) {
        $v = (string)$v;
        $rows .= '<tr>'
            . '<td style="padding:13px 18px;width:32%;background:#f8fafc;color:#64748b;font-size:14px;border-bottom:1px solid #f1f5f9;vertical-align:top;">' . htmlspecialchars((string)$k) . '</td>'
            . '<td style="padding:13px 18px;color:#1e293b;font-size:14px;font-weight:600;border-bottom:1px solid #f1f5f9;word-break:break-all;">' . htmlspecialchars($v) . '</td>'
            . '</tr>';
    }
    return $rows;
}

function huli_email_html_template($title, $content, array $fields, $site_name = 'huliapi') {
    if (!empty($fields)) {
        $rows = huli_email_html_rows($fields);
        $body = '<table width="100%" cellpadding="0" cellspacing="0" style="border-radius:12px;overflow:hidden;border:1px solid #eef2f7;border-collapse:separate;">'
            . $rows
            . '</table>';
    } else {
        $body = '<p style="margin:0;color:#1e293b;font-size:15px;line-height:1.8;">' . nl2br(htmlspecialchars($content)) . '</p>';
    }
    return '<div style="background:#eef2f7;padding:32px 12px;">'
        . '<div style="max-width:560px;margin:0 auto;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,\'PingFang SC\',\'Microsoft YaHei\',sans-serif;">'
        . '<div style="background:linear-gradient(135deg,#2563eb,#4f7cff);padding:26px 28px;border-radius:14px 14px 0 0;">'
        . '<div style="color:#ffffff;font-size:16px;font-weight:600;opacity:.85;letter-spacing:1px;">' . htmlspecialchars($site_name) . '</div>'
        . '<div style="color:#ffffff;font-size:20px;font-weight:700;margin-top:6px;">' . htmlspecialchars($title) . '</div>'
        . '</div>'
        . '<div style="background:#ffffff;padding:24px 28px;border-left:1px solid #e3ebf6;border-right:1px solid #e3ebf6;">'
        . (!empty($fields) ? '<p style="margin:0 0 18px;color:#475569;font-size:14px;line-height:1.7;">检测到您的账号发生了一次新的登录行为，详情如下：</p>' : '')
        . $body
        . (!empty($fields) ? '<p style="margin:18px 0 0;color:#b45309;font-size:13px;line-height:1.7;">如非本人操作，请立即修改密码并检查账号安全，必要时联系管理员。</p>' : '')
        . '</div>'
        . '<div style="background:#f8fafc;padding:16px 28px;border-radius:0 0 14px 14px;border:1px solid #e3ebf6;border-top:none;font-size:12px;color:#94a3b8;line-height:1.7;">'
        . '本邮件由 ' . htmlspecialchars($site_name) . ' 系统自动发送，请勿直接回复。'
        . '</div>'
        . '</div></div>';
}

function huli_curl_post_json($url, $payload, $headers = []) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_UNICODE));
    $hdr = ['Content-Type: application/json; charset=utf-8'];
    foreach ($headers as $h) { $hdr[] = $h; }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $hdr);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'huliapi-push/1.0');
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err = curl_errno($ch) ? curl_error($ch) : '';
    curl_close($ch);
    return ['code' => $code, 'body' => $body, 'curl_err' => $curl_err];
}

function huli_curl_err($r) {
    if (!empty($r['curl_err'])) { return '网络错误: ' . $r['curl_err']; }
    $code = (int)($r['code'] ?? 0);
    if ($code >= 200 && $code < 400) { return ''; }
    $map = [400 => '请求参数有误(400)', 401 => '认证失败(401)', 403 => '无权限(403)', 404 => '接口不存在(404)', 429 => '发送频率受限(429)', 500 => '服务端异常(500)'];
    return isset($map[$code]) ? $map[$code] : ('HTTP ' . ($code ? $code : '连接失败'));
}

 
 

function huli_push_wecom($cfg, $title, $content, $fields = null) {
    $url = $cfg['webhook'] ?? '';
    if (!$url) { return ['ok' => false, 'err' => 'webhook 未配置']; }
    if (is_array($fields) && !empty($fields)) {
        $content = huli_md_fields($fields, $title);
    }
    $payload = [
        'msgtype' => 'markdown',
        'markdown' => [
            'content' => "## <font color=\"warning\">🔔 " . $title . "</font>\n\n" . $content . "\n\n> 🛡 本消息由 huliapi 推送，如非本人操作请及时处理",
        ],
    ];
    $r = huli_curl_post_json($url, $payload);
    $ok = $r['code'] === 200;
    return ['ok' => $ok, 'code' => $r['code'], 'body' => $r['body'], 'err' => $ok ? '' : huli_curl_err($r)];
}

function huli_push_dingtalk($cfg, $title, $content, $fields = null) {
    $url = $cfg['webhook'] ?? '';
    $secret = $cfg['secret'] ?? '';
    if (!$url) { return ['ok' => false, 'err' => 'webhook 未配置']; }
    if ($secret) {
        $timestamp = round((microtime(true) * 1000));
        $sign = base64_encode(hash_hmac('sha256', $timestamp . "\n" . $secret, $secret, true));
        $url .= (strpos($url, '?') === false ? '?' : '&') . 'timestamp=' . $timestamp . '&sign=' . urlencode($sign);
    }
    if (is_array($fields) && !empty($fields)) {
        $content = huli_md_fields($fields);
    }
    $payload = [
        'msgtype' => 'markdown',
        'markdown' => [
            'title' => $title,
            'text' => "### " . $title . "\n\n" . $content . "\n\n---\n🛡 本消息由 huliapi 推送",
        ],
    ];
    $r = huli_curl_post_json($url, $payload);
    $ok = $r['code'] === 200;
    return ['ok' => $ok, 'code' => $r['code'], 'body' => $r['body'], 'err' => $ok ? '' : huli_curl_err($r)];
}

function huli_push_feishu($cfg, $title, $content, $fields = null) {
    $url = $cfg['webhook'] ?? '';
    $secret = $cfg['secret'] ?? '';
    if (!$url) { return ['ok' => false, 'err' => 'webhook 未配置']; }
    if ($secret) {
        $timestamp = round((microtime(true) * 1000));
        $string_to_sign = $timestamp . "\n" . $secret;
        $hmac = hash_hmac('sha256', $string_to_sign, $secret, true);
        $sign = base64_encode($hmac);
        $url .= (strpos($url, '?') === false ? '?' : '&') . 'timestamp=' . $timestamp . '&sign=' . urlencode($sign);
    }
    $md = is_array($fields) && !empty($fields) ? '' : $content;
    if (is_array($fields) && !empty($fields)) {
        foreach ($fields as $k => $v) {
            $md .= '**' . (string)$k . '**：' . (string)$v . "\n";
        }
        $md = rtrim($md);
    }
    $elements = [['tag' => 'div', 'text' => ['tag' => 'lark_md', 'content' => $md ?: ' ']]];
    $payload = [
        'msg_type' => 'interactive',
        'card' => [
            'header' => ['title' => ['tag' => 'plain_text', 'content' => $title . ' 🔔'], 'template' => 'blue'],
            'elements' => $elements,
        ],
    ];
    $r = huli_curl_post_json($url, $payload);
    $ok = $r['code'] === 200;
    return ['ok' => $ok, 'code' => $r['code'], 'body' => $r['body'], 'err' => $ok ? '' : huli_curl_err($r)];
}

function huli_push_bark($cfg, $title, $content, $fields = null) {
    $server = rtrim($cfg['server'] ?? 'https://api.day.app', '/');
    $key = $cfg['device_key'] ?? '';
    if (!$key) { return ['ok' => false, 'err' => 'device_key 未配置']; }
    if (is_array($fields) && !empty($fields)) {
        $content = huli_md_fields($fields);
    }
    $payload = ['title' => $title, 'body' => $content, 'group' => 'huliapi', 'level' => 'active', 'sound' => 'default'];
    $r = huli_curl_post_json($server . '/' . $key, $payload);
    $ok = $r['code'] === 200;
    return ['ok' => $ok, 'code' => $r['code'], 'body' => $r['body'], 'err' => $ok ? '' : huli_curl_err($r)];
}

function huli_push_webhook($cfg, $title, $content, $fields = null) {
    $url = $cfg['url'] ?? '';
    if (!$url) { return ['ok' => false, 'err' => 'url 未配置']; }
    $method = strtoupper($cfg['method'] ?? 'POST');
    $headers = [];
    if (!empty($cfg['headers'])) {
        foreach (explode("\n", $cfg['headers']) as $line) {
            $line = trim($line);
            if ($line !== '' && strpos($line, ':') !== false) {
                [$k, $v] = explode(':', $line, 2);
                $headers[] = trim($k) . ': ' . trim($v);
            }
        }
    }
    $payload = json_encode([
        'event' => 'push',
        'title' => $title,
        'content' => $content,
        'data' => is_array($fields) && !empty($fields) ? $fields : (object)[],
        'time' => date('c'),
    ], JSON_UNESCAPED_UNICODE);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    $hdr = ['Content-Type: application/json; charset=utf-8'];
    foreach ($headers as $h) { $hdr[] = $h; }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $hdr);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'huliapi-push/1.0');
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err = curl_errno($ch) ? curl_error($ch) : '';
    curl_close($ch);
    $ok = $code >= 200 && $code < 400;
    return ['ok' => $ok, 'code' => $code, 'body' => $body, 'err' => $ok ? '' : ($curl_err ? '网络错误: ' . $curl_err : huli_curl_err(['code' => $code]))];
}

function huli_push_email($cfg, $to, $title, $content, $fields = null, $site_name = 'huliapi') {
    if (!file_exists(__DIR__ . '/PHPMailer/src/PHPMailer.php')) { return ['ok' => false, 'err' => 'PHPMailer 未安装']; }
    require_once __DIR__ . '/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/src/SMTP.php';
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->CharSet = 'UTF-8';
        $mail->Host = $cfg['smtp_host'] ?? '';
        $mail->SMTPAuth = true;
        $mail->Username = $cfg['smtp_user'] ?? '';
        $mail->Password = $cfg['smtp_pass'] ?? '';
        $secure = strtolower($cfg['smtp_secure'] ?? 'ssl');
        $mail->SMTPSecure = $secure === 'tls' ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = (int)($cfg['smtp_port'] ?? ($secure === 'tls' ? 587 : 465));
        $mail->setFrom($cfg['smtp_user'] ?? '', $cfg['from_name'] ?? $site_name);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $title;
        $mail->Body = huli_email_html_template($title, $content, is_array($fields) && !empty($fields) ? $fields : [], $site_name);
        $mail->AltBody = $contents = huli_md_fields(is_array($fields) && !empty($fields) ? $fields : ['消息' => $content]);
        $mail->send();
        return ['ok' => true];
    } catch (Exception $e) {
        return ['ok' => false, 'err' => $mail->ErrorInfo ?? $e->getMessage()];
    }
}

 

function huli_load_system_mail_config($pdo) {
    static $cache = null;
    if ($cache !== null) { return $cache; }
    $cache = ['smtp_host' => '', 'smtp_port' => 465, 'smtp_secure' => 'ssl', 'smtp_user' => '', 'smtp_pass' => '', 'from_name' => 'huliapi'];
    try {
        $rows = $pdo->query("SELECT setting_key, setting_value FROM huli_settings WHERE setting_key IN ('mail_smtp_host','mail_smtp_port','mail_smtp_secure','mail_smtp_user','mail_smtp_pass','site_name')")->fetchAll(PDO::FETCH_KEY_PAIR);
        if (!empty($rows['mail_smtp_host'])) $cache['smtp_host'] = $rows['mail_smtp_host'];
        if (!empty($rows['mail_smtp_port'])) $cache['smtp_port'] = (int)$rows['mail_smtp_port'];
        if (!empty($rows['mail_smtp_secure'])) $cache['smtp_secure'] = $rows['mail_smtp_secure'];
        if (!empty($rows['mail_smtp_user'])) $cache['smtp_user'] = $rows['mail_smtp_user'];
        if (!empty($rows['mail_smtp_pass'])) $cache['smtp_pass'] = $rows['mail_smtp_pass'];
        if (!empty($rows['site_name'])) $cache['from_name'] = $rows['site_name'];
        $cache['site_name'] = $rows['site_name'] ?? 'huliapi';
    } catch (Throwable $e) {}
    return $cache;
}

function huli_load_push_settings($pdo) {
    static $cache = null;
    if ($cache !== null) { return $cache; }
    try {
        $rows = $pdo->query("SELECT channel, name, enabled, config, events FROM huli_push_settings")->fetchAll(PDO::FETCH_ASSOC);
        $cache = [];
        foreach ($rows as $r) {
            $cache[$r['channel']] = [
                'name' => $r['name'],
                'enabled' => (int)$r['enabled'] === 1,
                'config' => json_decode($r['config'], true) ?: [],
                'events' => json_decode($r['events'], true) ?: [],
            ];
        }
    } catch (Throwable $e) {
        $cache = [];
    }
    return $cache;
}

function huli_load_user_push_settings($pdo, $user_id) {
    $cache = [];
    try {
        $stmt = $pdo->prepare("SELECT channel, name, enabled, config, events FROM huli_user_push_settings WHERE user_id = ?");
        $stmt->execute([(int)$user_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $cache[$r['channel']] = [
                'name' => $r['name'],
                'enabled' => (int)$r['enabled'] === 1,
                'config' => json_decode($r['config'], true) ?: [],
                'events' => json_decode($r['events'], true) ?: [],
            ];
        }
    } catch (Throwable $e) {}
    return $cache;
}

function huli_ensure_user_push_defaults($pdo, $user_id) {
    $sys_set = huli_load_push_settings($pdo);
    $defaults = [
        'email'    => ['name' => '邮件通知',     'enabled' => 1, 'config' => '{}',                                            'events' => ['login.notify']],
        'wecom'    => ['name' => '企业微信',     'enabled' => 0, 'config' => '{"webhook":""}',                                 'events' => ['login.notify']],
        'dingtalk' => ['name' => '钉钉',         'enabled' => 0, 'config' => '{"webhook":"","secret":""}',                     'events' => ['login.notify']],
        'feishu'   => ['name' => '飞书',         'enabled' => 0, 'config' => '{"webhook":"","secret":""}',                     'events' => ['login.notify']],
        'bark'     => ['name' => 'Bark iOS',     'enabled' => 0, 'config' => '{"server":"https://api.day.app","device_key":""}','events' => ['login.notify']],
        'webhook'  => ['name' => '自定义 Webhook','enabled' => 0, 'config' => '{"url":"","method":"POST","headers":""}',       'events' => ['login.notify']],
    ];
    foreach ($defaults as $channel => $d) {
        if (!isset($sys_set[$channel]) || !$sys_set[$channel]['enabled']) { continue; }
        try {
            $stmt = $pdo->prepare("INSERT IGNORE INTO huli_user_push_settings (user_id, channel, name, enabled, config, events) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([(int)$user_id, $channel, $d['name'], $d['enabled'], $d['config'], json_encode($d['events'], JSON_UNESCAPED_UNICODE)]);
        } catch (Throwable $e) {}
    }
}

 

function huli_send_by_channel($channel, $cfg, $title, $content, $recipient = '', $fields = null, $site_name = 'huliapi') {
    $r = ['channel' => $channel, 'ok' => false];
    switch ($channel) {
        case 'email':
            if (!$recipient) { $r['err'] = '未指定收件人'; break; }
            $r = array_merge($r, huli_push_email($cfg, $recipient, $title, $content, $fields, $site_name));
            break;
        case 'wecom':    $r = array_merge($r, huli_push_wecom($cfg, $title, $content, $fields)); break;
        case 'dingtalk': $r = array_merge($r, huli_push_dingtalk($cfg, $title, $content, $fields)); break;
        case 'feishu':   $r = array_merge($r, huli_push_feishu($cfg, $title, $content, $fields)); break;
        case 'bark':     $r = array_merge($r, huli_push_bark($cfg, $title, $content, $fields)); break;
        case 'webhook':  $r = array_merge($r, huli_push_webhook($cfg, $title, $content, $fields)); break;
        default:         $r['err'] = '未知通道'; break;
    }
    return $r;
}

function huli_push_dispatch($pdo, $event, $title, $content, $recipient = '', $fields = null) {
    $set = huli_load_push_settings($pdo);
    if (empty($set)) { return ['dispatched' => 0, 'results' => []]; }
    $sys_mail = huli_load_system_mail_config($pdo);
    $results = [];
    foreach ($set as $channel => $cfg) {
        if (!$cfg['enabled']) { continue; }
        if (!empty($cfg['events']) && !in_array($event, $cfg['events'], true)) { continue; }
        $effective_cfg = ($channel === 'email') ? $sys_mail : $cfg['config'];
        try {
            $results[] = huli_send_by_channel($channel, $effective_cfg, $title, $content, $recipient, $fields, $sys_mail['site_name'] ?? 'huliapi');
        } catch (Throwable $e) {
            $results[] = ['channel' => $channel, 'ok' => false, 'err' => $e->getMessage()];
        }
    }
    return ['dispatched' => count($results), 'results' => $results];
}

function huli_push_dispatch_user($pdo, $user_id, $event, $title, $content, $recipient = '', $fields = null) {
    $set = huli_load_user_push_settings($pdo, $user_id);
    if (empty($set)) { return ['dispatched' => 0, 'results' => []]; }
    $sys_mail = huli_load_system_mail_config($pdo);
    $results = [];
    foreach ($set as $channel => $cfg) {
        if (!$cfg['enabled']) { continue; }
        if (!empty($cfg['events']) && !in_array($event, $cfg['events'], true)) { continue; }
        $effective_cfg = ($channel === 'email') ? $sys_mail : $cfg['config'];
        try {
            $results[] = huli_send_by_channel($channel, $effective_cfg, $title, $content, $recipient, $fields, $sys_mail['site_name'] ?? 'huliapi');
        } catch (Throwable $e) {
            $results[] = ['channel' => $channel, 'ok' => false, 'err' => $e->getMessage()];
        }
    }
    return ['dispatched' => count($results), 'results' => $results];
}