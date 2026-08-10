<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
@set_time_limit(0);
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
if (file_exists('../config.php')) { require_once '../config.php'; } else { die("出现错误！配置文件丢失。"); }
if (file_exists('../common/version.php')) { require_once '../common/version.php'; } else { define('SENLIN_CLIENT_VERSION', '0.0.0'); }
require_once '../common/github_update.php';

function huli_api($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function huli_find_root($dir) {
    $entries = scandir($dir);
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') { continue; }
        $p = $dir . '/' . $entry;
        if (is_dir($p) && file_exists($p . '/index.php') && is_dir($p . '/admin')) {
            return $p;
        }
    }
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..' || !is_dir($dir . '/' . $entry)) { continue; }
        return $dir . '/' . $entry;
    }
    return $dir;
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

function huli_download($url, $dest) {
    $fp = fopen($dest, 'w+');
    if (!$fp) { throw new Exception('无法创建临时文件，请检查临时目录权限。'); }
    $ch = curl_init(str_replace(' ', '%20', $url));
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'huliapi-updater');
    if (!curl_exec($ch)) {
        $err = curl_error($ch);
        curl_close($ch);
        fclose($fp);
        throw new Exception('下载更新包失败: ' . $err);
    }
    curl_close($ch);
    fclose($fp);
}

function huli_api_check() {
    $info = huli_detect_update_info();
    if (!$info) { huli_api(['success' => false, 'message' => '无法从 GitHub 获取更新信息。']); }
    huli_api([
        'success' => true,
        'current_version' => SENLIN_CLIENT_VERSION,
        'current_date' => defined('SENLIN_CLIENT_RELEASE_DATE') ? SENLIN_CLIENT_RELEASE_DATE : '',
        'info' => $info,
        'update_available' => !empty($info['update_available']),
    ]);
}

function huli_api_prepare() {
    $info = huli_detect_update_info();
    if (!$info) { huli_api(['success' => false, 'message' => '无法获取更新信息。']); }
    if (empty($info['update_available'])) { huli_api(['success' => false, 'message' => '已经是最新版本，无需更新。']); }
    $zip = rtrim(sys_get_temp_dir(), '/') . '/huli_update_' . uniqid() . '.zip';
    $extract = rtrim(sys_get_temp_dir(), '/') . '/huli_extract_' . uniqid();
    try {
        huli_download($info['download_url'], $zip);
        if (!class_exists('ZipArchive')) { throw new Exception('服务器不支持ZipArchive，无法解压。请安装php-zip扩展。'); }
        $za = new ZipArchive;
        if ($za->open($zip) !== true) { throw new Exception('无法打开更新包文件。'); }
        if (!@mkdir($extract, 0755, true)) { throw new Exception('无法创建临时解压目录。'); }
        $za->extractTo($extract);
        $za->close();
    } catch (Exception $e) {
        @unlink($zip);
        huli_api(['success' => false, 'message' => $e->getMessage()]);
    }
    $_SESSION['huli_update_zip'] = $zip;
    $_SESSION['huli_update_extract'] = $extract;
    $_SESSION['huli_update_info'] = $info;
    huli_api(['success' => true, 'message' => '更新包已下载并解压完成。', 'version' => $info['version']]);
}

