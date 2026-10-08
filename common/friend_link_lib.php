<?php
if (!defined('HULI_FRIEND_LINK_LIB')) { define('HULI_FRIEND_LINK_LIB', 1); }

function huli_ensure_friend_link_columns(PDO $pdo)
{
    static $done = false;
    if ($done) { return; }
    $done = true;
    try {
        $columns = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'huli_friend_links'")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        return;
    }
    if (!in_array('email', $columns, true)) {
        try {
            $pdo->exec("ALTER TABLE `huli_friend_links` ADD COLUMN `email` varchar(100) DEFAULT NULL COMMENT '申请者联系邮箱（选填）' AFTER `user_id`");
        } catch (Exception $e) {
        }
    }
}

function huli_friend_link_resolve_recipient(PDO $pdo, array $link)
{
    if (!empty($link['email']) && filter_var($link['email'], FILTER_VALIDATE_EMAIL)) {
        return $link['email'];
    }
    if (!empty($link['user_id'])) {
        try {
            $stmt = $pdo->prepare("SELECT email FROM huli_users WHERE id = ?");
            $stmt->execute([$link['user_id']]);
            $email = $stmt->fetchColumn();
            if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        } catch (Exception $e) {
        }
    }
    return '';
}

function huli_friend_link_mail_template($type, array $link, $note, $site_name, $logo_url, $year)
{
    $siteLabel = htmlspecialchars($link['site_name'] ?? '', ENT_QUOTES, 'UTF-8');
    $urlLabel  = htmlspecialchars($link['url'] ?? '', ENT_QUOTES, 'UTF-8');
    $noteLabel = htmlspecialchars((string)$note, ENT_QUOTES, 'UTF-8');

    if ($type === 'reject') {
        $subject = '【' . $site_name . '】友链申请已拒绝';
        $heading = '友链申请已拒绝';
        $intro   = '您的友链申请未通过审核，详情如下：';
        $extraRow = '<p style="color: #666666; font-size: 14px; line-height: 1.8; margin: 8px 0;"><span style="display: inline-block; width: 100px;">拒绝原因：</span> ' . $noteLabel . '</p>';
        $tips = '<p style="color: #666666; font-size: 13px; line-height: 1.6; margin: 0; font-weight: 600;"><span style="color: #2066ff;">●</span> 如有疑问，请联系管理员</p>
            <p style="color: #666666; font-size: 13px; line-height: 1.6; margin: 8px 0 0; font-weight: 600;"><span style="color: #2066ff;">●</span> 您可以根据原因修改后重新申请</p>';
    } else {
        $subject = '【' . $site_name . '】友链申请已通过';
        $heading = '友链申请已通过';
        $intro   = '您的友链申请已通过审核，详情如下：';
        $extraRow = '';
        $tips = '<p style="color: #666666; font-size: 13px; line-height: 1.6; margin: 0; font-weight: 600;"><span style="color: #2066ff;">●</span> 您的友链已成功展示，感谢您的合作</p>
            <p style="color: #666666; font-size: 13px; line-height: 1.6; margin: 8px 0 0; font-weight: 600;"><span style="color: #2066ff;">●</span> 如有任何问题，请联系管理员</p>';
    }

    $body = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 15px; background-color: #f0f3f8; font-family: \'PingFang SC\', \'Microsoft YaHei\', sans-serif;">
<div style="max-width: 600px; margin: 0 auto; width: 100%; background-color: #ffffff; border-radius: 16px; box-shadow: 0 4px 20px rgba(32,102,255,0.08);">
    <div style="padding: 30px 20px; text-align: center; background: linear-gradient(135deg, #2066ff 0%, #1955d4 100%); border-radius: 16px 16px 0 0;">
        <img style="max-height: 45px; width: auto; max-width: 100%;" src="' . $logo_url . '" alt="' . htmlspecialchars($site_name, ENT_QUOTES, 'UTF-8') . '" />
    </div>
    <div style="padding: 30px 20px;">
        <h1 style="color: #2066ff; font-size: 24px; margin: 0 0 25px; text-align: center; font-weight: bold;">' . $heading . '</h1>
        <p style="color: #333333; font-size: 15px; line-height: 1.8; margin: 0; font-weight: 600;">尊敬的用户：</p>
        <p style="color: #333333; font-size: 15px; line-height: 1.8; margin: 10px 0; font-weight: 600;">' . $intro . '</p>
        <div style="background: linear-gradient(to right, #f8f9ff, #f0f5ff); border-radius: 12px; padding: 20px; margin: 20px 0; border: 1px solid rgba(32,102,255,0.1);">
            <p style="color: #666666; font-size: 14px; line-height: 1.8; margin: 8px 0;"><span style="display: inline-block; width: 100px;">网站名称：</span> ' . $siteLabel . '</p>
            <p style="color: #666666; font-size: 14px; line-height: 1.8; margin: 8px 0;"><span style="display: inline-block; width: 100px;">网站URL：</span> <a href="' . $urlLabel . '" target="_blank">' . $urlLabel . '</a></p>
            ' . $extraRow . '
        </div>
        <div style="background-color: #f8f9fa; border-radius: 8px; padding: 15px; margin: 20px 0;">
            ' . $tips . '
        </div>
        <p style="color: #666666; font-size: 13px; line-height: 1.6; margin: 20px 0 0; font-weight: 600;">如有任何问题，请联系客服支持。</p>
    </div>
    <div style="padding: 20px 15px; background-color: #f8f9fa; border-radius: 0 0 16px 16px; border-top: 1px solid #eef0f5;">
        <p style="color: #999999; font-size: 13px; text-align: center; margin: 0; line-height: 1.8; font-weight: 500;">本邮件由系统自动发送，请勿直接回复<br />Copyright © 2025-' . $year . ' huliapi 版权所有</p>
    </div>
</div>
</body>
</html>';

    return [$subject, $body];
}

function huli_friend_link_send_notify(PDO $pdo, array $link, $type, $note, $site_name, $logo_url, $year)
{
    $to = huli_friend_link_resolve_recipient($pdo, $link);
    if ($to === '') {
        return false;
    }
    require_once __DIR__ . '/mail.php';
    list($subject, $body) = huli_friend_link_mail_template($type, $link, $note, $site_name, $logo_url, $year);
    return send_mail($to, $subject, $body, $pdo);
}
