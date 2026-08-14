<?php
@session_start();
error_reporting(E_ALL);
ini_set('display_errors', 'On');
if (!file_exists('../config.php')) {
    die("出现错误！配置文件丢失，请先完成安装。");
}
require_once '../config.php';
if (file_exists('../common/version.php')) {
    require_once '../common/version.php';
}
require_once __DIR__ . '/../common/payment/order_fulfillment.php';
if (!defined('SENLIN_CLIENT_VERSION')) {
    define('SENLIN_CLIENT_VERSION', '1.5.0');
}
if (!defined('SENLIN_CLIENT_RELEASE_DATE')) {
    define('SENLIN_CLIENT_RELEASE_DATE', '');
}
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: login.php');
    exit;
}
$username = htmlspecialchars($_SESSION['admin_username'] ?? '管理员');
$stats = [
    'today_calls' => 0,
    'yesterday_calls' => 0,
    'month_calls' => 0,
    'total_apis' => 0,
    'total_users' => 0,
    'total_calls_all' => 0,
    'pending_feedback' => 0,
    'success_orders' => 0,
    'failed_orders' => 0,
    'pending_orders' => 0,
    'today_income' => 0
];
$server_info = [
    'php_version' => PHP_VERSION,
    'server_software' => isset($_SERVER['SERVER_SOFTWARE']) ? substr($_SERVER['SERVER_SOFTWARE'], 0, 25) . '...' : '未知',
    'mysql_version' => 'N/A',
    'load_avg' => 'N/A'
];
$chart_data_json = '{"labels":[],"data":[]}';
$top_apis_today = [];
$pdo = null;
$db_error = '';
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    huli_ensure_afdian_order_columns($pdo);
    $pdo->exec("CREATE TABLE IF NOT EXISTS huli_daily_stats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        stat_date DATE NOT NULL,
        call_count INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_date (stat_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS huli_feedback (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        content TEXT NOT NULL,
        status ENUM('pending', 'processed') NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $stats['today_calls'] = $pdo->query("SELECT COUNT(*) FROM huli_api_logs WHERE DATE(request_time) = CURDATE()")->fetchColumn() ?: 0;
    $stats['yesterday_calls'] = $pdo->query("SELECT COUNT(*) FROM huli_api_logs WHERE DATE(request_time) = CURDATE() - INTERVAL 1 DAY")->fetchColumn() ?: 0;
    $stats['month_calls'] = $pdo->query("SELECT COUNT(*) FROM huli_api_logs WHERE MONTH(request_time) = MONTH(CURDATE()) AND YEAR(request_time) = YEAR(CURDATE())")->fetchColumn() ?: 0;
    $stats['total_apis'] = $pdo->query("SELECT COUNT(*) FROM huli_apis")->fetchColumn() ?: 0;
    $stats['total_users'] = $pdo->query("SELECT COUNT(*) FROM huli_users")->fetchColumn() ?: 0;
    $stats['total_calls_all'] = $pdo->query("SELECT SUM(total_calls) FROM huli_apis")->fetchColumn() ?: 0;
    $stats['pending_feedback'] = $pdo->query("SELECT COUNT(*) FROM huli_feedback WHERE status = 'pending'")->fetchColumn() ?: 0;
    $stats['success_orders'] = $pdo->query("SELECT COUNT(*) FROM huli_orders WHERE status = 'paid' AND DATE(created_at) = CURDATE()")->fetchColumn() ?: 0;
    $stats['failed_orders'] = $pdo->query("SELECT COUNT(*) FROM huli_orders WHERE status = 'failed' AND DATE(created_at) = CURDATE()")->fetchColumn() ?: 0;
    $stats['pending_orders'] = $pdo->query("SELECT COUNT(*) FROM huli_orders WHERE status = 'pending'")->fetchColumn() ?: 0;
    $stats['today_income'] = $pdo->query("SELECT SUM(amount) FROM huli_orders WHERE status = 'paid' AND DATE(created_at) = CURDATE()")->fetchColumn() ?: 0;
    $server_info['mysql_version'] = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
    $chart_query = $pdo->query("
        SELECT DATE(request_time) AS stat_date, COUNT(*) AS call_count
        FROM huli_api_logs
        WHERE request_time >= CURDATE() - INTERVAL 30 DAY
        GROUP BY DATE(request_time)
        ORDER BY stat_date ASC
    ");
    $chart_raw_data = $chart_query->fetchAll(PDO::FETCH_ASSOC);
    $chart_labels = [];
    $chart_values = [];
    $period = new DatePeriod(
        new DateTime('-29 days'),
        new DateInterval('P1D'),
        new DateTime('+1 day')
    );
    $dates = [];
    foreach ($period as $date) {
        $dates[$date->format('Y-m-d')] = 0;
    }
    foreach ($chart_raw_data as $row) {
        if (isset($dates[$row['stat_date']])) {
            $dates[$row['stat_date']] = (int)$row['call_count'];
        }
    }
    foreach ($dates as $day => $calls) {
        $chart_labels[] = date('m-d', strtotime($day));
        $chart_values[] = $calls;
    }
    $chart_data_json = json_encode(['labels' => $chart_labels, 'data' => $chart_values]);

    $top_apis_query = $pdo->query("
        SELECT api_id, COUNT(*) as call_count
        FROM huli_api_logs
        WHERE DATE(request_time) = CURDATE()
        GROUP BY api_id
        ORDER BY call_count DESC
        LIMIT 5
    ");
    $top_apis_today = $top_apis_query->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($top_apis_today)) {
        $api_ids = array_column($top_apis_today, 'api_id');
        $placeholders = implode(',', array_fill(0, count($api_ids), '?'));
        $stmt_api_names = $pdo->prepare("SELECT id, name FROM huli_apis WHERE id IN ($placeholders)");
        $stmt_api_names->execute($api_ids);
        $api_names = $stmt_api_names->fetchAll(PDO::FETCH_KEY_PAIR);

        foreach ($top_apis_today as &$api) {
            $api['name'] = $api_names[$api['api_id']] ?? '未知API';
        }
    }
} catch (PDOException $e) {
    $db_error = "数据库连接错误: " . $e->getMessage();
    error_log("[" . date('Y-m-d H:i:s') . "] 数据库错误: " . $e->getMessage() . "\n", 3, "../logs/db_errors.log");
    $server_info['mysql_version'] = '连接失败';
}
if (function_exists('sys_getloadavg')) {
    $load = sys_getloadavg();
    $server_info['load_avg'] = round($load[0], 2);
}
$sysInfo = [
    '系统版本' => 'v' . SENLIN_CLIENT_VERSION . (SENLIN_CLIENT_RELEASE_DATE ? ' (' . SENLIN_CLIENT_RELEASE_DATE . ')' : ''),
    '服务器' => $_SERVER['SERVER_SOFTWARE'] ?? '未知',
    'PHP版本' => phpversion(),
    'MySQL版本' => $server_info['mysql_version'],
    '系统负载' => $server_info['load_avg'] . ' (1分钟内)'
];
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    $sysInfo['操作系统'] = 'Windows';
} else {
    $sysInfo['操作系统'] = php_uname('s') . ' ' . php_uname('r');
}
if (function_exists('is_readable') && @is_readable('/proc/cpuinfo')) {
    $cpuinfo = @file_get_contents('/proc/cpuinfo');
    if ($cpuinfo !== false) {
        preg_match_all('/model name\s*:\s*(.+)/', $cpuinfo, $matches);
        $cpuModel = isset($matches[1][0]) ? $matches[1][0] : '未知';
        $cpuCount = count($matches[1]);
        $sysInfo['CPU信息'] = "{$cpuCount}核 - {$cpuModel}";
    }
} else {
    $sysInfo['CPU信息'] = '权限不足，无法获取';
}
if (function_exists('is_readable') && @is_readable('/proc/meminfo')) {
    $meminfo = @file_get_contents('/proc/meminfo');
    if ($meminfo !== false) {
        preg_match('/MemTotal:\s+(\d+)/', $meminfo, $matches);
        $total = isset($matches[1]) ? round($matches[1] / 1024) : 0;
        preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $matches);
        $available = isset($matches[1]) ? round($matches[1] / 1024) : 0;
        $used = $total - $available;
        $percent = $total > 0 ? round(($used / $total) * 100) : 0;
        $sysInfo['内存使用'] = "{$used}MB / {$total}MB ({$percent}%)";
    }
} else {
    $sysInfo['内存使用'] = '权限不足，无法获取';
}
$disk_path = __DIR__;
$sysInfo['磁盘空间'] = '权限不足，无法获取';
if (function_exists('disk_total_space') && @disk_total_space($disk_path) !== false) {
    $diskTotal = round(disk_total_space($disk_path) / (1024 * 1024 * 1024), 2);
    $diskFree = round(disk_free_space($disk_path) / (1024 * 1024 * 1024), 2);
    $diskUsed = $diskTotal - $diskFree;
    $diskPercent = $diskTotal > 0 ? round(($diskUsed / $diskTotal) * 100) : 0;
    $sysInfo['磁盘空间'] = "{$diskUsed}GB / {$diskTotal}GB ({$diskPercent}%)";
}
$sysInfo['PHP内存限制'] = ini_get('memory_limit');
$sysInfo['PHP最大执行时间'] = ini_get('max_execution_time') . '秒';
$sysInfo['服务器IP'] = $_SERVER['SERVER_ADDR'] ?? '未知';
$sysInfo['客户端IP'] = $_SERVER['REMOTE_ADDR'] ?? '未知';
if (function_exists('curl_init')) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.ipify.org');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $publicIP = curl_exec($ch);
    curl_close($ch);
    $sysInfo['公网IP'] = $publicIP ?: '获取失败';
} else {
    $sysInfo['公网IP'] = 'CURL未启用';
}

