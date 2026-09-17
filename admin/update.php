<?php
require_once __DIR__ . '/../common/session_boot.php';
@error_reporting(0);
@ini_set('display_errors', 'Off');
@set_time_limit(0);
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
if (file_exists('../config.php')) { require_once '../config.php'; } else { die("出现错误！配置文件丢失。"); }
if (file_exists('../common/version.php')) { require_once '../common/version.php'; } else { define('SENLIN_CLIENT_VERSION', '0.0.0'); }
require_once '../common/github_update.php';
require_once '../common/updater.php';

function huli_api($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function huli_emit_json($data) {
    if (!headers_sent()) { header('Content-Type: application/json; charset=utf-8'); }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    while (ob_get_level() > 0) { @ob_end_flush(); }
    @flush();
}

function huli_site_url() {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host === '') { return ''; }
    return $scheme . '://' . $host . '/';
}

function huli_api_check() {
    $info = huli_detect_update_info();
    $stored_token = isset($_SESSION['huli_update_token']) ? huli_updater_safe_token($_SESSION['huli_update_token']) : '';
    $task = $stored_token !== '' ? huli_updater_read_status($stored_token) : null;
    if (!$info) {
        huli_api(['success' => false, 'message' => '无法从 GitHub 获取更新信息。', 'task' => $task]);
    }
    huli_api([
        'success' => true,
        'current_version' => SENLIN_CLIENT_VERSION,
        'current_date' => defined('SENLIN_CLIENT_RELEASE_DATE') ? SENLIN_CLIENT_RELEASE_DATE : '',
        'info' => $info,
        'update_available' => !empty($info['update_available']),
        'task' => $task,
    ]);
}

function huli_api_status() {
    $token = isset($_POST['token']) ? $_POST['token'] : (isset($_SESSION['huli_update_token']) ? $_SESSION['huli_update_token'] : '');
    $token = huli_updater_safe_token($token);
    if ($token === '') { huli_api(['success' => false, 'message' => '没有正在进行的更新任务。']); }
    $status = huli_updater_read_status($token);
    if (!$status) { huli_api(['success' => false, 'message' => '未找到更新任务状态。']); }
    huli_api(['success' => true, 'status' => $status]);
}

function huli_api_log() {
    $token = isset($_POST['token']) ? $_POST['token'] : (isset($_SESSION['huli_update_token']) ? $_SESSION['huli_update_token'] : '');
    $token = huli_updater_safe_token($token);
    if ($token === '') { huli_api(['success' => false, 'message' => '没有正在进行的更新任务。']); }
    $status = huli_updater_read_status($token);
    $lines = [];
    $log_exists = false;
    $logFile = huli_updater_worker_log_file($token);
    if (is_file($logFile)) {
        $log_exists = true;
        $raw = @file_get_contents($logFile);
        if ($raw !== false) {
            $arr = preg_split('/\r?\n/', trim($raw));
            $lines = array_slice(array_filter($arr), -200);
        }
    }
    huli_api(['success' => true, 'token' => $token, 'status' => $status, 'log' => $lines, 'log_exists' => $log_exists]);
}

