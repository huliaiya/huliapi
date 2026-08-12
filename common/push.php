<?php
if (!defined('HULI_PUSH_LIB')) { define('HULI_PUSH_LIB', 1); }

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
    curl_close($ch);
    return ['code' => $code, 'body' => $body];
}

function huli_push_wecom($cfg, $title, $content) {
    $url = $cfg['webhook'] ?? '';
    if (!$url) { return ['ok' => false, 'err' => 'webhook 未配置']; }
    $payload = [
        'msgtype' => 'markdown',
        'markdown' => [
            'content' => "## <font color=\"info\">" . $title . "</font>\n\n" . $content,
        ],
    ];
    $r = huli_curl_post_json($url, $payload);
    return ['ok' => $r['code'] === 200, 'code' => $r['code'], 'body' => $r['body']];
}

function huli_push_dingtalk($cfg, $title, $content) {
    $url = $cfg['webhook'] ?? '';
    $secret = $cfg['secret'] ?? '';
    if (!$url) { return ['ok' => false, 'err' => 'webhook 未配置']; }
    if ($secret) {
        $timestamp = round((microtime(true) * 1000));
        $sign = base64_encode(hash_hmac('sha256', $timestamp . "\n" . $secret, $secret, true));
        $url .= (strpos($url, '?') === false ? '?' : '&') . 'timestamp=' . $timestamp . '&sign=' . urlencode($sign);
    }
    $payload = [
        'msgtype' => 'markdown',
        'markdown' => [
            'title' => $title,
            'text' => "### " . $title . "\n\n" . $content,
        ],
    ];
    $r = huli_curl_post_json($url, $payload);
    return ['ok' => $r['code'] === 200, 'code' => $r['code'], 'body' => $r['body']];
}

function huli_push_feishu($cfg, $title, $content) {
    $url = $cfg['webhook'] ?? '';
    $secret = $cfg['secret'] ?? '';
    if (!$url) { return ['ok' => false, 'err' => 'webhook 未配置']; }
    if ($secret) {
        $timestamp = round((microtime(true) * 1000));
        $string_to_sign = $timestamp . "\n" . $secret;
        $hmac = hash_hmac('sha256', '', $string_to_sign, true);
        $sign = base64_encode($hmac);
        $url .= (strpos($url, '?') === false ? '?' : '&') . 'timestamp=' . $timestamp . '&sign=' . urlencode($sign);
    }
    $payload = [
        'msg_type' => 'interactive',
        'card' => [
            'header' => ['title' => ['tag' => 'plain_text', 'content' => $title], 'template' => 'blue'],
            'elements' => [
                [['tag' => 'markdown', 'content' => $content]],
            ],
        ],
    ];
    $r = huli_curl_post_json($url, $payload);
    return ['ok' => $r['code'] === 200, 'code' => $r['code'], 'body' => $r['body']];
}

function huli_push_bark($cfg, $title, $content) {
    $server = rtrim($cfg['server'] ?? 'https://api.day.app', '/');
    $key = $cfg['device_key'] ?? '';
    if (!$key) { return ['ok' => false, 'err' => 'device_key 未配置']; }
    $payload = ['title' => $title, 'body' => $content, 'group' => 'huliapi', 'level' => 'active'];
    $r = huli_curl_post_json($server . '/' . $key, $payload);
    return ['ok' => $r['code'] === 200, 'code' => $r['code'], 'body' => $r['body']];
}

function huli_push_webhook($cfg, $title, $content) {
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
    $payload = json_encode(['title' => $title, 'content' => $content, 'time' => date('c')], JSON_UNESCAPED_UNICODE);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    $hdr = ['Content-Type: application/json; charset=utf-8'];
    foreach ($headers as $h) { $hdr[] = $h; }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $hdr);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['ok' => $code >= 200 && $code < 400, 'code' => $code, 'body' => $body];
}

function huli_push_email($cfg, $to, $title, $content) {
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
        $mail->setFrom($cfg['smtp_user'] ?? '', $cfg['from_name'] ?? 'huliapi');
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $title;
        $mail->Body = '<div style="font-family:Helvetica,Arial,sans-serif;font-size:14px;color:#333;line-height:1.7;">'
            . '<h3 style="color:#2879ba;margin-bottom:12px;">' . htmlspecialchars($title) . '</h3>'
            . nl2br(htmlspecialchars($content))
            . '<hr style="border:none;border-top:1px solid #eee;margin:18px 0;">'
            . '<p style="font-size:12px;color:#888;">本邮件由 huliapi 系统自动发送，请勿直接回复。</p>'
            . '</div>';
        $mail->send();
        return ['ok' => true];
    } catch (Exception $e) {
        return ['ok' => false, 'err' => $mail->ErrorInfo ?? $e->getMessage()];
    }
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

function huli_push_dispatch($pdo, $event, $title, $content, $recipient = '') {
    $set = huli_load_push_settings($pdo);
    if (empty($set)) { return ['dispatched' => 0, 'results' => []]; }
    $results = [];
    foreach ($set as $channel => $cfg) {
        if (!$cfg['enabled']) { continue; }
        if (!empty($cfg['events']) && !in_array($event, $cfg['events'], true)) { continue; }
        $r = ['channel' => $channel, 'ok' => false];
        try {
            switch ($channel) {
                case 'email':
                    if (!$recipient) { $r['err'] = '未指定收件人'; break; }
                    $r = array_merge($r, huli_push_email($cfg['config'], $recipient, $title, $content));
                    break;
                case 'wecom':    $r = array_merge($r, huli_push_wecom($cfg['config'], $title, $content)); break;
                case 'dingtalk': $r = array_merge($r, huli_push_dingtalk($cfg['config'], $title, $content)); break;
                case 'feishu':   $r = array_merge($r, huli_push_feishu($cfg['config'], $title, $content)); break;
                case 'bark':     $r = array_merge($r, huli_push_bark($cfg['config'], $title, $content)); break;
                case 'webhook':  $r = array_merge($r, huli_push_webhook($cfg['config'], $title, $content)); break;
            }
        } catch (Throwable $e) {
            $r['err'] = $e->getMessage();
        }
        $results[] = $r;
    }
    return ['dispatched' => count($results), 'results' => $results];
}
