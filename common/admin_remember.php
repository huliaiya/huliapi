<?php
if (!defined('HULI_ADMIN_REMEMBER_LIB')) { define('HULI_ADMIN_REMEMBER_LIB', 1); }

function huli_admin_remember_cookie_name() {
    return 'huli_admin_remember';
}

function huli_admin_remember_ttl() {
    return 7 * 24 * 60 * 60;
}

function huli_admin_remember_pdo() {
    static $pdo = null;
    static $tried = false;
    if ($pdo instanceof PDO) { return $pdo; }
    if ($tried) { return null; }
    $tried = true;
    if (!defined('DB_HOST')) { return null; }
    try {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . (defined('DB_PORT') ? DB_PORT : 3306) . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 3,
        ]);
    } catch (Throwable $e) {
        $pdo = null;
    }
    return $pdo;
}

function huli_admin_remember_table(PDO $pdo = null) {
    static $done = false;
    if ($done) { return; }
    $pdo = $pdo ?: huli_admin_remember_pdo();
    if (!$pdo instanceof PDO) { return; }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `huli_admin_remember` (
            `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '令牌ID',
            `admin_id` int(11) NOT NULL COMMENT '管理员ID',
            `selector` char(16) NOT NULL COMMENT '选择器',
            `token_hash` char(64) NOT NULL COMMENT '验证令牌哈希',
            `expires_at` datetime NOT NULL COMMENT '过期时间',
            `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT '创建时间',
            `ip` varchar(45) DEFAULT NULL COMMENT '签发IP',
            `user_agent` varchar(255) DEFAULT NULL COMMENT '签发UA',
            PRIMARY KEY (`id`),
            UNIQUE KEY `selector` (`selector`),
            KEY `admin_id` (`admin_id`),
            KEY `expires_at` (`expires_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='后台管理员自动登录令牌表'");
        $done = true;
    } catch (Throwable $e) {
    }
}

function huli_admin_remember_is_secure_request() {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') { return true; }
    if (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) { return true; }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $fw = strtolower(trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]));
        if ($fw === 'https') { return true; }
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) !== 'off') { return true; }
    return false;
}

function huli_admin_remember_send_cookie($value, $expires) {
    $name = huli_admin_remember_cookie_name();
    $options = [
        'expires' => $expires,
        'path' => '/',
        'secure' => huli_admin_remember_is_secure_request(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    if (PHP_VERSION_ID >= 70300) {
        setcookie($name, $value, $options);
    } else {
        setcookie($name, $value, $expires, '/', '', $options['secure'], true);
    }
    if ($value === '') {
        unset($_COOKIE[$name]);
    } else {
        $_COOKIE[$name] = $value;
    }
}

function huli_admin_remember_issue($admin_id, PDO $pdo = null) {
    $admin_id = (int)$admin_id;
    if ($admin_id <= 0) { return false; }
    $pdo = $pdo ?: huli_admin_remember_pdo();
    if (!$pdo instanceof PDO) { return false; }
    huli_admin_remember_table($pdo);
    try {
        $selector = bin2hex(random_bytes(8));
        $validator = bin2hex(random_bytes(32));
        $token_hash = hash('sha256', $validator);
        $expires_ts = time() + huli_admin_remember_ttl();
        $ip = function_exists('huli_get_client_ip') ? huli_get_client_ip() : (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250);
        $stmt = $pdo->prepare('INSERT INTO huli_admin_remember (admin_id, selector, token_hash, expires_at, ip, user_agent) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$admin_id, $selector, $token_hash, date('Y-m-d H:i:s', $expires_ts), $ip, $ua]);
        huli_admin_remember_send_cookie($selector . ':' . $validator, $expires_ts);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function huli_admin_remember_revoke(PDO $pdo = null) {
    $raw = (string)($_COOKIE[huli_admin_remember_cookie_name()] ?? '');
    if ($raw !== '' && strpos($raw, ':') !== false) {
        $pdo = $pdo ?: huli_admin_remember_pdo();
        if ($pdo instanceof PDO) {
            try {
                huli_admin_remember_table($pdo);
                $selector = explode(':', $raw, 2)[0];
                if ($selector !== '') {
                    $stmt = $pdo->prepare('DELETE FROM huli_admin_remember WHERE selector = ?');
                    $stmt->execute([$selector]);
                }
            } catch (Throwable $e) {
            }
        }
    }
    huli_admin_remember_send_cookie('', time() - 3600);
}

function huli_admin_remember_login(PDO $pdo = null) {
    $raw = (string)($_COOKIE[huli_admin_remember_cookie_name()] ?? '');
    if ($raw === '' || strpos($raw, ':') === false) { return false; }
    $parts = explode(':', $raw, 2);
    $selector = $parts[0];
    $validator = $parts[1];
    if ($selector === '' || $validator === '') { return false; }
    $pdo = $pdo ?: huli_admin_remember_pdo();
    if (!$pdo instanceof PDO) { return false; }
    try {
        huli_admin_remember_table($pdo);
        $stmt = $pdo->prepare('SELECT id, admin_id, token_hash, expires_at FROM huli_admin_remember WHERE selector = ? LIMIT 1');
        $stmt->execute([$selector]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            huli_admin_remember_send_cookie('', time() - 3600);
            return false;
        }
        if (strtotime((string)$row['expires_at']) < time()) {
            $pdo->prepare('DELETE FROM huli_admin_remember WHERE id = ?')->execute([$row['id']]);
            huli_admin_remember_send_cookie('', time() - 3600);
            return false;
        }
        if (!hash_equals((string)$row['token_hash'], hash('sha256', $validator))) {
            $pdo->prepare('DELETE FROM huli_admin_remember WHERE admin_id = ?')->execute([$row['admin_id']]);
            huli_admin_remember_send_cookie('', time() - 3600);
            return false;
        }
        $admin_id = (int)$row['admin_id'];
        $a = $pdo->prepare('SELECT id, username, nickname, status FROM huli_admins WHERE id = ? LIMIT 1');
        $a->execute([$admin_id]);
        $admin = $a->fetch(PDO::FETCH_ASSOC);
        if (!$admin || (int)$admin['status'] !== 1) {
            $pdo->prepare('DELETE FROM huli_admin_remember WHERE admin_id = ?')->execute([$admin_id]);
            huli_admin_remember_send_cookie('', time() - 3600);
            return false;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['admin_id'] = (int)$admin['id'];
        $_SESSION['admin_username'] = defined('ADMIN_NICKNAME') ? ADMIN_NICKNAME : (($admin['nickname'] !== null && $admin['nickname'] !== '') ? $admin['nickname'] : $admin['username']);
        $pdo->prepare('DELETE FROM huli_admin_remember WHERE id = ?')->execute([$row['id']]);
        huli_admin_remember_issue($admin_id, $pdo);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}
