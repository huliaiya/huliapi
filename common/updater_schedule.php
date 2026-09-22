<?php
if (!defined('HULI_UPDATER_SCHEDULE_LIB')) { define('HULI_UPDATER_SCHEDULE_LIB', 1); }

/**
 * 惰性定时更新触发器（无需 cron）。
 * 挂在 session_boot.php 上，任意页面收到请求时触发。
 * 先做秒级时间闸，避免每次请求都读库；命中分钟后才读配置并判断是否到点，
 * 到点且当天未执行时自动后台启动更新。
 */
function huli_updater_maybe_run_schedule() {
    $root = dirname(__DIR__);

    // 秒级闸门：约每 45 秒才重新评估一次，避免每个请求都走完整逻辑
    $gate = $root . '/logs/update_schedule_gate.lock';
    if (is_file($gate) && (time() - (int)@filemtime($gate)) < 45) { return false; }
    if (!@touch($gate) && !is_file($gate)) { return false; }

    if (!file_exists($root . '/config.php')) { return false; }
    require_once $root . '/config.php';
    if (file_exists($root . '/common/version.php')) { require_once $root . '/common/version.php'; }
    require_once $root . '/common/github_update.php';
    require_once $root . '/common/updater.php';

    $pdo = huli_updater_make_pdo();
    if (!$pdo) { return false; }
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM huli_settings WHERE setting_key IN ('update_schedule_enabled','update_schedule_time')");
        $cfg = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Throwable $e) { return false; }
    if ((string)($cfg['update_schedule_enabled'] ?? '') !== '1') { return false; }

    $time = trim((string)($cfg['update_schedule_time'] ?? ''));
    if ($time === '' || !preg_match('#^([01][0-9]|2[0-3]):[0-5][0-9]$#', $time)) { return false; }
    if (date('H:i') !== $time) { return false; }

    // 当天已触发过则跳过（重要状态落库：优先 huli_settings，文件仅兜底）
    $markerDate = '';
    try {
        $markerDate = (string)$pdo->query("SELECT setting_value FROM huli_settings WHERE setting_key = 'updater_schedule_last_date'")->fetchColumn();
    } catch (Throwable $e) { $markerDate = ''; }
    if ($markerDate === '') {
        $markerFile = huli_updater_logs_dir() . '/update_schedule_last.json';
        $marker = huli_updater_read_json($markerFile);
        if (is_array($marker) && isset($marker['date'])) { $markerDate = (string)$marker['date']; }
    }
    if ($markerDate === date('Y-m-d')) { return false; }

    $info = null;
    try { $info = huli_detect_update_info(); } catch (Throwable $e) { $info = null; }
    if (!is_array($info) || empty($info['update_available'])) {
        huli_updater_write_json(huli_updater_logs_dir() . '/update_schedule_last.json', ['date' => date('Y-m-d'), 'result' => 'no-update']);
        huli_updater_mark_schedule_date_db($pdo, date('Y-m-d'));
        return false;
    }

    $token = huli_updater_new_token();
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

    if (!huli_updater_spawn($token)) {
        huli_updater_run_task($token, $task, '');
    }
    huli_updater_write_json($markerFile, ['date' => date('Y-m-d'), 'result' => 'triggered']);
    return true;
}
