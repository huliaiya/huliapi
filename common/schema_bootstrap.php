<?php
if (!defined('HULI_SCHEMA_BOOTSTRAP')) {
    define('HULI_SCHEMA_BOOTSTRAP', 1);
}

if (!defined('HULI_SCHEMA_VERSION')) {
    define('HULI_SCHEMA_VERSION', '2026-11-06-1');
}

/**
 * 幂等的数据库结构自愈。
 *
 * 历史上这些 SHOW COLUMNS / SHOW INDEX / SHOW TABLES / ALTER / INSERT 检查
 * 在每个 API 请求里都会执行，是接口响应变慢的主要来源。
 * 现在改为进程级缓存：结果写入 logs/.huli_schema_cache，
 * 仅当版本号变化或超过 TTL 时才重新探测。
 *
 * 缓存不可写时自动退化为每次执行（与旧行为一致），保证功能不受影响。
 */
function huli_schema_cache_file() {
    return dirname(__DIR__) . '/logs/.huli_schema_cache';
}

function huli_schema_cache_valid() {
    $cacheFile = huli_schema_cache_file();
    if (!is_file($cacheFile)) {
        return false;
    }
    $raw = @file_get_contents($cacheFile);
    if ($raw === false || $raw === '') {
        return false;
    }
    $data = @json_decode($raw, true);
    if (!is_array($data)) {
        return false;
    }
    if (($data['version'] ?? '') !== HULI_SCHEMA_VERSION) {
        return false;
    }
    return (time() - (int)($data['time'] ?? 0)) < 21600;
}

function huli_schema_cache_write() {
    $cacheFile = huli_schema_cache_file();
    $dir = dirname($cacheFile);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents($cacheFile, json_encode([
        'version' => HULI_SCHEMA_VERSION,
        'time'    => time(),
    ]), LOCK_EX);
}

/**
 * 执行结构自愈语句。并发首次探测时可能出现「已存在」类错误，
 * 这类错误可安全忽略；其他错误仍然抛出。
 */
function huli_schema_exec(PDO $pdo, $sql) {
    try {
        $pdo->exec($sql);
    } catch (PDOException $e) {
        $message = $e->getMessage();
        $ignorable = stripos($message, 'Duplicate') !== false
            || stripos($message, 'already exists') !== false;
        if (!$ignorable) {
            throw $e;
        }
    }
}

function huli_schema_bootstrap(PDO $pdo) {
    if (huli_schema_cache_valid()) {
        return;
    }

    $user_columns = $pdo->query("SHOW COLUMNS FROM `huli_users`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('points', $user_columns)) {
        huli_schema_exec($pdo, "ALTER TABLE `huli_users` ADD `points` INT NOT NULL DEFAULT 0 AFTER `balance`");
    }
    if (!in_array('membership_level', $user_columns)) {
        huli_schema_exec($pdo, "ALTER TABLE `huli_users` ADD `membership_level` ENUM('normal', 'super') NOT NULL DEFAULT 'normal'");
    }
    if (!in_array('membership_expire', $user_columns)) {
        huli_schema_exec($pdo, "ALTER TABLE `huli_users` ADD `membership_expire` DATETIME NULL DEFAULT NULL");
    }
    if (!in_array('last_points_warn_date', $user_columns)) {
        huli_schema_exec($pdo, "ALTER TABLE `huli_users` ADD `last_points_warn_date` DATE NULL DEFAULT NULL");
    }
    if (!in_array('last_balance_warn_date', $user_columns)) {
        huli_schema_exec($pdo, "ALTER TABLE `huli_users` ADD `last_balance_warn_date` DATE NULL DEFAULT NULL");
    }

    $api_columns = $pdo->query("SHOW COLUMNS FROM `huli_apis`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('created_at', $api_columns)) {
        huli_schema_exec($pdo, "ALTER TABLE `huli_apis` ADD `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `status`");
    }
    if (!in_array('updated_at', $api_columns)) {
        huli_schema_exec($pdo, "ALTER TABLE `huli_apis` ADD `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`");
    }

    $log_columns = $pdo->query("SHOW COLUMNS FROM `huli_api_logs`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('billing_type', $log_columns)) {
        huli_schema_exec($pdo, "ALTER TABLE `huli_api_logs` ADD `billing_type` VARCHAR(20) NOT NULL DEFAULT 'free' AFTER `is_success`");
    }
    if (!in_array('billing_amount', $log_columns)) {
        huli_schema_exec($pdo, "ALTER TABLE `huli_api_logs` ADD `billing_amount` DECIMAL(10,4) NOT NULL DEFAULT 0 AFTER `billing_type`");
    }
    $index_check = $pdo->query("SHOW INDEX FROM `huli_api_logs` WHERE Key_name = 'idx_user_time'")->fetch();
    if (!$index_check) {
        huli_schema_exec($pdo, "ALTER TABLE `huli_api_logs` ADD INDEX `idx_user_time` (`user_id`, `request_time`)");
    }
    $ip_index_check = $pdo->query("SHOW INDEX FROM `huli_api_logs` WHERE Key_name = 'idx_ip_time'")->fetch();
    if (!$ip_index_check) {
        huli_schema_exec($pdo, "ALTER TABLE `huli_api_logs` ADD INDEX `idx_ip_time` (`ip_address`, `request_time`)");
    }

    $claim_table_check = $pdo->query("SHOW TABLES LIKE 'huli_daily_points_claim'")->fetch();
    if (!$claim_table_check) {
        huli_schema_exec($pdo, "CREATE TABLE huli_daily_points_claim (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            claim_date DATE NOT NULL,
            points_granted INT NOT NULL DEFAULT 0,
            claimed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_date (user_id, claim_date),
            INDEX idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    $rate_table_check = $pdo->query("SHOW TABLES LIKE 'huli_rate_limits'")->fetch();
    if (!$rate_table_check) {
        huli_schema_exec($pdo, "CREATE TABLE huli_rate_limits (
            scope VARCHAR(16) NOT NULL,
            identifier VARCHAR(64) NOT NULL,
            window_start INT UNSIGNED NOT NULL,
            request_count INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (scope, identifier, window_start)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    $required_settings = [
        'enable_free_qps_limit'    => 1,
        'qps_mode'                 => 'database',
        'redis_host'               => '127.0.0.1',
        'redis_port'               => 6379,
        'redis_password'           => '',
        'redis_database'           => 0,
        'free_qps_seconds'         => 1,
        'free_qps_limit'           => 10,
        'enable_member_qps_limit'  => 1,
        'member_qps_seconds'       => 1,
        'member_qps_limit'         => 20,
        'daily_free_points'        => 100,
        'enable_daily_points'      => 1,
        'warn_points_threshold'    => 5,
        'warn_balance_threshold'   => 0.01,
        'enable_warn_notification' => 1,
        'enable_daily_points_notification' => 1,
    ];
    $settings_check = $pdo->query("SELECT setting_key FROM huli_settings")->fetchAll(PDO::FETCH_COLUMN);
    $missing_settings = array_diff(array_keys($required_settings), $settings_check);
    if (!empty($missing_settings)) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO huli_settings (setting_key, setting_value) VALUES (?, ?)");
        foreach ($missing_settings as $key) {
            $stmt->execute([$key, $required_settings[$key]]);
        }
    }

    huli_schema_cache_write();
}