$dbCard = [
    'status' => false,
    'host' => defined('DB_HOST') ? DB_HOST : '-',
    'name' => defined('DB_NAME') ? DB_NAME : '-',
    'charset' => defined('DB_CHARSET') ? DB_CHARSET : '-',
    'version' => 'N/A',
    'size' => 'N/A',
    'tables' => 0,
    'uptime' => 'N/A',
    'ping_ms' => null,
    'max_conn' => 'N/A',
    'current_conn' => 'N/A',
];
if (isset($pdo) && $pdo instanceof PDO) {
    $dbCard['status'] = true;
    $dbCard['version'] = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
    $pingStart = microtime(true);
    try { $pdo->query('SELECT 1'); } catch (Throwable $e) {}
    $dbCard['ping_ms'] = round((microtime(true) - $pingStart) * 1000, 2);
    try {
        $dbCard['tables'] = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = " . $pdo->quote($dbCard['name']))->fetchColumn();
    } catch (Throwable $e) { $dbCard['tables'] = 0; }
    try {
        $status = $pdo->query('SHOW STATUS')->fetchAll(PDO::FETCH_KEY_PAIR);
        $dbCard['uptime'] = isset($status['Uptime']) ? formatUptime((int)$status['Uptime']) : 'N/A';
        $dbCard['max_conn'] = isset($status['Max_used_connections']) ? $status['Max_used_connections'] . ' (峰值)' : 'N/A';
        $vars = $pdo->query('SHOW VARIABLES')->fetchAll(PDO::FETCH_KEY_PAIR);
        $dbCard['current_conn'] = isset($vars['max_connections']) ? ($status['Threads_connected'] ?? '?') . ' / ' . $vars['max_connections'] : 'N/A';
    } catch (Throwable $e) {}
    try {
        $sizeRow = $pdo->query("SELECT SUM(data_length + index_length) FROM information_schema.tables WHERE table_schema = " . $pdo->quote($dbCard['name']))->fetchColumn();
        if ($sizeRow) {
            $bytes = (int)$sizeRow;
            if ($bytes >= 1073741824) $dbCard['size'] = round($bytes / 1073741824, 2) . ' GB';
            elseif ($bytes >= 1048576) $dbCard['size'] = round($bytes / 1048576, 2) . ' MB';
            elseif ($bytes >= 1024) $dbCard['size'] = round($bytes / 1024, 2) . ' KB';
            else $dbCard['size'] = $bytes . ' B';
        }
    } catch (Throwable $e) {}
}