function huli_api_start() {
    $info = huli_detect_update_info();
    if (!$info) { huli_api(['success' => false, 'message' => '无法获取更新信息。']); }
    if (empty($info['update_available'])) { huli_api(['success' => false, 'message' => '已经是最新版本，无需更新。']); }

    $token = huli_updater_new_token();
    $_SESSION['huli_update_token'] = $token;
    $task = [
        'info' => $info,
        'admin_id' => isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : 0,
        'site_url' => huli_site_url(),
        'created_at' => time(),
    ];
    huli_updater_write_json(huli_updater_task_file($token), $task);
    huli_updater_write_status($token, [
        'status' => 'running',
        'stage' => 'queued',
        'percent' => 1,
        'message' => '更新任务已创建，正在启动后台执行...',
        'version' => $info['version'],
        'old_version' => SENLIN_CLIENT_VERSION,
        'started_at' => time(),
        'finished_at' => 0,
        'error' => '',
    ]);

    if (function_exists('fastcgi_finish_request')) {
        @ignore_user_abort(true);
        @set_time_limit(0);
        huli_emit_json([
            'success' => true,
            'background' => true,
            'mode' => 'fastcgi',
            'token' => $token,
            'message' => '更新已在后台开始执行，可以关闭此页面，完成后会向管理员邮箱发送通知。',
        ]);
        if (function_exists('session_write_close')) { @session_write_close(); }
        @fastcgi_finish_request();
        huli_updater_run_task($token, $task, $task['site_url']);
        exit;
    }

    if (huli_updater_spawn($token)) {
        huli_api([
            'success' => true,
            'background' => true,
            'mode' => 'cli',
            'token' => $token,
            'message' => '更新已在后台开始执行，可以关闭此页面，完成后会向管理员邮箱发送通知。',
        ]);
    }

    $result = huli_updater_run_task($token, $task, $task['site_url']);
    $status = huli_updater_read_status($token);
    $result['background'] = false;
    $result['token'] = $token;
    if (is_array($status)) {
        $result['notified'] = !empty($status['notified']);
        $result['notify_message'] = isset($status['notify_message']) ? $status['notify_message'] : '';
        $result['status'] = isset($status['status']) ? $status['status'] : ($result['success'] ? 'success' : 'failed');
    }
    huli_api($result);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'check') { huli_api_check(); }
    elseif ($_POST['action'] === 'start') { huli_api_start(); }
    elseif ($_POST['action'] === 'status') { huli_api_status(); }
    elseif ($_POST['action'] === 'log') { huli_api_log(); }
    huli_api(['success' => false, 'message' => '未知操作。']);
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
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
                <p class="text-muted mb-0">通过 GitHub 仓库自动检测最新版本和最近提交时间，支持后台执行与邮件通知</p>
            </div>
            <div class="col-auto d-flex gap-2 align-items-center">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-log"><i class="mdi mdi-file-document-outline me-1"></i>查看日志</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-recheck"><i class="mdi mdi-refresh me-1"></i>重新检测</button>
            </div>
        </div>
        <div id="task-banner"></div>
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
                        <p class="text-muted small mb-0 mt-2"><i class="mdi mdi-information-outline"></i> 更新在后台执行，提交后可以关闭此页面，完成后会向管理员邮箱发送通知。</p>
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
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content update-modal">
      <div class="modal-body p-4">
        <div class="d-flex align-items-center mb-3">
          <div class="update-icon"><i class="mdi mdi-cloud-download-outline"></i></div>
          <div class="flex-grow-1">
            <h5 class="modal-title mb-0" id="progress-title">正在更新系统</h5>
            <small class="text-muted" id="progress-hint">更新在后台执行，可以关闭此页面</small>
          </div>
        </div>
        <div class="progress update-progress mb-3" style="height: 10px;">
          <div class="progress-bar" id="progress-bar" role="progressbar" style="width: 0%;"></div>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-3">
          <span class="text-muted small" id="progress-text">准备更新...</span>
          <span class="progress-percent" id="progress-percent">0%</span>
        </div>
        <ul class="update-steps">
          <li class="step" data-step="download"><span class="step-dot"></span><span class="step-label">下载更新包</span></li>
          <li class="step" data-step="extract"><span class="step-dot"></span><span class="step-label">解压文件</span></li>
          <li class="step" data-step="apply"><span class="step-dot"></span><span class="step-label">应用更新</span></li>
          <li class="step" data-step="finish"><span class="step-dot"></span><span class="step-label">完成</span></li>
        </ul>
        <div class="d-flex justify-content-end mt-3">
          <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">关闭（后台继续执行）</button>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="result-modal" data-bs-backdrop="static" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content update-modal">
      <div class="modal-body p-4">
        <div class="d-flex align-items-center mb-3">
          <div class="update-icon" id="result-icon"><i class="mdi mdi-check-circle-outline"></i></div>
          <div class="flex-grow-1">
            <h5 class="modal-title mb-0" id="result-title">更新完成</h5>
            <small class="text-muted" id="result-sub"></small>
          </div>
        </div>
        <div id="result-body"></div>
        <div class="d-flex justify-content-end gap-2 mt-3">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">关闭</button>
          <a href="main.php" class="btn btn-primary">返回后台首页</a>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="log-modal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="mdi mdi-file-document-outline me-1"></i> 更新执行日志</h5>
        <div class="d-flex align-items-center gap-2">
          <button type="button" class="btn btn-sm btn-light" id="log-refresh"><i class="mdi mdi-refresh"></i> 刷新</button>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <div class="modal-body">
        <div class="text-muted small mb-2" id="log-meta"></div>
        <pre id="log-content" class="bg-light p-3 rounded mb-0" style="max-height: 60vh; overflow: auto; white-space: pre-wrap; font-size: 13px;"></pre>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script type="text/javascript" src="../assets/js/jquery.min.js"></script>
