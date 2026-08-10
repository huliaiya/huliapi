<?php
session_start();
error_reporting(0);
ini_set('display_errors', 'Off');
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
if (isset($_SESSION['user_id'])) { 
    if ($is_ajax) {
        header('Content-Type: application/json');
        die(json_encode(['success' => false, 'message' => '您已登录']));
    }
    header('Location: ../'); 
    exit; 
}
$rootPath = dirname(__DIR__, 3);
define('ROOT_PATH', $rootPath . '/');
if (!file_exists(ROOT_PATH . 'config.php')) { 
    if ($is_ajax) {
        header('Content-Type: application/json');
        die(json_encode(['success' => false, 'message' => '系统错误：配置文件丢失']));
    }
    die("系统错误：配置文件丢失。路径: " . ROOT_PATH . 'config.php'); 
}
require_once ROOT_PATH . 'config.php';
$error_msg = ''; 
$success_msg = ''; 
$settings = [];
$registration_allowed = true;
$mail_reg_enabled = false;
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt_settings = $pdo->query("SELECT setting_key, setting_value FROM sl_settings WHERE setting_key IN ('allow_registration', 'mail_reg_enabled')");
    $settings = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR);
    $registration_allowed = isset($settings['allow_registration']) ? (bool)$settings['allow_registration'] : true;
    $mail_reg_enabled = isset($settings['mail_reg_enabled']) ? (bool)$settings['mail_reg_enabled'] : false;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = trim($_POST['username'] ?? ''); 
        $email = trim($_POST['email'] ?? ''); 
        $password = trim($_POST['password'] ?? ''); 
        $confirm_password = trim($_POST['confirm_password'] ?? '');
        $code = trim($_POST['code'] ?? '');
        if (empty($username) || empty($email) || empty($password)) {
            throw new Exception('所有字段均为必填项');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('邮箱格式不正确');
        }
        if (strlen($password) < 6) {
            throw new Exception('密码长度不能少于6位');
        }
        if ($password !== $confirm_password) {
            throw new Exception('两次输入的密码不一致');
        }
        if ($mail_reg_enabled) {
            if (empty($code) || !isset($_SESSION['reg_code']) || strtolower($code) != strtolower($_SESSION['reg_code']) || strtolower($email) != strtolower($_SESSION['reg_email'])) {
                throw new Exception('邮箱验证码不正确或已过期');
            }
        }
        $stmt_check_user = $pdo->prepare("SELECT id FROM sl_users WHERE username = ?");
        $stmt_check_user->execute([$username]);
        if ($stmt_check_user->fetch()) {
            throw new Exception('该用户名已被注册');
        }
        $stmt_check_email = $pdo->prepare("SELECT id FROM sl_users WHERE email = ?");
        $stmt_check_email->execute([$email]);
        if ($stmt_check_email->fetch()) {
            throw new Exception('该邮箱已被注册');
        }
        $api_key = bin2hex(random_bytes(32));      
        $sql = "INSERT INTO sl_users (username, email, password, api_key, status, created_at) VALUES (?, ?, ?, ?, 'active', NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username, $email, $password, $api_key]);
        if ($mail_reg_enabled) {
            unset($_SESSION['reg_code'], $_SESSION['reg_email']);
        }
        if ($is_ajax) {
            header('Content-Type: application/json');
            die(json_encode([
                'success' => true,
                'message' => '注册成功！您现在可以使用您的账户登录了。'
            ]));
        }
        $success_msg = '注册成功！您现在可以使用您的账户登录了。';
    }
} catch (Exception $e) { 
    if ($is_ajax) {
        header('Content-Type: application/json');
        die(json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]));
    }
    $error_msg = $e->getMessage(); 
}
?>

<!DOCTYPE html>
<html lang="zh">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <title>用户注册 - <?php echo htmlspecialchars($settings['site_name'] ?? '白茶API'); ?></title>
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-touch-fullscreen" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="stylesheet" type="text/css" href="../../../assets/css/materialdesignicons.min.css">
    <link rel="stylesheet" type="text/css" href="../../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="../../../assets/css/animate.min.css">
    <link rel="stylesheet" type="text/css" href="../../../assets/css/style.min.css">
    <style>
