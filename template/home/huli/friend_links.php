<?php
 
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', 'Off');

 
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_trans_sid', 0);
require_once __DIR__ . '/../../../common/session_boot.php';

 
$rootPath = dirname(__DIR__, 3);
define('ROOT_PATH', $rootPath . '/');

 
if (!file_exists(ROOT_PATH . 'config.php')) {
    die("系统错误：配置文件丢失。路径: " . ROOT_PATH . 'config.php');
}
require_once ROOT_PATH . 'config.php';
require_once ROOT_PATH . 'common/TemplateManager.php';
require_once ROOT_PATH . 'common/mail.php';

 
function getDb()
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    return $pdo;
}

 
function checkUserLoginStatus()
{
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    try {
        $pdo = getDb();
        $stmt = $pdo->prepare("SELECT username, email FROM huli_users WHERE id = ? AND status = 1");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $_SESSION['user_username'] = $user['username'];
            $_SESSION['user_email'] = $user['email'];
            return true;
        }
    } catch (PDOException $e) {
        error_log("登录状态检查错误: " . $e->getMessage());
    }

     
    unset($_SESSION['user_id'], $_SESSION['user_username'], $_SESSION['user_email']);
    return false;
}

 
function createCsrfToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

 
function verifyCsrfToken($token)
{
    return !empty($token) && $token === $_SESSION['csrf_token'];
}

 
$is_logged_in = checkUserLoginStatus();
$user_info = $is_logged_in ? [
    'username' => $_SESSION['user_username'],
    'email'    => $_SESSION['user_email']
] : null;

