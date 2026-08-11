<?php
session_start();
error_reporting(0);
ini_set('display_errors', 'Off');
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
if (!isset($_POST['plan_id']) || !isset($_POST['payment_method'])) {
    header('Location: index.php');
    exit;
}
$rootPath = dirname(__DIR__, 3);
define('ROOT_PATH', $rootPath . '/');
if (!file_exists(ROOT_PATH . 'config.php')) {
    die("系统错误：配置文件丢失。路径: " . ROOT_PATH . 'config.php');
}
require_once ROOT_PATH . 'config.php';
$plan_id = intval($_POST['plan_id']);
$payment_method = trim($_POST['payment_method']);
$user_id = $_SESSION['user_id'];
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt_settings = $pdo->query("SELECT setting_key, setting_value FROM huli_settings WHERE setting_key IN ('epay_pid', 'epay_key', 'epay_url')");
    $epay_config = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR);
    if (empty($epay_config['epay_pid']) || empty($epay_config['epay_key']) || empty($epay_config['epay_url'])) {
        throw new Exception("支付网关未配置，请联系管理员。");
    }
    $stmt_plan = $pdo->prepare("SELECT * FROM huli_billing_plans WHERE id = ? AND is_active = 1");
    $stmt_plan->execute([$plan_id]);
    $plan = $stmt_plan->fetch(PDO::FETCH_ASSOC);

    if (!$plan) {
        throw new Exception("所选计费方案无效或已下架。");
    }
    $order_id = date('YmdHis') . mt_rand(10000, 99999);
    $amount = $plan['price'];
    $sql = "INSERT INTO huli_orders (order_id, user_id, plan_id, amount, status) VALUES (?, ?, ?, ?, 'pending')";
    $stmt_insert = $pdo->prepare($sql);
    $stmt_insert->execute([$order_id, $user_id, $plan_id, $amount]);
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
    $notify_url = $base_url . '/common/payment/notify.php';
    $return_url = $base_url . '/common/payment/return.php';
    $params = [
        "pid" => $epay_config['epay_pid'],
        "type" => $payment_method,
        "out_trade_no" => $order_id,
        "notify_url" => $notify_url,
        "return_url" => $return_url,
        "name" => $plan['name'],
        "money"	=> $amount,
        "sitename" => "huliapi"
    ];
    ksort($params);
    $string_to_sign = "";
    foreach ($params as $key => $val) {
        if ($val != '' && $key != 'sign' && $key != 'sign_type') {
            $string_to_sign .= $key . "=" . $val . "&";
        }
    }
    $string_to_sign = substr($string_to_sign, 0, -1);
    $sign = md5($string_to_sign . $epay_config['epay_key']);
    $params['sign'] = $sign;
    $params['sign_type'] = 'MD5';
    $payment_url = rtrim($epay_config['epay_url'], '/') . '/submit.php?' . http_build_query($params);
    if (strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
        echo json_encode([
            'success' => true,
            'payment_url' => $payment_url,
            'order_id' => $order_id
        ]);
    } else {
        header("Location: {$payment_url}");
    }
    exit;
} catch (Exception $e) {
    error_log('创建充值订单失败: ' . $e->getMessage());
    $error_msg = '创建订单失败，请稍后重试。';
    if (strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
        http_response_code($e->getCode() ?: 500);
        echo json_encode([
            'success' => false,
            'message' => $error_msg,
            'code' => $e->getCode() ?: 500
        ]);
    } else {
        $_SESSION['feedback_msg'] = $error_msg;
        $_SESSION['feedback_type'] = 'error';
        header('Location: index.php');
    }
    exit;
}
?>
