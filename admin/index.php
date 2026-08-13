<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (!file_exists('../config.php')) {
    die("出现错误！配置文件丢失，请先完成安装。");
}
require_once '../config.php';
require_once __DIR__ . '/../common/avatar.php';
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: login.php');
    exit;
}
$username = htmlspecialchars($_SESSION['admin_username']);
$admin_qq = '';
try { $pdo_a = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS); $stmt_a = $pdo_a->prepare("SELECT qq FROM huli_admins WHERE id = ?"); $stmt_a->execute([$_SESSION['admin_id']]); $admin_qq = $stmt_a->fetchColumn() ?: ''; $favicon_url = $pdo_a->query("SELECT setting_value FROM huli_settings WHERE setting_key='favicon_url'")->fetchColumn()?:''; } catch (Exception $e) { $favicon_url = ''; }
try { @require_once '../common/email_broadcast_dispatcher.php'; huli_broadcast_web_tick($pdo_a); } catch (Throwable $e) {}
?>

<!DOCTYPE html>
<html lang="zh">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
<meta name="description" content="<?php echo htmlspecialchars($settings['site_name'] ?? 'huliapi'); ?> 仪表盘 - huliapi">
<meta name="author" content="yinq">
<title><?php echo htmlspecialchars($settings['site_name'] ?? 'huliapi'); ?> - 仪表盘 - huliapi</title>
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-touch-fullscreen" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
    <?php if (!empty($favicon_url)): ?><link rel="shortcut icon" type="image/x-icon" href="<?php echo htmlspecialchars($favicon_url); ?>"><?php endif; ?>