$currentTemplate  = basename(dirname(__FILE__));
$activeTemplate   = TemplateManager::getActiveUserTemplate();
$homeTemplate     = TemplateManager::getActiveHomeTemplate();
$homeTemplateBaseUrl = "/template/home/{$homeTemplate}/";
$userTemplate     = TemplateManager::getActiveUserTemplate();
$userTemplateBaseUrl = "/template/user/{$userTemplate}/";

 
if ($currentTemplate !== $activeTemplate) {
    header("HTTP/1.1 403 Forbidden");
    ?>
    <!DOCTYPE html>
    <html lang="zh">
    <head>
        <meta charset="UTF-8">
        <title>访问被拒绝</title>
        <style>
            body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
            .container { max-width: 600px; margin: 0 auto; }
            h1 { color: #d9534f; }
            .btn { display: inline-block; padding: 10px 20px; background: #337ab7; color: white; text-decoration: none; border-radius: 4px; margin-top: 20px; }
            .btn:hover { background: #286090; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>访问被拒绝</h1>
            <p>您正在尝试访问未激活的模板页面。</p>
            <p>请从首页重新进入用户中心。</p>
            <a href="/" class="btn">返回首页</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

 
$links         = [];
$apply_msg     = '';
$apply_type    = '';
$total_links  = 0;
$broken_links  = 0;
$pdo           = getDb();

try {
     
    $sql = "SELECT * FROM huli_friend_links WHERE status='approved' AND is_hidden=0 ORDER BY sort_order DESC, created_at DESC";
    $stmt_links = $pdo->query($sql);
    $links = $stmt_links->fetchAll(PDO::FETCH_ASSOC);
    $total_links = count($links);

     
    $cntStmt = $pdo->query("SELECT COUNT(*) AS cnt FROM huli_friend_links WHERE status='approved' AND is_hidden=0 AND status_check='broken'");
    $broken_links = (int)$cntStmt->fetchColumn();

     
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_friend_link'])) {
         
        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            throw new Exception("请求非法，请刷新页面重试");
        }
        if (!$is_logged_in) {
            throw new Exception("请先登录后再申请友链");
        }

        $site_name    = trim($_POST['site_name'] ?? '');
        $url          = trim($_POST['url'] ?? '');
        $description  = trim($_POST['description'] ?? '');
        $logo_url     = trim($_POST['logo_url'] ?? '');

         
        if (empty($site_name) || empty($url)) {
            throw new Exception("网站名称和URL为必填项");
        }
        if (mb_strlen($site_name, 'UTF-8') > 50) {
            throw new Exception("网站名称长度不能超过50个字符");
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new Exception("网站URL格式不正确，请以http://或https://开头");
        }
        if (!empty($logo_url) && !filter_var($logo_url, FILTER_VALIDATE_URL)) {
            throw new Exception("LOGO URL格式不正确，请以http://或https://开头");
        }
        if (!empty($description) && mb_strlen($description, 'UTF-8') > 200) {
            throw new Exception("网站描述长度不能超过200个字符");
        }

        $user_id = $_SESSION['user_id'];
         
        $stmt_apply = $pdo->prepare("
            INSERT INTO huli_friend_links
            (site_name, url, description, logo, user_id, status, created_at)
            VALUES (?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt_apply->execute([$site_name, $url, $description, $logo_url, $user_id]);

         
        $stmt_settings = $pdo->query("SELECT setting_key, setting_value FROM huli_settings");
        $settings = [];
        while ($row = $stmt_settings->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

         
        $admin_email    = $settings['admin_email'] ?? '326284281@qq.com';
        $site_name_config = $settings['site_name'] ?? 'huliapi';
        $admin_url      = $settings['admin_url'] ?? '#';
        $current_year   = date('Y');
        $logo_url_config = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://')
            . $_SERVER['HTTP_HOST'] . '/assets/images/logo-sidebar.png';

         
        $mailSiteName  = htmlspecialchars($site_name, ENT_QUOTES);
        $mailUrl       = htmlspecialchars($url, ENT_QUOTES);
        $mailDesc      = htmlspecialchars($description, ENT_QUOTES);
        $mailLogo      = htmlspecialchars($logo_url, ENT_QUOTES);
        $mailUid       = (int)$user_id;
        $mailTime      = date('Y-m-d H:i:s');

        $subject = '【huliapi】友链申请通知';
        $body = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 15px; background-color: #f0f3f8; font-family: \'PingFang SC\', \'Microsoft YaHei\', sans-serif;">
<div style="max-width: 600px; margin: 0 auto; width: 100%; background-color: #ffffff; border-radius: 16px; box-shadow: 0 4px 20px rgba(32,102,255,0.08);">
    <div style="padding: 30px 20px; text-align: center; background: linear-gradient(135deg, #2066ff 0%, #1955d4 100%); border-radius: 16px 16px 0 0;">
        <img style="max-height: 45px; width: auto; max-width: 100%;" src="' . $logo_url_config . '" alt="' . $site_name_config . '" />
    </div>
    <div style="padding: 30px 20px;">
        <h1 style="color: #2066ff; font-size: 24px; margin: 0 0 25px; text-align: center; font-weight: bold;">友链申请通知</h1>
        <p style="color: #333333; font-size: 15px; line-height: 1.8; margin: 0; font-weight: 600;">尊敬的管理员：</p>
        <p style="color: #333333; font-size: 15px; line-height: 1.8; margin: 10px 0; font-weight: 600;">新的友链申请已提交，详情如下：</p>
        <div style="background: linear-gradient(to right, #f8f9ff, #f0f5ff); border-radius: 12px; padding: 20px; margin: 20px 0; border: 1px solid rgba(32,102,255,0.1);">
            <p style="color: #666666; font-size: 14px; line-height: 1.8; margin: 8px 0;"><span style="display: inline-block; width: 100px;">网站名称：</span> ' . $mailSiteName . '</p>
            <p style="color: #666666; font-size: 14px; line-height: 1.8; margin: 8px 0;"><span style="display: inline-block; width: 100px;">网站URL：</span> <a href="' . $mailUrl . '" target="_blank">' . $mailUrl . '</a></p>
            <p style="color: #666666; font-size: 14px; line-height: 1.8; margin: 8px 0;"><span style="display: inline-block; width: 100px;">网站描述：</span> ' . $mailDesc . '</p>
            <p style="color: #666666; font-size: 14px; line-height: 1.8; margin: 8px 0;"><span style="display: inline-block; width: 100px;">LOGO链接：</span> ' . $mailLogo . '</p>
            <p style="color: #666666; font-size: 14px; line-height: 1.8; margin: 8px 0;"><span style="display: inline-block; width: 100px;">申请用户ID：</span> ' . $mailUid . '</p>
            <p style="color: #666666; font-size: 14px; line-height: 1.8; margin: 8px 0;"><span style="display: inline-block; width: 100px;">申请时间：</span> ' . $mailTime . '</p>
        </div>
        <div style="background-color: #f8f9fa; border-radius: 8px; padding: 15px; margin: 20px 0;">
            <p style="color: #666666; font-size: 13px; line-height: 1.6; margin: 0; font-weight: 600;"><span style="color: #2066ff;">●</span> 请登录管理后台审核此友链申请</p>
            <p style="color: #666666; font-size: 13px; line-height: 1.6; margin: 8px 0 0; font-weight: 600;"><span style="color: #2066ff;">●</span> 审核通过后将在前端展示</p>
        </div>
        <div style="text-align: center; margin: 30px 0;">
            <a href="' . $admin_url . '" target="_blank" style="display: inline-block; padding: 12px 35px; background-color: #2066ff; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 15px; font-weight: 500;">立即登录后台处理</a>
        </div>
        <p style="color: #666666; font-size: 13px; line-height: 1.6; margin: 20px 0 0; font-weight: 600;">如有任何问题，请联系系统管理员。</p>
    </div>
    <div style="padding: 20px 15px; background-color: #f8f9fa; border-radius: 0 0 16px 16px; border-top: 1px solid #eef0f5;">
        <p style="color: #999999; font-size: 13px; text-align: center; margin: 0; line-height: 1.8; font-weight: 500;">本邮件由系统自动发送，请勿直接回复<br />Copyright © 2025-' . $current_year . ' huliapi 版权所有</p>
    </div>
</div>
</body>
</html>';

         
        send_mail($admin_email, $subject, $body, $pdo);
        $apply_msg  = "友链申请已提交，我们将在1-3个工作日内审核，请耐心等待";
        $apply_type = "success";

         
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
} catch (Exception $e) {
    $apply_msg  = $e->getMessage();
    $apply_type = "danger";
}

$csrf_token = createCsrfToken();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
<meta name="author" content="yinq">
<title>友情链接 - huliapi</title>
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-touch-fullscreen" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: "PingFang SC", "Microsoft YaHei", Arial, sans-serif; background-color: #f8f9fa; color: #333; line-height: 1.6; }
.container-fluid { max-width: 1200px; margin: 0 auto; padding: 1rem !important; }
.card { border-radius: 1rem !important; border: none !important; box-shadow: 0 2px 15px rgba(0,0,0,0.05) !important; transition: all 0.3s ease; }
.card-header { border-bottom: 1px solid #f1f3f5 !important; border-radius: 1rem 1rem 0 0 !important; padding: 1rem 1.5rem !important; }
.card-body { padding: 1.5rem !important; }
.btn { border-radius: 0.5rem !important; padding: 0.5rem 1.25rem !important; font-weight: 500 !important; transition: all 0.2s ease; border: none !important; }
.btn-primary { background-color: #4096ff !important; }
.btn-primary:hover { background-color: #337ecc !important; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(64, 150, 255, 0.3); }
.btn-secondary { background-color: #86909c !important; }
.btn-secondary:hover { background-color: #737f8c !important; }
.form-control { border-radius: 0.5rem !important; border: 1px solid #e5e6eb !important; padding: 0.75rem 1rem !important; transition: all 0.2s ease; }
.form-control:focus { border-color: #4096ff !important; box-shadow: 0 0 0 3px rgba(64, 150, 255, 0.1) !important; outline: none !important; }
.alert { border-radius: 0.5rem !important; border: none !important; padding: 1rem 1.25rem !important; margin-bottom: 1.5rem !important; }
.badge { border-radius: 0.3rem !important; padding: 0.25rem 0.5rem !important; font-size: 0.75rem !important; }
.modal-content { border-radius: 1rem !important; border: none !important; box-shadow: 10px 10px 30px rgba(0,0,0,0.1); }
.modal-header { border-bottom: 1px solid #f1f3f5 !important; padding: 1rem 1.5rem !important; }
.modal-footer { border-top: 1px solid #f1f3f5 !important; padding: 1rem 1.5rem !important; }
.friend-card { transition: all 0.3s ease; border-radius: 0.75rem; box-shadow: 0 3px 10px rgba(0,0,0,0.07); overflow: hidden; position: relative; border: 1px solid #f0f2f5; margin-bottom: 1rem; background: #fff; height: 100%; }
.friend-card:hover { transform: translateY(-4px); box-shadow: 0 10px 20px rgba(0,0,0,0.12); border-color: #e5e9f2; }
.friend-card .card-body { padding: 1rem !important; }
.friend-logo-container { width: 52px; height: 52px; display: flex; align-items: center; justify-content: center; background: #f8f9fa; border-radius: 6px; flex-shrink: 0; overflow: hidden; border: 1px solid #e9ecef; }
.friend-logo-img { width: 100%; height: 100%; object-fit: contain; transition: transform 0.3s ease; background: #f5f5f5; }
.friend-logo-container:hover .friend-logo-img { transform: scale(1.1); }
.friend-logo-icon { font-size: 22px; color: #4d5b76; }
.friend-card h5 { margin-top: 0; margin-bottom: 0.3rem; line-height: 1.4; font-size: 1.05rem; }
.friend-card h5 a { color: #333; text-decoration: none; transition: color 0.2s ease; }
.friend-card h5 a:hover { color: #4096ff; }
.friend-card p { margin-bottom: 0; font-size: 0.9rem; color: #6c757d; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.5; }
.friend-status { position: absolute; top: 0.6rem; right: 0.6rem; z-index: 10; }
.stats-container { display: flex; gap: 1rem; margin: 1.5rem 1.5rem 2rem; align-items: center; flex-wrap: wrap; }
.stats-card { flex: 1; min-width: 200px; border-radius: 0.75rem; transition: all 0.3s ease; overflow: hidden; box-shadow: 0 3px 10px rgba(0,0,0,0.07); display: flex; align-items: center; padding: 1rem 1.5rem; height: 90px; background: #fff; }
.stats-card:hover { transform: translateY(-3px); box-shadow: 0 8px 15px rgba(0,0,0,0.1); }
.stats-card .mdi { font-size: 2rem; margin-right: 1.2rem; flex-shrink: 0; }
.stats-info { flex-grow: 1; }
.stats-card h5.card-title { font-size: 0.9rem; margin-bottom: 0.2rem; color: #495057; font-weight: 500; }
.stats-card .stat-value { font-size: 1.8rem; font-weight: 600; line-height: 1.2; }
.stats-card.bg-primary-light { background: linear-gradient(135deg, rgba(64, 150, 255, 0.1) 0%, rgba(64, 150, 255, 0.05) 100%); color: #4096ff; }
.stats-card.bg-danger-light { background: linear-gradient(135deg, rgba(255, 77, 79, 0.1) 0%, rgba(255, 77, 79, 0.05) 100%); color: #ff4d4f; }
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 3px; }
::-webkit-scrollbar-thumb { background: #c1c1c1; border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: #a8a8a8; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.fade-in { animation: fadeIn 0.4s ease forwards; }
.friend-card-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.25rem; }
.login-tip { display: inline-flex; align-items: center; gap: 0.5rem; color: #ff4d4f; font-size: 0.9rem; }
.login-tip span { color: #ff4d4f; }
.unlogin-hint { color: #6c757d; font-size: 0.95rem; }
@media (max-width: 768px) {
    .stats-container { margin: 1rem; }
    .stats-card { height: 80px; padding: 0.8rem 1rem; }
    .stats-card .stat-value { font-size: 1.5rem; }
    .friend-card-grid { grid-template-columns: 1fr; }
    .modal-dialog { margin: 1rem; }
}
.loading-skeleton { background: linear-gradient(90deg, #f0f0f0 25%, #f8f8f8 50%, #f0f0f0 75%); background-size: 200% 100%; animation: skeleton-loading 1.5s infinite; }
@keyframes skeleton-loading { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
</style>
<link rel="stylesheet" href="../../../assets/css/materialdesignicons.min.css" media="print" onload="this.media='all'">
<link rel="stylesheet" href="../../../assets/css/bootstrap.min.css" media="print" onload="this.media='all'">
<noscript>
    <link rel="stylesheet" href="../../../assets/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css">
</noscript>
</head>
<body>
<div class="container-fluid px-3 py-4">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h4 class="mb-0 fw-bold">友情链接</h4>
                    <p class="text-muted small mb-0">展示合作网站链接及自助申请功能</p>
                </div>
                <div class="stats-container">
                    <div class="stats-card bg-primary-light">
                        <i class="mdi mdi-link-variant"></i>
                        <div class="stats-info">
                            <h5 class="card-title mb-1">友链总数</h5>
                            <div class="stat-value"><?= (int)$total_links ?></div>
                        </div>
                    </div>
                    <div class="stats-card bg-danger-light">
                        <i class="mdi mdi-alert-circle-outline"></i>
                        <div class="stats-info">
                            <h5 class="card-title mb-1">异常友链</h5>
                            <div class="stat-value"><?= (int)$broken_links ?></div>
                        </div>
                    </div>
                </div>
                <div class="card mb-4 shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center bg-white">
                        <h5 class="card-title mb-0 fw-medium"><i class="mdi mdi-website me-2"></i>友情链接列表</h5>
                        <?php if ($is_logged_in): ?>
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#applyLinkModal">
                                <i class="mdi mdi-pencil-plus me-1"></i>申请友链
                            </button>
                        <?php else: ?>
                            <div class="login-tip">
                                <i class="mdi mdi-login"></i>
                                <span>请登录后申请友链</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($apply_msg): ?>
                            <div class="alert alert-<?= htmlspecialchars($apply_type) ?> alert-dismissible fade show mb-4">
                                <?= htmlspecialchars($apply_msg) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        <div class="friend-card-grid">
                            <?php if (empty($links)): ?>
                                <div class="col-12 text-center py-5 text-muted">
                                    <i class="mdi mdi-information-outline display-4 mb-3 text-secondary"></i>
                                    <p class="fs-6">暂无友情链接，欢迎登录后申请合作</p>
                                    <?php if ($is_logged_in): ?>
                                        <button type="button" class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#applyLinkModal">
                                            <i class="mdi mdi-pencil-plus me-1"></i>立即申请
                                        </button>
                                    <?php else: ?>
                                        <p class="unlogin-hint mt-3">请先登录后进行友链申请</p>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <?php foreach ($links as $link): ?>
                                    <div class="friend-card">
                                        <div class="friend-status">
                                            <?php if ($link['status_check'] == 'broken'): ?>
                                                <span class="badge bg-danger bg-opacity-10 text-danger">
                                                    <i class="mdi mdi-alert-circle-outline me-1"></i>异常
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-success bg-opacity-10 text-success">
                                                    <i class="mdi mdi-check-circle-outline me-1"></i>正常
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-body p-3">
                                            <div class="d-flex">
                                                <div class="friend-logo-container flex-shrink-0 me-3">
                                                    <?php if (!empty($link['logo'])): ?>
                                                        <img src="<?= htmlspecialchars($link['logo']) ?>" class="friend-logo-img" alt="<?= htmlspecialchars($link['site_name']) ?>" loading="lazy">
                                                    <?php else: ?>
                                                        <?php
                                                        $siteType = 'mdi mdi-web';
                                                        if (strpos($link['url'], 'blog') !== false) $siteType = 'mdi mdi-blog';
                                                        elseif (strpos($link['url'], 'api') !== false) $siteType = 'mdi mdi-api';
                                                        elseif (strpos($link['url'], 'shop') !== false) $siteType = 'mdi mdi-store';
                                                        ?>
                                                        <i class="friend-logo-icon <?= $siteType ?>"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h5 class="card-title mb-1 fw-medium">
                                                        <a href="<?= htmlspecialchars($link['url']) ?>" target="_blank" rel="noopener noreferrer">
                                                            <?= htmlspecialchars($link['site_name']) ?>
                                                        </a>
                                                    </h5>
                                                    <p class="card-text text-sm">
                                                        <?= htmlspecialchars($link['description'] ?? '暂无描述') ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php if ($is_logged_in): ?>
                <div class="modal fade" id="applyLinkModal" tabindex="-1" aria-labelledby="applyLinkModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content shadow">
                            <div class="modal-header bg-white">
                                <h5 class="modal-title fw-medium" id="applyLinkModalLabel"><i class="mdi mdi-pencil-plus me-2"></i>申请友情链接</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-4">
                                <form id="friend-link-form" method="post" class="needs-validation" novalidate>
                                    
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label for="site_name" class="form-label">网站名称 <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="site_name" name="site_name" placeholder="请输入网站名称" maxlength="50" required>
                                            <div class="invalid-feedback">请填写网站名称（最多50个字符）</div>
                                        </div>
                                        <div class="col-12">
                                            <label for="url" class="form-label">网站URL <span class="text-danger">*</span></label>
                                            <input type="url" class="form-control" id="url" name="url" placeholder="请输入http://或https://开头的网址" required>
                                            <div class="invalid-feedback">请填写有效的URL地址</div>
                                        </div>
                                        <div class="col-12">
                                            <label for="logo_url" class="form-label">网站LOGO链接</label>
                                            <input type="url" class="form-control" id="logo_url" name="logo_url" placeholder="请输入http://或https://开头的LOGO链接">
                                            <div class="invalid-feedback">请填写有效的URL地址</div>
                                            <div class="form-text text-muted small mt-1">建议尺寸：120x120px，支持JPG、PNG、GIF、WebP格式</div>
                                        </div>
                                        <div class="col-12">
                                            <label for="description" class="form-label">网站描述</label>
                                            <textarea class="form-control" id="description" name="description" rows="3" placeholder="请简要描述您的网站" maxlength="200"></textarea>
                                            <div class="form-text text-muted small mt-1">建议填写网站核心内容或特色，有助于提高审核通过率（最多200个字符）</div>
                                        </div>
                                        <div class="col-12 mt-2">
                                            <label class="form-label d-block fw-medium">申请须知</label>
                                            <div class="alert alert-info py-2 px-3 small mb-2">
                                                <strong>申请前请先添加本站友链，信息如下：</strong><br>
                                                网站名：huliapi<br>
                                                介绍：huliapi致力于为用户提供稳定、高效的API接口服务，包含随机一言、工具类API等多种接口<br>
                                                LOGO：<a href="https://api.ipojie.com/favicon.ico" target="_blank">https://api.ipojie.com/favicon.ico</a><br>
                                                链接：<a href="https://api.ipojie.com" target="_blank">https://api.ipojie.com</a>
                                            </div>
                                            <ul class="text-muted small mb-0">
                                                <li>1. 提交后将在1-3个工作日内完成审核</li>
                                                <li>2. 内容需符合法律法规及平台规范</li>
                                                <li>3. 审核通过后将展示在友链列表中</li>
                                                <li>4. 若网站内容与本平台不符，可能会被拒绝</li>
                                            </ul>
                                        </div>
                                    </div>
                                    <input type="hidden" name="apply_friend_link" value="1">
                                </form>
                            </div>
                            <div class="modal-footer bg-white">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                                <button type="submit" form="friend-link-form" class="btn btn-primary">
                                    <i class="mdi mdi-send me-2"></i>提交申请
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script src="../../../assets/js/jquery.min.js" defer></script>
<script src="../../../assets/js/popper.min.js" defer></script>
<script src="../../../assets/js/bootstrap.min.js" defer></script>
<script>
setTimeout(() => {
    const scripts = [
        '../../../assets/js/perfect-scrollbar.min.js',
        '../../../assets/js/bootstrap-multitabs/multitabs.min.js',
        '../../../assets/js/jquery.cookie.min.js',
        '../../../assets/js/index.min.js'
    ];
    scripts.forEach(src => {
        const script = document.createElement('script');
        script.src = src;
        script.defer = true;
        document.body.appendChild(script);
    });
}, 1000);
document.addEventListener('DOMContentLoaded', function() {
    (function() {
        'use strict';
        const forms = document.querySelectorAll('.needs-validation');
        if (!forms.length) return;
        Array.from(forms).forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!form.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    })();
    const alertEl = document.querySelector('.alert');
    if (alertEl) {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alertEl);
            bsAlert.close();
        }, 5000);
    }
    const logoInput = document.getElementById('logo_url');
    if (logoInput) {
        logoInput.addEventListener('blur', function() {
            const value = this.value.trim();
            if (value && !isValidUrl(value)) {
                this.setCustomValidity('请输入有效的URL地址');
                this.classList.add('is-invalid');
            } else {
                this.setCustomValidity('');
                this.classList.remove('is-invalid');
            }
        });
    }
    function isValidUrl(string) {
        try {
            new URL(string);
            return true;
        } catch (_) {
            return false;
        }
    }
    const friendCards = document.querySelectorAll('.friend-card');
    if (friendCards.length) {
        friendCards.forEach((card, index) => {
            setTimeout(() => {
                card.classList.add('fade-in');
            }, 50 * index);
        });
    }
});
</script>
</body>
</html>
