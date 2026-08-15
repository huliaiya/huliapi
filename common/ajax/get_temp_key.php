<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function getUserIP() {
    $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    foreach ($ip_keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip_list = explode(',', $_SERVER[$key]);
            foreach ($ip_list as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function json_response($success, $message) {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, '无效的请求方式。');
}
if (!file_exists('../../config.php')) {
    json_response(false, '系统错误: 配置文件丢失。');
}
require_once '../../config.php';
require_once __DIR__ . '/../turnstile.php';
$email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
if (!$email) {
    json_response(false, '请输入有效的邮箱地址。');
}
$turnstile_reason = '';
if (!huli_turnstile_verify($turnstile_reason)) {
    json_response(false, $turnstile_reason ?: '人机验证失败，请完成 Cloudflare 验证后重试。');
}
if (isset($_SESSION['last_temp_key_sent']) && time() - $_SESSION['last_temp_key_sent'] < 60) {
    json_response(false, '请求过于频繁，请稍后再试。');
}
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE TABLE IF NOT EXISTS huli_temp_key_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $columns = $pdo->query("SHOW COLUMNS FROM `huli_users`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('call_limit', $columns)) {
        $pdo->exec("ALTER TABLE `huli_users` ADD `call_limit` INT NOT NULL DEFAULT 0");
    }
    if (!in_array('expires_at', $columns)) {
        $pdo->exec("ALTER TABLE `huli_users` ADD `expires_at` DATETIME NULL DEFAULT NULL");
    }
    $stmt_settings = $pdo->query("SELECT setting_key, setting_value FROM huli_settings");
    $settings = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR);
    if (empty($settings['allow_temp_key'])) {
        json_response(false, '此功能已暂时关闭。');
    }
    if (empty($settings['mail_smtp_host']) || empty($settings['mail_smtp_user']) || empty($settings['mail_smtp_pass'])) {
        json_response(false, '系统邮件服务未配置，无法发送密钥，请联系管理员。');
    }
    $stmt_check_email = $pdo->prepare("SELECT id FROM huli_users WHERE email = ?");
    $stmt_check_email->execute([$email]);
    if ($stmt_check_email->fetch()) {
        json_response(false, '该邮箱已被注册，请直接登录或找回密码。');
    }
    $ip_address = getUserIP();
    $stmt_ip_check = $pdo->prepare("SELECT COUNT(*) FROM huli_temp_key_logs WHERE ip_address = ? AND created_at >= CURDATE()");
    $stmt_ip_check->execute([$ip_address]);
    if ($stmt_ip_check->fetchColumn() > 0) {
        json_response(false, '每个IP地址每天只能申请一次。');
    }
    $pdo->beginTransaction();
    $duration_hours = intval($settings['temp_key_duration'] ?? 24);
    $limit_calls = intval($settings['temp_key_limit'] ?? 100);
    $expires_at = date('Y-m-d H:i:s', strtotime("+{$duration_hours} hours"));
    if (!function_exists('random_bytes')) {
        function random_bytes($length) {
            $bytes = '';
            for ($i = 0; $i < $length; $i++) {
                $bytes .= chr(mt_rand(0, 255));
            }
            return $bytes;
        }
    }
    $temp_username = 'temp_' . bin2hex(random_bytes(8));
    $temp_password = bin2hex(random_bytes(16));
    $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
    $api_key = bin2hex(random_bytes(32));
    $sql = "INSERT INTO huli_users (username, email, password, api_key, status, call_limit, expires_at) VALUES (?, ?, ?, ?, 'active', ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$temp_username, $email, $hashed_password, $api_key, $limit_calls, $expires_at]);
    $last_user_id = $pdo->lastInsertId();
    if (!$last_user_id) {
        throw new Exception("创建临时用户失败。");
    }
    $phpmailer_path = __DIR__ . '/../PHPMailer/src/Exception.php';
    if (!file_exists($phpmailer_path)) {
        throw new Exception("邮件库缺失");
    }
    require $phpmailer_path;
    require __DIR__ . '/../PHPMailer/src/PHPMailer.php';
    require __DIR__ . '/../PHPMailer/src/SMTP.php';
    $mail = new PHPMailer(true);
    try {
        $mail->SMTPDebug = 0;
        $mail->Debugoutput = function($str, $level) {
            if ($level <= 1) {
                error_log('PHPMailer: ' . $str);
            }
        };
        $mail->isSMTP();
        $mail->Host       = $settings['mail_smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $settings['mail_smtp_user'];
        $mail->Password   = $settings['mail_smtp_pass'];
        $mail->SMTPSecure = $settings['mail_smtp_secure'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = intval($settings['mail_smtp_port'] ?? 465);
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom($settings['mail_smtp_user'], ($settings['site_name'] ?? 'API服务'));
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = '【' . ($settings['site_name'] ?? 'API服务') . '】您的临时API密钥';
        $site_name = $settings['site_name'] ?? 'huliapi';
        $current_year = date('Y');
        $logo_url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/assets/images/logo-sidebar.png';
        $mail->Body = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 15px; background-color: #f0f3f8; font-family: \'PingFang SC\', \'Microsoft YaHei\', sans-serif;">
<div style="max-width: 600px; margin: 0 auto; width: 100%; background-color: #ffffff; border-radius: 16px; box-shadow: 0 4px 20px rgba(32,102,255,0.08);">
    <div style="padding: 30px 20px; text-align: center; background: linear-gradient(135deg, #2066ff 0%, #1955d4 100%); border-radius: 16px 16px 0 0;">
        <img style="max-height: 45px; width: auto; max-width: 100%;" src="' . $logo_url . '" alt="' . $site_name . '" />
    </div>
    <div style="padding: 30px 20px;">
        <h1 style="color: #2066ff; font-size: 24px; margin: 0 0 25px; text-align: center; font-weight: bold;">临时API密钥</h1>
        <p style="color: #333333; font-size: 15px; line-height: 1.8; margin: 0; font-weight: 600;">尊敬的用户：</p>
        <p style="color: #333333; font-size: 15px; line-height: 1.8; margin: 10px 0; font-weight: 600;">您好！您已成功申请临时API访问权限，以下是您的凭证信息：</p>
        <div style="background: linear-gradient(to right, #f8f9ff, #f0f5ff); border-radius: 12px; padding: 20px; margin: 20px 0; border: 1px solid rgba(32,102,255,0.1);">
            <div style="margin-bottom: 15px;">
                <span style="color: #2066ff; font-weight: bold;">API密钥：</span>
                <div style="background-color: #ffffff; border-radius: 8px; padding: 12px; margin-top: 8px; font-family: monospace; word-break: break-all; border: 1px solid #eef0f5;">' . $api_key . '</div>
            </div>
            <p style="color: #666666; font-size: 14px; line-height: 1.8; margin: 8px 0;"><span style="display: inline-block; width: 100px;">调用次数限制：</span> ' . $limit_calls . ' 次</p>
            <p style="color: #666666; font-size: 14px; line-height: 1.8; margin: 8px 0;"><span style="display: inline-block; width: 100px;">有效期至：</span> ' . $expires_at . '</p>
        </div>
        <div style="background-color: #f8f9fa; border-radius: 8px; padding: 15px; margin: 20px 0;">
            <p style="color: #666666; font-size: 13px; line-height: 1.6; margin: 0; font-weight: 600;"><span style="color: #2066ff;">●</span> 请妥善保管您的API密钥，不要泄露给他人</p>
            <p style="color: #666666; font-size: 13px; line-height: 1.6; margin: 8px 0 0; font-weight: 600;"><span style="color: #2066ff;">●</span> 此密钥仅用于临时测试用途，到期后将自动失效</p>
        </div>
        <p style="color: #666666; font-size: 13px; line-height: 1.6; margin: 20px 0 0; font-weight: 600;">如有任何问题，请联系客服支持。</p>
    </div>
    <div style="padding: 20px 15px; background-color: #f8f9fa; border-radius: 0 0 16px 16px; border-top: 1px solid #eef0f5;">
        <p style="color: #999999; font-size: 13px; text-align: center; margin: 0; line-height: 1.8; font-weight: 500;">本邮件由系统自动发送，请勿直接回复<br />Copyright © 2025-' . $current_year . ' huliapi 版权所有</p>
    </div>
</div>
</body>
</html>';
        $mail->send();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('邮件发送失败: ' . $e->getMessage() . ' ErrorInfo: ' . $mail->ErrorInfo);
        json_response(false, '邮件发送失败，请检查您的邮箱地址或联系管理员。');
    }
    $stmt_log_ip = $pdo->prepare("INSERT INTO huli_temp_key_logs (ip_address) VALUES (?)");
    $stmt_log_ip->execute([$ip_address]);
    $pdo->commit();
    $_SESSION['last_temp_key_sent'] = time();
    json_response(true, '申请成功！临时密钥已发送至您的邮箱，请注意查收。');
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[get_temp_key] 申请失败：' . $e->getMessage());
    json_response(false, '申请失败，请稍后重试。');
}
?>
