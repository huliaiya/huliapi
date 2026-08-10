<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
@set_time_limit(0);
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
if (file_exists('../config.php')) { require_once '../config.php'; } else { die("出现错误！配置文件丢失。"); }
if (file_exists('../common/version.php')) { require_once '../common/version.php'; } else { define('SENLIN_CLIENT_VERSION', '0.0.0'); }
require_once '../common/github_update.php';
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
        $update_info = huli_detect_update_info();
        if (!$update_info) { throw new Exception('无法从 GitHub 获取更新信息。'); }
        $update_available = !empty($update_info['update_available']);
        if (isset($_POST['action']) && $_POST['action'] === 'check') {
            $feedback_msg = '已成功从 GitHub 获取最新版本信息。';
            $feedback_type = 'success';
        }
    } catch (Exception $e) {
        $feedback_msg = '检测更新失败: ' . $e->getMessage();
        $feedback_type = 'error';
    }
}

function run_update() {
    global $feedback_msg, $feedback_type, $update_info;
    try {
        $update_info = huli_detect_update_info();
        if (!$update_info || empty($update_info['download_url'])) { throw new Exception('无法获取更新包信息。'); }
        if (empty($update_info['update_available'])) { $feedback_msg = '已经是最新版本，无需更新。'; $feedback_type = 'success'; return; }
        $download_url = $update_info['download_url'];
        $temp_zip_path = rtrim(sys_get_temp_dir(), '/') . '/update_package_' . uniqid() . '.zip';
        $extract_path = dirname(__FILE__, 2);
        $fp = fopen($temp_zip_path, 'w+');
        if (!$fp) { throw new Exception('无法创建临时文件，请检查临时目录权限。'); }
        $ch = curl_init(str_replace(' ', '%20', $download_url));
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        if (!curl_exec($ch)) { throw new Exception('下载更新包失败: ' . curl_error($ch)); }
        curl_close($ch);
        fclose($fp);
        if (!class_exists('ZipArchive')) { throw new Exception('服务器不支持ZipArchive，无法解压。请安装php-zip扩展。'); }
        $zip = new ZipArchive;
        if ($zip->open($temp_zip_path) !== TRUE) { throw new Exception('无法打开更新包文件。'); }
        $temp_extract = sys_get_temp_dir() . '/huli_extract_' . uniqid();
        @mkdir($temp_extract, 0755, true);
        $zip->extractTo($temp_extract);
        $zip->close();
        $extracted_root = $temp_extract;
        $entries = scandir($temp_extract);
        foreach ($entries as $entry) {
            if ($entry !== '.' && $entry !== '..' && is_dir($temp_extract . '/' . $entry)) {
                $extracted_root = $temp_extract . '/' . $entry;
                break;
            }
        }
        $protected_files = ['config.php', 'install.lock', 'admin/fanghong_switch.txt'];
        $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($extracted_root, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iter as $item) {
            $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($extracted_root) + 1));
            $target = $extract_path . '/' . $relative;
            if (in_array($relative, $protected_files, true)) { continue; }
            if (strpos($relative, 'install/') === 0) { continue; }
            if ($item->isDir()) {
                if (!is_dir($target)) { @mkdir($target, 0755, true); }
                continue;
            }
            @mkdir(dirname($target), 0755, true);
            if (!copy($item->getPathname(), $target)) { throw new Exception('复制文件失败: ' . $relative); }
        }
        $repo = defined('SENLIN_CLIENT_REPO') ? SENLIN_CLIENT_REPO : 'huliaiya/huliapi';
        $repo_branch = defined('SENLIN_CLIENT_REPO_BRANCH') ? SENLIN_CLIENT_REPO_BRANCH : 'main';
        $update_branch = defined('SENLIN_CLIENT_UPDATE_BRANCH') ? SENLIN_CLIENT_UPDATE_BRANCH : 'miao';
        $version_file_path = $extract_path . '/common/version.php';
        $new_version_content = "<?php\ndefine('SENLIN_CLIENT_VERSION', '" . addslashes($update_info['version']) . "');\n";
        if (!empty($update_info['published_at'])) {
            $new_version_content .= "define('SENLIN_CLIENT_RELEASE_DATE', '" . addslashes(date('Y-m-d', strtotime($update_info['published_at']))) . "');\n";
        }
        $new_version_content .= "define('SENLIN_CLIENT_REPO', '" . addslashes($repo) . "');\ndefine('SENLIN_CLIENT_REPO_BRANCH', '" . addslashes($repo_branch) . "');\ndefine('SENLIN_CLIENT_UPDATE_BRANCH', '" . addslashes($update_branch) . "');\n";
        $new_version_content .= "?>";
        if (file_put_contents($version_file_path, $new_version_content) === false) {
            throw new Exception('无法自动更新本地版本号文件，请检查 /common/version.php 文件的权限。');
        }
        if (function_exists('opcache_invalidate')) { opcache_invalidate($version_file_path, true); }
        huli_rrmdir($temp_extract);
        $feedback_msg = '系统已成功更新到版本 ' . $update_info['version'] . '！';
        $feedback_type = 'success';
    } catch (Exception $e) {
        $feedback_msg = '更新过程中发生错误: ' . $e->getMessage();
        $feedback_type = 'error';
    } finally {
        if (!empty($temp_zip_path) && file_exists($temp_zip_path)) { @unlink($temp_zip_path); }
    }
}

