<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
if (file_exists('../config.php')) { require_once '../config.php'; } else { die("出现错误！配置文件丢失。"); }
$username = htmlspecialchars($_SESSION['admin_username']);
$feedback_msg = ''; $feedback_type = ''; $page_title = '系统设置';
$settings_keys = [
    'site_name', 'site_description', 'copyright_info', 'allow_registration', 'allow_temp_key',
    'temp_key_duration', 'temp_key_limit',
    'mail_smtp_host', 'mail_smtp_port', 'mail_smtp_secure', 'mail_smtp_user', 'mail_smtp_pass',
    'mail_reg_enabled', 'mail_forgot_enabled', 'turnstile_enabled', 'turnstile_site_key', 'turnstile_secret_key', 'qps_mode', 'redis_host', 'redis_port', 'redis_password', 'redis_database', 'enable_free_qps_limit', 'free_qps_seconds', 'free_qps_limit', 'enable_member_qps_limit', 'member_qps_seconds', 'member_qps_limit', 'warn_points_threshold', 'warn_balance_threshold', 'enable_warn_notification', 'enable_daily_points', 'daily_free_points', 'enable_daily_points_notification', 'icp_record_number', 'police_record_number', 'favicon_url'
];
$defaults = [
    'site_name' => 'huliapi', 'site_description' => 'huliapi致力于为用户提供稳定、高效的API接口服务，包含随机一言、工具类API等多种接口', 'copyright_info' => 'Copyright © 2025-2026 huliapi 版权所有',
    'allow_registration' => 1, 'allow_temp_key' => 1, 'temp_key_duration' => 24, 'temp_key_limit' => 100,
    'mail_smtp_host' => '', 'mail_smtp_port' => '465', 'mail_smtp_secure' => 'ssl', 'mail_smtp_user' => '', 'mail_smtp_pass' => '',
    'mail_reg_enabled' => 0, 'mail_forgot_enabled' => 0, 'turnstile_enabled' => 0,
    'turnstile_site_key' => '3x00000000000000000000FF', 'turnstile_secret_key' => '1x0000000000000000000000000000000AA',
    'qps_mode' => 'database', 'redis_host' => '127.0.0.1', 'redis_port' => 6379, 'redis_password' => '', 'redis_database' => 0,
    'enable_free_qps_limit' => 1, 'free_qps_seconds' => 1, 'free_qps_limit' => 10, 'enable_member_qps_limit' => 1, 'member_qps_seconds' => 1, 'member_qps_limit' => 20,
    'warn_points_threshold' => 5, 'warn_balance_threshold' => 0.01, 'enable_warn_notification' => 1, 'enable_daily_points' => 0, 'daily_free_points' => 100, 'enable_daily_points_notification' => 1,
    'icp_record_number' => '', 'police_record_number' => '', 'favicon_url' => ''
];
if (empty($defaults['icp_record_number'])) {
    $icp_provinces = ['京','津','沪','渝','冀','豫','云','辽','黑','湘','皖','鲁','新','苏','浙','赣','鄂','甘','晋','蒙','陕','吉','闽','贵','粤','青','藏','川','宁','琼'];
    $icp_p = $icp_provinces[array_rand($icp_provinces)];
    $icp_y = date('Y');
    $icp_n = mt_rand(1000000, 99999999);
    $defaults['icp_record_number'] = $icp_p . 'ICP备' . $icp_y . str_pad((string)$icp_n, 8, '0', STR_PAD_LEFT) . '号';
}
if (empty($defaults['police_record_number'])) {
    $police_provinces = ['京','津','沪','渝','冀','豫','云','辽','黑','湘','皖','鲁','新','苏','浙','赣','鄂','甘','晋','蒙','陕','吉','闽','贵','粤','青','藏','川','宁','琼'];
    $police_p = $police_provinces[array_rand($police_provinces)];
    $police_a = mt_rand(10, 99);
    $police_b = mt_rand(100000, 999999);
    $defaults['police_record_number'] = $police_p . '公网安备 ' . $police_a . str_pad((string)$police_b, 6, '0', STR_PAD_LEFT) . '号';
}
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $init_sql = "INSERT IGNORE INTO huli_settings (setting_key, setting_value) VALUES ('site_name', 'huliapi'), ('site_description', 'huliapi致力于为用户提供稳定、高效的API接口服务，包含随机一言、工具类API等多种接口'), ('copyright_info', 'Copyright © 2025-2026 huliapi 版权所有'), ('allow_registration', '1'), ('allow_temp_key', '1'), ('temp_key_duration', '24'), ('temp_key_limit', '100'), ('mail_smtp_host', ''), ('mail_smtp_port', '465'), ('mail_smtp_secure', 'ssl'), ('mail_smtp_user', ''), ('mail_smtp_pass', ''), ('mail_reg_enabled', '0'), ('mail_forgot_enabled', '0'), ('turnstile_enabled', '0'), ('turnstile_site_key', '3x00000000000000000000FF'), ('turnstile_secret_key', '1x0000000000000000000000000000000AA'), ('qps_mode', 'database'), ('redis_host', '127.0.0.1'), ('redis_port', '6379'), ('redis_password', ''), ('redis_database', '0'), ('enable_free_qps_limit', '1'), ('free_qps_seconds', '1'), ('free_qps_limit', '10'), ('enable_member_qps_limit', '1'), ('member_qps_seconds', '1'), ('member_qps_limit', '20'), ('warn_points_threshold', '5'), ('warn_balance_threshold', '0.01'), ('enable_warn_notification', '1'), ('enable_daily_points', '0'), ('daily_free_points', '100'), ('enable_daily_points_notification', '1'), ('icp_record_number', ''), ('police_record_number', ''), ('favicon_url', '');";
    $pdo->exec($init_sql);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE huli_settings SET setting_value = ? WHERE setting_key = ?");
        foreach ($settings_keys as $key) {
            if(in_array($key, ['allow_registration', 'allow_temp_key', 'mail_reg_enabled', 'mail_forgot_enabled', 'turnstile_enabled', 'enable_warn_notification', 'enable_daily_points', 'enable_daily_points_notification'])) {
                $value = isset($_POST[$key]) ? '1' : '0';
            } else {
                $value = isset($_POST[$key]) ? trim($_POST[$key]) : '';
            }
            $stmt->execute([$value, $key]);
        }
        $pdo->commit();
        $feedback_msg = '设置已成功保存。';
        $feedback_type = 'success';
    }
    $stmt_get = $pdo->query("SELECT setting_key, setting_value FROM huli_settings");
    $db_settings = $stmt_get->fetchAll(PDO::FETCH_KEY_PAIR);
    $settings = array_merge($defaults, $db_settings);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    $feedback_msg = '操作失败: ' . $e->getMessage(); $feedback_type = 'error';
}
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="zh">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-touch-fullscreen" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="stylesheet" type="text/css" href="../assets/css/materialdesignicons.min.css">
    <link rel="stylesheet" type="text/css" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="../assets/css/style.min.css">
