<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
if (isset($_SESSION['admin_id'])) {
    if ($is_ajax) {
        header('Content-Type: application/json');
        die(json_encode(['success' => false, 'message' => '您已登录']));
    }
    header('Location: index.php');
    exit;
}
if (file_exists('../config.php')) { require_once '../config.php'; } else { die("出现错误！配置文件丢失，请先完成安装。"); }
require_once __DIR__ . '/../common/turnstile.php';
$mail_forgot_enabled = false;
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->query("SELECT setting_value FROM huli_settings WHERE setting_key = 'mail_admin_forgot_enabled'");
    $mail_forgot_enabled = ($stmt->fetchColumn() == 1);
    $site_name = $pdo->query("SELECT setting_value FROM huli_settings WHERE setting_key='site_name'")->fetchColumn() ?: 'huliapi';
} catch (Exception $e) { $site_name = 'huliapi'; }
if (!$mail_forgot_enabled) {
    if ($is_ajax) {
        header('Content-Type: application/json');
        die(json_encode(['success' => false, 'message' => '管理员已关闭找回密码功能']));
    }
    die("管理员已关闭找回密码功能。");
}
$email_from_get = isset($_GET['email']) ? trim($_GET['email']) : '';
$error_msg = '';
$success_msg = '';
$turnstile_reason = '';
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = trim($_POST['email']);
        $code = trim($_POST['code']);
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        if (!huli_turnstile_verify($turnstile_reason)) {
            throw new Exception($turnstile_reason ?: '人机验证失败，请完成 Cloudflare 验证后重试');
        }
        if (empty($email) || empty($code) || empty($password)) {
            throw new Exception('所有字段均为必填项');
        }
        if ($password !== $confirm_password) {
            throw new Exception('两次输入的密码不一致');
        }
        if (strlen($password) < 6) {
            throw new Exception('密码至少 6 位');
        }
        if (!isset($_SESSION['admin_reset_code']) || !isset($_SESSION['admin_reset_code_expire']) || time() > $_SESSION['admin_reset_code_expire'] || strtolower($code) != strtolower($_SESSION['admin_reset_code']) || strtolower($email) != strtolower($_SESSION['admin_reset_email'])) {
            throw new Exception('邮箱验证码不正确或已过期');
        }
        $stmt = $pdo->prepare("SELECT id, status FROM huli_admins WHERE email = ?");
        $stmt->execute([$email]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$admin) {
            throw new Exception('该邮箱未绑定任何管理员账号');
        }
        if ((int)$admin['status'] !== 1) {
            throw new Exception('该管理员账号已被禁用，无法重置密码');
        }
        $stmt = $pdo->prepare("UPDATE huli_admins SET password = ? WHERE id = ?");
        $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $admin['id']]);
        if ($stmt->rowCount() === 0) {
            throw new Exception('密码更新失败，请重试');
        }
        unset($_SESSION['admin_reset_code'], $_SESSION['admin_reset_email'], $_SESSION['admin_reset_code_expire']);
        if ($is_ajax) {
            header('Content-Type: application/json');
            die(json_encode(['success' => true, 'message' => '密码重置成功！请使用新密码登录']));
        }
        $success_msg = '密码重置成功！请使用新密码登录';
    }
} catch (Exception $e) {
    if ($is_ajax) {
        header('Content-Type: application/json');
        die(json_encode(['success' => false, 'message' => $e->getMessage()]));
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
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-touch-fullscreen" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<title>重置密码 - <?php echo htmlspecialchars($site_name); ?></title>
<link rel="stylesheet" type="text/css" href="../assets/css/materialdesignicons.min.css">
<link rel="stylesheet" type="text/css" href="../assets/css/bootstrap.min.css">
<link rel="stylesheet" type="text/css" href="../assets/css/animate.min.css">
<link rel="stylesheet" type="text/css" href="../assets/css/style.min.css">
<style>
body {
    background: 
        radial-gradient(circle at 10% 20%, rgba(93, 177, 255, 0.52), transparent 45%),
        radial-gradient(circle at 90% 80%, rgba(38, 208, 194, 0.38), transparent 48%),
        radial-gradient(circle at 50% 50%, rgba(113, 132, 255, 0.28), transparent 50%),
        linear-gradient(135deg, #f5f8fc 0%, #eef3fa 100%);
    background-attachment: fixed;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}
.glass-card {
    background: rgba(255, 255, 255, 0.45);
    backdrop-filter: blur(25px) saturate(200%);
    -webkit-backdrop-filter: blur(25px) saturate(200%);
    border: 1px solid rgba(255, 255, 255, 0.35);
    border-radius: 24px;
    box-shadow: 0 28px 75px rgba(10, 25, 50, 0.12), inset 0 1px 0 rgba(255, 255, 255, 0.45);
    padding: 3.5rem !important;
}
.code-group {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
}
.code-group input#code {
    flex: 1;
    min-width: 0;
}
.code-group button#send-code-btn {
    white-space: nowrap;
    min-height: 46px;
    border-radius: 12px;
}
.has-feedback {
    position: relative;
}
.has-feedback .mdi {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    z-index: 4;
    color: #61718c;
    pointer-events: none;
}
.has-feedback input {
    padding-left: 38px;
}
.form-control {
    background: rgba(255, 255, 255, 0.55) !important;
    border: 1px solid rgba(255, 255, 255, 0.45) !important;
    backdrop-filter: blur(5px);
    border-radius: 12px;
    color: #2c3e50 !important;
    min-height: 46px;
    transition: all 0.3s ease;
}
.form-control:focus {
    background: rgba(255, 255, 255, 0.85) !important;
    border-color: #2879ba !important;
    box-shadow: 0 0 15px rgba(40, 121, 186, 0.2) !important;
}
.btn-primary {
    background: linear-gradient(135deg, #2879ba 0%, #2cb4e1 100%) !important;
    border: none !important;
    border-radius: 12px !important;
    box-shadow: 0 8px 25px rgba(40, 121, 186, 0.3) !important;
    transition: all 0.3s ease !important;
    font-weight: 600 !important;
    min-height: 46px;
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 30px rgba(40, 121, 186, 0.4) !important;
}
.btn-primary:active {
    transform: translateY(0);
}
</style>
</head>
<body>
<div class="glass-card mb-0 mr-2 ml-2" style="max-width: 450px; width: 100%;">
  <div class="text-center mb-3">
    <a href="./"> <img alt="huli admin" src="../assets/images/logo-sidebar.png"> </a>
  </div>
  <h4 class="text-center mb-3">重置密码</h4>
  <?php if (!empty($error_msg)): ?>
    <div class="alert alert-danger text-center"><?php echo htmlspecialchars($error_msg); ?></div>
  <?php endif; ?>
  <?php if (!empty($success_msg)): ?>
    <div class="alert alert-success text-center"><?php echo htmlspecialchars($success_msg); ?></div>
  <?php endif; ?>
  <?php if (empty($success_msg)): ?>
  <form method="POST" action="reset_password.php" class="signin-form needs-validation" novalidate>
    <div class="mb-3 has-feedback">
      <span class="mdi mdi-email" aria-hidden="true"></span>
      <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email_from_get); ?>" placeholder="管理员邮箱" required>
    </div>
    <div class="mb-3 has-feedback">
      <span class="mdi mdi-shield-key" aria-hidden="true"></span>
      <div class="code-group">
        <input type="text" id="code" name="code" class="form-control" placeholder="邮箱验证码" required>
        <button type="button" id="send-code-btn" class="btn btn-primary">获取验证码</button>
      </div>
    </div>
    <div class="mb-3 has-feedback">
      <span class="mdi mdi-lock" aria-hidden="true"></span>
      <input type="password" id="password" name="password" class="form-control" placeholder="新密码（至少 6 位）" required minlength="6">
    </div>
    <div class="mb-3 has-feedback">
      <span class="mdi mdi-lock-check" aria-hidden="true"></span>
      <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="确认新密码" required minlength="6">
    </div>
    <?php echo huli_turnstile_widget_html(); ?>
    <div class="mb-3 d-grid">
      <button class="btn btn-primary" type="submit">确认重置</button>
    </div>
  </form>
  <?php else: ?>
    <div class="d-grid">
      <a href="login.php" class="btn btn-primary">返回登录</a>
    </div>
  <?php endif; ?>
</div>
<script type="text/javascript" src="../assets/js/jquery.min.js"></script>
<script type="text/javascript" src="../assets/js/popper.min.js"></script>
<script type="text/javascript" src="../assets/js/bootstrap.min.js"></script>
<script type="text/javascript" src="../assets/js/lyear-loading.js"></script>
<script type="text/javascript" src="../assets/js/bootstrap-notify.min.js"></script>
<script type="text/javascript">
$(document).ready(function() {
    function notifyError(message) {
        $.notify({ message: message }, {
            type: 'danger', placement: { from: 'top', align: 'right' },
            z_index: 10800, delay: 2500,
            animate: { enter: 'animate__animated animate__shakeX', exit: 'animate__animated animate__fadeOutDown' }
        });
    }

    function resetTurnstileWidget() {
        if (typeof window.huliTurnstileConsumed === 'function') {
            window.huliTurnstileConsumed();
        }
    }

    // 人机验证关闭时运行时脚本不会输出，这里保证提交流程仍然可用。
    function ensureTurnstile(onReady, onFail) {
        if (typeof window.huliTurnstileEnsureToken === 'function') {
            window.huliTurnstileEnsureToken(onReady, onFail);
        } else {
            onReady('');
        }
    }

    $('#send-code-btn').click(function() {
        const email = $('#email').val().trim();
        if (!email) {
            $.notify({ message: '请先输入您的邮箱地址' }, {
                type: 'danger', placement: { from: 'top', align: 'right' },
                z_index: 10800, delay: 1500,
                animate: { enter: 'animate__animated animate__shakeX', exit: 'animate__animated animate__fadeOutDown' }
            });
            return;
        }
        const $btn = $(this);
        $btn.prop('disabled', true);
        let countdown = 60;
        $btn.text(countdown + 's');
        const interval = setInterval(() => {
            countdown--;
            $btn.text(countdown + 's');
            if (countdown <= 0) {
                clearInterval(interval);
                $btn.prop('disabled', false);
                $btn.text('获取验证码');
            }
        }, 1000);
        $.post('../common/ajax/send_code.php', {
            email: email,
            type: 'admin_reset'
        }, function(response) {
            if (!response.success) {
                clearInterval(interval);
                $btn.prop('disabled', false);
                $btn.text('获取验证码');
                $.notify({ message: response.message || '验证码发送失败' }, {
                    type: 'danger', placement: { from: 'top', align: 'right' },
                    z_index: 10800, delay: 1500,
                    animate: { enter: 'animate__animated animate__shakeX', exit: 'animate__animated animate__fadeOutDown' }
                });
            } else {
                $.notify({ message: '验证码已发送到您的邮箱' }, {
                    type: 'success', placement: { from: 'top', align: 'right' },
                    z_index: 10800, delay: 1500,
                    animate: { enter: 'animate__animated animate__fadeInUp', exit: 'animate__animated animate__fadeOutDown' }
                });
            }
        }).fail(function() {
            clearInterval(interval);
            $btn.prop('disabled', false);
            $btn.text('获取验证码');
            $.notify({ message: '服务器错误，请稍后再试' }, {
                type: 'danger', placement: { from: 'top', align: 'right' },
                z_index: 10800, delay: 1500,
                animate: { enter: 'animate__animated animate__shakeX', exit: 'animate__animated animate__fadeOutDown' }
            });
        });
    });
    $('.signin-form').on('submit', function(event) {
        event.preventDefault();
        const $form = $(this);
        const $submitBtn = $form.find('button[type="submit"]');
        $submitBtn.html('正在验证...').prop('disabled', true);
        ensureTurnstile(function() {
            submitReset($form, $submitBtn);
        }, function(message) {
            $submitBtn.html('确认重置').prop('disabled', false);
            notifyError(message);
        });
    });

    function submitReset($form, $submitBtn) {
        $submitBtn.html('处理中...').prop('disabled', true);
        const loader = $submitBtn.lyearloading({ opacity: 0.2, spinnerSize: 'nm' });
        $.ajax({
            url: $form.attr('action'),
            type: 'POST',
            data: $form.serialize(),
            dataType: 'json',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            success: function(response) {
                loader.destroy();
                $submitBtn.html('确认重置').prop('disabled', false);
                if (response.success) {
                    $.notify({ message: response.message || '密码重置成功' }, {
                        type: 'success', placement: { from: 'top', align: 'right' },
                        z_index: 10800, delay: 1500,
                        animate: { enter: 'animate__animated animate__fadeInUp', exit: 'animate__animated animate__fadeOutDown' }
                    });
                    setTimeout(() => { window.location.href = 'login.php'; }, 1500);
                } else {
                    notifyError(response.message || '密码重置失败');
                }
            },
            error: function(xhr, status, error) {
                loader.destroy();
                $submitBtn.html('确认重置').prop('disabled', false);
                notifyError('服务器错误: ' + error);
            },
            complete: function() {
                resetTurnstileWidget();
            }
        });
    }
});
</script>
</body>
</html>
