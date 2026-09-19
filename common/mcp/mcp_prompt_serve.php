<?php
// 公开下载转发：admin/user 根目录入口共用此逻辑，只输出对应的固定提示词文件，不暴露内部相对路径。
function huli_serve_mcp_prompt($role) {
    if ($role !== 'admin' && $role !== 'user') {
        http_response_code(400);
        echo "# 错误\n\nrole 仅支持 admin 或 user。";
        return;
    }
    $path = __DIR__ . '/mcp_prompt_' . $role . '.md';
    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') {
        http_response_code(404);
        echo "# 错误\n\n未找到固定指令文件：mcp_prompt_" . $role . ".md";
        return;
    }

    if (function_exists('huli_mcp_log_download')) {
        try { huli_mcp_log_download($role, 'public'); }
        catch (Throwable $e) { error_log('[mcp_prompt] 下载记录写入失败: ' . $e->getMessage()); }
    }
    $roleName = $role === 'admin' ? '管理员' : '用户';
    $downloadFilename = 'huliapi-mcp-' . $role . '-接入指令.md';
    header('Content-Type: text/markdown; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $downloadFilename . '"; filename*=UTF-8\'\'' .
        rawurlencode('接入指令-' . $roleName . '.md'));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store, max-age=0');
    echo $raw;
}
