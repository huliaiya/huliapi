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
