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
$checks['curl_ext'] = [ 'name' => 'cURL 扩展', 'required' => '已开启', 'current' => extension_loaded('curl') ? '已开启' : '未开启', 'status' => extension_loaded('curl'), 'help' => '用于调用外部 API（如在线更新、邮件 SMTP、推送），必须开启。'];
$checks['openssl_ext'] = [ 'name' => 'OpenSSL 扩展', 'required' => '已开启', 'current' => extension_loaded('openssl') ? '已开启' : '未开启', 'status' => extension_loaded('openssl'), 'help' => '用于 HTTPS 通信与加密解密，在线更新与推送均依赖。'];
$checks['mbstring_ext'] = [ 'name' => 'Mbstring 扩展', 'required' => '已开启', 'current' => extension_loaded('mbstring') ? '已开启' : '未开启', 'status' => extension_loaded('mbstring'), 'help' => '用于多字节字符串处理（中文用户名、邮件主题等），强烈建议开启。'];
$checks['fileinfo_ext'] = [ 'name' => 'Fileinfo 扩展', 'required' => '已开启', 'current' => extension_loaded('fileinfo') ? '已开启' : '未开启', 'status' => extension_loaded('fileinfo'), 'help' => '用于检测上传文件真实类型（防止伪造扩展名），建议开启。'];
$checks['json_ext'] = [ 'name' => 'JSON 扩展', 'required' => '已开启', 'current' => extension_loaded('json') ? '已开启' : '未开启', 'status' => extension_loaded('json'), 'help' => 'PHP 8 已内置，用于 API 返回与解析；缺失将导致系统完全不可用。'];
$checks['redis_client'] = [ 'name' => 'Redis 客户端', 'required' => '可选', 'current' => class_exists('Redis') ? '已安装' : '未安装', 'status' => class_exists('Redis'), 'help' => '可选；启用后可使用 Redis 做速率限制、缓存与会话存储。'];
$mem_limit = (int)ini_get('memory_limit');
$mem_mb = ($mem_limit > 0 && $mem_limit !== -1) ? $mem_limit : 0;
$checks['memory_limit'] = [ 'name' => 'PHP 内存上限', 'required' => '>= 128M', 'current' => $mem_mb > 0 ? $mem_mb . 'M' : '未限制', 'status' => $mem_mb >= 128, 'help' => '推荐 ≥128M，用于在线更新与大批量数据处理。'];
$up_size = (int)ini_get('upload_max_filesize');
$checks['upload_max_filesize'] = [ 'name' => '上传文件上限', 'required' => '>= 10M', 'current' => $up_size > 0 ? $up_size . 'M' : '未限制', 'status' => $up_size >= 10, 'help' => '推荐 ≥10M，影响头像、附件、批量导入等接口。'];
$post_size = (int)ini_get('post_max_size');
$checks['post_max_size'] = [ 'name' => 'POST 数据上限', 'required' => '>= 10M', 'current' => $post_size > 0 ? $post_size . 'M' : '未限制', 'status' => $post_size >= 10, 'help' => '推荐 ≥10M，必须大于 upload_max_filesize 才能正常接收上传。'];
$exec_time = (int)ini_get('max_execution_time');
$checks['max_execution_time'] = [ 'name' => '脚本最大执行时间', 'required' => '>= 30s', 'current' => $exec_time > 0 ? $exec_time . ' 秒' : '未限制', 'status' => $exec_time === 0 || $exec_time >= 30, 'help' => '推荐 ≥30 秒；过短会导致在线更新/导出任务中断。'];
$disk_free = @disk_free_space(dirname(__FILE__, 2));
$disk_total = @disk_total_space(dirname(__FILE__, 2));
$disk_free_gb = $disk_free !== false ? round($disk_free / 1024 / 1024 / 1024, 2) : 0;
$disk_total_gb = $disk_total !== false ? round($disk_total / 1024 / 1024 / 1024, 2) : 0;
$checks['disk_space'] = [ 'name' => '磁盘剩余空间', 'required' => '>= 1 GB', 'current' => $disk_total_gb > 0 ? "剩余 {$disk_free_gb} GB / 总 {$disk_total_gb} GB" : '无法获取', 'status' => $disk_free_gb >= 1, 'help' => '推荐 ≥1 GB；在线更新需要解压空间，日志也会持续写入。'];
$tz = date_default_timezone_get();
$tz_ok = in_array($tz, ['Asia/Shanghai', 'Asia/Hong_Kong', 'Asia/Tokyo', 'Asia/Singapore', 'UTC'], true);
$checks['timezone'] = [ 'name' => 'PHP 时区', 'required' => 'Asia/Shanghai', 'current' => $tz, 'status' => $tz_ok, 'help' => '推荐设为 Asia/Shanghai，否则日志/订单时间会偏差。'];
$checks['ssl_verify'] = [ 'name' => 'HTTPS CA 证书', 'required' => '可用', 'current' => function_exists('curl_version') ? '已加载' : '缺失', 'status' => function_exists('curl_version') && curl_version()['features'] & CURL_VERSION_SSL, 'help' => '用于访问 GitHub/推送服务；缺失会导致在线更新失败。'];
$api_dir = '../API'; if (!is_dir($api_dir)) { @mkdir($api_dir, 0755, true); }
$checks['api_writable'] = [ 'name' => '/API 目录可写', 'required' => '可写', 'current' => is_writable($api_dir) ? '可写' : '不可写', 'status' => is_writable($api_dir), 'help' => '后台创建/编辑接口时，需要写入PHP文件到此目录。'];

