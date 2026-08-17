<?php
require_once __DIR__ . '/../common/session_boot.php';
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
if (file_exists('../config.php')) { require_once '../config.php'; } else { die("出现错误！配置文件丢失。"); }
$username = htmlspecialchars($_SESSION['admin_username']);
$feedback_msg = ''; $feedback_type = ''; $page_title = '支付设置';
$settings = ['afdian_user_id' => '', 'afdian_token' => '', 'afdian_page_url' => ''];
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("INSERT IGNORE INTO huli_settings (setting_key, setting_value) VALUES ('afdian_user_id', ''), ('afdian_token', ''), ('afdian_page_url', '');");
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE huli_settings SET setting_value = ? WHERE setting_key = ?");
        $stmt->execute([trim($_POST['afdian_user_id']), 'afdian_user_id']);
        $stmt->execute([trim($_POST['afdian_token']), 'afdian_token']);
        $stmt->execute([trim($_POST['afdian_page_url']), 'afdian_page_url']);
        $pdo->commit();
        $feedback_msg = '支付设置已成功保存。';
        $feedback_type = 'success';
    }
    $stmt_get = $pdo->query("SELECT setting_key, setting_value FROM huli_settings WHERE setting_key LIKE 'afdian_%'");
    $db_settings = $stmt_get->fetchAll(PDO::FETCH_KEY_PAIR);
    $settings = array_merge($settings, $db_settings);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('[payment_settings.php] 操作失败: ' . $e->getMessage());
    $feedback_msg = '操作失败，请稍后重试。'; $feedback_type = 'error';
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
                            <label for="afdian_user_id" class="form-label">爱发电用户ID (User ID)</label>
                            <input type="text" class="form-control" id="afdian_user_id" name="afdian_user_id" placeholder="请输入爱发电开发者 User ID" value="<?php echo htmlspecialchars($settings['afdian_user_id']); ?>">
                            <small class="form-text text-muted">登录爱发电后，进入「设置 - 开发者」页面获取。</small>
                        </div>
                        <div class="mb-3">
                            <label for="afdian_token" class="form-label">爱发电API Token</label>
                            <input type="text" class="form-control" id="afdian_token" name="afdian_token" placeholder="请输入爱发电 API Token" value="<?php echo htmlspecialchars($settings['afdian_token']); ?>">
                            <small class="form-text text-muted">请务必保管好您的 Token，切勿泄露。</small>
                        </div>
                        <div class="mb-3">
                            <label for="afdian_page_url" class="form-label">爱发电创作者主页链接</label>
                            <input type="url" class="form-control" id="afdian_page_url" name="afdian_page_url" placeholder="例如：https://afdian.com/a/yourname" value="<?php echo htmlspecialchars($settings['afdian_page_url']); ?>">
                            <small class="form-text text-muted">用户付款时将被引导至此赞助页面。</small>
                        </div>
                        <h5 class="mt-4 mb-3">接入说明</h5>
                        <div class="alert alert-info">
                            <ul class="mb-0">
                                <li>用户在本站选择充值方案后，将跳转到您设置的赞助页面付款。</li>
                                <li>用户需要在爱发电付款时选择与本站充值金额一致的赞助档位，并在「备注」中填写系统生成的备注码。</li>
                                <li>系统每 5 秒自动检测一次，并配合后台定时补偿查询；超过 5 分钟未到账的订单将自动作废。</li>
                                <li>支付方式由爱发电平台决定，无需在本站配置。</li>
                            </ul>
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
