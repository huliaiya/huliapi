<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
$rootPath = dirname(__DIR__, 3);
define('ROOT_PATH', $rootPath . '/');
if (!file_exists(ROOT_PATH . 'config.php')) {
    die("系统错误：配置文件丢失。路径: " . ROOT_PATH . 'config.php');
}
require_once ROOT_PATH . 'config.php';
$apis = []; $settings = [];
$is_logged_in = isset($_SESSION['user_id']);
$user_info = $is_logged_in ? ['username' => $_SESSION['user_username'], 'email' => $_SESSION['user_email']] : null;
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt_apis = $pdo->query("SELECT id, name FROM huli_apis ORDER BY name ASC");
    $apis = $stmt_apis->fetchAll(PDO::FETCH_ASSOC);
    $stmt_settings = $pdo->query("SELECT setting_key, setting_value FROM huli_settings");
    $settings = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) { /* silent fail */ }
    $site_name = $settings['site_name'] ?? 'huliapi';
$allow_temp_key = $settings['allow_temp_key'] ?? 1;
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>意见反馈 - <?php echo htmlspecialchars($site_name); ?></title>
    <link rel="stylesheet" href="../../../assets/css/liquid-glass.css">
    <style>
        :root {
            --bg-color: #f7f8fc; --sidebar-bg: #ffffff; --card-bg: #ffffff;
            --primary-color: #4a69bd; --primary-hover: #3b528f; --primary-light: #eef2ff;
            --text-dark: #1f2937; --text-normal: #4b5563; --text-light: #9ca3af;
            --border-color: #e5e7eb; --shadow-color: rgba(149, 157, 165, 0.1);
            --success-bg: #dcfce7; --success-text: #166534; --error-bg: #fee2e2; --error-text: #991b1b;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { -webkit-font-smoothing: antialiased; }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-color); color: var(--text-normal); }
        #page-container { display: flex; min-height: 100vh; }
        #sidebar { width: 280px; background-color: var(--sidebar-bg); border-right: 1px solid var(--border-color); display: flex; flex-direction: column; position: fixed; top: 0; left: 0; height: 100%; z-index: 100; transform: translateX(-100%); transition: transform 0.3s ease; }
        .sidebar-header { padding: 24px; border-bottom: 1px solid var(--border-color); }
        .sidebar-logo { font-size: 24px; font-weight: 700; color: var(--text-dark); text-decoration: none; }
        .user-info-panel { padding: 24px; text-align: center; }
        .user-info-panel .avatar { width: 80px; height: 80px; border-radius: 50%; background-color: var(--primary-light); color: var(--primary-color); display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: 600; margin: 0 auto 16px; }
        .user-info-panel .username { font-size: 18px; font-weight: 600; color: var(--text-dark); }
        .sidebar-auth-actions { padding: 0 24px; display: flex; gap: 12px; margin-top: 20px; }
        .sidebar-auth-actions a { flex: 1; text-align:center; text-decoration: none; padding: 10px; border-radius: 8px; }
        .sidebar-auth-actions .btn-login { background-color: var(--primary-light); color: var(--primary-color); }
        .sidebar-auth-actions .btn-register { background-color: var(--primary-color); color: #fff; }
        .sidebar-nav { padding: 16px 24px; flex-grow: 1; }
        .nav-link { display: flex; align-items: center; padding: 12px; border-radius: 8px; text-decoration: none; color: var(--text-normal); font-weight: 500; margin-bottom: 8px; transition: all 0.2s; cursor: pointer; }
        .nav-link.active, .nav-link:hover { background-color: var(--primary-light); color: var(--primary-color); }
        .nav-link svg { margin-right: 12px; flex-shrink: 0; }
        .sidebar-footer { padding: 24px; border-top: 1px solid var(--border-color); }
        .btn-logout { display: block; width: 100%; text-align: center; padding: 12px; border-radius: 8px; background-color: #fee2e2; color: #b91c1c; font-weight: 600; text-decoration: none; }
        #main-content { flex-grow: 1; margin-left: 0; display: flex; flex-direction: column; width: 100%; }
        .main-header { display: flex; justify-content: space-between; align-items: center; padding: 16px 32px; background-color: var(--card-bg); border-bottom: 1px solid var(--border-color); position: sticky; top: 0; z-index: 50; }
        #mobile-menu-btn { background: none; border: none; cursor: pointer; }
        .content-wrapper { padding: 32px; max-width: 800px; margin: 0 auto; width: 100%; }
        .page-header h1 { font-size: 32px; font-weight: 800; color: var(--text-dark); margin-bottom: 8px; }
        .page-header p { font-size: 16px; color: var(--text-light); }
        .feedback-form-card { background-color: var(--card-bg); border-radius: 16px; border: 1px solid var(--border-color); box-shadow: 0 4px 12px var(--shadow-color); padding: 32px; margin-top: 24px; }
        .form-group { margin-bottom: 24px; }
        .form-label { display: block; font-weight: 600; color: var(--text-normal); margin-bottom: 8px; }
        .form-control, .form-select, .form-textarea { width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border-color); background-color: var(--bg-color); font-size: 16px; }
        .form-textarea { min-height: 150px; resize: vertical; }
        .btn-submit { width: 100%; padding: 14px; background-color: var(--primary-color); color: #fff; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .feedback-alert { padding: 16px; border-radius: 8px; margin-bottom: 24px; }
        .feedback-alert.success { background-color: var(--success-bg); color: var(--success-text); }
        .feedback-alert.error { background-color: var(--error-bg); color: var(--error-text); }
        #sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 99; }
        @media (min-width: 1025px) { #sidebar { transform: translateX(0); } #main-content { margin-left: 280px; } #mobile-menu-btn { display: none; } }
        @media (max-width: 1024px) { body.sidebar-open #sidebar { transform: translateX(0); } body.sidebar-open #sidebar-overlay { display: block; } }
    </style>
</head>
<body>
    <div id="page-container">
        <aside id="sidebar">
            <div class="sidebar-header"><a href="../" class="sidebar-logo"><?php echo htmlspecialchars($site_name); ?></a></div>
            <div class="user-info-panel">
                <?php if ($is_logged_in): ?>
                    <div class="avatar"><?php echo strtoupper(substr($user_info['username'], 0, 1)); ?></div><div class="username"><?php echo htmlspecialchars($user_info['username']); ?></div><div class="email"><?php echo htmlspecialchars($user_info['email']); ?></div>
                <?php else: ?>
                    <div class="avatar">?</div><div class="username">游客, 您好！</div><div class="sidebar-auth-actions"><a href="login.php" class="btn-login">登录</a><a href="register.php" class="btn-register">注册</a></div>
                <?php endif; ?>
            </div>
            <nav class="sidebar-nav">
                <a href="../../../" class="nav-link"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path d="M3.75 3A1.75 1.75 0 002 4.75v10.5c0 .966.784 1.75 1.75 1.75h12.5A1.75 1.75 0 0018 15.25V4.75A1.75 1.75 0 0016.25 3H3.75zM9.5 4.5a.75.75 0 00-1.5 0v11a.75.75 0 001.5 0v-11z" /></svg>接口大厅</a>
                <a href="index.php" class="nav-link"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-5.5-2.5a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0zM10 12a5.99 5.99 0 00-4.793 2.39A6.483 6.483 0 0010 16.5a6.483 6.483 0 004.793-2.11A5.99 5.99 0 0010 12z" clip-rule="evenodd" /></svg>用户中心</a>
                <a href="feedback.php" class="nav-link active"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 2c-1.717 0-3.28.534-4.522 1.425C3.899 4.347 3 5.91 3 7.5c0 1.27.666 2.454 1.734 3.162a.75.75 0 01.266.588v3.5a.75.75 0 00.75.75h8.5a.75.75 0 00.75-.75v-3.5a.75.75 0 01.266-.588C16.334 9.954 17 8.77 17 7.5c0-1.59-.899-3.153-2.478-4.075C13.28 2.534 11.717 2 10 2zM4.5 7.5c0-1.06.688-2.184 1.88-2.94A5.498 5.498 0 0110 3.5c1.45 0 2.763.54 3.62 1.06A3.48 3.48 0 0115.5 7.5c0 .84-.42 1.68-1.12 2.24a2.253 2.253 0 00-1.38.527.75.75 0 01-.266.588v2.895h-5.5V10.85a.75.75 0 01-.266-.588 2.253 2.253 0 00-1.38-.527C4.92 9.18 4.5 8.34 4.5 7.5z" clip-rule="evenodd" /></svg>意见反馈</a>
            </nav>
            <?php if ($is_logged_in): ?><div class="sidebar-footer"><a href="logout.php" class="btn-logout">安全退出</a></div><?php endif; ?>
        </aside>
        <div id="sidebar-overlay"></div>
        <div id="main-content">
            <header class="main-header"><button id="mobile-menu-btn"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg></button><div></div></header>
            <main class="content-wrapper">
                <div class="page-header"><h1>意见反馈</h1><p>感谢您的宝贵意见，它将帮助我们不断改进。</p></div>
                <div id="feedback-result"></div>
                <div class="feedback-form-card">
                    <form id="feedback-form">
                        <div class="form-group">
                            <label for="feedback_type" class="form-label">反馈类型</label>
                            <select id="feedback_type" name="type" class="form-select">
                                <option value="general">意见与建议</option>
                                <option value="api">接口问题反馈</option>
                            </select>
                        </div>
                        <div class="form-group" id="api-select-group" style="display: none;">
                            <label for="api_id" class="form-label">选择接口</label>
                            <select id="api_id" name="api_id" class="form-select">
                                <option value="">请选择一个接口</option>
                                <?php foreach($apis as $api): ?>
                                <option value="<?php echo $api['id']; ?>"><?php echo htmlspecialchars($api['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group"><label for="content" class="form-label">反馈内容</label><textarea id="content" name="content" class="form-textarea" placeholder="请详细描述您遇到的问题或建议..." required></textarea></div>
                        <div class="form-group"><label for="contact" class="form-label">联系方式 (可选)</label><input type="text" id="contact" name="contact" class="form-control" placeholder="邮箱或QQ，方便我们与您联系" value="<?php echo htmlspecialchars($user_info['email'] ?? ''); ?>"></div>
                        <button type="submit" class="btn-submit">提交反馈</button>
                    </form>
                </div>
            </main>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const pageContainer = document.body;
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const sidebarOverlay = document.getElementById('sidebar-overlay');
        if (mobileMenuBtn) { mobileMenuBtn.addEventListener('click', (e) => { e.stopPropagation(); pageContainer.classList.toggle('sidebar-open'); }); }
        if (sidebarOverlay) { sidebarOverlay.addEventListener('click', () => { pageContainer.classList.remove('sidebar-open'); }); }
        const feedbackTypeSelect = document.getElementById('feedback_type');
        const apiSelectGroup = document.getElementById('api-select-group');
        feedbackTypeSelect.addEventListener('change', function() {
            apiSelectGroup.style.display = this.value === 'api' ? 'block' : 'none';
        });
        const feedbackForm = document.getElementById('feedback-form');
        const resultContainer = document.getElementById('feedback-result');
        feedbackForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = '正在提交...';
            fetch('../../../common/ajax/submit_feedback.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                let alertClass = data.success ? 'success' : 'error';
                resultContainer.innerHTML = `<div class="feedback-alert ${alertClass}">${data.message}</div>`;
                if (data.success) { feedbackForm.reset(); apiSelectGroup.style.display = 'none'; }
            })
            .catch(error => {
                resultContainer.innerHTML = `<div class="feedback-alert error">提交失败，请检查网络后重试。</div>`;
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = '提交反馈';
            });
        });
    });
    </script>
</body>
</html>