$update_api_url = 'https://api.github.com/repos/' . (defined('SENLIN_CLIENT_REPO') ? SENLIN_CLIENT_REPO : 'huliaiya/huliapi') . '/commits/' . rawurlencode(defined('SENLIN_CLIENT_UPDATE_BRANCH') ? SENLIN_CLIENT_UPDATE_BRANCH : (defined('SENLIN_CLIENT_REPO_BRANCH') ? SENLIN_CLIENT_REPO_BRANCH : 'main')) . '?per_page=1';
$ping_start = microtime(true);
$upd_ch = curl_init();
curl_setopt($upd_ch, CURLOPT_URL, $update_api_url);
curl_setopt($upd_ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($upd_ch, CURLOPT_NOBODY, false);
curl_setopt($upd_ch, CURLOPT_USERAGENT, 'huliapi-systemcheck');
curl_setopt($upd_ch, CURLOPT_HTTPHEADER, ['Accept: application/vnd.github+json']);
curl_setopt($upd_ch, CURLOPT_CONNECTTIMEOUT, 8);
curl_setopt($upd_ch, CURLOPT_TIMEOUT, 12);
curl_setopt($upd_ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($upd_ch, CURLOPT_SSL_VERIFYPEER, true);
curl_exec($upd_ch);
$upd_ping_ms = round((microtime(true) - $ping_start) * 1000, 2);
$upd_curl_errno = curl_errno($upd_ch);
$upd_curl_err = curl_error($upd_ch);
$upd_http_code = curl_getinfo($upd_ch, CURLINFO_HTTP_CODE);
curl_close($upd_ch);
if ($upd_curl_errno) {
    $upd_status = false;
    $upd_current = '连接失败: ' . $upd_curl_err . '（延迟 N/A）';
} elseif ($upd_http_code >= 200 && $upd_http_code < 400) {
    $upd_status = true;
    $upd_current = '延迟 ' . $upd_ping_ms . ' ms (HTTP ' . $upd_http_code . ')';
} else {
    $upd_status = false;
    $upd_current = '延迟 ' . $upd_ping_ms . ' ms，服务器返回状态码: ' . $upd_http_code;
}
$checks['update_branch_latency'] = ['name' => '更新分支连接延迟', 'required' => '可连接', 'current' => $upd_current, 'status' => $upd_status, 'help' => '测量到 GitHub 更新分支 ' . htmlspecialchars(defined('SENLIN_CLIENT_UPDATE_BRANCH') ? SENLIN_CLIENT_UPDATE_BRANCH : 'main') . ' 的 HEAD 提交接口的连接耗时。'];
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