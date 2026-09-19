<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/common/mcp/mcp_lib.php';

header('Content-Type: text/markdown; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

$authorization = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? '');
if ($authorization === '' && function_exists('getallheaders')) {
    $headers = getallheaders();
    $authorization = (string)($headers['Authorization'] ?? $headers['authorization'] ?? '');
}
$token = preg_match('/^Bearer\s+(.+)$/i', trim($authorization), $matches)
    ? trim($matches[1])
    : '';
if ($token === '') {
    http_response_code(400);
    echo "# 错误\n\n缺少 Authorization Bearer Token。\n";
    try {
        $pdo = huli_mcp_pdo();
        $stmt = $pdo->prepare("INSERT INTO huli_mcp_logs (role, user_id, username, method, tool_name, ip_address, status, error_msg, latency_ms) VALUES ('user', 0, '', 'access_doc', NULL, ?, 'invalid', 'missing token', 0)");
        $stmt->execute([(string)($_SERVER['REMOTE_ADDR'] ?? '')]);
    } catch (Throwable $e) { error_log('[mcp_access] 缺少token日志写入失败: ' . $e->getMessage()); }
    exit;
}

$ctx = huli_mcp_validate_token($token);
if (!$ctx) {
    http_response_code(403);
    echo "# 错误\n\nToken 无效或已失效。\n";
    try {
        $pdo = huli_mcp_pdo();
        $stmt = $pdo->prepare("INSERT INTO huli_mcp_logs (role, user_id, username, method, tool_name, ip_address, status, error_msg, latency_ms) VALUES ('user', 0, '', 'access_doc', NULL, ?, 'error', ?, 0)");
        $stmt->execute([(string)($_SERVER['REMOTE_ADDR'] ?? ''), 'invalid token prefix=' . substr($token, 0, 4) . '***']);
    } catch (Throwable $e) { error_log('[mcp_access] 无效token日志写入失败: ' . $e->getMessage()); }
    exit;
}

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
    [$serverName, $roleName, $mcpUrl, $token, (string)$toolCount],
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
