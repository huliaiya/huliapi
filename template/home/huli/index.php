

<?php
$conf['qqjump']=1;
if(strpos($_SERVER['HTTP_USER_AGENT'], 'QQ/')||strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')!==false && $conf['qqjump']==1){
$siteurl=htmlspecialchars('http://'.$_SERVER['SERVER_NAME'].':'.$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"], ENT_QUOTES, 'UTF-8');
echo "
<html>
<head>
    <meta charset='UTF-8'>
    <title>使用浏览器打开</title>
    <meta content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no' name='viewport'>
    <meta content='yes' name='apple-mobile-web-app-capable'>
    <meta content='black' name='apple-mobile-web-app-status-bar-style'>
    <meta name='format-detection' content='telephone=no'>
    <meta content='false' name='twcClient' id='twcClient'>
    <meta name='aplus-touch' content='1'>
    <style>
        body,html{width:100%;height:100%}
        *{margin:0;padding:0}
        body{background-color:#fff}
        #browser img{width:50px;}
        #browser{margin:0px 10px;text-align:center;}
        #contens{font-weight:bold;margin:-285px 0px 10px;text-align:center;font-size:20px;margin-bottom:125px;}
        .top-bar-guidance{font-size:15px;color:#fff;height:70%;line-height:1.8;padding-left:20px;padding-top:20px;background:url(//gw.alicdn.com/tfs/TB1eSZaNFXXXXb.XXXXXXXXXXXX-750-234.png) center top/contain no-repeat}
        .top-bar-guidance .icon-safari{width:25px;height:25px;vertical-align:middle;margin:0 .2em}
        .app-download-tip{margin:0 auto;width:290px;text-align:center;font-size:15px;color:#2466f4;background:url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAAcAQMAAACak0ePAAAABlBMVEUAAAAdYfh+GakkAAAAAXRSTlMAQObYZgAAAA5JREFUCNdjwA8acEkAAAy4AIE4hQq/AAAAAElFTkSuQmCC) left center/auto 15px repeat-x}
        .app-download-tip .guidance-desc{background-color:#fff;padding:0 5px}
        .app-download-btn{display:block;width:214px;height:40px;line-height:40px;margin:18px auto 0 auto;text-align:center;font-size:18px;color:#2466f4;border-radius:20px;border:.5px #2466f4 solid;text-decoration:none}
    </style>
</head>
<body>
    <div class='top-bar-guidance'>
        <p>点击右上角<img src='//gw.alicdn.com/tfs/TB1xwiUNpXXXXaIXXXXXXXXXXXX-55-55.png' class='icon-safari'> <span id='openm'>Safari打开</span></p>
        <p>可以继续浏览本站哦~</p>
    </div>
    <div id='contens'>
        1.防止腾讯屏蔽本站链接<br /><br />
        2.建议用QQ浏览器打开效果最佳<br />
    </div>
    <div id='browser'>
        <a href='mttbrowser://url=".$siteurl."'><img src='https://gimg3.baidu.com/search/src=https%3A%2F%2Fapp-center.cdn.bcebos.com%2Fappcenter%2Fsts%2Fpcfile%2F5246296509%2F6eb2db54d4e6c9cc9149df6c154f0f34.jpg&refer=http%3A%2F%2Fwww.baidu.com&app=2021&size=w150&n=0&g=0n&er=404&q=100&fmt=auto&maxorilen2heic=2000000?sec=1758042000&t=f0e9b397102a040d0847b9ba38e6ceb3'></img></a>
        <a href='googlechrome://browse?url=".$siteurl."'><img src='https://gimg3.baidu.com/topone/src=https%3A%2F%2Fbkimg.cdn.bcebos.com%2Fsmart%2Fbba1cd11728b4710b912b512f295d4fdfc03934585e0-bkimg-process%2Cv_1%2Crw_1%2Crh_1%2Cmaxl_800%2Cpad_1%3Fx-bce-process%3Dimage%2Fresize%2Cm_pad%2Cw_348%2Ch_348%2Ccolor_ffffff&refer=http%3A%2F%2Fwww.baidu.com&app=2011&size=f200,200&n=0&g=0n&er=404&q=75&fmt=auto&maxorilen2heic=2000000?sec=1758042000&t=354f26b9d3e715b2240add7a0b73144a'></img></a>
        <a href='alipays://platformapi/startapp?appId=20000067&url=".$siteurl."'><img src='https://gimg3.baidu.com/search/src=http%3A%2F%2Fgdown.baidu.com%2Fappcenter%2Fsource%2F6853219754%2F9f89f22c405c408c475839154ad7f5b4%2Fres%2Fdrawable-xxxhdpi-v4%2Fappicon.png&refer=http%3A%2F%2Fwww.baidu.com&app=2021&size=w150&n=0&g=0n&er=404&q=100&fmt=auto&maxorilen2heic=2000000?sec=1758042000&t=4884b29bb5854d92fbf46e871380a44b'></img></a>
        <a href='ucbrowser://".$siteurl."'><img src='https://fc1tn.baidu.com/it/u=3267880334,2269955267&fm=203&app=0&size=r1,1&n=0&g=4n&f=auto'></img></a>
        <a href='bdbrowser://".$siteurl."'><img src='https://img1.baidu.com/it/u=1371010713,304130506&fm=253&fmt=auto&app=138&f=PNG?w=285&h=285'></img></a>
    </div>
    <div class='app-download-tip'>
        <span class='guidance-desc'>点击上方图标or复制本站网址自行打开</span>
    </div>
    <script src='https://code.jquery.com/jquery-3.3.1.min.js'></script>
    <script>
        $(document).on('click',function(){
            window.location.href='https://ti.qq.com/new_open_qq/index.html?appid=64&url=mqqapi%3A%2F%2Fgroup%2Fjoin_troop%3Fsrc_type%3Dinternal%26version%3D1%26troop_uin%3D871910217%26subsource_id%3D1030%26is_need_jump_aio%3D1';
        });
    </script>
</body>
</html>
";
exit;
}
?>


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
require_once ROOT_PATH . 'common/avatar.php';
require_once ROOT_PATH . 'common/TemplateManager.php';

function checkUserLoginStatus() {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
         $stmt = $pdo->prepare("SELECT username, email, qq FROM huli_users WHERE id = ? AND status = 1");
         $stmt->execute([$_SESSION['user_id']]);
         $user = $stmt->fetch(PDO::FETCH_ASSOC);
         if ($user) {
             $_SESSION['user_username'] = $user['username'];
             $_SESSION['user_email'] = $user['email'];
             $_SESSION['user_qq'] = $user['qq'] ?? '';
            return true;
        }
    } catch (PDOException $e) {
        error_log("登录状态检查错误: " . $e->getMessage());
    }
    unset($_SESSION['user_id'], $_SESSION['user_username'], $_SESSION['user_email']);
    return false;
}
$is_logged_in = checkUserLoginStatus();
$user_info = $is_logged_in ? ['username' => $_SESSION['user_username'], 'email' => $_SESSION['user_email']] : null;
try {
    $homeTemplate = TemplateManager::getActiveHomeTemplate() ?: '2';
    $userTemplate = TemplateManager::getActiveUserTemplate() ?: '2';
} catch (Exception $e) {
    $homeTemplate = 'default';
    $userTemplate = 'default';
    error_log("获取模板信息失败: " . $e->getMessage());
}
$homeTemplateBaseUrl = "/template/home/{$homeTemplate}/";
$userTemplateBaseUrl = "/template/user/{$userTemplate}/";
$apis = []; $announcement = null; $settings = []; $recent_announcements = [];
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt_apis = $pdo->query("SELECT * FROM huli_apis ORDER BY id DESC");
    $apis = $stmt_apis->fetchAll(PDO::FETCH_ASSOC);
    $stmt_announcement = $pdo->query("SELECT * FROM huli_announcements WHERE is_active = 1 ORDER BY created_at DESC LIMIT 1");
    $announcement = $stmt_announcement->fetch(PDO::FETCH_ASSOC);
    $stmt_recent = $pdo->query("SELECT * FROM huli_announcements WHERE is_active = 1 ORDER BY created_at DESC LIMIT 3");
    $recent_announcements = $stmt_recent->fetchAll(PDO::FETCH_ASSOC);
    $stmt_settings = $pdo->query("SELECT setting_key, setting_value FROM huli_settings");
    $settings = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    error_log("数据库连接错误: " . $e->getMessage());
    $settings = [
        'site_name' => 'huliapi',
        'site_description' => 'huliapi致力于为用户提供稳定、高效的API接口服务，包含随机一言、工具类API等多种接口',
        'copyright_info' => 'Copyright © 2025-2026 huliapi 版权所有',
        'allow_temp_key' => 1
    ];
}
$site_name = $settings['site_name'] ?? 'huliapi';
$site_description = $settings['site_description'] ?? 'huliapi致力于为用户提供稳定、高效的API接口服务，包含随机一言、工具类API等多种接口';
$copyright_info = $settings['copyright_info'] ?? 'Copyright © 2025-2026 huliapi 版权所有';
$allow_temp_key = isset($settings['allow_temp_key']) ? (int)$settings['allow_temp_key'] : 1;
?>

<!DOCTYPE html>
<html lang="zh">
<head>
<link rel="canonical" href="https://api.ipojie.com/">
<meta name="360-site-verification" content="3de8421c57ddad73afb44d022c9d75c5" />
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
<meta name="keywords" content="<?php echo htmlspecialchars($site_name); ?>">
<meta name="description" content="<?php echo htmlspecialchars($site_description); ?>">
<meta name="author" content="yinq">
<title>首页 - <?php echo htmlspecialchars($site_name); ?></title>
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-touch-fullscreen" content="yes">
    <?php if (!empty($settings['favicon_url'])): ?><link rel="shortcut icon" type="image/x-icon" href="<?php echo htmlspecialchars($settings['favicon_url']); ?>"><?php endif; ?>
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<link rel="stylesheet" type="text/css" href="../../../assets/css/materialdesignicons.min.css">
<link rel="stylesheet" type="text/css" href="../../../assets/css/bootstrap.min.css">
<link rel="stylesheet" type="text/css" href="../../../assets/css/animate.min.css">
<link rel="stylesheet" type="text/css" href="../../../assets/js/bootstrap-multitabs/multitabs.min.css">
<link rel="stylesheet" type="text/css" href="../../../assets/css/style.min.css">
<style>
.search-box-wrapper {
    position: relative;
    width: 100%;
}
.search-box-wrapper .search-icon {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #6c757d;
    font-size: 18px;
    z-index: 2;
    pointer-events: none;
}
.search-box-wrapper .form-control {
    padding-left: 45px !important;
    border-radius: 50px;
    height: 44px;
    border: 1px solid #e2e8f0;
    transition: all 0.3s ease;
}
.search-box-wrapper .form-control:focus {
    border-color: #4096ff;
    box-shadow: 0 0 0 3px rgba(64, 150, 255, 0.1);
}
body {
    background: 
        radial-gradient(circle at 5% 5%, rgba(93, 177, 255, 0.35), transparent 35%),
        radial-gradient(circle at 95% 95%, rgba(38, 208, 194, 0.3), transparent 40%),
        radial-gradient(circle at 50% 10%, rgba(113, 132, 255, 0.22), transparent 45%),
        linear-gradient(135deg, #f5f8fc 0%, #eef3fa 100%) !important;
    background-attachment: fixed !important;
}
.lyear-layout-sidebar {
    background: rgba(255, 255, 255, 0.45) !important;
    backdrop-filter: blur(25px) saturate(200%) !important;
    -webkit-backdrop-filter: blur(25px) saturate(200%) !important;
    border-right: 1px solid rgba(255, 255, 255, 0.3) !important;
}
.lyear-layout-header {
    background: rgba(255, 255, 255, 0.4) !important;
    backdrop-filter: blur(25px) saturate(200%) !important;
    -webkit-backdrop-filter: blur(25px) saturate(200%) !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.25) !important;
    box-shadow: 0 10px 30px rgba(0,0,0,0.02) !important;
}
.lyear-layout-content {
    background: transparent !important;
}
.sidebar-header {
    background: transparent !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.2) !important;
}
.user-info-panel {
    border-bottom-color: rgba(255, 255, 255, 0.2) !important;
}
.recent-announcements {
    border-bottom-color: rgba(255, 255, 255, 0.2) !important;
}
.nav-drawer a {
    color: #4a5568 !important;
    transition: all 0.2s ease !important;
}
.nav-drawer li.active a, .nav-drawer li.active a:hover {
    background: rgba(40, 121, 186, 0.15) !important;
    color: #2879ba !important;
    border-radius: 10px;
}
.nav-drawer li a:hover {
    background: rgba(255, 255, 255, 0.3) !important;
    border-radius: 10px;
}
.sidebar-footer {
    border-top: 1px solid rgba(255, 255, 255, 0.2) !important;
    background: transparent !important;
}
</style>
</head>
<body class="lyear-index">
<div class="lyear-layout-web">
  <div class="lyear-layout-container">
    <aside class="lyear-layout-sidebar">
      <div id="logo" class="sidebar-header">
        <a href="./"><img src="../../../assets/images/logo-sidebar.png" title="huliapi" alt="huliapi" /></a>
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
        <div class="recent-announcements p-3 border-bottom">
            <h6 class="fw-bold mb-3"><i class="mdi mdi-bullhorn-outline me-2"></i>最新公告</h6>
            <?php if (!empty($recent_announcements)): ?>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($recent_announcements as $item):
                        $year = date('Y', strtotime($item['created_at']));
                        $month = date('n', strtotime($item['created_at']));
                        $day = date('j', strtotime($item['created_at']));
                        $time = date('H:i:s', strtotime($item['created_at']));
                        $formatted_time = "{$year}年{$month}月{$day}日 {$time}";
                    ?>
                        <li class="mb-3">
                            <a href="javascript:void(0);" class="text-decoration-none announcement-link d-block"
                               data-title="<?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?>"
                               data-content="<?php echo htmlspecialchars($item['content'], ENT_QUOTES, 'UTF-8'); ?>"
                               data-created="<?php echo htmlspecialchars($item['created_at']); ?>">
                                <span class="d-block fw-normal text-dark">
                                    <i class="mdi mdi-circle-small text-primary"></i>
                                    <?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                                <span class="d-block small text-muted mt-1">发布时间:<?php echo $formatted_time; ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="text-muted small mb-0">暂无公告</p>
            <?php endif; ?>
        </div>
        <nav class="sidebar-main">
          <ul class="nav-drawer">
            <li class="nav-item active">
              <a class="multitabs" href="<?= $homeTemplateBaseUrl ?>main1.php" id="default-page">
                <i class="mdi mdi-home"></i>
                <span>首页</span>
              </a>
            </li>
            <li class="nav-item">
                <a href="/user/" target="_blank"><i class="mdi mdi-account-circle"></i>
                <span>用户中心</span></a>
            </li>
            <li class="nav-item">
              <a class="multitabs" href="<?= $homeTemplateBaseUrl ?>friend_links.php" id="default-page">
                <i class="mdi mdi-link"></i>
                <span>友链列表</span>
              </a>
            </li>
                        <li class="nav-item">
              <a class="multitabs" href="<?= $userTemplateBaseUrl ?>feedback.php" id="default-page">
                <i class="mdi mdi-comment-question-outline"></i>
                <span>问题反馈</span>
              </a>
            </li>
                        <li class="nav-item">
              <a class="multitabs" href="<?= $userTemplateBaseUrl ?>payok.php" id="default-page">
                <i class="mdi mdi-credit-card-outline"></i>
                <span>在线充值</span>
              </a>
            </li>
<li class="nav-item">
  <a class="multitabs" href="<?= $homeTemplateBaseUrl ?>tongji.php">
    <i class="mdi mdi-chart-bar"></i>
    <span>API统计</span>
  </a>
</li>
<?php if ($allow_temp_key && !$is_logged_in): ?>
    <li class="nav-item">
        <a class="multitabs" href="<?= $userTemplateBaseUrl ?>temp_key.php" id="default-page">
            <i class="mdi mdi-key-variant"></i>
            <span>申请临时密钥</span>
        </a>
    </li>
<?php endif; ?>
<li class="nav-item">
  <a class="multitabs" href="zanzhu.html">
    <i class="mdi mdi-information-outline"></i>
    <span>赞助我们</span>
  </a>
</li>
<li class="nav-item">
  <a class="multitabs" href="https://t.me/huliaiya">
    <i class="mdi mdi-chart-bar"></i>
    <span>加入官方群</span>
  </a>
</li>
          </ul>
        </nav>
        <div class="sidebar-footer">
          <p class="copyright">
            <span><?php echo htmlspecialchars($copyright_info); ?> </span>
          </p>
          <div class="running-time">
            <span>运行时间：</span>
            <span id="running-time">计算中...</span>
          </div>
          <div class="filing-info">
            <?php if (!empty($settings['icp_record_number'])): ?>
            <div class="filing-item">
              <span>ICP备案号：</span>
              <a href="http://beian.miit.gov.cn/" target="_blank"><?php echo htmlspecialchars($settings['icp_record_number']); ?></a>
            </div>
            <?php endif; ?>
            <?php if (!empty($settings['police_record_number'])): ?>
            <div class="filing-item">
              <img src="http://qy.xilemon.com/static/public/images/mps.png" alt="公安备案标识" class="police-badge">
              <a href="https://beian.mps.gov.cn/#/query/webSearch?code=<?php echo htmlspecialchars(preg_replace('/[^0-9]/', '', $settings['police_record_number'])); ?>" target="_blank"><?php echo htmlspecialchars($settings['police_record_number']); ?></a>
            </div>
            <?php endif; ?>
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
                  <a class="dropdown-item" href="/user/">
                    <i class="mdi mdi-account-circle-outline me-2"></i>
                    <span>用户中心</span>
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

<div class="modal fade" id="announcementModal" tabindex="-1" aria-labelledby="announcementModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="announcementModalLabel">公告详情</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h6 id="modalAnnouncementTitle" class="fw-bold"></h6>
                <p id="modalAnnouncementContent" class="mt-2" style="white-space: pre-wrap; word-break: break-word;"></p>
                <div id="modalAnnouncementTime" class="text-muted small mt-3"></div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript" src="../../../assets/js/jquery.min.js"></script>
<script type="text/javascript" src="../../../assets/js/popper.min.js"></script>
<script type="text/javascript" src="../../../assets/js/bootstrap.min.js"></script>
<script type="text/javascript" src="../../../assets/js/perfect-scrollbar.min.js"></script>
<script type="text/javascript" src="../../../assets/js/bootstrap-multitabs/multitabs.min.js"></script>
<script type="text/javascript" src="../../../assets/js/jquery.cookie.min.js"></script>
<script type="text/javascript" src="/../../assets/js/index.min.js"></script>
<script type="text/javascript">
function formatTime(datetimeStr) {
    var date = new Date(datetimeStr);
    var year = date.getFullYear();
    var month = date.getMonth() + 1;
    var day = date.getDate();
    var hours = date.getHours();
    var minutes = date.getMinutes();
    var seconds = date.getSeconds();
    return year + '年' + month + '月' + day + '日 ' +
           (hours < 10 ? '0' + hours : hours) + ':' +
           (minutes < 10 ? '0' + minutes : minutes) + ':' +
           (seconds < 10 ? '0' + seconds : seconds);
}

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

    function updateRunningTime() {
        var startTime = new Date('<?php echo date('Y-m-d H:i:s', filemtime(ROOT_PATH . 'config.php')); ?>');
        var now = new Date();
        var diff = now - startTime;
        var days = Math.floor(diff / (1000 * 60 * 60 * 24));
        var hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        var minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        var seconds = Math.floor((diff % (1000 * 60)) / 1000);
        var runningTimeStr = days + '天 ' + hours + '小时 ' + minutes + '分钟';
        $('#running-time').text(runningTimeStr);
    }
    updateRunningTime();
    setInterval(updateRunningTime, 1000);
    <?php if (!empty($announcement)):
        $announcement_id = $announcement['id'];
        $announcement_title = $announcement['title'];
        $announcement_content = $announcement['content'];
        $simple_time = date('Y-m-d H:i:s', strtotime($announcement['created_at']));
    ?>

    function shouldShowAnnouncement() {
        var today = new Date().toISOString().split('T')[0];
        var storedData = localStorage.getItem('announcement_viewed');
        if (storedData) {
            try {
                var data = JSON.parse(storedData);
                if (data.date === today && data.announcementId === <?php echo $announcement_id; ?>) {
                    return false;
                }
            } catch (e) {
            }
        }
        localStorage.setItem('announcement_viewed', JSON.stringify({
            date: today,
            announcementId: <?php echo $announcement_id; ?>
        }));
        return true;
    }
    if (shouldShowAnnouncement()) {
        $('#modalAnnouncementTitle').text('标题:' + <?php echo json_encode($announcement_title, JSON_UNESCAPED_UNICODE); ?>);
        $('#modalAnnouncementContent').text('内容:' + <?php echo json_encode($announcement_content, JSON_UNESCAPED_UNICODE); ?>);
        $('#modalAnnouncementTime').text('发布时间:<?php echo $simple_time; ?>');
        $('#announcementModal').modal('show');
    }
    <?php endif; ?>
    $('.announcement-link').on('click', function(e) {
        e.preventDefault();
        var title = $(this).data('title');
        var content = $(this).data('content');
        var created = $(this).data('created');
        $('#modalAnnouncementTitle').text('标题:' + title);
        $('#modalAnnouncementContent').text('内容:' + content);
        $('#modalAnnouncementTime').text('发布时间:' + formatTime(created));
        $('#announcementModal').modal('show');
    });

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
/* Random floating motion for stat cards, API cards, and FAB.
   Each element gets a unique duration (3.8-6.4s) and delay (0-2s)
   so the page feels alive without synchronised pulses. */
(function () {
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    var targets = document.querySelectorAll(
        '.card.bg-primary, .card.bg-pink, .card.bg-success, .card.bg-danger, .card.bg-warning, .card.bg-info, .api-card, .floating-sidebar-btn .btn-float'
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
