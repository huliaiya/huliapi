<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}
if (file_exists('../config.php')) {
    require_once '../config.php';
} else {
    die("出现错误！配置文件丢失。");
}
$username = htmlspecialchars($_SESSION['admin_username']);
$feedback_msg = '';
$feedback_type = '';
$get_page = isset($_GET['page']) ? intval($_GET['page']) : 0;
$get_status = isset($_GET['status']) ? preg_replace('/[^a-z_0-9]/i', '', $_GET['status']) : '';
$get_is_hidden = isset($_GET['is_hidden']) ? (int)$_GET['is_hidden'] : 0;
$get_name = isset($_GET['name']) ? $_GET['name'] : '';
$page = $get_page > 0 ? $get_page : 1;
$limit = 18;
$offset = ($page - 1) * $limit;
$totalRecords = 0;
$totalPages = 0;
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET . ";connect_timeout=10",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    $stmt_settings = $pdo->query("SELECT setting_key, setting_value FROM huli_settings");
    $settings = [];
    while ($row = $stmt_settings->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $site_name = $settings['site_name'] ?? 'huliapi';
    $logo_url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/assets/images/logo-sidebar.png';
    $current_year = date('Y');
    $admin_url = $settings['admin_url'] ?? '#';
    if (isset($_GET['action']) && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        switch ($_GET['action']) {
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM huli_friend_links WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['feedback_msg'] = '友链已删除';
                break;
            case 'approve':
                $stmt = $pdo->prepare("UPDATE huli_friend_links SET status='approved', reviewed_at=NOW() WHERE id = ?");
                $stmt->execute([$id]);
                $stmt_link = $pdo->prepare("SELECT site_name, url, user_id FROM huli_friend_links WHERE id = ?");
                $stmt_link->execute([$id]);
                $link = $stmt_link->fetch(PDO::FETCH_ASSOC);
                if ($link && $link['user_id']) {
                    $stmt_user = $pdo->prepare("SELECT email FROM huli_users WHERE id = ?");
                    $stmt_user->execute([$link['user_id']]);
                    $user = $stmt_user->fetch(PDO::FETCH_ASSOC);
                    if ($user && $user['email']) {
                        require_once '../common/mail.php';
                        $subject = '【' . $site_name . '】友链申请已通过';
                        $body = '<!DOCTYPE html>
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
        <h1 style="color: #2066ff; font-size: 24px; margin: 0 0 25px; text-align: center; font-weight: bold;">友链申请已通过</h1>
        <p style="color: #333333; font-size: 15px; line-height: 1.8; margin: 0; font-weight: 600;">尊敬的用户：</p>
        <p style="color: #333333; font-size: 15px; line-height: 1.8; margin: 10px 0; font-weight: 600;">您的友链申请已通过审核，详情如下：</p>
        <div style="background: linear-gradient(to right, #f8f9ff, #f0f5ff); border-radius: 12px; padding: 20px; margin: 20px 0; border: 1px solid rgba(32,102,255,0.1);">
            <p style="color: #666666; font-size: 14px; line-height: 1.8; margin: 8px 0;"><span style="display: inline-block; width: 100px;">网站名称：</span> ' . htmlspecialchars($link['site_name']) . '</p>
            <p style="color: #666666; font-size: 14px; line-height: 1.8; margin: 8px 0;"><span style="display: inline-block; width: 100px;">网站URL：</span> <a href="' . htmlspecialchars($link['url']) . '" target="_blank">' . htmlspecialchars($link['url']) . '</a></p>
        </div>
        <div style="background-color: #f8f9fa; border-radius: 8px; padding: 15px; margin: 20px 0;">
            <p style="color: #666666; font-size: 13px; line-height: 1.6; margin: 0; font-weight: 600;"><span style="color: #2066ff;">●</span> 您的友链已成功展示，感谢您的合作</p>
            <p style="color: #666666; font-size: 13px; line-height: 1.6; margin: 8px 0 0; font-weight: 600;"><span style="color: #2066ff;">●</span> 如有任何问题，请联系管理员</p>
        </div>
        <p style="color: #666666; font-size: 13px; line-height: 1.6; margin: 20px 0 0; font-weight: 600;">如有任何问题，请联系客服支持。</p>
    </div>
    <div style="padding: 20px 15px; background-color: #f8f9fa; border-radius: 0 0 16px 16px; border-top: 1px solid #eef0f5;">
        <p style="color: #999999; font-size: 13px; text-align: center; margin: 0; line-height: 1.8; font-weight: 500;">本邮件由系统自动发送，请勿直接回复<br />Copyright © 2025-' . $current_year . ' huliapi 版权所有</p>
    </div>
</div>
</body>
</html>';
                        send_mail($user['email'], $subject, $body, $pdo);
                    }
                }
                $_SESSION['feedback_msg'] = '友链已通过审核';
                break;
            case 'reject':
                $note = isset($_POST['reject_note']) ? trim($_POST['reject_note']) : '未提供原因';
                $stmt = $pdo->prepare("UPDATE huli_friend_links SET status='rejected', review_note=?, reviewed_at=NOW() WHERE id = ?");
                $stmt->execute([$note, $id]);
                $stmt_link = $pdo->prepare("SELECT site_name, url, user_id FROM huli_friend_links WHERE id = ?");
                $stmt_link->execute([$id]);
                $link = $stmt_link->fetch(PDO::FETCH_ASSOC);
                if ($link && $link['user_id']) {
                    $stmt_user = $pdo->prepare("SELECT email FROM huli_users WHERE id = ?");
                    $stmt_user->execute([$link['user_id']]);
                    $user = $stmt_user->fetch(PDO::FETCH_ASSOC);
                    if ($user && $user['email']) {
                        require_once '../common/mail.php';
                        $subject = '【' . $site_name . '】友链申请已拒绝';
                        $body = '<!DOCTYPE html>
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
        <h1 style="color: #2066ff; font-size: 24px; margin: 0 0 25px; text-align: center; font-weight: bold;">友链申请已拒绝</h1>
        <p style="color: #333333; font-size: 15px; line-height: 1.8; margin: 0; font-weight: 600;">尊敬的用户：</p>
        <p style="color: #333333; font-size: 15px; line-height: 1.8; margin: 10px 0; font-weight: 600;">您的友链申请未通过审核，详情如下：</p>
        <div style="background: linear-gradient(to right, #f8f9ff, #f0f5ff); border-radius: 12px; padding: 20px; margin: 20px 0; border: 1px solid rgba(32,102,255,0.1);">
            <p style="color: #666666; font-size: 14px; line-height: 1.8; margin: 8px 0;"><span style="display: inline-block; width: 100px;">网站名称：</span> ' . htmlspecialchars($link['site_name']) . '</p>
            <p style="color: #666666; font-size: 14px; line-height: 1.8; margin: 8px 0;"><span style="display: inline-block; width: 100px;">网站URL：</span> <a href="' . htmlspecialchars($link['url']) . '" target="_blank">' . htmlspecialchars($link['url']) . '</a></p>
            <p style="color: #666666; font-size: 14px; line-height: 1.8; margin: 8px 0;"><span style="display: inline-block; width: 100px;">拒绝原因：</span> ' . htmlspecialchars($note) . '</p>
        </div>
        <div style="background-color: #f8f9fa; border-radius: 8px; padding: 15px; margin: 20px 0;">
            <p style="color: #666666; font-size: 13px; line-height: 1.6; margin: 0; font-weight: 600;"><span style="color: #2066ff;">●</span> 如有疑问，请联系管理员</p>
            <p style="color: #666666; font-size: 13px; line-height: 1.6; margin: 8px 0 0; font-weight: 600;"><span style="color: #2066ff;">●</span> 您可以根据原因修改后重新申请</p>
        </div>
        <p style="color: #666666; font-size: 13px; line-height: 1.6; margin: 20px 0 0; font-weight: 600;">如有任何问题，请联系客服支持。</p>
    </div>
    <div style="padding: 20px 15px; background-color: #f8f9fa; border-radius: 0 0 16px 16px; border-top: 1px solid #eef0f5;">
        <p style="color: #999999; font-size: 13px; text-align: center; margin: 0; line-height: 1.8; font-weight: 500;">本邮件由系统自动发送，请勿直接回复<br />Copyright © 2025-' . $current_year . ' huliapi 版权所有</p>
    </div>
</div>
</body>
</html>';
                        send_mail($user['email'], $subject, $body, $pdo);
                    }
                }
                $_SESSION['feedback_msg'] = '友链已拒绝';
                break;
            case 'toggle':
                $stmt = $pdo->prepare("SELECT is_hidden FROM huli_friend_links WHERE id = ?");
                $stmt->execute([$id]);
                $link = $stmt->fetch();
                $newStatus = $link['is_hidden'] ? 0 : 1;
                $stmt = $pdo->prepare("UPDATE huli_friend_links SET is_hidden=? WHERE id = ?");
                $stmt->execute([$newStatus, $id]);
                $_SESSION['feedback_msg'] = '友链' . ($newStatus ? '已隐藏' : '已显示');
                break;
        }
        $_SESSION['feedback_type'] = 'success';
        $redirectParams = [];
        if (isset($get_page)) $redirectParams[] = "page={$get_page}";
        if (isset($get_status)) $redirectParams[] = "status={$get_status}";
        $redirectUrl = 'friend_links.php' . (count($redirectParams) ? '?' . implode('&', $redirectParams) : '');
        header('Location: ' . $redirectUrl);
        exit;
    }
    if (isset($_POST['batch_action']) && isset($_POST['ids'])) {
        $ids = array_map('intval', $_POST['ids']);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo->beginTransaction();
        try {
            switch ($_POST['batch_action']) {
                case 'approve':
                    $stmt = $pdo->prepare("UPDATE huli_friend_links SET status='approved', reviewed_at=NOW() WHERE id IN ($placeholders)");
                    $stmt->execute($ids);
                    $stmt_links = $pdo->prepare("SELECT site_name, url, user_id FROM huli_friend_links WHERE id IN ($placeholders)");
                    $stmt_links->execute($ids);
                    $links = $stmt_links->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($links as $link) {
                        if ($link && $link['user_id']) {
                            $stmt_user = $pdo->prepare("SELECT email FROM huli_users WHERE id = ?");
                            $stmt_user->execute([$link['user_id']]);
                            $user = $stmt_user->fetch(PDO::FETCH_ASSOC);
                            if ($user && $user['email']) {
                                require_once '../common/mail.php';
                                $subject = '【' . $site_name . '】友链申请已通过';
                                $body = '<!DOCTYPE html>
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
        <h1 style="color: #2066ff; font-size: 24px; margin: 0 0 25px; text-align: center; font-weight: bold;">友链申请已通过</h1>
        <p style="color: #333333; font-size: 15px; line-height: 1.8; margin: 0; font-weight: 600;">尊敬的用户：</p>
        <p style="color: #333333; font-size: 15px; line-height: 1.8; margin: 10px 0; font-weight: 600;">您的友链申请已通过审核，详情如下：</p>
        <div style="background: linear-gradient(to right, #f8f9ff, #f0f5ff); border-radius: 12px; padding: 20px; margin: 20px 0; border: 1px solid rgba(32,102,255,0.1);">
            <p style="color: #666666; font-size: 14px; line-height: 1.8; margin: 8px 0;"><span style="display: inline-block; width: 100px;">网站名称：</span> ' . htmlspecialchars($link['site_name']) . '</p>
            <p style="color: #666666; font-size: 14px; line-height: 1.8; margin: 8px 0;"><span style="display: inline-block; width: 100px;">网站URL：</span> <a href="' . htmlspecialchars($link['url']) . '" target="_blank">' . htmlspecialchars($link['url']) . '</a></p>
        </div>
        <div style="background-color: #f8f9fa; border-radius: 8px; padding: 15px; margin: 20px 0;">
            <p style="color: #666666; font-size: 13px; line-height: 1.6; margin: 0; font-weight: 600;"><span style="color: #2066ff;">●</span> 您的友链已成功展示，感谢您的合作</p>
            <p style="color: #666666; font-size: 13px; line-height: 1.6; margin: 8px 0 0; font-weight: 600;"><span style="color: #2066ff;">●</span> 如有任何问题，请联系管理员</p>
        </div>
        <p style="color: #666666; font-size: 13px; line-height: 1.6; margin: 20px 0 0; font-weight: 600;">如有任何问题，请联系客服支持。</p>
    </div>
    <div style="padding: 20px 15px; background-color: #f8f9fa; border-radius: 0 0 16px 16px; border-top: 1px solid #eef0f5;">
        <p style="color: #999999; font-size: 13px; text-align: center; margin: 0; line-height: 1.8; font-weight: 500;">本邮件由系统自动发送，请勿直接回复<br />Copyright © 2025-' . $current_year . ' huliapi 版权所有</p>
    </div>
</div>
</body>
</html>';
                                send_mail($user['email'], $subject, $body, $pdo);
                            }
                        }
                    }
                    $_SESSION['feedback_msg'] = '已批量通过' . count($ids) . '条友链';
                    break;
                case 'reject':
                    $note = $_POST['reject_note'] ?? '批量拒绝';
                    $stmt = $pdo->prepare("UPDATE huli_friend_links SET status='rejected', review_note=?, reviewed_at=NOW() WHERE id IN ($placeholders)");
                    $stmt->execute(array_merge([$note], $ids));
                    $stmt_links = $pdo->prepare("SELECT site_name, url, user_id FROM huli_friend_links WHERE id IN ($placeholders)");
                    $stmt_links->execute($ids);
                    $links = $stmt_links->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($links as $link) {
                        if ($link && $link['user_id']) {
                            $stmt_user = $pdo->prepare("SELECT email FROM huli_users WHERE id = ?");
                            $stmt_user->execute([$link['user_id']]);
                            $user = $stmt_user->fetch(PDO::FETCH_ASSOC);
                            if ($user && $user['email']) {
                                require_once '../common/mail.php';
                                $subject = '【' . $site_name . '】友链申请已拒绝';
                                $body = '<!DOCTYPE html>
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
        <h1 style="color: #2066ff; font-size: 24px; margin: 0 0 25px; text-align: center; font-weight: bold;">友链申请已拒绝</h1>
        <p style="color: #333333; font-size: 15px; line-height: 1.8; margin: 0; font-weight: 600;">尊敬的用户：</p>
        <p style="color: #333333; font-size: 15px; line-height: 1.8; margin: 10px 0; font-weight: 600;">您的友链申请未通过审核，详情如下：</p>
        <div style="background: linear-gradient(to right, #f8f9ff, #f0f5ff); border-radius: 12px; padding: 20px; margin: 20px 0; border: 1px solid rgba(32,102,255,0.1);">
            <p style="color: #666666; font-size: 14px; line-height: 1.8; margin: 8px 0;"><span style="display: inline-block; width: 100px;">网站名称：</span> ' . htmlspecialchars($link['site_name']) . '</p>
            <p style="color: #666666; font-size: 14px; line-height: 1.8; margin: 8px 0;"><span style="display: inline-block; width: 100px;">网站URL：</span> <a href="' . htmlspecialchars($link['url']) . '" target="_blank">' . htmlspecialchars($link['url']) . '</a></p>
            <p style="color: #666666; font-size: 14px; line-height: 1.8; margin: 8px 0;"><span style="display: inline-block; width: 100px;">拒绝原因：</span> ' . htmlspecialchars($note) . '</p>
        </div>
        <div style="background-color: #f8f9fa; border-radius: 8px; padding: 15px; margin: 20px 0;">
            <p style="color: #666666; font-size: 13px; line-height: 1.6; margin: 0; font-weight: 600;"><span style="color: #2066ff;">●</span> 如有疑问，请联系管理员</p>
            <p style="color: #666666; font-size: 13px; line-height: 1.6; margin: 8px 0 0; font-weight: 600;"><span style="color: #2066ff;">●</span> 您可以根据原因修改后重新申请</p>
        </div>
        <p style="color: #666666; font-size: 13px; line-height: 1.6; margin: 20px 0 0; font-weight: 600;">如有任何问题，请联系客服支持。</p>
    </div>
    <div style="padding: 20px 15px; background-color: #f8f9fa; border-radius: 0 0 16px 16px; border-top: 1px solid #eef0f5;">
        <p style="color: #999999; font-size: 13px; text-align: center; margin: 0; line-height: 1.8; font-weight: 500;">本邮件由系统自动发送，请勿直接回复<br />Copyright © 2025-' . $current_year . ' huliapi 版权所有</p>
    </div>
</div>
</body>
</html>';
                                send_mail($user['email'], $subject, $body, $pdo);
                            }
                        }
                    }
                    $_SESSION['feedback_msg'] = '已批量拒绝' . count($ids) . '条友链';
                    break;
                case 'delete':
                    $stmt = $pdo->prepare("DELETE FROM huli_friend_links WHERE id IN ($placeholders)");
                    $stmt->execute($ids);
                    $_SESSION['feedback_msg'] = '已批量删除' . count($ids) . '条友链';
                    break;
                case 'toggle':
                    $stmt = $pdo->prepare("UPDATE huli_friend_links SET is_hidden = 1 - is_hidden WHERE id IN ($placeholders)");
                    $stmt->execute($ids);
                    $_SESSION['feedback_msg'] = '已批量切换' . count($ids) . '条友链显示状态';
                    break;
            }
            $pdo->commit();
            $_SESSION['feedback_type'] = 'success';
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        $redirectParams = [];
        if (isset($get_page)) $redirectParams[] = "page={$get_page}";
        if (isset($get_status)) $redirectParams[] = "status={$get_status}";
        $redirectUrl = 'friend_links.php' . (count($redirectParams) ? '?' . implode('&', $redirectParams) : '');
        header('Location: ' . $redirectUrl);
        exit;
    }
    $where = [];
    $params = [];
    if (!empty($get_name)) {
        $where[] = "site_name LIKE ?";
        $params[] = "%{$get_name}%";
    }
    if (!empty($get_status)) {
        $where[] = "status = ?";
        $params[] = $get_status;
    }
    if (isset($get_is_hidden) && $get_is_hidden !== '') {
        $where[] = "is_hidden = ?";
        $params[] = intval($get_is_hidden);
    }
    $whereStr = $where ? "WHERE " . implode(' AND ', $where) : '';
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM huli_friend_links {$whereStr}");
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetchColumn();
    $totalPages = max(1, ceil($totalRecords / $limit));
    $stmt = $pdo->prepare("SELECT id, site_name, url, logo, user_id, sort_order, created_at, status, is_hidden, review_note FROM huli_friend_links {$whereStr} ORDER BY sort_order DESC, created_at DESC LIMIT ?, ?");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key + 1, $value);
    }
    $stmt->bindValue(count($params) + 1, $offset, PDO::PARAM_INT);
    $stmt->bindValue(count($params) + 2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $links = $stmt->fetchAll();
    if (isset($_SESSION['feedback_msg'])) {
        $feedback_msg = $_SESSION['feedback_msg'];
        $feedback_type = $_SESSION['feedback_type'];
        unset($_SESSION['feedback_msg'], $_SESSION['feedback_type']);
    }
} catch (PDOException $e) {
    error_log('友链管理数据库错误: ' . $e->getMessage());
    $feedback_msg = '数据库操作失败，请稍后重试';
    $feedback_type = 'error';
    $links = [];
}

