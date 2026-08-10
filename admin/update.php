<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
@set_time_limit(0);
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
if (file_exists('../config.php')) { require_once '../config.php'; } else { die("出现错误！配置文件丢失。"); }
if (file_exists('../common/version.php')) { require_once '../common/version.php'; } else { define('SENLIN_CLIENT_VERSION', '0.0.0'); }
define('UPDATE_API_URL', 'https://xiaoqi.icofun.cn/updates/api.php');
$username = htmlspecialchars($_SESSION['admin_username']);
$page_title = '在线更新';
$current_page = basename($_SERVER['PHP_SELF']);
$feedback_msg = '';
$feedback_type = '';
$update_info = null;
$update_available = false;

function check_for_updates() {
    global $feedback_msg, $feedback_type, $update_info, $update_available;
    try {
        $post_data = [
            'url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]",
            'ip' => $_SERVER['SERVER_ADDR'] ?? gethostbyname($_SERVER['SERVER_NAME']),
            'version' => SENLIN_CLIENT_VERSION
        ];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, UPDATE_API_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        $api_response_str = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (curl_errno($ch)) { throw new Exception('cURL Error: ' . curl_error($ch)); }
        if ($http_code !== 200) { throw new Exception('更新服务器返回了非正常的HTTP状态码: ' . $http_code); }
        curl_close($ch);
        $api_response = json_decode($api_response_str, true);
        if ($api_response === null) { throw new Exception('无法解析来自更新服务器的响应。'); }
        if (!$api_response['success']) { throw new Exception($api_response['message'] ?? '从服务器获取更新信息失败。'); }
        $update_info = $api_response;
        $update_available = version_compare(SENLIN_CLIENT_VERSION, $api_response['version'], '<');
        if(isset($_POST['action']) && $_POST['action'] === 'check'){
             $feedback_msg = '已成功获取最新版本信息。';
             $feedback_type = 'success';
        }
    } catch (Exception $e) {
        $feedback_msg = '检测更新失败: ' . $e->getMessage();
        $feedback_type = 'error';
    }
}

function run_update() {
    global $feedback_msg, $feedback_type, $update_info;    
    $ch_check = curl_init();
    curl_setopt($ch_check, CURLOPT_URL, UPDATE_API_URL);
    curl_setopt($ch_check, CURLOPT_RETURNTRANSFER, true);
    $update_info_json = curl_exec($ch_check);
    curl_close($ch_check);
    $update_info = json_decode($update_info_json, true);
    if (!$update_info || !$update_info['success']) { $feedback_msg = "无法开始更新，获取更新包信息失败。"; $feedback_type = 'error'; return; }
    if (!version_compare(SENLIN_CLIENT_VERSION, $update_info['version'], '<')) { $feedback_msg = "已经是最新版本，无需更新。"; $feedback_type = 'success'; return; }
    $download_url = $update_info['download_url'];
    $temp_zip_path = rtrim(sys_get_temp_dir(), '/') . '/update_package_' . uniqid() . '.zip';
    $extract_path = dirname(__FILE__, 2);
    try {
        $fp = fopen($temp_zip_path, 'w+');
        if (!$fp) { throw new Exception('无法创建临时文件，请检查临时目录权限。'); }
        $ch = curl_init(str_replace(" ", "%20", $download_url));
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        if(!curl_exec($ch)) { throw new Exception('下载更新包失败: ' . curl_error($ch)); }
        curl_close($ch);
        fclose($fp);
        if (!class_exists('ZipArchive')) { throw new Exception('服务器不支持ZipArchive，无法解压。请安装php-zip扩展。'); }
        $zip = new ZipArchive;
        if ($zip->open($temp_zip_path) === TRUE) {
            $zip->extractTo($extract_path);
            $zip->close();
        } else {
            throw new Exception('无法打开更新包文件。');
        }
        $version_file_path = $extract_path . '/common/version.php';
        $new_version_content = "<?php\n\ndefine('SENLIN_CLIENT_VERSION', '" . $update_info['version'] . "');\n?>";
        if (file_put_contents($version_file_path, $new_version_content) === false) {
             throw new Exception("文件覆盖成功，但无法自动更新本地版本号文件，请检查 /common/version.php 文件的权限。");
        }
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($version_file_path, true);
        }
        $feedback_msg = '系统已成功更新到版本 ' . $update_info['version'] . '！';
        $feedback_type = 'success';
    } catch (Exception $e) {
        $feedback_msg = '更新过程中发生错误: ' . $e->getMessage();
        $feedback_type = 'error';
    } finally {
        if (file_exists($temp_zip_path)) {
            unlink($temp_zip_path);
        }
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update') {
        run_update();
    }
}
check_for_updates();
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
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
</head>
<body>
<div class="container-fluid">
  <div class="row">
    <div class="container py-4">
        <div class="row mb-4">
            <div class="col">
                <h2 class="fw-bold">在线更新</h2>
            </div>
        </div>
        <?php if ($feedback_msg): ?>
        <div class="alert alert-<?php echo $feedback_type; ?> alert-dismissible fade show mb-4">
            <?php echo htmlspecialchars($feedback_msg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card version-card">
                    <div class="card-body">
                        <div class="row text-center py-3">
                            <div class="col">
                                <h5 class="text-muted">当前版本</h5>
                                <p class="version-number"><?php echo SENLIN_CLIENT_VERSION; ?></p>
                            </div>
                            <div class="col">
                                <h5 class="text-muted">最新版本</h5>
                                <p class="version-number"><?php echo htmlspecialchars($update_info['version'] ?? 'N/A'); ?></p>
                            </div>
                        </div>
                        <form action="update.php" method="POST" onsubmit="if(document.getElementById('update-btn').disabled){return false;} return confirm('确定要更新到版本 <?php echo htmlspecialchars($update_info['version']); ?> 吗？更新过程请勿关闭页面。')">
                            <input type="hidden" name="action" value="update">
                            <button type="submit" id="update-btn" class="btn <?php echo $update_available ? 'btn-danger' : 'btn-primary'; ?> w-100 py-2" <?php if(!$update_available) echo 'disabled'; ?>>
                                <?php echo $update_available ? '立即更新到 v' . htmlspecialchars($update_info['version']) : '已是最新版本'; ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card version-card h-100">
                    <div class="card-body">
                        <h5 class="card-title mb-3">更新日志</h5>
                        <div class="changelog">
                            <?php if($update_info && !empty($update_info['changelog'])): ?>
                                <ul class="list-unstyled">
                                    <?php foreach(explode("\n", $update_info['changelog']) as $line): ?>
                                        <li><?php echo htmlspecialchars(trim($line)); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted">暂无更新日志信息。</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script type="text/javascript" src="../assets/js/jquery.min.js"></script>
<script type="text/javascript" src="../assets/js/popper.min.js"></script>
<script type="text/javascript" src="../assets/js/bootstrap.min.js"></script>
</body>
</html>