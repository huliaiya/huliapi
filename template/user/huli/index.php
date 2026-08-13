<?php
session_start();
error_reporting(0);
ini_set('display_errors', 'Off');
if (!isset($_SESSION['user_id'])) {
header('Location: /template/user/huli/login.php');
exit;
}
$rootPath = dirname(__DIR__, 3);
define('ROOT_PATH', $rootPath . '/');
require_once ROOT_PATH . 'config.php';
require_once ROOT_PATH . 'common/avatar.php';
require_once ROOT_PATH . 'common/TemplateManager.php';
$favicon_url = ''; try{$fp=new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET,DB_USER,DB_PASS);$favicon_url=$fp->query("SELECT setting_value FROM huli_settings WHERE setting_key='favicon_url'")->fetchColumn()?:'';}catch(Exception $e){}

function checkUserLoginStatus() {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
         $stmt = $pdo->prepare("SELECT username, email, qq, membership_level, membership_expire FROM huli_users WHERE id = ? AND status = 1");
         $stmt->execute([$_SESSION['user_id']]);
         $user = $stmt->fetch(PDO::FETCH_ASSOC);
         if ($user) {
             $_SESSION['user_username'] = $user['username'];
             $_SESSION['user_email'] = $user['email'];
             $_SESSION['user_qq'] = $user['qq'] ?? '';
             $_SESSION['user_membership_level'] = $user['membership_level'] ?? 'normal';
            $_SESSION['user_membership_expire'] = $user['membership_expire'] ?? null;
            return true;
        }
    } catch (PDOException $e) {
        error_log("登录状态检查错误: " . $e->getMessage());
    }
    unset($_SESSION['user_id'], $_SESSION['user_username'], $_SESSION['user_email'], $_SESSION['user_membership_level'], $_SESSION['user_membership_expire']);
    return false;
}
$is_logged_in = checkUserLoginStatus();
$user_info = $is_logged_in ? [
    'username' => $_SESSION['user_username'],
    'email' => $_SESSION['user_email'],
    'membership_level' => $_SESSION['user_membership_level'],
    'membership_expire' => $_SESSION['user_membership_expire']
] : null;
$currentTemplate = basename(dirname(__FILE__));
$activeTemplate = TemplateManager::getActiveUserTemplate();
$homeTemplate = TemplateManager::getActiveHomeTemplate();
$homeTemplateBaseUrl = "/template/home/{$homeTemplate}/";
$userTemplate = TemplateManager::getActiveUserTemplate();
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
            h1 { color: 
            .btn {
                display: inline-block;
                padding: 10px 20px;
                background: 
                color: white;
                text-decoration: none;
                border-radius: 4px;
                margin-top: 20px;
            }
            .btn:hover { background: 
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
?>

<!DOCTYPE html>
<html lang="zh">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
<meta name="author" content="yinq">
<title>用户中心 - huliapi</title>
    <?php if($favicon_url):?><link rel="shortcut icon" type="image/x-icon" href="<?php echo htmlspecialchars($favicon_url);?>"><?php endif;?>
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-touch-fullscreen" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<link rel="stylesheet" type="text/css" href="../../../assets/css/materialdesignicons.min.css">
<link rel="stylesheet" type="text/css" href="../../../assets/css/bootstrap.min.css">
<link rel="stylesheet" type="text/css" href="../../../assets/css/animate.min.css">
<link rel="stylesheet" type="text/css" href="../../../assets/js/bootstrap-multitabs/multitabs.min.css">
<link rel="stylesheet" type="text/css" href="../../../assets/css/style.min.css">
</head>
<body class="lyear-index">
<div class="lyear-layout-web">
  <div class="lyear-layout-container">
    <aside class="lyear-layout-sidebar">
      <div id="logo" class="sidebar-header">
        <a href="index.php"><img src="../../../assets/images/logo-sidebar.png" title="huliapi" alt="huliapi" /></a>
      </div>
      <div class="lyear-layout-sidebar-info lyear-scroll">
        <div class="user-info-panel text-center p-4 border-bottom">
            <?php if ($is_logged_in): ?>
                <div class="username h5 fw-bold text-dark mb-1">
                    <?php echo htmlspecialchars($user_info['username'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <div class="email small text-muted">
                    <?php echo htmlspecialchars($user_info['email'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <div class="mt-3">
                    <a href="/user/" class="btn btn-outline-primary btn-sm w-100">
                        <i class="mdi mdi-account-circle-outline me-1"></i> 个人中心
                    </a>
                </div>
            <?php else: ?>
                <div class="username h5 fw-bold text-dark mb-3">
                    游客, 您好！
                </div>
                <div class="sidebar-auth-actions d-flex gap-3 px-3">
                    <a href="<?= $userTemplateBaseUrl ?>login.php"
                       class="btn btn-outline-primary flex-grow-1">
                        登录
                    </a>
                    <a href="<?= $userTemplateBaseUrl ?>register.php"
                       class="btn btn-primary flex-grow-1">
                        注册
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <nav class="sidebar-main">
          <ul class="nav-drawer">
            <li class="nav-item active">
              <a class="multitabs" href="<?= $userTemplateBaseUrl ?>main.php" id="default-page">
                <i class="mdi mdi-home"></i>
                <span>首页</span>
              </a>
            </li>
            <li class="nav-item">
                <a href="../../../index.php" target="_blank"><i class="mdi mdi-api"></i>
                <span>接口大厅</span></a>
            </li>
            <li class="nav-item">
              <a class="multitabs" href="<?= $userTemplateBaseUrl ?>feedback.php">
                <i class="mdi mdi-comment-question-outline"></i>
                <span>问题反馈</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="multitabs" href="<?= $userTemplateBaseUrl ?>payok.php">
                <i class="mdi mdi-credit-card-outline"></i>
                <span>在线充值</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="multitabs" href="<?= $userTemplateBaseUrl ?>login_logs.php">
                <i class="mdi mdi-shield-lock-outline"></i>
                <span>我的登录日志</span>
              </a>
            </li>
          </ul>
        </nav>
        <div class="sidebar-footer">
          <p class="copyright">
            <span>Copyright © 2025-2026 huliapi 版权所有</span>
          </p>
        </div>
      </div>
    </aside>
    <header class="lyear-layout-header">
      <nav class="navbar">
        <div class="navbar-left">
          <div class="lyear-aside-toggler">
            <span class="lyear-toggler-bar"></span>
            <span class="lyear-toggler-bar"></span>
            <span class="lyear-toggler-bar"></span>
          </div>
        </div>
        <ul class="navbar-right d-flex align-items-center">
          <li class="dropdown">
            <a href="javascript:void(0)" data-bs-toggle="dropdown" class="dropdown-toggle d-flex align-items-center">
              <?php if ($is_logged_in): ?>
                <img class="avatar-md rounded-circle me-2" src="<?php echo htmlspecialchars(huli_avatar_url($_SESSION['user_qq'] ?? '')); ?>" alt="" style="width:40px;height:40px;object-fit:cover;">
                <span><?php echo htmlspecialchars($user_info['username'], ENT_QUOTES, 'UTF-8'); ?></span>
              <?php else: ?>
                <img class="avatar-md rounded-circle me-2" src="<?php echo htmlspecialchars(huli_avatar_url('')); ?>" alt="" style="width:40px;height:40px;object-fit:cover;">
                <span>未登录</span>
              <?php endif; ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
              <?php if ($is_logged_in): ?>
                <li>
                  <a class="dropdown-item" href="../../../">
                    <i class="mdi mdi-api"></i>
                    <span>接口大厅</span>
                  </a>
                </li>
                <li class="dropdown-divider"></li>
                <li>
                  <a class="dropdown-item" href="<?= $userTemplateBaseUrl ?>logout.php">
                    <i class="mdi mdi-logout-variant me-2"></i>
                    <span>退出登录</span>
                  </a>
                </li>
              <?php else: ?>
                <li class="px-2 py-1">
                  <a href="<?= $userTemplateBaseUrl ?>login.php" class="btn btn-outline-primary w-100">登录</a>
                </li>
                <li class="px-2 py-1">
                  <a href="<?= $userTemplateBaseUrl ?>register.php" class="btn btn-primary w-100">注册</a>
                </li>
              <?php endif; ?>
            </ul>
          </li>
        </ul>
      </nav>
    </header>
    <main class="lyear-layout-content">
      <div id="iframe-content"></div>
    </main>
  </div>
</div>
<script type="text/javascript" src="../../../assets/js/jquery.min.js"></script>
<script type="text/javascript" src="../../../assets/js/popper.min.js"></script>
<script type="text/javascript" src="../../../assets/js/bootstrap.min.js"></script>
<script type="text/javascript" src="../../../assets/js/perfect-scrollbar.min.js"></script>
<script type="text/javascript" src="../../../assets/js/bootstrap-multitabs/multitabs.min.js"></script>
<script type="text/javascript" src="../../../assets/js/jquery.cookie.min.js"></script>
<script type="text/javascript" src="../../../assets/js/index.min.js"></script>
<script type="text/javascript">
$(document).ready(function() {
    var iframe = $('.tab-pane.active iframe');
    if(iframe.length) {
        iframe.attr('src', iframe.attr('src') + '?_=' + new Date().getTime());
    }
});
</script>
<script type="text/javascript">
$(document).ready(function() {
    if (performance.navigation.type === 1 &&
        (document.referrer.indexOf('login.php') !== -1 ||
         document.referrer.indexOf('logout.php') !== -1)) {
        location.reload(true);
    }

    setInterval(function() {
        $.ajax({
            url: '<?= $userTemplateBaseUrl ?>check_session.php',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.logged_in !== <?= $is_logged_in ? 'true' : 'false' ?>) {
                    location.reload();
                }
            }
        });
    }, 300000);

    function huliInitDropdowns() {
        if (window.bootstrap && bootstrap.Dropdown) {
            document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(function (el) {
                if (!bootstrap.Dropdown.getInstance(el)) {
                    try { new bootstrap.Dropdown(el); } catch (e) {}
                }
            });
        }
        if (!document.documentElement.getAttribute('data-huli-dd-init')) {
            document.documentElement.setAttribute('data-huli-dd-init', '1');
            document.addEventListener('click', function(e) {
                if (e.target.closest('.dropdown-menu') || e.target.closest('[data-bs-toggle="dropdown"]')) return;
                document.querySelectorAll('.dropdown-menu.show').forEach(function(m) {
                    var parent = m.closest('.dropdown');
                    var t = parent ? parent.querySelector('[data-bs-toggle="dropdown"]') : null;
                    var d = t ? bootstrap.Dropdown.getInstance(t) : null;
                    if (d) d.hide();
                });
            }, true);
        }
    }
    huliInitDropdowns();
    window.addEventListener('load', huliInitDropdowns);
});
</script>
</body>
</html>
