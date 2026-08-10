<?php
error_reporting(E_ALL);
ini_set('display_errors', 'On');
$log_file = __DIR__ . '/notify_log.txt';
function log_message($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}
log_message("=== 新的回调请求 ===");
log_message("请求方式: " . $_SERVER['REQUEST_METHOD']);
log_message("GET参数: " . json_encode($_GET));
log_message("POST参数: " . json_encode($_POST));
log_message("REQUEST参数: " . json_encode($_REQUEST));
log_message("原始POST数据: " . file_get_contents('php://input'));
if (!file_exists('../../config.php')) { 
    log_message("错误: 配置文件不存在");
    die("fail"); 
}
if (!file_exists('lib/epaycore.php')) { 
    log_message("错误: 支付核心库不存在");
    die("fail"); 
}
require_once '../../config.php';
require_once 'lib/epaycore.php';
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt_settings = $pdo->query("SELECT setting_key, setting_value FROM sl_settings WHERE setting_key IN ('epay_pid', 'epay_key', 'epay_url')");
    $epay_db_config = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR);
    log_message("支付配置: " . json_encode($epay_db_config));
    $epay_sdk_config = [
        'pid' => $epay_db_config['epay_pid'],
        'key' => $epay_db_config['epay_key'],
        'apiurl' => $epay_db_config['epay_url']
    ];
    $epay = new EpayCore($epay_sdk_config);
    $verify_result = $epay->verifyNotify();
    log_message("签名验证结果: " . ($verify_result ? '成功' : '失败'));
    if ($verify_result) {
        $out_trade_no = $_REQUEST['out_trade_no'] ?? '未知';
        $trade_no = $_REQUEST['trade_no'] ?? '未知';
        $trade_status = $_REQUEST['trade_status'] ?? '未知';
        log_message("订单号: $out_trade_no");
        log_message("交易号: $trade_no");
        log_message("交易状态: $trade_status");
        if ($trade_status !== 'TRADE_SUCCESS') {
            log_message("交易状态不是TRADE_SUCCESS，跳过处理");
            echo "success";
            exit;
        }
        $pdo->beginTransaction();
        try {
            $stmt_order = $pdo->prepare("SELECT * FROM sl_orders WHERE order_id = ? AND status = 'pending' FOR UPDATE");
            $stmt_order->execute([$out_trade_no]);
            $order = $stmt_order->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                log_message("订单不存在或已处理: $out_trade_no");
                $pdo->rollBack();
                echo "success";
                exit;
            }
            log_message("订单信息: " . json_encode($order));
            $stmt_plan = $pdo->prepare("SELECT billing_type, balance_to_add, points_to_add, membership_days FROM sl_billing_plans WHERE id = ?");
            $stmt_plan->execute([$order['plan_id']]);
            $plan = $stmt_plan->fetch(PDO::FETCH_ASSOC);
            if (!$plan) {
                log_message("套餐不存在: plan_id=" . $order['plan_id']);
                $pdo->rollBack();
                echo "success";
                exit;
            }
            log_message("套餐信息: " . json_encode($plan));
            $updated = false;
            if ($plan['balance_to_add'] > 0) {
                $stmt_update_user = $pdo->prepare("UPDATE sl_users SET balance = balance + ? WHERE id = ?");
                $stmt_update_user->execute([$plan['balance_to_add'], $order['user_id']]);
                log_message("充值余额: " . $plan['balance_to_add'] . " 元");
                $updated = true;
            }
            if ($plan['points_to_add'] > 0) {
                $stmt_update_user = $pdo->prepare("UPDATE sl_users SET points = points + ? WHERE id = ?");
                $stmt_update_user->execute([$plan['points_to_add'], $order['user_id']]);
                log_message("充值点数: " . $plan['points_to_add']);
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
                log_message("充值会员: " . $plan['membership_days'] . " 天");
                $updated = true;
            }
            if ($updated) {
                $stmt_update_order = $pdo->prepare("UPDATE sl_orders SET status = 'paid', paid_at = CURRENT_TIMESTAMP, payment_method = ? WHERE id = ?");
                $stmt_update_order->execute([$_REQUEST['type'] ?? 'unknown', $order['id']]);
                $pdo->commit();
                log_message("订单处理成功，已提交事务");
            } else {
                $pdo->rollBack();
                log_message("订单处理失败，无更新操作");
            }
            echo "success";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            log_message("订单处理异常: " . $e->getMessage());
            echo "fail";
        }
    } else {
        log_message("签名验证失败");
        echo "fail";
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    log_message("系统异常: " . $e->getMessage());
    echo "fail";
}
exit;
?>