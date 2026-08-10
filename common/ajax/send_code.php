<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
header('Content-Type: application/json; charset=utf-8');
function json_response($success, $message) {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { json_response(false, '无效的请求方式。'); }
if (!file_exists('../../config.php')) { json_response(false, '系统错误: 配置文件丢失。'); }
require_once '../../config.php';
require_once __DIR__ . '/../turnstile.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
if (!file_exists('../../common/PHPMailer/src/Exception.php')) { json_response(false, '系统错误: 邮件库未配置。'); }
require '../../common/PHPMailer/src/Exception.php';
require '../../common/PHPMailer/src/PHPMailer.php';
require '../../common/PHPMailer/src/SMTP.php';
$email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
$type = $_POST['type'] ?? '';
if (!$email) { json_response(false, '请输入有效的邮箱地址。'); }
if (!in_array($type, ['register', 'reset', 'friend_link', 'feedback'])) { json_response(false, '无效的操作类型。'); }
if (isset($_SESSION['last_sent_time']) && time() - $_SESSION['last_sent_time'] < 60) {
    json_response(false, '请求过于频繁，请稍后再试。');
}
if ($type === 'friend_link' || $type === 'feedback') {
    if (!huli_turnstile_verify()) {
        json_response(false, '人机验证失败，请完成 Cloudflare 验证后重试');
    }
    if (!isset($_SESSION['user_email']) || $_SESSION['user_email'] !== $email) {
        json_response(false, '验证邮箱与登录账号不一致');
    }
}
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if ($type === 'register' || $type === 'reset') {
        $stmt_check = $pdo->prepare("SELECT id FROM huli_users WHERE email = ?");
        $stmt_check->execute([$email]);
        $user_exists = $stmt_check->fetch();
        if ($type === 'register' && $user_exists) { json_response(false, '该邮箱已被注册。'); }
        if ($type === 'reset' && !$user_exists) { json_response(false, '该邮箱未注册。'); }
    }
    $stmt_get = $pdo->query("SELECT setting_key, setting_value FROM huli_settings");
    $settings = $stmt_get->fetchAll(PDO::FETCH_KEY_PAIR);
    if (empty($settings['mail_smtp_host']) || empty($settings['mail_smtp_user']) || empty($settings['mail_smtp_pass'])) {
        json_response(false, '系统邮件服务未配置，请联系管理员。');
    }
    $code = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = $settings['mail_smtp_host'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $settings['mail_smtp_user'];
    $mail->Password   = $settings['mail_smtp_pass'];
    $mail->SMTPSecure = $settings['mail_smtp_secure'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = intval($settings['mail_smtp_port']);
    $mail->CharSet    = 'UTF-8';
    $mail->setFrom($settings['mail_smtp_user'], $settings['site_name'] ?? 'huliapi');
    $mail->addAddress($email);
    $mail->isHTML(true);
if ($type === 'register') {
    $mail->Subject = '【' . ($settings['site_name'] ?? '系统') . '】欢迎注册 - 您的验证码';
    $mail->Body = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body {font-family: "Helvetica Neue", Arial, sans-serif;line-height: 1.6;color: #333;max-width: 600px;margin: 0 auto;padding: 0;background-color: #f7f7f7;}
            .container {background: #fff;border-radius: 8px;box-shadow: 0 2px 10px rgba(0,0,0,0.05);overflow: hidden;margin: 20px auto;}
            .header {background-color: #4096FF;color: white;padding: 25px;text-align: center;}
            .content {padding: 30px;}
            .code {font-size: 28px;letter-spacing: 3px;color: #4096FF;text-align: center;margin: 25px 0;font-weight: bold;}
            .footer {padding: 15px;text-align: center;color: #777;font-size: 12px;border-top: 1px solid #eee;}
            .note {background: #f9f9f9;padding: 15px;border-radius: 4px;margin-top: 20px;font-size: 14px;}
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header"><h2>欢迎注册' . ($settings['site_name'] ?? '') . '</h2></div>
            <div class="content">
                <p>感谢您选择我们的服务！请使用以下验证码完成注册：</p>
                <div class="code">' . $code . '</div>
                <div class="note">
                    <p>请注意：</p>
                    <ul><li>此验证码 <strong>5分钟</strong> 内有效</li><li>请勿将验证码透露给他人</li><li>如非本人操作，请忽略此邮件</li></ul>
                </div>
            </div>
            <div class="footer"><p>© ' . date('Y') . ' ' . ($settings['site_name'] ?? '') . ' 版权所有</p></div>
        </div>
    </body>
    </html>
    ';
    $_SESSION['reg_code'] = $code;
    $_SESSION['reg_email'] = $email;
    $_SESSION['reg_code_expire'] = time() + 300;
} elseif ($type === 'reset') {
    $mail->Subject = '【' . ($settings['site_name'] ?? '系统') . '】密码重置 - 您的验证码';
    $mail->Body = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body {font-family: "Helvetica Neue", Arial, sans-serif;line-height: 1.6;color: #333;max-width: 600px;margin: 0 auto;padding: 0;background-color: #f7f7f7;}
            .container {background: #fff;border-radius: 8px;box-shadow: 0 2px 10px rgba(0,0,0,0.05);overflow: hidden;margin: 20px auto;}
            .header {background-color: #2196F3;color: white;padding: 25px;text-align: center;}
            .content {padding: 30px;}
            .code {font-size: 28px;letter-spacing: 3px;color: #E91E63;text-align: center;margin: 25px 0;font-weight: bold;}
            .footer {padding: 15px;text-align: center;color: #777;font-size: 12px;border-top: 1px solid #eee;}
            .warning {background: #fff8e1;padding: 15px;border-radius: 4px;margin-top: 20px;font-size: 14px;border-left: 4px solid #FFC107;}
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header"><h2>密码重置请求</h2></div>
            <div class="content">
                <p>您正在尝试重置账户密码，请使用以下验证码继续操作：</p>
                <div class="code">' . $code . '</div>
                <div class="warning">
                    <p><strong>安全提示：</strong></p>
                    <ul><li>此验证码 <strong>5分钟</strong> 内有效</li><li>请勿向任何人透露此验证码</li><li>如非本人操作，请立即修改账户密码</li></ul>
                </div>
            </div>
            <div class="footer"><p>© ' . date('Y') . ' ' . ($settings['site_name'] ?? '') . ' 版权所有</p></div>
        </div>
    </body>
    </html>
    ';
    $_SESSION['reset_code'] = $code;
    $_SESSION['reset_email'] = $email;
} elseif ($type === 'friend_link') {
    $mail->Subject = '【' . ($settings['site_name'] ?? '系统') . '】友情链接申请 - 您的验证码';
    $mail->Body = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body {font-family: "Helvetica Neue", Arial, sans-serif;line-height: 1.6;color: #333;max-width: 600px;margin: 0 auto;padding: 0;background-color: #f7f7f7;}
            .container {background: #fff;border-radius: 8px;box-shadow: 0 2px 10px rgba(0,0,0,0.05);overflow: hidden;margin: 20px auto;}
            .header {background-color: #4096ff;color: white;padding: 25px;text-align: center;}
            .content {padding: 30px;}
            .code {font-size: 28px;letter-spacing: 3px;color: #4096ff;text-align: center;margin: 25px 0;font-weight: bold;}
            .footer {padding: 15px;text-align: center;color: #777;font-size: 12px;border-top: 1px solid #eee;}
            .tip {background: #f0f7ff;padding: 15px;border-radius: 4px;margin-top: 20px;font-size: 14px;border-left: 4px solid #4096ff;}
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header"><h2>友情链接申请验证</h2></div>
            <div class="content">
                <p>您正在提交友情链接申请，请使用以下验证码完成验证：</p>
                <div class="code">' . $code . '</div>
                <div class="tip">
                    <p><strong>验证提示：</strong></p>
                    <ul><li>此验证码 <strong>5分钟</strong> 内有效</li><li>请勿向任何人透露此验证码</li><li>验证通过后申请将进入审核队列，1-3个工作日内处理</li></ul>
                </div>
            </div>
            <div class="footer"><p>© ' . date('Y') . ' ' . ($settings['site_name'] ?? '') . ' 版权所有</p></div>
        </div>
    </body>
    </html>
    ';
    $_SESSION['friend_link_code'] = $code;
    $_SESSION['friend_link_email'] = $email;
} elseif ($type === 'feedback') {
    $mail->Subject = '【' . ($settings['site_name'] ?? '系统') . '】意见反馈 - 您的验证码';
    $mail->Body = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body {font-family: "Helvetica Neue", Arial, sans-serif;line-height: 1.6;color: #333;max-width: 600px;margin: 0 auto;padding: 0;background-color: #f7f7f7;}
            .container {background: #fff;border-radius: 8px;box-shadow: 0 2px 10px rgba(0,0,0,0.05);overflow: hidden;margin: 20px auto;}
            .header {background-color: #4096ff;color: white;padding: 25px;text-align: center;}
            .content {padding: 30px;}
            .code {font-size: 28px;letter-spacing: 3px;color: #4096ff;text-align: center;margin: 25px 0;font-weight: bold;}
            .footer {padding: 15px;text-align: center;color: #777;font-size: 12px;border-top: 1px solid #eee;}
            .tip {background: #f0f7ff;padding: 15px;border-radius: 4px;margin-top: 20px;font-size: 14px;border-left: 4px solid #4096ff;}
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header"><h2>意见反馈验证</h2></div>
            <div class="content">
                <p>您正在提交意见反馈，请使用以下验证码完成验证：</p>
                <div class="code">' . $code . '</div>
                <div class="tip">
                    <p><strong>验证提示：</strong></p>
                    <ul><li>此验证码 <strong>5分钟</strong> 内有效</li><li>请勿向任何人透露此验证码</li><li>验证通过后反馈将立即提交，我们会尽快处理并回复</li></ul>
                </div>
            </div>
            <div class="footer"><p>© ' . date('Y') . ' ' . ($settings['site_name'] ?? '') . ' 版权所有</p></div>
        </div>
    </body>
    </html>
    ';
    $_SESSION['friend_link_code'] = $code;
    $_SESSION['friend_link_email'] = $email;
}
    $_SESSION['last_sent_time'] = time();
    $mail->send();
    json_response(true, '验证码已成功发送到您的邮箱，请注意查收。');
} catch (Exception $e) {
    json_response(false, "邮件发送失败: " . $mail->ErrorInfo);
}
?>
