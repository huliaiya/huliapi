<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
$rootPath = dirname(__DIR__, 3);
define('ROOT_PATH', $rootPath . '/');
if (!file_exists(ROOT_PATH . 'config.php')) { 
    die("系统错误：配置文件丢失。路径: " . ROOT_PATH . 'config.php'); 
}
require_once ROOT_PATH . 'config.php';
require_once ROOT_PATH . 'common/TemplateManager.php';
$template = TemplateManager::getActiveUserTemplate();
$template_base_url = "/template/user/{$template}/";
$is_logged_in = isset($_SESSION['user_id']);
if ($is_logged_in) {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
        $stmt = $pdo->prepare("SELECT api_key FROM sl_users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $_SESSION['user_api_key'] = $user['api_key'] ?? '';
    } catch (PDOException $e) {
    }
}
    $api = null; $params = []; $site_name = 'huliapi';
$is_logged_in = isset($_SESSION['user_id']);
$user_info = $is_logged_in ? ['username' => $_SESSION['user_username'], 'email' => $_SESSION['user_email']] : null;
$api_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$api_id) { header('Location: index.php'); exit; }
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $columns = $pdo->query("SHOW COLUMNS FROM `sl_apis`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('created_at', $columns)) $pdo->exec("ALTER TABLE `sl_apis` ADD `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `status`;");
    if (!in_array('updated_at', $columns)) $pdo->exec("ALTER TABLE `sl_apis` ADD `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;");
    $stmt_api = $pdo->prepare("SELECT * FROM sl_apis WHERE id = ?");
    $stmt_api->execute([$api_id]);
    $api = $stmt_api->fetch(PDO::FETCH_ASSOC);
    if (!$api) { header('Location: index.php'); exit; }
    $params = json_decode($api['parameters'], true);
    if (!is_array($params)) $params = [];
    $stmt_settings = $pdo->query("SELECT setting_value FROM sl_settings WHERE setting_key = 'site_name'");
    $db_site_name = $stmt_settings->fetchColumn();
    if($db_site_name) $site_name = $db_site_name;
} catch (PDOException $e) { }