</head>
<body>
<div class="container-fluid">
  <div class="row">
    <div class="col-lg-12">
      <div class="card">
        <header class="card-header"><div class="card-title">系统设置</div></header>
        <div class="card-body">
          <ul class="nav nav-tabs">
            <li class="nav-item">
              <button class="nav-link active" id="basic-config" data-bs-toggle="tab" data-bs-target="#config" type="button">基本设置</button>
            </li>
            <li class="nav-item">
              <button class="nav-link" id="basic-function" data-bs-toggle="tab" data-bs-target="#function" type="button">功能设置</button>
            </li>
            <li class="nav-item">
              <button class="nav-link" id="basic-mail" data-bs-toggle="tab" data-bs-target="#mail" type="button">邮件设置</button>
            </li>
            <li class="nav-item">
              <button class="nav-link" id="basic-turnstile" data-bs-toggle="tab" data-bs-target="#turnstile" type="button">人机验证</button>
            </li>
          </ul>
          <form method="POST" action="settings.php" class="edit-form">
            <div class="tab-content">
              <div class="tab-pane fade show active" id="config" aria-labelledby="basic-config">
                <?php if ($feedback_msg): ?>
                <div class="alert alert-<?php echo $feedback_type === 'success' ? 'success' : 'danger'; ?> mb-3">
                  <?php echo htmlspecialchars($feedback_msg); ?>
                </div>
                <?php endif; ?>
                <div class="mb-3">
                  <label for="site_name" class="form-label">网站标题</label>
                  <input class="form-control" type="text" id="site_name" name="site_name" value="<?php echo htmlspecialchars($settings['site_name']); ?>" placeholder="请输入站点标题">
                </div>
                <div class="mb-3">
                  <label for="site_description" class="form-label">网站描述</label>
                  <textarea class="form-control" id="site_description" rows="5" name="site_description" placeholder="请输入站点描述"><?php echo htmlspecialchars($settings['site_description']); ?></textarea>
                </div>
                <div class="mb-3">
                  <label for="copyright_info" class="form-label">版权信息</label>
                  <input class="form-control" type="text" id="copyright_info" name="copyright_info" value="<?php echo htmlspecialchars($settings['copyright_info']); ?>" placeholder="请输入版权信息">
                </div>
                <div class="mb-3">
                  <label for="icp_record_number" class="form-label">ICP备案号</label>
                  <input class="form-control" type="text" id="icp_record_number" name="icp_record_number" value="<?php echo htmlspecialchars($settings['icp_record_number'] ?? ''); ?>" placeholder="例如：沪ICP备2023019171号-6">
                </div>
                <div class="mb-3">
                  <label for="police_record_number" class="form-label">公安备案号</label>
                  <input class="form-control" type="text" id="police_record_number" name="police_record_number" value="<?php echo htmlspecialchars($settings['police_record_number'] ?? ''); ?>" placeholder="例如：京公网安备 11010102000000号">
                  <small class="form-text text-muted">留空则使用默认随机生成的备案号</small>
                </div>
                <div class="mb-3">
                  <label for="favicon_url" class="form-label">网站图标 (Favicon)</label>
                  <input class="form-control" type="url" id="favicon_url" name="favicon_url" value="<?php echo htmlspecialchars($settings['favicon_url'] ?? ''); ?>" placeholder="https://example.com/favicon.ico">
                  <small class="form-text text-muted">留空则使用浏览器默认图标</small>
                </div>
                <div>
                  <button type="submit" class="btn btn-primary me-1">保存设置</button>
                </div>
              </div>
              <div class="tab-pane fade" id="function" aria-labelledby="basic-function">
                <div class="mb-3">
                  <label class="form-label">允许用户注册</label>
                  <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input" id="allow_registration" name="allow_registration" value="1" <?php echo $settings['allow_registration'] ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="allow_registration"></label>
                  </div>
                  <small class="form-text">关闭后，前台将注册入口禁止访问</small>
                </div>
                <div class="mb-3">
                  <label class="form-label">允许申请临时密钥</label>
                  <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input" id="allow_temp_key" name="allow_temp_key" value="1" <?php echo $settings['allow_temp_key'] ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="allow_temp_key"></label>
                  </div>
                  <small class="form-text">关闭后，前台将隐藏申请临时密钥的入口</small>
                </div>
                <div class="mb-3">
                  <label for="temp_key_duration" class="form-label">临时密钥有效时间 (小时)</label>
                  <input class="form-control" type="number" id="temp_key_duration" name="temp_key_duration" value="<?php echo htmlspecialchars($settings['temp_key_duration']); ?>" placeholder="请输入有效时间">
                </div>
                <div class="mb-3">
    <label class="form-label">启用无会员QPS限制</label>
    <div class="form-check form-switch">
        <input type="checkbox" class="form-check-input" id="enable_free_qps_limit" name="enable_free_qps_limit" value="1" <?php echo $settings['enable_free_qps_limit'] ? 'checked' : ''; ?>>
        <label class="form-check-label" for="enable_free_qps_limit"></label>
    </div>
    <small class="form-text">开启后，系统将对未登录用户实施QPS限制</small>
