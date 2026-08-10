<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
if (file_exists('../config.php')) { require_once '../config.php'; } else { die("出现错误！配置文件丢失。"); }
$username = htmlspecialchars($_SESSION['admin_username']);
$page_title = '系统环境检测';
$current_page = basename($_SERVER['PHP_SELF']);
$checks = [];
$checks['php_version'] = [ 'name' => 'PHP 版本', 'required' => '>= 8.0.0', 'current' => PHP_VERSION, 'status' => version_compare(PHP_VERSION, '8.0.0', '>='), 'help' => '系统推荐使用PHP 8.0或更高版本。'];
$checks['pdo_mysql'] = [ 'name' => 'PDO MySQL 扩展', 'required' => '已开启', 'current' => extension_loaded('pdo_mysql') ? '已开启' : '未开启', 'status' => extension_loaded('pdo_mysql'), 'help' => '用于数据库连接，是系统运行的必要扩展。'];
$checks['gd_library'] = [ 'name' => 'GD 图形库', 'required' => '已安装', 'current' => extension_loaded('gd') && function_exists('gd_info') ? '已安装' : '未安装', 'status' => extension_loaded('gd') && function_exists('gd_info'), 'help' => '用于生成图片验证码等图形功能，必须安装。'];
$checks['zip_archive'] = [ 'name' => 'ZipArchive 类', 'required' => '可用', 'current' => class_exists('ZipArchive') ? '可用' : '不可用', 'status' => class_exists('ZipArchive'), 'help' => '用于在线更新时解压文件，必须可用。'];
$api_dir = '../API'; if (!is_dir($api_dir)) { @mkdir($api_dir, 0755, true); }
$checks['api_writable'] = [ 'name' => '/API 目录可写', 'required' => '可写', 'current' => is_writable($api_dir) ? '可写' : '不可写', 'status' => is_writable($api_dir), 'help' => '后台创建/编辑接口时，需要写入PHP文件到此目录。'];
$update_server_url = 'xiaoqi.icofun.cn/updates/api.php';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $update_server_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_exec($ch);
if (curl_errno($ch)) {
    $update_check_status = false;
    $update_check_current = '连接失败: ' . curl_error($ch);
} else {
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($http_code == 200) {
        $update_check_status = true;
        $update_check_current = '连接成功 (HTTP ' . $http_code . ')';
    } else {
        $update_check_status = false;
        $update_check_current = '连接成功，但服务器返回错误状态码: ' . $http_code;
    }
}
curl_close($ch);
$checks['update_server'] = ['name' => '更新服务器连通性', 'required' => '可连接', 'current' => $update_check_current, 'status' => $update_check_status, 'help' => '检测系统能否连接到更新源服务器以获取新版本信息。'];
?>

<!DOCTYPE html>
<html lang="zh">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-touch-fullscreen" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<link rel="stylesheet" type="text/css" href="../assets/css/materialdesignicons.min.css">
<link rel="stylesheet" type="text/css" href="../assets/css/bootstrap.min.css">
<link rel="stylesheet" type="text/css" href="../assets/css/style.min.css">
</head>
<body>
<div class="container-fluid">
  <div class="row">
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold">系统环境检测</h2>
        </div>        
        <div class="card shadow-sm mb-4">
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php foreach($checks as $check): ?>
                    <li class="list-group-item d-flex align-items-center py-3 px-4">
                        <div class="me-3">
                            <?php if($check['status']): ?>
                                <i class="bi bi-check-circle-fill text-success fs-4"></i>
                            <?php else: ?>
                                <i class="bi bi-x-circle-fill text-danger fs-4"></i>
                            <?php endif; ?>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="mb-1 fw-bold"><?php echo $check['name']; ?></h5>
                            <p class="mb-0 text-muted small"><?php echo $check['help']; ?></p>
                        </div>
                        <div class="ms-3">
                            <span class="fw-bold <?php echo $check['status'] ? 'text-success' : 'text-danger'; ?>">
                                <?php echo $check['current']; ?>
                            </span>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <div class="alert alert-info">
            <h5 class="alert-heading">检测说明</h5>
            <p class="mb-0">所有检测项必须通过才能确保系统正常运行。如有未通过的检测项，请根据提示进行相应调整。</p>
        </div>
    </div>
</div>
</div>
<script type="text/javascript" src="../assets/js/jquery.min.js"></script>
<script type="text/javascript" src="../assets/js/popper.min.js"></script>
<script type="text/javascript" src="../assets/js/bootstrap.min.js"></script>
</body>
</html>