<?php
error_reporting(0);
ini_set('display_errors', '0');
$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
    header('Content-Type: application/json');
    die(json_encode(['错误' => '系统配置丢失'], JSON_UNESCAPED_UNICODE));
}
require_once $configFile;
$format = isset($_GET['type']) ? strtolower($_GET['type']) : 'text';
$validFormats = ['json', 'text'];
if (!in_array($format, $validFormats)) {
    $format = 'text';
}
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $rawStats = [
        'today_calls' => (int)$pdo->query("SELECT COUNT(*) FROM huli_api_logs WHERE DATE(request_time) = CURDATE()")->fetchColumn(),
        'yesterday_calls' => (int)$pdo->query("SELECT COUNT(*) FROM huli_api_logs WHERE DATE(request_time) = CURDATE() - INTERVAL 1 DAY")->fetchColumn(),
        'total_calls' => (int)$pdo->query("SELECT COALESCE(SUM(total_calls), 0) FROM huli_apis")->fetchColumn(),
        'today_income' => (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM huli_orders WHERE status = 'paid' AND DATE(created_at) = CURDATE()")->fetchColumn(),
        'total_apis' => (int)$pdo->query("SELECT COUNT(*) FROM huli_apis")->fetchColumn(),
        'total_users' => (int)$pdo->query("SELECT COUNT(*) FROM huli_users")->fetchColumn(),
        'pending_feedback' => (int)$pdo->query("SELECT COUNT(*) FROM huli_feedback WHERE status = 'pending'")->fetchColumn(),
        'success_orders' => (int)$pdo->query("SELECT COUNT(*) FROM huli_orders WHERE status = 'paid' AND DATE(created_at) = CURDATE()")->fetchColumn(),
        'failed_orders' => (int)$pdo->query("SELECT COUNT(*) FROM huli_orders WHERE status = 'failed' AND DATE(created_at) = CURDATE()")->fetchColumn(),
        'pending_orders' => (int)$pdo->query("SELECT COUNT(*) FROM huli_orders WHERE status = 'pending'")->fetchColumn(),
        'friend_links' => (int)$pdo->query("SELECT COUNT(*) FROM huli_friend_links WHERE status='approved' AND is_hidden=0")->fetchColumn(),
        'pending_links' => (int)$pdo->query("SELECT COUNT(*) FROM huli_friend_links WHERE status='pending'")->fetchColumn(),
        'rejected_links' => (int)$pdo->query("SELECT COUNT(*) FROM huli_friend_links WHERE status='rejected'")->fetchColumn(),
    ];

    function formatNumber($number) {
        if ($number >= 100000000) {
            return round($number / 100000000, 2) . '亿';
        } elseif ($number >= 10000) {
            return round($number / 10000, 1) . 'w';
        } elseif (is_float($number)) {
            return number_format($number, 2);
        } else {
            return number_format($number);
        }
    }

    $formattedStats = [
        '今日调用' => formatNumber($rawStats['today_calls']),
        '昨日调用' => formatNumber($rawStats['yesterday_calls']),
        '总调用量' => formatNumber($rawStats['total_calls']),
        '今日收益' => formatNumber($rawStats['today_income']) . '元',
        'API总数' => formatNumber($rawStats['total_apis']),
        '用户总数' => formatNumber($rawStats['total_users']),
        '待处理反馈' => formatNumber($rawStats['pending_feedback']),
        '今日成功订单' => formatNumber($rawStats['success_orders']),
        '今日失败订单' => formatNumber($rawStats['failed_orders']),
        '待处理订单' => formatNumber($rawStats['pending_orders']),
        // 友情链接统计
        '友链总数' => formatNumber($rawStats['friend_links']),
        '待审核友链' => formatNumber($rawStats['pending_links']),
        '已拒绝友链' => formatNumber($rawStats['rejected_links']),
        '更新时间' => date('Y-m-d H:i:s')
    ];
    if ($format === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($formattedStats, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } else {
        header('Content-Type: text/plain; charset=utf-8');
        $output = " ╔huliapi╗\n\n";
        $output .= "╟地址:api.ipojie.com\n\n";
        $output .= "╟总接口:".$formattedStats['API总数']."个\n\n";
        $output .= "╟反馈总数:".$formattedStats['待处理反馈']."个\n\n";
        $output .= "╟------------------\n\n";
        $output .= "╟总调用:".$formattedStats['总调用量']."次\n\n";
        $output .= "╟今日调用:".$formattedStats['今日调用']."次\n\n";
        $output .= "╟昨日调用:".$formattedStats['昨日调用']."次\n\n";
        $output .= "╟------------------\n\n";
        $output .= "╟今日收益:".$formattedStats['今日收益']."\n\n";
        $output .= "╟成功订单:".$formattedStats['今日成功订单']."笔\n\n";
        $output .= "╟失败订单:".$formattedStats['今日失败订单']."笔\n\n";
        $output .= "╟待处理订单:".$formattedStats['待处理订单']."笔\n\n";
        $output .= "╟------------------\n\n";
        $output .= "╟友链总数:".$formattedStats['友链总数']."个\n\n";
        $output .= "╟待审核友链:".$formattedStats['待审核友链']."个\n\n";
        $output .= "╚已拒绝友链:".$formattedStats['已拒绝友链']."个\n\n";
        $output .= "更新时间:".$formattedStats['更新时间']."\n\n";
        echo $output;
    }
} catch (PDOException $e) {
    error_log('[api.php] 数据库错误: ' . $e->getMessage());
    header('Content-Type: text/plain; charset=utf-8');
    echo "╔错误信息╗\n";
    echo "╟数据库连接错误\n";
    echo "╚请检查配置后重试\n";
} catch (Exception $e) {
    error_log('[api.php] 系统错误: ' . $e->getMessage());
    header('Content-Type: text/plain; charset=utf-8');
    echo "╔错误信息╗\n";
    echo "╟系统错误\n";
    echo "╚请稍后重试\n";
}
