<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
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
$feedback_msg = ''; 
$feedback_type = '';
$user_id = $_SESSION['user_id'];
$user_info = [
    'username' => $_SESSION['user_username'], 
    'email' => $_SESSION['user_email']
];
$user_data = [
    'api_key' => 'N/A', 
    'call_count' => 0, 
    'balance' => '0.00',
    'points' => 0,
    'created_at' => 'N/A'
];
$recent_logs = []; 
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
    if (isset($_GET['action']) && $_GET['action'] === 'regenerate_key') {
        $new_key = bin2hex(random_bytes(32));
        $stmt_update = $pdo->prepare("UPDATE sl_users SET api_key = ? WHERE id = ?");
        $stmt_update->execute([$new_key, $user_id]);    
        if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'new_key' => $new_key]);
            exit;
        } else {
            $_SESSION['feedback_msg'] = 'API密钥已成功重新生成！';
            $_SESSION['feedback_type'] = 'success';
            header('Location: index.php'); 
            exit;
        }
    }
    if(isset($_POST['cdkey']) && !empty($_POST['cdkey'])) {
        $cdkey = trim($_POST['cdkey']);       
        $stmt = $pdo->prepare("SELECT * FROM sl_cdkeys WHERE cdkey = ? AND status = 'unused'");
        $stmt->execute([$cdkey]);
        $cdkey_info = $stmt->fetch(PDO::FETCH_ASSOC);
        if(!$cdkey_info) {
            die(json_encode(['success' => false, 'message' => '卡密无效或已被使用']));
        }
        $valid_types = ['balance', 'points'];
        $cdkey_type = isset($cdkey_info['type']) && in_array($cdkey_info['type'], $valid_types) ? $cdkey_info['type'] : 'balance';
        $value = $cdkey_type === 'balance' ? $cdkey_info['balance'] : $cdkey_info['points'];
        if($value <= 0) {
            die(json_encode(['success' => false, 'message' => '卡密价值无效']));
        }
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE sl_cdkeys SET status = 'used', used_by_user_id = ?, used_at = NOW() WHERE cdkey = ?");
            $stmt->execute([$user_id, $cdkey]);
            if($cdkey_type === 'balance') {
                $stmt = $pdo->prepare("UPDATE sl_users SET balance = balance + ? WHERE id = ?");
                $transaction_desc = "余额卡密充值: {$cdkey}";
            } else {
                $stmt = $pdo->prepare("UPDATE sl_users SET points = points + ? WHERE id = ?");
                $transaction_desc = "点数卡密充值: {$cdkey}";
            }
            $stmt->execute([$value, $user_id]);
            $stmt = $pdo->prepare("INSERT INTO sl_transactions (user_id, amount, type, description, created_at) VALUES (?, ?, 'cdkey', ?, NOW())");
            $stmt->execute([$user_id, $value, $transaction_desc]);
            if($cdkey_type === 'balance') {
                $stmt_get = $pdo->prepare("SELECT balance FROM sl_users WHERE id = ?");
                $stmt_get->execute([$user_id]);
                $new_value = $stmt_get->fetchColumn();
                $formatted_value = number_format($new_value, 3, '.', '');
            } else {
                $stmt_get = $pdo->prepare("SELECT points FROM sl_users WHERE id = ?");
                $stmt_get->execute([$user_id]);
                $new_value = $stmt_get->fetchColumn();
                $formatted_value = number_format($new_value);
            }
            $pdo->commit();
            die(json_encode([
                'success' => true,
                'type' => $cdkey_type,
                'message' => $cdkey_type === 'balance' ? 
                    "成功充值 ¥{$value} 元" : 
                    "成功充值 {$value} 点",
                'new_value' => $formatted_value
            ]));
        } catch (Exception $e) {
            $pdo->rollBack();
            die(json_encode(['success' => false, 'message' => '兑换失败，请稍后重试：' . $e->getMessage()]));
        }
    }
    if(isset($_SESSION['feedback_msg'])) {
        $feedback_msg = $_SESSION['feedback_msg'];
        $feedback_type = $_SESSION['feedback_type'];
        unset($_SESSION['feedback_msg'], $_SESSION['feedback_type']);
    }
    $stmt_get_user = $pdo->prepare("SELECT api_key, call_count, balance, points, created_at, membership_level, membership_expire FROM sl_users WHERE id = ?");
    $stmt_get_user->execute([$user_id]);
    $fetched_data = $stmt_get_user->fetch(PDO::FETCH_ASSOC);
    if ($fetched_data) { 
        $user_data = $fetched_data; 
        $user_data['balance'] = number_format($user_data['balance'], 3, '.', '');
        $user_data['points'] = intval($user_data['points']);
        $user_data['membership_level'] = $fetched_data['membership_level'] ?? 'normal';
        $user_data['membership_expire'] = $fetched_data['membership_expire'] ?? null;
    } else { 
        session_destroy(); 
        header('Location: login.php'); 
        exit; 
    }
    $stmt_get_logs = $pdo->prepare("
        SELECT l.request_time, l.is_success, a.name as api_name 
        FROM sl_api_logs l 
        JOIN sl_apis a ON l.api_id = a.id 
        WHERE l.user_id = ? 
        ORDER BY l.request_time DESC 
        LIMIT 5
    ");
    $stmt_get_logs->execute([$user_id]);
    $recent_logs = $stmt_get_logs->fetchAll(PDO::FETCH_ASSOC);
    $billing_plans = $pdo->query("
        SELECT * FROM sl_billing_plans 
        WHERE is_active = 1 
        ORDER BY price ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
    $stmt_settings = $pdo->query("SELECT setting_key, setting_value FROM sl_settings");
    $settings = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR);
    $site_name = $settings['site_name'] ?? '白茶API';
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
    $feedback_msg = '无法加载您的数据，请稍后重试。'; 
    $feedback_type = 'error'; 
}
?>