$redisCard = [
    'available' => class_exists('Redis'),
    'host' => '127.0.0.1',
    'port' => 6379,
    'status' => false,
    'ping_ms' => null,
    'version' => 'N/A',
    'used_memory' => 'N/A',
    'keys' => 'N/A',
    'uptime' => 'N/A',
    'mode' => 'database',
];
try {
    $pdo_settings = $pdo ?? null;
    if ($pdo_settings) {
        $stmt = $pdo_settings->query("SELECT setting_key, setting_value FROM huli_settings WHERE setting_key IN ('redis_host','redis_port','redis_password','redis_database','qps_mode')");
        $set = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $redisCard['host'] = $set['redis_host'] ?? '127.0.0.1';
        $redisCard['port'] = (int)($set['redis_port'] ?? 6379);
        $redisCard['mode'] = $set['qps_mode'] ?? 'database';
    }
} catch (Throwable $e) {}
if ($redisCard['available']) {
    try {
        $r = new Redis();
        $pingStart = microtime(true);
        $r->connect($redisCard['host'], $redisCard['port'], 0.5);
        $redisCard['ping_ms'] = round((microtime(true) - $pingStart) * 1000, 2);
        $redisCard['status'] = true;
        $info = $r->info();
        $redisCard['version'] = $info['redis_version'] ?? 'N/A';
        $redisCard['uptime'] = isset($info['uptime_in_seconds']) ? formatUptime((int)$info['uptime_in_seconds']) : 'N/A';
        if (isset($info['used_memory_human'])) $redisCard['used_memory'] = $info['used_memory_human'];
        try {
            $redisCard['keys'] = $r->dbSize();
        } catch (Throwable $e) { $redisCard['keys'] = 'N/A'; }
        $r->close();
    } catch (Throwable $e) {
        $redisCard['status'] = false;
        $redisCard['ping_ms'] = null;
    }
}

