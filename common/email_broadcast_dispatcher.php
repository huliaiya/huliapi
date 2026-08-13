<?php
if (!defined('HULI_BROADCAST_LIB')) { define('HULI_BROADCAST_LIB', 1); }

define('HULI_BROADCAST_COLUMNS', [
    'send_type' => "ALTER TABLE huli_email_broadcasts ADD COLUMN `send_type` enum('once','daily') NOT NULL DEFAULT 'once' COMMENT '发送模式：once=仅一次 daily=每日定时'",
    'last_run_at' => "ALTER TABLE huli_email_broadcasts ADD COLUMN `last_run_at` datetime DEFAULT NULL COMMENT '最近一次实际发送时间'",
    'last_error' => "ALTER TABLE huli_email_broadcasts ADD COLUMN `last_error` varchar(500) DEFAULT NULL COMMENT '最近一次发送错误信息'",
]);

function huli_ensure_broadcast_columns(PDO $pdo)
{
    try {
        $existing = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'huli_email_broadcasts'")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        return;
    }
    foreach (HULI_BROADCAST_COLUMNS as $column => $sql) {
        if (!in_array($column, $existing, true)) {
            try {
                $pdo->exec($sql);
            } catch (Exception $e) {
            }
        }
    }
}

function huli_broadcast_pick(array $row, array $keys, $default = '')
{
    foreach ($keys as $key) {
        if (isset($row[$key]) && $row[$key] !== '') { return $row[$key]; }
    }
    return $default;
}

function huli_broadcast_render_template($template, array $user, array $settings, array $broadcast, $html = true)
{
    $email = trim((string)($user['email'] ?? ''));
    $siteName = $settings['site_name'] ?? 'huliapi';
    $value = function ($v) use ($html) {
        $v = (string)$v;
        return $html ? htmlspecialchars($v, ENT_QUOTES, 'UTF-8') : strip_tags($v);
    };
    $map = [
        '{{email}}' => $value($email),
        '{{site_name}}' => $value($siteName),
        '{{user_id}}' => $value(huli_broadcast_pick($user, ['id', 'user_id'])),
        '{{username}}' => $value(huli_broadcast_pick($user, ['username', 'user_name', 'name', 'nickname'], $email)),
        '{{nickname}}' => $value(huli_broadcast_pick($user, ['nickname', 'name', 'username'], $email)),
        '{{qq}}' => $value(huli_broadcast_pick($user, ['qq'])),
        '{{points}}' => $value(huli_broadcast_pick($user, ['points', 'point'], 0)),
        '{{balance}}' => $value(huli_broadcast_pick($user, ['balance', 'money', 'amount'], 0)),
        '{{vip_expire_time}}' => $value(huli_broadcast_pick($user, ['vip_expire_time', 'vip_end_time', 'expire_time'])),
        '{{register_time}}' => $value(huli_broadcast_pick($user, ['created_at', 'create_time', 'reg_time', 'register_time'])),
        '{{broadcast_title}}' => $value($broadcast['title'] ?? ''),
        '{{send_time}}' => $value(date('Y-m-d H:i:s')),
        '{{today}}' => $value(date('Y-m-d')),
        '{{year}}' => $value(date('Y')),
        '{{month}}' => $value(date('m')),
        '{{day}}' => $value(date('d')),
    ];
    return strtr((string)$template, $map);
}

