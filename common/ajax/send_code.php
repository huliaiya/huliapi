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
if (!in_array($type, ['register', 'reset', 'admin_reset', 'friend_link', 'feedback'])) { json_response(false, '无效的操作类型。'); }
if (isset($_SESSION['last_sent_time']) && time() - $_SESSION['last_sent_time'] < 60) {
    json_response(false, '请求过于频繁，请稍后再试。');
}
if ($type === 'friend_link' || $type === 'feedback') {
    $turnstile_reason = '';
    if (!huli_turnstile_verify($turnstile_reason)) {
        json_response(false, $turnstile_reason ?: '人机验证失败，请完成 Cloudflare 验证后重试');
    }
    if (!isset($_SESSION['user_email']) || $_SESSION['user_email'] !== $email) {
        json_response(false, '验证邮箱与登录账号不一致');
    }
}
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 获取真实客户端 IP
    $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    $client_ip = '0.0.0.0';
    foreach ($ip_keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip_list = explode(',', $_SERVER[$key]);
            foreach ($ip_list as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    $client_ip = $ip;
                    break 2;
                }
            }
        }
    }
    if ($client_ip === '0.0.0.0') {
        $client_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    // 动态检测并创建日志表，防邮件/验证码轰炸
    $table_check = $pdo->query("SHOW TABLES LIKE 'huli_verification_code_logs'")->fetch();
    if (!$table_check) {
        $pdo->exec("CREATE TABLE huli_verification_code_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            email VARCHAR(255) NOT NULL,
            sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ip_time (ip_address, sent_at),
            INDEX idx_email_time (email, sent_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    // 1. 校验 IP 的发送频率限制
    $stmt_ip_1h = $pdo->prepare("SELECT COUNT(*) FROM huli_verification_code_logs WHERE ip_address = ? AND sent_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $stmt_ip_1h->execute([$client_ip]);
    if ((int)$stmt_ip_1h->fetchColumn() >= 10) {
        json_response(false, '当前IP请求验证码过于频繁，请1小时后再试。');
    }
    
    $stmt_ip_24h = $pdo->prepare("SELECT COUNT(*) FROM huli_verification_code_logs WHERE ip_address = ? AND sent_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stmt_ip_24h->execute([$client_ip]);
    if ((int)$stmt_ip_24h->fetchColumn() >= 30) {
        json_response(false, '当前IP今日请求验证码次数已达上限。');
    }

    // 2. 校验邮箱的发送频率限制
    $stmt_email_24h = $pdo->prepare("SELECT COUNT(*) FROM huli_verification_code_logs WHERE email = ? AND sent_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stmt_email_24h->execute([$email]);
    if ((int)$stmt_email_24h->fetchColumn() >= 5) {
        json_response(false, '该邮箱今日接收验证码次数已达上限。');
    }

    if ($type === 'register' || $type === 'reset') {
        $stmt_check = $pdo->prepare("SELECT id FROM huli_users WHERE email = ?");
        $stmt_check->execute([$email]);
        $user_exists = $stmt_check->fetch();
        if ($type === 'register' && $user_exists) { json_response(false, '该邮箱已被注册。'); }
        if ($type === 'reset' && !$user_exists) { json_response(false, '该邮箱未注册。'); }
    }
    if ($type === 'admin_reset') {
        $stmt_admin = $pdo->prepare("SELECT setting_value FROM huli_settings WHERE setting_key = 'mail_admin_forgot_enabled'");
        $stmt_admin->execute();
        if ((int)$stmt_admin->fetchColumn() !== 1) { json_response(false, '管理员已关闭找回密码功能。'); }
        $stmt_check = $pdo->prepare("SELECT id FROM huli_admins WHERE email = ?");
        $stmt_check->execute([$email]);
        if (!$stmt_check->fetch()) { json_response(false, '该邮箱未绑定任何管理员账号。'); }
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
    $_SESSION['reset_code_expire'] = time() + 300;
} elseif ($type === 'admin_reset') {
    $mail->Subject = '【' . ($settings['site_name'] ?? '系统') . '】管理员密码重置 - 您的验证码';
    $mail->Body = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body {font-family: "Helvetica Neue", Arial, sans-serif;line-height: 1.6;color: #333;max-width: 600px;margin: 0 auto;padding: 0;background-color: #f7f7f7;}
            .container {background: #fff;border-radius: 8px;box-shadow: 0 2px 10px rgba(0,0,0,0.05);overflow: hidden;margin: 20px auto;}
            .header {background-color: #dc3545;color: white;padding: 25px;text-align: center;}
            .content {padding: 30px;}
            .code {font-size: 28px;letter-spacing: 3px;color: #dc3545;text-align: center;margin: 25px 0;font-weight: bold;}
            .footer {padding: 15px;text-align: center;color: #777;font-size: 12px;border-top: 1px solid #eee;}
            .warning {background: #fff8e1;padding: 15px;border-radius: 4px;margin-top: 20px;font-size: 14px;border-left: 4px solid #FFC107;}
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header"><h2>管理员密码重置请求</h2></div>
            <div class="content">
                <p>您正在尝试重置 <strong>管理员账号</strong> 的密码，请使用以下验证码继续操作：</p>
                <div class="code">' . $code . '</div>
                <div class="warning">
                    <p><strong>安全提示：</strong></p>
                    <ul><li>此验证码 <strong>5分钟</strong> 内有效</li><li>请勿向任何人透露此验证码</li><li>如非本人操作，请立即检查账号安全</li></ul>
                </div>
            </div>
            <div class="footer"><p>© ' . date('Y') . ' ' . ($settings['site_name'] ?? '') . ' 版权所有</p></div>
        </div>
    </body>
    </html>
    ';
    $_SESSION['admin_reset_code'] = $code;
    $_SESSION['admin_reset_email'] = $email;
    $_SESSION['admin_reset_code_expire'] = time() + 300;
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

    // 邮件发送成功后记录日志，用于限制频率
    $stmt_log_send = $pdo->prepare("INSERT INTO huli_verification_code_logs (ip_address, email) VALUES (?, ?)");
    $stmt_log_send->execute([$client_ip, $email]);

    json_response(true, '验证码已成功发送到您的邮箱，请注意查收。');
} catch (Exception $e) {
    error_log('[send_code] 邮件发送失败: ' . $e->getMessage() . ' ErrorInfo: ' . $mail->ErrorInfo);
    json_response(false, "邮件发送失败，请稍后再试。");
}
?>
