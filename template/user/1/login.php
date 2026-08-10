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
    if (!huli_turnstile_verify()) {
        $error_msg = '人机验证失败，请完成 Cloudflare 验证后重试。';
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
                 WHERE username = ?
                 LIMIT 1"
            );
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user && $password === $user['password']) {
                if ($user['status'] === 'active') {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_username'] = $user['username'];
                    $_SESSION['user_email'] = $user['email'];
                    header('Location: index.php'); exit;
                } else {
                    $error_msg = '您的账户已被封禁或正在审核中。';
                }
            } else {
                $error_msg = '用户名或密码不正确。';
            }
        } catch (PDOException $e) {
            $error_msg = '系统服务暂时不可用。';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户登录 - huliapi</title>
    <?php if($favicon_url):?><link rel="shortcut icon" type="image/x-icon" href="<?php echo htmlspecialchars($favicon_url);?>"><?php endif;?>
    <style>
        :root {
            --bg-color: #f8f9fa; --form-bg-color: #ffffff; --primary-color: #4a69bd;
            --text-color-dark: #212529; --text-color-light: #6c757d; --border-color: #dee2e6;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; background-color: var(--bg-color); }
        .auth-wrapper { width: 100%; max-width: 420px; padding: 20px; }
        .auth-box { background-color: var(--form-bg-color); padding: 40px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); border: 1px solid var(--border-color); }
        .auth-header { text-align: center; margin-bottom: 32px; }
        .auth-header .logo { display: inline-block; background-color: var(--primary-color); color: #fff; width: 50px; height: 50px; border-radius: 12px; font-size: 24px; font-weight: 700; line-height: 50px; margin-bottom: 16px; text-decoration: none; }
        h1 { font-size: 24px; font-weight: 600; margin-bottom: 4px; }
        p { font-size: 14px; color: var(--text-color-light); }
        .form-group { margin-bottom: 24px; }
        .form-label-group { display: flex; justify-content: space-between; align-items: baseline; }
        .form-label { font-size: 14px; font-weight: 500; margin-bottom: 8px; display: block; }
        .form-link { font-size: 13px; color: var(--primary-color); text-decoration: none; font-weight: 500; }
        .form-control { width: 100%; height: 48px; padding: 0 16px; background-color: #f1f3f5; border: 1px solid transparent; border-radius: 8px; font-size: 16px; }
        .btn-submit { width: 100%; padding: 14px; border: none; border-radius: 8px; background-color: var(--primary-color); color: #fff; font-size: 16px; font-weight: 600; cursor: pointer; margin-top: 8px; }
        .error-message { background-color: #f8d7da; color: #721c24; padding: 10px 16px; border-radius: 8px; text-align: center; font-size: 14px; margin-bottom: 20px; }
        .auth-footer { text-align: center; margin-top: 24px; font-size: 14px; color: var(--text-color-light); }
        .auth-footer a { color: var(--primary-color); text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-box">
            <header class="auth-header"><a href="../" class="logo">S</a><h1>欢迎回来</h1><p>登录以继续使用我们的服务</p></header>
            <?php if (!empty($error_msg)): ?><div class="error-message"><?php echo htmlspecialchars($error_msg); ?></div><?php endif; ?>
            <form method="POST" action="login.php" novalidate>
                <div class="form-group"><label for="username" class="form-label">用户名</label><input type="text" id="username" name="username" class="form-control" placeholder="请输入您的用户名" required></div>
                <div class="form-group"><div class="form-label-group"><label for="password" class="form-label">密码</label><?php if ($mail_forgot_enabled): ?><a href="forgot_password.php" class="form-link">忘记密码？</a><?php endif; ?></div><input type="password" id="password" name="password" class="form-control" placeholder="请输入您的密码" required></div>
                <?php echo huli_turnstile_widget_html(); ?>
                <button type="submit" class="btn-submit">登 录</button>
            </form>
        </div>
        <footer class="auth-footer">还没有账户？ <a href="register.php">立即注册</a></footer>
    </div>
</body>
</html>
