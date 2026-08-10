<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
header('Content-Type: application/json; charset=utf-8');
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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
$type = $_POST['type'] ?? '';
$api_id = isset($_POST['api_id']) && $_POST['api_id'] !== '' ? intval($_POST['api_id']) : null;
$content = trim($_POST['content'] ?? '');
$contact = trim($_POST['contact'] ?? '');
$user_id = $_SESSION['user_id'] ?? null;
if (empty($type) || empty($content)) {
    json_response(false, '反馈类型和内容不能为空。');
}
if ($type === 'api' && $api_id === null) {
    json_response(false, '请选择一个需要反馈的接口。');
}
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $sql = "INSERT INTO sl_feedback (user_id, api_id, type, content, contact) VALUES (?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $api_id, $type, $content, $contact]);
    $feedback_id = $pdo->lastInsertId();
    if (!$feedback_id) {
        throw new Exception("无法将反馈存入数据库。");
    }
    try {
        $stmt_settings = $pdo->query("SELECT setting_key, setting_value FROM sl_settings");
        $settings = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR);
        $admin_email_stmt = $pdo->query("SELECT email FROM sl_admins ORDER BY id ASC LIMIT 1");
        $admin_email = $admin_email_stmt->fetchColumn();
        if ($admin_email && !empty($settings['mail_smtp_host']) && !empty($settings['mail_smtp_user']) && !empty($settings['mail_smtp_pass'])) {
            if (file_exists('../../common/PHPMailer/src/Exception.php')) {
                require '../../common/PHPMailer/src/Exception.php';
                require '../../common/PHPMailer/src/PHPMailer.php';
                require '../../common/PHPMailer/src/SMTP.php';
                $logo_url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/assets/images/logo-sidebar.png';
                $site_name = $settings['site_name'] ?? 'huliapi';
                $current_year = date('Y');
                $type_text = $type === 'api' ? '接口问题' : '意见建议';
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = $settings['mail_smtp_host'];
                $mail->SMTPAuth   = true;
                $mail->Username   = $settings['mail_smtp_user'];
                $mail->Password   = $settings['mail_smtp_pass'];
                $mail->SMTPSecure = $settings['mail_smtp_secure'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = intval($settings['mail_smtp_port']);
                $mail->CharSet    = 'UTF-8';
                $mail->setFrom($settings['mail_smtp_user'], $site_name);
                $mail->addAddress($admin_email);
                $mail->isHTML(true);
                $mail->Subject = '【' . $site_name . '通知】用户反馈 - ' . $type_text;
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
        <h1 style="color: #2066ff; font-size: 24px; margin: 0 0 25px; text-align: center; font-weight: bold;">用户反馈通知</h1>
        <p style="color: #333333; font-size: 15px; line-height: 1.8; margin: 0; font-weight: 600;">尊敬的管理员：</p>
        <p style="color: #333333; font-size: 15px; line-height: 1.8; margin: 10px 0; font-weight: 600;">系统收到一条新的用户反馈，请及时处理：</p>
        <div style="background: linear-gradient(to right, #f8f9ff, #f0f5ff); border-radius: 12px; padding: 20px; margin: 20px 0; border: 1px solid rgba(32,102,255,0.1);">
            <p style="color: #666666; font-size: 14px; line-height: 1.8; margin: 8px 0;"><span style="display: inline-block; width: 90px;">反馈类型：</span> <strong>' . $type_text . '</strong></p>
            <p style="color: #666666; font-size: 14px; line-height: 1.8; margin: 8px 0;"><span style="display: inline-block; width: 90px;">联系方式：</span> ' . htmlspecialchars($contact) . '</p>
            <p style="color: #666666; font-size: 14px; line-height: 1.8; margin: 8px 0;"><span style="display: inline-block; width: 90px;">提交时间：</span> ' . date('Y-m-d H:i:s') . '</p>
            <p style="color: #666666; font-size: 14px; line-height: 1.8; margin: 8px 0;"><span style="display: inline-block; width: 90px;">反馈内容：</span></p>
            <div style="background-color: #ffffff; border-radius: 8px; padding: 15px; margin-top: 8px; border: 1px solid #eef0f5;">' . nl2br(htmlspecialchars($content)) . '</div>
        </div>
        <div style="background-color: #f8f9fa; border-radius: 8px; padding: 15px; margin: 20px 0;">
            <p style="color: #666666; font-size: 13px; line-height: 1.6; margin: 0; font-weight: 600;"><span style="color: #2066ff;">●</span> 请及时登录后台查看并回复用户</p>
            <p style="color: #666666; font-size: 13px; line-height: 1.6; margin: 8px 0 0; font-weight: 600;"><span style="color: #2066ff;">●</span> 用户反馈有助于我们改进服务</p>
        </div>
        <div style="text-align: center; margin: 30px 0;">
            <a style="display: inline-block; padding: 12px 35px; background-color: #2066ff; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 15px; font-weight: 500;" href="' . ($settings['admin_url'] ?? '#') . '" target="_blank" rel="noopener">立即登录后台处理</a>
        </div>
        <p style="color: #666666; font-size: 13px; line-height: 1.6; margin: 20px 0 0; font-weight: 600;">如有任何问题，请联系系统管理员。</p>
    </div>
    <div style="padding: 20px 15px; background-color: #f8f9fa; border-radius: 0 0 16px 16px; border-top: 1px solid #eef0f5;">
        <p style="color: #999999; font-size: 13px; text-align: center; margin: 0; line-height: 1.8; font-weight: 500;">本邮件由系统自动发送，请勿直接回复<br />Copyright © 2025-' . $current_year . ' huliapi 版权所有</p>
    </div>
</div>
</body>
</html>';
                $mail->send();
            }
        }
    } catch (Exception $mail_error) {
    }
    json_response(true, '您的反馈已成功提交，感谢您的支持！');
} catch (Exception $e) {
    json_response(false, '提交失败，请稍后重试。');
}
?>
