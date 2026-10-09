<?php require_once __DIR__ . '/../common/security/api_auth.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $endpoint = trim($_GET['endpoint'] ?? '');

    $data = [
        'total_apis' => 0,
        'total_calls' => 0,
        'success_calls' => 0,
        'fail_calls' => 0,
        'today_calls' => 0,
        'today_success' => 0,
        'week_calls' => 0,
        'api' => null,
    ];

    if ($endpoint !== '') {
        $stmt = $pdo->prepare("SELECT id, category_id, name, description, endpoint, method, type, total_calls, visibility, is_billable, price_per_call, points_per_call, status FROM huli_apis WHERE endpoint = ? LIMIT 1");
        $stmt->execute([$endpoint]);
        $api = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$api) {
            echo json_encode(['code' => 404, 'msg' => '未找到指定接口'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $api_id = (int)$api['id'];
        $data['api'] = $api;
        $data['total_calls'] = (int)$api['total_calls'];

        $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt, COALESCE(SUM(is_success),0) AS okc FROM huli_api_logs WHERE api_id = ?");
        $stmt->execute([$api_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $data['success_calls'] = (int)$row['okc'];
        $data['fail_calls'] = (int)$row['cnt'] - (int)$row['okc'];

        $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt, COALESCE(SUM(is_success),0) AS okc FROM huli_api_logs WHERE api_id = ? AND request_time >= CURDATE()");
        $stmt->execute([$api_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $data['today_calls'] = (int)$row['cnt'];
        $data['today_success'] = (int)$row['okc'];

        $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM huli_api_logs WHERE api_id = ? AND request_time >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)");
        $stmt->execute([$api_id]);
        $data['week_calls'] = (int)$stmt->fetchColumn();

        $data['total_apis'] = (int)$pdo->query("SELECT COUNT(*) FROM huli_apis WHERE status = 'normal'")->fetchColumn();
    } else {
        $data['total_apis'] = (int)$pdo->query("SELECT COUNT(*) FROM huli_apis WHERE status = 'normal'")->fetchColumn();
        $data['total_calls'] = (int)$pdo->query("SELECT COALESCE(SUM(total_calls),0) FROM huli_apis")->fetchColumn();

        $row = $pdo->query("SELECT COUNT(*) AS cnt, COALESCE(SUM(is_success),0) AS okc FROM huli_api_logs")->fetch(PDO::FETCH_ASSOC);
        $data['success_calls'] = (int)$row['okc'];
        $data['fail_calls'] = (int)$row['cnt'] - (int)$row['okc'];

        $row = $pdo->query("SELECT COUNT(*) AS cnt, COALESCE(SUM(is_success),0) AS okc FROM huli_api_logs WHERE request_time >= CURDATE()")->fetch(PDO::FETCH_ASSOC);
        $data['today_calls'] = (int)$row['cnt'];
        $data['today_success'] = (int)$row['okc'];

        $data['week_calls'] = (int)$pdo->query("SELECT COUNT(*) FROM huli_api_logs WHERE request_time >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)")->fetchColumn();
    }

    echo json_encode(['code' => 200, 'data' => $data], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['code' => 500, 'msg' => '调用统计失败，请稍后重试'], JSON_UNESCAPED_UNICODE);
}