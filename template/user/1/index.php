<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (!isset($_SESSION['user_id'])) { header('Location: /template/user/1/login.php');
exit; }
$rootPath = dirname(__DIR__, 3);
define('ROOT_PATH', $rootPath . '/');
if (!file_exists(ROOT_PATH . 'config.php')) {
    die("系统错误：配置文件丢失。路径: " . ROOT_PATH . 'config.php');
}
require_once ROOT_PATH . 'config.php';
require_once ROOT_PATH . 'common/TemplateManager.php';
$feedback_msg = ''; $feedback_type = '';
$user_id = $_SESSION['user_id'];
$user_info = ['username' => $_SESSION['user_username'], 'email' => $_SESSION['user_email']];
$user_data = ['api_key' => 'N/A', 'call_count' => 0, 'balance' => '0.00', 'created_at' => 'N/A'];
$recent_logs = []; $billing_plans = [];
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if (isset($_GET['action']) && $_GET['action'] === 'regenerate_key') {
        $new_key = bin2hex(random_bytes(32));
        $stmt_update = $pdo->prepare("UPDATE huli_users SET api_key = ? WHERE id = ?");
        $stmt_update->execute([$new_key, $user_id]);
        $_SESSION['feedback_msg'] = 'API密钥已成功重新生成！';
        $_SESSION['feedback_type'] = 'success';
        header('Location: index.php'); exit;
    }
    if(isset($_SESSION['feedback_msg'])){
        $feedback_msg = $_SESSION['feedback_msg'];
        $feedback_type = $_SESSION['feedback_type'];
        unset($_SESSION['feedback_msg'], $_SESSION['feedback_type']);
    }
    $stmt_get_user = $pdo->prepare("SELECT api_key, call_count, balance, created_at, membership_level, membership_expire FROM huli_users WHERE id = ?");
    $stmt_get_user->execute([$user_id]);
    $fetched_data = $stmt_get_user->fetch(PDO::FETCH_ASSOC);
    if ($fetched_data) {
        $user_data = $fetched_data;
        $user_data['membership_level'] = $fetched_data['membership_level'] ?? 'normal';
        $user_data['membership_expire'] = $fetched_data['membership_expire'] ?? null;
    }
    else { session_destroy(); header('Location: login.php'); exit; }
    $stmt_get_logs = $pdo->prepare("SELECT l.request_time, l.is_success, a.name as api_name FROM huli_api_logs l JOIN huli_apis a ON l.api_id = a.id WHERE l.user_id = ? ORDER BY l.request_time DESC LIMIT 5");
    $stmt_get_logs->execute([$user_id]);
    $recent_logs = $stmt_get_logs->fetchAll(PDO::FETCH_ASSOC);
    $billing_plans = $pdo->query("SELECT * FROM huli_billing_plans WHERE is_active = 1 ORDER BY price ASC")->fetchAll(PDO::FETCH_ASSOC);
    $stmt_settings = $pdo->query("SELECT setting_key, setting_value FROM huli_settings");
    $settings = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR);
    $site_name = $settings['site_name'] ?? 'huliapi';
} catch (PDOException $e) { $feedback_msg = '无法加载您的数据，请稍后重试。'; $feedback_type = 'error'; }
$currentTemplate = basename(dirname(__FILE__));
$activeTemplate = TemplateManager::getActiveUserTemplate();
if ($currentTemplate !== $activeTemplate) {
    header("HTTP/1.1 403 Forbidden");
    ?>
    <!DOCTYPE html>
    <html lang="zh">
    <head>
        <meta charset="UTF-8">
        <title>访问被拒绝</title>
        <style>
            body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
            .container { max-width: 600px; margin: 0 auto; }
            h1 { color: 
            .btn {
                display: inline-block;
                padding: 10px 20px;
                background: 
                color: white;
                text-decoration: none;
                border-radius: 4px;
                margin-top: 20px;
            }
            .btn:hover { background: 
        </style>
    </head>
    <body>
        <div class="container">
            <h1>访问被拒绝</h1>
            <p>您正在尝试访问未激活的模板页面。</p>
            <p>请从首页重新进入用户中心。</p>
            <a href="/" class="btn">返回首页</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户中心 - <?php echo htmlspecialchars($site_name); ?></title>
    <?php if (!empty($settings['favicon_url'])): ?><link rel="shortcut icon" type="image/x-icon" href="<?php echo htmlspecialchars($settings['favicon_url']); ?>"><?php endif; ?>
    <style>
        :root {
            --bg-color: 
            --primary-color: 
            --text-dark: 
            --border-color: 
            --success-bg: 
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-color); color: var(--text-normal); line-height: 1.6; }

        .sidebar-header { padding: 24px; border-bottom: 1px solid var(--border-color); }
        .sidebar-logo { font-size: 24px; font-weight: 700; color: var(--text-dark); text-decoration: none; }
        .user-info-panel { padding: 24px; text-align: center; }
        .user-info-panel .avatar { width: 80px; height: 80px; border-radius: 50%; background-color: var(--primary-light); color: var(--primary-color); display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: 600; margin: 0 auto 16px; }
        .user-info-panel .username { font-size: 18px; font-weight: 600; color: var(--text-dark); }
        .user-info-panel .email { font-size: 14px; color: var(--text-light); margin-top: 4px; }
        .sidebar-nav { padding: 16px 24px; flex-grow: 1; }
        .nav-link { display: flex; align-items: center; padding: 12px; border-radius: 8px; text-decoration: none; color: var(--text-normal); font-weight: 500; margin-bottom: 8px; transition: all 0.2s; cursor: pointer; }
        .nav-link.active, .nav-link:hover { background-color: var(--primary-light); color: var(--primary-color); }
        .nav-link svg { margin-right: 12px; flex-shrink: 0; }
        .sidebar-footer { padding: 24px; border-top: 1px solid var(--border-color); }
        .btn-logout { display: block; width: 100%; text-align: center; padding: 12px; border-radius: 8px; background-color: 
        
        .main-header { display: flex; justify-content: space-between; align-items: center; padding: 16px 32px; background-color: var(--card-bg); border-bottom: 1px solid var(--border-color); position: sticky; top: 0; z-index: 50; }
        
        .content-wrapper { padding: 32px; }
        .page-header h1 { font-size: 32px; font-weight: 800; color: var(--text-dark); margin: 0; }
        .feedback-alert { padding: 16px; border-radius: 8px; font-weight: 500; margin-bottom: 24px; border: 1px solid transparent; opacity: 0; transition: opacity 0.5s; }
        .feedback-alert.show { opacity: 1; }
        .feedback-alert.success { background-color: var(--success-bg); color: var(--success-text); }
        .feedback-alert.error { background-color: var(--error-bg); color: var(--error-text); }
        .content-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 32px; }
        .main-column, .sidebar-column { display: flex; flex-direction: column; gap: 32px; }
        .info-card { background-color: var(--card-bg); border-radius: 16px; border: 1px solid var(--border-color); box-shadow: 0 4px 12px var(--shadow-color); padding: 24px; }
        .card-title { font-size: 18px; font-weight: 600; color: var(--text-dark); margin-bottom: 16px; }
        .api-key-box { background-color: var(--bg-color); padding: 16px; border-radius: 8px; font-family: 'Courier New', monospace; font-size: 14px; color: var(--text-dark); word-break: break-all; margin-bottom: 16px; }
        .key-actions { display: flex; gap: 12px; }
        .btn { padding: 10px 16px; border-radius: 8px; text-decoration: none; font-weight: 600; transition: all 0.2s; border: none; cursor: pointer; }
        .btn-primary { background-color: var(--primary-color); color: 
        .btn-danger { background-color: 
        .stat-value { font-size: 36px; font-weight: 700; color: var(--text-dark); }
        .stat-label { font-size: 14px; color: var(--text-light); margin-top: 4px; }
        .activity-list { list-style: none; }
        .activity-item { display: flex; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--border-color); }
        .activity-item:last-child { border-bottom: none; }
        .activity-icon { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 12px; }
        .activity-icon.success { background-color: 
        .activity-icon.fail { background-color: 
        .activity-details { flex-grow: 1; }
        .activity-title { font-weight: 500; }
        .activity-time { font-size: 12px; color: var(--text-light); }
        .input-group { display: flex; flex-wrap: wrap; gap: 10px; }
        .form-control { width: 100%; height: 44px; padding: 0 12px; border-radius: 8px; border: 1px solid var(--border-color); background-color: 
        .input-group button { flex-shrink: 0; }
        .redeem-feedback { min-height: 24px; margin-top: 12px; font-weight: 500; font-size: 14px; }
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1000; display: none; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s ease; }
        .modal-overlay.show { display: flex; opacity: 1; }
        .modal-content { background: var(--card-bg); border-radius: 16px; padding: 32px; width: 100%; max-width: 800px; max-height: 90vh; overflow-y: auto; transform: scale(0.95); transition: transform 0.3s ease; }
        .modal-overlay.show .modal-content { transform: scale(1); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .modal-title { font-size: 24px; font-weight: 700; }
        .btn-close-modal { background: none; border: none; font-size: 24px; cursor: pointer; }
        .plans-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; }
        .plan-card { border: 2px solid var(--border-color); border-radius: 12px; padding: 20px; text-align: center; transition: all 0.2s; cursor: pointer; }
        .plan-card.selected { border-color: var(--primary-color); box-shadow: 0 0 0 3px var(--primary-light); }
        .plan-price { font-size: 32px; font-weight: 700; color: var(--primary-color); margin: 8px 0; }
        .btn-confirm-payment { width: 100%; margin-top: 24px; padding: 14px; }
        .btn-confirm-payment:disabled { background-color: 
        
        @media (min-width: 1025px) { 
        @media (max-width: 1024px) { body.sidebar-open 
        @media (max-width: 768px) { .content-wrapper { padding: 16px; } .page-header h1 { font-size: 28px; } }
    </style>
</head>
<body>
    <div id="page-container">
        <aside id="sidebar">
            <div class="sidebar-header"><a href="../../../" class="sidebar-logo"><?php echo htmlspecialchars($site_name); ?></a></div>
            <div class="user-info-panel"><div class="avatar"><?php echo strtoupper(substr($user_info['username'], 0, 1)); ?></div><div class="username"><?php echo htmlspecialchars($user_info['username']); ?></div><div class="email"><?php echo htmlspecialchars($user_info['email']); ?></div><div class="email">会员类型：<?php echo $user_data['membership_level'] === 'super' ? '超级会员' : '普通会员'; ?></div><div class="email">会员到期：<?php echo $user_data['membership_expire'] ? date('Y-m-d H:i:s', strtotime($user_data['membership_expire'])) : '永久有效'; ?></div></div>
            <nav class="sidebar-nav">
                <a href="../../../" class="nav-link"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path d="M3.75 3A1.75 1.75 0 002 4.75v10.5c0 .966.784 1.75 1.75 1.75h12.5A1.75 1.75 0 0018 15.25V4.75A1.75 1.75 0 0016.25 3H3.75zM9.5 4.5a.75.75 0 00-1.5 0v11a.75.75 0 001.5 0v-11z" /></svg>接口大厅</a>
                <a href="index.php" class="nav-link active"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-5.5-2.5a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0zM10 12a5.99 5.99 0 00-4.793 2.39A6.483 6.483 0 0010 16.5a6.483 6.483 0 004.793-2.11A5.99 5.99 0 0010 12z" clip-rule="evenodd" /></svg>用户中心</a>
                <a href="#" id="recharge-menu-item" class="nav-link"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path d="M2.5 4A1.5 1.5 0 001 5.5v1A1.5 1.5 0 002.5 8H3v8.5A1.5 1.5 0 004.5 18h11a1.5 1.5 0 001.5-1.5V8h.5A1.5 1.5 0 0019 6.5v-1A1.5 1.5 0 0017.5 4h-15zM11 12a1 1 0 10-2 0 1 1 0 002 0z" /></svg>账户充值</a>
                <a href="<?= TemplateManager::getUserTemplateUrl() ?>feedback.php" class="nav-link"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 2c-1.717 0-3.28.534-4.522 1.425C3.899 4.347 3 5.91 3 7.5c0 1.27.666 2.454 1.734 3.162a.75.75 0 01.266.588v3.5a.75.75 0 00.75.75h8.5a.75.75 0 00.75-.75v-3.5a.75.75 0 01.266-.588C16.334 9.954 17 8.77 17 7.5c0-1.59-.899-3.153-2.478-4.075C13.28 2.534 11.717 2 10 2zM4.5 7.5c0-1.06.688-2.184 1.88-2.94A5.498 5.498 0 0110 3.5c1.45 0 2.763.54 3.62 1.06A3.48 3.48 0 0115.5 7.5c0 .84-.42 1.68-1.12 2.24a2.253 2.253 0 00-1.38.527.75.75 0 01-.266.588v2.895h-5.5V10.85a.75.75 0 01-.266-.588 2.253 2.253 0 00-1.38-.527C4.92 9.18 4.5 8.34 4.5 7.5z" clip-rule="evenodd" /></svg>意见反馈</a>
                <a href="<?= TemplateManager::getUserTemplateUrl() ?>login_logs.php" class="nav-link"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z"/></svg>我的登录日志</a>
            </nav>
            <div class="sidebar-footer"><a href="logout.php" class="btn-logout">安全退出</a></div>
        </aside>
        <div id="sidebar-overlay"></div>
        <div id="main-content">
            <header class="main-header"><button id="mobile-menu-btn"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg></button><div></div></header>
            <main class="content-wrapper">
                <div class="page-header"><h1>欢迎, <?php echo htmlspecialchars($user_info['username']); ?></h1></div>
                <?php if ($feedback_msg): ?><div id="feedback-alert" class="feedback-alert <?php echo $feedback_type; ?> show"><?php echo htmlspecialchars($feedback_msg); ?></div><?php endif; ?>
                <div class="content-grid">
                    <div class="main-column">
                        <div class="info-card"><h2 class="card-title">您的API密钥</h2><div class="api-key-box" id="api-key-text"><?php echo htmlspecialchars($user_data['api_key']); ?></div><div class="key-actions"><button id="copy-key-btn" class="btn btn-primary">复制密钥</button><a href="?action=regenerate_key" id="regen-key-btn" class="btn btn-danger">重新生成</a></div></div>
                        <div class="info-card"><h2 class="card-title">卡密兑换</h2><form id="redeem-form"><div class="input-group"><input type="text" id="cdkey-input" class="form-control" placeholder="请输入卡密" required><button type="submit" class="btn btn-primary">立即兑换</button></div><div class="redeem-feedback" id="redeem-feedback"></div></form></div>
                    </div>
                    <div class="sidebar-column">
                        <div class="info-card"><h2 class="card-title">调用统计</h2><div class="stat-value"><?php echo number_format($user_data['call_count']); ?></div><p class="stat-label">总调用次数</p></div>
                        <div class="info-card"><h2 class="card-title">账户余额</h2><div class="stat-value">¥ <?php echo number_format($user_data['balance'], 2); ?></div><p class="stat-label">可用于计费接口</p></div>
                        <div class="info-card"><h2 class="card-title">最近调用记录</h2><ul class="activity-list"><?php if(empty($recent_logs)): ?><li><p style="color:var(--text-light)">暂无调用记录</p></li><?php else: foreach($recent_logs as $log): ?><li class="activity-item"><div class="activity-icon <?php echo $log['is_success'] ? 'success' : 'fail'; ?>"><?php echo $log['is_success'] ? '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" /></svg>' : '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" /></svg>'; ?></div><div class="activity-details"><div class="activity-title">调用 <?php echo htmlspecialchars($log['api_name']); ?></div><div class="activity-time"><?php echo date('Y-m-d H:i', strtotime($log['request_time'])); ?></div></div></li><?php endforeach; endif; ?></ul></div></div>
                </div>
            </main>
        </div>
    </div>
    <div id="recharge-modal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header"><h2 class="modal-title">选择充值方案</h2><button id="close-recharge-modal" class="btn-close-modal">&times;</button></div>
            <form action="<?= TemplateManager::getUserTemplateUrl() ?>recharge.php" method="post" id="recharge-form">
                <div class="plans-grid">
                    <?php if(empty($billing_plans)): ?><p>当前暂无可用的充值方案。</p><?php else: ?><?php foreach($billing_plans as $plan): ?><div class="plan-card" data-plan-id="<?php echo $plan['id']; ?>"><h3 class="plan-name"><?php echo htmlspecialchars($plan['name']); ?></h3><p class="plan-price">¥<?php echo number_format($plan['price'], 2); ?></p><?php if ($plan['billing_type'] === 'balance'): ?><p class="plan-balance">可得余额 ¥<?php echo number_format($plan['balance_to_add'], 2); ?></p><?php elseif ($plan['billing_type'] === 'points'): ?><p class="plan-balance">可得点数 <?php echo number_format($plan['points_to_add']); ?></p><?php else: ?><p class="plan-balance">可得超级会员 <?php echo number_format($plan['membership_days']); ?> 天</p><?php endif; ?></div><?php endforeach; endif; ?>
                </div>
                <input type="hidden" name="plan_id" id="selected-plan-id"><button type="submit" id="confirm-payment-btn" class="btn btn-primary btn-confirm-payment" disabled>立即支付</button>
            </form>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const pageContainer = document.body;
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const sidebarOverlay = document.getElementById('sidebar-overlay');
        if (mobileMenuBtn) { mobileMenuBtn.addEventListener('click', (e) => { e.stopPropagation(); pageContainer.classList.toggle('sidebar-open'); }); }
        if (sidebarOverlay) { sidebarOverlay.addEventListener('click', () => { pageContainer.classList.remove('sidebar-open'); }); }
        const feedbackAlert = document.getElementById('feedback-alert');
        if(feedbackAlert) { setTimeout(() => { feedbackAlert.style.opacity = '0'; }, 2000); }
        const copyBtn = document.getElementById('copy-key-btn');
        if (copyBtn) { copyBtn.addEventListener('click', function() { const keyText = document.getElementById('api-key-text').innerText; navigator.clipboard.writeText(keyText).then(() => { const originalText = this.innerText; this.innerText = '已复制!'; setTimeout(() => { this.innerText = originalText; }, 1500); }); }); }
        const regenBtn = document.getElementById('regen-key-btn');
        if (regenBtn) { regenBtn.addEventListener('click', function(e) { if (!confirm('您确定要重新生成API密钥吗？旧的密钥将立即失效。')) { e.preventDefault(); } }); }
        const modal = document.getElementById('recharge-modal');
        const openModalBtn = document.getElementById('recharge-menu-item');
        const closeModalBtn = document.getElementById('close-recharge-modal');
        const planCards = document.querySelectorAll('.plan-card');
        const selectedPlanInput = document.getElementById('selected-plan-id');
        const confirmPaymentBtn = document.getElementById('confirm-payment-btn');
        const rechargeForm = document.getElementById('recharge-form');
        let selectedPlanId = null;
        function checkSelections() { confirmPaymentBtn.disabled = !selectedPlanId; }
        if(openModalBtn) openModalBtn.addEventListener('click', (e) => { e.preventDefault(); modal.classList.add('show'); });
        if(closeModalBtn) closeModalBtn.addEventListener('click', () => modal.classList.remove('show'));
        if(modal) modal.addEventListener('click', (e) => { if(e.target === modal) modal.classList.remove('show'); });
        planCards.forEach(card => { card.addEventListener('click', function() { planCards.forEach(c => c.classList.remove('selected')); this.classList.add('selected'); selectedPlanId = this.dataset.planId; selectedPlanInput.value = selectedPlanId; checkSelections(); }); });
        if(rechargeForm) { rechargeForm.addEventListener('submit', function(e) { if (!selectedPlanId) { e.preventDefault(); alert('请先选择一个充值方案。'); } }); }
        const redeemForm = document.getElementById('redeem-form');
        const redeemFeedback = document.getElementById('redeem-feedback');
        if(redeemForm) {
            redeemForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const cdkey = document.getElementById('cdkey-input').value;
                const submitBtn = this.querySelector('button');
                if(!cdkey) { redeemFeedback.style.color = 'var(--error-text)'; redeemFeedback.textContent = '请输入卡密。'; return; }
                submitBtn.disabled = true; submitBtn.textContent = '兑换中...';
                fetch('../../../common/ajax/redeem_cdkey.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'cdkey=' + encodeURIComponent(cdkey)
                })
                .then(res => res.json())
                .then(data => {
                    if(data.success) {
                        redeemFeedback.style.color = 'var(--success-text)';
                        redeemFeedback.textContent = data.message;
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        redeemFeedback.style.color = 'var(--error-text)';
                        redeemFeedback.textContent = data.message;
                        submitBtn.disabled = false; submitBtn.textContent = '立即兑换';
                    }
                }).catch(() => {
                    redeemFeedback.style.color = 'var(--error-text)';
                    redeemFeedback.textContent = '请求失败，请检查网络。';
                    submitBtn.disabled = false; submitBtn.textContent = '立即兑换';
                });
            });
        }
    });
    </script>
</body>
</html>