function huli_api_apply() {
    if (empty($_SESSION['huli_update_extract']) || empty($_SESSION['huli_update_info'])) {
        huli_api(['success' => false, 'message' => '更新包状态已丢失，请刷新页面后重新更新。']);
    }
    $extract = $_SESSION['huli_update_extract'];
    $info = $_SESSION['huli_update_info'];
    $root = huli_find_root($extract);
    $target = dirname(__FILE__, 2);
    $admin_path = defined('ADMIN_PATH') && ADMIN_PATH !== '' ? ADMIN_PATH : 'admin';
    $admin_redirect = ($admin_path !== 'admin');
    $protected_files = ['config.php', 'install.lock', 'admin/fanghong_switch.txt'];
    try {
        $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iter as $item) {
            $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));
            if ($relative === '') { continue; }
            if (in_array($relative, $protected_files, true)) { continue; }
            if (strpos($relative, 'install/') === 0) { continue; }
            if ($admin_redirect && ($relative === 'admin' || strpos($relative, 'admin/') === 0)) {
                $relative = $admin_path . substr($relative, 5);
            }
            $dest = $target . '/' . $relative;
            if ($item->isDir()) {
                if (!is_dir($dest)) { @mkdir($dest, 0755, true); }
                continue;
            }
            @mkdir(dirname($dest), 0755, true);
            if (!copy($item->getPathname(), $dest)) { throw new Exception('复制文件失败: ' . $relative); }
        }
        $repo = defined('SENLIN_CLIENT_REPO') ? SENLIN_CLIENT_REPO : 'huliaiya/huliapi';
        $repo_branch = defined('SENLIN_CLIENT_REPO_BRANCH') ? SENLIN_CLIENT_REPO_BRANCH : 'main';
        $update_branch = defined('SENLIN_CLIENT_UPDATE_BRANCH') ? SENLIN_CLIENT_UPDATE_BRANCH : 'miao';
        $new_version_content = "<?php\ndefine('SENLIN_CLIENT_VERSION', '" . addslashes($info['version']) . "');\n";
        if (!empty($info['published_at'])) {
            $new_version_content .= "define('SENLIN_CLIENT_RELEASE_DATE', '" . addslashes(date('Y-m-d', strtotime($info['published_at']))) . "');\n";
        }
        $new_version_content .= "define('SENLIN_CLIENT_REPO', '" . addslashes($repo) . "');\ndefine('SENLIN_CLIENT_REPO_BRANCH', '" . addslashes($repo_branch) . "');\ndefine('SENLIN_CLIENT_UPDATE_BRANCH', '" . addslashes($update_branch) . "');\n?>";
        if (file_put_contents($target . '/common/version.php', $new_version_content) === false) {
            throw new Exception('无法自动更新本地版本号文件，请检查 /common/version.php 文件的权限。');
        }
        if (function_exists('opcache_invalidate')) { opcache_invalidate($target . '/common/version.php', true); }
    } catch (Exception $e) {
        huli_api(['success' => false, 'message' => '更新过程中发生错误: ' . $e->getMessage()]);
    } finally {
        if (!empty($_SESSION['huli_update_zip'])) { @unlink($_SESSION['huli_update_zip']); }
        huli_rrmdir($extract);
        unset($_SESSION['huli_update_zip'], $_SESSION['huli_update_extract'], $_SESSION['huli_update_info']);
    }
    $admin_path_changed = $admin_redirect;
    $admin_msg = '';
    if ($admin_path_changed) {
        $admin_msg = '检测到您的后台目录为 /' . $admin_path . '/，本次更新已自动将后台代码更新到该目录，无需手动处理。';
    }
    huli_api([
        'success' => true,
        'message' => '系统已成功更新到版本 ' . $info['version'] . '！',
        'version' => $info['version'],
        'admin_path_changed' => $admin_path_changed,
        'admin_path' => $admin_path,
        'admin_msg' => $admin_msg,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'check') { huli_api_check(); }
    elseif ($_POST['action'] === 'prepare') { huli_api_prepare(); }
    elseif ($_POST['action'] === 'apply') { huli_api_apply(); }
    huli_api(['success' => false, 'message' => '未知操作。']);
}
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
            <div class="col-auto">
                <button type="button" class="btn btn-outline-secondary" id="btn-recheck"><i class="mdi mdi-refresh"></i> 重新检测</button>
            </div>
        </div>
        <div id="feedback-box"></div>
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card version-card h-100">
                    <div class="card-body">
                        <div class="row text-center py-3">
                            <div class="col">
                                <h5 class="text-muted">当前版本</h5>
                                <p class="version-number" id="cur-version"><?php echo SENLIN_CLIENT_VERSION; ?></p>
                                <small class="text-muted" id="cur-date"><?php echo defined('SENLIN_CLIENT_RELEASE_DATE') ? SENLIN_CLIENT_RELEASE_DATE : ''; ?></small>
                            </div>
                            <div class="col">
                                <h5 class="text-muted">最新版本</h5>
                                <p class="version-number" id="new-version">检测中...</p>
                                <small class="text-muted" id="new-date"></small>
                            </div>
                        </div>
                        <p class="text-muted small mb-3" id="update-source"></p>
                        <button type="button" id="update-btn" class="btn btn-danger w-100 py-2" disabled>
                            <i class="mdi mdi-download"></i> <span id="update-btn-text">检测中...</span>
                        </button>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card version-card h-100">
                    <div class="card-body">
                        <h5 class="card-title mb-3">更新说明</h5>
                        <div class="changelog" id="changelog">
                            <p class="text-muted">正在获取更新说明...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
  </div>
</div>

<div class="modal fade" id="progress-modal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="mdi mdi-progress-download"></i> 正在更新系统</h5>
      </div>
      <div class="modal-body">
        <p class="mb-3" id="progress-text">准备更新...</p>
        <div class="progress" style="height: 22px;">
          <div class="progress-bar progress-bar-striped progress-bar-animated" id="progress-bar" role="progressbar" style="width: 0%;">0%</div>
        </div>
        <p class="text-muted small mt-3 mb-0">更新期间请勿关闭页面。</p>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="result-modal" data-bs-backdrop="static" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="result-title">更新完成</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="result-body"></div>
      <div class="modal-footer">
        <a href="main.php" class="btn btn-primary">返回后台首页</a>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">关闭</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script type="text/javascript" src="../assets/js/jquery.min.js"></script>
<script type="text/javascript" src="../assets/js/popper.min.js"></script>
<script type="text/javascript" src="../assets/js/bootstrap.min.js"></script>
<script>
var updateAvailable = false;
var updateVersion = '';

