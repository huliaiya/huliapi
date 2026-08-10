<?php
session_start();
error_reporting(0);
ini_set('display_errors', 'Off');
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$rootPath = dirname(__DIR__, 3);
define('ROOT_PATH', $rootPath . '/');
if (!file_exists(ROOT_PATH . 'config.php')) {
    die("系统错误：配置文件丢失。路径: " . ROOT_PATH . 'config.php');
}
require_once ROOT_PATH . 'config.php';
require_once ROOT_PATH . 'common/TemplateManager.php';
$template = TemplateManager::getActiveUserTemplate();
$template_base_url = "/template/user/{$template}/";
$user_id = $_SESSION['user_id'];
$user_data = ['balance' => '0.00'];
$billing_plans = [];
$payment_methods = [];
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST .
        ";dbname=" . DB_NAME .
        ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt_get_user = $pdo->prepare("SELECT balance FROM huli_users WHERE id = ?");
    $stmt_get_user->execute([$user_id]);
    if ($row = $stmt_get_user->fetch(PDO::FETCH_ASSOC)) {
        $user_data = $row;
    }
    $billing_plans = $pdo->query("
        SELECT * FROM huli_billing_plans
        WHERE is_active = 1
        ORDER BY price ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
    $stmt_settings = $pdo->query("SELECT setting_key, setting_value FROM huli_settings");
    $settings = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR);
    $site_name = $settings['site_name'] ?? 'huliapi';
    $payment_map = [
        'alipay' => ['name' => '支付宝', 'icon' => 'zfb.png'],
        'wxpay' => ['name' => '微信支付', 'icon' => 'wx.png'],
        'qqpay' => ['name' => 'QQ钱包', 'icon' => 'qq.png']
    ];
    if(!empty($settings['payment_alipay_enabled'])) {
        $payment_methods['alipay'] = $payment_map['alipay'];
    }
    if(!empty($settings['payment_wxpay_enabled'])) {
        $payment_methods['wxpay'] = $payment_map['wxpay'];
    }
    if(!empty($settings['payment_qqpay_enabled'])) {
        $payment_methods['qqpay'] = $payment_map['qqpay'];
    }
} catch (PDOException $e) {
    die('数据库连接错误：' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="zh">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-touch-fullscreen" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="stylesheet" type="text/css" href="../../../assets/css/materialdesignicons.min.css">
    <link rel="stylesheet" type="text/css" href="../../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="../../../assets/css/style.min.css">
    <style>
        .balance-display {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 20px;
            text-align: center;
            padding: 15px;
            border-radius: 4px;
        }
        .plan-card {
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 15px;
        }
        .plan-card:hover, .plan-card.selected {
            border-color: #4a69bd;
            box-shadow: 0 0 0 3px rgba(74, 105, 189, 0.1);
        }
        .payment-method {
            cursor: pointer;
        }
        .payment-method.selected {
            border-color: #4a69bd;
        }
        .payment-method img {
            width: 40px;
            margin-right: 15px;
        }
        .card-title {
            font-weight: 600;
            margin-bottom: 20px;
        }
.d-grid {
    margin-bottom: 30px;
}
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <header class="card-header">
                    <div class="card-title"><i class="mdi mdi-currency-cny me-2"></i>在线充值</div>
                </header>
                <div class="card-body">
                    <div class="balance-display mb-4">
                        当前余额：<span>¥<?php echo number_format($user_data['balance'], 2); ?></span>
                    </div>
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <div class="card-title"><i class="mdi mdi-wallet-giftcard me-2"></i>选择充值方案</div>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <?php if(empty($billing_plans)): ?>
                                            <div class="col-12">
                                                <div class="alert alert-warning">当前暂无可用的充值方案。</div>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach($billing_plans as $plan): ?>
                                            <div class="col-md-4 mb-3">
                                                <div class="card plan-card" data-plan-id="<?php echo $plan['id']; ?>">
                                                    <div class="card-body text-center">
                                                        <h5><?php echo htmlspecialchars($plan['name']); ?></h5>
                                                        <div class="text-primary fw-bold fs-3">¥<?php echo number_format($plan['price'], 2); ?></div>
                                                        <?php if ($plan['billing_type'] === 'balance'): ?>
                                                        <p>可得余额 ¥<?php echo number_format($plan['balance_to_add'], 2); ?></p>
                                                        <?php elseif ($plan['billing_type'] === 'points'): ?>
                                                        <p>可得点数 <?php echo number_format($plan['points_to_add']); ?></p>
                                                        <?php else: ?>
                                                        <p>可得超级会员 <?php echo number_format($plan['membership_days']); ?> 天</p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="card mb-4">
                                <div class="card-header">
                                    <div class="card-title"><i class="mdi mdi-credit-card-multiple me-2"></i>选择支付方式</div>
                                </div>
                                <div class="card-body">
                                    <?php if(empty($payment_methods)): ?>
                                        <div class="alert alert-warning">当前暂无可用的支付方式。</div>
                                    <?php else: ?>
                                        <div class="row">
                                            <?php foreach($payment_methods as $method_key => $method_info): ?>
                                            <div class="col-md-6 mb-3">
                                                <div class="card payment-method" data-method="<?php echo $method_key; ?>">
                                                    <div class="card-body d-flex align-items-center">
                                                        <img src="../../../common/payment/<?php echo $method_info['icon']; ?>" alt="<?php echo $method_info['name']; ?>">
                                                        <span><?php echo $method_info['name']; ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="d-grid">
                                <button type="button" id="confirm-payment-btn" class="btn btn-primary btn-lg" disabled>
                                    <i class="mdi mdi-currency-cny me-1"></i> 立即支付
                                </button>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-header">
                                    <div class="card-title"><i class="mdi mdi-information-outline me-2"></i>充值说明</div>
                                </div>
                                <div class="card-body">
                                    <ul class="list-unstyled">
                                        <li class="mb-2"><i class="mdi mdi-check-circle-outline text-success me-2"></i> 充值后余额可用于所有计费接口</li>
                                        <li class="mb-2"><i class="mdi mdi-check-circle-outline text-success me-2"></i> 充值金额永久有效</li>
                                        <li class="mb-2"><i class="mdi mdi-check-circle-outline text-success me-2"></i> 充值成功后即时到账</li>
                                        <li class="mb-2"><i class="mdi mdi-check-circle-outline text-success me-2"></i> 如有问题请联系客服</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="../../../assets/js/jquery.min.js"></script>
<script src="../../../assets/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    let selectedPlanId = null;
    let selectedPaymentMethod = null;
    $('.plan-card').click(function() {
        $('.plan-card').removeClass('border-primary');
        $(this).addClass('border-primary');
        selectedPlanId = $(this).data('plan-id');
        checkSelections();
    });
    $('.payment-method').click(function() {
        $('.payment-method').removeClass('border-primary');
        $(this).addClass('border-primary');
        selectedPaymentMethod = $(this).data('method');
        checkSelections();
    });
    function checkSelections() {
        if (selectedPlanId && selectedPaymentMethod) {
            $('#confirm-payment-btn').prop('disabled', false);
        } else {
            $('#confirm-payment-btn').prop('disabled', true);
        }
    }
    $('#confirm-payment-btn').click(function() {
        if (!selectedPlanId || !selectedPaymentMethod) {
            alert('请先选择充值方案和支付方式');
            return;
        }
        const btn = $(this);
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>处理中...');
        $.ajax({
            url: 'recharge.php',
            type: 'POST',
            dataType: 'json',
            data: {
                plan_id: selectedPlanId,
                payment_method: selectedPaymentMethod
            },
            success: function(response) {
                if(response.success && response.payment_url) {
                    window.location.href = response.payment_url;
                } else {
                    alert(response.message || '支付处理失败');
                    btn.prop('disabled', false).html('<i class="mdi mdi-currency-cny me-1"></i> 立即支付');
                }
            },
            error: function(xhr) {
                let errorMsg = '支付请求失败，请检查网络';
                if(xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                alert(errorMsg);
                btn.prop('disabled', false).html('<i class="mdi mdi-currency-cny me-1"></i> 立即支付');
            }
        });
    });
});
</script>
</body>
</html>