</div>
                <div class="mb-3">
                  <label for="free_qps_seconds" class="form-label">无会员QPS限制秒数</label>
                  <input class="form-control" type="number" id="free_qps_seconds" name="free_qps_seconds" value="<?php echo htmlspecialchars($settings['free_qps_seconds']); ?>" placeholder="请输入无会员QPS限制秒数">
                  <small class="form-text">未登录用户的QPS限制时间窗口（秒）</small>
                </div>
                <div class="mb-3">
                  <label for="free_qps_limit" class="form-label">无会员QPS限制请求数</label>
                  <input class="form-control" type="number" id="free_qps_limit" name="free_qps_limit" value="<?php echo htmlspecialchars($settings['free_qps_limit']); ?>" placeholder="请输入无会员QPS限制请求数">
                  <small class="form-text">未登录用户在限制时间窗口内的最大请求数</small>
                </div>
                <div class="mb-3">
    <label class="form-label">启用普通会员QPS限制</label>
    <div class="form-check form-switch">
        <input type="checkbox" class="form-check-input" id="enable_member_qps_limit" name="enable_member_qps_limit" value="1" <?php echo $settings['enable_member_qps_limit'] ? 'checked' : ''; ?>>
        <label class="form-check-label" for="enable_member_qps_limit"></label>
    </div>
                 <small class="form-text">开启后，系统将对普通会员实施QPS限制</small>
