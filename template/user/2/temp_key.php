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
$site_name = $settings['site_name'];
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>申请临时密钥 - <?php echo htmlspecialchars($site_name); ?></title>
    <link rel="stylesheet" type="text/css" href="../../../assets/css/materialdesignicons.min.css">
    <link rel="stylesheet" type="text/css" href="../../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="../../../assets/css/animate.min.css">
    <link rel="stylesheet" type="text/css" href="../../../assets/css/style.min.css">
    <style>
        :root {
            --bg-color: #f8f9fa;
            --card-bg: #ffffff;
            --primary-color: #4a69bd;
            --text-dark: #212529;
            --text-light: #6c757d;
            --border-color: #dee2e6;
            --success-bg: #d1e7dd;
            --success-text: #0f5132;
            --error-bg: #f8d7da;
            --error-text: #721c24;
        }
        body {
            font-family: 'Inter', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: var(--bg-color);
            background-image: url(../../../assets/images/login-bg-2.jpg);
            background-size: cover;
        }
        .card {
            background-color: var(--card-bg);
            padding: 2.5rem;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
            max-width: 450px;
            width: 100%;
        }
        .card-title {
            font-size: 1.5rem;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            text-align: center;
        }
        .card-text {
            color: var(--text-light);
            margin-bottom: 1.5rem;
            text-align: center;
        }
        .form-group {
            margin-bottom: 1.25rem;
        }
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        .form-control {
            width: 100%;
            height: 3rem;
            padding: 0 1rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }
        .btn-submit {
            width: 100%;
            padding: 0.875rem;
            border: none;
            border-radius: 8px;
            background-color: var(--primary-color);
            color: #fff;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .btn-submit:hover {
            background-color: #3a5a9d;
        }
        .btn-submit:disabled {
            background-color: #d1d5db;
            cursor: not-allowed;
        }
        .alert {
            margin-top: 1rem;
            font-weight: 500;
            padding: 0.75rem;
            border-radius: 8px;
            animation-duration: 0.5s;
        }
        .alert-success {
            background-color: var(--success-bg);
            color: var(--success-text);
        }
        .alert-danger {
            background-color: var(--error-bg);
            color: var(--error-text);
        }
        .footer-link {
            margin-top: 1.5rem;
            font-size: 0.875rem;
            text-align: center;
        }
        .footer-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
        }
        .spinner-border {
            vertical-align: middle;
            margin-right: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="card card-shadowed p-5 mb-0 mr-2 ml-2">
        <div class="text-center mb-4">
            <h4 class="mt-3">申请临时密钥</h4>
            <?php if (!$allow_temp_key): ?>
                <p class="text-danger">抱歉，管理员已暂时关闭临时密钥申请功能。</p>
            <?php else: ?>
                <p class="text-muted">密钥将发送至您的邮箱，请注意查收</p>
            <?php endif; ?>
        </div>
        <?php if ($allow_temp_key): ?>
        <div id="result-area"></div>
        <form id="temp-key-form">
            <div class="mb-3">
                <label for="email" class="form-label"><span class="mdi mdi-email" aria-hidden="true"></span>邮箱地址</label>
                <div class="has-feedback">
                    <input type="email" id="email" name="email" class="form-control" placeholder="用于接收临时密钥" required>
                </div>
            </div>
            <?php echo huli_turnstile_widget_html(); ?>
            <div class="mb-3 d-grid">
                <button type="submit" class="btn btn-primary" id="submit-btn">
                    <span class="mdi mdi-send"></span> 发送临时密钥到邮箱
                </button>
            </div>
        </form>
        <?php endif; ?>
        <div class="footer-link">
            <a href="../../../"><span class="mdi mdi-home"></span> 返回首页</a>
        </div>
    </div>
    <script src="../../../assets/js/jquery.min.js"></script>
    <script src="../../../assets/js/bootstrap-notify.min.js"></script>
    <script>
    $(document).ready(function() {
        $('#temp-key-form').on('submit', function(e) {
            e.preventDefault();
            const email = $('#email').val().trim();
            const $submitBtn = $('#submit-btn');
            if (!email) {
                showError('请输入您的邮箱地址');
                return;
            }
            $submitBtn.html('<span class="spinner-border spinner-border-sm" role="status"></span> 正在处理...').prop('disabled', true);
            $.ajax({
                url: '../../../common/ajax/get_temp_key.php',
                type: 'POST',
                data: $('form').serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showSuccess(response.message);
                        $('#temp-key-form')[0].reset();
                    } else {
                        showError(response.message);
                    }
                },
                error: function(xhr) {
                    showError('请求失败: ' + getErrorMessage(xhr));
                },
                complete: function() {
                    $submitBtn.html('<span class="mdi mdi-send"></span> 发送临时密钥到邮箱').prop('disabled', false);
                }
            });
        });

        function showSuccess(message) {
            $('#result-area').html('<div class="alert alert-success animate__animated animate__fadeIn">' + message + '</div>');
        }

        function showError(message) {
            $('#result-area').html('<div class="alert alert-danger animate__animated animate__shakeX">' + message + '</div>');
        }

        function getErrorMessage(xhr) {
            try {
                const response = JSON.parse(xhr.responseText);
                if (response && response.message) {
                    return response.message;
                }
            } catch (e) {
            }
            if (xhr.status === 0) {
                return '网络连接错误';
            } else if (xhr.status === 500) {
                return '服务器内部错误';
            }
            return '未知错误 (' + xhr.status + ')';
        }
    });
    </script>
</body>
</html>