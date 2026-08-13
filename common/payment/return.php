<?php
@error_reporting(0);
@ini_set('display_errors', 'Off');
$status = 'error';
$message = '本站充值已切换为爱发电主动查询模式，此页面不再使用。';
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <title>支付结果</title>
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-touch-fullscreen" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="stylesheet" type="text/css" href="../../../assets/css/materialdesignicons.min.css">
    <link rel="stylesheet" type="text/css" href="../../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="../../../assets/css/style.min.css">
    <style>
        .payment-result-card { max-width: 500px; margin: 0 auto; text-align: center; padding: 40px; }
        .payment-icon { width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 36px; background-color: #fee2e2; color: #991b1b; }
        .payment-title { font-weight: 600; margin-bottom: 15px; font-size: 24px; }
        .payment-message { color: #666; margin-bottom: 25px; font-size: 16px; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <div class="card payment-result-card">
                <div class="payment-icon">
                    <i class="mdi mdi-alert-circle"></i>
                </div>
                <h2 class="payment-title">操作结果</h2>
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
</body>
</html>
