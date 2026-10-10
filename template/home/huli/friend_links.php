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
require_once ROOT_PATH . 'common/url_helper.php';
require_once ROOT_PATH . 'common/gallery.php';
require_once ROOT_PATH . 'common/friend_link_lib.php';
require_once ROOT_PATH . 'common/turnstile.php';

 
function getDb()
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . (defined('DB_PORT') ? DB_PORT : 3306) . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
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
    $session_token = $_SESSION['csrf_token'] ?? '';
    return $session_token !== '' && $token !== '' && hash_equals($session_token, (string)$token);
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
huli_ensure_friend_link_columns($pdo);

$settings = [];
try {
    $stmt_settings = $pdo->query("SELECT setting_key, setting_value FROM huli_settings");
    while ($row = $stmt_settings->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    error_log("读取站点设置错误: " . $e->getMessage());
}
$admin_email      = $settings['admin_email'] ?? '326284281@qq.com';
$site_name_config = $settings['site_name'] ?? 'huliapi';
$admin_url        = $settings['admin_url'] ?? '#';
$current_year     = date('Y');
$site_url_config  = huli_current_origin('/');
$logo_url_config  = huli_current_origin('/assets/images/logo-sidebar.png');
$notice_logo_url  = huli_current_origin('/favicon.ico');

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

        $turnstile_reason = '';
        if (!huli_turnstile_verify($turnstile_reason)) {
            throw new Exception($turnstile_reason ?: '人机验证失败，请完成验证后再提交');
        }

        $site_name    = trim($_POST['site_name'] ?? '');
        $url          = trim($_POST['url'] ?? '');
        $description  = trim($_POST['description'] ?? '');
        $logo_url     = trim($_POST['logo_url'] ?? '');
        $email        = trim($_POST['email'] ?? '');

         
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
        if (empty($email)) {
            throw new Exception("联系邮箱为必填项");
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("联系邮箱格式不正确");
        }
        if (mb_strlen($email, 'UTF-8') > 100) {
            throw new Exception("联系邮箱长度不能超过100个字符");
        }
        if (!empty($description) && mb_strlen($description, 'UTF-8') > 200) {
            throw new Exception("网站描述长度不能超过200个字符");
        }

        $user_id = $is_logged_in ? (int)$_SESSION['user_id'] : 0;
        
        $stmt_apply = $pdo->prepare("
            INSERT INTO huli_friend_links
            (site_name, url, description, logo, user_id, email, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt_apply->execute([$site_name, $url, $description, $logo_url, $user_id, $email]);

        $mailSiteName  = htmlspecialchars($site_name, ENT_QUOTES);
        $mailUrl       = htmlspecialchars($url, ENT_QUOTES);
        $mailDesc      = htmlspecialchars($description, ENT_QUOTES);
        $mailLogo      = htmlspecialchars($logo_url, ENT_QUOTES);
        $mailUid       = $user_id > 0 ? (string)(int)$user_id : '游客（未登录）';
        $mailEmail     = htmlspecialchars($email, ENT_QUOTES);
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
            <p style="color: #666666; font-size: 14px; line-height: 1.8; margin: 8px 0;"><span style="display: inline-block; width: 100px;">联系邮箱：</span> ' . $mailEmail . '</p>
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

         
        try {
            send_mail($admin_email, $subject, $body, $pdo);
        } catch (Exception $mailError) {
            error_log("友链申请通知邮件发送失败: " . $mailError->getMessage());
        }
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
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="author" content="yinq">
<title>友情链接 - <?= htmlspecialchars($site_name_config, ENT_QUOTES, 'UTF-8') ?></title>
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-touch-fullscreen" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<link rel="stylesheet" href="../../../assets/css/liquid-glass.css?v=3">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: "PingFang SC", "Microsoft YaHei", Arial, sans-serif; background: transparent; color: var(--glass-text, #17233b); line-height: 1.6; min-height: 100vh; }
.huli-bg {
    position: fixed;
    inset: 0;
    z-index: 0;
    background:
        linear-gradient(rgba(255, 255, 255, 0.35), rgba(255, 255, 255, 0.35)),
        url('<?php echo htmlspecialchars(huli_session_gallery_image()); ?>');
    background-size: cover, cover;
    background-position: center, center;
    background-repeat: no-repeat, no-repeat;
    pointer-events: none;
}
.container-fluid { position: relative; z-index: 1; max-width: 1200px; margin: 0 auto; padding: 1rem !important; }
.card { border-radius: 1rem !important; border: 1px solid rgba(180, 220, 245, .5) !important; background: linear-gradient(140deg, rgba(255, 255, 255, .58) 0%, rgba(220, 238, 252, .44) 100%) !important; backdrop-filter: blur(16px) saturate(160%); -webkit-backdrop-filter: blur(16px) saturate(160%); box-shadow: 0 12px 32px rgba(64, 120, 180, .12) !important; transition: all 0.3s ease; }
.card-header { border-bottom: 1px solid rgba(180, 220, 245, .42) !important; border-radius: 1rem 1rem 0 0 !important; padding: 1rem 1.5rem !important; background: linear-gradient(135deg, rgba(238, 247, 255, .5), rgba(219, 234, 254, .34)) !important; }
.card-body { padding: 1.5rem !important; }
.btn { border-radius: 0.5rem !important; padding: 0.5rem 1.25rem !important; font-weight: 500 !important; transition: all 0.2s ease; border: none !important; }
.btn-primary { background-color: #4096ff !important; }
.btn-primary:hover { background-color: #337ecc !important; box-shadow: 0 4px 12px rgba(64, 150, 255, 0.3); }
.btn-secondary { background-color: #86909c !important; }
.btn-secondary:hover { background-color: #737f8c !important; }
.form-control { border-radius: 0.5rem !important; border: 1px solid rgba(180, 220, 245, .6) !important; background: rgba(255, 255, 255, .72) !important; padding: 0.75rem 1rem !important; transition: all 0.2s ease; }
.form-control:focus { border-color: #4096ff !important; box-shadow: 0 0 0 3px rgba(64, 150, 255, 0.12) !important; background: rgba(255, 255, 255, .9) !important; outline: none !important; }
.alert { border-radius: 0.5rem !important; border: none !important; padding: 1rem 1.25rem !important; margin-bottom: 1.5rem !important; }
.badge { border-radius: 0.3rem !important; padding: 0.25rem 0.5rem !important; font-size: 0.75rem !important; }
.modal-content { border-radius: 1rem !important; border: 1px solid rgba(180, 220, 245, .55) !important; background: linear-gradient(140deg, rgba(255, 255, 255, .8) 0%, rgba(224, 240, 253, .68) 100%) !important; backdrop-filter: blur(20px) saturate(160%); -webkit-backdrop-filter: blur(20px) saturate(160%); box-shadow: 10px 30px 40px rgba(0,0,0,0.12); max-height: calc(100vh - 2rem); overflow-y: auto; }
.modal-header { border-bottom: 1px solid rgba(180, 220, 245, .42) !important; padding: 1rem 1.5rem !important; }
.modal-footer { border-top: 1px solid rgba(180, 220, 245, .42) !important; padding: 1rem 1.5rem !important; position: sticky; bottom: 0; z-index: 5; background: linear-gradient(135deg, rgba(255, 255, 255, .92), rgba(224, 240, 253, .88)); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); }
.friend-card { transition: all 0.3s ease; border-radius: 0.85rem; box-shadow: 0 8px 22px rgba(64, 120, 180, 0.10); overflow: hidden; position: relative; border: 1px solid rgba(180, 220, 245, .5); margin-bottom: 1rem; background: linear-gradient(140deg, rgba(255, 255, 255, .6) 0%, rgba(220, 238, 252, .44) 100%); backdrop-filter: blur(14px) saturate(150%); -webkit-backdrop-filter: blur(14px) saturate(150%); height: 100%; }
.friend-card:hover { box-shadow: 0 14px 30px rgba(64, 120, 180, 0.18); border-color: rgba(93, 159, 232, .55); }
.friend-card .card-body { padding: 1rem !important; }
.friend-logo-container { width: 52px; height: 52px; display: flex; align-items: center; justify-content: center; background: rgba(255, 255, 255, .6); border-radius: 6px; flex-shrink: 0; overflow: hidden; border: 1px solid rgba(180, 220, 245, .55); }
.friend-logo-img { width: 100%; height: 100%; object-fit: contain; transition: transform 0.3s ease; background: #f5f5f5; }
.friend-logo-container:hover .friend-logo-img { transform: scale(1.1); }
.friend-logo-icon { font-size: 22px; color: #4d5b76; }
.friend-card h5 { margin-top: 0; margin-bottom: 0.3rem; line-height: 1.4; font-size: 1.05rem; }
.friend-card h5 a { color: #333; text-decoration: none; transition: color 0.2s ease; }
.friend-card h5 a:hover { color: #4096ff; }
.friend-card p { margin-bottom: 0; font-size: 0.9rem; color: #6c757d; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.5; }
.friend-status { position: absolute; top: 0.6rem; right: 0.6rem; z-index: 10; }
.friend-stat-badge { display: inline-flex; align-items: center; gap: .45rem; padding: .45rem .95rem; border-radius: 2rem; font-size: .875rem; background: rgba(64, 150, 255, .1); color: #2f7fe0; border: 1px solid rgba(64, 150, 255, .28); }
.friend-stat-badge strong { font-weight: 700; font-size: .95rem; }
.friend-stat-badge.is-danger { background: rgba(255, 77, 79, .08); color: #e0393e; border-color: rgba(255, 77, 79, .28); }
.friend-empty-state { padding: 2.5rem 1rem 2rem; }
.friend-empty-icon { width: 96px; height: 96px; margin: 0 auto; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, rgba(64, 150, 255, .16), rgba(105, 177, 255, .1)); border: 1px dashed rgba(64, 150, 255, .45); animation: emptyPulse 3s ease-in-out infinite; }
.friend-empty-icon .mdi { font-size: 2.8rem; color: #4096ff; }
@keyframes emptyPulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(64, 150, 255, .18); } 50% { box-shadow: 0 0 0 14px rgba(64, 150, 255, 0); } }
.apply-steps { display: flex; justify-content: center; align-items: flex-start; flex-wrap: wrap; margin-top: 2.25rem; }
.apply-step { display: flex; flex-direction: column; align-items: center; min-width: 150px; max-width: 190px; padding: 0 1rem; text-align: center; }
.apply-step .step-num { width: 38px; height: 38px; border-radius: 50%; background: linear-gradient(135deg, #4096ff, #69b1ff); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: .95rem; box-shadow: 0 6px 14px rgba(64, 150, 255, .35); }
.apply-step .step-text strong { display: block; font-size: .93rem; color: #343a40; margin-top: .65rem; }
.apply-step .step-text small { display: block; color: #8a94a6; font-size: .8rem; margin-top: .2rem; line-height: 1.45; }
.apply-step-line { width: 72px; height: 2px; margin-top: 18px; background: linear-gradient(90deg, rgba(64, 150, 255, .15), #4096ff, rgba(64, 150, 255, .15)); border-radius: 1px; flex-shrink: 0; }
.friend-url { display: inline-flex; align-items: center; gap: .35rem; margin-top: .55rem; font-size: .8rem; color: #8a94a6; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.friend-url .mdi { font-size: .85rem; }
.friend-card:hover .friend-url { color: #4096ff; }
.friend-visit-hint { position: absolute; right: .6rem; bottom: .6rem; font-size: .75rem; color: #4096ff; opacity: 0; transform: translateX(-6px); transition: all .25s ease; }
.friend-card:hover .friend-visit-hint { opacity: 1; transform: translateX(0); }
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 3px; }
::-webkit-scrollbar-thumb { background: #c1c1c1; border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: #a8a8a8; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.fade-in { animation: fadeIn 0.4s ease forwards; }
.friend-card-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.25rem; }
@media (max-width: 768px) {
    .friend-card-grid { grid-template-columns: 1fr; }
    .modal-dialog { margin: 1rem; }
    .apply-step-line { width: 36px; }
    .card-header.d-flex {
        flex-wrap: wrap;
        gap: 10px;
    }
}
.loading-skeleton { background: linear-gradient(90deg, #f0f0f0 25%, #f8f8f8 50%, #f0f0f0 75%); background-size: 200% 100%; animation: skeleton-loading 1.5s infinite; }
@keyframes skeleton-loading { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
</style>
<link rel="stylesheet" href="../../../assets/css/materialdesignicons.min.css">
<link rel="stylesheet" href="../../../assets/css/bootstrap.min.css">
</head>
<body>
<div class="huli-bg"></div>
<div class="container-fluid px-3 py-4">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h4 class="mb-1 fw-bold">友情链接</h4>
                        <p class="text-muted small mb-0">优质站点互换友链，申请提交后 1-3 个工作日内完成审核</p>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="friend-stat-badge"><i class="mdi mdi-link-variant"></i>友链总数 <strong><?= (int)$total_links ?></strong></span>
                        <?php if ((int)$broken_links > 0): ?>
                            <span class="friend-stat-badge is-danger"><i class="mdi mdi-alert-circle-outline"></i>异常友链 <strong><?= (int)$broken_links ?></strong></span>
                        <?php endif; ?>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#applyLinkModal">
                            <i class="mdi mdi-pencil-plus me-1"></i>申请友链
                        </button>
                    </div>
                </div>
                <div class="card-body p-4">
                    <?php if ($apply_msg): ?>
                        <div id="page-alert" class="alert alert-<?= htmlspecialchars($apply_type) ?> alert-dismissible fade show mb-4">
                            <?= htmlspecialchars($apply_msg) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    <?php if (empty($links)): ?>
                        <div class="friend-empty-state text-center">
                            <div class="friend-empty-icon"><i class="mdi mdi-link-variant"></i></div>
                            <h5 class="fw-bold mt-4 mb-2">期待与你互换友链</h5>
                            <p class="text-muted mb-4 mx-auto" style="max-width: 460px;">欢迎 API 工具、开发者博客、技术社区等优质站点申请合作，共同成长</p>
                            <button type="button" class="btn btn-primary btn-lg px-4" data-bs-toggle="modal" data-bs-target="#applyLinkModal">
                                <i class="mdi mdi-pencil-plus me-1"></i>立即申请友链
                            </button>
                            <div class="apply-steps">
                                <div class="apply-step">
                                    <span class="step-num">1</span>
                                    <div class="step-text"><strong>填写申请</strong><small>提交站点名称、链接与联系邮箱</small></div>
                                </div>
                                <div class="apply-step-line"></div>
                                <div class="apply-step">
                                    <span class="step-num">2</span>
                                    <div class="step-text"><strong>邮件通知</strong><small>申请信息将通过邮件通知管理员</small></div>
                                </div>
                                <div class="apply-step-line"></div>
                                <div class="apply-step">
                                    <span class="step-num">3</span>
                                    <div class="step-text"><strong>审核上线</strong><small>通过后自动展示并邮件告知结果</small></div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                            <h5 class="card-title mb-0 fw-medium"><i class="mdi mdi-website me-2"></i>友情链接列表</h5>
                            <span class="text-muted small">共 <strong class="text-body"><?= count($links) ?></strong> 个站点</span>
                        </div>
                        <div class="friend-card-grid">
                            <?php foreach ($links as $link): ?>
                                <?php $link_host = parse_url($link['url'], PHP_URL_HOST) ?: $link['url']; ?>
                                <div class="friend-card fade-in">
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
                                                    <img src="<?= htmlspecialchars($link['logo']) ?>" class="friend-logo-img" alt="<?= htmlspecialchars($link['site_name']) ?>" loading="lazy" referrerpolicy="no-referrer">
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
                                                <div class="friend-url"><i class="mdi mdi-domain"></i><?= htmlspecialchars($link_host) ?></div>
                                            </div>
                                        </div>
                                    </div>
                                    <span class="friend-visit-hint"><i class="mdi mdi-open-in-new me-1"></i>访问</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
                <div class="modal fade" id="applyLinkModal" tabindex="-1" aria-labelledby="applyLinkModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content shadow">
                            <div class="modal-header">
                                <h5 class="modal-title fw-medium" id="applyLinkModalLabel"><i class="mdi mdi-pencil-plus me-2"></i>申请友情链接</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-4">
                                <form id="friend-link-form" method="post" class="needs-validation" novalidate>
                                    <?php if ($is_logged_in): ?>
                                        <p class="small text-muted mb-3"><i class="mdi mdi-account-check-outline me-1"></i>已识别登录账号 <strong><?= htmlspecialchars($_SESSION['user_username'], ENT_QUOTES, 'UTF-8') ?></strong>，联系邮箱已自动填充</p>
                                    <?php else: ?>
                                        <p class="small text-muted mb-3"><i class="mdi mdi-account-outline me-1"></i>未登录，可直接以游客身份提交申请</p>
                                    <?php endif; ?>
                                     <?= huli_turnstile_widget_html() ?>
                                     <div id="turnstile-error" class="alert alert-danger small mt-2 mb-3 d-none"></div>
                                     <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                     <input type="hidden" name="cf-turnstile-response" value="">
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
                                            <label for="email" class="form-label">联系邮箱 <span class="text-danger">*</span></label>
                                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user_info['email'] ?? '') ?>" placeholder="请输入联系邮箱，用于接收审核结果通知" maxlength="100" required>
                                            <div class="invalid-feedback">请填写有效的邮箱地址</div>
                                            <div class="form-text text-muted small mt-1">审核通过或拒绝后，我们会向该邮箱发送通知</div>
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
                                                网站名：<?= htmlspecialchars($site_name_config, ENT_QUOTES, 'UTF-8') ?><br>
                                                介绍：<?= htmlspecialchars($site_name_config, ENT_QUOTES, 'UTF-8') ?>致力于为用户提供稳定、高效的API接口服务，包含随机一言、工具类API等多种接口<br>
                                                LOGO：<a href="<?= htmlspecialchars($notice_logo_url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= htmlspecialchars($notice_logo_url, ENT_QUOTES, 'UTF-8') ?></a><br>
                                                链接：<a href="<?= htmlspecialchars($site_url_config, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= htmlspecialchars($site_url_config, ENT_QUOTES, 'UTF-8') ?></a>
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
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                                <button type="submit" form="friend-link-form" class="btn btn-primary">
                                    <i class="mdi mdi-send me-2"></i>提交申请
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
<script src="../../../assets/js/jquery.min.js" defer></script>
<script src="../../../assets/js/popper.min.js" defer></script>
<script src="../../../assets/js/bootstrap.min.js" defer></script>
<script>
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
    const pageAlert = document.getElementById('page-alert');
    if (pageAlert) {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(pageAlert);
            bsAlert.close();
        }, 5000);
        pageAlert.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    const friendForm = document.getElementById('friend-link-form');
    const submitBtn = document.querySelector('#applyLinkModal button[form="friend-link-form"]');
    const turnstileErrorBox = document.getElementById('turnstile-error');
    function restoreSubmitBtn() {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="mdi mdi-send me-2"></i>提交申请';
    }
    if (friendForm && submitBtn) {
        friendForm.addEventListener('submit', function(e) {
            if (turnstileErrorBox) turnstileErrorBox.classList.add('d-none');
            if (!friendForm.checkValidity()) return;
            const needsTurnstile = typeof window.huliTurnstileEnsureToken === 'function' && document.querySelector('.huli-turnstile');
            if (!needsTurnstile) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>提交中...';
                setTimeout(restoreSubmitBtn, 10000);
                return;
            }
            e.preventDefault();
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>验证中...';
            window.huliTurnstileEnsureToken(function() {
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>提交中...';
                setTimeout(restoreSubmitBtn, 10000);
                friendForm.submit();
            }, function(message) {
                restoreSubmitBtn();
                if (turnstileErrorBox) {
                    turnstileErrorBox.textContent = message || '人机验证失败，请完成验证后再提交';
                    turnstileErrorBox.classList.remove('d-none');
                    turnstileErrorBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });
        });
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