function getStatusBadge($status) {
    switch ($status) {
        case 'pending': return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800"><i class="fa fa-clock-o mr-1"></i> 待审核</span>';
        case 'approved': return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800"><i class="fa fa-check mr-1"></i> 已通过</span>';
        case 'rejected': return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800"><i class="fa fa-times mr-1"></i> 已拒绝</span>';
        default: return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800"><i class="fa fa-question mr-1"></i> 未知</span>';
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>友链管理 - huliapi</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css" rel="stylesheet">
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: '#409EFF',
            success: '#67C23A',
            warning: '#E6A23C',
            danger: '#F56C6C',
            info: '#909399',
            light: '#F5F7FA',
            dark: '#303133',
          },
          fontFamily: {
            inter: ['Inter', 'system-ui', 'sans-serif'],
          },
        },
      }
    }
  </script>
  <style type="text/tailwindcss">
    @layer utilities {
      .content-auto {
        content-visibility: auto;
      }
      .card-shadow {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
      }
      .card-hover {
        transition: all 0.3s ease;
      }
      .card-hover:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
      }
      .btn-effect {
        transition: all 0.2s ease;
      }
      .btn-effect:hover {
        transform: translateY(-2px);
      }
      .btn-effect:active {
        transform: translateY(0);
      }
      .badge-pulse {
        animation: pulse 2s infinite;
      }
      .fade-in {
        animation: fadeIn 0.3s ease-in-out;
      }
      .slide-up {
        animation: slideUp 0.3s ease-out;
      }
      .scale-in {
        animation: scaleIn 0.2s ease-out;
      }
      @keyframes pulse {
        0% { box-shadow: 0 0 0 0 rgba(64, 158, 255, 0.4); }
        70% { box-shadow: 0 0 0 10px rgba(64, 158, 255, 0); }
        100% { box-shadow: 0 0 0 0 rgba(64, 158, 255, 0); }
      }
      @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
      }
      @keyframes slideUp {
        from { transform: translateY(20px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
      }
      @keyframes scaleIn {
        from { transform: scale(0.95); opacity: 0; }
        to { transform: scale(1); opacity: 1; }
      }
    }
  </style>
</head>
<body class="bg-gray-50 font-inter text-dark">
  <div class="container mx-auto px-4 py-6">
    <div class="mb-6 fade-in">
      <div>
        <h1 class="text-[clamp(1.5rem,3vw,2rem)] font-bold text-gray-800">
          <i class="fa fa-link text-primary mr-2"></i>友链管理
        </h1>
        <p class="text-gray-500 mt-1">管理和审核网站友情链接</p>
      </div>
    </div>
    <?php if ($feedback_msg): ?>
    <div class="mb-6 fade-in" id="feedback-alert">
      <div class="p-4 rounded-lg bg-<?= $feedback_type === 'success' ? 'green-50' : 'red-50' ?> border-l-4 border-<?= $feedback_type === 'success' ? 'green-400' : 'red-400' ?> shadow-sm">
        <div class="flex">
          <div class="flex-shrink-0">
            <i class="fa fa-<?= $feedback_type === 'success' ? 'check-circle text-green-500' : 'exclamation-circle text-red-500' ?>"></i>
          </div>
          <div class="ml-3">
            <p class="text-sm font-medium text-<?= $feedback_type === 'success' ? 'green-800' : 'red-800' ?>">
              <?= htmlspecialchars($feedback_msg) ?>
            </p>
          </div>
          <div class="ml-auto pl-3">
            <div class="flex -mx-1.5 -my-1.5">
              <button type="button" class="inline-flex p-1.5 rounded-md text-<?= $feedback_type === 'success' ? 'green-500' : 'red-500' ?> hover:bg-<?= $feedback_type === 'success' ? 'green-100' : 'red-100' ?> focus:outline-none" onclick="this.parentElement.parentElement.parentElement.parentElement.style.display='none'">
                <i class="fa fa-times"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>
    <div class="bg-white rounded-xl shadow-sm p-6 mb-6 card-shadow fade-in" style="animation-delay: 0.1s">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-gray-800">
          <i class="fa fa-filter text-primary mr-2"></i>搜索筛选
        </h2>
      </div>
      <form class="space-y-4" method="get">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
              <i class="fa fa-globe mr-1"></i>网站名称
            </label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <i class="fa fa-search text-gray-400"></i>
              </div>
              <input type="text" name="name" id="name" value="<?= htmlspecialchars($get_name ?? '') ?>"
                     placeholder="请输入网站名称" class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all duration-200" />
            </div>
          </div>
          <div>
            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">
              <i class="fa fa-check-square-o mr-1"></i>审核状态
            </label>
            <div class="relative">
              <select name="status" id="status" class="block w-full pl-3 pr-10 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary/50 focus:border-primary appearance-none transition-all duration-200">
                <option value="">全部</option>
                <option value="pending" <?= isset($get_status) && $get_status === 'pending' ? 'selected' : '' ?>>待审核</option>
                <option value="approved" <?= isset($get_status) && $get_status === 'approved' ? 'selected' : '' ?>>已通过</option>
                <option value="rejected" <?= isset($get_status) && $get_status === 'rejected' ? 'selected' : '' ?>>已拒绝</option>
              </select>
              <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-400">
                <i class="fa fa-chevron-down text-xs"></i>
              </div>
            </div>
          </div>
          <div>
            <label for="is_hidden" class="block text-sm font-medium text-gray-700 mb-1">
              <i class="fa fa-eye mr-1"></i>显示状态
            </label>
            <div class="relative">
              <select name="is_hidden" id="is_hidden" class="block w-full pl-3 pr-10 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary/50 focus:border-primary appearance-none transition-all duration-200">
                <option value="">全部</option>
                <option value="0" <?= isset($get_is_hidden) && $get_is_hidden === '0' ? 'selected' : '' ?>>显示</option>
                <option value="1" <?= isset($get_is_hidden) && $get_is_hidden === '1' ? 'selected' : '' ?>>隐藏</option>
              </select>
              <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-400">
                <i class="fa fa-chevron-down text-xs"></i>
              </div>
            </div>
          </div>
        </div>
        <div class="flex flex-wrap gap-3 pt-2">
          <button type="submit" class="btn-effect inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg shadow-sm hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
            <i class="fa fa-search mr-2"></i>搜索
          </button>
          <a href="friend_links.php" class="btn-effect inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 rounded-lg shadow-sm hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
            <i class="fa fa-refresh mr-2"></i>重置
          </a>
        </div>
      </form>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 mb-6 card-shadow fade-in" style="animation-delay: 0.2s">
      <div class="flex flex-wrap gap-2 justify-between">
        <div class="flex flex-wrap gap-2">
          <button type="button" id="batch-check-all" class="btn-effect inline-flex items-center px-3 py-1.5 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 focus:outline-none">
            <i class="fa fa-check-square-o mr-1"></i>全选
          </button>
          <button type="button" id="batch-uncheck-all" class="btn-effect inline-flex items-center px-3 py-1.5 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 focus:outline-none">
            <i class="fa fa-square-o mr-1"></i>取消全选
          </button>
        </div>
        <div class="flex flex-wrap gap-2 mt-2 sm:mt-0">
          <button type="button" class="batch-action btn-effect inline-flex items-center px-3 py-1.5 text-sm bg-green-100 text-green-700 rounded-lg hover:bg-green-200 focus:outline-none" data-action="approve">
            <i class="fa fa-check mr-1"></i>批量通过
          </button>
          <button type="button" class="batch-action btn-effect inline-flex items-center px-3 py-1.5 text-sm bg-amber-100 text-amber-700 rounded-lg hover:bg-amber-200 focus:outline-none" data-action="reject">
            <i class="fa fa-times mr-1"></i>批量拒绝
          </button>
          <button type="button" class="batch-action btn-effect inline-flex items-center px-3 py-1.5 text-sm bg-red-100 text-red-700 rounded-lg hover:bg-red-200 focus:outline-none" data-action="delete">
            <i class="fa fa-trash mr-1"></i>批量删除
          </button>
        </div>
        <div class="flex flex-wrap gap-2 mt-2 sm:mt-0">
          <button type="button" class="batch-action btn-effect inline-flex items-center px-3 py-1.5 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 focus:outline-none" data-action="toggle">
            <i class="fa fa-eye mr-1"></i>批量切换显示
          </button>
        </div>
        <div class="mt-2 sm:mt-0 flex items-center">
          <span class="text-sm text-gray-500">
            <i class="fa fa-database mr-1"></i>共 <span class="font-medium text-primary"><?= $totalRecords ?></span> 条数据
          </span>
        </div>
      </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-6 mb-6 card-shadow fade-in" style="animation-delay: 0.3s">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-gray-800">
          <i class="fa fa-list-alt text-primary mr-2"></i>友链列表
        </h2>
      </div>
      <div id="skeleton-loader" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 hidden"></div>
      <div id="content-container" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php if (empty($links)): ?>
          <div class="col-span-full bg-gray-50 rounded-lg p-8 text-center slide-up">
            <div class="mx-auto flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
              <i class="fa fa-link text-gray-400 text-2xl"></i>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-1">暂无友链数据</h3>
            <p class="text-gray-500">添加您的第一个友链或调整筛选条件以显示更多结果</p>
            <div class="mt-4">
              <a href="friend_link_add.php" class="btn-effect inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                <i class="fa fa-plus mr-1"></i>添加友链
              </a>
            </div>
          </div>
        <?php else: ?>
          <?php foreach ($links as $index => $link): ?>
            <div class="card-hover bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm slide-up" style="animation-delay: <?= ($index % 6) * 0.05 ?>s">
              <div class="p-4 border-b border-gray-100">
                <div class="flex items-start">
                  <div class="flex-shrink-0 mr-3 mt-0.5">
                    <input type="checkbox" class="ids h-4 w-4 text-primary rounded border-gray-300 focus:ring-primary" name="ids[]"
                           value="<?= $link['id'] ?>" id="ids-<?= $link['id'] ?>">
                  </div>
                  <div class="flex-shrink-0">
                    <div class="h-12 w-12 rounded-lg overflow-hidden bg-gray-100 flex items-center justify-center">
                      <?php if (!empty($link['logo'])): ?>
                        <img src="<?= htmlspecialchars($link['logo']) ?>"
                             alt="<?= htmlspecialchars($link['site_name']) ?>"
                             class="h-full w-full object-cover"
                             onError="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTYiIGhlaWdodD0iMTYiIHZpZXdCb3g9IjAgMCAxNiAxNiIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjE2IiBoZWlnaHQ9IjE2IiBmaWxsPSIjRjBGMEYwIi8+CjxwYXRoIGQ9Ik04IDNDNi4zNDM3NSAzIDUgNC4zNDM3NSA1IDZDNSA3LjY1NjI1IDYuMzQzNzUgOSA4IDlDOS42NTYyNSA5IDExIDcuNjU2MjUgMTEgNkMxMSA0LjM0Mzc1IDkuNjU2MjUgMyA4IDNaTTMgMTNIMTRWOUg4LjVMMTAgNy41SDUuNUw3IDlIN0wzIDEzWiIgZmlsbD0iIzlFOUY5RSIvPgo8L3N2Zz4='">
                      <?php else: ?>
                        <svg class="h-8 w-8 text-gray-400" fill="currentColor" viewBox="0 0 24 24">
                          <path d="M20 21V19C20 17.9391 19.5786 16.9217 18.8284 16.1716C18.0783 15.4214 17.0609 15 16 15H8C6.93913 15 5.92172 15.4214 5.17157 16.1716C4.42143 16.9217 4 17.9391 4 19V21"></path>
                          <circle cx="8" cy="8" r="4"></circle>
                          <circle cx="16" cy="8" r="4"></circle>
                        </svg>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div class="ml-3 flex-1">
                    <h3 class="text-sm font-semibold text-gray-900">
                      <a href="<?= htmlspecialchars($link['url']) ?>" target="_blank" class="hover:text-primary transition-colors">
                        <?= htmlspecialchars($link['site_name']) ?>
                        <i class="fa fa-external-link text-xs ml-1 text-gray-400"></i>
                      </a>
                    </h3>
                    <p class="text-xs text-gray-500 mt-0.5">
                      <i class="fa fa-globe mr-1"></i>
                      <?= mb_strlen($link['url']) > 30 ? mb_substr($link['url'], 0, 30) . '...' : $link['url'] ?>
                    </p>
                  </div>
                </div>
              </div>
              <div class="p-4">
                <div class="space-y-2">
                  <div class="flex items-center text-sm">
                    <span class="text-gray-500 w-20">
                      <i class="fa fa-user mr-1"></i>申请用户:
                    </span>
                    <span class="text-gray-900"><?= $link['user_id'] ? "用户ID:{$link['user_id']}" : '游客' ?></span>
                  </div>
                  <div class="flex items-center text-sm">
                    <span class="text-gray-500 w-20">
                      <i class="fa fa-sort-numeric-asc mr-1"></i>排序值:
                    </span>
                    <span class="text-gray-900"><?= $link['sort_order'] ?? 0 ?></span>
                  </div>
                  <div class="flex items-center text-sm">
                    <span class="text-gray-500 w-20">
                      <i class="fa fa-calendar mr-1"></i>申请时间:
                    </span>
                    <span class="text-gray-900"><?= $link['created_at'] ?></span>
                  </div>
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                  <?= getStatusBadge($link['status']) ?>
                  <?php if ($link['is_hidden']): ?>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                      <i class="fa fa-eye-slash mr-1"></i>隐藏
                    </span>
                  <?php endif; ?>
                </div>
                <?php if ($link['status'] === 'rejected' && !empty($link['review_note'])): ?>
                  <div class="mt-3 p-2 bg-red-50 rounded text-sm text-red-700">
                    <i class="fa fa-info-circle mr-1"></i>
                    <strong>拒绝原因:</strong> <?= htmlspecialchars($link['review_note']) ?>
                  </div>
                <?php endif; ?>
              </div>
              <div class="p-3 bg-gray-50 border-t border-gray-100 flex justify-between items-center">
                <div class="flex space-x-2">
                  <a href="friend_link_edit.php?id=<?= $link['id'] ?>" class="btn-effect inline-flex items-center px-3 py-1.5 text-xs bg-gray-100 text-gray-700 rounded hover:bg-gray-200 focus:outline-none">
                    <i class="fa fa-pencil mr-1"></i>编辑
                  </a>
                  <?php if ($link['status'] === 'pending'): ?>
                    <a href="friend_links.php?action=approve&id=<?= $link['id'] ?><?= isset($get_page) ? "&page={$get_page}" : '' ?>"
                       class="btn-effect inline-flex items-center px-3 py-1.5 text-xs bg-green-100 text-green-700 rounded hover:bg-green-200 focus:outline-none"
                       onclick="return confirm('确定通过此友链？')">
                      <i class="fa fa-check mr-1"></i>通过
                    </a>
                    <button type="button" class="btn-effect inline-flex items-center px-3 py-1.5 text-xs bg-red-100 text-red-700 rounded hover:bg-red-200 focus:outline-none reject-btn"
                            data-id="<?= $link['id'] ?>">
                      <i class="fa fa-times mr-1"></i>拒绝
                    </button>
                  <?php else: ?>
                    <a href="friend_links.php?action=toggle&id=<?= $link['id'] ?><?= isset($get_page) ? "&page={$get_page}" : '' ?>"
                       class="btn-effect inline-flex items-center px-3 py-1.5 text-xs bg-gray-100 text-gray-700 rounded hover:bg-gray-200 focus:outline-none"
                       title="<?= $link['is_hidden'] ? '显示' : '隐藏' ?>">
                      <i class="fa fa-<?= $link['is_hidden'] ? 'eye' : 'eye-slash' ?> mr-1"></i><?= $link['is_hidden'] ? '显示' : '隐藏' ?>
                    </a>
                  <?php endif; ?>
                </div>
                <a href="friend_links.php?action=delete&id=<?= $link['id'] ?><?= isset($get_page) ? "&page={$get_page}" : '' ?>"
                   class="btn-effect inline-flex items-center px-3 py-1.5 text-xs bg-red-100 text-red-700 rounded hover:bg-red-200 focus:outline-none"
                   onclick="return confirm('确定删除此友链？删除后不可恢复！')">
                  <i class="fa fa-trash mr-1"></i>删除
                </a>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="bg-white rounded-xl shadow-sm p-4 card-shadow fade-in" style="animation-delay: 0.4s">
      <div class="flex flex-col sm:flex-row justify-between items-center">
        <div class="mb-4 sm:mb-0 text-sm text-gray-500">
          显示第 <span class="font-medium text-primary"><?= ($page - 1) * $limit + 1 ?></span> 至
          <span class="font-medium text-primary"><?= min($page * $limit, $totalRecords) ?></span> 条，
          共 <span class="font-medium text-primary"><?= $totalRecords ?></span> 条数据
        </div>
        <div class="flex items-center space-x-1">
          <?php if ($page > 1): ?>
            <a href="?page=1<?= !empty($get_name) ? "&name=" . urlencode($get_name) : '' ?><?= !empty($get_status) ? "&status={$get_status}" : '' ?><?= isset($get_is_hidden) ? "&is_hidden={$get_is_hidden}" : '' ?>"
               class="btn-effect inline-flex items-center justify-center w-8 h-8 rounded-md border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
              <i class="fa fa-angle-double-left"></i>
            </a>
          <?php else: ?>
            <span class="inline-flex items-center justify-center w-8 h-8 rounded-md border border-gray-300 bg-gray-50 text-sm font-medium text-gray-400">
              <i class="fa fa-angle-double-left"></i>
            </span>
          <?php endif; ?>
          <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?><?= !empty($get_name) ? "&name=" . urlencode($get_name) : '' ?><?= !empty($get_status) ? "&status={$get_status}" : '' ?><?= isset($get_is_hidden) ? "&is_hidden={$get_is_hidden}" : '' ?>"
               class="btn-effect inline-flex items-center justify-center w-8 h-8 rounded-md border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
              <i class="fa fa-angle-left"></i>
            </a>
          <?php else: ?>
            <span class="inline-flex items-center justify-center w-8 h-8 rounded-md border border-gray-300 bg-gray-50 text-sm font-medium text-gray-400">
              <i class="fa fa-angle-left"></i>
            </span>
          <?php endif; ?>
          <?php
          $startPage = max(1, $page - 2);
          $endPage = min($totalPages, $page + 2);
          if ($endPage - $startPage < 4) {
              if ($startPage == 1) {
                  $endPage = min(5, $totalPages);
              } elseif ($endPage == $totalPages) {
                  $startPage = max(1, $totalPages - 4);
              }
          }
          $showStartEllipsis = $startPage > 1;
          $showEndEllipsis = $endPage < $totalPages;
          if ($showStartEllipsis) {
              echo '<a href="?page=1' . (!empty($get_name) ? "&name=" . urlencode($get_name) : '') . (!empty($get_status) ? "&status={$get_status}" : '') . (isset($get_is_hidden) ? "&is_hidden={$get_is_hidden}" : '') . '" class="btn-effect inline-flex items-center justify-center w-8 h-8 rounded-md border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">1</a>';
              if ($startPage > 2) {
                  echo '<span class="inline-flex items-center justify-center w-8 h-8 text-gray-400">...</span>';
              }
          }
          for ($i = $startPage; $i <= $endPage; $i++): ?>
            <a href="?page=<?= $i ?><?= !empty($get_name) ? "&name=" . urlencode($get_name) : '' ?><?= !empty($get_status) ? "&status={$get_status}" : '' ?><?= isset($get_is_hidden) ? "&is_hidden={$get_is_hidden}" : '' ?>"
               class="btn-effect inline-flex items-center justify-center w-8 h-8 rounded-md border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 <?= $page == $i ? 'bg-primary text-white border-primary' : '' ?>">
              <?= $i ?>
            </a>
          <?php endfor; ?>
          <?php if ($showEndEllipsis): ?>
              <?php if ($endPage < $totalPages - 1): ?>
                  <span class="inline-flex items-center justify-center w-8 h-8 text-gray-400">...</span>
              <?php endif; ?>
              <a href="?page=<?= $totalPages ?><?= !empty($get_name) ? "&name=" . urlencode($get_name) : '' ?><?= !empty($get_status) ? "&status={$get_status}" : '' ?><?= isset($get_is_hidden) ? "&is_hidden={$get_is_hidden}" : '' ?>"
                 class="btn-effect inline-flex items-center justify-center w-8 h-8 rounded-md border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                <?= $totalPages ?>
              </a>
          <?php endif; ?>
          <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $page + 1 ?><?= !empty($get_name) ? "&name=" . urlencode($get_name) : '' ?><?= !empty($get_status) ? "&status={$get_status}" : '' ?><?= isset($get_is_hidden) ? "&is_hidden={$get_is_hidden}" : '' ?>"
               class="btn-effect inline-flex items-center justify-center w-8 h-8 rounded-md border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
              <i class="fa fa-angle-right"></i>
            </a>
          <?php else: ?>
            <span class="inline-flex items-center justify-center w-8 h-8 rounded-md border border-gray-300 bg-gray-50 text-sm font-medium text-gray-400">
              <i class="fa fa-angle-right"></i>
            </span>
          <?php endif; ?>
          <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $totalPages ?><?= !empty($get_name) ? "&name=" . urlencode($get_name) : '' ?><?= !empty($get_status) ? "&status={$get_status}" : '' ?><?= isset($get_is_hidden) ? "&is_hidden={$get_is_hidden}" : '' ?>"
               class="btn-effect inline-flex items-center justify-center w-8 h-8 rounded-md border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
              <i class="fa fa-angle-double-right"></i>
            </a>
          <?php else: ?>
            <span class="inline-flex items-center justify-center w-8 h-8 rounded-md border border-gray-300 bg-gray-50 text-sm font-medium text-gray-400">
              <i class="fa fa-angle-double-right"></i>
            </span>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>
  <div id="rejectModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 opacity-0 pointer-events-none transition-opacity duration-300">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 transform transition-all duration-300 scale-95 translate-y-4">
      <div class="flex items-center justify-between p-4 border-b">
        <h3 class="text-lg font-medium text-gray-900">填写拒绝原因</h3>
        <button type="button" class="text-gray-400 hover:text-gray-500 focus:outline-none close-modal" onclick="hideRejectModal()">
          <i class="fa fa-times"></i>
        </button>
      </div>
      <div class="p-4">
        <form id="reject-form" method="post">
          <input type="hidden" name="id" id="reject-id">
          <div class="mb-4">
            <label for="reject-note" class="block text-sm font-medium text-gray-700 mb-1">
              拒绝原因（选填）
            </label>
            <textarea id="reject-note" name="reject_note" rows="3" class="block w-full border border-gray-300 rounded-md shadow-sm focus:ring-primary focus:border-primary" placeholder="请输入拒绝原因，将显示给申请者"></textarea>
          </div>
        </form>
      </div>
      <div class="p-4 border-t flex justify-end space-x-3">
        <button type="button" class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary close-modal" onclick="hideRejectModal()">
          取消
        </button>
        <button type="button" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary" onclick="submitReject()">
          确认拒绝
        </button>
      </div>
    </div>
  </div>
  <div id="loadingModal" class="fixed inset-0 bg-black bg-opacity-30 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg p-6 max-w-xs w-full text-center scale-in">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4"></div>
      <h3 class="text-lg font-medium text-gray-900 mb-2">处理中</h3>
      <p class="text-gray-500 text-sm">请稍候，正在执行操作...</p>
    </div>
  </div>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const feedbackAlert = document.getElementById('feedback-alert');
      if (feedbackAlert) {
        setTimeout(() => {
          feedbackAlert.style.opacity = '0';
          feedbackAlert.style.transition = 'opacity 0.5s ease';
          setTimeout(() => {
            feedbackAlert.style.display = 'none';
          }, 500);
        }, 5000);
      }
    });
    document.getElementById('batch-check-all').addEventListener('click', function() {
      document.querySelectorAll('.ids').forEach(function(checkbox) {
        checkbox.checked = true;
      });
    });
    document.getElementById('batch-uncheck-all').addEventListener('click', function() {
      document.querySelectorAll('.ids').forEach(function(checkbox) {
        checkbox.checked = false;
      });
    });
    document.querySelectorAll('.batch-action').forEach(function(button) {
      button.addEventListener('click', function() {
        var action = this.getAttribute('data-action');
        var checkedIds = Array.from(document.querySelectorAll('.ids:checked')).map(cb => cb.value);
        if (checkedIds.length === 0) {
          showNotification('请至少选择一条友链', 'warning');
          return;
        }
        var confirmMsg = {
          'approve': '确定通过选中的' + checkedIds.length + '条友链？',
          'delete': '确定删除选中的' + checkedIds.length + '条友链？此操作不可恢复！',
          'toggle': '确定切换选中的' + checkedIds.length + '条友链的显示状态？',
          'reject': '确定拒绝选中的' + checkedIds.length + '条友链？'
        }[action] || '确定执行此操作？';
        if (confirm(confirmMsg)) {
          if (action === 'reject') {
            var note = prompt('请输入拒绝原因（选填）');
            if (note === null) return;
            submitBatchAction(action, checkedIds, note);
          } else {
            submitBatchAction(action, checkedIds);
          }
        }
      });
    });
    document.querySelectorAll('.reject-btn').forEach(function(button) {
      button.addEventListener('click', function() {
        var id = this.getAttribute('data-id');
        document.getElementById('reject-id').value = id;
        document.getElementById('reject-note').value = '';
        showRejectModal();
      });
    });

    function showRejectModal() {
      const modal = document.getElementById('rejectModal');
      modal.classList.remove('opacity-0', 'pointer-events-none');
      modal.querySelector('div').classList.remove('scale-95', 'translate-y-4');
      modal.querySelector('div').classList.add('scale-100', 'translate-y-0');
      document.getElementById('reject-note').focus();
    }

    function hideRejectModal() {
      const modal = document.getElementById('rejectModal');
      modal.classList.add('opacity-0', 'pointer-events-none');
      modal.querySelector('div').classList.remove('scale-100', 'translate-y-0');
      modal.querySelector('div').classList.add('scale-95', 'translate-y-4');
    }
    document.addEventListener('click', function(e) {
      if (e.target.classList.contains('close-modal')) {
        hideRejectModal();
      }
    });
    document.getElementById('rejectModal').addEventListener('click', function(e) {
      if (e.target === this) {
        hideRejectModal();
      }
    });

    function submitReject() {
      var id = document.getElementById('reject-id').value;
      var note = document.getElementById('reject-note').value;
      var page = <?= isset($get_page) ? $get_page : 1 ?>;
      if (!id) return;
      showLoading();
      var formData = new FormData();
      formData.append('reject_note', note);
      fetch(`friend_links.php?action=reject&id=${id}&page=${page}`, {
        method: 'POST',
        body: formData
      })
      .then(response => {
        if (response.ok) {
          hideRejectModal();
          setTimeout(() => {
            location.reload();
          }, 600);
        } else {
          throw new Error('提交失败');
        }
      })
      .catch(error => {
        hideLoading();
        showNotification('操作失败: ' + error.message, 'error');
      });
    }

    function submitBatchAction(action, ids, note) {
      showLoading();
      var formData = new FormData();
      ids.forEach(id => formData.append('ids[]', id));
      formData.append('batch_action', action);
      if (action === 'reject' && note) {
        formData.append('reject_note', note);
      }
      fetch('friend_links.php', {
        method: 'POST',
        body: formData
      })
      .then(response => {
        if (response.ok) {
          setTimeout(() => {
            location.reload();
          }, 600);
        } else {
          throw new Error('提交失败');
        }
      })
      .catch(error => {
        hideLoading();
        showNotification('操作失败: ' + error.message, 'error');
      });
    }

    function showLoading() {
      document.getElementById('loadingModal').classList.remove('hidden');
    }

    function hideLoading() {
      document.getElementById('loadingModal').classList.add('hidden');
    }

    function showNotification(message, type = 'info') {
      const notification = document.createElement('div');
      notification.className = `fixed bottom-4 right-4 px-4 py-3 rounded-lg shadow-lg z-50 fade-in ${
        type === 'success' ? 'bg-green-50 text-green-800 border-l-4 border-green-400' :
        type === 'error' ? 'bg-red-50 text-red-800 border-l-4 border-red-400' :
        type === 'warning' ? 'bg-yellow-50 text-yellow-800 border-l-4 border-yellow-400' :
        'bg-blue-50 text-blue-800 border-l-4 border-blue-400'
      }`;
      notification.innerHTML = `
        <div class="flex">
          <div class="flex-shrink-0">
            <i class="fa fa-${
              type === 'success' ? 'check-circle text-green-500' :
              type === 'error' ? 'exclamation-circle text-red-500' :
              type === 'warning' ? 'exclamation-triangle text-yellow-500' :
              'info-circle text-blue-500'
            }"></i>
          </div>
          <div class="ml-3">
            <p class="text-sm font-medium">${message}</p>
          </div>
        </div>
      `;
      document.body.appendChild(notification);
      setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transition = 'opacity 0.5s ease';
        setTimeout(() => {
          notification.remove();
        }, 500);
      }, 3000);
    }
    document.querySelectorAll('button, a').forEach(el => {
      if (el.classList.contains('btn-effect')) return;
      el.classList.add('transition-all', 'duration-200');
      el.addEventListener('mouseenter', function() {
        this.classList.add('scale-105');
      });
      el.addEventListener('mouseleave', function() {
        this.classList.remove('scale-105');
      });
    });
  </script>
</body>
</html>
