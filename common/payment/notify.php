<?php
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (!file_exists('../../config.php')) { die("fail"); }
if (!file_exists('lib/epaycore.php')) { die("fail"); }
require_once '../../config.php';
require_once 'lib/epaycore.php';
function log_notify($msg) {
    file_put_contents('notify_log.txt', date('Y-m-d H:i:s') . ' ' . $msg . "\n", FILE_APPEND);
}
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt_settings = $pdo->query("SELECT setting_key, setting_value FROM sl_settings WHERE setting_key IN ('epay_pid', 'epay_key', 'epay_url')");
    $epay_db_config = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR);
    $epay_sdk_config = [
        'pid' => $epay_db_config['epay_pid'],
        'key' => $epay_db_config['epay_key'],
        'apiurl' => $epay_db_config['epay_url']
    ];
    log_notify("=== 新的回调请求 ===");
    log_notify("请求方式: " . $_SERVER['REQUEST_METHOD']);
    log_notify("GET参数: " . json_encode($_GET));
    log_notify("POST参数: " . json_encode($_POST));
    log_notify("原始POST: " . file_get_contents('php://input'));
    log_notify("epay_pid: " . $epay_db_config['epay_pid']);
    $epay = new EpayCore($epay_sdk_config);
    $verify_result = $epay->verifyNotify();
    log_notify("签名验证结果: " . ($verify_result ? '成功' : '失败'));
    if ($verify_result) {
        $out_trade_no = $_REQUEST['out_trade_no'];
        $trade_no = $_REQUEST['trade_no'];
        $trade_status = $_REQUEST['trade_status'];
        if ($trade_status !== 'TRADE_SUCCESS') {
            echo "success";
            exit;
        }
        $pdo->beginTransaction();
        try {
            $stmt_order = $pdo->prepare("SELECT * FROM sl_orders WHERE order_id = ? AND status = 'pending' FOR UPDATE");
            $stmt_order->execute([$out_trade_no]);
            $order = $stmt_order->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                $pdo->rollBack();
                echo "success";
                exit;
            }
            $stmt_plan = $pdo->prepare("SELECT billing_type, balance_to_add, points_to_add, membership_days FROM sl_billing_plans WHERE id = ?");
            $stmt_plan->execute([$order['plan_id']]);
            $plan = $stmt_plan->fetch(PDO::FETCH_ASSOC);
            if (!$plan) {
                $pdo->rollBack();
                echo "success";
                exit;
            }
            $updated = false;
            if ($plan['balance_to_add'] > 0) {
                $stmt_update_user = $pdo->prepare("UPDATE sl_users SET balance = balance + ? WHERE id = ?");
                $stmt_update_user->execute([$plan['balance_to_add'], $order['user_id']]);
                $updated = true;
            }
            if ($plan['points_to_add'] > 0) {
                $stmt_update_user = $pdo->prepare("UPDATE sl_users SET points = points + ? WHERE id = ?");
                $stmt_update_user->execute([$plan['points_to_add'], $order['user_id']]);
                $updated = true;
            }
            if ($plan['membership_days'] > 0) {
                $stmt_check_user = $pdo->prepare("SELECT membership_expire FROM sl_users WHERE id = ?");
                $stmt_check_user->execute([$order['user_id']]);
                $user = $stmt_check_user->fetch(PDO::FETCH_ASSOC);
                $expire_date = null;
                if ($user && $user['membership_expire'] && strtotime($user['membership_expire']) > time()) {
                    $expire_date = "DATE_ADD(membership_expire, INTERVAL ? DAY)";
                } else {
                    $expire_date = "DATE_ADD(NOW(), INTERVAL ? DAY)";
                }
                $stmt_update_user = $pdo->prepare("UPDATE sl_users SET membership_level = 'super', membership_expire = $expire_date WHERE id = ?");
                $stmt_update_user->execute([$plan['membership_days'], $order['user_id']]);
                $updated = true;
            }
            if ($updated) {
                $stmt_update_order = $pdo->prepare("UPDATE sl_orders SET status = 'paid', paid_at = CURRENT_TIMESTAMP, payment_method = ? WHERE id = ?");
                $stmt_update_order->execute([$_REQUEST['type'] ?? 'unknown', $order['id']]);
                $pdo->commit();
            } else {
                $pdo->rollBack();
            }
            echo "success";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo "fail";
        }
    } else {
        echo "fail";
    }
} catch (Exception $e) { if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); } echo "fail"; }
exit;