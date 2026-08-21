<?php
require_once __DIR__ . '/../../../common/session_boot.php';
@error_reporting(0);
@ini_set('display_errors', 'Off');
$rootPath = dirname(__DIR__, 3);
define('ROOT_PATH', $rootPath . '/');
if (!file_exists(ROOT_PATH . 'config.php')) {
    die("系统错误：配置文件丢失。路径: " . ROOT_PATH . 'config.php');
}
require_once ROOT_PATH . 'config.php';
require_once ROOT_PATH . 'common/TemplateManager.php';
require_once ROOT_PATH . 'common/url_helper.php';
require_once ROOT_PATH . 'common/gallery.php';
$template = TemplateManager::getActiveUserTemplate();
$template_base_url = "/template/user/{$template}/";
$is_logged_in = isset($_SESSION['user_id']);
if ($is_logged_in) {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
        $stmt = $pdo->prepare("SELECT api_key FROM huli_users WHERE id = ?");
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
    $columns = $pdo->query("SHOW COLUMNS FROM `huli_apis`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('created_at', $columns)) $pdo->exec("ALTER TABLE `huli_apis` ADD `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `status`;");
    if (!in_array('updated_at', $columns)) $pdo->exec("ALTER TABLE `huli_apis` ADD `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;");
    $stmt_api = $pdo->prepare("SELECT * FROM huli_apis WHERE id = ?");
    $stmt_api->execute([$api_id]);
    $api = $stmt_api->fetch(PDO::FETCH_ASSOC);
    if (!$api) { header('Location: index.php'); exit; }
    $params = json_decode($api['parameters'], true);
    if (!is_array($params)) $params = [];
    $stmt_settings = $pdo->query("SELECT setting_value FROM huli_settings WHERE setting_key = 'site_name'");
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

$base_url = huli_current_origin();
$api_endpoint = trim((string)($api['endpoint'] ?? ''));
if (!preg_match('/^[A-Za-z0-9_]+$/', $api_endpoint)) {
    $api_endpoint = '';
}
$request_url = $base_url . '/API/' . $api_endpoint . '.php';
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
    <link rel="stylesheet" type="text/css" href="../../../assets/css/liquid-glass.css?v=3">
    <style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}
body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    line-height: 1.5;
    background: transparent;
    color: var(--glass-text);
    font-size: 14px;
    min-height: 100vh;
}
.huli-bg {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 0;
    background:
      linear-gradient(rgba(255, 255, 255, 0.35), rgba(255, 255, 255, 0.35)),
      url('<?php echo htmlspecialchars(huli_session_gallery_image()); ?>');
    background-size: cover, cover;
    background-position: center, center;
    background-repeat: no-repeat, no-repeat;
    pointer-events: none;
}
.container-fluid {
    position: relative;
    z-index: 1;
}
.container-fluid {
    padding: 18px 20px !important;
    max-width: 1180px;
}
.page-title {
    font-size: 26px;
    font-weight: 800;
    color: var(--glass-text);
    margin: 6px 0 18px 0;
    position: relative;
    padding-left: 14px;
}
.btn-back-home {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 18px;
    background: linear-gradient(135deg, rgba(108, 177, 245, .22), rgba(92, 197, 211, .22));
    border: 1px solid rgba(180, 220, 245, .55);
    border-radius: 22px;
    color: #1f3a5f;
    font-weight: 600;
    font-size: 14px;
    text-decoration: none;
    backdrop-filter: blur(10px) saturate(180%);
    -webkit-backdrop-filter: blur(10px) saturate(180%);
    box-shadow: 0 4px 14px rgba(45, 100, 155, .08);
    transition: all .25s;
}
.btn-back-home:hover {
    background: linear-gradient(135deg, rgba(108, 177, 245, .35), rgba(92, 197, 211, .35));
    border-color: var(--glass-accent, #5d9fe8);
    color: var(--glass-accent, #5d9fe8);
    transform: translateX(-2px);
    box-shadow: 0 6px 18px rgba(93, 159, 232, .25);
    text-decoration: none;
}
.btn-back-home i {
    font-size: 18px;
}
.page-title::before {
    content: '';
    position: absolute;
    left: 0;
    top: 6px;
    bottom: 6px;
    width: 4px;
    border-radius: 4px;
    background: linear-gradient(180deg, var(--glass-accent), var(--glass-accent-2));
}
.api-card {
    background: linear-gradient(140deg, rgba(255, 255, 255, .22) 0%, rgba(214, 234, 250, .14) 50%, rgba(196, 224, 244, .12) 100%);
    backdrop-filter: blur(6px) saturate(120%);
    -webkit-backdrop-filter: blur(6px) saturate(120%);
    border: 1px solid rgba(255, 255, 255, .35);
    border-radius: 18px;
    margin-bottom: 18px;
    overflow: hidden;
    box-shadow: 0 12px 32px rgba(64, 120, 180, .12);
    transition: transform .3s, box-shadow .3s;
}
.api-card:hover {
    box-shadow: 0 18px 44px rgba(64, 120, 180, .18);
}
.api-card .card-header {
    background: linear-gradient(135deg, rgba(238, 247, 255, .35), rgba(219, 234, 254, .22));
    border-bottom: 1px solid rgba(180, 220, 245, .35);
    padding: 12px 18px;
    font-weight: 700;
    color: var(--glass-text);
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.api-card .card-header i {
    color: var(--glass-accent);
    font-size: 18px;
}
.api-card .card-body {
    padding: 16px 18px;
}
.info-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    font-size: 13px;
    color: var(--glass-muted);
}
.info-row i {
    font-size: 14px;
    color: var(--glass-accent);
}
.info-row strong {
    font-weight: 600;
    color: var(--glass-text);
}
.url-box {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--glass-border);
    border-radius: 10px;
    font-family: "SFMono-Regular", Menlo, Monaco, Consolas, monospace;
    font-size: 12.5px;
    margin: 8px 0;
    background: rgba(245, 250, 255, .7);
    color: var(--glass-text);
    word-break: break-all;
    backdrop-filter: blur(8px);
}
.copy-btn {
    background: linear-gradient(135deg, var(--glass-accent), var(--glass-accent-2));
    color: #fff;
    border: none;
    padding: 7px 14px;
    border-radius: 10px;
    font-size: 12.5px;
    font-weight: 600;
    cursor: pointer;
    margin-bottom: 12px;
    box-shadow: 0 4px 12px rgba(93, 159, 232, .35);
    transition: all .25s;
}
.copy-btn:hover {
    box-shadow: 0 6px 18px rgba(93, 159, 232, .5);
}
.param-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    border-radius: 10px;
    overflow: hidden;
    border: 1px solid var(--glass-border);
}
.param-table th, .param-table td {
    border: none;
    border-bottom: 1px solid rgba(180, 220, 245, .25);
    padding: 10px 12px;
    text-align: left;
}
.param-table th {
    background: linear-gradient(135deg, rgba(238, 247, 255, .85), rgba(219, 234, 254, .65));
    font-weight: 700;
    color: var(--glass-text);
    font-size: 12.5px;
}
.param-table tbody tr:hover {
    background: rgba(238, 247, 255, .5);
}
.param-table tbody tr:last-child td { border-bottom: none; }
.test-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
    font-size: 13px;
}
.test-row label {
    min-width: 80px;
    font-weight: 600;
    color: var(--glass-text);
}
.test-row input {
    flex: 1;
    padding: 8px 12px;
    border: 1px solid var(--glass-border);
    border-radius: 10px;
    font-size: 13px;
    background: rgba(255, 255, 255, .65);
    transition: all .2s;
}
.test-row input:focus {
    outline: none;
    border-color: var(--glass-accent);
    box-shadow: 0 0 0 3px rgba(93, 159, 232, .18);
    background: rgba(255, 255, 255, .9);
}
.btn-test {
    background: linear-gradient(135deg, var(--glass-accent), var(--glass-accent-2));
    color: #fff;
    border: none;
    padding: 8px 18px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(93, 159, 232, .35);
    transition: all .25s;
}
.btn-test:hover {
    box-shadow: 0 6px 18px rgba(93, 159, 232, .5);
}
.response-area {
    width: 100%;
    height: 220px;
    padding: 10px 12px;
    border: 1px solid var(--glass-border);
    border-radius: 10px;
    font-family: "SFMono-Regular", Menlo, Monaco, Consolas, monospace;
    font-size: 12.5px;
    background: rgba(245, 250, 255, .7);
    color: var(--glass-text);
    overflow-y: auto;
    margin-top: 12px;
    white-space: pre-wrap;
    backdrop-filter: blur(8px);
}
.response-area img, .response-area audio, .response-area video {
  max-width: 100%;
  max-height: 200px;
  display: block;
  margin: 8px 0;
  border-radius: 8px;
  cursor: pointer;
}
.response-area pre {
  margin: 0;
  white-space: pre-wrap;
  word-wrap: break-word;
}
.code-tabs {
    display: flex;
    gap: 6px;
    margin-bottom: 10px;
    overflow-x: auto;
    flex-wrap: nowrap;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
    padding-bottom: 2px;
}
.tab-btn {
    padding: 6px 14px;
    background: rgba(255, 255, 255, .55);
    border: 1px solid var(--glass-border);
    border-radius: 10px 10px 0 0;
    font-size: 12.5px;
    font-weight: 600;
    color: var(--glass-muted);
    cursor: pointer;
    border-bottom: none;
    transition: all .2s;
    white-space: nowrap;
    flex: 0 0 auto;
}
.tab-btn:hover {
    background: rgba(238, 247, 255, .85);
    color: var(--glass-text);
}
.tab-btn.active {
    background: linear-gradient(135deg, var(--glass-accent), var(--glass-accent-2));
    color: #fff;
    border-color: transparent;
    box-shadow: 0 4px 12px rgba(93, 159, 232, .35);
}
.code-panel {
    display: none;
}
.code-panel.active {
    display: block;
}
.code-panel pre {
    margin: 0;
    padding: 14px;
    border: 1px solid var(--glass-border);
    border-radius: 0 10px 10px 10px;
    font-family: "SFMono-Regular", Menlo, Monaco, Consolas, monospace;
    font-size: 12.5px;
    background: rgba(245, 250, 255, .75);
    color: var(--glass-text);
    overflow-x: auto;
    backdrop-filter: blur(8px);
}
.status-badge {
    padding: 3px 8px;
    border-radius: 10px;
    font-size: 11.5px;
    font-weight: 600;
}
.status-green {
    background: rgba(220, 252, 231, .85);
    color: #16a34a;
}
.status-red {
    background: rgba(254, 226, 226, .85);
    color: #dc2626;
}
.status-yellow {
    background: rgba(255, 251, 235, .85);
    color: #ca8a04;
}
.status-gray {
    background: rgba(243, 244, 246, .85);
    color: #6b7280;
}
.api-list {
    padding: 8px 0;
    max-height: 360px;
    overflow-y: auto;
}
.api-item {
    padding: 11px 18px;
    cursor: pointer;
    transition: background-color .2s ease;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-left: 3px solid transparent;
}
.api-item:hover {
    background-color: rgba(238, 247, 255, .6);
}
.api-item.active {
    background: linear-gradient(90deg, rgba(93, 159, 232, .14), transparent);
    border-left-color: var(--glass-accent);
}
.api-item .api-info {
    flex: 1;
}
.api-item .api-name {
    font-size: 13px;
    color: var(--glass-text);
    font-weight: 600;
    margin-bottom: 2px;
}
.api-item .api-endpoint {
    font-size: 11.5px;
    color: var(--glass-muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.api-item .api-status {
    font-size: 10.5px;
    padding: 3px 8px;
    border-radius: 10px;
    font-weight: 600;
}
.api-list::-webkit-scrollbar {
    width: 6px;
}
.api-list::-webkit-scrollbar-track {
    background: transparent;
}
.api-list::-webkit-scrollbar-thumb {
    background: rgba(93, 159, 232, .35);
    border-radius: 6px;
}
.api-list::-webkit-scrollbar-thumb:hover {
    background: rgba(93, 159, 232, .55);
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
    overflow-x: auto;
    flex-wrap: nowrap;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
    padding-bottom: 2px;
}
.tab-btn {
    padding: 4px 10px;
    background-color: #f3f4f6;
    border: 1px solid #e5e7eb;
    border-radius: 4px 4px 0 0;
    font-size: 12px;
    cursor: pointer;
    border-bottom: none;
    white-space: nowrap;
    flex: 0 0 auto;
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
/* ===== 移动端适配 ===== */
@media (max-width: 768px) {
    .container-fluid {
        padding: 12px 12px !important;
    }
    .page-title {
        font-size: 20px;
    }
    .test-row {
        flex-wrap: wrap;
    }
    .test-row label {
        min-width: 100%;
        margin-bottom: 4px;
    }
    /* 参数表 & 状态码表卡片化 */
    .param-table {
        border: none;
    }
    .param-table thead {
        display: none;
    }
    .param-table tbody,
    .param-table tr,
    .param-table td {
        display: block;
        width: 100%;
    }
    .param-table tr {
        background:
            linear-gradient(140deg, rgba(255, 255, 255, .20) 0%, rgba(214, 234, 250, .14) 50%, rgba(196, 224, 244, .12) 100%);
        border: 1px solid rgba(180, 220, 245, .35);
        border-radius: 12px;
        padding: 4px 10px;
        margin-bottom: 10px;
    }
    .param-table td {
        border: none;
        padding: 6px 4px;
        text-align: left;
    }
    .param-table td[data-label]::before {
        content: attr(data-label);
        display: inline-block;
        width: 54px;
        margin-right: 8px;
        font-weight: 700;
        color: var(--glass-accent, #5d9fe8);
        font-size: 12px;
        vertical-align: top;
    }
    .param-table td[data-label="说明"]::before {
        width: 54px;
    }
    .param-table tr:has(td[colspan]) {
        padding: 10px;
    }
    .code-tabs .tab-btn {
        padding: 8px 14px;
        font-size: 13px;
    }
}
</style>
</head>
 <body>
    <div class="huli-bg" aria-hidden="true"></div>
    <div class="container-fluid">
        <div class="d-flex align-items-center mb-3 gap-2">
            <a href="<?= $homeTemplateBaseUrl ?>main1.php" class="btn-back-home">
                <i class="mdi mdi-arrow-left"></i>
                <span>返回 API 大厅</span>
            </a>
        </div>
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
                            <td data-label="参数名"><?php echo htmlspecialchars($p['name']); ?></td>
                            <td data-label="类型"><?php echo htmlspecialchars($p['type']); ?></td>
                            <td data-label="必填"><?php echo $p['required'] === 'yes' ? '是' : '否'; ?></td>
                            <td data-label="说明"><?php echo nl2br(htmlspecialchars(str_replace('<br>', "\n", $p['desc']))); ?></td>
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
                            <td data-label="状态码">200</td>
                            <td data-label="说明">请求成功，服务器已成功处理了请求。</td>
                        </tr>
                        <tr>
                            <td data-label="状态码">201</td>
                            <td data-label="说明">已创建。请求成功并且服务器已创建了新的资源。</td>
                        </tr>
                        <tr>
                            <td data-label="状态码">204</td>
                            <td data-label="说明">无内容。服务器成功处理了请求，但不需要返回任何实体内容。</td>
                        </tr>
                        <tr>
                            <td data-label="状态码">400</td>
                            <td data-label="说明">错误请求。服务器无法理解请求的格式，请检查请求参数是否正确。</td>
                        </tr>
                        <tr>
                            <td data-label="状态码">401</td>
                            <td data-label="说明">未授权。请求需要身份验证，请提供有效的身份凭据（如登录态、Token）。</td>
                        </tr>
                        <tr>
                            <td data-label="状态码">403</td>
                            <td data-label="说明">服务器拒绝请求。这可能是由于缺少必要的认证凭据（如API密钥）或权限不足。</td>
                        </tr>
                        <tr>
                            <td data-label="状态码">404</td>
                            <td data-label="说明">请求的资源未找到。请检查您的请求地址是否正确。</td>
                        </tr>
                        <tr>
                            <td data-label="状态码">405</td>
                            <td data-label="说明">方法不允许。请求使用的 HTTP 方法不被该接口支持，请检查请求方式（GET/POST）。</td>
                        </tr>
                        <tr>
                            <td data-label="状态码">408</td>
                            <td data-label="说明">请求超时。服务器等待请求时发生超时，请稍后重试。</td>
                        </tr>
                        <tr>
                            <td data-label="状态码">429</td>
                            <td data-label="说明">请求过于频繁。您已超出速率限制，请稍后再试。</td>
                        </tr>
                        <tr>
                            <td data-label="状态码">500</td>
                            <td data-label="说明">服务器内部错误。服务器在执行请求时遇到了问题。</td>
                        </tr>
                        <tr>
                            <td data-label="状态码">502</td>
                            <td data-label="说明">网关错误。作为网关或代理的服务器从上游服务器收到了无效响应。</td>
                        </tr>
                        <tr>
                            <td data-label="状态码">503</td>
                            <td data-label="说明">服务不可用。服务器暂时无法处理请求（可能正在维护或过载），请稍后重试。</td>
                        </tr>
                        <tr>
                            <td data-label="状态码">504</td>
                            <td data-label="说明">网关超时。作为网关或代理的服务器未能及时从上游服务器收到请求。</td>
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
                    <div class="d-flex flex-wrap gap-2 align-items-center mt-2">
                        <button type="submit" class="btn-test">
                            <i class="mdi mdi-send mr-1"></i>立即测试
                        </button>
                        <button type="button" id="auto-build-url-btn" class="btn-test" style="background:linear-gradient(135deg,#10b981,#34d399);">
                            <i class="mdi mdi-link-variant mr-1"></i>自动获取链接
                        </button>
                        <button type="button" id="copy-test-url-btn" class="btn-test" style="background:linear-gradient(135deg,#6366f1,#818cf8);display:none;">
                            <i class="mdi mdi-content-copy mr-1"></i>复制测试链接
                        </button>
                    </div>
                    <div id="auto-url-box" class="url-box" style="display:none;margin-top:12px;"></div>
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
                    <button class="tab-btn" data-target="node">Node.js</button>
                    <button class="tab-btn" data-target="curl">cURL</button>
                    <button class="tab-btn" data-target="java">Java</button>
                    <button class="tab-btn" data-target="go">Go</button>
                </div>
                <div id="code-panels"><?php
$param_list = [];
foreach ($params as $p) {
    $pname = htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8');
    $param_list[] = "'" . $pname . "' => 'YOUR_VALUE'";
}
$param_inline = implode(', ', $param_list);
$param_python = '';
foreach ($params as $p) {
    $pname = htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8');
    $param_python .= "    '" . $pname . "': 'YOUR_VALUE',\n";
}
$param_node = '';
foreach ($params as $p) {
    $pname = htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8');
    $param_node .= "\n    '" . $pname . "': 'YOUR_VALUE',";
}
$param_curl = '';
foreach ($params as $p) {
    $pname = htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8');
    $param_curl .= "  --data-urlencode \"" . $pname . "=YOUR_VALUE\" \\\n";
}
$param_java_set = '';
foreach ($params as $i => $p) {
    $pname = htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8');
    $param_java_set .= "        urlBuilder.append(\"" . $pname . "=YOUR_VALUE&\");\n";
}
$param_go_set = '';
foreach ($params as $p) {
    $pname = htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8');
    $param_go_set .= "    q.Set(\"" . $pname . "\", \"YOUR_VALUE\")\n";
}
$request_url_esc = htmlspecialchars($request_url, ENT_QUOTES, 'UTF-8');

echo '<div class="code-panel active" id="panel-php"><pre><code><?php
// 使用 cURL 调用 API
$url = \'' . $request_url_esc . '\';
$params = [' . $param_inline . '];
$url .= \'?\' . http_build_query($params);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
$response = curl_exec($ch);

if (curl_errno($ch)) {
    die(\'请求失败: \' . curl_error($ch));
}
curl_close($ch);

// 解析 JSON 响应
$data = json_decode($response, true);
echo "code: " . ($data[\'code\'] ?? \'N/A\') . "\n";
echo "msg:  " . ($data[\'msg\'] ?? $response);
?></code></pre></div>';

echo '<div class="code-panel" id="panel-python"><pre><code>import requests

# 调用 API
url = "' . $request_url_esc . '"
params = {
' . $param_python . '}

try:
    response = requests.get(url, params=params, timeout=10)
    response.raise_for_status()
    data = response.json()
    print(f"code: {data.get(\'code\')}")
    print(f"msg:  {data.get(\'msg\')}")
except requests.exceptions.RequestException as e:
    print(f"请求失败: {e}")</code></pre></div>';

echo '<div class="code-panel" id="panel-js"><pre><code>// 使用原生 fetch（浏览器/现代 Node.js）
const url = new URL(\'' . $request_url_esc . '\');
const params = {
' . $param_python . '};

Object.keys(params).forEach(key => url.searchParams.append(key, params[key]));

fetch(url, { method: \'GET\' })
    .then(async response => {
        const data = await response.json();
        console.log(\'code:\', data.code);
        console.log(\'msg: \', data.msg);
    })
    .catch(error => console.error(\'请求失败:\', error));</code></pre></div>';

echo '<div class="code-panel" id="panel-node"><pre><code>// Node.js (需要先安装: npm install axios)
const axios = require(\'axios\');

const url = \'' . $request_url_esc . '\';
const params = {' . $param_node . '};

axios.get(url, { params, timeout: 10000 })
    .then(response => {
        console.log(\'code:\', response.data.code);
        console.log(\'msg: \', response.data.msg);
    })
    .catch(error => {
        if (error.response) {
            console.error(\'服务器返回错误:\', error.response.status);
        } else {
            console.error(\'请求失败:\', error.message);
        }
    });</code></pre></div>';

echo '<div class="code-panel" id="panel-curl"><pre><code># 使用 cURL 命令行调用 API
curl -X GET "' . $request_url_esc . '" \
' . $param_curl . '  --connect-timeout 10 \
  --max-time 30 \
  -H "Accept: application/json"

# 带 API Key 鉴权（如需）：
# curl -X GET "' . $request_url_esc . '?apikey=YOUR_API_KEY"</code></pre></div>';

echo '<div class="code-panel" id="panel-java"><pre><code>import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;

public class ApiClient {
    public static void main(String[] args) throws Exception {
        StringBuilder urlBuilder = new StringBuilder("' . $request_url_esc . '?");
' . $param_java_set . '        String url = urlBuilder.toString();

        HttpURLConnection conn = (HttpURLConnection) new URL(url).openConnection();
        conn.setRequestMethod("GET");
        conn.setConnectTimeout(5000);
        conn.setReadTimeout(10000);
        conn.setRequestProperty("Accept", "application/json");

        try (BufferedReader br = new BufferedReader(
                new InputStreamReader(conn.getInputStream(), StandardCharsets.UTF_8))) {
            StringBuilder response = new StringBuilder();
            String line;
            while ((line = br.readLine()) != null) response.append(line);
            System.out.println(response);
        } finally {
            conn.disconnect();
        }
    }
}</code></pre></div>';

echo '<div class="code-panel" id="panel-go"><pre><code>package main

import (
    "fmt"
    "io"
    "net/http"
    "net/url"
    "time"
)

func main() {
    // 构造 URL 与参数
    base, _ := url.Parse("' . $request_url_esc . '")
    q := base.Query()
' . $param_go_set . '    base.RawQuery = q.Encode()

    // 发送请求
    client := &http.Client{Timeout: 10 * time.Second}
    resp, err := client.Get(base.String())
    if err != nil {
        panic(err)
    }
    defer resp.Body.Close()

    body, _ := io.ReadAll(resp.Body)
    fmt.Println(string(body))
}</code></pre></div>';
?>
                </div>
            </div>
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

    function renderResponse(raw) {
        const el = document.getElementById('response-output');
        if (!raw) { el.textContent = '(空响应)'; return; }
        const ct = (raw.headers && raw.headers.get('content-type')) || '';
        if (ct.indexOf('application/json') !== -1) {
            try {
                const obj = JSON.parse(raw.body);
                el.textContent = JSON.stringify(obj, null, 2);
                return;
            } catch (e) { /* fall through */ }
        }
        const trimmed = (raw.body || '').trim();
        if ((trimmed.startsWith('{') && trimmed.endsWith('}')) || (trimmed.startsWith('[') && trimmed.endsWith(']'))) {
            try {
                const obj = JSON.parse(trimmed);
                el.textContent = JSON.stringify(obj, null, 2);
                return;
            } catch (e) { /* fall through */ }
        }
        el.textContent = raw.body;
    }

    document.addEventListener('DOMContentLoaded', function() {
        const tabBtns = document.querySelectorAll('.tab-btn');
        const codePanels = document.querySelectorAll('.code-panel');
        tabBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                const target = btn.dataset.target;
                tabBtns.forEach(function(b) { b.classList.remove('active'); });
                codePanels.forEach(function(p) { p.classList.remove('active'); });
                btn.classList.add('active');
                const panel = document.getElementById('panel-' + target);
                if (panel) { panel.classList.add('active'); }
            });
        });

        const form = document.getElementById('api-tester-form');
        const baseUrl = form.dataset.url;
        const method = (form.dataset.method || 'GET').toUpperCase();
        const output = document.getElementById('response-output');

        function buildUrl() {
            const inputs = form.querySelectorAll('input[name]');
            const params = [];
            inputs.forEach(function(inp) {
                const v = inp.value.trim();
                if (v !== '') { params.push([inp.name, v]); }
            });
            if (params.length === 0) { return baseUrl; }
            const qs = params.map(function(p) { return encodeURIComponent(p[0]) + '=' + encodeURIComponent(p[1]); }).join('&');
            return baseUrl + (baseUrl.indexOf('?') === -1 ? '?' : '&') + qs;
        }

        form.addEventListener('submit', async function(ev) {
            ev.preventDefault();
            const testUrl = buildUrl();
            output.textContent = '请求中: ' + method + ' ' + testUrl;
            try {
                const resp = await fetch(testUrl, { method: method, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const body = await resp.text();
                renderResponse({ code: resp.status, headers: resp.headers, body: body });
            } catch (err) {
                output.textContent = '请求失败：' + err.message;
            }
        });

        const autoBtn = document.getElementById('auto-build-url-btn');
        const urlBox = document.getElementById('auto-url-box');
        const copyBtn = document.getElementById('copy-test-url-btn');
        if (autoBtn) {
            autoBtn.addEventListener('click', function() {
                const url = buildUrl();
                urlBox.textContent = url;
                urlBox.style.display = 'block';
                copyBtn.style.display = 'inline-flex';
                copyBtn.dataset.url = url;
            });
        }
        if (copyBtn) {
            copyBtn.addEventListener('click', function() {
                const url = copyBtn.dataset.url || '';
                if (!url) return;
                const tmp = document.createElement('textarea');
                tmp.value = url;
                document.body.appendChild(tmp);
                tmp.select();
                try { document.execCommand('copy'); alert('测试链接已复制到剪贴板'); }
                catch (e) { alert('复制失败，请手动复制'); }
                document.body.removeChild(tmp);
            });
        }
    });
    </script>
</body>
</html>
