<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/common/mcp/mcp_lib.php';

header('Content-Type: text/markdown; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

$token = isset($_GET['token']) ? (string)$_GET['token'] : '';
if ($token === '') {
    http_response_code(400);
    echo "# 错误\n\n缺少 token 参数。\n";
    try {
        $pdo = huli_mcp_pdo();
        $stmt = $pdo->prepare("INSERT INTO huli_mcp_logs (role, user_id, username, method, tool_name, ip_address, status, error_msg, latency_ms) VALUES ('user', 0, '', 'access_doc', NULL, ?, 'invalid', 'missing token', 0)");
        $stmt->execute([(string)($_SERVER['REMOTE_ADDR'] ?? '')]);
    } catch (Throwable $e) {}
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
    } catch (Throwable $e) {}
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

$toolNames = [];
foreach ($tools as $t) {
    $toolNames[] = '- ' . $t['name'] . ': ' . $t['description'];
}
$toolsList = implode("\n", $toolNames);

$out = "# huliapi MCP 接入指令\n\n"
    . "本文档由 huliapi 服务端动态生成，AI Agent 读取本文后请严格按照下方步骤完成 MCP 接入。\n\n"
    . "## MCP 服务信息\n\n"
    . "- 服务名称：`{$serverName}`\n"
    . "- 角色：`{$roleName}`\n"
    . "- 服务地址（支持 Streamable HTTP 与 SSE 双协议）：`{$mcpUrl}`\n"
    . "- 鉴权方式：请求头 `Authorization: Bearer {$token}`\n"
    . "- TLS：使用标准 HTTPS，无需额外处理证书\n"
    . "- 可用工具数量：{$toolCount}\n\n"
    . "## 可用工具\n\n"
    . "{$toolsList}\n\n"
    . "## 配置步骤\n\n"
    . "1. 确认你自身 Agent 的 MCP 配置方式（不确定时先查阅你自身的官方文档或搜索「<你的名称> MCP http 配置」，**严禁硬编码路径与格式**）。\n"
    . "2. 按你自身支持的 MCP 客户端配置格式，添加名为 `{$serverName}` 的 MCP 服务：\n"
    . "   - url：`{$mcpUrl}`\n"
    . "   - 请求头 Authorization：`Bearer {$token}`\n"
    . "3. 重启或重新加载 MCP 客户端，使配置生效。\n"
    . "4. 验证工具列表：应能列出 {$toolCount} 个工具，且无 TLS / 401 等错误。\n"
    . "5. 调用一个只读工具（例如 `get_account_info` / `get_system_stats`）确认连通性。\n\n"
    . "## 验证\n\n"
    . "- [ ] 配置文件中 url、Authorization 与本文档一致\n"
    . "- [ ] 客户端能成功列出全部 {$toolCount} 个工具\n"
    . "- [ ] 至少一个只读工具可正常调用并返回数据\n"
    . "- [ ] 用户 Token 与管理员 Token 已严格隔离（用户 Token 调用管理员工具应返回 `Unknown tool`）\n\n"
    . "## 完成\n\n"
    . "配置成功且验证通过后，简短输出确认（含服务地址、工具数量、接入方式），并对所有敏感信息（Token、密钥、密码）做脱敏展示。\n";

try {
    huli_mcp_ensure_log_schema();
    $pdo = huli_mcp_pdo();
    $stmt = $pdo->prepare("INSERT INTO huli_mcp_logs (role, user_id, username, method, tool_name, ip_address, status, error_msg, latency_ms) VALUES (?, ?, ?, 'access_doc', NULL, ?, 'success', '', 0)");
    $stmt->execute([$ctx['role'], (int)$ctx['id'], (string)$ctx['username'], (string)($_SERVER['REMOTE_ADDR'] ?? '')]);
} catch (Throwable $e) {}

echo $out;
