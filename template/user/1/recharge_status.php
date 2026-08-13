<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$rootPath = dirname(__DIR__, 3);
define('ROOT_PATH', $rootPath . '/');
if (!file_exists(ROOT_PATH . 'config.php')) { die("系统错误：配置文件丢失。"); }
require_once ROOT_PATH . 'config.php';
require_once ROOT_PATH . 'common/payment/order_fulfillment.php';
$site_name = 'huliapi';
$order = null;
$page_url = '';
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    huli_ensure_afdian_order_columns($pdo);
    $order_id = trim((string)($_GET['order_id'] ?? ''));
    if ($order_id !== '') {
        $stmt = $pdo->prepare("SELECT o.*, p.name AS plan_name FROM huli_orders o LEFT JOIN huli_billing_plans p ON o.plan_id = p.id WHERE o.order_id = ? AND o.user_id = ?");
        $stmt->execute([$order_id, $_SESSION['user_id']]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    $settings = $pdo->query("SELECT setting_key, setting_value FROM huli_settings WHERE setting_key IN ('site_name', 'favicon_url')")->fetchAll(PDO::FETCH_KEY_PAIR);
    $site_name = $settings['site_name'] ?? 'huliapi';
    $favicon_url = $settings['favicon_url'] ?? '';
    $config = huli_get_afdian_config($pdo);
    $page_url = $config['page_url'];
} catch (Exception $e) { }
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>等待付款 - <?php echo htmlspecialchars($site_name); ?></title>
<?php if (!empty($favicon_url)): ?><link rel="shortcut icon" type="image/x-icon" href="<?php echo htmlspecialchars($favicon_url); ?>"><?php endif; ?>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Inter', -apple-system, sans-serif; background: #f4f6fa; color: #1f2937; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
    .pay-card { background: #fff; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.08); width: 100%; max-width: 520px; padding: 36px; text-align: center; }
    .pay-card h1 { font-size: 22px; font-weight: 700; margin-bottom: 8px; }
    .pay-sub { color: #6b7280; font-size: 14px; margin-bottom: 24px; }
    .amount { font-size: 36px; font-weight: 800; color: #2563eb; margin-bottom: 4px; }
    .plan-name { color: #6b7280; font-size: 14px; margin-bottom: 24px; }
    .code-box { background: #f0f4ff; border: 2px dashed #2563eb; border-radius: 12px; padding: 18px; margin-bottom: 20px; }
    .code-label { font-size: 13px; color: #6b7280; margin-bottom: 8px; }
    .code-value { font-size: 30px; font-weight: 800; letter-spacing: 4px; color: #1d4ed8; font-family: 'Courier New', monospace; }
    .btn-pay { display: inline-block; background: #2563eb; color: #fff; text-decoration: none; font-weight: 600; font-size: 16px; padding: 14px 32px; border-radius: 10px; margin: 8px 0; transition: background 0.2s; }
    .btn-pay:hover { background: #1d4ed8; }
    .tips { background: #fff7ed; border-radius: 10px; padding: 14px 16px; text-align: left; font-size: 13px; color: #9a3412; margin: 16px 0 24px; line-height: 1.7; }
    .tips b { color: #c2410c; }
    .status-line { font-size: 14px; color: #6b7280; }
    .status-line .spinner { display: inline-block; width: 14px; height: 14px; border: 2px solid #d1d5db; border-top-color: #2563eb; border-radius: 50%; animation: spin 0.8s linear infinite; vertical-align: -2px; margin-right: 6px; }
    @keyframes spin { to { transform: rotate(360deg); } }
    .status-ok { color: #059669; font-weight: 600; }
    .status-err { color: #dc2626; font-weight: 600; }
    .countdown { font-size: 13px; color: #9ca3af; margin-top: 8px; }
</style>
</head>
<body>
<div class="pay-card">
    <?php if (!$order): ?>
        <h1>订单不存在</h1>
        <p class="pay-sub">无法找到该充值订单，请重新下单。</p>
        <a class="btn-pay" href="index.php">返回用户中心</a>
    <?php elseif ($order['status'] === 'paid'): ?>
        <h1>充值成功</h1>
        <p class="status-line status-ok">权益已到账，正在跳转用户中心...</p>
        <script>setTimeout(function(){ window.location.href = 'index.php'; }, 2000);</script>
    <?php elseif ($order['status'] === 'canceled' || $order['status'] === 'failed'): ?>
        <h1>订单已作废</h1>
        <p class="pay-sub">订单已超过支付时限或已失效，请重新下单。</p>
        <a class="btn-pay" href="index.php">重新充值</a>
    <?php else: ?>
        <h1>等待付款</h1>
        <p class="pay-sub">请完成爱发电赞助，系统将自动确认到账</p>
        <div class="amount">¥ <?php echo number_format($order['amount'], 2); ?></div>
        <div class="plan-name"><?php echo htmlspecialchars($order['plan_name'] ?? '充值套餐'); ?></div>
        <div class="code-box">
            <div class="code-label">请前往爱发电付款，并在「备注」中填写此码</div>
            <div class="code-value"><?php echo htmlspecialchars($order['match_code']); ?></div>
        </div>
        <?php if (!empty($page_url)): ?>
        <a class="btn-pay" href="<?php echo htmlspecialchars($page_url); ?>" target="_blank" rel="noopener">去爱发电付款</a>
        <?php endif; ?>
        <div class="tips">
            1. 点击上方按钮前往爱发电赞助页面<br>
            2. 选择 <b>金额与本站一致</b> 的赞助档位<br>
            3. 在付款页面的「备注」栏填写 <b><?php echo htmlspecialchars($order['match_code']); ?></b><br>
            4. 完成支付后本页将自动确认到账，无需刷新
        </div>
        <div class="status-line" id="status-line"><span class="spinner"></span><span id="status-text">正在等待支付确认...</span></div>
        <div class="countdown" id="countdown"></div>
    <?php endif; ?>
</div>
<?php if ($order && $order['status'] === 'pending'): ?>
<script>
(function () {
    const orderId = '<?php echo htmlspecialchars($order['order_id']); ?>';
    const created = <?php echo strtotime($order['created_at']) * 1000; ?>;
    const ttl = <?php echo HULI_PAYMENT_TTL; ?> * 1000;
    const statusText = document.getElementById('status-text');
    const countdown = document.getElementById('countdown');
    let finalized = false;

    function updateCountdown() {
        const left = created + ttl - Date.now();
        if (left <= 0) { countdown.textContent = '订单即将作废'; return; }
        const totalSec = Math.ceil(left / 1000);
        const min = Math.floor(totalSec / 60);
        const sec = totalSec % 60;
        countdown.textContent = '剩余付款时间 ' + min + ' 分 ' + String(sec).padStart(2, '0') + ' 秒';
    }
    updateCountdown();
    setInterval(updateCountdown, 1000);

    function poll() {
        if (finalized) return;
        fetch('../../../common/payment/query.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'order_id=' + encodeURIComponent(orderId)
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.status === 'paid') {
                finalized = true;
                statusText.textContent = '支付成功！权益已到账，正在跳转...';
                document.querySelector('.status-line').className = 'status-line status-ok';
                setTimeout(function () { window.location.href = 'index.php'; }, 2500);
            } else if (data.status === 'canceled') {
                finalized = true;
                statusText.textContent = data.message || '订单已作废';
                document.querySelector('.status-line').className = 'status-line status-err';
                setTimeout(function () { window.location.href = 'index.php'; }, 2500);
            } else {
                statusText.textContent = '正在等待支付确认...';
                setTimeout(poll, 5000);
            }
        })
        .catch(function () { setTimeout(poll, 5000); });
    }
    setTimeout(poll, 5000);
})();
</script>
<?php endif; ?>
</body>
</html>