function huli_broadcast_send_one($pdo, $id, &$err = null)
{
    huli_ensure_broadcast_columns($pdo);
    $stmt = $pdo->prepare("SELECT * FROM huli_email_broadcasts WHERE id = ?");
    $stmt->execute([$id]);
    $b = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$b) { $err = "群发任务不存在"; return false; }
    if ($b['status'] === 'sending') { $err = "该任务正在发送中"; return false; }
    $nowSql = date('Y-m-d H:i:s');
    if ($b['status'] === 'sent' && $b['send_type'] === 'once') { $err = "该任务已发送"; return false; }

    $settings = $pdo->query("SELECT setting_key,setting_value FROM huli_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    if (empty($settings['mail_smtp_host']) || empty($settings['mail_smtp_user'])) {
        $err = "SMTP未配置";
        $pdo->prepare("UPDATE huli_email_broadcasts SET last_error = ? WHERE id = ?")->execute([$err, $id]);
        return false;
    }

    require_once __DIR__ . '/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/src/SMTP.php';

    $pdo->prepare("UPDATE huli_email_broadcasts SET status = 'sending', started_at = ?, last_error = NULL WHERE id = ?")->execute([$nowSql, $id]);

    $users = $pdo->query("SELECT * FROM huli_users WHERE email IS NOT NULL AND email != ''")->fetchAll(PDO::FETCH_ASSOC);
    $total = count($users);
    $pdo->prepare("UPDATE huli_email_broadcasts SET total_count = ? WHERE id = ?")->execute([$total, $id]);

    if ($total === 0) {
        $pdo->prepare("UPDATE huli_email_broadcasts SET status = ?, sent_count = 0, last_run_at = ?, finished_at = ? WHERE id = ?")
            ->execute([$b['send_type'] === 'daily' ? 'scheduled' : 'sent', $nowSql, $nowSql, $id]);
        $err = "没有可发送的用户";
        return ['sent' => 0, 'total' => 0];
    }

    $sent = 0;
    $failed = 0;
    $firstError = null;
    $site_name = $settings['site_name'] ?? 'huliapi';
    foreach ($users as $user) {
        $email = trim((string)($user['email'] ?? ''));
        if ($email === '') { continue; }
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
            $mail->Subject = huli_broadcast_render_template($b['title'], $user, $settings, $b, false);
            $body = huli_broadcast_render_template($b['content'], $user, $settings, $b);
            $mail->Body = $body;
            $mail->send();
            $sent++;
        } catch (Exception $e) {
            $failed++;
            if ($firstError === null) { $firstError = $e->getMessage(); }
        }
        if ($sent > 0 && $sent % 10 === 0) {
            $pdo->prepare("UPDATE huli_email_broadcasts SET sent_count = ? WHERE id = ?")->execute([$sent, $id]);
        }
    }

    if ($sent === 0 && $failed > 0) {
        $pdo->prepare("UPDATE huli_email_broadcasts SET status = 'scheduled', last_error = ? WHERE id = ?")
            ->execute([mb_substr($firstError, 0, 500), $id]);
        $err = "发送失败：" . $firstError;
        return false;
    }

    $newStatus = $b['send_type'] === 'daily' ? 'scheduled' : 'sent';
    $pdo->prepare("UPDATE huli_email_broadcasts SET status = ?, sent_count = ?, last_run_at = ?, finished_at = ?, last_error = NULL WHERE id = ?")
        ->execute([$newStatus, $sent, $nowSql, $nowSql, $id]);
    return ['sent' => $sent, 'total' => $total, 'failed' => $failed];
}

function huli_broadcast_tick($pdo, $limit = 5)
{
    huli_ensure_broadcast_columns($pdo);
    $dispatched = [];
    try {
        $rows = $pdo->prepare("SELECT id FROM huli_email_broadcasts WHERE status = 'scheduled' AND scheduled_at IS NOT NULL ORDER BY scheduled_at ASC LIMIT " . max(1, min(50, intval($limit)) * 10));
        $rows->execute();
        $ids = $rows->fetchAll(PDO::FETCH_COLUMN);
        $now = new DateTime();
        $dispatchedCount = 0;
        foreach ($ids as $id) {
            if ($dispatchedCount >= intval($limit)) { break; }
            $stmt = $pdo->prepare("SELECT send_type, scheduled_at, last_run_at FROM huli_email_broadcasts WHERE id = ?");
            $stmt->execute([intval($id)]);
            $meta = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$meta) { continue; }
            if ($meta['send_type'] === 'daily') {
                $scheduledTime = strtotime($meta['scheduled_at']);
                if ($scheduledTime === false) { continue; }
                $lastRun = $meta['last_run_at'] ? strtotime($meta['last_run_at']) : 0;
                $today = new DateTime('today');
                $lastRunDay = $lastRun ? (new DateTime())->setTimestamp($lastRun)->format('Y-m-d') : null;
                $timeNow = $now->format('H:i:s');
                $timeScheduled = date('H:i:s', $scheduledTime);
                if ($lastRunDay === $today->format('Y-m-d')) { continue; }
                if ($timeNow < $timeScheduled) { continue; }
            } else {
                if (strtotime($meta['scheduled_at']) > time()) { continue; }
            }
            $r = huli_broadcast_send_one($pdo, intval($id), $err);
            $dispatched[] = ['id' => intval($id), 'result' => $r, 'error' => $err ?? null];
            $dispatchedCount++;
        }
    } catch (Throwable $e) {
        $dispatched[] = ['id' => 0, 'result' => false, 'error' => $e->getMessage()];
    }
    return $dispatched;
}

function huli_broadcast_web_tick($pdo)
{
    $lock = sys_get_temp_dir() . '/huli_broadcast_tick.lock';
    $fh = @fopen($lock, 'c+');
    if (!$fh) { return 'tick lock failed'; }
    if (!flock($fh, LOCK_EX | LOCK_NB)) { fclose($fh); return null; }
    $r = huli_broadcast_tick($pdo, 1);
    flock($fh, LOCK_UN);
    fclose($fh);
    if (!empty($r[0]['error'])) { return $r[0]['error']; }
    return null;
}
