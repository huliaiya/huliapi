<?php
if (!defined('HULI_QUERY_PENDING_ORDERS_LOADED')) {
    define('HULI_QUERY_PENDING_ORDERS_LOADED', true);

    require_once dirname(__DIR__) . '/common/payment/afdian_client.php';
    require_once dirname(__DIR__) . '/common/payment/order_fulfillment.php';

    function huli_query_pending_afdian_orders(PDO $pdo, $limit = 50)
    {
        huli_ensure_afdian_order_columns($pdo);
        $config = huli_get_afdian_config($pdo);
        $configured = huli_is_afdian_configured($config);
        $client = $configured ? new AfdianClient($config['user_id'], $config['token']) : null;

        $result = ['checked' => 0, 'fulfilled' => 0, 'canceled' => 0, 'skipped' => 0, 'configured' => $configured];

        $stmt = $pdo->prepare("SELECT * FROM huli_orders WHERE status = 'pending' AND (last_queried_at IS NULL OR last_queried_at < NOW() - INTERVAL 60 SECOND) ORDER BY created_at ASC LIMIT " . intval($limit));
        $stmt->execute();
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($orders as $order) {
            $result['checked']++;
            $stmt = $pdo->prepare("UPDATE huli_orders SET last_queried_at = CURRENT_TIMESTAMP, query_attempts = query_attempts + 1 WHERE id = ?");
            $stmt->execute([$order['id']]);

            $createdAt = strtotime($order['created_at']);
            $expired = time() - $createdAt > HULI_PAYMENT_TTL;

            $matched = false;
            if ($client && !empty($order['match_code'])) {
                try {
                    $afdianOrder = $client->findOrderByRemark($order['match_code'], 3);
                    if ($afdianOrder && (int)($afdianOrder['status'] ?? 0) === 2) {
                        $fulfill = huli_fulfill_afdian_order($pdo, $order['id'], $afdianOrder);
                        if ($fulfill['ok']) {
                            $result['fulfilled']++;
                            $matched = true;
                        }
                    }
                } catch (Exception $e) {
                }
            }

            if (!$matched) {
                if ($expired) {
                    $stmt = $pdo->prepare("UPDATE huli_orders SET status = 'canceled', failure_reason = '超过支付时限未到账，已作废' WHERE id = ? AND status = 'pending'");
                    $stmt->execute([$order['id']]);
                    $result['canceled']++;
                } else {
                    $result['skipped']++;
                }
            }
        }
        return $result;
    }
}

if (PHP_SAPI === 'cli' && isset($argv) && realpath($argv[0]) === __FILE__) {
    @error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
    $root = dirname(__DIR__);
    if (!file_exists($root . '/config.php')) { fwrite(STDERR, "config.php missing\n"); exit(2); }
    require_once $root . '/config.php';
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (Throwable $e) { fwrite(STDERR, "DB error: " . $e->getMessage() . "\n"); exit(3); }
    $r = huli_query_pending_afdian_orders($pdo);
    printf("afdian_query checked=%d fulfilled=%d canceled=%d skipped=%d configured=%s\n", $r['checked'], $r['fulfilled'], $r['canceled'], $r['skipped'], $r['configured'] ? 'yes' : 'no');
    exit(0);
}