</div>
                <div class="mb-3">
                  <label for="qps_mode" class="form-label">接口限速模式</label>
                  <select class="form-select" id="qps_mode" name="qps_mode">
                    <option value="database" <?php echo ($settings['qps_mode'] ?? 'database') === 'database' ? 'selected' : ''; ?>>系统自带（数据库）</option>
                    <option value="redis" <?php echo ($settings['qps_mode'] ?? 'database') === 'redis' ? 'selected' : ''; ?>>Redis</option>
                  </select>
                  <small class="form-text">Redis 模式需要 PHP Redis 扩展和可用的 Redis 服务，连接失败时自动回退到数据库模式。</small>
                </div>
                <div class="mb-3">
                  <label for="redis_host" class="form-label">Redis 地址</label>
                  <input class="form-control" type="text" id="redis_host" name="redis_host" value="<?php echo htmlspecialchars($settings['redis_host'] ?? '127.0.0.1'); ?>">
                </div>
                <div class="mb-3">
                  <label for="redis_port" class="form-label">Redis 端口</label>
                  <input class="form-control" type="number" id="redis_port" name="redis_port" value="<?php echo htmlspecialchars($settings['redis_port'] ?? 6379); ?>">
                </div>
                <div class="mb-3">
                  <label for="redis_password" class="form-label">Redis 密码</label>
                  <input class="form-control" type="password" id="redis_password" name="redis_password" value="<?php echo htmlspecialchars($settings['redis_password'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                  <label for="redis_database" class="form-label">Redis 数据库编号</label>
                  <input class="form-control" type="number" min="0" id="redis_database" name="redis_database" value="<?php echo htmlspecialchars($settings['redis_database'] ?? 0); ?>">
                </div>
                <div class="mb-3">
                  <label for="member_qps_seconds" class="form-label">普通会员QPS限制秒数</label>
                  <input class="form-control" type="number" id="member_qps_seconds" name="member_qps_seconds" value="<?php echo htmlspecialchars($settings['member_qps_seconds']); ?>" placeholder="请输入普通会员QPS限制秒数">
                  <small class="form-text">普通会员的QPS限制时间窗口（秒）</small>
                </div>
                <div class="mb-3">
                  <label for="member_qps_limit" class="form-label">普通会员QPS限制请求数</label>
                  <input class="form-control" type="number" id="member_qps_limit" name="member_qps_limit" value="<?php echo htmlspecialchars($settings['member_qps_limit']); ?>" placeholder="请输入普通会员QPS限制请求数">
                  <small class="form-text">普通会员在限制时间窗口内的最大请求数</small>
                </div>
                <div class="mb-3">
                  <label for="temp_key_limit" class="form-label">临时密钥调用次数限制</label>
                  <input class="form-control" type="number" id="temp_key_limit" name="temp_key_limit" value="<?php echo htmlspecialchars($settings['temp_key_limit']); ?>" placeholder="请输入调用次数限制">
                </div>
                <div class="mb-3">
    <label class="form-label">启用余额/点数预警提醒</label>
    <div class="form-check form-switch">
        <input type="checkbox" class="form-check-input" id="enable_warn_notification" name="enable_warn_notification" value="1" <?php echo $settings['enable_warn_notification'] ? 'checked' : ''; ?>>
        <label class="form-check-label" for="enable_warn_notification"></label>
    </div>
    <small class="form-text">开启后，当用户余额或点数低于预警阈值时发送邮件提醒</small>
