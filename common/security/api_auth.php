<?php
@error_reporting(0);
@ini_set('display_errors', 'Off');
ob_start();

require_once __DIR__ . '/../redis_client.php';
require_once __DIR__ . '/../client_ip.php';
require_once __DIR__ . '/../schema_bootstrap.php';

function getUserIP() {
    return huli_client_ip();
}

function checkApiRateLimit($pdo, $settings, $scope, $identifier, $limit, $window) {
    if ($limit <= 0 || $window <= 0) return false;
    $mode = strtolower(trim($settings['qps_mode'] ?? 'database'));
    if ($mode === 'redis') {
        try {
            $settings['redis_timeout'] = 0.2;
            $key = 'huliapi:qps:' . $scope . ':' . hash('sha256', (string)$identifier);
            $count = huli_redis_raw_incr($settings, $key, $window);
            return $count > $limit;
        } catch (Throwable $e) {
            error_log('Redis限速不可用，已回退数据库限速: ' . $e->getMessage());
        }
    }
    $window_start = (int)(time() / $window) * $window;
    try {
        $stmt = $pdo->prepare("INSERT INTO huli_rate_limits (scope, identifier, window_start, request_count) VALUES (?, ?, ?, LAST_INSERT_ID(1)) ON DUPLICATE KEY UPDATE request_count = LAST_INSERT_ID(request_count + 1)");
        $stmt->execute([$scope, $identifier, $window_start]);
        $count = (int)$pdo->query("SELECT LAST_INSERT_ID()")->fetchColumn();
        if ($count % 64 === 0) {
            $pdo->prepare("DELETE FROM huli_rate_limits WHERE window_start < ?")->execute([time() - 86400]);
        }
        return $count > $limit;
    } catch (Throwable $e) {
        error_log('huliapi 限速计数异常: ' . $e->getMessage());
        return true;
    }
}

function sendDailyPointsNotification($pdo, $settings, $email, $points) {
    if (empty($email) || empty($settings['mail_smtp_host']) || empty($settings['mail_smtp_user']) || empty($settings['mail_smtp_pass'])) return false;
    require_once __DIR__ . '/../mail.php';
    $site_name = htmlspecialchars($settings['site_name'] ?? 'huliapi', ENT_QUOTES, 'UTF-8');
    $body = '<div style="font-family:Arial,sans-serif;line-height:1.8">'
        . '<h2>' . $site_name . ' 每日点数到账通知</h2>'
        . '<p>您的账户今日已自动赠送 <strong>' . (int)$points . '</strong> 点数。</p>'
        . '<p>本邮件由系统自动发送，请勿直接回复。</p></div>';
    return send_mail($email, '【' . $site_name . '】每日点数已到账', $body, $pdo);
}

