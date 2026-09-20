<?php
if (!defined('HULI_MCP_LIB')) { define('HULI_MCP_LIB', 1); }

function huli_mcp_pdo() {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";port=" . (defined('DB_PORT') ? DB_PORT : 3306) . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
    return $pdo;
}

function huli_mcp_ensure_schema() {
    static $done = false;
    if ($done) { return; }
    $done = true;
    $pdo = huli_mcp_pdo();
    $user_cols = $pdo->query("SHOW COLUMNS FROM `huli_users`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('mcp_token_hash', $user_cols)) {
        $pdo->exec("ALTER TABLE `huli_users` ADD `mcp_token_hash` VARCHAR(64) NULL DEFAULT NULL, ADD `mcp_token_prefix` VARCHAR(16) NULL DEFAULT NULL");
    }
    $admin_cols = $pdo->query("SHOW COLUMNS FROM `huli_admins`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('mcp_token_hash', $admin_cols)) {
        $pdo->exec("ALTER TABLE `huli_admins` ADD `mcp_token_hash` VARCHAR(64) NULL DEFAULT NULL, ADD `mcp_token_prefix` VARCHAR(16) NULL DEFAULT NULL");
    }
}

function huli_mcp_generate_token($role) {
    $prefix = $role === 'admin' ? 'mcp_a_' : 'mcp_u_';
    return $prefix . bin2hex(random_bytes(24));
}

function huli_mcp_store_token($role, $id, $token) {
    $pdo = huli_mcp_pdo();
    $hash = hash('sha256', $token);
    $prefix = substr($token, 0, 10) . '...';
    $table = $role === 'admin' ? 'huli_admins' : 'huli_users';
    $stmt = $pdo->prepare("UPDATE `$table` SET mcp_token_hash = ?, mcp_token_prefix = ? WHERE id = ?");
    $stmt->execute([$hash, $prefix, $id]);
    huli_mcp_log_token_event($role, $id, 'generate', $prefix);
}

function huli_mcp_clear_token($role, $id) {
    $pdo = huli_mcp_pdo();
    $table = $role === 'admin' ? 'huli_admins' : 'huli_users';
    $stmt = $pdo->prepare("UPDATE `$table` SET mcp_token_hash = NULL, mcp_token_prefix = NULL WHERE id = ?");
    $stmt->execute([$id]);
    huli_mcp_log_token_event($role, $id, 'revoke', '');
}

function huli_mcp_ensure_audit_schema() {
    static $done = false;
    if ($done) { return; }
    $done = true;
    $pdo = huli_mcp_pdo();
    $tables = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('huli_mcp_token_events', $tables)) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `huli_mcp_token_events` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `event_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `role` ENUM('user','admin') NOT NULL,
            `user_id` INT UNSIGNED NOT NULL DEFAULT 0,
            `username` VARCHAR(64) NOT NULL DEFAULT '',
            `action` ENUM('generate','revoke') NOT NULL,
            `token_prefix` VARCHAR(16) NOT NULL DEFAULT '',
            `ip_address` VARCHAR(64) NOT NULL DEFAULT '',
            `user_agent` VARCHAR(255) NOT NULL DEFAULT '',
            PRIMARY KEY (`id`),
            KEY `idx_role_time` (`role`,`event_time`),
            KEY `idx_user` (`role`,`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MCP Token 生成/撤销流水'");
    }

    if (!in_array('huli_mcp_instruction_downloads', $tables)) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `huli_mcp_instruction_downloads` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `download_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `role` VARCHAR(16) NOT NULL DEFAULT '',
            `ip_address` VARCHAR(64) NOT NULL DEFAULT '',
            `user_agent` VARCHAR(255) NOT NULL DEFAULT '',
            `via` ENUM('public','access_doc') NOT NULL DEFAULT 'public',
            PRIMARY KEY (`id`),
            KEY `idx_role_time` (`role`,`download_time`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MCP 接入指令下载记录'");
    }

    if (!in_array('huli_mcp_sessions', $tables)) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `huli_mcp_sessions` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `session_id` VARCHAR(64) NOT NULL DEFAULT '',
            `started_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `ended_at` DATETIME NULL DEFAULT NULL,
            `role` ENUM('user','admin') NOT NULL,
            `user_id` INT UNSIGNED NOT NULL DEFAULT 0,
            `username` VARCHAR(64) NOT NULL DEFAULT '',
            `protocol` VARCHAR(64) NOT NULL DEFAULT '',
            `ip_address` VARCHAR(64) NOT NULL DEFAULT '',
            `user_agent` VARCHAR(255) NOT NULL DEFAULT '',
            `messages_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `status` ENUM('active','closed','timed_out') NOT NULL DEFAULT 'active',
            PRIMARY KEY (`id`),
            KEY `idx_session` (`session_id`),
            KEY `idx_role_time` (`role`,`started_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MCP 客户端连接会话'");
    }

    if (!in_array('huli_mcp_tool_calls', $tables)) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `huli_mcp_tool_calls` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `call_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `role` ENUM('user','admin') NOT NULL,
            `user_id` INT UNSIGNED NOT NULL DEFAULT 0,
            `username` VARCHAR(64) NOT NULL DEFAULT '',
            `tool_name` VARCHAR(64) NOT NULL DEFAULT '',
            `args_json` MEDIUMTEXT NULL DEFAULT NULL,
            `status` ENUM('success','error') NOT NULL DEFAULT 'success',
            `error_msg` VARCHAR(500) NULL DEFAULT NULL,
            `ip_address` VARCHAR(64) NOT NULL DEFAULT '',
            `latency_ms` INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `idx_tool_time` (`tool_name`,`call_time`),
            KEY `idx_role_time` (`role`,`call_time`),
            KEY `idx_user` (`role`,`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MCP 工具调用记录（参数已脱敏）'");
    }
}

