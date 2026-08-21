<?php
require_once __DIR__ . '/../../../common/session_boot.php';
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$rootPath = dirname(__DIR__, 3);
define('ROOT_PATH', $rootPath . '/');
if (!file_exists(ROOT_PATH . 'config.php')) {
    die("系统错误：配置文件丢失。路径: " . ROOT_PATH . 'config.php');
}
require_once ROOT_PATH . 'config.php';
require_once ROOT_PATH . 'common/mcp/mcp_lib.php';
require_once ROOT_PATH . 'common/mcp/mcp_tools_user.php';

$user_id = (int)$_SESSION['user_id'];
$pdo = huli_mcp_pdo();
$stmt = $pdo->prepare("SELECT id, username, email, mcp_token_hash, mcp_token_prefix FROM huli_users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    die('用户不存在');
}
$stmt_settings = $pdo->query("SELECT setting_key, setting_value FROM huli_settings");
$settings = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR);
$site_name = $settings['site_name'] ?? 'huliapi';

$hasToken = !empty($user['mcp_token_hash']);
$tokenPrefix = $user['mcp_token_prefix'] ?? '';
$feedback_msg = '';
$feedback_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'generate') {
        $token = huli_mcp_generate_token('user');
        huli_mcp_store_token('user', $user_id, $token);
        $_SESSION['mcp_new_token'] = $token;
        $feedback_msg = 'MCP Token 已生成，请立即复制保存（刷新后不再显示）。';
        $feedback_type = 'success';
        header('Location: mcp.php');
        exit;
    }
    if ($_POST['action'] === 'revoke') {
        huli_mcp_clear_token('user', $user_id);
        unset($_SESSION['mcp_new_token']);
        $feedback_msg = 'MCP Token 已撤销，原有配置立即失效。';
        $feedback_type = 'success';
        header('Location: mcp.php');
        exit;
    }
}

$newToken = '';
if (isset($_SESSION['mcp_new_token'])) {
    $newToken = $_SESSION['mcp_new_token'];
    unset($_SESSION['mcp_new_token']);
}

$mcpUrl = huli_mcp_public_url('/mcp.php');

$tools = huli_mcp_list_tools();

$configToken = $newToken;
if ($configToken === '') {
    $configToken = $hasToken ? '请重新生成 Token 后填入完整值（前缀: ' . $tokenPrefix . '）' : '待生成 Token';
}
$configSite = $settings['site_name'] ?? 'huliapi';

$aiInstruction = '';
$mcpAccessUrl = '';
if ($newToken !== '') {
    $mcpAccessUrl = huli_mcp_public_url('/mcp_access.php/huliapi-mcp-user-接入指令.md?token=' . urlencode($newToken));
    $aiInstruction = "请下载并读取以下文件：\n"
        . $mcpAccessUrl . "\n"
        . "严格按照文档内容及步骤准确执行，不得擅自修改、遗漏或添加操作。\n"
        . "如遇错误，请立即停止，并输出错误步骤、完整错误信息、可能原因及解决建议。执行完成后，请汇总执行结果。涉及 Token、密钥、密码等敏感信息时，必须脱敏展示，禁止泄露。";
}

$mcpLogToday = 0;
$mcpLogYesterday = 0;
$mcpLogTotal = 0;
$mcpLogSuccess = 0;
$mcpLogError = 0;
$mcpLogRecent = [];
try {
    huli_mcp_ensure_log_schema();
    $pdo_log = huli_mcp_pdo();
    $mcpLogToday = (int)$pdo_log->query("SELECT COUNT(*) FROM huli_mcp_logs WHERE role = 'user' AND user_id = " . (int)$user_id . " AND DATE(request_time) = CURDATE()")->fetchColumn();
    $mcpLogYesterday = (int)$pdo_log->query("SELECT COUNT(*) FROM huli_mcp_logs WHERE role = 'user' AND user_id = " . (int)$user_id . " AND DATE(request_time) = CURDATE() - INTERVAL 1 DAY")->fetchColumn();
    $mcpLogTotal = (int)$pdo_log->query("SELECT COUNT(*) FROM huli_mcp_logs WHERE role = 'user' AND user_id = " . (int)$user_id)->fetchColumn();
    $mcpLogSuccess = (int)$pdo_log->query("SELECT COUNT(*) FROM huli_mcp_logs WHERE role = 'user' AND user_id = " . (int)$user_id . " AND status = 'success'")->fetchColumn();
    $mcpLogError = (int)$pdo_log->query("SELECT COUNT(*) FROM huli_mcp_logs WHERE role = 'user' AND user_id = " . (int)$user_id . " AND status IN ('error','invalid')")->fetchColumn();
    $stmt_log = $pdo_log->prepare("SELECT request_time, method, tool_name, status, latency_ms, ip_address, user_agent FROM huli_mcp_logs WHERE role = 'user' AND user_id = ? ORDER BY id DESC LIMIT 8");
    $stmt_log->execute([$user_id]);
    $mcpLogRecent = $stmt_log->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}
