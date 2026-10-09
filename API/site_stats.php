<?php require_once __DIR__ . '/../common/security/api_auth.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $days = (int)($_GET['days'] ?? 30);
    if ($days < 1) $days = 30;
    if ($days > 365) $days = 365;

    $row = $pdo->query("SELECT
        COUNT(*) AS total_calls,
        COALESCE(SUM(is_success),0) AS success_calls,
        COALESCE(SUM(billing_amount),0) AS billed_total,
        SUM(request_time >= CURDATE()) AS today_calls,
        SUM(request_time >= CURDATE() AND is_success = 1) AS today_success,
        SUM(request_time >= CURDATE() - INTERVAL 1 DAY AND request_time < CURDATE()) AS yesterday_calls,
        SUM(request_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS week_calls,
        SUM(request_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS month_calls,
        SUM(request_time >= DATE_FORMAT(NOW(), '%Y-%m-01')) AS this_month_calls,
        COUNT(DISTINCT user_id) AS active_users
    FROM huli_api_logs")->fetch(PDO::FETCH_ASSOC);

    $total_calls = (int)$row['total_calls'];
    $summary = [
        'total_calls' => $total_calls,
        'success_calls' => (int)$row['success_calls'],
        'fail_calls' => $total_calls - (int)$row['success_calls'],
        'success_rate' => $total_calls > 0 ? round((int)$row['success_calls'] / $total_calls * 100, 2) : 0,
        'billed_total' => (float)$row['billed_total'],
        'today_calls' => (int)$row['today_calls'],
        'today_success' => (int)$row['today_success'],
        'today_fail' => (int)$row['today_calls'] - (int)$row['today_success'],
        'yesterday_calls' => (int)$row['yesterday_calls'],
        'week_calls' => (int)$row['week_calls'],
        'month_calls' => (int)$row['month_calls'],
        'this_month_calls' => (int)$row['this_month_calls'],
        'active_users' => (int)$row['active_users'],
        'total_apis' => (int)$pdo->query("SELECT COUNT(*) FROM huli_apis WHERE status = 'normal'")->fetchColumn(),
    ];

    $apis = [];
    $stmt = $pdo->query("SELECT a.id, a.name, a.endpoint, a.method, a.status, a.total_calls,
        COALESCE(g.calls,0) AS log_calls,
        COALESCE(g.ok,0) AS success_calls,
        COALESCE(g.calls,0) - COALESCE(g.ok,0) AS fail_calls,
        COALESCE(g.today_calls,0) AS today_calls,
        g.last_called
        FROM huli_apis a
        LEFT JOIN (
            SELECT api_id, COUNT(*) AS calls, COALESCE(SUM(is_success),0) AS ok,
                   SUM(request_time >= CURDATE()) AS today_calls, MAX(request_time) AS last_called
            FROM huli_api_logs GROUP BY api_id
        ) g ON g.api_id = a.id
        ORDER BY log_calls DESC, a.total_calls DESC, a.id ASC");
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $apis[] = [
            'id' => (int)$r['id'],
            'name' => $r['name'],
            'endpoint' => $r['endpoint'],
            'method' => $r['method'],
            'status' => $r['status'],
            'total_calls' => (int)$r['total_calls'],
            'log_calls' => (int)$r['log_calls'],
            'success_calls' => (int)$r['success_calls'],
            'fail_calls' => (int)$r['fail_calls'],
            'today_calls' => (int)$r['today_calls'],
            'last_called' => $r['last_called'],
        ];
    }

    $by_day = [];
    $stmt = $pdo->prepare("SELECT DATE(request_time) AS d, COUNT(*) AS calls,
        COALESCE(SUM(is_success),0) AS ok, COALESCE(SUM(billing_amount),0) AS billed
        FROM huli_api_logs WHERE request_time >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        GROUP BY d ORDER BY d ASC");
    $stmt->execute([$days - 1]);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $by_day[] = [
            'date' => $r['d'],
            'calls' => (int)$r['calls'],
            'success_calls' => (int)$r['ok'],
            'fail_calls' => (int)$r['calls'] - (int)$r['ok'],
            'billed' => (float)$r['billed'],
        ];
    }

    $by_user = [];
    $stmt = $pdo->query("SELECT COALESCE(user_id,0) AS user_id, COUNT(*) AS calls,
        COALESCE(SUM(is_success),0) AS ok, COALESCE(SUM(billing_amount),0) AS billed,
        MAX(request_time) AS last_called
        FROM huli_api_logs GROUP BY COALESCE(user_id,0) ORDER BY calls DESC LIMIT 500");
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $by_user[] = [
            'user_id' => (int)$r['user_id'],
            'calls' => (int)$r['calls'],
            'success_calls' => (int)$r['ok'],
            'fail_calls' => (int)$r['calls'] - (int)$r['ok'],
            'billed' => (float)$r['billed'],
            'last_called' => $r['last_called'],
        ];
    }

    $by_status = [];
    $stmt = $pdo->query("SELECT response_code, COUNT(*) AS calls FROM huli_api_logs GROUP BY response_code ORDER BY response_code ASC");
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $by_status[] = ['response_code' => (int)$r['response_code'], 'calls' => (int)$r['calls']];
    }

    echo json_encode([
        'code' => 200,
        'data' => [
            'range_days' => $days,
            'summary' => $summary,
            'apis' => $apis,
            'by_day' => $by_day,
            'by_user' => $by_user,
            'by_status' => $by_status,
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['code' => 500, 'msg' => '全站调用统计失败，请稍后重试'], JSON_UNESCAPED_UNICODE);
}