function getStatusBadge($status) {
    switch ($status) {
        case 'normal': return '<span class="status-badge status-green">正常</span>';
        case 'error': return '<span class="status-badge status-red">异常</span>';
        case 'maintenance': return '<span class="status-badge status-yellow">维护</span>';
        default: return '<span class="status-badge status-gray">未知</span>';
    }
}

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') ? 'https' : 'http';
$base_url = $protocol . "://" . $_SERVER['HTTP_HOST'];
$request_url = $base_url . '/API/' . rawurldecode($api['endpoint']) . '.php';
$example_url = !empty($api['request_example']) ? $base_url . $api['request_example'] : $request_url;
$hasApiKeyParam = false;
foreach($params as $p) {
    if(strtolower($p['name']) === 'apikey' || strtolower($p['name']) === 'api_key') {
        $hasApiKeyParam = true;
        break;
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <title><?php echo htmlspecialchars($api['name']); ?> - API详情 - <?php echo htmlspecialchars($site_name); ?></title>
    <link rel="stylesheet" type="text/css" href="../../../assets/css/materialdesignicons.min.css">
    <link rel="stylesheet" type="text/css" href="../../../assets/css/bootstrap.min.css">
    <style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}
body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    line-height: 1.5;
    background-color: #f5f7fa;
    font-size: 14px;
}
.container-fluid {
    padding: 10px 15px !important;
}
.page-title {
    font-size: 22px;
    font-weight: 600;
    color: #1e293b;
    margin: 10px 0 20px 0;
    position: relative;
    padding-left: 10px;
    border-left: 3px solid #4096ff;
}
.api-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    margin-bottom: 15px;
    overflow: hidden;
}
.api-card .card-header {
    background-color: #f9fafb;
    border-bottom: 1px solid #e5e7eb;
    padding: 8px 15px;
    font-weight: 500;
    color: #374151;
    font-size: 13px;
}
.api-card .card-body {
    padding: 12px 15px;
}
.info-row {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 6px;
    font-size: 12px;
}
.info-row i {
    font-size: 11px;
    color: #6366f1;
}
.info-row strong {
    font-weight: 500;
    color: #1f2937;
}
.url-box {
    width: 100%;
    padding: 8px 10px;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    font-family: "SFMono-Regular", Menlo, Monaco, Consolas, monospace;
    font-size: 12px;
    margin: 8px 0;
    background-color: #f9fafb;
    word-break: break-all;
}
.copy-btn {
    background-color: #4096ff;
    color: #fff;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 12px;
    cursor: pointer;
    margin-bottom: 10px;
}
.copy-btn:hover {
    background-color: #3385ff;
}
.param-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}
.param-table th, .param-table td {
    border: 1px solid #e5e7eb;
    padding: 8px 10px;
    text-align: left;
}
.param-table th {
    background-color: #f9fafb;
    font-weight: 500;
    color: #374151;
}
.test-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
    font-size: 12px;
}
.test-row label {
    min-width: 60px;
    font-weight: 500;
    color: #374151;
}
.test-row input {
    flex: 1;
    padding: 6px 8px;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    font-size: 12px;
}
.btn-test {
    background-color: #4096ff;
    color: #fff;
    border: none;
    padding: 6px 15px;
    border-radius: 4px;
    font-size: 12px;
    cursor: pointer;
}
.btn-test:hover {
    background-color: #3385ff;
}
.response-area {
    width: 100%;
    height: 200px;
    padding: 8px 10px;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    font-family: "SFMono-Regular", Menlo, Monaco, Consolas, monospace;
    font-size: 12px;
    background-color: #f9fafb;
    overflow-y: auto;
    margin-top: 10px;
    white-space: pre-wrap;
}
.response-area img, .response-area audio, .response-area video {
  max-width: 100%;
  max-height: 180px;
  display: block;
  margin: 8px 0;
  border-radius: 4px;
  cursor: pointer;
}
.response-area pre {
  margin: 0;
  white-space: pre-wrap;
  word-wrap: break-word;
}
.code-tabs {
    display: flex;
    gap: 5px;
    margin-bottom: 10px;
}
.tab-btn {
    padding: 4px 10px;
    background-color: #f3f4f6;
    border: 1px solid #e5e7eb;
    border-radius: 4px 4px 0 0;
    font-size: 12px;
    cursor: pointer;
    border-bottom: none;
}
.tab-btn.active {
    background-color: #4096ff;
    color: #fff;
    border-color: #4096ff;
}
.code-panel {
    display: none;
}
.code-panel.active {
    display: block;
}
.code-panel pre {
    padding: 10px;
    border: 1px solid #e5e7eb;
    border-radius: 0 4px 4px 4px;
    font-family: "SFMono-Regular", Menlo, Monaco, Consolas, monospace;
    font-size: 12px;
    background-color: #f9fafb;
    overflow-x: auto;
}
.status-badge {
    padding: 2px 6px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 500;
}
.status-green {
    background: #dcfce7;
    color: #16a34a;
}
.status-red {
    background: #fee2e2;
    color: #dc2626;
}
.status-yellow {
    background: #fffbeb;
    color: #ca8a04;
}
.status-gray {
    background: #f3f4f6;
    color: #6b7280;
}
.floating-api-switcher {
    position: fixed;
    right: 20px;
    bottom: 20px;
    z-index: 999;
}
.floating-btn {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: #4096ff;
    color: #fff;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 3px 10px rgba(64, 150, 255, 0.4);
    transition: all 0.2s ease;
}
.floating-btn:hover {
    background: #3385ff;
    transform: scale(1.05);
}
.api-list-container {
    display: none;
    position: absolute;
    right: 0;
    bottom: 60px;
    width: 280px;
    max-height: 400px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
    overflow: hidden;
    border: 1px solid #e8f3ff;
}
.api-list-header {
    padding: 12px 16px;
    background: #f8fbff;
    border-bottom: 1px solid #e8f3ff;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.api-list-header h5 {
    margin: 0;
    font-size: 14px;
    color: #2d3748;
    font-weight: 600;
}
.close-list-btn {
    background: transparent;
    border: none;
    color: #94a3b8;
    font-size: 18px;
    cursor: pointer;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
}
.close-list-btn:hover {
    background: #f1f5f9;
    color: #64748b;
}
.api-list {
    padding: 8px 0;
    max-height: 340px;
    overflow-y: auto;
}
.api-item {
    padding: 10px 16px;
    cursor: pointer;
    transition: background-color 0.2s ease;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-left: 3px solid transparent;
}
.api-item:hover {
    background-color: #f8fbff;
}
.api-item.active {
    background-color: #e8f3ff;
    border-left-color: #4096ff;
}
.api-item .api-info {
    flex: 1;
}
.api-item .api-name {
    font-size: 13px;
    color: #2d3748;
    font-weight: 500;
    margin-bottom: 2px;
}
.api-item .api-endpoint {
    font-size: 11px;
    color: #94a3b8;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.api-item .api-status {
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 10px;
}
.api-list::-webkit-scrollbar {
    width: 5px;
}
.api-list::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 5px;
}
.api-list::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 5px;
}
.api-list::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}
</style>
</head>
<body>
    <div class="container-fluid">
        <h1 class="page-title"><?php echo htmlspecialchars($api['name']); ?></h1>
        <div class="api-card">
            <div class="card-header">
                <i class="mdi mdi-link-variant mr-1"></i>接口信息
            </div>
            <div class="card-body">
                <div class="info-row">
                    <i class="mdi mdi-counter"></i>
                    总调用: <strong><?php echo number_format($api['total_calls']); ?></strong>
                </div>
                <div class="info-row">
                    <i class="mdi mdi-calendar"></i>
                    添加时间: <strong><?php echo date('Y-m-d', strtotime($api['created_at'])); ?></strong>
                </div>
                <div class="info-row">
                    <i class="mdi mdi-update"></i>
                    更新时间: <strong><?php echo date('Y-m-d', strtotime($api['updated_at'])); ?></strong>
                </div>
                <div class="info-row">
                    <i class="mdi mdi-shield-account"></i>
                    访问权限: 
                    <?php if ($api['visibility'] === 'public' && !$api['is_billable']): ?>
                        <strong>公开访问（无需密钥）</strong>
                    <?php elseif ($api['visibility'] === 'public' && $api['is_billable']): ?>
                        <strong>公开访问（需API密钥）</strong>
                    <?php else: ?>
                        <strong>需API密钥访问</strong>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="api-card">
            <div class="card-header">
                <i class="mdi mdi-link-variant mr-2"></i>请求信息
            </div>
            <div class="card-body">
                <div>
                    <strong>请求地址:</strong>
                    <div class="url-box" id="request-url"><?php echo htmlspecialchars($request_url); ?></div>
                    <button class="copy-btn" onclick="copyText('request-url')">复制请求地址</button>
                </div>
                <div>
                    <strong>示例地址:</strong>
                    <div class="url-box" id="example-url"><?php echo htmlspecialchars($example_url); ?></div>
                    <button class="copy-btn" onclick="copyText('example-url')">复制示例地址</button>
                </div>
            </div>
        </div>
        <div class="api-card">
            <div class="card-header">
                <i class="mdi mdi-format-list-checks mr-2"></i>请求参数
            </div>
            <div class="card-body">
                <table class="param-table">
                    <thead>
                        <tr>
                            <th>参数名</th>
                            <th>类型</th>
                            <th>必填</th>
                            <th>说明</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($params)): ?>
                        <tr>
                            <td colspan="4" style="text-align:center;">此接口无需请求参数</td>
                        </tr>
                        <?php else: foreach($params as $p): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($p['name']); ?></td>
                            <td><?php echo htmlspecialchars($p['type']); ?></td>
                            <td><?php echo $p['required'] === 'yes' ? '是' : '否'; ?></td>
                            <td><?php echo nl2br(htmlspecialchars(str_replace('<br>', "\n", $p['desc']))); ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="api-card">
            <div class="card-header">
                <i class="mdi mdi-code-tags mr-2"></i>状态码说明
            </div>
            <div class="card-body">
                <table class="param-table">
                    <thead>
                        <tr>
                            <th>状态码</th>
                            <th>说明</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>200</td>
                            <td>请求成功，服务器已成功处理了请求。</td>
                        </tr>
                        <tr>
                            <td>403</td>
                            <td>服务器拒绝请求。这可能是由于缺少必要的认证凭据（如API密钥）或权限不足。</td>
                        </tr>
                        <tr>
                            <td>404</td>
                            <td>请求的资源未找到。请检查您的请求地址是否正确。</td>
                        </tr>
                        <tr>
                            <td>429</td>
                            <td>请求过于频繁。您已超出速率限制，请稍后再试。</td>
                        </tr>
                        <tr>
                            <td>500</td>
                            <td>服务器内部错误。服务器在执行请求时遇到了问题。</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="api-card">
            <div class="card-header">
                <i class="mdi mdi-test-tube mr-2"></i>在线测试
            </div>
            <div class="card-body">
                <form id="api-tester-form" data-url="<?php echo htmlspecialchars($request_url); ?>" data-method="<?php echo htmlspecialchars($api['method']); ?>">
                    <?php foreach($params as $p): ?>
                        <?php
                        $isApiKeyParam = (strtolower($p['name']) === 'apikey' || strtolower($p['name']) === 'api_key');
                        if ($isApiKeyParam && $api['visibility'] === 'public' && !$api['is_billable']) {
                            continue;
                        }
                        ?>
                        <div class="test-row">
                            <label for="param-<?php echo htmlspecialchars($p['name']); ?>"><?php echo htmlspecialchars($p['name']); ?></label>
                            <input type="text" id="param-<?php echo htmlspecialchars($p['name']); ?>" name="<?php echo htmlspecialchars($p['name']); ?>"
                                <?php if ($isApiKeyParam && $is_logged_in && isset($_SESSION['user_api_key'])): ?>
                                    value="<?php echo htmlspecialchars($_SESSION['user_api_key']); ?>" placeholder="自动填充您的API密钥"
                                <?php else: ?>
                                    placeholder="<?php echo htmlspecialchars(str_replace('<br>', ' ', $p['desc'])); ?>"
                                <?php endif; ?>
                            >
                        </div>
                    <?php endforeach; ?>
                    <button type="submit" class="btn-test">
                        <i class="mdi mdi-send mr-1"></i>立即测试
                    </button>
                    <div class="response-area" id="response-output">此处将显示接口返回结果...</div>
                </form>
            </div>
        </div>
        <div class="api-card">
            <div class="card-header">
                <i class="mdi mdi-code-braces mr-2"></i>调用示例
            </div>
            <div class="card-body">
                <div class="code-tabs" id="code-tabs">
                    <button class="tab-btn active" data-target="php">PHP</button>
                    <button class="tab-btn" data-target="python">Python</button>
                    <button class="tab-btn" data-target="js">JavaScript</button>
                </div>
                <div id="code-panels">
                    <div class="code-panel active" id="panel-php">
                        <pre><code>&lt;?php