</div>
                <div class="mb-3">
                  <label for="warn_points_threshold" class="form-label">点数预警阈值</label>
                  <input class="form-control" type="number" id="warn_points_threshold" name="warn_points_threshold" value="<?php echo htmlspecialchars($settings['warn_points_threshold']); ?>" placeholder="请输入点数预警阈值">
                  <small class="form-text">当用户点数低于此值时发送邮件提醒（设为0或负数则不提醒）</small>
                </div>
                <div class="mb-3">
                  <label for="warn_balance_threshold" class="form-label">余额预警阈值（元）</label>
                  <input class="form-control" type="number" step="0.01" id="warn_balance_threshold" name="warn_balance_threshold" value="<?php echo htmlspecialchars($settings['warn_balance_threshold']); ?>" placeholder="请输入余额预警阈值">
                  <small class="form-text">当用户余额低于此值时发送邮件提醒（设为0或负数则不提醒）</small>
                </div>
                <div class="mb-3">
    <label class="form-label">启用每日赠送点数</label>
    <div class="form-check form-switch">
        <input type="checkbox" class="form-check-input" id="enable_daily_points" name="enable_daily_points" value="1" <?php echo $settings['enable_daily_points'] ? 'checked' : ''; ?>>
        <label class="form-check-label" for="enable_daily_points"></label>
    </div>
    <small class="form-text">开启后，系统每天自动赠送点数给普通会员</small>
