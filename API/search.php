<?php require_once __DIR__ . '/../common/security/api_auth.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $q           = trim($_GET['q'] ?? '');
    $category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
    $page        = max(1, (int)($_GET['page'] ?? 1));
    $per_page    = (int)($_GET['per_page'] ?? 20);
    if ($per_page < 1) $per_page = 1;
    if ($per_page > 100) $per_page = 100;
    $offset      = ($page - 1) * $per_page;

    $where  = ["status = 'normal'"];
    $params = [];
    if ($q !== '') {
        $where[] = "(name LIKE ? OR description LIKE ? OR endpoint LIKE ?)";
        $like = '%' . $q . '%';
        array_push($params, $like, $like, $like);
    }
    if ($category_id > 0) {
        $where[] = "category_id = ?";
        $params[] = $category_id;
    }
    $where_sql = implode(' AND ', $where);

    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM huli_apis WHERE " . $where_sql);
    $stmt_count->execute($params);
    $total = (int)$stmt_count->fetchColumn();

    $stmt = $pdo->prepare("SELECT id, category_id, name, description, endpoint, method, type, parameters, request_example, response_format, total_calls, visibility, is_billable, price_per_call, points_per_call
                           FROM huli_apis WHERE " . $where_sql . " ORDER BY total_calls DESC, id ASC LIMIT " . $offset . ", " . $per_page);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $items = [];
    foreach ($rows as $row) {
        $row['parameters'] = json_decode($row['parameters'], true) ?: [];
        $items[] = $row;
    }

    echo json_encode([
        'code' => 200,
        'data' => [
            'total'   => $total,
            'page'    => $page,
            'per_page'=> $per_page,
            'items'   => $items,
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['code' => 500, 'msg' => '接口搜索失败，请稍后重试'], JSON_UNESCAPED_UNICODE);
}