.form-code-group {
    display: flex;
    align-items: center;
    gap: 10px;
    position: relative;
}
.form-code-group .mdi {
    position: absolute;
    left: 12px; 
    top: 50%;
    transform: translateY(-50%);
    z-index: 1;
    color: #6c757d; 
}
.form-code-group input#code {
    flex: 1;
    min-width: 0;
    padding-left: 38px;
}
.form-code-group button#send-code-btn {
    white-space: nowrap;
    padding: 0.375rem 0.75rem;
}
.has-feedback {
    position: relative;
}
.has-feedback .mdi {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    z-index: 1;
    color: #6c757d;
}
.has-feedback input {
    padding-left: 38px; 
}
</style>
</head>
<body class="center-vh" style="background-image: url(../../../assets/images/login-bg-2.jpg); background-size: cover;">
<div class="card card-shadowed p-5 mb-0 mr-2 ml-2">
    <div class="text-center mb-4">
        <a href="../"> <img alt="" src="../../../assets/images/logo-sidebar.png"> </a>
        <h4 class="mt-3">创建您的账户</h4>
        <p class="text-muted">加入我们，开启您的API之旅</p>
    </div>
    <?php if (!$registration_allowed): ?>
    <div class="alert alert-danger animate__animated animate__fadeIn">管理员已暂时关闭注册功能。</div>
    <?php else: ?>
        <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger animate__animated animate__shakeX"><?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>
        <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success animate__animated animate__fadeIn"><?php echo htmlspecialchars($success_msg); ?></div>
        <?php endif; ?>
    <?php endif; ?>
    <?php if (empty($success_msg) && $registration_allowed): ?>
    <form method="POST" action="register.php" class="signup-form needs-validation" novalidate>
        <div class="mb-3 has-feedback">
            <span class="mdi mdi-account" aria-hidden="true"></span>
            <input type="text" id="username" name="username" class="form-control" placeholder="创建您的用户名" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
        </div>
        <div class="mb-3 has-feedback">
            <span class="mdi mdi-email" aria-hidden="true"></span>
            <input type="email" id="email" name="email" class="form-control" placeholder="请输入您的邮箱" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
        </div>
        <?php if ($mail_reg_enabled): ?>
        <div class="mb-3 has-feedback form-code-group">
            <span class="mdi mdi-shield-check" aria-hidden="true"></span>
            <input type="text" id="code" name="code" class="form-control" placeholder="请输入6位验证码" required value="<?php echo htmlspecialchars($_POST['code'] ?? ''); ?>">
            <button type="button" id="send-code-btn" class="btn btn-primary">获取验证码</button>
        </div>
        <?php endif; ?>
        <div class="mb-3 has-feedback">
            <span class="mdi mdi-lock" aria-hidden="true"></span>
            <input type="password" id="password" name="password" class="form-control" placeholder="设置您的密码 (至少6位)" required>
        </div>
        <div class="mb-3 has-feedback">
            <span class="mdi mdi-lock" aria-hidden="true"></span>
            <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="请再次输入密码" required>
        </div>
        <div class="mb-3 d-grid">
            <button class="btn btn-primary" type="submit" id="submit-btn">立即注册</button>
        </div>
    </form>
    <?php endif; ?>
    <p class="text-center text-muted mb-0">已有账户？ <a href="login.php">直接登录</a></p>
</div>
<script type="text/javascript" src="../../../assets/js/jquery.min.js"></script>
<script type="text/javascript" src="../../../assets/js/bootstrap.min.js"></script>
<script type="text/javascript" src="../../../assets/js/lyear-loading.js"></script>
<script type="text/javascript" src="../../../assets/js/bootstrap-notify.min.js"></script>
<script type="text/javascript">
$(document).ready(function() {
    $('#send-code-btn').on('click', function() {
        const email = $('#email').val().trim();
        if (!email) {
            showError('请先输入您的邮箱地址');
            return;
        }
        const $btn = $(this);
        $btn.prop('disabled', true);
        let countdown = 60;
        $btn.text(countdown + 's 后重试');
        
        const interval = setInterval(() => {
            countdown--;
            $btn.text(countdown + 's 后重试');
            if (countdown <= 0) {
                clearInterval(interval);
                $btn.prop('disabled', false).text('获取验证码');
            }
        }, 1000);
        $.ajax({
            url: '../../../common/ajax/send_code.php',
            type: 'POST',
            data: { email: email, type: 'register' },
            dataType: 'json',
            success: function(response) {
                if (response && response.success) {
                    showSuccess('验证码已发送，请注意查收');
                } else {
                    clearInterval(interval);
                    $btn.prop('disabled', false).text('获取验证码');
                    showError(response ? response.message : '验证码发送失败');
                }
            },
            error: function(xhr) {
                clearInterval(interval);
                $btn.prop('disabled', false).text('获取验证码');
                showError('发送验证码时出错: ' + getErrorMessage(xhr));
            }
        });
    });
    $('.signup-form').on('submit', function(event) {
        event.preventDefault();
        const $form = $(this);
        const $submitBtn = $('#submit-btn');
        $submitBtn.html('<span class="spinner-border spinner-border-sm" role="status"></span> 注册中...').prop('disabled', true);
        $.ajax({
            url: $form.attr('action'),
            type: 'POST',
            data: $form.serialize(),
            dataType: 'json',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(response) {
                if (response && typeof response === 'object') {
                    if (response.success) {
                        showSuccess(response.message || '注册成功');
                        setTimeout(() => {
                            window.location.href = 'login.php';
                        }, 1500);
                    } else {
                        showError(response.message || '注册失败');
                    }
                } else {
                    showError('无效的服务器响应');
                }
            },
            error: function(xhr) {
                showError('服务器错误: ' + getErrorMessage(xhr));
            },
            complete: function() {
                $submitBtn.text('立即注册').prop('disabled', false);
            }
        });
    });

    function showError(message) {
        $.notify({
            message: message
        },{
            type: 'danger',
            placement: { from: 'top', align: 'right' },
            z_index: 10800,
            delay: 1500,
            animate: { 
                enter: 'animate__animated animate__shakeX',
                exit: 'animate__animated animate__fadeOutDown'
            }
        });
    }

    function showSuccess(message) {
        $.notify({
            message: message
        },{
            type: 'success',
            placement: { from: 'top', align: 'right' },
            z_index: 10800,
            delay: 1500,
            animate: { 
                enter: 'animate__animated animate__fadeInUp',
                exit: 'animate__animated animate__fadeOutDown'
            }
        });
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
        } else if (xhr.status === 403) {
            return '请求被拒绝';
        } else if (xhr.status === 404) {
            return '请求的资源不存在';
        }
        return '未知错误 (' + xhr.status + ')';
    }
});
</script>
</body>
</html>