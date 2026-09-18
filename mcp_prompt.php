<?php
// 公开提供固定 MCP 接入提示词文件（非敏感文档，可直接下载；不含真实 Token/密钥）。
$role = (string)($_GET['role'] ?? '');
if ($role !== 'admin' && $role !== 'user') {
    http_response_code(400);
    echo "# 错误\n\nrole 参数仅支持 admin 或 user。";
    exit;
}
$path = __DIR__ . '/common/mcp/mcp_prompt_' . $role . '.md';
$raw = @file_get_contents($path);
if ($raw === false || $raw === '') {
    http_response_code(404);
    echo "# 错误\n\n未找到固定指令文件：mcp_prompt_" . $role . ".md";
    exit;
}
$downloadFilename = '接入指令-' . ($role === 'admin' ? '管理员' : '用户') . '.md';
$fallbackFilename = 'mcp_prompt_' . $role . '.md';
header('Content-Type: text/markdown; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $fallbackFilename . '"; filename*=UTF-8\'\'' . rawurlencode($downloadFilename));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, max-age=0');
echo $raw;