function huli_mcp_audit_ip() {
    return (string)($_SERVER['REMOTE_ADDR'] ?? '');
}

function huli_mcp_audit_ua() {
    return (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
}

function huli_mcp_resolve_username($role, $id) {
    try {
        $pdo = huli_mcp_pdo();
        $table = $role === 'admin' ? 'huli_admins' : 'huli_users';
        $stmt = $pdo->prepare("SELECT username FROM `$table` WHERE id = ? LIMIT 1");
        $stmt->execute([(int)$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (string)$row['username'] : '';
    } catch (Throwable $e) { return ''; }
}

function huli_mcp_log_token_event($role, $id, $action, $tokenPrefix) {
    try {
        huli_mcp_ensure_audit_schema();
        $pdo = huli_mcp_pdo();
        $stmt = $pdo->prepare("INSERT INTO huli_mcp_token_events (role, user_id, username, action, token_prefix, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $role === 'admin' ? 'admin' : 'user',
            (int)$id,
            huli_mcp_resolve_username($role, $id),
            $action === 'revoke' ? 'revoke' : 'generate',
            $tokenPrefix !== '' ? substr($tokenPrefix, 0, 10) . '...' : '',
            huli_mcp_audit_ip(),
            huli_mcp_audit_ua(),
        ]);
    } catch (Throwable $e) { error_log('[mcp_lib] Token 流水写入失败: ' . $e->getMessage()); }
}

// 对工具调用参数做脱敏：隐藏 api_key / token / password / secret / key 等敏感字段值。
function huli_mcp_sanitize_args(array $args) {
    $sensitive = ['api_key', 'apikey', 'api-key', 'token', 'password', 'passwd', 'secret', 'secret_key', 'key', 'authorization'];
    $sensitive = array_fill_keys($sensitive, true);
    $out = [];
    foreach ($args as $k => $v) {
        $keyLower = strtolower((string)$k);
        if (isset($sensitive[$keyLower]) || (strpos($keyLower, 'key') !== false)) {
            $out[$k] = is_scalar($v) && $v !== null ? (strlen((string)$v) === 0 ? '' : '***' . str_repeat('*', (int)floor(strlen((string)$v) * 0.6))) : '***';
            continue;
        }
        if (is_array($v)) {
            $out[$k] = huli_mcp_sanitize_args($v);
        } else {
            $out[$k] = $v;
        }
    }
    return $out;
}

function huli_mcp_log_tool_call($ctx, $toolName, array $args, $status, $errorMsg = '', $latencyMs = 0) {
    try {
        huli_mcp_ensure_audit_schema();
        $pdo = huli_mcp_pdo();
        $sanitized = huli_mcp_sanitize_args($args);
        $argsJson = json_encode($sanitized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $argsJson = ($argsJson === false || strlen($argsJson) > 65000) ? null : $argsJson;
        $stmt = $pdo->prepare("INSERT INTO huli_mcp_tool_calls (role, user_id, username, tool_name, args_json, status, error_msg, ip_address, latency_ms) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $ctx['role'] ?? 'user',
            (int)($ctx['id'] ?? 0),
            (string)($ctx['username'] ?? ''),
            $toolName,
            $argsJson,
            $status === 'error' ? 'error' : 'success',
            (string)$errorMsg,
            huli_mcp_audit_ip(),
            (int)$latencyMs,
        ]);
    } catch (Throwable $e) { error_log('[mcp_lib] 工具调用记录写入失败: ' . $e->getMessage()); }
}

function huli_mcp_log_download($role, $via = 'public') {
    try {
        huli_mcp_ensure_audit_schema();
        $pdo = huli_mcp_pdo();
        $stmt = $pdo->prepare("INSERT INTO huli_mcp_instruction_downloads (role, ip_address, user_agent, via) VALUES (?, ?, ?, ?)");
        $stmt->execute([(string)$role, huli_mcp_audit_ip(), huli_mcp_audit_ua(), $via === 'access_doc' ? 'access_doc' : 'public']);
    } catch (Throwable $e) { error_log('[mcp_lib] 指令下载记录写入失败: ' . $e->getMessage()); }
}

function huli_mcp_session_start($sessionId, $ctx, $protocol) {
    $dbId = null;
    try {
        huli_mcp_ensure_audit_schema();
        $pdo = huli_mcp_pdo();
        $stmt = $pdo->prepare("INSERT INTO huli_mcp_sessions (session_id, role, user_id, username, protocol, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            (string)$sessionId,
            $ctx['role'] ?? 'user',
            (int)($ctx['id'] ?? 0),
            (string)($ctx['username'] ?? ''),
            (string)$protocol,
            huli_mcp_audit_ip(),
            huli_mcp_audit_ua(),
        ]);
        $dbId = (int)$pdo->lastInsertId();
    } catch (Throwable $e) { error_log('[mcp_lib] 会话建立记录写入失败: ' . $e->getMessage()); }
    return $dbId;
}

function huli_mcp_session_bump($sessionId, $status = null) {
    try {
        huli_mcp_ensure_audit_schema();
        $pdo = huli_mcp_pdo();
        $stmt = $pdo->prepare("UPDATE huli_mcp_sessions SET messages_count = messages_count + 1" . ($status !== null ? ", status = '" . ($status === 'closed' ? 'closed' : 'timed_out') . "'" : "") . " WHERE session_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([(string)$sessionId]);
    } catch (Throwable $e) { error_log('[mcp_lib] 会话更新写入失败: ' . $e->getMessage()); }
}

function huli_mcp_session_close($sessionId, $status = 'closed') {
    try {
        huli_mcp_ensure_audit_schema();
        $pdo = huli_mcp_pdo();
        $stmt = $pdo->prepare("UPDATE huli_mcp_sessions SET status = ?, ended_at = NOW() WHERE session_id = ? AND status = 'active'");
        $stmt->execute([$status === 'timed_out' ? 'timed_out' : 'closed', (string)$sessionId]);
    } catch (Throwable $e) { error_log('[mcp_lib] 会话关闭写入失败: ' . $e->getMessage()); }
}

function huli_mcp_validate_token($token) {
    if (!is_string($token) || $token === '') {
        return null;
    }
    $hash = hash('sha256', $token);
    $pdo = huli_mcp_pdo();
    $stmt = $pdo->prepare("SELECT id, username, status FROM huli_users WHERE mcp_token_hash = ? LIMIT 1");
    $stmt->execute([$hash]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        return ['role' => 'user', 'id' => (int)$user['id'], 'username' => $user['username'], 'status' => $user['status']];
    }
    $stmt = $pdo->prepare("SELECT id, username, status FROM huli_admins WHERE mcp_token_hash = ? LIMIT 1");
    $stmt->execute([$hash]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($admin) {
        return ['role' => 'admin', 'id' => (int)$admin['id'], 'username' => $admin['username'], 'status' => (int)$admin['status']];
    }
    return null;
}

$GLOBALS['HULI_MCP_TOOLS'] = [];

function huli_mcp_register_tool($name, $description, $inputSchema, $callable) {
    $GLOBALS['HULI_MCP_TOOLS'][$name] = [
        'name' => $name,
        'description' => $description,
        'inputSchema' => $inputSchema,
        'callable' => $callable,
    ];
}

function huli_mcp_list_tools() {
    $out = [];
    foreach ($GLOBALS['HULI_MCP_TOOLS'] as $t) {
        $out[] = [
            'name' => $t['name'],
            'description' => $t['description'],
            'inputSchema' => $t['inputSchema'],
        ];
    }
    return $out;
}

function huli_mcp_json($data) {
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function huli_mcp_detect_scheme() {
    $scheme = 'http';
    if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)) {
        $scheme = 'https';
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $forwarded = strtolower(trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]));
        if ($forwarded === 'https' || $forwarded === 'http') {
            $scheme = $forwarded;
        }
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) !== 'off') {
        $scheme = 'https';
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']) && $scheme === 'http') {
        $scheme = 'https';
    }
    return $scheme;
}