$mcpLogSuccessRate = $mcpLogTotal > 0 ? round(($mcpLogSuccess / $mcpLogTotal) * 100, 1) : 0.0;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MCP 配置 - <?php echo htmlspecialchars($site_name); ?></title>
    <link rel="stylesheet" type="text/css" href="../../../assets/css/materialdesignicons.min.css">
    <link rel="stylesheet" type="text/css" href="../../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="../../../assets/css/style.min.css">
    <style>
        body {
            background:
                radial-gradient(circle at 10% 20%, rgba(93, 177, 255, 0.35), transparent 45%),
                radial-gradient(circle at 90% 80%, rgba(38, 208, 194, 0.25), transparent 48%),
                linear-gradient(135deg, #f5f8fc 0%, #eef3fa 100%) !important;
            background-attachment: fixed !important;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .card {
            background: rgba(255, 255, 255, 0.45) !important;
            backdrop-filter: blur(25px) saturate(200%) !important;
            -webkit-backdrop-filter: blur(25px) saturate(200%) !important;
            border: 1px solid rgba(255, 255, 255, 0.35) !important;
            border-radius: 20px !important;
            box-shadow: 0 15px 50px rgba(10, 25, 50, 0.05), inset 0 1px 0 rgba(255, 255, 255, 0.4) !important;
        }
        .card-header { background: transparent !important; border-bottom: 1px solid rgba(255, 255, 255, 0.25) !important; }
        .mcp-box {
            background: rgba(255, 255, 255, 0.5) !important;
            border: 1px solid rgba(255, 255, 255, 0.35) !important;
            padding: 14px 16px;
            border-radius: 12px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            word-break: break-all;
            margin-bottom: 14px;
        }
        .token-full {
            background: rgba(255, 251, 235, 0.85) !important;
            border: 1px solid #f5c542 !important;
            padding: 16px;
            border-radius: 12px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            word-break: break-all;
            margin-bottom: 14px;
        }
        .tool-list .tool-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 10px 4px;
            border-bottom: 1px dashed rgba(255, 255, 255, 0.4);
        }
        .tool-item .mdi { color: #5d9fe8; font-size: 20px; }
        .code-block {
            background: #1e293b;
            color: #e2e8f0;
            padding: 16px;
            border-radius: 12px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            white-space: pre-wrap;
            word-break: break-all;
            margin-bottom: 14px;
        }
        .btn-copy { cursor: pointer; }
    </style>
</head>
<body>
<div class="container py-4" style="max-width: 860px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0"><i class="mdi mdi-server-network text-primary me-2"></i>MCP 配置</h4>
    </div>

    <?php if ($feedback_msg): ?>
    <div class="alert alert-<?php echo $feedback_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($feedback_msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <?php if ($newToken !== ''): ?>
    <div class="card mb-4">
        <div class="card-header">
            <div class="card-title"><i class="mdi mdi-alert-decagram text-warning me-2"></i>新 Token 已生成（仅此一次显示）</div>
        </div>
        <div class="card-body">
            <div class="token-full" id="new-token"><?php echo htmlspecialchars($newToken); ?></div>
            <button class="btn btn-primary btn-sm btn-copy" data-copy="#new-token"><i class="mdi mdi-content-copy me-1"></i>复制 Token</button>
            <div class="text-danger small mt-2"><i class="mdi mdi-information-outline me-1"></i>请立即妥善保存，页面刷新后将无法再次查看。</div>
        </div>
    </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <div class="card-title"><i class="mdi mdi-link-variant me-2"></i>服务信息</div>
        </div>
        <div class="card-body">
            <div class="mb-2 small text-muted">MCP 服务器地址（Streamable HTTP / SSE 双协议）：</div>
            <div class="mcp-box"><?php echo htmlspecialchars($mcpUrl); ?></div>
            <div class="mb-2 small text-muted">Token 状态：</div>
            <div class="mcp-box">
                <?php if ($hasToken): ?>
                    <span class="text-success"><i class="mdi mdi-check-circle me-1"></i>已启用</span>
                    <span class="ms-2 text-muted">(前缀: <?php echo htmlspecialchars($tokenPrefix); ?>)</span>
                <?php else: ?>
                    <span class="text-muted"><i class="mdi mdi-minus-circle me-1"></i>未生成</span>
                <?php endif; ?>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <?php if (!$hasToken): ?>
                <form method="POST" action="mcp.php" class="d-inline">
                    <input type="hidden" name="action" value="generate">
                    <button type="submit" class="btn btn-primary"><i class="mdi mdi-key-plus me-1"></i>生成 Token</button>
                </form>
                <?php else: ?>
                <form method="POST" action="mcp.php" class="d-inline">
                    <input type="hidden" name="action" value="generate">
                    <button type="submit" class="btn btn-warning" onclick="return confirm('重新生成后，旧 Token 将立即失效，确定继续？');"><i class="mdi mdi-key-change me-1"></i>重新生成 Token</button>
                </form>
                <form method="POST" action="mcp.php" class="d-inline">
                    <input type="hidden" name="action" value="revoke">
                    <button type="submit" class="btn btn-danger" onclick="return confirm('撤销后该 MCP 服务立即不可用，确定继续？');"><i class="mdi mdi-key-remove me-1"></i>撤销 Token</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <div class="card-title"><i class="mdi mdi-robot mdi-spin me-2"></i>可用的 MCP 工具（<?php echo count($tools); ?> 个）</div>
        </div>
        <div class="card-body tool-list">
            <?php if (empty($tools)): ?>
                <div class="text-muted">暂无可用工具</div>
            <?php else: ?>
                <?php foreach ($tools as $t): ?>
                <div class="tool-item">
                    <i class="mdi mdi-toolbox-outline mt-1"></i>
                    <div>
                        <div class="fw-semibold text-primary"><?php echo htmlspecialchars($t['name']); ?></div>
                        <div class="small text-muted"><?php echo htmlspecialchars($t['description']); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <div class="card-title"><i class="mdi mdi-cog-outline me-2"></i>客户端接入配置示例</div>
        </div>
        <div class="card-body">
            <div class="small text-muted mb-2">将以下 JSON 配置到支持 MCP 的客户端（Claude Desktop / Cursor / Dify 等）：</div>
            <div class="code-block" id="client-config">{
  "mcpServers": {
    "<?php echo htmlspecialchars($configSite) ?>-user": {
      "url": "<?php echo htmlspecialchars($mcpUrl); ?>",
      "headers": {
        "Authorization": "Bearer <?php echo htmlspecialchars($configToken); ?>"}
    }
  }
}</div>
            <button class="btn btn-outline-primary btn-sm btn-copy" data-copy="#client-config"><i class="mdi mdi-content-copy me-1"></i>复制配置</button>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <div class="card-title"><i class="mdi mdi-robot-outline me-2"></i>AI 自动接入命令（一键复制）</div>
        </div>
        <div class="card-body">
            <div class="small text-muted mb-2">复制下方命令并发送给你的 AI 编程助手（Claude Code / Codex / Cursor 等），它会自动下载指令文档并完成 MCP 接入：</div>
            <?php if ($aiInstruction !== ''): ?>
            <div class="mb-2"><i class="mdi mdi-link-variant me-1 text-muted"></i><a href="<?php echo htmlspecialchars($mcpAccessUrl); ?>" target="_blank" class="text-break"><?php echo htmlspecialchars($mcpAccessUrl); ?></a></div>
            <div class="code-block" id="ai-instruction"><?php echo htmlspecialchars($aiInstruction); ?></div>
            <button class="btn btn-primary btn-sm btn-copy" data-copy="#ai-instruction"><i class="mdi mdi-content-copy me-1"></i>一键复制接入命令</button>
            <?php else: ?>
            <div class="alert alert-warning mb-0 py-2"><i class="mdi mdi-alert-outline me-1"></i>接入命令中包含 Token。<?php echo $hasToken ? '请点击上方「重新生成 Token」后立即复制（Token 仅生成时可见）。' : '请先点击上方「生成 Token」，生成后即可一键复制接入命令。'; ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <div class="card-title"><i class="mdi mdi-shield-alert-outline me-2"></i>安全说明</div>
        </div>
        <div class="card-body small text-muted">
            <ul class="mb-0 ps-3">
                <li>MCP Token 等同于你的账号部分操作权限（查看账号信息、调用 API、管理自己的 API Key），请勿泄露给他人。</li>
                <li>Token 以哈希形式存储在数据库，明文仅在生成时展示一次。</li>
                <li>如需停用，请点击「撤销 Token」，配置将立即失效。</li>
                <li>本站 MCP 服务与管理员 MCP 服务相互隔离，你无法调用任何管理功能。</li>
            </ul>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <div class="card-title"><i class="mdi mdi-history me-2"></i>我的 MCP 调用记录</div>
        </div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-6 col-md-3">
                    <div class="text-muted small">今日调用</div>
                    <div class="h4 mb-0 text-primary"><?php echo number_format($mcpLogToday); ?></div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-muted small">昨日调用</div>
                    <div class="h4 mb-0 text-secondary"><?php echo number_format($mcpLogYesterday); ?></div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-muted small">历史累计</div>
                    <div class="h4 mb-0"><?php echo number_format($mcpLogTotal); ?></div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-muted small">成功率</div>
                    <div class="h4 mb-0 text-success"><?php echo $mcpLogSuccessRate; ?>%</div>
                </div>
            </div>
            <?php if (empty($mcpLogRecent)): ?>
                <div class="text-muted small">暂无调用记录。AI 接入完成后，相关调用会出现在此处。</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead><tr><th>时间</th><th>方法</th><th>工具</th><th>状态</th><th>设备</th><th class="text-end">耗时</th><th>IP</th></tr></thead>
                        <tbody>
                        <?php foreach ($mcpLogRecent as $lr): ?>
                            <tr>
                                <td class="small text-muted"><?php echo htmlspecialchars($lr['request_time']); ?></td>
                                <td><span class="badge bg-light text-dark"><?php echo htmlspecialchars($lr['method']); ?></span></td>
                                <td><?php echo $lr['tool_name'] ? '<code>' . htmlspecialchars($lr['tool_name']) . '</code>' : '<span class="text-muted">-</span>'; ?></td>
                                <td>
                                    <?php if ($lr['status'] === 'success'): ?>
                                        <span class="badge bg-success">成功</span>
                                    <?php elseif ($lr['status'] === 'invalid'): ?>
                                        <span class="badge bg-secondary">非法</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">失败</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small" title="<?php echo htmlspecialchars($lr['user_agent'] ?? ''); ?>"><?php echo htmlspecialchars(huli_device_label($lr['user_agent'] ?? '')); ?></td>
                                <td class="text-end small"><?php echo (int)$lr['latency_ms']; ?> ms</td>
                                <td class="small text-muted"><?php echo htmlspecialchars($lr['ip_address']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="../../../assets/js/jquery.min.js"></script>
<script src="../../../assets/js/bootstrap.min.js"></script>
<script>
$(function() {
    $('.btn-copy').on('click', function() {
        var text = $(this).data('copy');
        var content = $(text).text().trim();
        var done = function() {
            var self = this;
            $(this).text('已复制').prop('disabled', true);
            setTimeout(function() { $(self).text('复制').prop('disabled', false); }, 1500);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(content).then(done.bind(this)).catch(function() {
                fallbackCopy(content, done.bind(this));
            });
        } else {
            fallbackCopy(content, done.bind(this));
        }
    });
    function fallbackCopy(text, cb) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch(e) {}
        document.body.removeChild(ta);
        cb();
    }
    $('.btn-download-md').on('click', function() {
        var content = $('#ai-instruction').text().trim();
        if (!content) { return; }
        var blob = new Blob(['\uFEFF' + content], { type: 'text/markdown;charset=utf-8' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = $(this).data('filename');
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        var self = this;
        var orig = $(this).html();
        $(this).prop('disabled', true).html('<i class="mdi mdi-check me-1"></i>已下载');
        setTimeout(function() { $(self).prop('disabled', false).html(orig); }, 1500);
    });
});
</script>
</body>
</html>
