<?php
session_start();
error_reporting(0);
ini_set('display_errors', 'Off');
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
if (!isset($_POST['plan_id'])) { header('Location: index.php'); exit; }
$rootPath = dirname(__DIR__, 3);
define('ROOT_PATH', $rootPath . '/');
if (!file_exists(ROOT_PATH . 'config.php')) {
    die("系统错误：配置文件丢失。路径: " . ROOT_PATH . 'config.php');
}
require_once ROOT_PATH . 'config.php';
require_once ROOT_PATH . 'common/payment/order_fulfillment.php';
$plan_id = intval($_POST['plan_id']);
$user_id = $_SESSION['user_id'];
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    huli_ensure_afdian_order_columns($pdo);
    $config = huli_get_afdian_config($pdo);
    if (!huli_is_afdian_configured($config)) {
        throw new Exception("支付网关未配置，请联系管理员。");
    }
    $stmt_plan = $pdo->prepare("SELECT * FROM huli_billing_plans WHERE id = ? AND is_active = 1");
    $stmt_plan->execute([$plan_id]);
    $plan = $stmt_plan->fetch(PDO::FETCH_ASSOC);
    if (!$plan) {
        throw new Exception("所选计费方案无效或已下架。");
    }
    $order_id = date('YmdHis') . mt_rand(10000, 99999);
    $match_code = huli_generate_match_code($pdo);
    $amount = $plan['price'];
    $sql = "INSERT INTO huli_orders (order_id, user_id, plan_id, amount, status, payment_method, match_code, provider) VALUES (?, ?, ?, ?, 'pending', 'afdian', ?, 'afdian')";
    $stmt_insert = $pdo->prepare($sql);
    $stmt_insert->execute([$order_id, $user_id, $plan_id, $amount, $match_code]);
    header("Location: recharge_status.php?order_id=" . urlencode($order_id));
    exit;
} catch (Exception $e) {
    $_SESSION['feedback_msg'] = '创建订单失败: ' . $e->getMessage();
    $_SESSION['feedback_type'] = 'error';
    header('Location: index.php');
    exit;
}
?>