$url = '<?php echo $request_url; ?>';
$params = [<?php foreach($params as $p) echo "'".htmlspecialchars($p['name'])."' => 'YOUR_VALUE', "; ?>];
$url .= '?' . http_build_query($params);
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);
echo $response;
?&gt;</code></pre>
                    </div>
                    <div class="code-panel" id="panel-python">
                        <pre><code>import requests
url = "<?php echo $request_url; ?>"
params = {
<?php foreach($params as $p) echo "    '".htmlspecialchars($p['name'])."': 'YOUR_VALUE',\n"; ?>}
response = requests.get(url, params=params)
print(response.text)</code></pre>
                    </div>
                    <div class="code-panel" id="panel-js">
                        <pre><code>const url = new URL('<?php echo $request_url; ?>');
const params = {
<?php foreach($params as $p) echo "    '".htmlspecialchars($p['name'])."': 'YOUR_VALUE',\n"; ?>};
Object.keys(params).forEach(key => url.searchParams.append(key, params[key]));
fetch(url)
    .then(response => response.text())
    .then(data => console.log(data))
    .catch(error => console.error('Error:', error));</code></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="floating-api-switcher">
        <button class="floating-btn" id="api-switcher-btn">
            <i class="mdi mdi-api"></i>
        </button>
        <div class="api-list-container" id="api-list-container">
            <div class="api-list-header">
                <h5>API 接口列表</h5>
                <button class="close-list-btn">&times;</button>
            </div>
            <div class="api-list" id="api-list"></div>
        </div>
    </div>
    <script src="../../../assets/js/jquery.min.js"></script>
    <script>
    function copyText(elementId) {
        const element = document.getElementById(elementId);
        const text = element.textContent.trim();
        const tempInput = document.createElement('textarea');
        tempInput.value = text;
        document.body.appendChild(tempInput);
        tempInput.select();
        document.execCommand('copy');
        document.body.removeChild(tempInput);
        alert('复制成功！');
    }

    function formatJson(jsonStr) {
        try {
            const obj = JSON.parse(jsonStr);
            return JSON.stringify(obj, null, 2);
        } catch (e) {
            return jsonStr;
        }
    }

    function getStatusLabel(status) {
        switch(status) {
            case 'normal': return '<span class="api-status status-green">正常</span>';
            case 'error': return '<span class="api-status status-red">异常</span>';
            case 'maintenance': return '<span class="api-status status-yellow">维护</span>';
            default: return '<span class="api-status status-gray">未知</span>';
        }
    }
    $(document).ready(function() {
        $('#code-tabs').on('click', '.tab-btn', function() {
            const targetId = $(this).data('target');
            $('#code-tabs .tab-btn').removeClass('active');
            $(this).addClass('active');
            $('#code-panels .code-panel').removeClass('active');
            $('#panel-' + targetId).addClass('active');
        });
        $('#api-tester-form').on('submit', async function(e) {
            e.preventDefault();
            const responseOutput = $('#response-output');
            responseOutput.html('正在请求...');
            const apiUrl = $(this).data('url');
            const apiMethod = $(this).data('method').toUpperCase();
            const formData = $(this).serialize();
            let requestUrl = apiUrl;
            let fetchOptions = { 
                method: apiMethod,
                headers: {}
            };
            <?php if($is_logged_in): ?>
            fetchOptions.headers['Authorization'] = 'Bearer <?php echo $_SESSION['user_api_key'] ?? ''; ?>';
            <?php endif; ?>
            if (apiMethod === 'GET') {
                requestUrl += '?' + formData;
            } else {
                fetchOptions.headers['Content-Type'] = 'application/x-www-form-urlencoded';
                fetchOptions.body = formData;
            }
            try {
                const response = await fetch(requestUrl, fetchOptions);
                const status = response.status;
                const contentType = response.headers.get('Content-Type') || '';
                const isImage = /^image\//.test(contentType);
                const isAudio = /^audio\//.test(contentType);
                const isVideo = /^video\//.test(contentType);
                const isMedia = isImage || isAudio || isVideo;
                let resultHtml = `<div style="color:#16a34a; margin-bottom:8px;">HTTP Status: ${status}</div><hr style="border:0.5px solid #ccc; margin:8px 0;">`;
                if (isMedia) {
                    const blob = await response.blob();
                    const blobUrl = URL.createObjectURL(blob);
                    if (isImage) {
                        resultHtml += `<img src="${blobUrl}" alt="图片返回结果" onclick="window.open('${blobUrl}', '_blank')" title="点击查看原图">`;
                    } else if (isAudio) {
                        resultHtml += `<audio controls src="${blobUrl}">您的浏览器不支持音频播放`;
                    } else if (isVideo) {
                        resultHtml += `<video controls src="${blobUrl}" preload="metadata">您的浏览器不支持视频播放</video>`;
                    }
                    window.addEventListener('unload', () => URL.revokeObjectURL(blobUrl));
                } else {
                    let responseText = await response.text();
                    if (contentType.indexOf('application/json') === -1) {
                        responseText = responseText.replace(/\\n/g, '\n');
                    }
                    const formattedText = formatJson(responseText);
                    resultHtml += `<pre>${formattedText}</pre>`;
                }
                responseOutput.html(resultHtml);
            } catch (error) {
                responseOutput.html(`<div style="color:#dc2626;">请求失败: ${error.message}</div>`);
            }
        });
        async function loadApiList() {
            try {
                const response = await fetch('get_api_list.php');
                const apis = await response.json();
                const apiList = $('#api-list');
                apiList.empty();
                apis.forEach(api => {
                    const isActive = api.id === <?php echo $api_id; ?>;
                    const statusLabel = getStatusLabel(api.status);
                    apiList.append(`
                        <div class="api-item ${isActive ? 'active' : ''}" data-id="${api.id}">
                            <div class="api-info">
                                <div class="api-name">${api.name}</div>
                                <div class="api-endpoint">${api.endpoint}</div>
                            </div>
                            ${statusLabel}
                        </div>
                    `);
                });
                $('.api-item').on('click', function() {
                    const apiId = $(this).data('id');
                    if (apiId !== <?php echo $api_id; ?>) {
                        window.location.href = `?id=${apiId}`;
                    }
                });
            } catch (error) {
                console.error('加载API列表失败:', error);
            }
        }
        $('#api-switcher-btn').on('click', function() {
            $('#api-list-container').toggle();
            if ($('#api-list-container').is(':visible')) {
                loadApiList();
            }
        });
        $('.close-list-btn').on('click', function() {
            $('#api-list-container').hide();
        });
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.floating-api-switcher').length) {
                $('#api-list-container').hide();
            }
        });
    });
    </script>
</body>
</html>