function formatUptime($seconds) {
    $d = floor($seconds / 86400); $h = floor(($seconds % 86400) / 3600); $m = floor(($seconds % 3600) / 60);
    if ($d > 0) return $d . '天' . $h . '时' . $m . '分';
    if ($h > 0) return $h . '时' . $m . '分';
    return $m . '分' . ($seconds % 60) . '秒';
}
?>

<!DOCTYPE html>
<html lang="zh">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
<title>huliapi - 统计面板</title>
<link rel="stylesheet" type="text/css" href="../assets/css/materialdesignicons.min.css">
<link rel="stylesheet" type="text/css" href="../assets/css/bootstrap.min.css">
<link rel="stylesheet" type="text/css" href="../assets/css/style.min.css">
<style>
.stats-card {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 18px 20px;
    min-height: 96px;
    border-radius: 18px;
    border: 1px solid rgba(180, 220, 245, .45);
    background:
        linear-gradient(135deg, rgba(255, 255, 255, .62) 0%, rgba(220, 238, 252, .48) 100%);
    box-shadow:
        0 6px 22px rgba(45, 100, 155, .10),
        inset 0 1px 0 rgba(255, 255, 255, .65);
    backdrop-filter: blur(14px) saturate(160%);
    -webkit-backdrop-filter: blur(14px) saturate(160%);
    transition: transform .25s ease, box-shadow .25s ease;
}
.stats-card:hover {
    transform: translateY(-2px);
    box-shadow:
        0 12px 28px rgba(45, 100, 155, .18),
        inset 0 1px 0 rgba(255, 255, 255, .75);
}
.stats-card-icon {
    flex: 0 0 auto;
    width: 52px;
    height: 52px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    box-shadow:
        inset 0 2px 4px rgba(255, 255, 255, .35),
        0 4px 10px rgba(45, 100, 155, .15);
}
.stats-card-icon > i {
    font-size: 26px;
    line-height: 1;
}
.stats-card-info {
    flex: 1 1 auto;
    display: flex;
    flex-direction: column;
    justify-content: center;
    min-width: 0;
}
.stats-card-value {
    font-size: 28px;
    font-weight: 700;
    line-height: 1.1;
    color: #0a4b7a;
    letter-spacing: -.5px;
}
.stats-card-label {
    font-size: 13px;
    color: #4a7290;
    margin-top: 4px;
    letter-spacing: .5px;
}
.ranking-badge {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
}
.info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0;
}
.info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 14px;
    border-bottom: 1px dashed rgba(134, 194, 255, .18);
    transition: background .2s ease;
}
.info-row:hover {
    background: linear-gradient(90deg, rgba(134, 194, 255, .08), rgba(119, 222, 218, .05));
}
.info-row:nth-last-child(-n+2) {
    border-bottom: none;
}
.info-key {
    color: #5c7d99;
    font-size: 13px;
    font-weight: 500;
}
.info-val {
    color: #0a4b7a;
    font-size: 13px;
    font-weight: 600;
    text-align: right;
    word-break: break-all;
    max-width: 65%;
}
.card-status {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
    background: rgba(255, 255, 255, .55);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
}
.card-status.is-ok {
    color: #2eb883;
    background: linear-gradient(135deg, rgba(46, 184, 131, .16), rgba(86, 219, 168, .12));
    border: 1px solid rgba(46, 184, 131, .35);
}
.card-status.is-fail {
    color: #e74c3c;
    background: linear-gradient(135deg, rgba(231, 76, 60, .16), rgba(241, 145, 122, .12));
    border: 1px solid rgba(231, 76, 60, .35);
}
@media (max-width: 768px) {
    .info-grid { grid-template-columns: 1fr; }
    .info-row:nth-last-child(-n+2) { border-bottom: 1px dashed rgba(134, 194, 255, .18); }
    .info-row:last-child { border-bottom: none; }
}
</style>
</head>
<body>
<div class="container-fluid">
    <?php if ($db_error): ?>
    <div class="alert alert-danger mt-3">
        <?php echo $db_error; ?>
    </div>
    <?php endif; ?>
    <div class="row mt-3 stats-card-row g-3">
        <?php
        $admin_stats = [
            ['label' => '今日调用', 'value' => number_format($stats['today_calls']), 'icon' => 'mdi-code-array', 'bg' => 'rgba(108, 178, 235, .85)', 'accent' => '#5ba4dc'],
            ['label' => '昨日调用', 'value' => number_format($stats['yesterday_calls']), 'icon' => 'mdi-code-brackets', 'bg' => 'rgba(120, 200, 230, .85)', 'accent' => '#5fb3d8'],
            ['label' => '总调用数', 'value' => number_format($stats['total_calls_all']), 'icon' => 'mdi-database', 'bg' => 'rgba(140, 215, 175, .85)', 'accent' => '#7bc89e'],
            ['label' => '今日收益(元)', 'value' => number_format($stats['today_income'] ?? 0, 2), 'icon' => 'mdi-currency-cny', 'bg' => 'rgba(235, 200, 130, .85)', 'accent' => '#e0b45f'],
            ['label' => 'API 总数', 'value' => number_format($stats['total_apis']), 'icon' => 'mdi-api', 'bg' => 'rgba(235, 145, 160, .85)', 'accent' => '#e08899'],
            ['label' => '用户总数', 'value' => number_format($stats['total_users']), 'icon' => 'mdi-account', 'bg' => 'rgba(160, 150, 230, .85)', 'accent' => '#8f86d6'],
            ['label' => '待处理反馈', 'value' => number_format($stats['pending_feedback']), 'icon' => 'mdi-thumb-up-outline', 'bg' => 'rgba(180, 150, 220, .85)', 'accent' => '#b08fd8'],
        ];
        foreach ($admin_stats as $as): ?>
        <div class="col-md-6 col-xl-3">
            <div class="stats-card">
                <div class="stats-card-icon" style="background: <?php echo $as['bg']; ?>;">
                    <i class="mdi <?php echo $as['icon']; ?>"></i>
                </div>
                <div class="stats-card-info">
                    <div class="stats-card-value scroll-numbers"><?php echo $as['value']; ?></div>
                    <div class="stats-card-label"><?php echo htmlspecialchars($as['label']); ?></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="row mt-3">
        <div class="col-lg-12">
            <div class="card">
                <header class="card-header">
                    <div class="card-title">API调用统计 (近30天)</div>
                </header>
                <div class="card-body">
                    <?php if (empty($chart_labels)): ?>
                        <div class="alert alert-info">
                            暂无API调用数据或数据库连接错误
                        </div>
                    <?php else: ?>
                        <div class="chart-container" style="position: relative; height:40vh; width:100%">
                            <canvas id="apiCallsChart"></canvas>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="row mt-3">
        <div class="col-lg-6">
            <div class="card">
                <header class="card-header">
                    <div class="card-title"><i class="mdi mdi-trending-up me-1"></i>今日API请求排名 TOP5</div>
                </header>
                <div class="card-body">
                    <?php if (empty($top_apis_today)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="mdi mdi-chart-line fs-1 d-block mb-2 opacity-25"></i>
                            暂无调用数据
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php
                            $rank = 1;
                            foreach ($top_apis_today as $api):
                                $badgeClass = $rank === 1 ? 'bg-danger' : ($rank === 2 ? 'bg-warning' : ($rank === 3 ? 'bg-info' : 'bg-secondary'));
                            ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <div class="d-flex align-items-center">
                                        <span class="badge rounded-pill <?php echo $badgeClass; ?> me-3" style="min-width: 28px;">
                                            <?php echo $rank; ?>
                                        </span>
                                        <span class="text-truncate" style="max-width: 200px;"><?php echo htmlspecialchars($api['name']); ?></span>
                                    </div>
                                    <span class="badge bg-primary rounded-pill"><?php echo number_format($api['call_count']); ?> 次</span>
                                </div>
                            <?php
                                $rank++;
                            endforeach;
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-12">
            <div class="card">
                <header class="card-header">
                    <div class="card-title"><i class="mdi mdi-information-outline me-1"></i>系统信息</div>
                </header>
                <div class="card-body">
                    <div class="info-grid">
                        <?php foreach ($sysInfo as $name => $value): ?>
                            <div class="info-row">
                                <span class="info-key"><?php echo htmlspecialchars($name); ?></span>
                                <span class="info-val"><?php echo htmlspecialchars($value); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row mt-3">
        <div class="col-lg-6">
            <div class="card">
                <header class="card-header">
                    <div class="card-title"><i class="mdi mdi-database me-1"></i>数据库</div>
                    <span class="card-status <?php echo $dbCard['status'] ? 'is-ok' : 'is-fail'; ?>">
                        <i class="mdi <?php echo $dbCard['status'] ? 'mdi-check-circle' : 'mdi-close-circle'; ?>"></i>
                        <?php echo $dbCard['status'] ? '已连接' : '未连接'; ?>
                    </span>
                </header>
                <div class="card-body">
                    <div class="info-grid">
                        <div class="info-row"><span class="info-key">类型</span><span class="info-val">MySQL</span></div>
                        <div class="info-row"><span class="info-key">版本</span><span class="info-val"><?php echo htmlspecialchars($dbCard['version']); ?></span></div>
                        <div class="info-row"><span class="info-key">主机</span><span class="info-val"><?php echo htmlspecialchars($dbCard['host']); ?></span></div>
                        <div class="info-row"><span class="info-key">数据库</span><span class="info-val"><?php echo htmlspecialchars($dbCard['name']); ?></span></div>
                        <div class="info-row"><span class="info-key">字符集</span><span class="info-val"><?php echo htmlspecialchars($dbCard['charset']); ?></span></div>
                        <div class="info-row"><span class="info-key">表数量</span><span class="info-val"><?php echo number_format($dbCard['tables']); ?></span></div>
                        <div class="info-row"><span class="info-key">数据大小</span><span class="info-val"><?php echo htmlspecialchars($dbCard['size']); ?></span></div>
                        <div class="info-row"><span class="info-key">连接数</span><span class="info-val"><?php echo htmlspecialchars((string)$dbCard['current_conn']); ?></span></div>
                        <div class="info-row"><span class="info-key">运行时间</span><span class="info-val"><?php echo htmlspecialchars($dbCard['uptime']); ?></span></div>
                        <div class="info-row"><span class="info-key">响应延迟</span><span class="info-val"><?php echo $dbCard['ping_ms'] !== null ? $dbCard['ping_ms'] . ' ms' : 'N/A'; ?></span></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <header class="card-header">
                    <div class="card-title"><i class="mdi mdi-server me-1"></i>Redis</div>
                    <?php if (!$redisCard['available']): ?>
                        <span class="card-status is-fail"><i class="mdi mdi-close-circle"></i>扩展未安装</span>
                    <?php elseif ($redisCard['status']): ?>
                        <span class="card-status is-ok"><i class="mdi mdi-check-circle"></i>已连接</span>
                    <?php else: ?>
                        <span class="card-status is-fail"><i class="mdi mdi-close-circle"></i>未连接</span>
                    <?php endif; ?>
                </header>
                <div class="card-body">
                    <div class="info-grid">
                        <div class="info-row"><span class="info-key">限速模式</span><span class="info-val"><?php echo $redisCard['mode'] === 'redis' ? 'Redis' : '数据库'; ?></span></div>
                        <div class="info-row"><span class="info-key">版本</span><span class="info-val"><?php echo htmlspecialchars((string)$redisCard['version']); ?></span></div>
                        <div class="info-row"><span class="info-key">主机</span><span class="info-val"><?php echo htmlspecialchars($redisCard['host']); ?></span></div>
                        <div class="info-row"><span class="info-key">端口</span><span class="info-val"><?php echo (int)$redisCard['port']; ?></span></div>
                        <div class="info-row"><span class="info-key">已用内存</span><span class="info-val"><?php echo htmlspecialchars((string)$redisCard['used_memory']); ?></span></div>
                        <div class="info-row"><span class="info-key">键数量</span><span class="info-val"><?php echo is_numeric($redisCard['keys']) ? number_format((int)$redisCard['keys']) : htmlspecialchars((string)$redisCard['keys']); ?></span></div>
                        <div class="info-row"><span class="info-key">运行时间</span><span class="info-val"><?php echo htmlspecialchars((string)$redisCard['uptime']); ?></span></div>
                        <div class="info-row"><span class="info-key">响应延迟</span><span class="info-val"><?php echo $redisCard['ping_ms'] !== null ? $redisCard['ping_ms'] . ' ms' : 'N/A'; ?></span></div>
                        <div class="info-row"><span class="info-key">扩展</span><span class="info-val"><?php echo $redisCard['available'] ? '已安装' : '未安装'; ?></span></div>
                        <div class="info-row"><span class="info-key">状态</span><span class="info-val"><?php echo $redisCard['status'] ? '在线' : '离线'; ?></span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script type="text/javascript" src="../assets/js/jquery.min.js"></script>
<script type="text/javascript" src="../assets/js/popper.min.js"></script>
<script type="text/javascript" src="../assets/js/bootstrap.min.js"></script>
<script type="text/javascript" src="../assets/js/chart.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('apiCallsChart');
    if (ctx) {
        try {
            const chartData = <?php echo $chart_data_json; ?>;
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'API调用量',
                        data: chartData.data,
                        borderColor: '#4e73df',
                        backgroundColor: 'rgba(78, 115, 223, 0.1)',
                        borderWidth: 2,
                        pointBackgroundColor: '#4e73df',
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        }
                    }
                }
            });
        } catch (e) {
            console.error('图表初始化错误:', e);
            if (ctx.parentNode) {
                ctx.parentNode.innerHTML = '<div class="alert alert-danger">图表加载失败: ' + e.message + '</div>';
            }
        }
    }
});
</script>
</body>
</html>