function huli_mcp_public_url($path = '/mcp.php') {
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
    return huli_mcp_detect_scheme() . '://' . $host . $path;
}

function huli_mcp_ensure_log_schema() {
    static $done = false;
    if ($done) { return; }
    $done = true;
    $pdo = huli_mcp_pdo();
    $exists = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'huli_mcp_logs'")->fetchColumn();
    if (!$exists) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `huli_mcp_logs` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `request_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `role` ENUM('user','admin') NOT NULL,
      `user_id` INT UNSIGNED NOT NULL DEFAULT 0,
      `username` VARCHAR(64) NOT NULL DEFAULT '',
      `method` VARCHAR(64) NOT NULL DEFAULT '',
      `tool_name` VARCHAR(64) NULL DEFAULT NULL,
      `ip_address` VARCHAR(64) NOT NULL DEFAULT '',
      `user_agent` VARCHAR(255) NOT NULL DEFAULT '',
      `status` ENUM('success','error','invalid') NOT NULL DEFAULT 'success',
      `error_msg` VARCHAR(500) NULL DEFAULT NULL,
      `latency_ms` INT UNSIGNED NOT NULL DEFAULT 0,
      PRIMARY KEY (`id`),
      KEY `idx_request_time` (`request_time`),
      KEY `idx_role_time` (`role`, `request_time`),
      KEY `idx_user_time` (`role`, `user_id`, `request_time`),
      KEY `idx_method` (`method`),
      KEY `idx_tool` (`tool_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MCP 请求日志'");
        return;
    }
    $col = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'huli_mcp_logs' AND column_name = 'user_agent'")->fetchColumn();
    if (!$col) {
        $pdo->exec("ALTER TABLE `huli_mcp_logs` ADD COLUMN `user_agent` VARCHAR(255) NOT NULL DEFAULT '' AFTER `ip_address`");
    }
}

