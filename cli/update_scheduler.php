<?php
@error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
@ini_set('display_errors', 'Off');
@set_time_limit(0);
@ignore_user_abort(true);

$root = dirname(__DIR__);
if (!file_exists($root . '/config.php')) { fwrite(STDERR, "config.php missing\n"); exit(2); }
require_once $root . '/config.php';
if (file_exists($root . '/common/version.php')) { require_once $root . '/common/version.php'; }
require_once $root . '/common/github_update.php';
require_once $root . '/common/updater.php';

function huli_scheduler_write_marker($value) {
    return huli_updater_write_json(huli_updater_logs_dir() . '/update_schedule_last.json', $value);
}
function huli_scheduler_read_marker() {
    return huli_updater_read_json(huli_updater_logs_dir() . '/update_schedule_last.json');
}

$pdo = huli_updater_make_pdo();
$enabled = false;
$time = '';
if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM huli_settings WHERE setting_key IN ('update_schedule_enabled','update_schedule_time')");
        foreach ($stmt->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) {
            if ($k === 'update_schedule_enabled') { $enabled = (string)$v === '1'; }
            if ($k === 'update_schedule_time') { $time = trim((string)$v); }
        }
    } catch (Throwable $e) { }
}

if (!$enabled) {
    fwrite(STDOUT, sprintf("[%s] scheduled update disabled, skip\n", date('Y-m-d H:i:s')));
    exit(0);
}
if ($time === '' || !preg_match('#^([01][0-9]|2[0-3]):[0-5][0-9]$#', $time)) {
    fwrite(STDOUT, sprintf("[%s] invalid schedule time, skip\n", date('Y-m-d H:i:s')));
    exit(0);
}
$now = date('H:i');
if ($now !== $time) {
    fwrite(STDOUT, sprintf("[%s] not scheduled time (target=%s now=%s), skip\n", date('Y-m-d H:i:s'), $time, $now));
    exit(0);
}

$today = date('Y-m-d');
$marker = huli_scheduler_read_marker();
if (is_array($marker) && isset($marker['date']) && (string)$marker['date'] === $today) {
    fwrite(STDOUT, sprintf("[%s] already triggered today, skip\n", date('Y-m-d H:i:s')));
    exit(0);
}

$token = huli_updater_new_token();
$info = null;
try { $info = huli_detect_update_info(); } catch (Throwable $e) { $info = null; }

if (!is_array($info) || empty($info['update_available'])) {
    huli_scheduler_write_marker(['date' => $today, 'result' => 'no-update']);
    fwrite(STDOUT, sprintf("[%s] no update available, marked done for today\n", date('Y-m-d H:i:s')));
    exit(0);
}

$task = ['info' => $info, 'site_url' => '', 'admin_id' => 0];
huli_updater_write_json(huli_updater_task_file($token), $task);
huli_updater_write_status($token, [
    'status' => 'running',
    'stage' => 'queued',
    'percent' => 2,
    'message' => '定时更新任务已启动...',
    'version' => isset($info['version']) ? $info['version'] : '',
    'old_version' => defined('SENLIN_CLIENT_VERSION') ? SENLIN_CLIENT_VERSION : '',
    'started_at' => time(),
    'trigger' => 'schedule',
]);

if (huli_updater_spawn($token)) {
    fwrite(STDOUT, sprintf("[%s] scheduled update spawned token=%s version=%s\n", date('Y-m-d H:i:s'), $token, isset($info['version']) ? $info['version'] : ''));
} else {
    fwrite(STDOUT, sprintf("[%s] spawn failed, running inline\n", date('Y-m-d H:i:s')));
    $result = huli_updater_run_task($token, $task, '');
    fwrite(STDOUT, sprintf("[%s] inline done success=%s\n", date('Y-m-d H:i:s'), !empty($result['success']) ? '1' : '0'));
}

huli_scheduler_write_marker(['date' => $today, 'result' => 'triggered']);
exit(0);
