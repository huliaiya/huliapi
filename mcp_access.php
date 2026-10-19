<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/common/mcp/mcp_lib.php';

header('Content-Type: text/markdown; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

$code = trim((string)($_GET['code'] ?? ''));
if ($code === '') {
    http_response_code(400);
    echo "# 错误\n\n缺少短期接入码 code。请登录后在 MCP 页面重新生成（10 分钟内有效，且仅可使用一次）。\n";
    try {
        $pdo = huli_mcp_pdo();
        $stmt = $pdo->prepare("INSERT INTO huli_mcp_logs (role, user_id, username, method, tool_name, ip_address, status, error_msg, latency_ms) VALUES ('user', 0, '', 'access_doc', NULL, ?, 'invalid', 'missing code', 0)");
        $stmt->execute([(string)($_SERVER['REMOTE_ADDR'] ?? '')]);
    } catch (Throwable $e) { error_log('[mcp_access] 缺少code日志写入失败: ' . $e->getMessage()); }
    exit;
}

$access = huli_mcp_consume_access_code($code);
if (!$access) {
    http_response_code(403);
    echo "# 错误\n\n接入码无效、已过期或已被使用，请回 MCP 页面重新生成。\n";
    try {
        $pdo = huli_mcp_pdo();
        $stmt = $pdo->prepare("INSERT INTO huli_mcp_logs (role, user_id, username, method, tool_name, ip_address, status, error_msg, latency_ms) VALUES ('user', 0, '', 'access_doc', NULL, ?, 'error', ?, 0)");
        $stmt->execute([(string)($_SERVER['REMOTE_ADDR'] ?? ''), 'invalid access code']);
    } catch (Throwable $e) { error_log('[mcp_access] 无效code日志写入失败: ' . $e->getMessage()); }
    exit;
}

$role = $access['role'] === 'admin' ? 'admin' : 'user';
$lookupId = (int)$access['user_id'];
$pdo = huli_mcp_pdo();
$table = $role === 'admin' ? 'huli_admins' : 'huli_users';
$stmt = $pdo->prepare("SELECT id, username, status FROM `$table` WHERE id = ? LIMIT 1");
$stmt->execute([$lookupId]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$account) {
    http_response_code(403);
    echo "# 错误\n\n接入码对应的账号不存在。\n";
    exit;
}
$ctx = [
    'role' => $role,
    'id' => (int)$account['id'],
    'username' => (string)$account['username'],
    'status' => $role === 'admin' ? (int)$account['status'] : (string)$account['status'],
];

$account_disabled = ($ctx['role'] === 'user' && $ctx['status'] !== 'active')
    || ($ctx['role'] === 'admin' && (int)$ctx['status'] !== 1);
if ($account_disabled) {
    http_response_code(403);
    echo "# 错误\n\n账号状态不允许使用 MCP 服务。\n";
    exit;
}

$role = $ctx['role'];
$roleName = $role === 'admin' ? '管理员' : '用户';
$serverName = $role === 'admin' ? 'huliapi-admin' : 'huliapi-user';
$downloadFilename = ($role === 'admin' ? 'huliapi-mcp-admin' : 'huliapi-mcp-user') . '-接入指令.md';
header('Content-Disposition: attachment; filename="' . $downloadFilename . '"; filename*=UTF-8\'\'' . rawurlencode($downloadFilename));

huli_mcp_pdo();
require_once __DIR__ . '/common/mcp/mcp_tools_' . $role . '.php';
$tools = huli_mcp_list_tools();
$toolCount = count($tools);

$base = huli_mcp_detect_scheme() . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$mcpUrl = $base . '/mcp.php';

$promptFile = __DIR__ . '/common/mcp/mcp_prompt_' . $role . '.md';
$out = @file_get_contents($promptFile);
if ($out === false) {
    http_response_code(500);
    echo "# 错误\n\n未找到角色「{$roleName}」的接入指令固定文件：{$promptFile}\n";
    exit;
}
$out = str_replace(
    ['{{SERVER_NAME}}', '{{ROLE_NAME}}', '{{MCP_URL}}', '{{TOKEN}}', '{{TOOL_COUNT}}'],
    [$serverName, $roleName, $mcpUrl, '（请填写你在 MCP 页面生成并保存的 Token）', (string)$toolCount],
    $out
);

huli_mcp_log_download($role, 'access_doc');

try {
    huli_mcp_ensure_log_schema();
    $pdo = huli_mcp_pdo();
    $stmt = $pdo->prepare("INSERT INTO huli_mcp_logs (role, user_id, username, method, tool_name, ip_address, status, error_msg, latency_ms) VALUES (?, ?, ?, 'access_doc', NULL, ?, 'success', '', 0)");
    $stmt->execute([$ctx['role'], (int)$ctx['id'], (string)$ctx['username'], (string)($_SERVER['REMOTE_ADDR'] ?? '')]);
} catch (Throwable $e) { error_log('[mcp_access] 成功访问日志写入失败: ' . $e->getMessage()); }

echo $out;