function huli_rrmdir($dir) {
    if (!is_dir($dir)) { return; }
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') { continue; }
        $path = $dir . '/' . $item;
        if (is_dir($path)) { huli_rrmdir($path); } else { @unlink($path); }
    }
    @rmdir($dir);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update') { run_update(); }
}
check_for_updates();
?>

<!DOCTYPE html>
<html lang="zh">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
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
                <p class="text-muted mb-0">通过 GitHub 仓库自动检测最新版本和最近提交时间</p>
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
                <div class="card version-card h-100">
                    <div class="card-body">
                        <div class="row text-center py-3">
                            <div class="col">
                                <h5 class="text-muted">当前版本</h5>
                                <p class="version-number"><?php echo SENLIN_CLIENT_VERSION; ?></p>
                                <?php if (defined('SENLIN_CLIENT_RELEASE_DATE')): ?>
                                <small class="text-muted"><?php echo SENLIN_CLIENT_RELEASE_DATE; ?></small>
                                <?php endif; ?>
                            </div>
                            <div class="col">
                                <h5 class="text-muted">最新版本</h5>
                                <p class="version-number"><?php echo htmlspecialchars($update_info['version'] ?? 'N/A'); ?></p>
                                <small class="text-muted"><?php echo htmlspecialchars($update_info['published_at_human'] ?? ''); ?></small>
                            </div>
                        </div>
                        <?php if (!empty($update_info['source'])): ?>
                        <p class="text-muted small mb-3">
                            <i class="mdi mdi-source-branch"></i> 来源: <?php echo $update_info['source'] === 'release' ? 'GitHub Release' : 'GitHub 分支'; ?>
                            <?php if (defined('SENLIN_CLIENT_REPO')): ?>
                            / <?php echo htmlspecialchars(SENLIN_CLIENT_REPO); ?>
                            <?php endif; ?>
                        </p>
                        <?php endif; ?>
                        <form action="update.php" method="POST" onsubmit="return confirm('确定要更新到版本 <?php echo htmlspecialchars($update_info['version'] ?? ''); ?> 吗？更新过程请勿关闭页面。')">
                            <input type="hidden" name="action" value="update">
                            <button type="submit" class="btn <?php echo $update_available ? 'btn-danger' : 'btn-primary'; ?> w-100 py-2" <?php if(!$update_available) echo 'disabled'; ?>>
                                <?php echo $update_available ? '立即更新到 v' . htmlspecialchars($update_info['version']) : '已是最新版本'; ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card version-card h-100">
                    <div class="card-body">
                        <h5 class="card-title mb-3">更新说明</h5>
                        <div class="changelog">
                            <?php if($update_info && !empty($update_info['body'])): ?>
                                <pre class="bg-light p-3 rounded" style="white-space: pre-wrap;"><?php echo htmlspecialchars($update_info['body']); ?></pre>
                            <?php elseif ($update_info && !empty($update_info['name'])): ?>
                                <p><?php echo htmlspecialchars($update_info['name']); ?></p>
                            <?php else: ?>
                                <p class="text-muted">暂无更新说明。</p>
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
