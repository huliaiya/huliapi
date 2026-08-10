<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: index.php');
    exit;
}
$rootPath = dirname(__DIR__, 3); 
define('ROOT_PATH', $rootPath . '/');
if (!file_exists(ROOT_PATH . 'config.php')) { 
    die("系统错误：配置文件丢失。路径: " . ROOT_PATH . 'config.php'); 
}
if (!file_exists(ROOT_PATH . 'config.php')) { die("出现错误！系统尚未安装，请先访问 /install.php 完成安装。"); }
require_once 'config.php';
require_once 'common/TemplateManager.php';
$apis = []; $announcement = null; $settings = [];
$is_logged_in = isset($_SESSION['user_id']);
$user_info = $is_logged_in ? ['username' => $_SESSION['user_username'], 'email' => $_SESSION['user_email']] : null;
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt_apis = $pdo->query("SELECT * FROM sl_apis ORDER BY id DESC");
    $apis = $stmt_apis->fetchAll(PDO::FETCH_ASSOC);
    $stmt_announcement = $pdo->query("SELECT * FROM sl_announcements WHERE is_active = 1 ORDER BY created_at DESC LIMIT 1");
    $announcement = $stmt_announcement->fetch(PDO::FETCH_ASSOC);
    $stmt_settings = $pdo->query("SELECT setting_key, setting_value FROM sl_settings");
    $settings = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) { }
    $site_name = $settings['site_name'] ?? 'huliapi';
    $site_description = $settings['site_description'] ?? 'huliapi致力于为用户提供稳定、高效的API接口服务，包含随机一言、工具类API等多种接口';
    $copyright_info = $settings['copyright_info'] ?? 'Copyright © 2025-2026 huliapi 版权所有';
$allow_temp_key = $settings['allow_temp_key'] ?? 1;

function getStatusBadge($status) {
    switch ($status) {
        case 'normal': return '<span class="status-badge status-green">正常</span>';
        case 'error': return '<span class="status-badge status-red">异常</span>';
        case 'maintenance': return '<span class="status-badge status-yellow">维护</span>';
        default: return '<span class="status-badge status-gray">未知</span>';
    }
}

function getVisibilityBadge($visibility, $is_billable) {
    if ($is_billable) return '<div class="meta-item"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path d="M2.5 4A1.5 1.5 0 001 5.5v1A1.5 1.5 0 002.5 8H3v8.5A1.5 1.5 0 004.5 18h11a1.5 1.5 0 001.5-1.5V8h.5A1.5 1.5 0 0019 6.5v-1A1.5 1.5 0 0017.5 4h-15zM11 12a1 1 0 10-2 0 1 1 0 002 0z" /></svg>计费调用</div>';
    if ($visibility === 'private') return '<div class="meta-item"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd" /></svg>密钥调用</div>';
    return '';
}

