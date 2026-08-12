<?php
if (!defined('HULI_BROADCAST_LIB')) { define('HULI_BROADCAST_LIB', 1); }

function huli_broadcast_send_one($pdo, $id, &$err = null) {
    $stmt = $pdo->prepare("SELECT * FROM huli_email_broadcasts WHERE id = ?");
    $stmt->execute([$id]); $b = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$b) { $err = "群发任务不存在"; return false; }
    if ($b['status'] === 'sending') { $err = "该任务正在发送中"; return false; }
    if ($b['status'] === 'sent') { $err = "该任务已发送"; return false; }

    $settings = $pdo->query("SELECT setting_key,setting_value FROM huli_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    if (empty($settings['mail_smtp_host']) || empty($settings['mail_smtp_user'])) { $err = "SMTP未配置"; return false; }

    require_once __DIR__ . '/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/src/SMTP.php';

    $pdo->prepare("UPDATE huli_email_broadcasts SET status = 'sending', started_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$id]);

    $users = $pdo->query("SELECT email FROM huli_users WHERE email IS NOT NULL AND email != ''")->fetchAll(PDO::FETCH_COLUMN);
    $total = count($users);
    $pdo->prepare("UPDATE huli_email_broadcasts SET total_count = ? WHERE id = ?")->execute([$total, $id]);

    $sent = 0; $site_name = $settings['site_name'] ?? 'huliapi';
    foreach ($users as $email) {
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $settings['mail_smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $settings['mail_smtp_user'];
            $mail->Password = $settings['mail_smtp_pass'];
            $mail->SMTPSecure = ($settings['mail_smtp_secure'] ?? 'tls') === 'ssl' ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = intval($settings['mail_smtp_port']);
            $mail->CharSet = 'UTF-8';
            $mail->setFrom($settings['mail_smtp_user'], $site_name);
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = $b['title'];
            $body = str_replace('{{email}}', $email, $b['content']);
            $body = str_replace('{{site_name}}', $site_name, $body);
            $mail->Body = $body;
            $mail->send();
            $sent++;
        } catch (Exception $e) {}
        if ($sent > 0 && $sent % 10 === 0) {
            $pdo->prepare("UPDATE huli_email_broadcasts SET sent_count = ? WHERE id = ?")->execute([$sent, $id]);
        }
    }
    $pdo->prepare("UPDATE huli_email_broadcasts SET status = 'sent', sent_count = ?, finished_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$sent, $id]);
    return ['sent' => $sent, 'total' => $total];
}

function huli_broadcast_tick($pdo, $limit = 5) {
    $dispatched = [];
    try {
        $rows = $pdo->prepare("SELECT id FROM huli_email_broadcasts WHERE status = 'scheduled' AND scheduled_at IS NOT NULL AND scheduled_at <= NOW() ORDER BY scheduled_at ASC LIMIT $limit");
        $rows->execute();
        $ids = $rows->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $id) {
            $r = huli_broadcast_send_one($pdo, intval($id), $err);
            $dispatched[] = ['id' => intval($id), 'result' => $r, 'error' => $err ?? null];
        }
    } catch (Throwable $e) {
        $dispatched[] = ['id' => 0, 'result' => false, 'error' => $e->getMessage()];
    }
    return $dispatched;
}

function huli_broadcast_web_tick($pdo) {
    $lock = sys_get_temp_dir() . '/huli_broadcast_tick.lock';
    $fh = @fopen($lock, 'c+');
    if (!$fh) { return 'tick lock failed'; }
    if (!flock($fh, LOCK_EX | LOCK_NB)) { fclose($fh); return null; }
    $r = huli_broadcast_tick($pdo, 1);
    flock($fh, LOCK_UN); fclose($fh);
    if (!empty($r[0]['error'])) { return $r[0]['error']; }
    return null;
}
