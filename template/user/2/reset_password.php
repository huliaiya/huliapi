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
require_once ROOT_PATH . 'common/turnstile.php';
$email_from_get = isset($_GET['email']) ? trim($_GET['email']) : '';
$error_msg = '';
$success_msg = '';
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = trim($_POST['email']);
        $code = trim($_POST['code']);
        $password = trim($_POST['password']);
        $confirm_password = trim($_POST['confirm_password']);
        if (!huli_turnstile_verify()) {
            throw new Exception('人机验证失败，请完成 Cloudflare 验证后重试');
        }
        if (empty($email) || empty($code) || empty($password)) {
            throw new Exception('所有字段均为必填项');
        }
        if ($password !== $confirm_password) {
            throw new Exception('两次输入的密码不一致');
        }
        if (!isset($_SESSION['reset_code']) || strtolower($code) != strtolower($_SESSION['reset_code']) || strtolower($email) != strtolower($_SESSION['reset_email'])) {
            throw new Exception('邮箱验证码不正确或已过期');
        }
        $stmt = $pdo->prepare("SELECT id FROM huli_users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() === 0) {
            throw new Exception('该邮箱未注册');
        }
        $stmt = $pdo->prepare("UPDATE huli_users SET password = ? WHERE email = ?");
        $stmt->execute([$password, $email]);
        if ($stmt->rowCount() === 0) {
            throw new Exception('密码更新失败，请重试');
        }
        unset($_SESSION['reset_code'], $_SESSION['reset_email']);
        if ($is_ajax) {
            header('Content-Type: application/json');
            die(json_encode([
                'success' => true,
                'message' => '密码重置成功！请使用新密码登录'
            ]));
        }
        $success_msg = '密码重置成功！请使用新密码登录';
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
<title>重置密码 - <?php echo htmlspecialchars($settings['site_name'] ?? 'huliapi'); ?></title>
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-touch-fullscreen" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<link rel="stylesheet" type="text/css" href="../../../assets/css/materialdesignicons.min.css">
<link rel="stylesheet" type="text/css" href="../../../assets/css/bootstrap.min.css">
<link rel="stylesheet" type="text/css" href="../../../assets/css/animate.min.css">
<link rel="stylesheet" type="text/css" href="../../../assets/css/style.min.css">
<style>
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
  <div class="text-center mb-3">
    <a href="./"> <img alt="API" src="../../../assets/images/logo-sidebar.png"> </a>
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
      <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email_from_get); ?>" placeholder="邮箱地址" required>
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
      <input type="password" id="password" name="password" class="form-control" placeholder="新密码" required>
    </div>
    <div class="mb-3 has-feedback">
      <span class="mdi mdi-lock-check" aria-hidden="true"></span>
      <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="确认新密码" required>
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
<script type="text/javascript" src="../../../assets/js/jquery.min.js"></script>
<script type="text/javascript" src="../../../assets/js/popper.min.js"></script>
<script type="text/javascript" src="../../../assets/js/bootstrap.min.js"></script>
<script type="text/javascript" src="../../../assets/js/lyear-loading.js"></script>
<script type="text/javascript" src="../../../assets/js/bootstrap-notify.min.js"></script>
<script type="text/javascript">
$(document).ready(function() {
    $('#send-code-btn').click(function() {
        const email = $('#email').val().trim();
        if (!email) {
            $.notify({
                message: '请先输入您的邮箱地址',
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
        $.post('../../../common/ajax/send_code.php', {
            email: email,
            type: 'reset'
        }, function(response) {
            if (!response.success) {
                clearInterval(interval);
                $btn.prop('disabled', false);
                $btn.text('获取验证码');
                $.notify({
                    message: response.message || '验证码发送失败',
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
            } else {
                $.notify({
                    message: '验证码已发送到您的邮箱',
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
        }).fail(function() {
            clearInterval(interval);
            $btn.prop('disabled', false);
            $btn.text('获取验证码');
            $.notify({
                message: '服务器错误，请稍后再试',
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
        });
    });
    $('.signin-form').on('submit', function(event) {
        event.preventDefault();
        const $form = $(this);
        const $submitBtn = $form.find('button[type="submit"]');
        $submitBtn.html('处理中...').prop('disabled', true);
        const loader = $submitBtn.lyearloading({ opacity: 0.2, spinnerSize: 'nm' });
        $.ajax({
            url: $form.attr('action'),
            type: 'POST',
            data: $form.serialize(),
            dataType: 'json',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(response) {
                loader.destroy();
                $submitBtn.html('确认重置').prop('disabled', false);
                if (response.success) {
                    $.notify({
                        message: response.message || '密码重置成功',
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
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 1500);
                } else {
                    $.notify({
                        message: response.message || '密码重置失败',
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
            },
            error: function(xhr, status, error) {
                loader.destroy();
                $submitBtn.html('确认重置').prop('disabled', false);
                $.notify({
                    message: '服务器错误: ' + error,
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
        });
    });
});
</script>
</body>
</html>
