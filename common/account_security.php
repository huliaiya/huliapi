<?php
if (!defined('HULI_ACCOUNT_SECURITY_LIB')) {
    define('HULI_ACCOUNT_SECURITY_LIB', 1);
}

/**
 * 在密码重置或修改后吊销该账号的持久化登录与 MCP 凭证。
 * 已有登录会话无法逐个失效（未存储会话索引），但记住登录令牌与 MCP Token 必须立即作废。
 */
function huli_revoke_account_tokens(PDO $pdo, string $role, int $accountId): void
{
    if ($accountId <= 0 || ($role !== 'admin' && $role !== 'user')) {
        return;
    }
    if ($role === 'admin') {
        try {
            $pdo->prepare("DELETE FROM huli_admin_remember WHERE admin_id = ?")->execute([$accountId]);
        } catch (Throwable $e) {
            error_log('[account_security] 清理记住登录令牌失败: ' . $e->getMessage());
        }
        try {
            $pdo->prepare("UPDATE huli_admins SET mcp_token_hash = NULL, mcp_token_prefix = NULL WHERE id = ?")->execute([$accountId]);
        } catch (Throwable $e) {
            error_log('[account_security] 吊销管理员 MCP Token 失败: ' . $e->getMessage());
        }
        return;
    }
    try {
        $pdo->prepare("UPDATE huli_users SET mcp_token_hash = NULL, mcp_token_prefix = NULL WHERE id = ?")->execute([$accountId]);
    } catch (Throwable $e) {
        error_log('[account_security] 吊销用户 MCP Token 失败: ' . $e->getMessage());
    }
}
