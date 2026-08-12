<?php
if (!defined('HULI_LOGIN_LIB')) { define('HULI_LOGIN_LIB', 1); }

function huli_get_client_ip() {
    $candidates = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    foreach ($candidates as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = trim(explode(',', $_SERVER[$k])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) { return $ip; }
        }
    }
    return '0.0.0.0';
}

function huli_geo_lookup($ip, $pdo = null) {
    if (!$ip || $ip === '127.0.0.1' || $ip === '::1' || strpos($ip, '192.168.') === 0 || strpos($ip, '10.') === 0) {
        return ['country' => '内网', 'region' => '本地', 'city' => '本地', 'isp' => '内网'];
    }
    $ctx = stream_context_create(['http' => ['timeout' => 4, 'header' => "User-Agent: huliapi-geo/1.0\r\n"]]);
    $body = @file_get_contents("https://ipwho.is/" . urlencode($ip) . "?lang=zh-CN", false, $ctx);
    if ($body) {
        $j = json_decode($body, true);
        if (is_array($j) && ($j['success'] ?? true) && !empty($j['country'])) {
            return [
                'country' => $j['country'] ?? '',
                'region' => $j['region'] ?? '',
                'city' => $j['city'] ?? '',
                'isp' => $j['connection']['isp'] ?? ($j['connection']['org'] ?? ''),
            ];
        }
    }
    return ['country' => '未知', 'region' => '未知', 'city' => '未知', 'isp' => '未知'];
}

function huli_record_login($pdo, $actor_type, $actor_id, $actor_name, $status, $extra = []) {
    $ip = huli_get_client_ip();
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    $geo = huli_geo_lookup($ip, $pdo);
    $log_id = 0;
    try {
        if ($actor_type === 'admin') {
            $stmt = $pdo->prepare("INSERT INTO huli_login_logs (actor_id, actor_name, status, ip, country, region, city, isp, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$actor_id, $actor_name, $status, $ip, $geo['country'], $geo['region'], $geo['city'], $geo['isp'], $ua]);
            $log_id = (int)$pdo->lastInsertId();
        } elseif ($actor_type === 'user') {
            $stmt = $pdo->prepare("INSERT INTO huli_user_login_logs (user_id, username, status, ip, country, region, city, isp, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$actor_id, $actor_name, $status, $ip, $geo['country'], $geo['region'], $geo['city'], $geo['isp'], $ua]);
            $log_id = (int)$pdo->lastInsertId();
        } else {
            $stmt = $pdo->prepare("INSERT INTO huli_login_logs (actor_type, actor_id, actor_name, status, ip, country, region, city, isp, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$actor_type, $actor_id, $actor_name, $status, $ip, $geo['country'], $geo['region'], $geo['city'], $geo['isp'], $ua]);
            $log_id = (int)$pdo->lastInsertId();
        }
    } catch (Throwable $e) {
        $log_id = 0;
    }
    if ($status === 'success' && $actor_type === 'user' && !empty($extra['notify']) && !empty($extra['email'])) {
        huli_notify_login($pdo, $actor_name, $extra['email'], $ip, $geo, $ua, 'user');
    } elseif ($status === 'success' && $actor_type === 'admin') {
        $admin_email = '';
        try {
            $row = $pdo->prepare("SELECT email FROM huli_admins WHERE id = ?");
            $row->execute([$actor_id]);
            $admin_email = $row->fetchColumn() ?: '';
        } catch (Throwable $e) {}
        if ($admin_email) {
            huli_notify_login($pdo, $actor_name, $admin_email, $ip, $geo, $ua, 'admin');
        }
    }
    return ['log_id' => $log_id, 'ip' => $ip, 'geo' => $geo];
}

function huli_notify_login($pdo, $actor_name, $email, $ip, $geo, $ua, $actor_type) {
    if (!function_exists('huli_push_dispatch')) {
        $push_lib = __DIR__ . '/push.php';
        if (file_exists($push_lib)) { require_once $push_lib; }
    }
    if (!function_exists('huli_push_dispatch')) { return; }
    $title = $actor_type === 'admin' ? '【后台登录提醒】' . ($actor_name ?: '管理员') : '【账号登录提醒】' . ($actor_name ?: '用户');
    $ua_short = strlen($ua) > 80 ? substr($ua, 0, 80) . '…' : $ua;
    $content = "**账号**：" . ($actor_name ?: '-') . "\n"
        . "**时间**：" . date('Y-m-d H:i:s') . "\n"
        . "**IP**：" . $ip . "\n"
        . "**位置**：" . ($geo['country'] ?: '未知') . ' ' . ($geo['region'] ?: '') . ' ' . ($geo['city'] ?: '') . "\n"
        . "**网络**：" . ($geo['isp'] ?: '未知') . "\n"
        . "**设备**：" . $ua_short;
    try {
        huli_push_dispatch($pdo, 'login.notify', $title, $content, $email);
    } catch (Throwable $e) {}
}
