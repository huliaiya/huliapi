<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
function send_mail($to, $subject, $body, $pdo) {
    try {
        $stmt_get = $pdo->query("SELECT setting_key, setting_value FROM huli_settings");
        $settings = $stmt_get->fetchAll(PDO::FETCH_KEY_PAIR);
        $site_name = $settings['site_name'] ?? 'huliapi';
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->SMTPDebug = SMTP::DEBUG_OFF;
        $mail->Host       = $settings['mail_smtp_host'] ?? '';
        $mail->SMTPAuth   = true;
        $mail->Username   = $settings['mail_smtp_user'] ?? '';
        $mail->Password   = $settings['mail_smtp_pass'] ?? '';        
        $secure_type = $settings['mail_smtp_secure'] ?? 'ssl';
        if ($secure_type === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = intval($settings['mail_smtp_port'] ?? 465);
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = intval($settings['mail_smtp_port'] ?? 587);
        }        
        $mail->CharSet    = 'UTF-8';
        $mail->Encoding   = 'base64';      
        $mail->setFrom($settings['mail_smtp_user'] ?? '', $site_name);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);
        if (!$mail->send()) {
            throw new Exception($mail->ErrorInfo);
        }
        return true;
    } catch (Exception $e) {
        error_log("邮件发送失败: " . $e->getMessage());
        return false;
    }
}
?>