</div>
                <div class="mb-3">
                  <label for="daily_free_points" class="form-label">每日赠送点数数量</label>
                  <input class="form-control" type="number" id="daily_free_points" name="daily_free_points" value="<?php echo htmlspecialchars($settings['daily_free_points']); ?>" placeholder="请输入每日赠送点数数量">
                 <small class="form-text">每天自动赠送给普通会员的点数数量</small>
                </div>
                <div class="mb-3">
                  <label class="form-label">每日赠送点数邮件通知</label>
                  <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input" id="enable_daily_points_notification" name="enable_daily_points_notification" value="1" <?php echo ($settings['enable_daily_points_notification'] ?? 1) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="enable_daily_points_notification"></label>
                  </div>
                  <small class="form-text">赠送成功后向用户邮箱发送到账通知，SMTP 配置有效时生效。</small>
                </div>
                <div>
                  <button type="submit" class="btn btn-primary me-1">保存设置</button>
                </div>
              </div>
              <div class="tab-pane fade" id="mail" aria-labelledby="basic-mail">
                <div class="mb-3">
                  <label class="form-label">开启邮件验证码注册</label>
                  <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input" id="mail_reg_enabled" name="mail_reg_enabled" value="1" <?php echo $settings['mail_reg_enabled'] ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="mail_reg_enabled"></label>
                  </div>
                  <small class="form-text">开启后，用户注册时必须通过邮箱验证码</small>
                </div>
                <div class="mb-3">
                  <label class="form-label">开启邮件找回密码</label>
                  <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input" id="mail_forgot_enabled" name="mail_forgot_enabled" value="1" <?php echo $settings['mail_forgot_enabled'] ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="mail_forgot_enabled"></label>
                  </div>
                  <small class="form-text">开启后，用户可以通过邮箱重置密码</small>
                </div>
                <div class="mb-3">
                  <label for="mail_smtp_host" class="form-label">SMTP 服务器</label>
                  <input class="form-control" type="text" id="mail_smtp_host" name="mail_smtp_host" value="<?php echo htmlspecialchars($settings['mail_smtp_host']); ?>" placeholder="例如：smtp.qq.com">
                </div>
                <div class="mb-3">
                  <label for="mail_smtp_port" class="form-label">端口</label>
                  <input class="form-control" type="number" id="mail_smtp_port" name="mail_smtp_port" value="<?php echo htmlspecialchars($settings['mail_smtp_port']); ?>" placeholder="例如：465">
                </div>
                <div class="mb-3">
                  <label for="mail_smtp_secure" class="form-label">加密方式</label>
                  <select class="form-select" id="mail_smtp_secure" name="mail_smtp_secure">
                    <option value="ssl" <?php echo $settings['mail_smtp_secure'] === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                    <option value="tls" <?php echo $settings['mail_smtp_secure'] === 'tls' ? 'selected' : ''; ?>>TLS</option>
                  </select>
                </div>
                <div class="mb-3">
                  <label for="mail_smtp_user" class="form-label">发信邮箱账户</label>
                  <input class="form-control" type="email" id="mail_smtp_user" name="mail_smtp_user" value="<?php echo htmlspecialchars($settings['mail_smtp_user']); ?>" placeholder="例如：yourname@qq.com">
                </div>
                <div class="mb-3">
                  <label for="mail_smtp_pass" class="form-label">发信邮箱密码/授权码</label>
                  <input class="form-control" type="password" id="mail_smtp_pass" name="mail_smtp_pass" value="<?php echo htmlspecialchars($settings['mail_smtp_pass']); ?>" placeholder="请输入密码或授权码">
                </div>
                <div class="d-flex justify-content-between align-items-center">
                  <a href="mail_test.php" class="text-primary">发送测试邮件...</a>
                  <button type="submit" class="btn btn-primary me-1">保存设置</button>
                </div>
              </div>
              <div class="tab-pane fade" id="turnstile" aria-labelledby="basic-turnstile">
                <div class="mb-3 form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="turnstile_enabled" name="turnstile_enabled" value="1" <?php echo $settings['turnstile_enabled'] ? 'checked' : ''; ?>>
                  <label class="form-check-label" for="turnstile_enabled">启用人机验证 (Cloudflare Turnstile)</label>
                  <div class="form-text">关闭时使用内置图形验证码；开启时需填写下方两个 Key，并使用 Cloudflare Turnstile 校验。</div>
                </div>
                <div class="mb-3">
                  <label for="turnstile_site_key" class="form-label">Turnstile Site Key</label>
                  <input class="form-control" type="text" id="turnstile_site_key" name="turnstile_site_key" value="<?php echo htmlspecialchars($settings['turnstile_site_key'] ?? ''); ?>" placeholder="0x4AAAAAA...">
                </div>
                <div class="mb-3">
                  <label for="turnstile_secret_key" class="form-label">Turnstile Secret Key</label>
                  <input class="form-control" type="text" id="turnstile_secret_key" name="turnstile_secret_key" value="<?php echo htmlspecialchars($settings['turnstile_secret_key'] ?? ''); ?>" placeholder="0x4AAAAAA...">
                </div>
                <small class="form-text text-muted d-block mb-3">在 https://dash.cloudflare.com 创建 Turnstile 站点后获取。需先勾选启用开关并填写两个 Key，后台与用户端登录/注册/重置密码/申请临时密钥才会使用 Turnstile 校验；留空或未勾选则使用内置图形验证码。</small>
                <div>
                  <button type="submit" class="btn btn-primary me-1">保存设置</button>
                </div>
              </div>
            </div>
          </form>
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