<link rel="stylesheet" type="text/css" href="../assets/css/materialdesignicons.min.css">
<link rel="stylesheet" type="text/css" href="../assets/css/bootstrap.min.css">
<link rel="stylesheet" type="text/css" href="../assets/css/animate.min.css">
<link rel="stylesheet" type="text/css" href="../assets/js/bootstrap-multitabs/multitabs.min.css">
<link rel="stylesheet" type="text/css" href="../assets/css/style.min.css">
<style>
.sidebar-main .nav-item .mdi {
    font-size: 16px;
    width: 24px;
    height: 24px;
    display: inline-block;
    text-align: center;
    line-height: 24px;
    margin-right: 8px;
    transition: all 0.3s ease;
}
.sidebar-main .nav-item:hover .mdi {
    transform: scale(1.05);
}
.sidebar-main .nav-item.active .mdi {
    font-weight: 500;
}
.sidebar-main .nav-item > a {
    display: flex;
    align-items: center;
}
</style>
</head>
<body class="lyear-index">
<div class="lyear-layout-web">
  <div class="lyear-layout-container">
    <aside class="lyear-layout-sidebar">
      <div id="logo" class="sidebar-header">
        <a href="./"><img src="../assets/images/logo-sidebar.png" title="huliapi" alt="huliapi" /></a>
      </div>
      <div class="lyear-layout-sidebar-info lyear-scroll">
        <nav class="sidebar-main">
          <ul class="nav-drawer">
            <li class="nav-item active">
              <a class="multitabs" href="main.php" id="default-page">
                <i class="mdi mdi-home-outline"></i>
                <span>首页</span>
              </a>
            </li>
            <li class="nav-item nav-item-has-subnav">
              <a href="javascript:void(0)">
                <i class="mdi mdi-api"></i>
                <span>API 管理</span>
              </a>
              <ul class="nav nav-subnav">
                <li> <a class="multitabs" href="api_list.php">接口列表</a> </li>
                <li> <a class="multitabs" href="api_edit.php">添加接口</a> </li>
                <li> <a class="multitabs" href="category_edit.php">添加分类</a> </li>
                <li> <a class="multitabs" href="category_list.php">接口分类</a> </li>
              </ul>
            </li>
            <li class="nav-item nav-item-has-subnav">
              <a href="javascript:void(0)">
                <i class="mdi mdi-account-group"></i>
                <span>用户管理</span>
              </a>
              <ul class="nav nav-subnav">
                <li> <a class="multitabs" href="user_list.php">用户列表</a> </li>
                <li> <a class="multitabs" href="temp_keys.php">临时密钥</a> </li>
              </ul>
            </li>
            <li class="nav-item nav-item-has-subnav">
              <a href="javascript:void(0)">
                <i class="mdi mdi-link-variant"></i>
                <span>友链管理</span>
              </a>
              <ul class="nav nav-subnav">
                <li> <a class="multitabs" href="friend_links.php">友链列表</a> </li>
                <li> <a class="multitabs" href="friend_link_add.php">添加友链</a> </li>
              </ul>
            </li>
            <li class="nav-item nav-item-has-subnav">
              <a href="javascript:void(0)">
                <i class="mdi mdi-bullhorn"></i>
                <span>广告管理</span>
              </a>
              <ul class="nav nav-subnav">
                <li> <a class="multitabs" href="advertisements.php">广告列表</a> </li>
                <li> <a class="multitabs" href="add_advertisement.php">添加广告</a> </li>
              </ul>
            </li>
            <li class="nav-item nav-item-has-subnav">
              <a href="javascript:void(0)">
                <i class="mdi mdi-email-send"></i>
                <span>邮件群发</span>
              </a>
              <ul class="nav nav-subnav">
                <li> <a class="multitabs" href="email_broadcasts.php">群发列表</a> </li>
                <li> <a class="multitabs" href="email_broadcast_create.php">新建群发</a> </li>
              </ul>
            </li>
            <li class="nav-item nav-item-has-subnav">
              <a href="javascript:void(0)">
                <i class="mdi mdi-credit-card-outline"></i>
                <span>计费管理</span>
              </a>
              <ul class="nav nav-subnav">
                <li> <a class="multitabs" href="billing_plans.php">计费方案</a> </li>
                <li> <a class="multitabs" href="order_list.php">订单列表</a> </li>
                <li> <a class="multitabs" href="cdkeys.php">卡密管理</a> </li>
              </ul>
            </li>
            <li class="nav-item nav-item-has-subnav">
              <a href="javascript:void(0)">
                <i class="mdi mdi-text-box-outline"></i>
                <span>内容管理</span>
              </a>
              <ul class="nav nav-subnav">
                <li> <a class="multitabs" href="announcement_list.php">公告管理</a> </li>
                <li> <a class="multitabs" href="feedback_list.php">用户反馈</a> </li>
              </ul>
            </li>
            <li class="nav-item nav-item-has-subnav">
              <a href="javascript:void(0)">
                <i class="mdi mdi-cog-outline"></i>
                <span>系统设置</span>
              </a>
              <ul class="nav nav-subnav">
                <li> <a class="multitabs" href="settings.php">基础设置</a> </li>
                <li> <a class="multitabs" href="payment_settings.php">支付配置</a> </li>
                <li> <a class="multitabs" href="template.php">模板切换</a> </li>

              </ul>
            </li>
            <li class="nav-item nav-item-has-subnav">
              <a href="javascript:void(0)">
                <i class="mdi mdi-toolbox-outline"></i>
                <span>系统工具</span>
              </a>
              <ul class="nav nav-subnav">
                <li> <a class="multitabs" href="update.php">更新检测</a> </li>
                <li> <a class="multitabs" href="system_check.php">环境检测</a> </li>
                <li> <a class="multitabs" href="login_logs.php">登录日志</a> </li>
              </ul>
            </li>
          </ul>
        </nav>
        <div class="sidebar-footer">
          <p class="copyright">
            <span>Copyright © 2025-<?php echo "".date("Y").""; ?> huliapi 版权所有</span>
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
            <a href="javascript:void(0)" data-bs-toggle="dropdown" class="dropdown-toggle d-flex align-items-center huli-user-toggle">
              <img class="avatar-md rounded-circle" src="<?php echo htmlspecialchars(huli_avatar_url($admin_qq)); ?>" alt="" style="width:40px;height:40px;object-fit:cover;" />
              <span style="margin-left: 10px;"><?php echo htmlspecialchars($username); ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
              <li>
                <a class="multitabs dropdown-item" data-url="profile.php" href="javascript:void(0)">
                  <i class="mdi mdi-account"></i>
                  <span>个人信息</span>
                </a>
              </li>
              <li>
                <a class="dropdown-item" href="javascript:void(0)">
                  <i class="mdi mdi-delete"></i>
                  <span>清空缓存</span>
                </a>
              </li>
              <li class="dropdown-divider"></li>
              <li>
                <a class="dropdown-item" href="?action=logout">
                  <i class="mdi mdi-logout-variant"></i>
                  <span>退出登录</span>
                </a>
              </li>
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
<script type="text/javascript" src="../assets/js/jquery.min.js"></script>
<script type="text/javascript" src="../assets/js/popper.min.js"></script>
<script type="text/javascript" src="../assets/js/bootstrap.min.js"></script>
<script type="text/javascript" src="../assets/js/perfect-scrollbar.min.js"></script>
<script type="text/javascript" src="../assets/js/bootstrap-multitabs/multitabs.min.js"></script>
<script type="text/javascript" src="../assets/js/jquery.cookie.min.js"></script>
<script type="text/javascript" src="../assets/js/index.min.js"></script>
<script type="text/javascript">
$(function() {
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
<script>
(function () {
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    var targets = document.querySelectorAll(
        '.card.bg-primary, .card.bg-pink, .card.bg-success, .card.bg-danger, .card.bg-warning, .card.bg-info, .floating-sidebar-btn .btn-float'
    );
    targets.forEach(function (el) {
        var duration = (3.8 + Math.random() * 2.6).toFixed(2) + 's';
        var delay = (Math.random() * 2).toFixed(2) + 's';
        el.classList.add('huli-float');
        el.style.setProperty('--float-duration', duration);
        el.style.setProperty('--float-delay', delay);
    });
})();
</script>
</body>
</html>
