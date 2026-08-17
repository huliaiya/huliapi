<?php
require_once __DIR__ . '/../session_boot.php';
@error_reporting(0);
@ini_set('display_errors', 'Off');
header('Content-Type: application/json; charset=utf-8');

function huli_query_json($data, $code = 200)
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    huli_query_json(['success' => false, 'code' => 401, 'message' => '请先登录'], 401);
}

$rootPath = dirname(__DIR__, 2);
if (!file_exists($rootPath . '/config.php')) {
    huli_query_json(['success' => false, 'code' => 500, 'message' => '配置文件丢失'], 500);
}
require_once $rootPath . '/config.php';
require_once __DIR__ . '/afdian_client.php';
require_once __DIR__ . '/order_fulfillment.php';

$orderId = trim((string)($_POST['order_id'] ?? ''));
if ($orderId === '') {
    huli_query_json(['success' => false, 'code' => 400, 'message' => '缺少订单号'], 400);
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    huli_ensure_afdian_order_columns($pdo);

    $stmt = $pdo->prepare("SELECT * FROM huli_orders WHERE order_id = ? AND user_id = ?");
    $stmt->execute([$orderId, $_SESSION['user_id']]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        huli_query_json(['success' => false, 'code' => 404, 'message' => '订单不存在'], 404);
    }

    if ($order['status'] === 'paid') {
        huli_query_json(['success' => true, 'status' => 'paid', 'message' => '充值成功，权益已到账']);
    }
    if ($order['status'] === 'canceled' || $order['status'] === 'failed') {
        huli_query_json(['success' => true, 'status' => 'canceled', 'message' => '订单已作废，请重新下单']);
    }

    $createdAt = strtotime($order['created_at']);
    $expired = time() - $createdAt > HULI_PAYMENT_TTL;

    if ($expired) {
        $stmt = $pdo->prepare("UPDATE huli_orders SET status = 'canceled', failure_reason = '超过支付时限未到账，已作废' WHERE id = ? AND status = 'pending'");
        $stmt->execute([$order['id']]);
        huli_query_json(['success' => true, 'status' => 'canceled', 'message' => '订单已超过5分钟支付时限，已作废']);
    }

    $config = huli_get_afdian_config($pdo);
    if (!huli_is_afdian_configured($config)) {
        huli_query_json(['success' => true, 'status' => 'pending', 'message' => '支付网关暂未配置']);
    }

    $lastQueried = $order['last_queried_at'] ? strtotime($order['last_queried_at']) : 0;
    $shouldQuery = time() - $lastQueried >= 20;

    $stmt = $pdo->prepare("UPDATE huli_orders SET last_queried_at = CURRENT_TIMESTAMP, query_attempts = query_attempts + 1 WHERE id = ?");
    $stmt->execute([$order['id']]);

    if (!$shouldQuery) {
        huli_query_json(['success' => true, 'status' => 'pending', 'message' => '等待支付确认中']);
    }

    $client = new AfdianClient($config['user_id'], $config['token']);
    $afdianOrder = $client->findOrderByRemark($order['match_code'], 2);

    if ($afdianOrder && (int)($afdianOrder['status'] ?? 0) === 2) {
        $result = huli_fulfill_afdian_order($pdo, $order['id'], $afdianOrder);
        if ($result['ok']) {
            huli_query_json(['success' => true, 'status' => 'paid', 'message' => '充值成功，权益已到账']);
        }
        huli_query_json(['success' => true, 'status' => 'pending', 'message' => $result['message']]);
    }

    huli_query_json(['success' => true, 'status' => 'pending', 'message' => '等待支付确认中']);
} catch (Exception $e) {
    huli_query_json(['success' => false, 'code' => 500, 'message' => '查询失败，请稍后重试'], 500);
}