<!DOCTYPE html>
<html lang="zh">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <title>用户中心 - <?php echo htmlspecialchars($site_name); ?></title>
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-touch-fullscreen" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="stylesheet" type="text/css" href="../../../assets/css/materialdesignicons.min.css">
    <link rel="stylesheet" type="text/css" href="../../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="../../../assets/css/style.min.css">
    <style>
        .api-key-box {
            background-color: transparent;
            padding: 15px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            word-break: break-all;
            margin-bottom: 15px;
        }
        .stat-card {
            margin-bottom: 20px;
        }
        .stat-value {
            font-size: 28px;
            font-weight: 700;
        }
        .activity-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
        }
        .activity-icon.success {
            background-color: #dcfce7;
            color: #166534;
        }
        .activity-icon.fail {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .nav-tabs .nav-link.active {
            font-weight: 600;
        }
        .card-title {
            font-weight: 600;
            margin-bottom: 20px;
        }
        .badge-balance {
            background-color: #d1fae5;
            color: #065f46;
        }
        .badge-points {
            background-color: #dbeafe;
            color: #1e40af;
        }
        .plan-card {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .plan-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .plan-card.border-primary {
            border: 2px solid #0d6efd;
            background-color: rgba(13, 110, 253, 0.05);
        }
        .payment-method {
            cursor: pointer;
        }
        .payment-method.border-primary {
            border: 2px solid #0d6efd;
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <header class="card-header">
                    <div class="card-title">用户中心</div>
                </header>
                <div class="card-body">
                    <?php if ($feedback_msg): ?>
                    <div class="alert alert-<?php echo $feedback_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                        <?php echo $feedback_msg; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>
                    <ul class="nav nav-tabs">
                        <li class="nav-item">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#profile" type="button">个人信息</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#api" type="button">API管理</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#statistics" type="button">使用统计</button>
                        </li>
                    </ul>
                    <div class="tab-content mt-3">
                        <div class="tab-pane fade show active" id="profile">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="card">
                                        <div class="card-body text-center">
                                            <div class="avatar-lg rounded-circle bg-primary bg-opacity-10 text-primary d-inline-flex align-items-center justify-content-center mb-3">
                                                <i class="mdi mdi-account" style="font-size: 2rem;"></i>
                                            </div>
                                            <h4><?php echo htmlspecialchars($user_info['username']); ?></h4>
                                            <p class="text-muted"><?php echo htmlspecialchars($user_info['email']); ?></p>
                                            <a href="logout.php" class="btn btn-danger btn-sm">安全退出</a>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <div class="card">
                                        <div class="card-body">
                                            <form>
                                                <div class="mb-3">
                                                    <label class="form-label">用户名</label>
                                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_info['username']); ?>" readonly>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">注册邮箱</label>
                                                    <input type="email" class="form-control" value="<?php echo htmlspecialchars($user_info['email']); ?>" readonly>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">注册时间</label>
                                                    <input type="text" class="form-control" value="<?php echo date('Y-m-d H:i:s', strtotime($user_data['created_at'])); ?>" readonly>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">会员类型</label>
                                                    <input type="text" class="form-control" value="<?php echo $user_data['membership_level'] === 'super' ? '超级会员' : '普通会员'; ?>" readonly>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">会员有效期</label>
                                                    <input type="text" class="form-control" value="<?php echo $user_data['membership_expire'] ? date('Y-m-d H:i:s', strtotime($user_data['membership_expire'])) : '永久有效'; ?>" readonly>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="api">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-body">
                                            <h5 class="card-title">API密钥管理</h5>                                           
                                            <div class="api-key-box"><?php echo htmlspecialchars($user_data['api_key']); ?></div>
                                            <div class="d-flex gap-2">
                                                <button id="copy-key-btn" class="btn btn-primary">复制密钥</button>
                                                <a href="?action=regenerate_key" id="regen-key-btn" class="btn btn-danger">重新生成</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-body">
                                            <h5 class="card-title">卡密兑换</h5>
                                            <form id="redeem-form">
                                                <div class="input-group mb-3">
                                                    <input type="text" id="cdkey-input" class="form-control" placeholder="请输入卡密（支持余额和点数卡密）" required>
                                                    <button type="submit" class="btn btn-primary">立即兑换</button>
                                                </div>
                                                <div class="redeem-feedback" id="redeem-feedback"></div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="statistics">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="card stat-card">
                                        <div class="card-body">
                                            <h5 class="card-title">账户余额</h5>
                                            <div class="stat-value" id="balance-display">¥ <?php echo htmlspecialchars($user_data['balance']); ?></div>
                                            <p class="text-muted">可用于计费接口</p>
                                            <button class="btn btn-primary btn-sm" id="recharge-menu-item">立即充值</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card stat-card">
                                        <div class="card-body">
                                            <h5 class="card-title">可用点数</h5>
                                            <div class="stat-value" id="points-display"><?php echo number_format($user_data['points']); ?></div>
                                            <p class="text-muted">可用于点数计费接口</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card stat-card">
                                        <div class="card-body">
                                            <h5 class="card-title">调用统计</h5>
                                            <div class="stat-value"><?php echo number_format($user_data['call_count']); ?></div>
                                            <p class="text-muted">总调用次数</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card mt-3">
                                <div class="card-body">
                                    <h5 class="card-title">最近调用记录</h5>
                                    <ul class="list-group">
                                        <?php if(empty($recent_logs)): ?>
                                            <li class="list-group-item">暂无调用记录</li>
                                        <?php else: ?>
                                            <?php foreach($recent_logs as $log): ?>
                                                <li class="list-group-item">
                                                    <div class="d-flex align-items-center">
                                                        <div class="activity-icon <?php echo $log['is_success'] ? 'success' : 'fail'; ?>">
                                                            <?php echo $log['is_success'] ? '<i class="mdi mdi-check"></i>' : '<i class="mdi mdi-close"></i>'; ?>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <div class="d-flex justify-content-between">
                                                                <span>调用 <?php echo htmlspecialchars($log['api_name']); ?></span>
                                                                <small class="text-muted"><?php echo date('Y-m-d H:i', strtotime($log['request_time'])); ?></small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </li>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
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
<div class="modal fade" id="recharge-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">选择充值方案</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="recharge.php" method="post" id="recharge-form">
                <div class="modal-body">
                    <div class="row">
                        <?php if(empty($billing_plans)): ?>
                            <div class="col-12">当前暂无可用的充值方案。</div>
                        <?php else: ?>
                            <?php foreach($billing_plans as $plan): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="card plan-card" data-plan-id="<?php echo $plan['id']; ?>">
                                        <div class="card-body text-center">
                                            <h5><?php echo htmlspecialchars($plan['name']); ?></h5>
                                            <div class="text-primary fw-bold fs-3">¥<?php echo number_format($plan['price'], 2); ?></div>
                                            <?php if($plan['billing_type'] === 'points'): ?>
                                                <p>付款后可得：<strong><?php echo number_format($plan['points_to_add']); ?> 点数</strong></p>
                                                <span class="badge badge-points">点数套餐</span>
                                            <?php elseif($plan['billing_type'] === 'membership'): ?>
                                                <p>付款后可得：<strong><?php echo number_format($plan['membership_days']); ?> 天超级会员</strong></p>
                                                <span class="badge bg-warning text-dark">会员套餐</span>
                                            <?php else: ?>
                                                <p>付款后可得：<strong>¥<?php echo number_format($plan['balance_to_add'], 3); ?> 余额</strong></p>
                                                <span class="badge badge-balance">余额套餐</span>
                                            <?php endif; ?>
                                            <?php if(!empty($plan['description'])): ?>
                                                <p class="text-muted mt-2" style="font-size: 0.9rem;"><?php echo htmlspecialchars($plan['description']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="mt-4">
                        <h5>选择支付方式</h5>
                        <div class="row">
                            <?php foreach($payment_methods as $method_key => $method_info): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card payment-method" data-method="<?php echo $method_key; ?>">
                                        <div class="card-body d-flex align-items-center">
                                            <img src="../../../common/payment/<?php echo $method_info['icon']; ?>" alt="<?php echo $method_info['name']; ?>" width="40" class="me-3">
                                            <span><?php echo $method_info['name']; ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <input type="hidden" name="plan_id" id="selected-plan-id">
                    <input type="hidden" name="payment_method" id="selected-payment-method">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" id="confirm-payment-btn" class="btn btn-primary" disabled>立即支付</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script type="text/javascript" src="../../../assets/js/jquery.min.js"></script>
<script type="text/javascript" src="../../../assets/js/popper.min.js"></script>
<script type="text/javascript" src="../../../assets/js/bootstrap.min.js"></script>
<script type="text/javascript" src="../../../assets/js/main.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const copyKeyBtn = document.getElementById('copy-key-btn');
    const apiKeyBox = document.querySelector('.api-key-box');
    copyKeyBtn.addEventListener('click', function() {
        const apiKey = apiKeyBox.textContent.trim();
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(apiKey)
                .then(() => showCopySuccess(copyKeyBtn))
                .catch(err => {
                    console.error('Clipboard API 失败:', err);
                    useFallbackMethod(apiKey, copyKeyBtn);
                });
        } else {
            useFallbackMethod(apiKey, copyKeyBtn);
        }
    });

    function useFallbackMethod(text, button) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.left = '-9999px';
        textarea.style.top = '-9999px';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            const successful = document.execCommand('copy');
            if (successful) {
                showCopySuccess(button);
            } else {
                showCopyFailed(button);
            }
        } catch (err) {
            showCopyFailed(button);
        } finally {
            document.body.removeChild(textarea);
        }
    }
    
    function showCopySuccess(button) {
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="mdi mdi-check me-1"></i>已复制';
        button.classList.remove('btn-primary');
        button.classList.add('btn-success');
        setTimeout(() => {
            button.innerHTML = originalText;
            button.classList.remove('btn-success');
            button.classList.add('btn-primary');
        }, 2000);
    }
    
    function showCopyFailed(button) {
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="mdi mdi-alert me-1"></i>复制失败';
        button.classList.remove('btn-primary');
        button.classList.add('btn-danger');
        setTimeout(() => {
            button.innerHTML = originalText;
            button.classList.remove('btn-danger');
            button.classList.add('btn-primary');
        }, 2000);
    }

    $('#regen-key-btn').click(function(e) {
        e.preventDefault();
        const btn = $(this);
        if (confirm('您确定要重新生成API密钥吗？旧的密钥将立即失效。')) {
            btn.html('<span class="spinner-border spinner-border-sm me-1"></span>处理中...')
               .prop('disabled', true);
            $.get(btn.attr('href'), function(response) {
                if(response.success) {
                    $('.api-key-box').text(response.new_key);
                    alert('API密钥已成功重新生成！');
                }
                btn.html('<i class="mdi mdi-key-change me-1"></i>重新生成')
                   .prop('disabled', false);
            }).fail(function() {
                alert('重新生成密钥失败，请稍后重试');
                btn.html('<i class="mdi mdi-key-change me-1"></i>重新生成')
                   .prop('disabled', false);
            });
        }
    });
    const rechargeModal = new bootstrap.Modal('#recharge-modal');
    $('#recharge-menu-item').click(function(e) {
        e.preventDefault();
        rechargeModal.show();
    });
    $('.plan-card').click(function() {
        $('.plan-card').removeClass('border-primary');
        $(this).addClass('border-primary');
        $('#selected-plan-id').val($(this).data('plan-id'));
        checkSelections();
    });
    $('.payment-method').click(function() {
        $('.payment-method').removeClass('border-primary');
        $(this).addClass('border-primary');
        $('#selected-payment-method').val($(this).data('method'));
        checkSelections();
    });

    function checkSelections() {
        if ($('#selected-plan-id').val() && $('#selected-payment-method').val()) {
            $('#confirm-payment-btn').prop('disabled', false);
        } else {
            $('#confirm-payment-btn').prop('disabled', true);
        }
    }
    
    $('#confirm-payment-btn').click(function(e) {
        e.preventDefault();
        if (!$('#selected-plan-id').val() || !$('#selected-payment-method').val()) {
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
                plan_id: $('#selected-plan-id').val(),
                payment_method: $('#selected-payment-method').val()
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
    $('#redeem-form').submit(function(e) {
        e.preventDefault();
        const cdkey = $('#cdkey-input').val().trim();
        const submitBtn = $(this).find('button[type="submit"]');
        const feedback = $('#redeem-feedback');
        feedback.html('').removeClass('alert-danger alert-success');
        if (!cdkey) {
            feedback.html('<div class="alert alert-danger">请输入卡密</div>');
            return;
        }
        submitBtn.prop('disabled', true)
                 .html('<span class="spinner-border spinner-border-sm me-1"></span>兑换中...');
        $.ajax({
            url: window.location.href,
            type: 'POST',
            dataType: 'json',
            data: { cdkey: cdkey },
            success: function(response) {
                if (response.success) {
                    feedback.html('<div class="alert alert-success">' + response.message + '</div>');
                    if(response.type === 'balance' && response.new_value !== undefined) {
                        $('#balance-display').text('¥ ' + response.new_value);
                    } else if(response.type === 'points' && response.new_value !== undefined) {
                        $('#points-display').text(response.new_value);
                    }
                    $('#cdkey-input').val('');
                } else {
                    feedback.html('<div class="alert alert-danger">' + response.message + '</div>');
                }
            },
            error: function(xhr) {
                let errorMsg = '兑换失败，请检查网络连接';
                if(xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                } else if(xhr.responseText) {
                    try {
                        const resp = JSON.parse(xhr.responseText);
                        errorMsg = resp.message || errorMsg;
                    } catch(e) {}
                }
                feedback.html('<div class="alert alert-danger">' + errorMsg + '</div>');
            },
            complete: function() {
                submitBtn.prop('disabled', false).html('立即兑换');
            }
        });
    });
    window.addEventListener('apiCallSuccess', function(e) {
        if (e.detail) {
            if (e.detail.type === 'balance' && typeof e.detail.cost === 'number') {
                const currentBalanceText = $('#balance-display').text().replace('¥ ', '').replace(/,/g, '');
                const currentBalance = parseFloat(currentBalanceText);
                const newBalance = currentBalance - e.detail.cost;
                $('#balance-display').text('¥ ' + newBalance.toFixed(3));
            }
            else if (e.detail.type === 'points' && typeof e.detail.cost === 'number') {
                const currentPoints = parseInt($('#points-display').text().replace(/,/g, ''));
                const newPoints = currentPoints - e.detail.cost;
                $('#points-display').text(newPoints);
            }
        }
    });
});
</script>
</body>
</html>