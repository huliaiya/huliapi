<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (!file_exists('../../config.php')) { die("系统错误：配置文件丢失。"); }
if (!file_exists('lib/epaycore.php')) { die("系统错误：支付核心库丢失。"); }
require_once '../../config.php';
require_once 'lib/epaycore.php';
$status = 'error'; $message = '支付验证失败，请联系管理员。';
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
    $epay = new EpayCore($epay_sdk_config);
    $verify_result = $epay->verifyReturn();
    if($verify_result) {
        if($_GET['trade_status'] == 'TRADE_SUCCESS') {
            $status = 'success';
            $message = '恭喜您，支付成功！我们正在为您处理订单，请稍后在用户中心查看余额变化。';
        } else {
            $message = '支付失败或尚未完成，交易状态: ' . htmlspecialchars($_GET['trade_status']);
        }
    }
} catch (Exception $e) { }
?>

<!DOCTYPE html>
<html lang="zh">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <title>支付结果 - <?php echo htmlspecialchars($site_name); ?></title>
    <link rel="shortcut icon" type="image/x-icon" href="https://picui.ogmua.cn/s1/2026/05/26/6a156ea77f458.webp">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-touch-fullscreen" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="stylesheet" type="text/css" href="../../../assets/css/materialdesignicons.min.css">
    <link rel="stylesheet" type="text/css" href="../../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="../../../assets/css/style.min.css">
    <style>
        .payment-result-card {
            max-width: 500px;
            margin: 0 auto;
            text-align: center;
            padding: 40px;
        }
        .payment-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 36px;
        }
        .payment-icon.success {
            background-color: #dcfce7;
            color: #166534;
        }
        .payment-icon.error {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .payment-title {
            font-weight: 600;
            margin-bottom: 15px;
            font-size: 24px;
        }
        .payment-message {
            color: #666;
            margin-bottom: 25px;
            font-size: 16px;
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <div class="card payment-result-card">
                <?php if ($status === 'success'): ?>
                <div class="payment-icon success">
                    <i class="mdi mdi-check-circle"></i>
                </div>
                <h2 class="payment-title">支付成功</h2>
                <?php else: ?>
                <div class="payment-icon error">
                    <i class="mdi mdi-alert-circle"></i>
                </div>
                <h2 class="payment-title">操作结果</h2>
                <?php endif; ?>
                <p class="payment-message"><?php echo htmlspecialchars($message); ?></p>
                <a href="../../user/" class="btn btn-primary">
                    <i class="mdi mdi-arrow-left me-1"></i>返回用户中心
                </a>
            </div>
        </div>
    </div>
</div>
<script type="text/javascript" src="../../../assets/js/jquery.min.js"></script>
<script type="text/javascript" src="../../../assets/js/popper.min.js"></script>
<script type="text/javascript" src="../../../assets/js/bootstrap.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($status === 'success'): ?>
    setTimeout(function() {
        window.location.href = '../../user/';
    }, 5000);
    <?php endif; ?>
});
</script>
</body>
</html>