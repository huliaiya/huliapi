<?php
require_once __DIR__ . '/../common/session_boot.php';
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
if (!file_exists('../config.php')) { die("出现错误！配置文件丢失。"); }
require_once '../config.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
if (!file_exists('../common/PHPMailer/src/Exception.php') || !file_exists('../common/PHPMailer/src/PHPMailer.php') || !file_exists('../common/PHPMailer/src/SMTP.php')) {
    die("错误：PHPMailer库文件未找到。请确认您已将PHPMailer解压到网站根目录的 common/PHPMailer/ 文件夹下。");
}
require '../common/PHPMailer/src/Exception.php';
require '../common/PHPMailer/src/PHPMailer.php';
require '../common/PHPMailer/src/SMTP.php';
$feedback_output = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recipient_email'])) {
    ob_start();
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt_get = $pdo->query("SELECT setting_key, setting_value FROM huli_settings");
        $settings = $stmt_get->fetchAll(PDO::FETCH_KEY_PAIR);
        $site_name = $settings['site_name'] ?? 'huliapi';
        $recipient_email = trim($_POST['recipient_email']);
        if (empty($recipient_email)) {
            throw new Exception("收件人邮箱地址不能为空");
        }
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        $mail->Host       = $settings['mail_smtp_host'] ?? '';
        $mail->SMTPAuth   = true;
        $mail->Username   = $settings['mail_smtp_user'] ?? '';
        $mail->Password   = $settings['mail_smtp_pass'] ?? '';
        $mail->SMTPSecure = ($settings['mail_smtp_secure'] ?? 'ssl') === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $settings['mail_smtp_port'] ?? 465;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom($settings['mail_smtp_user'] ?? '', $site_name);
        $mail->addAddress($recipient_email);
        $mail->isHTML(true);
        $mail->Subject = '【' . $site_name . '】测试邮件';
        $mail->Body    = '如果您收到这封邮件，说明您的SMTP配置正确！';
        if (!$mail->send()) {
            throw new Exception($mail->ErrorInfo);
        }
        echo '<strong>邮件已成功发送！</strong>';
    } catch (Exception $e) {
        echo "<strong>邮件发送失败。</strong> 错误信息: " . htmlspecialchars($e->getMessage());
    }
    $feedback_output = ob_get_clean();
}
?>

<!DOCTYPE html>
<html lang="zh">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <link rel="stylesheet" type="text/css" href="../assets/css/materialdesignicons.min.css">
    <link rel="stylesheet" type="text/css" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="../assets/css/style.min.css">
    <style>
        .output {
            margin-top: 20px;
            padding: 15px;
            background-color: #1e1e1e;
            color: #d4d4d4;
            border-radius: 4px;
            white-space: pre-wrap;
            word-wrap: break-word;
            font-family: 'Courier New', monospace;
            max-height: 400px;
            overflow-y: auto;
            font-size: 13px;
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <header class="card-header">
                    <div class="card-title">邮件发送测试</div>
                </header>
                <div class="card-body">
                    <p>此工具将使用您在"系统设置"中保存的SMTP配置来发送一封测试邮件。</p>
                    <form method="POST" action="" class="row g-3">
                        <div class="col-md-6">
                            <label for="recipient_email" class="form-label">收件人邮箱地址</label>
                            <input type="email" class="form-control" id="recipient_email" name="recipient_email" required>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">发送测试邮件</button>
                        </div>
                    </form>
                    <?php if ($feedback_output): ?>
                        <div class="output">
                            <h5>调试输出:</h5>
                            <?php echo nl2br(htmlspecialchars($feedback_output)); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script type="text/javascript" src="../assets/js/jquery.min.js"></script>
<script type="text/javascript" src="../assets/js/popper.min.js"></script>
<script type="text/javascript" src="../assets/js/bootstrap.min.js"></script>
<script type="text/javascript" src="../assets/js/main.min.js"></script>
</body>
</html>