<script type="text/javascript" src="../assets/js/popper.min.js"></script>
<script type="text/javascript" src="../assets/js/bootstrap.min.js"></script>
<style>
.update-modal.modal-content {
    background: linear-gradient(135deg, rgba(255,255,255,.88), rgba(244,249,253,.82)) !important;
    backdrop-filter: blur(24px) saturate(160%);
    -webkit-backdrop-filter: blur(24px) saturate(160%);
    border: 1px solid rgba(134, 194, 255, .35) !important;
    border-radius: 20px !important;
    box-shadow: 0 30px 60px rgba(45, 100, 155, .25), inset 0 1px 0 rgba(255,255,255,.7) !important;
    overflow: hidden;
}
body[data-theme="dark"] .update-modal.modal-content,
body.theme-dark .update-modal.modal-content {
    background: linear-gradient(135deg, rgba(28,42,58,.88), rgba(22,34,50,.82)) !important;
    border-color: rgba(134, 194, 255, .25) !important;
    color: #e6edf3;
}
.update-icon {
    width: 48px; height: 48px;
    border-radius: 14px;
    display: inline-flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, rgba(134,194,255,.25), rgba(119,222,218,.18));
    color: #4d8fd6;
    font-size: 26px;
    margin-right: 14px;
    box-shadow: inset 0 1px 0 rgba(255,255,255,.55);
}
.update-progress {
    background: rgba(134,194,255,.18) !important;
    border-radius: 999px !important;
    overflow: hidden;
    box-shadow: inset 0 1px 2px rgba(45,100,155,.12);
}
.update-progress .progress-bar {
    background: linear-gradient(90deg, #6cb6ff, #77ded9);
    border-radius: 999px;
    transition: width .35s cubic-bezier(.22,.61,.36,1);
    position: relative;
    overflow: hidden;
}
.update-progress .progress-bar::after {
    content: '';
    position: absolute; inset: 0;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,.45), transparent);
    animation: update-shimmer 1.6s linear infinite;
}
@keyframes update-shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}
.progress-percent {
    font-size: 18px;
    font-weight: 700;
    background: linear-gradient(135deg, #4d8fd6, #3fbfae);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    letter-spacing: .5px;
}
.update-steps {
    list-style: none;
    padding: 0;
    margin: 0;
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
}
.update-steps .step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    padding: 8px 4px;
    border-radius: 12px;
    background: rgba(255,255,255,.4);
    transition: background .3s ease, transform .3s ease;
}
.update-steps .step .step-dot {
    width: 10px; height: 10px;
    border-radius: 50%;
    background: rgba(134,194,255,.4);
    box-shadow: inset 0 0 0 2px rgba(255,255,255,.6);
    transition: background .3s ease, box-shadow .3s ease, transform .3s ease;
}
.update-steps .step .step-label {
    font-size: 11px;
    color: #64748b;
    font-weight: 500;
}
.update-steps .step.active {
    background: linear-gradient(135deg, rgba(134,194,255,.22), rgba(119,222,218,.16));
}
.update-steps .step.active .step-dot {
    background: linear-gradient(135deg, #6cb6ff, #77ded9);
    box-shadow: 0 0 0 4px rgba(108,182,255,.18), inset 0 0 0 2px rgba(255,255,255,.7);
    animation: step-pulse 1.2s ease-in-out infinite;
}
.update-steps .step.active .step-label {
    color: #2f78c4;
    font-weight: 600;
}
.update-steps .step.done {
    background: linear-gradient(135deg, rgba(134,194,255,.22), rgba(119,222,218,.18));
}
.update-steps .step.done .step-dot {
    background: linear-gradient(135deg, #6cb6ff, #77ded9);
    box-shadow: inset 0 0 0 2px rgba(255,255,255,.7), 0 2px 6px rgba(108,182,255,.35);
}
.update-steps .step.done .step-label {
    color: #2f78c4;
    font-weight: 600;
}
@keyframes step-pulse {
    0%, 100% { box-shadow: 0 0 0 4px rgba(108,182,255,.18), inset 0 0 0 2px rgba(255,255,255,.7); }
    50%      { box-shadow: 0 0 0 8px rgba(108,182,255,.06), inset 0 0 0 2px rgba(255,255,255,.7); }
}
.result-msg {
    background: linear-gradient(135deg, rgba(255,255,255,.55), rgba(244,249,253,.45));
    border: 1px solid rgba(134,194,255,.25);
    border-radius: 12px;
    padding: 12px 14px;
    color: #333;
    line-height: 1.7;
}
@media (prefers-reduced-motion: reduce) {
    .update-progress .progress-bar::after,
    .update-steps .step.active .step-dot {
        animation: none;
    }
}
</style>
<script>
var updateAvailable = false;
var updateVersion = '';
var pollTimer = null;

function esc(t) { return $('<div>').text(t == null ? '' : t).html(); }

function showFeedback(type, message) {
  $('#feedback-box').html('<div class="alert alert-' + type + ' alert-dismissible fade show mb-4">' + message +
    '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>');
}

function renderTaskBanner(task) {
  if (!task || !task.status) { $('#task-banner').empty(); return; }
  if (task.status === 'running') {
    if (task.stale) {
      $('#task-banner').html('<div class="alert alert-warning mb-4"><i class="mdi mdi-alert-outline me-1"></i>检测到上次更新任务似乎已中断（超过 15 分钟无进度）。您可以重新发起更新。</div>');
      return;
    }
    $('#task-banner').html('<div class="alert alert-info d-flex align-items-center mb-4">' +
      '<i class="mdi mdi-progress-clock me-2"></i>' +
      '<div class="flex-grow-1">后台更新任务正在执行（' + (task.percent || 0) + '%）' + esc(task.message || '') + ' 完成后会向管理员邮箱发送通知。</div>' +
      '<button type="button" class="btn btn-sm btn-primary ms-2" id="banner-view-progress">查看进度</button>' +
      '<button type="button" class="btn btn-sm btn-light ms-1" id="banner-view-log">查看日志</button></div>');
  } else if (task.status === 'success') {
    $('#task-banner').html('<div class="alert alert-success mb-4"><i class="mdi mdi-check-circle-outline me-1"></i>上次更新已成功完成（v' + esc(task.version || '') + '）。' + esc(task.notify_message || '') + '</div>');
  } else if (task.status === 'failed') {
    $('#task-banner').html('<div class="alert alert-danger mb-4"><i class="mdi mdi-alert-circle-outline me-1"></i>上次更新失败：' + esc(task.message || task.error || '未知错误') + '</div>');
  } else {
    $('#task-banner').empty();
  }
}

function refreshCheck() {
  $.post('update.php', {action: 'check'}, function(res) {
    if (!res.success) {
      showFeedback('danger', esc(res.message || '检测更新失败'));
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
      $('#changelog').html('<pre class="bg-light p-3 rounded" style="white-space: pre-wrap;">' + esc(info.body) + '</pre>');
    } else if (info.name) {
      $('#changelog').html('<p>' + esc(info.name) + '</p>');
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
    renderTaskBanner(res.task);
  }).fail(function() {
    showFeedback('danger', '检测更新请求失败，请检查服务器网络。');
    $('#new-version').text('N/A');
    $('#update-btn').prop('disabled', true).find('span').text('检测失败');
  });
}

function setProgress(percent, text, step) {
  var p = Math.max(0, Math.min(100, Math.round(percent)));
  $('#progress-bar').css('width', p + '%').attr('aria-valuenow', p);
  $('#progress-percent').text(p + '%');
  if (text) { $('#progress-text').text(text); }
  if (step) {
    if (step === 'queued') { step = 'download'; }
    $('.update-steps .step').removeClass('active done');
    var order = ['download', 'extract', 'apply', 'finish'];
    var idx = order.indexOf(step);
    if (idx < 0) { idx = 0; }
    for (var i = 0; i <= idx; i++) {
      $('.update-steps .step[data-step="' + order[i] + '"]').addClass(i < idx ? 'done' : 'active');
    }
  }
}

function showResultModal(title, html, type, sub) {
  var iconHtml = type === 'error'
    ? '<i class="mdi mdi-alert-circle-outline text-danger"></i>'
    : '<i class="mdi mdi-check-circle-outline text-success"></i>';
  $('#result-icon').html(iconHtml);
  $('#result-title').html(title);
  $('#result-sub').text(sub || '');
  $('#result-body').html(html);
  new bootstrap.Modal($('#result-modal')).show();
}

function statusToResult(st) {
  var r = st.result || {};
  r.success = (st.status === 'success');
  if (!r.message) { r.message = st.message; }
  if (!r.version) { r.version = st.version; }
  if (r.notified === undefined) { r.notified = st.notified; }
  if (r.notify_message === undefined) { r.notify_message = st.notify_message; }
  if (r.admin_path_changed === undefined) {
    r.admin_path_changed = st.admin_path_changed;
    r.admin_msg = st.admin_msg;
    r.admin_path = st.admin_path;
  }
  return r;
}

function renderResult(res) {
  var ok = !!res.success;
  var html = '<div class="result-msg"><i class="mdi ' + (ok ? 'mdi-check-circle text-success' : 'mdi-alert-circle text-danger') + ' me-1"></i>' + esc(res.message) + '</div>';
  if (res.admin_path_changed && res.admin_msg) {
    html += '<div class="result-msg mt-2"><i class="mdi mdi-folder-arrow-right text-primary me-1"></i><strong>后台目录已自动更新：</strong>' +
      esc(res.admin_msg) +
      '<div class="small mt-1">当前后台地址：<a href="../' + esc(res.admin_path) + '/" target="_blank">/' + esc(res.admin_path) + '/</a></div></div>';
  }
  if (res.notify_message) {
    html += '<div class="result-msg mt-2 small"><i class="mdi mdi-email-outline me-1"></i>' + esc(res.notify_message) + '</div>';
  }
  showResultModal(ok ? '更新完成' : '更新失败', html, ok ? 'success' : 'error',
    ok ? ('已成功升级到 v' + (res.version || '')) : '后台更新任务执行失败');
}

function startPolling(token, progressModal) {
  if (pollTimer) { clearInterval(pollTimer); }
  pollTimer = setInterval(function() {
    $.post('update.php', {action: 'status', token: token}, function(st) {
      if (!st.success || !st.status) { return; }
      var s = st.status;
      setProgress(s.percent != null ? s.percent : 0, s.message || '', s.stage || 'download');
      if (s.status === 'success' || s.status === 'failed') {
        clearInterval(pollTimer); pollTimer = null;
        setTimeout(function() {
          $.post('update.php', {action: 'status', token: token}, function(st2) {
            var finalStatus = (st2.success && st2.status) ? st2.status : s;
            progressModal.hide();
            renderResult(statusToResult(finalStatus));
            refreshCheck();
          }).fail(function() {
            progressModal.hide();
            renderResult(statusToResult(s));
            refreshCheck();
          });
        }, 1500);
      }
    });
  }, 2000);
}

$('#update-btn').on('click', function() {
  if (!updateAvailable) { return; }
  if (!confirm('确定要更新到版本 v' + updateVersion + ' 吗？\n更新将在后台执行，可以关闭此页面，完成后会向管理员邮箱发送通知。')) { return; }
  $(this).prop('disabled', true);
  var progressModal = new bootstrap.Modal($('#progress-modal'));
  progressModal.show();
  setProgress(3, '正在创建后台更新任务...', 'download');
  $.post('update.php', {action: 'start'}, function(res) {
    if (!res.success) {
      progressModal.hide();
      $('#update-btn').prop('disabled', false);
      renderResult({success: false, message: res.message || '启动更新失败'});
      refreshCheck();
      return;
    }
    if (res.background) {
      $('#progress-title').text('后台更新进行中');
      $('#progress-hint').text('可以关闭此页面，完成后会向管理员邮箱发送通知');
      startPolling(res.token, progressModal);
      return;
    }
    progressModal.hide();
    renderResult(res);
    refreshCheck();
  }).fail(function() {
    progressModal.hide();
    $('#update-btn').prop('disabled', false);
    renderResult({success: false, message: '启动更新请求失败，请检查服务器网络。'});
  });
});

$(document).on('click', '#banner-view-progress', function() {
  var progressModal = new bootstrap.Modal($('#progress-modal'));
  progressModal.show();
  $('#progress-title').text('后台更新进行中');
  $('#progress-hint').text('可以关闭此页面，完成后会向管理员邮箱发送通知');
  $.post('update.php', {action: 'status'}, function(st) {
    if (st.success && st.status && st.status.token) {
      var s = st.status;
      setProgress(s.percent != null ? s.percent : 0, s.message || '', s.stage || 'download');
      startPolling(s.token, progressModal);
    }
  });
});

$('#btn-recheck').on('click', function() {
  refreshCheck();
});

var logTimer = null;
function stopLog() {
  if (logTimer) { clearInterval(logTimer); logTimer = null; }
}
function loadLog(tail) {
  $.post('update.php', {action: 'log'}, function(res) {
    if (!res.success) { $('#log-meta').text(res.message || '无法读取日志'); return; }
    var st = res.status || {};
    var meta = '任务 ' + (res.token || '—');
    if (st.status) meta += '  · 状态: ' + st.status + (st.percent != null ? ' (' + st.percent + '%)' : '');
    if (st.stage) meta += ' · 阶段: ' + st.stage;
    if (st.started_at) meta += ' · 开始: ' + (new Date(st.started_at * 1000)).toLocaleString();
    if (st.message) meta += '  · ' + st.message;
    if (st.error) meta += '  · 错误: ' + st.error;
    if (st.notify_message) meta += '  · ' + st.notify_message;
    $('#log-meta').text(meta);
    var body = (res.log && res.log.length) ? res.log.join('\n')
      : (res.log_exists ? '（后台执行日志为空）' : '未找到后台执行日志文件（可能任务尚未启动）。');
    $('#log-content').text(body);
    if (tail && st.status === 'running') {
      stopLog();
      logTimer = setInterval(function() { loadLog(false); }, 2000);
    } else {
      stopLog();
    }
  }).fail(function() {
    $('#log-meta').text('读取日志请求失败，请检查服务器网络。');
    stopLog();
  });
}
function viewLog() {
  $('#log-content').text('正在读取日志...');
  $('#log-meta').text('');
  var m = new bootstrap.Modal($('#log-modal'));
  m.show();
  loadLog(true);
}

$('#btn-log').on('click', viewLog);
$(document).on('click', '#banner-view-log', viewLog);
$('#log-refresh').on('click', function() { loadLog(true); });
$('#log-modal').on('hidden.bs.modal', function() { stopLog(); });

$(document).ready(function() {
  refreshCheck();
});
</script>
</body>
</html>