function showFeedback(type, message) {
  $('#feedback-box').html('<div class="alert alert-' + type + ' alert-dismissible fade show mb-4">' + message +
    '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>');
}

function refreshCheck() {
  $.post('update.php', {action: 'check'}, function(res) {
    if (!res.success) {
      showFeedback('danger', res.message || '检测更新失败');
      $('#new-version').text('N/A');
      $('#update-btn').prop('disabled', true).find('span').text('检测失败');
      return;
    }
    var info = res.info || {};
    updateAvailable = res.update_available;
    updateVersion = info.version || '';
    $('#cur-version').text(res.current_version);
    $('#cur-date').text(res.current_date || '');
    $('#new-version').text(info.version || 'N/A');
    $('#new-date').text(info.published_at_human || '');
    var sourceText = '';
    if (info.source) {
      sourceText = '<i class="mdi mdi-source-branch"></i> 来源: ' + (info.source === 'release' ? 'GitHub Release' : 'GitHub 分支') +
        (info.repo ? ' / ' + info.repo : '');
    }
    $('#update-source').html(sourceText);
    if (info.body && info.body.length) {
      $('#changelog').html('<pre class="bg-light p-3 rounded" style="white-space: pre-wrap;">' + $('<div>').text(info.body).html() + '</pre>');
    } else if (info.name) {
      $('#changelog').html('<p>' + $('<div>').text(info.name).html() + '</p>');
    } else {
      $('#changelog').html('<p class="text-muted">暂无更新说明。</p>');
    }
    if (updateAvailable) {
      $('#update-btn').removeClass('btn-primary').addClass('btn-danger').prop('disabled', false);
      $('#update-btn-text').text('立即更新到 v' + updateVersion);
    } else {
      $('#update-btn').removeClass('btn-danger').addClass('btn-primary').prop('disabled', true);
      $('#update-btn-text').text('已是最新版本');
    }
  }).fail(function() {
    showFeedback('danger', '检测更新请求失败，请检查服务器网络。');
    $('#new-version').text('N/A');
    $('#update-btn').prop('disabled', true).find('span').text('检测失败');
  });
}

function setProgress(percent, text) {
  $('#progress-bar').css('width', percent + '%').text(percent + '%');
  if (text) { $('#progress-text').text(text); }
}

function showResultModal(title, html, type) {
  $('#result-title').html((type === 'error' ? '<i class="mdi mdi-alert-circle text-danger"></i> ' : '<i class="mdi mdi-check-circle text-success"></i> ') + title);
  $('#result-body').html(html);
  new bootstrap.Modal($('#result-modal')).show();
}

$('#update-btn').on('click', function() {
  if (!updateAvailable) { return; }
  if (!confirm('确定要更新到版本 v' + updateVersion + ' 吗？更新过程请勿关闭页面。')) { return; }
  $(this).prop('disabled', true);
  var progressModal = new bootstrap.Modal($('#progress-modal'));
  progressModal.show();
  setProgress(10, '正在下载更新包...');
  $.post('update.php', {action: 'prepare'}, function(res) {
    if (!res.success) {
      progressModal.hide();
      $(this).prop('disabled', false);
      showResultModal('更新失败', '<div class="alert alert-danger mb-0">' + $('<div>').text(res.message).html() + '</div>', 'error');
      return;
    }
    setProgress(60, '正在解压并应用更新文件...');
    $.post('update.php', {action: 'apply'}, function(res2) {
      if (!res2.success) {
        progressModal.hide();
        $('#update-btn').prop('disabled', false);
        showResultModal('更新失败', '<div class="alert alert-danger mb-0">' + $('<div>').text(res2.message).html() + '</div>', 'error');
        return;
      }
      setProgress(100, '更新完成');
      setTimeout(function() {
        progressModal.hide();
        var html = '<div class="alert alert-success mb-3">' + $('<div>').text(res2.message).html() + '</div>';
        if (res2.admin_path_changed && res2.admin_msg) {
          html += '<div class="alert alert-success mb-3"><i class="mdi mdi-check-circle"></i> <strong>后台目录已自动更新：</strong><br>' +
            $('<div>').text(res2.admin_msg).html() +
            '<div class="mt-2 small">当前后台地址：<a href="../' + res2.admin_path + '/" target="_blank">/' + res2.admin_path + '/</a></div></div>';
        }
        showResultModal('更新完成', html, 'success');
        refreshCheck();
      }, 600);
    }).fail(function() {
      progressModal.hide();
      showResultModal('更新失败', '<div class="alert alert-danger mb-0">应用更新请求失败，请检查服务器状态。</div>', 'error');
    });
  }).fail(function() {
    progressModal.hide();
    $('#update-btn').prop('disabled', false);
    showResultModal('更新失败', '<div class="alert alert-danger mb-0">下载更新包请求失败，请检查服务器网络。</div>', 'error');
  });
});

$('#btn-recheck').on('click', function() {
  refreshCheck();
});

$(document).ready(function() {
  refreshCheck();
});
</script>
</body>
</html>
