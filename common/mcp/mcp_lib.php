<?php
if (!defined('HULI_MCP_LIB')) { define('HULI_MCP_LIB', 1); }

function huli_mcp_pdo() {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
    return $pdo;
}

function huli_mcp_ensure_schema() {
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
}

function huli_mcp_clear_token($role, $id) {
    $pdo = huli_mcp_pdo();
    $table = $role === 'admin' ? 'huli_admins' : 'huli_users';
    $stmt = $pdo->prepare("UPDATE `$table` SET mcp_token_hash = NULL, mcp_token_prefix = NULL WHERE id = ?");
    $stmt->execute([$id]);
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
    if ($exists) { return; }
    $pdo->exec("CREATE TABLE IF NOT EXISTS `huli_mcp_logs` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `request_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `role` ENUM('user','admin') NOT NULL,
      `user_id` INT UNSIGNED NOT NULL DEFAULT 0,
      `username` VARCHAR(64) NOT NULL DEFAULT '',
      `method` VARCHAR(64) NOT NULL DEFAULT '',
      `tool_name` VARCHAR(64) NULL DEFAULT NULL,
      `ip_address` VARCHAR(64) NOT NULL DEFAULT '',
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
}

function huli_mcp_log($ctx, $method, $toolName, $status, $errorMsg = '', $latencyMs = 0) {
    try {
        huli_mcp_ensure_log_schema();
        $pdo = huli_mcp_pdo();
        $stmt = $pdo->prepare("INSERT INTO huli_mcp_logs (role, user_id, username, method, tool_name, ip_address, status, error_msg, latency_ms) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $ctx['role'] ?? 'user',
            (int)($ctx['id'] ?? 0),
            (string)($ctx['username'] ?? ''),
            (string)$method,
            $toolName,
            (string)($_SERVER['REMOTE_ADDR'] ?? ''),
            $status,
            $errorMsg !== '' ? mb_substr($errorMsg, 0, 500) : null,
            (int)$latencyMs,
        ]);
    } catch (Throwable $e) {
        // 日志写入失败不应影响主流程
    }
}