function api_error_exit($code, $message) {
    global $response_processed;
    $response_processed = true;
    ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['code' => $code, 'msg' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 将阻塞型任务（例如 SMTP 发信）推迟到响应发送之后再执行，
 * 避免拖慢接口本身的响应时间。
 */
if (!function_exists('huli_defer')) {
    function huli_defer($job) {
        if (is_callable($job)) {
            $GLOBALS['huli_deferred_jobs'][] = $job;
        }
    }
}
$GLOBALS['huli_deferred_jobs'] = [];

$valid_apikey_provided = false;
$response_processed = false;
register_shutdown_function(function() use (&$response_processed) {
    if (!$response_processed) {
        $content = ob_get_clean();
        if ($content !== false && $content !== '') {
            $content_type = null;
            foreach (headers_list() as $header) {
                if (stripos($header, 'Content-Type:') === 0) {
                    $content_type = trim(substr($header, strlen('Content-Type:')));
                    break;
                }
            }
            if (!$content_type) {
                $first_char = substr(ltrim($content), 0, 1);
                $content_type = ($first_char === '{' || $first_char === '[') ? 'application/json' : 'text/plain';
            }
            if (strpos($content_type, 'application/json') !== false) {
                echo $content;
            } else {
                echo $content . "\nTips：huliapi技术支持";
            }
        }
    }
    if (function_exists('fastcgi_finish_request')) {
        @fastcgi_finish_request();
    } elseif (function_exists('ob_end_flush')) {
        while (ob_get_level() > 0) {
            @ob_end_flush();
        }
        @flush();
    }
    foreach (($GLOBALS['huli_deferred_jobs'] ?? []) as $job) {
        try {
            $job();
        } catch (Throwable $e) {
            error_log('[api_auth] 延迟任务执行失败: ' . $e->getMessage());
        }
    }
});

function find_config_file() {
    $possible_paths = [
        __DIR__ . '/../../config.php',
        __DIR__ . '/../../../config.php',
        __DIR__ . '/../../../../config.php',
        $_SERVER['DOCUMENT_ROOT'] . '/config.php'
    ];
    foreach ($possible_paths as $path) {
        if (file_exists($path)) return $path;
    }
    return false;
}

$config_path = find_config_file();
if (!$config_path) api_error_exit(500, '内部服务器错误: 配置文件丢失');
require_once $config_path;
$api_root_relative = '/API/';
$api_root_absolute = realpath($_SERVER['DOCUMENT_ROOT'] . $api_root_relative);
if (!$api_root_absolute || !is_dir($api_root_absolute)) {
    api_error_exit(500, '内部服务器错误: API根目录不存在');
}
$current_script = realpath($_SERVER['SCRIPT_FILENAME']);
if (strpos($current_script, $api_root_absolute) !== 0) {
    api_error_exit(500, '内部服务器错误: 当前脚本不在API目录内');
}
$relative_path = substr($current_script, strlen($api_root_absolute));
$relative_path = ltrim($relative_path, '/\\');
$endpoint = preg_replace('/\.php$/i', '', $relative_path);
$encoded_endpoint = implode('/', array_map('rawurlencode', explode('/', $endpoint)));
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";port=" . (defined('DB_PORT') ? DB_PORT : 3306) . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]);

    huli_schema_bootstrap($pdo);
    $settings = [];
    $stmt_settings = $pdo->query("SELECT setting_key, setting_value FROM huli_settings");
    while ($row = $stmt_settings->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $enable_free_qps_limit   = (int)($settings['enable_free_qps_limit'] ?? 1);
    $free_qps_seconds        = (int)($settings['free_qps_seconds'] ?? 1);
    $free_qps_limit          = (int)($settings['free_qps_limit'] ?? 10);
    $enable_member_qps_limit = (int)($settings['enable_member_qps_limit'] ?? 1);
    $member_qps_seconds      = (int)($settings['member_qps_seconds'] ?? 1);
    $member_qps_limit        = (int)($settings['member_qps_limit'] ?? 20);
    $daily_free_points       = (int)($settings['daily_free_points'] ?? 100);
    $enable_daily_points     = (int)($settings['enable_daily_points'] ?? 1);
    $warn_points_threshold   = (int)($settings['warn_points_threshold'] ?? 5);
    $warn_balance_threshold  = (float)($settings['warn_balance_threshold'] ?? 0.01);
    $enable_warn_notification= (int)($settings['enable_warn_notification'] ?? 1);
    $enable_daily_points_notification = (int)($settings['enable_daily_points_notification'] ?? 1);
    $api = null;
    $stmt = $pdo->prepare("SELECT id, status, visibility, is_billable, price_per_call, points_per_call FROM huli_apis WHERE endpoint = ? LIMIT 1");
    $stmt->execute([$encoded_endpoint]);
    $api = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$api) {
        $stmt->execute([$endpoint]);
        $api = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if (!$api) api_error_exit(404, '接口不存在');
    switch ($api['status']) {
        case 'normal': break;
        case 'maintenance': api_error_exit(503, '接口正在维护中，请稍后再试');
        case 'error': api_error_exit(500, '接口当前异常，暂无法使用');
        case 'deprecated': api_error_exit(410, '接口已失效');
        default: api_error_exit(404, '接口状态未知');
    }
    $log_api_id = $api['id'];
    $log_user_id = null;
    $log_ip_address = getUserIP();
    $billing_amount = 0;
    $billing_type = 'free';
    $daily_points_granted = false;
    if ($api['is_billable'] == 1) {
        $billing_type = 'balance';
        $billing_amount = $api['price_per_call'];
    } elseif (!empty($api['points_per_call']) && $api['points_per_call'] > 0) {
        $billing_type = 'points';
        $billing_amount = $api['points_per_call'];
    }

    $api_key = $_REQUEST['apikey'] ?? '';
    if (empty($api_key) && defined('DEFAULT_API_KEY')) {
        $api_key = DEFAULT_API_KEY;
    }
    $valid_user = null;
    $is_super_member = false;
    if ($api_key) {
        $stmt_user = $pdo->prepare("SELECT id, username, email, status, balance, points, membership_level, membership_expire FROM huli_users WHERE api_key = ? LIMIT 1");
        $stmt_user->execute([$api_key]);
        $user = $stmt_user->fetch(PDO::FETCH_ASSOC);
        if ($user && $user['status'] === 'active') {
            $valid_apikey_provided = true;
            $valid_user = $user;
            $log_user_id = $user['id'];
            if ($user['membership_level'] === 'super') {
                if (!$user['membership_expire'] || strtotime($user['membership_expire']) > time()) {
                    $is_super_member = true;
                } else {
                    $stmt_update = $pdo->prepare("UPDATE huli_users SET membership_level = 'normal', membership_expire = NULL WHERE id = ?");
                    $stmt_update->execute([$user['id']]);
                }
            }
        }
    }
    if (!$valid_apikey_provided && $enable_free_qps_limit && $free_qps_limit > 0 && $free_qps_seconds > 0) {
        if (checkApiRateLimit($pdo, $settings, 'ip', $log_ip_address, $free_qps_limit, $free_qps_seconds)) {
            api_error_exit(429, "提示：无apikey访问，{$free_qps_seconds}秒内最多{$free_qps_limit}次请求，请稍后再试或获取apikey");
        }
    }
    $is_success = false;
    if ($api['visibility'] === 'public' && !$valid_apikey_provided) {
        $is_success = true;
    }
    elseif ($valid_apikey_provided) {
        if ($is_super_member) {
            $is_success = true;
            if ($billing_type === 'points') {
                $billing_type = 'free';
                $billing_amount = 0;
            }
        }
        else {
            if ($enable_member_qps_limit && $member_qps_limit > 0 && $member_qps_seconds > 0) {
                if (checkApiRateLimit($pdo, $settings, 'user', $user['id'], $member_qps_limit, $member_qps_seconds)) {
                    api_error_exit(429, "提示：普通会员{$member_qps_seconds}秒内最多{$member_qps_limit}次请求，超级会员无限制");
                }
            }
            if ($enable_daily_points && $daily_free_points > 0) {
                $stmt_check = $pdo->prepare("SELECT id FROM huli_daily_points_claim WHERE user_id = ? AND claim_date = CURDATE()");
                $stmt_check->execute([$user['id']]);
                if (!$stmt_check->fetch()) {
                    $pdo->beginTransaction();
                    try {
                        $stmt_add = $pdo->prepare("UPDATE huli_users SET points = points + ? WHERE id = ?");
                        $stmt_add->execute([$daily_free_points, $user['id']]);
                        $stmt_rec = $pdo->prepare("INSERT INTO huli_daily_points_claim (user_id, claim_date, points_granted) VALUES (?, CURDATE(), ?)");
                        $stmt_rec->execute([$user['id'], $daily_free_points]);
                        $pdo->commit();
                        $user['points'] += $daily_free_points;
                        $daily_points_granted = true;
                        if ($enable_daily_points_notification) {
                            $daily_email = $user['email'] ?? '';
                            huli_defer(function() use ($pdo, $settings, $daily_email, $daily_free_points) {
                                sendDailyPointsNotification($pdo, $settings, $daily_email, $daily_free_points);
                            });
                        }
                    } catch (Exception $e) {
                        $pdo->rollBack();
                    }
                }
            }
            if ($billing_type !== 'free' && $enable_warn_notification) {
    $stmt_user_info = $pdo->prepare("SELECT email, last_points_warn_date, last_balance_warn_date FROM huli_users WHERE id = ?");
    $stmt_user_info->execute([$user['id']]);
    $user_info = $stmt_user_info->fetch(PDO::FETCH_ASSOC);
    if ($user_info && $user_info['email']) {
        $today = date('Y-m-d');
        $site_name = $settings['site_name'] ?? 'huliapi';
        $logo_url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/assets/images/logo-sidebar.png';
        $current_year = date('Y');
        $need_send = false;
        $mail_subject = '';
        $mail_body = '';
        if ($billing_type === 'points' && $user['points'] > 0 && $user['points'] <= $warn_points_threshold) {
            if ($user_info['last_points_warn_date'] != $today) {
                $need_send = true;
                $mail_subject = '【' . $site_name . '】点数不足提醒';
                $mail_body = '<!DOCTYPE html>
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
        <h1 style="color: #2066ff; font-size: 24px; margin: 0 0 25px; text-align: center; font-weight: bold;">点数不足提醒</h1>
        <p style="color: #333333; font-size: 15px; line-height: 1.8; margin: 0; font-weight: 600;">尊敬的用户：</p>
        <p style="color: #333333; font-size: 15px; line-height: 1.8; margin: 10px 0; font-weight: 600;">您的API点数余额不足' . $warn_points_threshold . '点，请及时充值，以免影响您的API调用。</p>
        <div style="background: linear-gradient(to right, #f8f9ff, #f0f5ff); border-radius: 12px; padding: 20px; margin: 20px 0; border: 1px solid rgba(32,102,255,0.1);">
            <p style="color: #666666; font-size: 14px; line-height: 1.8; margin: 8px 0;"><span style="display: inline-block; width: 100px;">当前点数：</span> <strong>' . $user['points'] . '</strong></p>
        </div>
        <div style="background-color: #f8f9fa; border-radius: 8px; padding: 15px; margin: 20px 0;">
            <p style="color: #666666; font-size: 13px; line-height: 1.6; margin: 0; font-weight: 600;"><span style="color: #2066ff;">●</span> 请尽快充值，避免服务中断</p>
            <p style="color: #666666; font-size: 13px; line-height: 1.6; margin: 8px 0 0; font-weight: 600;"><span style="color: #2066ff;">●</span> 点击下方按钮前往充值</p>
        </div>
        <div style="text-align: center; margin: 30px 0;">
            <a href="' . ($settings['site_url'] ?? '#') . '/recharge" target="_blank" style="display: inline-block; padding: 12px 35px; background-color: #2066ff; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 15px; font-weight: 500;">立即充值</a>
        </div>
        <p style="color: #666666; font-size: 13px; line-height: 1.6; margin: 20px 0 0; font-weight: 600;">如有任何问题，请联系客服支持。</p>
    </div>
    <div style="padding: 20px 15px; background-color: #f8f9fa; border-radius: 0 0 16px 16px; border-top: 1px solid #eef0f5;">
        <p style="color: #999999; font-size: 13px; text-align: center; margin: 0; line-height: 1.8; font-weight: 500;">本邮件由系统自动发送，请勿直接回复<br />Copyright © 2025-' . $current_year . ' huliapi 版权所有</p>
    </div>
</div>
</body>
</html>';
            }
        }
        if ($billing_type === 'balance' && $user['balance'] > 0 && $user['balance'] <= $warn_balance_threshold) {
            if ($user_info['last_balance_warn_date'] != $today) {
                $need_send = true;
                $mail_subject = '【' . $site_name . '】余额不足提醒';
                $mail_body = '<!DOCTYPE html>
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
        <h1 style="color: #2066ff; font-size: 24px; margin: 0 0 25px; text-align: center; font-weight: bold;">余额不足提醒</h1>
        <p style="color: #333333; font-size: 15px; line-height: 1.8; margin: 0; font-weight: 600;">尊敬的用户：</p>
        <p style="color: #333333; font-size: 15px; line-height: 1.8; margin: 10px 0; font-weight: 600;">您的账户余额不足' . $warn_balance_threshold . '元，请及时充值，以免影响您的API调用。</p>
        <div style="background: linear-gradient(to right, #f8f9ff, #f0f5ff); border-radius: 12px; padding: 20px; margin: 20px 0; border: 1px solid rgba(32,102,255,0.1);">
            <p style="color: #666666; font-size: 14px; line-height: 1.8; margin: 8px 0;"><span style="display: inline-block; width: 100px;">当前余额：</span> <strong>' . $user['balance'] . '元</strong></p>
        </div>
        <div style="background-color: #f8f9fa; border-radius: 8px; padding: 15px; margin: 20px 0;">
            <p style="color: #666666; font-size: 13px; line-height: 1.6; margin: 0; font-weight: 600;"><span style="color: #2066ff;">●</span> 请尽快充值，避免服务中断</p>
            <p style="color: #666666; font-size: 13px; line-height: 1.6; margin: 8px 0 0; font-weight: 600;"><span style="color: #2066ff;">●</span> 点击下方按钮前往充值</p>
        </div>
        <div style="text-align: center; margin: 30px 0;">
            <a href="' . ($settings['site_url'] ?? '#') . '/recharge" target="_blank" style="display: inline-block; padding: 12px 35px; background-color: #2066ff; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 15px; font-weight: 500;">立即充值</a>
        </div>
        <p style="color: #666666; font-size: 13px; line-height: 1.6; margin: 20px 0 0; font-weight: 600;">如有任何问题，请联系客服支持。</p>
    </div>
    <div style="padding: 20px 15px; background-color: #f8f9fa; border-radius: 0 0 16px 16px; border-top: 1px solid #eef0f5;">
        <p style="color: #999999; font-size: 13px; text-align: center; margin: 0; line-height: 1.8; font-weight: 500;">本邮件由系统自动发送，请勿直接回复<br />Copyright © 2025-' . $current_year . ' huliapi 版权所有</p>
    </div>
</div>
</body>
</html>';
            }
        }
        if ($need_send) {
            require_once __DIR__ . '/../common/PHPMailer/src/Exception.php';
            require_once __DIR__ . '/../common/PHPMailer/src/PHPMailer.php';
            require_once __DIR__ . '/../common/PHPMailer/src/SMTP.php';
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = $settings['mail_smtp_host'];
                $mail->SMTPAuth   = true;
                $mail->Username   = $settings['mail_smtp_user'];
                $mail->Password   = $settings['mail_smtp_pass'];
                $mail->SMTPSecure = $settings['mail_smtp_secure'] === 'ssl' ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = intval($settings['mail_smtp_port'] ?? 465);
                $mail->CharSet    = 'UTF-8';
                $mail->setFrom($settings['mail_smtp_user'], $site_name);
                $mail->addAddress($user_info['email']);
                $mail->isHTML(true);
                $mail->Subject = $mail_subject;
                $mail->Body    = $mail_body;
                $warn_col = ($billing_type === 'points') ? 'last_points_warn_date' : 'last_balance_warn_date';
                $warn_user_id = $user['id'];
                huli_defer(function() use ($mail, $pdo, $warn_col, $today, $warn_user_id) {
                    try {
                        $mail->send();
                        $pdo->prepare("UPDATE huli_users SET `$warn_col` = ? WHERE id = ?")->execute([$today, $warn_user_id]);
                    } catch (Exception $e) {
                        error_log('[api_auth] 余额/积分告警邮件发送失败: ' . $e->getMessage());
                    }
                });
            } catch (Exception $e) {
                error_log('[api_auth] 余额/积分告警邮件准备失败: ' . $e->getMessage());
            }
        }
    }
}
            $is_success = true;
        }
    }
    else {
        api_error_exit(403, '此接口需要提供有效的apikey');
    }
    $pdo->beginTransaction();
    try {
        if ($log_user_id && $is_success && !$is_super_member) {
            if ($billing_type === 'balance') {
                $stmt_lock = $pdo->prepare("SELECT balance FROM huli_users WHERE id = ? FOR UPDATE");
                $stmt_lock->execute([$log_user_id]);
                $cur_balance = $stmt_lock->fetchColumn();
                if ($cur_balance < $billing_amount) {
                    $pdo->rollBack();
                    api_error_exit(402, "余额不足，当前余额：{$cur_balance}元");
                }
            } elseif ($billing_type === 'points') {
                $stmt_lock = $pdo->prepare("SELECT points FROM huli_users WHERE id = ? FOR UPDATE");
                $stmt_lock->execute([$log_user_id]);
                $cur_points = $stmt_lock->fetchColumn();
                if ($cur_points < $billing_amount) {
                    $grant_msg = $daily_points_granted ? "（今日已自动赠送{$daily_free_points}点）" : "";
                    $pdo->rollBack();
                    api_error_exit(402, "点数不足，当前点数：{$cur_points}{$grant_msg}");
                }
            }
        }
        $pdo->prepare("UPDATE huli_apis SET total_calls = total_calls + 1 WHERE id = ?")->execute([$log_api_id]);
        if ($log_user_id) {
            $pdo->prepare("UPDATE huli_users SET call_count = call_count + 1 WHERE id = ?")->execute([$log_user_id]);
            if ($is_success && !$is_super_member) {
                if ($billing_type === 'balance') {
                    $pdo->prepare("UPDATE huli_users SET balance = balance - ? WHERE id = ?")->execute([$billing_amount, $log_user_id]);
                } elseif ($billing_type === 'points') {
                    $pdo->prepare("UPDATE huli_users SET points = points - ? WHERE id = ?")->execute([$billing_amount, $log_user_id]);
                }
            }
        }
        $stmt_log = $pdo->prepare("INSERT INTO huli_api_logs (api_id, user_id, ip_address, response_code, is_success, billing_type, billing_amount) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt_log->execute([$log_api_id, $log_user_id, $log_ip_address, 200, $is_success ? 1 : 0, $billing_type, $billing_amount]);
        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('huliapi API计费入账异常: ' . $e->getMessage());
        api_error_exit(500, '内部服务器错误');
    }
} catch (Exception $e) {
    error_log('huliapi API网关异常: ' . $e->getMessage());
    api_error_exit(500, '内部服务器错误');
}
ob_end_flush();
?>
