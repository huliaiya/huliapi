<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
$rootPath = dirname(__DIR__, 3);
define('ROOT_PATH', $rootPath . '/');
if (isset($_SESSION['user_id'])) {
    header('Location: ' . ROOT_PATH);
    exit;
}
if (!file_exists(ROOT_PATH . 'config.php')) {
    die("系统错误：配置文件丢失。路径: " . ROOT_PATH . 'config.php');
}
require_once ROOT_PATH . 'config.php';
require_once ROOT_PATH . 'common/turnstile.php';
require_once ROOT_PATH . 'common/login_helper.php';
$favicon_url = ''; try{$fp=new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET,DB_USER,DB_PASS);$favicon_url=$fp->query("SELECT setting_value FROM huli_settings WHERE setting_key='favicon_url'")->fetchColumn()?:'';}catch(Exception $e){}
$error_msg = '';
try {
    $pdo_check = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS
    );
    $stmt_settings = $pdo_check->query(
        "SELECT setting_value FROM huli_settings WHERE setting_key = 'mail_forgot_enabled'"
    );
    $mail_forgot_enabled = ($stmt_settings->fetchColumn() == 1);
} catch(Exception $e) {
    $mail_forgot_enabled = false;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $captcha_ok = true;
    if (!huli_turnstile_enabled()) {
        $captcha = strtolower(trim($_POST['captcha'] ?? ''));
        if (empty($_SESSION['captcha_code']) || $captcha !== strtolower($_SESSION['captcha_code'])) {
            $captcha_ok = false;
        }
    }
    if (!$captcha_ok) {
        $error_msg = '验证码不正确，请重新输入';
    } elseif (!huli_turnstile_verify()) {
        $error_msg = '人机验证失败，请完成验证后重试';
    } elseif (empty($username) || empty($password)) {
        $error_msg = '用户名或密码不能为空。';
    } else {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
                DB_USER,
                DB_PASS
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $stmt = $pdo->prepare(
                "SELECT id, username, email, password, status
                 FROM huli_users
                 WHERE email = ? OR username = ?
                 ORDER BY (email = ?) DESC
                 LIMIT 1"
            );
            $stmt->execute([$username, $username, $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $login_ok = false;
                if (password_verify($password, $user['password'])) {
                    $login_ok = true;
                } elseif ($password === $user['password']) {
                    $pdo->prepare("UPDATE huli_users SET password = ? WHERE id = ?")->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
                    $login_ok = true;
                }
                if ($login_ok) {
                if ($user['status'] === 'active') {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_username'] = $user['username'];
                    $_SESSION['user_email'] = $user['email'];
                    try { huli_record_login($pdo, 'user', (int)$user['id'], $user['username'], 'success', ['email' => $user['email'], 'notify' => true]); } catch (Throwable $e) {}
                    try {
                        $ip_api = $pdo->prepare("SELECT id FROM huli_apis WHERE endpoint = 'ip' LIMIT 1");
                        $ip_api->execute();
                        $ip_api_id = $ip_api->fetchColumn();
                        if ($ip_api_id) {
                            $login_ip = huli_get_client_ip();
                            $pdo->prepare("UPDATE huli_apis SET total_calls = total_calls + 1 WHERE id = ?")->execute([$ip_api_id]);
                            $pdo->prepare("UPDATE huli_users SET call_count = call_count + 1 WHERE id = ?")->execute([$user['id']]);
                            $pdo->prepare("INSERT INTO huli_api_logs (api_id, user_id, ip_address, response_code, is_success, billing_type, billing_amount) VALUES (?, ?, ?, 200, 1, 'free', 0)")->execute([$ip_api_id, $user['id'], $login_ip]);
                        }
                    } catch (Throwable $e) {}
                    header('Location: index.php'); exit;
                } else {
                    $error_msg = '您的账户已被封禁或正在审核中。';
                    try { huli_record_login($pdo, 'user', (int)$user['id'], $user['username'], 'failed'); } catch (Throwable $e) {}
                }
            }
        } else {
                $error_msg = '用户名或密码不正确。';
                try { huli_record_login($pdo, 'user', 0, $username, 'failed'); } catch (Throwable $e) {}
            }
        } catch (PDOException $e) {
            $error_msg = '系统服务暂时不可用。';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
<title>用户登录 - huliapi</title>
<?php if($favicon_url):?><link rel="shortcut icon" type="image/x-icon" href="<?php echo htmlspecialchars($favicon_url);?>"><?php endif;?>
<link rel="shortcut icon" type="image/x-icon" href="https://picui.ogmua.cn/s1/2026/05/26/6a156ea77f458.webp">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-touch-fullscreen" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<link rel="stylesheet" type="text/css" href="../../../assets/css/materialdesignicons.min.css">
<link rel="stylesheet" type="text/css" href="../../../assets/css/bootstrap.min.css">
<link rel="stylesheet" type="text/css" href="../../../assets/css/animate.min.css">
<link rel="stylesheet" type="text/css" href="../../../assets/css/style.min.css">
<style>
.signin-form .has-feedback {
    position: relative;
}
.signin-form .has-feedback .form-control {
    padding-left: 36px;
}
.signin-form .has-feedback .mdi {
    position: absolute;
    top: 0;
    left: 0;
    right: auto;
    width: 36px;
    height: 36px;
    line-height: 36px;
    z-index: 4;
    color: 
    display: block;
    text-align: center;
    pointer-events: none;
}
.form-link {
    font-size: 13px;
    color: 
    text-decoration: none;
    font-weight: 500;
}
</style>
</head>
<body class="center-vh" style="background-image: url(https://api.ipojie.com/API/ksxjj.php?apikey=31a3477d8396e9a6f606e2c09c386b9b0bb472e21a32cf0d35f2b1af0177ceff); background-size: cover;">
<div class="card card-shadowed p-5 mb-0 mr-2 ml-2">
  <div class="text-center mb-3">
    <a href="./"> <img alt="logo" src="../../../assets/images/logo-sidebar.png"> </a>
  </div>
  <h4 class="text-center mb-3">欢迎回来</h4>
  <p class="text-center text-muted mb-4">登录以继续使用我们的服务</p>
  <?php if (!empty($error_msg)): ?>
    <div class="alert alert-danger text-center"><?php echo htmlspecialchars($error_msg); ?></div>
  <?php endif; ?>
  <form method="POST" action="login.php" class="signin-form">
    <div class="mb-3 has-feedback">
      <span class="mdi mdi-account" aria-hidden="true"></span>
      <input type="text" id="username" name="username" class="form-control" placeholder="用户名 / 邮箱" required>
    </div>
    <div class="mb-3 has-feedback">
      <span class="mdi mdi-lock" aria-hidden="true"></span>
      <input type="password" id="password" name="password" class="form-control" placeholder="密码" required>
      <?php if ($mail_forgot_enabled): ?>
      <div class="text-right mt-2">
        <a href="forgot_password.php" class="form-link">忘记密码？</a>
      </div>
      <?php endif; ?>
    </div>
    <?php if (huli_turnstile_enabled()): ?>
    <?php echo huli_turnstile_widget_html(); ?>
    <?php else: ?>
    <div class="mb-3 has-feedback">
      <span class="mdi mdi-shield-check" aria-hidden="true"></span>
      <div class="row g-2">
        <div class="col-7">
          <input type="text" id="captcha" name="captcha" class="form-control" placeholder="验证码" autocomplete="off" required>
        </div>
        <div class="col-5">
          <img src="../../../common/ajax/captcha.php?r=<?php echo time(); ?>" id="captcha_img" title="点击刷新" alt="captcha" class="img-fluid rounded" style="cursor:pointer;height:39px;width:100%;object-fit:cover;">
        </div>
      </div>
    </div>
    <?php endif; ?>
    <div class="mb-3 d-grid">
      <button class="btn btn-primary" type="submit">登 录</button>
    </div>
  </form>
  <p class="text-center text-muted mb-0">还没有账户？ <a href="register.php">立即注册</a></p>
</div>
<script type="text/javascript" src="../../../assets/js/jquery.min.js"></script>
<script type="text/javascript" src="../../../assets/js/bootstrap.min.js"></script>
<?php if (!huli_turnstile_enabled()): ?>
<script type="text/javascript">
document.getElementById('captcha_img').addEventListener('click', function() {
    this.src = '../../../common/ajax/captcha.php?r=' + new Date().getTime();
});
</script>
<?php endif; ?>
</body>
</html>