function getCallCountStyle($count) {
    $count = intval($count);
    $style = ['color' => 'var(--text-light)', 'flame_level' => 0, 'animation_duration' => '0s', 'border_color' => 'var(--border-color)'];
    if ($count > 500000) { $style = array_merge($style, ['color' => '#991b1b', 'flame_level' => 3, 'animation_duration' => '0.8s', 'border_color' => '#dc2626']); }
    elseif ($count > 100000) { $style = array_merge($style, ['color' => '#b91c1c', 'flame_level' => 3, 'animation_duration' => '1s', 'border_color' => '#ef4444']); }
    elseif ($count > 50000) { $style = array_merge($style, ['color' => '#dc2626', 'flame_level' => 2, 'animation_duration' => '1.2s', 'border_color' => '#f87171']); }
    elseif ($count > 10000) { $style = array_merge($style, ['color' => '#ef4444', 'flame_level' => 1, 'animation_duration' => '1.5s', 'border_color' => '#fca5a5']); }
    elseif ($count > 1000) { $style = array_merge($style, ['color' => '#f87171', 'border_color' => '#fee2e2']); }
    return $style;
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($site_name); ?> - <?php echo htmlspecialchars($site_description); ?></title>
    <link rel="shortcut icon" type="image/x-icon" href="https://picui.ogmua.cn/s1/2026/05/26/6a156ea77f458.webp">
    <style>
        :root {
            --bg-color: #f7f8fc; --sidebar-bg: #ffffff; --card-bg: #ffffff;
            --primary-color: #4a69bd; --primary-hover: #3b528f; --primary-light: #eef2ff;
            --text-dark: #1f2937; --text-normal: #4b5563; --text-light: #9ca3af;
            --border-color: #e5e7eb; --shadow-color: rgba(149, 157, 165, 0.1);
            --font-main: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes flame-flicker { 0%, 100% { transform: scaleY(1) rotate(-2deg); } 50% { transform: scaleY(1.05) rotate(2deg); } }
        html { -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }
        body { font-family: var(--font-main); background-color: var(--bg-color); color: var(--text-normal); line-height: 1.6; }
        #page-container { display: flex; min-height: 100vh; }
        #sidebar { width: 280px; background-color: var(--sidebar-bg); border-right: 1px solid var(--border-color); display: flex; flex-direction: column; position: fixed; top: 0; left: 0; height: 100%; z-index: 100; transform: translateX(-100%); transition: transform 0.3s ease; }
        .sidebar-header { padding: 24px; border-bottom: 1px solid var(--border-color); }
        .sidebar-logo { font-size: 24px; font-weight: 700; color: var(--text-dark); text-decoration: none; }
        .user-info-panel { padding: 24px; text-align: center; }
        .user-info-panel .avatar { width: 80px; height: 80px; border-radius: 50%; background-color: var(--primary-light); color: var(--primary-color); display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: 600; margin: 0 auto 16px; }
        .user-info-panel .username { font-size: 18px; font-weight: 600; color: var(--text-dark); }
        .user-info-panel .email { font-size: 14px; color: var(--text-light); margin-top: 4px; }
        .sidebar-auth-actions { padding: 0 24px; display: flex; gap: 12px; margin-top: 20px; }
        .sidebar-auth-actions a { flex: 1; text-align:center; text-decoration: none; color: var(--text-normal); font-weight: 500; padding: 10px; border-radius: 8px; transition: all 0.2s; }
        .sidebar-auth-actions .btn-login { background-color: var(--primary-light); color: var(--primary-color); }
        .sidebar-auth-actions .btn-register { background-color: var(--primary-color); color: #fff; }
        .sidebar-nav { padding: 16px 24px; flex-grow: 1; }
        .nav-link { display: flex; align-items: center; padding: 12px; border-radius: 8px; text-decoration: none; color: var(--text-normal); font-weight: 500; margin-bottom: 8px; transition: all 0.2s; }
        .nav-link.active, .nav-link:hover { background-color: var(--primary-light); color: var(--primary-color); }
        .nav-link svg { margin-right: 12px; flex-shrink: 0; }
        .sidebar-footer { padding: 24px; border-top: 1px solid var(--border-color); }
        .btn-logout { display: block; width: 100%; text-align: center; padding: 12px; border-radius: 8px; background-color: #fee2e2; color: #b91c1c; font-weight: 600; text-decoration: none; transition: all 0.2s; }
        #main-content { flex-grow: 1; margin-left: 0; display: flex; flex-direction: column; width: 100%; }
        .top-header { padding: 48px 32px; text-align: center; background-color: var(--card-bg); border-bottom: 1px solid var(--border-color); }
        .top-header-content { max-width: 700px; margin: 0 auto; }
        .top-header-content h1 { font-size: 42px; font-weight: 800; color: var(--text-dark); margin: 0; line-height: 1.2; }
        .top-header-content p { font-size: 18px; color: var(--text-light); margin-top: 16px; }
        .search-container { margin-top: 32px; position: relative; }
        .search-container input { width: 100%; height: 50px; border-radius: 12px; border: 1px solid var(--border-color); background-color: var(--bg-color); padding-left: 50px; padding-right: 20px; font-size: 16px; }
        .search-container input:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px var(--primary-light); }
        .search-container svg { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-light); }
        .main-header { display: flex; justify-content: space-between; align-items: center; padding: 16px 32px; background-color: var(--card-bg); border-bottom: 1px solid var(--border-color); position: sticky; top: 0; z-index: 50; }
        #mobile-menu-btn { background: none; border: none; cursor: pointer; padding: 8px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 16px; }
        #mobile-menu-btn:hover { background-color: var(--border-color); }
        .user-actions { display: flex; align-items: center; }
        .user-actions a { text-decoration: none; color: var(--text-normal); font-weight: 500; padding: 8px 16px; border-radius: 8px; transition: all 0.2s; }
        .user-actions a.btn-login { background-color: var(--primary-light); color: var(--primary-color); }
        .user-actions a.btn-register { background-color: var(--primary-color); color: #fff; margin-left: 8px; }
        .user-profile { display: flex; align-items: center; position: relative; cursor: pointer; }
        .user-profile .avatar { width: 40px; height: 40px; border-radius: 50%; background-color: var(--primary-light); color: var(--primary-color); display: flex; align-items: center; justify-content: center; font-weight: 600; margin-right: 12px; }
        .user-profile .username { font-weight: 600; }
        .user-profile .dropdown-menu { display: none; position: absolute; top: calc(100% + 10px); right: 0; background-color: var(--card-bg); border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); border: 1px solid var(--border-color); width: 180px; z-index: 200; padding: 8px; }
        .user-profile:hover .dropdown-menu { display: block; }
        .dropdown-item { display: block; padding: 10px 12px; color: var(--text-normal); text-decoration: none; border-radius: 6px; font-weight: 500; }
        .dropdown-item:hover { background-color: var(--bg-color); }
        .content-wrapper { padding: 32px; }
        .announcement-bar { background-color: var(--primary-light); color: var(--primary-color); padding: 16px; border-radius: 12px; margin-bottom: 32px; display: flex; align-items: center; animation: fadeIn 0.5s ease; }
        .announcement-bar svg { flex-shrink: 0; margin-right: 12px; }
        .api-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 24px; }
        .api-card { background-color: var(--card-bg); border-radius: 16px; border: 2px solid var(--border-color); box-shadow: 0 4px 12px var(--shadow-color); padding: 24px; display: flex; flex-direction: column; transition: all 0.3s ease; animation: fadeIn 0.5s ease forwards; }
        .api-card:hover { transform: translateY(-5px); box-shadow: 0 8px 20px var(--shadow-color); }
        .card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
        .api-name { font-size: 20px; font-weight: 700; color: #111827; }
        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 99px; font-size: 12px; font-weight: 600; }
        .status-green { background-color: #dcfce7; color: #166534; } .status-red { background-color: #fee2e2; color: #991b1b; } .status-yellow { background-color: #fef9c3; color: #854d0e; } .status-gray { background-color: #f3f4f6; color: #374151; }
        .api-description { color: var(--text-normal); margin-bottom: 16px; flex-grow: 1; }
        .card-footer { border-top: 1px solid var(--border-color); padding-top: 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .api-meta { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
        .meta-item { display: flex; align-items: center; color: var(--text-light); font-size: 14px; font-weight: 500; }
        .meta-item svg { margin-right: 6px; }
        .meta-item .call-count { font-weight: 700; display: flex; align-items: center; }
        .flame-icon { width: 16px; height: 16px; margin-left: 4px; animation: flame-flicker infinite; }
        .btn-details { background-color: var(--primary-color); color: #fff; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; transition: background-color 0.2s; white-space: nowrap; }
        .btn-details:hover { background-color: var(--primary-hover); }
        .page-footer { text-align: center; padding: 24px; font-size: 14px; color: var(--text-light); }
        #sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 99; }
        @media (min-width: 1025px) { #sidebar { transform: translateX(0); } #main-content { margin-left: 280px; } #mobile-menu-btn { display: none; } }
        @media (max-width: 1024px) { body.sidebar-open #sidebar { transform: translateX(0); } body.sidebar-open #sidebar-overlay { display: block; } }
        @media (max-width: 768px) { .api-grid { grid-template-columns: 1fr; } .top-header { padding: 32px 16px; } .top-header-content h1 { font-size: 32px; } .content-wrapper { padding: 16px; } .announcement-bar { margin-bottom: 16px; } }
        <?php for($i=0; $i<12000; $i++){ echo ".dynamic-index-final-fix-{$i}{float:left;}\n"; } ?>
    </style>
</head>
<body>
    <div id="page-container">
        <aside id="sidebar">
            <div class="sidebar-header"><a href="../../../" class="sidebar-logo"><?php echo htmlspecialchars($site_name); ?></a></div>
            <div class="user-info-panel">
                <?php if ($is_logged_in): ?>
                    <div class="avatar"><?php echo strtoupper(substr($user_info['username'], 0, 1)); ?></div><div class="username"><?php echo htmlspecialchars($user_info['username']); ?></div><div class="email"><?php echo htmlspecialchars($user_info['email']); ?></div>
                <?php else: ?>
                    <div class="avatar">?</div><div class="username">游客, 您好！</div><div class="sidebar-auth-actions"><a href="<?= TemplateManager::getUserTemplateUrl() ?>login.php" class="btn-login">登录</a><a href="<?= TemplateManager::getUserTemplateUrl() ?>register.php" class="btn-register">注册</a></div>
                <?php endif; ?>
            </div>
            <nav class="sidebar-nav">
                <a href="/" class="nav-link active"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path d="M3.75 3A1.75 1.75 0 002 4.75v10.5c0 .966.784 1.75 1.75 1.75h12.5A1.75 1.75 0 0018 15.25V4.75A1.75 1.75 0 0016.25 3H3.75zM9.5 4.5a.75.75 0 00-1.5 0v11a.75.75 0 001.5 0v-11z" /></svg>接口大厅</a>
                <a href="/user/" class="nav-link"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-5.5-2.5a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0zM10 12a5.99 5.99 0 00-4.793 2.39A6.483 6.483 0 0010 16.5a6.483 6.483 0 004.793-2.11A5.99 5.99 0 0010 12z" clip-rule="evenodd" /></svg>用户中心</a>
                <a href="<?= TemplateManager::getUserTemplateUrl() ?>feedback.php" class="nav-link"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 2c-1.717 0-3.28.534-4.522 1.425C3.899 4.347 3 5.91 3 7.5c0 1.27.666 2.454 1.734 3.162a.75.75 0 01.266.588v3.5a.75.75 0 00.75.75h8.5a.75.75 0 00.75-.75v-3.5a.75.75 0 01.266-.588C16.334 9.954 17 8.77 17 7.5c0-1.59-.899-3.153-2.478-4.075C13.28 2.534 11.717 2 10 2zM4.5 7.5c0-1.06.688-2.184 1.88-2.94A5.498 5.498 0 0110 3.5c1.45 0 2.763.54 3.62 1.06A3.48 3.48 0 0115.5 7.5c0 .84-.42 1.68-1.12 2.24a2.253 2.253 0 00-1.38.527.75.75 0 01-.266.588v2.895h-5.5V10.85a.75.75 0 01-.266-.588 2.253 2.253 0 00-1.38-.527C4.92 9.18 4.5 8.34 4.5 7.5z" clip-rule="evenodd" /></svg>意见反馈</a>
                <?php if ($allow_temp_key): ?><a href="<?= TemplateManager::getUserTemplateUrl() ?>temp_key.php" class="nav-link"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path d="M11.983 2.2a.75.75 0 01.447.691l-.255 2.896a.75.75 0 01-1.48-.132l.255-2.896a.75.75 0 011.033-.559zM15.42 5.06a.75.75 0 01.132 1.48l-2.896.255a.75.75 0 01-.56-.933l2.896-.255a.75.75 0 01.428-.547zM4.58 5.06a.75.75 0 01.547.428l.255 2.896a.75.75 0 01-1.48.132l-.255-2.896a.75.75 0 01.933-.56zM2.2 11.983a.75.75 0 01.691-.447l2.896.255a.75.75 0 01.132 1.48l-2.896-.255a.75.75 0 01-.559-1.033zM10 4a6 6 0 100 12 6 6 0 000-12zM3.5 10a6.5 6.5 0 1113 0 6.5 6.5 0 01-13 0z" /></svg>申请临时密钥</a><?php endif; ?>
            </nav>
            <?php if ($is_logged_in): ?><div class="sidebar-footer"><a href="?action=logout" class="btn-logout">安全退出</a></div><?php endif; ?>
        </aside>
        <div id="sidebar-overlay"></div>
        <div id="main-content">
            <header class="main-header">
                <button id="mobile-menu-btn"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg></button>
                <div class="user-actions">
                    <?php if ($is_logged_in): ?>
                    <div class="user-profile"><div class="avatar"><?php echo strtoupper(substr($user_info['username'], 0, 1)); ?></div><span class="username"><?php echo htmlspecialchars($user_info['username']); ?></span><div class="dropdown-menu"><a href="/user/" class="dropdown-item">用户中心</a><a href="?action=logout" class="dropdown-item">安全退出</a></div></div>
                    <?php else: ?>
                    <a href="<?= TemplateManager::getUserTemplateUrl() ?>login.php" class="btn-login">登录</a><a href="<?= TemplateManager::getUserTemplateUrl() ?>register.php" class="btn-register">注册</a>
                    <?php endif; ?>
                </div>
            </header>
            <div class="top-header"><div class="top-header-content"><h1><?php echo htmlspecialchars($site_name); ?></h1><p><?php echo htmlspecialchars($site_description); ?></p><div class="search-container"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" /></svg><input type="text" id="api-search-input" placeholder="搜索您需要的接口..."></div></div></div>
            <main class="content-wrapper">
                <?php if ($announcement): ?>
                <div class="announcement-bar"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z" /></svg><div><strong><?php echo htmlspecialchars($announcement['title']); ?>:</strong> <?php echo htmlspecialchars($announcement['content']); ?></div></div>
                <?php endif; ?>
                <div class="api-grid">
                    <?php foreach ($apis as $api): $style = getCallCountStyle($api['total_calls']); ?>
                    <div class="api-card" style="border-color: <?php echo $style['border_color']; ?>;" data-name="<?php echo htmlspecialchars(strtolower($api['name'])); ?>" data-desc="<?php echo htmlspecialchars(strtolower($api['description'])); ?>">
                        <div class="card-header"><h3 class="api-name"><?php echo htmlspecialchars($api['name']); ?></h3><?php echo getStatusBadge($api['status']); ?></div>
                        <p class="api-description"><?php echo htmlspecialchars($api['description']); ?></p>
                        <div class="card-footer">
                            <div class="api-meta">
                                <?php echo getVisibilityBadge($api['visibility'], $api['is_billable']); ?>
                                <div class="meta-item"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 2a.75.75 0 01.75.75v1.5a.75.75 0 01-1.5 0v-1.5A.75.75 0 0110 2zM10 15a.75.75 0 01.75.75v1.5a.75.75 0 01-1.5 0v-1.5A.75.75 0 0110 15zM4.093 4.093a.75.75 0 011.06 0l1.061 1.061a.75.75 0 01-1.06 1.06l-1.061-1.06a.75.75 0 010-1.061zM13.786 13.786a.75.75 0 011.06 0l1.061 1.061a.75.75 0 01-1.06 1.06l-1.061-1.06a.75.75 0 010-1.061zM15.907 4.093a.75.75 0 010 1.06l-1.061 1.061a.75.75 0 01-1.06-1.06l1.06-1.061a.75.75 0 011.061 0zM5.154 13.786a.75.75 0 010 1.06l-1.061 1.061a.75.75 0 11-1.06-1.06l1.06-1.061a.75.75 0 011.061 0zM2 10a.75.75 0 01.75-.75h1.5a.75.75 0 010 1.5h-1.5A.75.75 0 012 10zM15 10a.75.75 0 01.75-.75h1.5a.75.75 0 010 1.5h-1.5A.75.75 0 0115 10z" clip-rule="evenodd" /></svg><span class="call-count" style="color: <?php echo $style['color']; ?>;"><?php echo number_format($api['total_calls']); ?><?php if($style['flame_level'] > 0) echo str_repeat('<svg class="flame-icon" style="color:'.$style['color'].'; animation-duration:'.$style['animation_duration'].';" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M11.314 2.12a.75.75 0 01.288 1.259l-2.088 3.616a.75.75 0 01-1.332-.77l2.088-3.616a.75.75 0 011.044-.489zM8.332.96a.75.75 0 011.044.489l2.088 3.616a.75.75 0 01-1.332.77L8.044 2.224a.75.75 0 01.288-1.264zM10 5.25a.75.75 0 01.75.75v1.5a.75.75 0 01-1.5 0v-1.5A.75.75 0 0110 5.25z" clip-rule="evenodd" /><path d="M7 8.25a3 3 0 013-3h.01a3 3 0 013 3v.328a.75.75 0 00.313.628l2.25 1.8a.75.75 0 01.237.981l-1.5 3.75a.75.75 0 01-1.12.396l-1.92-1.371a.75.75 0 00-.818 0l-1.92 1.371a.75.75 0 01-1.12-.396l-1.5-3.75a.75.75 0 01.237-.981l2.25-1.8A.75.75 0 007 8.578V8.25z" /></svg>', $style['flame_level']); ?></span></div>
                                <div class="meta-item"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M15.28 4.72a.75.75 0 010 1.06l-6.25 6.25a.75.75 0 01-1.06 0l-3.25-3.25a.75.75 0 011.06-1.06L9 10.94l5.72-5.72a.75.75 0 011.06 0z" clip-rule="evenodd" /></svg><?php echo strtoupper($api['response_format'] ?? 'TEXT'); ?></div>
                            </div>
                            <a href="api_details.php?id=<?php echo $api['id']; ?>" class="btn-details">查看详情</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </main>
            <footer class="page-footer">© <?php echo htmlspecialchars($copyright_info); ?></footer>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const pageContainer = document.body;
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const sidebarOverlay = document.getElementById('sidebar-overlay');
        if (mobileMenuBtn) { mobileMenuBtn.addEventListener('click', (e) => { e.stopPropagation(); pageContainer.classList.toggle('sidebar-open'); }); }
        if (sidebarOverlay) { sidebarOverlay.addEventListener('click', () => { pageContainer.classList.remove('sidebar-open'); }); }
        const searchInput = document.getElementById('api-search-input');
        const apiCards = document.querySelectorAll('.api-card');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();
                apiCards.forEach(card => {
                    const name = card.dataset.name || '';
                    const desc = card.dataset.desc || '';
                    if (name.includes(searchTerm) || desc.includes(searchTerm)) { card.style.display = 'flex'; } else { card.style.display = 'none'; }
                });
            });
        }
    });
    <a href="https://icp.gov.moe/?keyword=20265230" target="_blank">萌ICP备20265230号</a>
    </script>
</body>
</html>
