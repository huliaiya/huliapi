<?php
@error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
@ini_set('display_errors', 'Off');
@set_time_limit(0);
@ignore_user_abort(true);

$root = dirname(__DIR__);
if (!file_exists($root . '/config.php')) { fwrite(STDERR, "config.php missing\n"); exit(2); }
require_once $root . '/config.php';
if (file_exists($root . '/common/version.php')) { require_once $root . '/common/version.php'; }
require_once $root . '/common/updater.php';

$token = isset($argv[1]) ? huli_updater_safe_token($argv[1]) : '';
if ($token === '') { fwrite(STDERR, "missing token\n"); exit(2); }

$task = huli_updater_read_json(huli_updater_task_file($token));
if (!is_array($task) || empty($task['info'])) {
    huli_updater_write_status($token, [
        'status' => 'failed',
        'stage' => 'finish',
        'percent' => 100,
        'message' => '更新任务信息丢失，无法执行。',
        'finished_at' => time(),
        'error' => '更新任务信息丢失，无法执行。',
    ]);
    fwrite(STDERR, "task missing\n");
    exit(3);
}

fwrite(STDOUT, sprintf("[%s] update worker start token=%s version=%s\n", date('Y-m-d H:i:s'), $token, isset($task['info']['version']) ? $task['info']['version'] : ''));

$site_url = isset($task['site_url']) ? (string)$task['site_url'] : '';
$result = huli_updater_run_task($token, $task, $site_url);

$status = huli_updater_read_status($token);
fwrite(STDOUT, sprintf(
    "[%s] update worker done success=%s notified=%s message=%s\n",
    date('Y-m-d H:i:s'),
    !empty($result['success']) ? '1' : '0',
    !empty($status['notified']) ? '1' : '0',
    isset($result['message']) ? $result['message'] : ''
));
exit(0);