function huli_mcp_log($ctx, $method, $toolName, $status, $errorMsg = '', $latencyMs = 0) {
    try {
        huli_mcp_ensure_log_schema();
        $pdo = huli_mcp_pdo();
        $stmt = $pdo->prepare("INSERT INTO huli_mcp_logs (role, user_id, username, method, tool_name, ip_address, user_agent, status, error_msg, latency_ms) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $ctx['role'] ?? 'user',
            (int)($ctx['id'] ?? 0),
            (string)($ctx['username'] ?? ''),
            (string)$method,
            $toolName,
            (string)($_SERVER['REMOTE_ADDR'] ?? ''),
            (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
            $status,
            $errorMsg !== '' ? mb_substr($errorMsg, 0, 500) : null,
            (int)$latencyMs,
        ]);
    } catch (Throwable $e) {
         
    }
}

function huli_device_label($ua) {
    $ua = (string)$ua;
    if ($ua === '') {
        return '-';
    }
    $device = '未知设备';
    if (stripos($ua, 'iPhone') !== false) {
        $device = 'iPhone';
    } elseif (stripos($ua, 'iPad') !== false) {
        $device = 'iPad';
    } elseif (stripos($ua, 'Android') !== false) {
        $device = 'Android';
    } elseif (stripos($ua, 'Windows') !== false) {
        $device = 'Windows';
    } elseif (stripos($ua, 'Mac OS X') !== false || stripos($ua, 'Macintosh') !== false) {
        $device = 'macOS';
    } elseif (stripos($ua, 'Linux') !== false) {
        $device = 'Linux';
    }
    $browser = '未知浏览器';
    if (stripos($ua, 'Edg') !== false) {
        $browser = 'Edge';
    } elseif (stripos($ua, 'Claude') !== false) {
        $browser = 'Claude';
    } elseif (stripos($ua, 'Chrome') !== false) {
        $browser = 'Chrome';
    } elseif (stripos($ua, 'Safari') !== false) {
        $browser = 'Safari';
    } elseif (stripos($ua, 'Firefox') !== false) {
        $browser = 'Firefox';
    } elseif (stripos($ua, 'curl') !== false) {
        $browser = 'curl';
    }
    return $device . ' / ' . $browser;
}
