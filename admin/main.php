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
if (!defined('SENLIN_CLIENT_VERSION')) {
    define('SENLIN_CLIENT_VERSION', '1.4.0');
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
    $pdo->exec("CREATE TABLE IF NOT EXISTS sl_daily_stats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        stat_date DATE NOT NULL,
        call_count INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_date (stat_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS sl_feedback (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        content TEXT NOT NULL,
        status ENUM('pending', 'processed') NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS sl_orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        status ENUM('pending', 'paid', 'failed') NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $stats['today_calls'] = $pdo->query("SELECT COUNT(*) FROM sl_api_logs WHERE DATE(request_time) = CURDATE()")->fetchColumn() ?: 0;
    $stats['yesterday_calls'] = $pdo->query("SELECT COUNT(*) FROM sl_api_logs WHERE DATE(request_time) = CURDATE() - INTERVAL 1 DAY")->fetchColumn() ?: 0;
    $stats['month_calls'] = $pdo->query("SELECT COUNT(*) FROM sl_api_logs WHERE MONTH(request_time) = MONTH(CURDATE()) AND YEAR(request_time) = YEAR(CURDATE())")->fetchColumn() ?: 0;
    $stats['total_apis'] = $pdo->query("SELECT COUNT(*) FROM sl_apis")->fetchColumn() ?: 0;
    $stats['total_users'] = $pdo->query("SELECT COUNT(*) FROM sl_users")->fetchColumn() ?: 0;
    $stats['total_calls_all'] = $pdo->query("SELECT SUM(total_calls) FROM sl_apis")->fetchColumn() ?: 0;
    $stats['pending_feedback'] = $pdo->query("SELECT COUNT(*) FROM sl_feedback WHERE status = 'pending'")->fetchColumn() ?: 0;
    $stats['success_orders'] = $pdo->query("SELECT COUNT(*) FROM sl_orders WHERE status = 'paid' AND DATE(created_at) = CURDATE()")->fetchColumn() ?: 0;
    $stats['failed_orders'] = $pdo->query("SELECT COUNT(*) FROM sl_orders WHERE status = 'failed' AND DATE(created_at) = CURDATE()")->fetchColumn() ?: 0;
    $stats['pending_orders'] = $pdo->query("SELECT COUNT(*) FROM sl_orders WHERE status = 'pending'")->fetchColumn() ?: 0;
    $stats['today_income'] = $pdo->query("SELECT SUM(amount) FROM sl_orders WHERE status = 'paid' AND DATE(created_at) = CURDATE()")->fetchColumn() ?: 0;
    $server_info['mysql_version'] = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
    $chart_query = $pdo->query("
        SELECT stat_date, call_count 
        FROM sl_daily_stats 
        WHERE stat_date >= CURDATE() - INTERVAL 30 DAY 
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
        FROM sl_api_logs 
        WHERE DATE(request_time) = CURDATE() 
        GROUP BY api_id 
        ORDER BY call_count DESC 
        LIMIT 5
    ");
    $top_apis_today = $top_apis_query->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($top_apis_today)) {
        $api_ids = array_column($top_apis_today, 'api_id');
        $placeholders = implode(',', array_fill(0, count($api_ids), '?'));
        $stmt_api_names = $pdo->prepare("SELECT id, name FROM sl_apis WHERE id IN ($placeholders)");
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
    '系统版本' => 'v' . SENLIN_CLIENT_VERSION,
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
?>

<!DOCTYPE html>
<html lang="zh">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
<title>API管理系统 - 统计面板</title>
<link rel="stylesheet" type="text/css" href="../assets/css/materialdesignicons.min.css">
<link rel="stylesheet" type="text/css" href="../assets/css/bootstrap.min.css">
<link rel="stylesheet" type="text/css" href="../assets/css/style.min.css">
<style>
.stat-card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
    transition: transform 0.2s, box-shadow 0.2s;
}
.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
}
.stat-card .avatar-box {
    width: 48px;
    height: 48px;
}
.stat-card .scroll-numbers {
    font-weight: 600;
    font-size: 1.5rem;
}
.ranking-badge {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
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
    <div class="row mt-3">
        <div class="col-md-6 col-xl-3">
            <div class="card stat-card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <span class="avatar-md rounded-circle bg-white bg-opacity-25 avatar-box">
                            <i class="mdi mdi-code-array fs-4"></i>
                        </span>
                        <span class="fs-4 scroll-numbers"><?php echo number_format($stats['today_calls']); ?></span>
                    </div>
                    <div class="text-end">今日调用</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card stat-card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <span class="avatar-md rounded-circle bg-white bg-opacity-25 avatar-box">
                            <i class="mdi mdi-code-array fs-4"></i>
                        </span>
                        <span class="fs-4 scroll-numbers"><?php echo number_format($stats['yesterday_calls']); ?></span>
                    </div>
                    <div class="text-end">昨日调用</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card stat-card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <span class="avatar-md rounded-circle bg-white bg-opacity-25 avatar-box">
                            <i class="mdi mdi-database fs-4"></i>
                        </span>
                        <span class="fs-4 scroll-numbers"><?php echo number_format($stats['total_calls_all']); ?></span>
                    </div>
                    <div class="text-end">总调用数</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card stat-card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <span class="avatar-md rounded-circle bg-white bg-opacity-25 avatar-box">
                            <i class="mdi mdi-currency-cny fs-4"></i>
                        </span>
                        <span class="fs-4 scroll-numbers"><?php echo number_format($stats['today_income'] ?? 0, 2); ?></span>
                    </div>
                    <div class="text-end">今日收益(元)</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card stat-card bg-danger text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <span class="avatar-md rounded-circle bg-white bg-opacity-25 avatar-box">
                            <i class="mdi mdi-api fs-4"></i>
                        </span>
                        <span class="fs-4 scroll-numbers"><?php echo number_format($stats['total_apis']); ?></span>
                    </div>
                    <div class="text-end">API总数</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card stat-card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <span class="avatar-md rounded-circle bg-white bg-opacity-25 avatar-box">
                            <i class="mdi mdi-account fs-4"></i>
                        </span>
                        <span class="fs-4 scroll-numbers"><?php echo number_format($stats['total_users']); ?></span>
                    </div>
                    <div class="text-end">用户总数</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card stat-card bg-purple text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <span class="avatar-md rounded-circle bg-white bg-opacity-25 avatar-box">
                            <i class="mdi mdi-thumb-up-outline fs-4"></i>
                        </span>
                        <span class="fs-4 scroll-numbers"><?php echo number_format($stats['pending_feedback']); ?></span>
                    </div>
                    <div class="text-end">待处理反馈</div>
                </div>
            </div>
        </div>
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
        <div class="col-lg-6">
            <div class="card">
                <header class="card-header">
                    <div class="card-title">系统信息</div>
                </header>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>项目</th>
                                    <th>值</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sysInfo as $name => $value): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($name); ?></td>
                                        <td><?php echo htmlspecialchars($value); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
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