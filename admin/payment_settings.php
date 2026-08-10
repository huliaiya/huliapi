<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
if (file_exists('../config.php')) { require_once '../config.php'; } else { die("出现错误！配置文件丢失。"); }
$username = htmlspecialchars($_SESSION['admin_username']);
$feedback_msg = ''; $feedback_type = ''; $page_title = '支付设置';
$settings = ['epay_pid' => '', 'epay_key' => '', 'epay_url' => '', 'payment_alipay_enabled' => 1, 'payment_wxpay_enabled' => 1, 'payment_qqpay_enabled' => 1];
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("INSERT IGNORE INTO sl_settings (setting_key, setting_value) VALUES ('epay_pid', ''), ('epay_key', ''), ('epay_url', ''), ('payment_alipay_enabled', '1'), ('payment_wxpay_enabled', '1'), ('payment_qqpay_enabled', '1');");
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE sl_settings SET setting_value = ? WHERE setting_key = ?");
        $stmt->execute([trim($_POST['epay_pid']), 'epay_pid']);
        $stmt->execute([trim($_POST['epay_key']), 'epay_key']);
        $stmt->execute([trim($_POST['epay_url']), 'epay_url']);
        $stmt->execute([isset($_POST['payment_alipay_enabled']) ? '1' : '0', 'payment_alipay_enabled']);
        $stmt->execute([isset($_POST['payment_wxpay_enabled']) ? '1' : '0', 'payment_wxpay_enabled']);
        $stmt->execute([isset($_POST['payment_qqpay_enabled']) ? '1' : '0', 'payment_qqpay_enabled']);
        $pdo->commit();
        $feedback_msg = '支付设置已成功保存。';
        $feedback_type = 'success';
    }
    $stmt_get = $pdo->query("SELECT setting_key, setting_value FROM sl_settings WHERE setting_key LIKE 'epay_%' OR setting_key LIKE 'payment_%'");
    $db_settings = $stmt_get->fetchAll(PDO::FETCH_KEY_PAIR);
    $settings = array_merge($settings, $db_settings);
} catch (Exception $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    $feedback_msg = '操作失败: ' . $e->getMessage(); $feedback_type = 'error';
}
$current_page = basename($_SERVER['PHP_SELF']);
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
    <link rel="stylesheet" type="text/css" href="../assets/css/materialdesignicons.min.css">
    <link rel="stylesheet" type="text/css" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="../assets/css/style.min.css">
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <header class="card-header">
                    <div class="card-title">支付设置</div>
                </header>
                <div class="card-body">
                    <?php if ($feedback_msg): ?>
                    <div class="alert alert-<?php echo $feedback_type; ?> alert-dismissible fade show mb-4">
                        <?php echo htmlspecialchars($feedback_msg); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>
                    <form method="POST" action="payment_settings.php">
                        <div class="mb-3">
                            <label for="epay_pid" class="form-label">易支付商户ID (PID)</label>
                            <input type="text" class="form-control" id="epay_pid" name="epay_pid" placeholder="请输入您的商户ID" value="<?php echo htmlspecialchars($settings['epay_pid']); ?>">
                            <small class="form-text text-muted">从您的易支付后台获取。</small>
                        </div>
                        <div class="mb-3">
                            <label for="epay_key" class="form-label">易支付商户密钥 (Key)</label>
                            <input type="text" class="form-control" id="epay_key" name="epay_key" placeholder="请输入您的商户密钥" value="<?php echo htmlspecialchars($settings['epay_key']); ?>">
                            <small class="form-text text-muted">请务必保管好您的密钥，切勿泄露。</small>
                        </div>
                        <div class="mb-3">
                            <label for="epay_url" class="form-label">易支付API接口地址</label>
                            <input type="url" class="form-control" id="epay_url" name="epay_url" placeholder="例如：https://pay.example.com/" value="<?php echo htmlspecialchars($settings['epay_url']); ?>">
                            <small class="form-text text-muted">请确保地址以 / 结尾，例如 https://pay.example.com/</small>
                        </div>
                        <h5 class="mt-4 mb-3">支付方式开关</h5>
                        <div class="mb-3 form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="payment_alipay_enabled" name="payment_alipay_enabled" value="1" <?php echo $settings['payment_alipay_enabled'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="payment_alipay_enabled">支付宝支付</label>
                        </div>
                        <div class="mb-3 form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="payment_wxpay_enabled" name="payment_wxpay_enabled" value="1" <?php echo $settings['payment_wxpay_enabled'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="payment_wxpay_enabled">微信支付</label>
                        </div>
                        <div class="mb-3 form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="payment_qqpay_enabled" name="payment_qqpay_enabled" value="1" <?php echo $settings['payment_qqpay_enabled'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="payment_qqpay_enabled">QQ钱包支付</label>
                        </div>
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary me-2">保存设置</button>
                            <button type="reset" class="btn btn-default">重置</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script type="text/javascript" src="../assets/js/jquery.min.js"></script>
<script type="text/javascript" src="../assets/js/popper.min.js"></script>
<script type="text/javascript" src="../assets/js/bootstrap.min.js"></script>
<script type="text/javascript" src="../assets/js/main.min.js"></script>
</body>
</html>