<?php
require_once __DIR__ . '/../common/session_boot.php';
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}
if (!file_exists('../config.php')) {
    die("出现错误！配置文件丢失，请先完成安装。");
}
require_once '../config.php';
require_once __DIR__ . '/../common/mcp/mcp_lib.php';
require_once __DIR__ . '/../common/mcp/mcp_tools_admin.php';

$admin_id = (int)$_SESSION['admin_id'];
$pdo = huli_mcp_pdo();
$stmt = $pdo->prepare("SELECT id, username, mcp_token_hash, mcp_token_prefix FROM huli_admins WHERE id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$admin) {
    die('管理员不存在');
}
$stmt_settings = $pdo->query("SELECT setting_key, setting_value FROM huli_settings");
$settings = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR);
$site_name = $settings['site_name'] ?? 'huliapi';

$hasToken = !empty($admin['mcp_token_hash']);
$tokenPrefix = $admin['mcp_token_prefix'] ?? '';
$feedback_msg = '';
$feedback_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'generate') {
        $token = huli_mcp_generate_token('admin');
        huli_mcp_store_token('admin', $admin_id, $token);
        $_SESSION['admin_mcp_new_token'] = $token;
        $feedback_msg = 'MCP Token 已生成，请立即复制保存（刷新后不再显示）。';
        $feedback_type = 'success';
        header('Location: mcp.php');
        exit;
    }
    if ($_POST['action'] === 'revoke') {
        huli_mcp_clear_token('admin', $admin_id);
        unset($_SESSION['admin_mcp_new_token']);
        $feedback_msg = 'MCP Token 已撤销，原有配置立即失效。';
        $feedback_type = 'success';
        header('Location: mcp.php');
        exit;
    }
}

$newToken = '';
if (isset($_SESSION['admin_mcp_new_token'])) {
    $newToken = $_SESSION['admin_mcp_new_token'];
    unset($_SESSION['admin_mcp_new_token']);
}

$mcpUrl = huli_mcp_public_url('/mcp.php');

$tools = huli_mcp_list_tools();

$configToken = $newToken;
if ($configToken === '') {
    $configToken = $hasToken ? '请重新生成 Token 后填入完整值（前缀: ' . $tokenPrefix . '）' : '待生成 Token';
}
$configSite = $settings['site_name'] ?? 'huliapi';

$aiInstruction = '';
if ($newToken !== '') {
    $aiInstruction = "# AI Agent 自动接入指令\n"
        . "\n请根据以下信息，为你的 MCP 功能配置远程服务并验证可用。配置方式以你自身的官方文档为准，不确定时先自查（例如搜索「<你的名称> MCP http 配置」），不要猜测路径或格式。\n"
        . "\n## MCP 服务信息\n"
        . "- 服务地址（支持 Streamable HTTP 与 SSE 双协议）：{$mcpUrl}\n"
        . "- 鉴权方式：请求头 `Authorization: Bearer {$newToken}`\n"
        . "- TLS：无需额外处理证书\n"
        . "\n## 配置步骤\n"
        . "1. 按你自身支持的 MCP 客户端配置格式，添加一个名为 `huliapi-admin` 的 MCP 服务：\n"
        . "   - url：{$mcpUrl}\n"
        . "   - headers.Authorization：Bearer {$newToken}\n"
        . "2. 重启或重新加载 MCP 客户端，使配置生效。\n"
        . "3. 列出 MCP 工具，确认能获取到工具列表（共 " . count($tools) . " 个管理员侧工具），且无 TLS 或 401 错误。\n"
        . "4. 调用一个只读工具（例如 get_system_stats）确认连通性。\n"
        . "\n## 完成\n"
        . "配置成功且验证通过后，请简短输出确认信息（含服务地址、工具数量、接入方式）。";
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MCP 配置 - <?php echo htmlspecialchars($site_name); ?></title>
    <link rel="stylesheet" type="text/css" href="../assets/css/materialdesignicons.min.css">
    <link rel="stylesheet" type="text/css" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="../assets/css/style.min.css">
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
        .badge-role { font-size: 12px; }
    </style>
</head>
<body>
<div class="container py-4" style="max-width: 880px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">
            <i class="mdi mdi-server-network text-primary me-2"></i>MCP 配置
            <span class="badge bg-danger ms-2">管理员</span>
        </h4>
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
    "<?php echo htmlspecialchars($configSite) ?>-admin": {
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
            <div class="card-title"><i class="mdi mdi-robot-outline me-2"></i>AI 自动接入指令（一键复制）</div>
        </div>
        <div class="card-body">
            <div class="small text-muted mb-2">复制下方指令并发送给你的 AI 编程助手（Claude Code / Codex / Cursor 等），它会自动读取并完成 MCP 接入：</div>
            <?php if ($aiInstruction !== ''): ?>
            <div class="code-block" id="ai-instruction"><?php echo htmlspecialchars($aiInstruction); ?></div>
            <button class="btn btn-primary btn-sm btn-copy" data-copy="#ai-instruction"><i class="mdi mdi-content-copy me-1"></i>一键复制接入指令</button>
            <button class="btn btn-success btn-sm btn-download-md" data-filename="huliapi-mcp-admin-接入指令.md"><i class="mdi mdi-download me-1"></i>下载 .md 接入文档</button>
            <?php else: ?>
            <div class="alert alert-warning mb-0 py-2"><i class="mdi mdi-alert-outline me-1"></i>接入指令中包含完整 Token。<?php echo $hasToken ? '请点击上方「重新生成 Token」后立即复制（Token 仅生成时可见）。' : '请先点击上方「生成 Token」，生成后即可一键复制接入指令。'; ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <div class="card-title"><i class="mdi mdi-shield-alert-outline me-2"></i>安全说明</div>
        </div>
        <div class="card-body small text-muted">
            <ul class="mb-0 ps-3">
                <li>管理员 MCP Token 拥有平台高级管理权限（用户余额/状态调整、API 状态变更等），请务必妥善保管，切勿泄露。</li>
                <li>Token 以哈希形式存储在数据库，明文仅在生成时展示一次。</li>
                <li>管理员 MCP 与用户 MCP 完全隔离，管理员 Token 无法调用用户侧工具，反之亦然。</li>
                <li>如需停用，请点击「撤销 Token」，配置将立即失效。</li>
            </ul>
        </div>
    </div>
</div>
<script src="../assets/js/jquery.min.js"></script>
<script src="../assets/js/bootstrap.min.js"></script>
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
