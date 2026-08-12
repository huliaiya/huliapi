<?php
session_start();
error_reporting(0);
ini_set('display_errors', 'Off');
$rootPath = dirname(__DIR__, 3);
define('ROOT_PATH', $rootPath . '/');
if (!file_exists(ROOT_PATH . 'config.php')) {
    die("系统错误：配置文件丢失。路径: " . ROOT_PATH . 'config.php');
}
require_once ROOT_PATH . 'config.php';
require_once ROOT_PATH . 'common/turnstile.php';
$settings = [];
$allow_temp_key = false;
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt_settings = $pdo->query("SELECT setting_key, setting_value FROM huli_settings");
    $settings = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR);
    $allow_temp_key = $settings['allow_temp_key'] ?? 0;
} catch (PDOException $e) { /* silent fail */ }
    $site_name = $settings['site_name'] ?? 'huliapi';
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>申请临时密钥 - <?php echo htmlspecialchars($site_name); ?></title>
    <style>
        :root {
            --bg-color: #f8f9fa; --card-bg: #ffffff; --primary-color: #4a69bd;
            --text-dark: #212529; --text-light: #6c757d; --border-color: #dee2e6;
            --success-bg: #d1e7dd; --success-text: #0f5132; --error-bg: #f8d7da; --error-text: #721c24;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; background-color: var(--bg-color); }
        .container { width: 100%; max-width: 450px; padding: 20px; }
        .card { background-color: var(--card-bg); padding: 40px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); border: 1px solid var(--border-color); text-align: center; }
        h1 { font-size: 24px; color: var(--text-dark); margin-bottom: 8px; }
        p { color: var(--text-light); margin-bottom: 24px; }
        .form-group { margin-bottom: 20px; text-align: left; }
        .form-label { display: block; margin-bottom: 8px; font-weight: 500; }
        .form-control { width: 100%; height: 48px; padding: 0 16px; border-radius: 8px; border: 1px solid var(--border-color); }
        .btn-submit { width: 100%; padding: 14px; border: none; border-radius: 8px; background-color: var(--primary-color); color: #fff; font-size: 16px; cursor: pointer; }
        .btn-submit:disabled { background-color: #d1d5db; cursor: not-allowed; }
        .feedback { margin-top: 16px; font-weight: 500; padding: 12px; border-radius: 8px; }
        .feedback.success { background-color: var(--success-bg); color: var(--success-text); }
        .feedback.error { background-color: var(--error-bg); color: var(--error-text); }
        .footer-link { margin-top: 24px; font-size: 14px; }
        .footer-link a { color: var(--primary-color); text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>申请临时密钥</h1>
            <?php if (!$allow_temp_key): ?>
                <p style="color: #c00;">抱歉，管理员已暂时关闭临时密钥申请功能。</p>
            <?php else: ?>
                <p>密钥将发送至您的邮箱，请注意查收。</p>
                <div id="result-area"></div>
                <form id="temp-key-form">
                    <div class="form-group"><label for="email" class="form-label">邮箱地址</label><input type="email" id="email" name="email" class="form-control" placeholder="用于接收临时密钥" required></div>
                    <?php echo huli_turnstile_widget_html(); ?>
                    <button type="submit" class="btn-submit">发送临时密钥到邮箱</button>
                </form>
            <?php endif; ?>
            <div class="footer-link"><a href="../../../">返回首页</a></div>
        </div>
    </div>
   <script>
    document.addEventListener('DOMContentLoaded', function() {
        const tempKeyForm = document.getElementById('temp-key-form');
        if(tempKeyForm) {
            tempKeyForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const emailInput = document.getElementById('email');
                const resultArea = document.getElementById('result-area');
                const submitBtn = this.querySelector('button');
                if (!emailInput.value) {
                    resultArea.innerHTML = `<p class="feedback error">请输入您的邮箱地址。</p>`;
                    return;
                }
                submitBtn.disabled = true;
                submitBtn.textContent = '正在处理...';
                resultArea.innerHTML = '';
                const formData = new URLSearchParams(new FormData(tempKeyForm));
                fetch('../../../common/ajax/get_temp_key.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: formData.toString()
                })
                .then(response => response.json())
                .then(data => {
                    let alertClass = data.success ? 'success' : 'error';
                    resultArea.innerHTML = `<p class="feedback ${alertClass}">${data.message}</p>`;
                    if (data.success) {
                        tempKeyForm.reset();
                    }
                })
                .catch(error => {
                    resultArea.innerHTML = `<p class="feedback error">请求失败，请重试。</p>`;
                    console.error('Error:', error);
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = '发送临时密钥到邮箱';
                });
            });
        }
    });
    </script>
</body>
</html>
