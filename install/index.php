<?php
session_start();
define('STEP_CHECK_ENV', 1);
define('STEP_DB_CONFIG', 2);
define('STEP_INSTALL_DB', 3);
define('STEP_COMPLETE', 4);

define('HULI_INSTALL_PHP_MIN_VERSION', '7.4.0');

$is_installed = file_exists(__DIR__ . '/install.lock');
if ($is_installed && basename($_SERVER['PHP_SELF']) !== 'install.php' && (!isset($_GET['step']) || (int)$_GET['step'] !== STEP_COMPLETE)) {
    die('系统已安装，如需重新安装请删除install.lock文件');
}
if (!$is_installed && $_SERVER['REQUEST_METHOD'] !== 'POST' && (!isset($_GET['step']) || (int)$_GET['step'] !== STEP_CHECK_ENV) && (!isset($_GET['step']) || (int)$_GET['step'] !== STEP_COMPLETE)) {
    header('Location: ?step=' . STEP_CHECK_ENV);
    exit;
}

$current_step = isset($_GET['step']) ? (int)$_GET['step'] : STEP_CHECK_ENV;
$error = null;
$field_errors = [];
$success_msg = null;
$config_written_this_run = false;
$disclaimer_required = true;

function huli_installer_disclaimer_text() {
    return "免责声明与使用条款\n\n" .
        "1. 本系统仅供合法用途，严禁用于任何违反当地法律法规的活动。\n" .
        "2. 安装即表示您同意：系统收集的部署信息（域名、IP、PHP 版本、SMTP 与 Turnstile 配置摘要）将通过 yanzhengapi@163.com 邮箱回执至开发者，用于版本通知与安全审计。\n" .
        "3. 您承诺妥善保管管理员账号、数据库密码、SMTP 凭据与 Turnstile 密钥，不向第三方泄露。\n" .
        "4. 因使用本系统所产生的任何后果由使用者自行承担，开发者不承担任何责任。\n" .
        "5. 如不同意以上条款，请立即停止安装并删除本程序。\n\n" .
        "如已阅读并同意上述全部条款，请在下方输入框中输入“我已同意”后开始安装。";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';

        if ($action === 'check_env') {
            checkEnvironment();
            $_SESSION['env_checked'] = true;
            $current_step = STEP_DB_CONFIG;
        } elseif ($action === 'db_config') {
            if (empty($_SESSION['env_checked'])) {
                throw new Exception('请先完成环境检测');
            }

            $db_host   = trim($_POST['db_host'] ?? '127.0.0.1');
            $db_name   = trim($_POST['db_name'] ?? '');
            $db_user   = trim($_POST['db_user'] ?? '');
            $db_pwd    = $_POST['db_pwd'] ?? '';
            $admin_username = trim($_POST['admin_username'] ?? 'admin');
            $admin_nickname = trim($_POST['admin_nickname'] ?? '管理员');
            $admin_email    = trim($_POST['admin_email'] ?? '');
            $admin_qq       = trim($_POST['admin_qq'] ?? '');
            $admin_password       = $_POST['admin_password'] ?? '';
            $admin_password_confirm = $_POST['admin_password_confirm'] ?? '';
            $admin_path   = trim($_POST['admin_path'] ?? 'admin');
            $smtp_host    = trim($_POST['mail_smtp_host'] ?? '');
            $smtp_port_raw = trim($_POST['mail_smtp_port'] ?? '');
            $smtp_port    = $smtp_port_raw === '' ? 465 : (int)$smtp_port_raw;
            $smtp_secure  = $_POST['mail_smtp_secure'] ?? 'ssl';
            $smtp_user    = trim($_POST['mail_smtp_user'] ?? '');
            $smtp_pass    = $_POST['mail_smtp_pass'] ?? '';
            $turnstile_site_key   = trim($_POST['turnstile_site_key'] ?? '');
            $turnstile_secret_key = trim($_POST['turnstile_secret_key'] ?? '');
            $turnstile_enabled    = isset($_POST['turnstile_enabled']) ? '1' : '0';

             
            if ($db_name === '') $field_errors['db_name'] = '数据库名称不能为空';
            if ($db_user === '') $field_errors['db_user'] = '数据库用户名不能为空';
            if (!preg_match('/^[A-Za-z0-9_]{2,32}$/', $admin_username)) $field_errors['admin_username'] = '管理员账号需要 2-32 位字母、数字或下划线';
            if ($admin_nickname === '') $field_errors['admin_nickname'] = '管理员昵称不能为空';
            if (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) $field_errors['admin_email'] = '请输入有效的邮箱地址';
            if (strlen($admin_password) < 6) $field_errors['admin_password'] = '管理员密码至少 6 位';
            elseif ($admin_password !== $admin_password_confirm) $field_errors['admin_password_confirm'] = '两次输入的密码不一致';
            if (!preg_match('/^[A-Za-z][A-Za-z0-9_-]{1,31}$/', $admin_path) || in_array(strtolower($admin_path), ['install','api','assets','common','template','user'], true)) {
                $field_errors['admin_path'] = '后台目录名格式无效或为系统保留名';
            }
             
            if ($smtp_host === '') $field_errors['mail_smtp_host'] = 'SMTP 主机不能为空';
            if ($smtp_port < 1 || $smtp_port > 65535) $field_errors['mail_smtp_port'] = 'SMTP 端口范围无效';
            if (!in_array($smtp_secure, ['ssl','tls'], true)) $field_errors['mail_smtp_secure'] = 'SMTP 加密方式无效';
            if ($smtp_user === '') $field_errors['mail_smtp_user'] = 'SMTP 用户名不能为空';
            if ($smtp_pass === '') $field_errors['mail_smtp_pass'] = 'SMTP 密码不能为空';
            if (($turnstile_site_key === '') !== ($turnstile_secret_key === '')) $field_errors['turnstile_keys'] = 'Site Key 与 Secret Key 需同时填写或同时留空';

            if (!empty($field_errors)) {
                 
                $current_step = STEP_DB_CONFIG;
            } else {
                 
                $dsn = "mysql:host={$db_host};charset=utf8mb4";
                $pdo = new PDO($dsn, $db_user, $db_pwd, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $stmt = $pdo->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?");
                $stmt->execute([$db_name]);
                if (!$stmt->fetch()) {
                    $field_errors['db_name'] = "数据库 {$db_name} 不存在，请先在 MySQL 中创建";
                    $current_step = STEP_DB_CONFIG;
                } else {
                    $_SESSION['db_config'] = [
                        'host' => $db_host,
                        'name' => $db_name,
                        'user' => $db_user,
                        'pwd'  => $db_pwd,
                    ];
                    $_SESSION['install_config'] = [
                        'admin_username' => $admin_username,
                        'admin_nickname' => $admin_nickname,
                        'admin_email'    => $admin_email,
                        'admin_qq'       => $admin_qq,
                        'admin_password' => $admin_password,
                        'admin_path'     => $admin_path,
                        'smtp_host'      => $smtp_host,
                        'smtp_port'      => $smtp_port,
                        'smtp_secure'    => $smtp_secure,
                        'smtp_user'      => $smtp_user,
                        'smtp_pass'      => $smtp_pass,
                        'turnstile_enabled'      => $turnstile_enabled,
                        'turnstile_site_key'     => $turnstile_site_key,
                        'turnstile_secret_key'   => $turnstile_secret_key,
                    ];
                    $current_step = STEP_INSTALL_DB;
                }
            }
        } elseif ($action === 'install_db') {
            if (empty($_SESSION['db_config']) || empty($_SESSION['install_config'])) {
                throw new Exception('安装配置丢失，请返回上一步重新配置');
            }
             
            $disclaimer_checked = isset($_POST['disclaimer_agree']);
            $disclaimer_typed   = trim($_POST['disclaimer_text'] ?? '');
            if (!$disclaimer_checked || $disclaimer_typed !== '我已同意') {
                $current_step = STEP_INSTALL_DB;
                $error = '请勾选并输入「我已同意」以确认免责声明，方可继续安装';
            } else {
                $db = $_SESSION['db_config'];
                $install = $_SESSION['install_config'];
                $log = '';
                $log .= "> 正在生成数据库配置文件...\n";
                $config_path = __DIR__ . '/../config.php';
                $csrf_tail = '';
                if (file_exists($config_path)) {
                    $existing = file_get_contents($config_path);
                    if (preg_match('/define\(\s*\'ADMIN_PATH\'\s*,\s*[^)]+\)\s*;\s*\n([\s\S]*)$/', $existing, $m)) {
                        $csrf_tail = $m[1];
                    }
                }
                $configContent = "<?php\n"
                    . "define('DB_HOST', " . var_export($db['host'], true) . ");\n"
                    . "define('DB_NAME', " . var_export($db['name'], true) . ");\n"
                    . "define('DB_USER', " . var_export($db['user'], true) . ");\n"
                    . "define('DB_PASS', " . var_export($db['pwd'], true) . ");\n"
                    . "define('DB_CHARSET', 'utf8mb4');\n"
                    . "define('ADMIN_PATH', " . var_export($install['admin_path'], true) . ");\n"
                    . $csrf_tail;
                if (!file_put_contents($config_path, $configContent)) {
                    throw new Exception('无法创建配置文件，请检查目录权限');
                }
                $config_written_this_run = true;
                $log .= "✓ 配置文件生成成功\n";
                $log .= "> 正在连接数据库...\n";
                $dsn = "mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4";
                $pdo = new PDO($dsn, $db['user'], $db['pwd'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $log .= "> 正在清理现有数据表...\n";
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
                $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                if (!empty($tables)) {
                    foreach ($tables as $table) {
                        try {
                            $pdo->exec("DROP TABLE `{$table}`");
                            $log .= "✓ 已删除表: {$table}\n";
                        } catch (PDOException $e) {
                            $log .= "⚠ 删除表失败: {$table} (" . $e->getMessage() . ")\n";
                        }
                    }
                }
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                $log .= "✓ 数据库清理完成\n";
                $log .= "> 正在解析SQL文件...\n";
                $sql = @file_get_contents('install.sql');
                if (!$sql) throw new Exception('无法读取 install.sql 文件');
                $sql_commands = preg_split('/;\s*\n/', $sql);
                $log .= "> 开始导入数据库结构 (共 " . count($sql_commands) . " 条 SQL 语句)...\n";
                foreach ($sql_commands as $command) {
                    $command = trim($command);
                    if (!empty($command)) {
                        $table_name = '';
                        if (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([^\s`(]+)/i', $command, $matches)) {
                            $table_name = $matches[1];
                        }
                        if (empty($table_name) && preg_match('/INSERT\s+INTO\s+`?([^\s`]+)/i', $command, $matches)) {
                            $table_name = $matches[1];
                        }
                        if (empty($table_name)) $table_name = 'SQL命令';
                        try {
                            $start_time = microtime(true);
                            $pdo->exec($command);
                            $time_taken = round((microtime(true) - $start_time) * 1000, 2);
                            $log .= "✓ [{$table_name}] 执行成功 ({$time_taken}ms)\n";
                        } catch (PDOException $e) {
                            if (strpos($e->getMessage(), 'already exists') !== false) {
                                $log .= "⚠ [{$table_name}] 表已存在 (跳过)\n";
                            } else {
                                $log .= "✗ [{$table_name}] 执行失败: " . $e->getMessage() . "\n";
                            }
                        }
                    }
                }
                $log .= "✓ 数据库导入完成\n";
                $adminStmt = $pdo->prepare("UPDATE huli_admins SET username = ?, password = ?, email = ?, qq = ?, nickname = ? WHERE id = 1");
                $adminUpdated = $adminStmt->execute([
                    $install['admin_username'],
                    password_hash($install['admin_password'], PASSWORD_DEFAULT),
                    $install['admin_email'],
                    $install['admin_qq'],
                    $install['admin_nickname'],
                ]);
                if ($adminStmt->rowCount() === 0) {
                    $adminInsert = $pdo->prepare("INSERT INTO huli_admins (id, username, password, email, qq, nickname, status) VALUES (1, ?, ?, ?, ?, ?, 1)");
                    $adminInsert->execute([
                        $install['admin_username'],
                        password_hash($install['admin_password'], PASSWORD_DEFAULT),
                        $install['admin_email'],
                        $install['admin_qq'],
                        $install['admin_nickname'],
                    ]);
                }
                $settingStmt = $pdo->prepare("UPDATE huli_settings SET setting_value = ? WHERE setting_key = ?");
                foreach ([
                    ['mail_smtp_host', $install['smtp_host']],
                    ['mail_smtp_port', (string)$install['smtp_port']],
                    ['mail_smtp_secure', $install['smtp_secure']],
                    ['mail_smtp_user', $install['smtp_user']],
                    ['mail_smtp_pass', $install['smtp_pass']],
                    ['turnstile_enabled', $install['turnstile_enabled']],
                    ['turnstile_site_key', $install['turnstile_site_key']],
                    ['turnstile_secret_key', $install['turnstile_secret_key']],
                ] as $setting) {
                    $settingStmt->execute([$setting[1], $setting[0]]);
                }
                $log .= "✓ 管理员和邮件配置保存成功\n";
                if ($install['admin_path'] !== 'admin') {
                    if (!is_dir(__DIR__ . '/../admin')) throw new Exception('默认后台目录不存在，无法重命名');
                    if (file_exists(__DIR__ . '/../' . $install['admin_path'])) throw new Exception('后台目录名已存在，请更换名称');
                    if (!rename(__DIR__ . '/../admin', __DIR__ . '/../' . $install['admin_path'])) throw new Exception('后台目录重命名失败，请检查目录权限');
                    $log .= "✓ 后台目录已设置为 /{$install['admin_path']}/\n";
                }
                $log .= "> 正在创建安装锁文件...\n";
                file_put_contents(__DIR__ . '/install.lock', "安装锁\n安装完成时间: " . date('Y-m-d H:i:s'));
                $log .= "✓ 安装锁文件创建成功\n";

                 
                $receipt_sent = false;
                $receipt_error = '';
                try {
                    $receipt_sent = send_install_receipt($install, $db, $log);
                } catch (Throwable $mailEx) {
                    $receipt_error = $mailEx->getMessage();
                    error_log('[install] receipt mail failed: ' . $receipt_error);
                }
                if ($receipt_sent) {
                    $log .= "✓ 已发送免责声明回执邮件到 yanzhengapi@163.com\n";
                } else {
                    $log .= "⚠ 免责声明回执邮件发送失败（不影响安装）\n";
                }

                $_SESSION['install_log'] = $log;
                $_SESSION['installed_admin'] = $install;
                $_SESSION['receipt_sent'] = $receipt_sent;
                header('Location: ?step=' . STEP_COMPLETE);
                exit;
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        if ($action === 'install_db' && $config_written_this_run) {
            if (file_exists(__DIR__ . '/../config.php')) @unlink(__DIR__ . '/../config.php');
            if (file_exists(__DIR__ . '/install.lock')) @unlink(__DIR__ . '/install.lock');
        }
    }
}

function send_install_receipt($install, $db, $log) {
    $smtp_host = $install['smtp_host'] ?? '';
    if ($smtp_host === '') return false;
    $domain = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'unknown');
    $ip = $_SERVER['SERVER_ADDR'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $phpv = PHP_VERSION;
    $server_software = $_SERVER['SERVER_SOFTWARE'] ?? 'unknown';
    $time = date('Y-m-d H:i:s');

    $subject = '[huliapi 安装回执] ' . $domain . ' - ' . $time;
    $body = huli_installer_disclaimer_text()
        . "\n\n========================================\n"
        . "安装回执\n"
        . "========================================\n"
        . "时间:        " . $time . "\n"
        . "域名:        " . $domain . "\n"
        . "服务器 IP:   " . $ip . "\n"
        . "PHP 版本:    " . $phpv . "\n"
        . "Web 软件:    " . $server_software . "\n"
        . "管理员账号:  " . $install['admin_username'] . "\n"
        . "管理员邮箱:  " . $install['admin_email'] . "\n"
        . "后台路径:    /" . $install['admin_path'] . "/\n"
        . "数据库名:    " . $db['name'] . "\n"
        . "数据库主机:  " . $db['host'] . "\n"
        . "SMTP 主机:   " . $install['smtp_host'] . "\n"
        . "SMTP 端口:   " . $install['smtp_port'] . "\n"
        . "SMTP 用户:   " . $install['smtp_user'] . "\n"
        . "Turnstile:   " . ($install['turnstile_enabled'] === '1' ? '启用' : '关闭') . "\n";

    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer') && !class_exists('PHPMailer')) {
        $base = __DIR__ . '/../common/PHPMailer/src/';
        if (file_exists($base . 'Exception.php')) require_once $base . 'Exception.php';
        if (file_exists($base . 'PHPMailer.php')) require_once $base . 'PHPMailer.php';
        if (file_exists($base . 'SMTP.php')) require_once $base . 'SMTP.php';
    }
    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer') && !class_exists('PHPMailer')) {
        $autoload = __DIR__ . '/../vendor/autoload.php';
        if (file_exists($autoload)) require_once $autoload;
    }
    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer') && !class_exists('PHPMailer')) {
        return false;
    }
    $cls = class_exists('PHPMailer\\PHPMailer\\PHPMailer') ? 'PHPMailer\\PHPMailer\\PHPMailer' : 'PHPMailer';
    $mail = new $cls();
    $mail->isSMTP();
    $mail->Host       = $install['smtp_host'];
    $mail->Port       = (int)$install['smtp_port'];
    $mail->SMTPSecure = $install['smtp_secure'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $install['smtp_user'];
    $mail->Password   = $install['smtp_pass'];
    $mail->CharSet    = 'UTF-8';
    $mail->SMTPDebug  = 0;
    $mail->setFrom($install['smtp_user'], 'huliapi Installer');
    $mail->addAddress('yanzhengapi@163.com', 'huliapi Dev');
    $mail->Subject = $subject;
    $mail->Body    = $body;
    return $mail->send();
}

function huli_installer_extension_available($key) {
    switch ($key) {
        case 'pdo_mysql':
            return class_exists('PDO') && extension_loaded('pdo_mysql');
        case 'curl':
            return function_exists('curl_init');
        case 'openssl':
            return extension_loaded('openssl') || function_exists('openssl_encrypt');
        case 'mbstring':
            return extension_loaded('mbstring') || function_exists('mb_strlen');
        case 'gd':
            return extension_loaded('gd') || function_exists('imagecreatetruecolor');
        case 'zip':
            return class_exists('ZipArchive');
        default:
            return extension_loaded($key);
    }
}

function huli_installer_env_checks() {
    $checks = [];

    $checks[] = [
        'name'     => 'PHP 版本',
        'pass'     => version_compare(PHP_VERSION, HULI_INSTALL_PHP_MIN_VERSION, '>='),
        'status'   => PHP_VERSION . '（要求 ≥ ' . HULI_INSTALL_PHP_MIN_VERSION . '）',
        'required' => true,
        'tip'      => '请升级至 PHP ' . HULI_INSTALL_PHP_MIN_VERSION . ' 或更高版本（推荐 8.0 及以上）',
    ];

    $extensions = [
        ['key' => 'pdo_mysql', 'label' => 'PDO MySQL 扩展', 'required' => true,  'tip' => '请启用 pdo_mysql 扩展以连接 MySQL/MariaDB 数据库'],
        ['key' => 'curl',      'label' => 'cURL 扩展',      'required' => true,  'tip' => '请启用 curl 扩展以支持 SMTP、推送通知与在线更新'],
        ['key' => 'openssl',   'label' => 'OpenSSL 扩展',   'required' => true,  'tip' => '请启用 openssl 扩展以支持 HTTPS 与加密通信'],
        ['key' => 'mbstring',  'label' => 'Mbstring 扩展',  'required' => true,  'tip' => '请启用 mbstring 扩展以支持中文等多字节字符串处理'],
        ['key' => 'gd',        'label' => 'GD 图形库',      'required' => true,  'tip' => '请启用 gd 扩展以支持图片验证码、头像裁剪等图形功能'],
        ['key' => 'zip',       'label' => 'Zip 扩展',       'required' => false, 'tip' => '仅后台「在线更新」解压升级包时需要 ZipArchive，未启用可正常安装与使用其余全部功能；如需启用，Linux 安装 php-zip 后重启 PHP（apt install php-zip / yum install php-zip），Windows 在 php.ini 启用 extension=zip'],
    ];
    foreach ($extensions as $ext) {
        $available = huli_installer_extension_available($ext['key']);
        $checks[] = [
            'name'     => $ext['label'],
            'pass'     => $available,
            'status'   => $available ? '已启用' : '未启用',
            'required' => $ext['required'],
            'tip'      => $ext['tip'],
        ];
    }

    $dirs = [
        ['label' => '网站根目录', 'path' => __DIR__ . '/../'],
        ['label' => 'API 接口目录', 'path' => __DIR__ . '/../API'],
    ];
    foreach ($dirs as $dir) {
        $writable = is_writable($dir['path']);
        $checks[] = [
            'name'     => '目录可写 · ' . $dir['label'],
            'pass'     => $writable,
            'status'   => $dir['path'] . '（' . ($writable ? '可写' : '不可写') . '）',
            'required' => true,
            'tip'      => '安装过程需要写入配置文件，请为 ' . $dir['label'] . ' 设置写权限',
        ];
    }

    $config_file = __DIR__ . '/../config.php';
    if (file_exists($config_file)) {
        $writable = is_writable($config_file);
        $checks[] = [
            'name'     => '配置文件可写',
            'pass'     => $writable,
            'status'   => $config_file . '（' . ($writable ? '可写' : '不可写') . '）',
            'required' => true,
            'tip'      => 'config.php 已存在，需要可写以便写入数据库连接等配置',
        ];
    }

    return $checks;
}

function checkEnvironment() {
    $failed = [];
    foreach (huli_installer_env_checks() as $check) {
        if (!$check['pass'] && $check['required']) {
            $failed[] = $check['name'] . '（' . $check['status'] . '）';
        }
    }
    if (!empty($failed)) {
        throw new Exception('环境检测未通过：' . implode('、', $failed));
    }
}

function showInstallPage($step, $error = null, $field_errors = []) {
    $steps = [
        STEP_CHECK_ENV => ['title' => '环境检测', 'active' => $step == STEP_CHECK_ENV, 'completed' => $step > STEP_CHECK_ENV],
        STEP_DB_CONFIG => ['title' => '数据库配置', 'active' => $step == STEP_DB_CONFIG, 'completed' => $step > STEP_DB_CONFIG],
        STEP_INSTALL_DB => ['title' => '安装数据库', 'active' => $step == STEP_INSTALL_DB, 'completed' => $step > STEP_INSTALL_DB],
        STEP_COMPLETE => ['title' => '安装完成', 'active' => $step == STEP_COMPLETE, 'completed' => false],
    ];

    $f = function($key) use ($field_errors) {
        return isset($field_errors[$key]) ? htmlspecialchars($field_errors[$key]) : '';
    };
    $has_error = function($key) use ($field_errors) {
        return isset($field_errors[$key]) ? ' is-invalid' : '';
    };
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>huliapi 系统安装向导</title>
<link rel="stylesheet" href="../assets/css/materialdesignicons.min.css">
<link rel="stylesheet" href="../assets/css/bootstrap.min.css">
<style>
:root {
  --primary: #1976d2;
  --primary-dark: #1565c0;
  --success: #10ae93;
  --warning: #f5a623;
  --danger: #dc5475;
  --text-secondary: #6c7a89;
  --bg-card: rgba(255,255,255,0.7);
}
body {
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  background: linear-gradient(135deg, #eaf3ff 0%, #f8f6ff 50%, #ebfbf8 100%);
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}
.install-container { width: 100%; max-width: 880px; }
.card {
  border: none;
  border-radius: 20px;
  background: var(--bg-card);
  backdrop-filter: blur(20px);
  box-shadow: 0 20px 60px rgba(0,0,0,0.1);
  overflow: hidden;
}
.card-header {
  background: linear-gradient(135deg, #267de0 0%, #2cb4e1 56%, #53d0d2 100%);
  color: white;
  padding: 30px;
  text-align: center;
  border-bottom: none;
}
.card-title {
  font-size: 1.5rem;
  font-weight: 600;
  margin: 0;
}
.card-body { padding: 40px; }
.step-indicator {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 40px;
  position: relative;
}
.step {
  flex: 1;
  text-align: center;
  position: relative;
}
.step-number {
  width: 50px;
  height: 50px;
  border-radius: 50%;
  background: #e0e9f5;
  color: #61718c;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 1.2rem;
  margin: 0 auto 10px;
  border: 2px solid transparent;
  transition: all 0.3s ease;
}
.step.active .step-number {
  background: var(--primary);
  color: white;
  box-shadow: 0 0 0 6px rgba(25,118,210,0.15);
}
.step.completed .step-number {
  background: var(--success);
  color: white;
}
.step-title {
  font-size: 0.85rem;
  color: var(--text-secondary);
  font-weight: 500;
}
.step.active .step-title { color: var(--primary-dark); font-weight: 600; }
.step.completed .step-title { color: var(--success); font-weight: 600; }
.step-connector {
  position: absolute;
  top: 25px;
  left: calc(50% + 30px);
  width: calc(100% - 60px);
  height: 2px;
  background: #e0e9f5;
  z-index: -1;
}
.step.completed .step-connector { background: var(--success); }
.env-check-box,
.credentials-box {
  background: rgba(255,255,255,0.5);
  border-radius: 12px;
  padding: 20px;
  border: 1px solid rgba(0,0,0,0.05);
}
.env-check-list { list-style: none; padding: 0; margin: 0; }
.env-check-item {
  display: flex;
  align-items: center;
  padding: 12px 0;
  border-bottom: 1px solid rgba(0,0,0,0.05);
  transition: background 0.2s;
}
.env-check-item:last-child { border-bottom: none; }
.env-check-item:hover { background: rgba(255,255,255,0.3); border-radius: 8px; padding-left: 8px; padding-right: 8px; }
.env-check-icon { font-size: 1.5rem; margin-right: 15px; }
.check-success { color: var(--success); }
.check-danger { color: var(--danger); }
.check-warning { color: var(--warning); }
.env-check-item strong { display: block; margin-bottom: 2px; }
.env-check-item p { margin: 0; font-size: 0.85rem; }
.btn-install {
  background: linear-gradient(135deg, #267de0 0%, #2cb4e1 100%);
  border: none;
  color: white;
  padding: 12px 30px;
  border-radius: 8px;
  font-weight: 500;
  transition: all 0.3s ease;
  box-shadow: 0 4px 15px rgba(38,125,224,0.3);
}
.btn-install:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(38,125,224,0.4);
  color: white;
}
.btn-install:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }
.success-icon { font-size: 4rem; color: var(--success); }
.credentials-box { margin-top: 25px; }
.credential-item {
  display: flex;
  align-items: center;
  padding: 12px 0;
  border-bottom: 1px solid rgba(0,0,0,0.05);
}
.credential-item:last-child { border-bottom: none; }
.credential-icon { font-size: 1.4rem; margin-right: 15px; color: var(--primary); width: 30px; }
.credential-label { font-weight: 600; min-width: 100px; color: var(--text-secondary); }
.credential-value { color: #2c3e50; font-family: monospace; }
.security-alert {
  background: rgba(245,166,35,0.1);
  border-left: 3px solid var(--warning);
  padding: 15px;
  border-radius: 8px;
  margin-top: 25px;
}
.security-alert h5 { color: var(--warning); margin-bottom: 8px; font-size: 1rem; }
.security-alert p { margin: 0; font-size: 0.9rem; }
.form-control {
  padding: 12px 15px;
  border-radius: 8px;
  border: 1px solid rgba(0,0,0,0.1);
  transition: all 0.3s ease;
}
.form-control:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 0.25rem rgba(25,118,210,0.25);
}
.form-control.is-invalid {
  border-color: var(--danger);
  box-shadow: 0 0 0 0.2rem rgba(220,84,117,0.18);
}
.invalid-feedback {
  display: block;
  color: var(--danger);
  font-size: 0.85rem;
  margin-top: 6px;
  font-weight: 500;
}
.alert {
  border-radius: 8px;
  padding: 15px;
}
.section-heading {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 24px 0 16px;
  padding-bottom: 10px;
  border-bottom: 1px solid #edf1f5;
  color: var(--primary-dark);
  font-weight: 600;
}
.section-heading i { font-size: 1.3rem; }
.section-heading small { margin-left: auto; color: var(--text-secondary); font-weight: 400; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.terminal-container h5 { color: var(--primary-dark); font-weight: 600; }
.credentials-box h5 { color: var(--primary-dark); font-weight: 600; }
@media (max-width: 768px) {
  .step { padding: 0 15px; }
  .step-number { width: 40px; height: 40px; font-size: 1rem; }
  .card-header { padding: 20px; }
  .card-body { padding: 20px; }
  .form-row { grid-template-columns: 1fr; gap: 0; }
  .section-heading small { display: none; }
}


.disclaimer-modal-mask {
  position: fixed;
  inset: 0;
  z-index: 9999;
  display: none;
  overflow: hidden;
  background: #f6f8fb;
}
.disclaimer-modal-mask.show { display: block; }
.disclaimer-modal {
  width: 100%;
  height: 100%;
  height: 100dvh;
  min-height: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  background: #fff;
}
.disclaimer-modal-header {
  flex: 0 0 auto;
  padding: calc(12px + env(safe-area-inset-top)) clamp(16px, 4vw, 36px) 12px;
  background: linear-gradient(135deg, #267de0 0%, #2cb4e1 56%, #53d0d2 100%);
  color: white;
  display: flex;
  align-items: center;
  gap: 10px;
}
.disclaimer-modal-header h5 {
  flex: 1 1 auto;
  margin: 0;
  font-weight: 600;
  font-size: clamp(1rem, 0.8vw + 0.85rem, 1.35rem);
  line-height: 1.3;
}
.disclaimer-close {
  flex: 0 0 auto;
  width: 38px;
  height: 38px;
  border: none;
  border-radius: 50%;
  background: rgba(255,255,255,0.2);
  color: #fff;
  font-size: 1.35rem;
  line-height: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background 0.2s ease, transform 0.25s ease;
}
.disclaimer-close:hover { background: rgba(255,255,255,0.34); transform: rotate(90deg); }
.disclaimer-modal-body {
  flex: 1 1 auto;
  min-height: 0;
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
  overscroll-behavior: contain;
  padding: clamp(16px, 3vw, 36px) clamp(16px, 4vw, 48px);
  background: #f6f8fb;
}
.disclaimer-content {
  max-width: 840px;
  margin: 0 auto;
  padding: clamp(18px, 3vw, 40px) clamp(18px, 3.5vw, 48px);
  background: #fff;
  border-radius: 14px;
  box-shadow: 0 12px 34px rgba(7, 17, 47, 0.07);
  color: #2c3e50;
  line-height: 1.9;
  font-size: clamp(0.92rem, 0.55vw + 0.86rem, 1.04rem);
  white-space: pre-line;
  word-break: break-word;
}
.disclaimer-modal-footer {
  flex: 0 0 auto;
  padding: calc(14px + env(safe-area-inset-bottom)) clamp(16px, 4vw, 36px);
  border-top: 1px solid #e6ebf2;
  display: flex;
  flex-direction: column;
  gap: 12px;
  background: #fff;
  box-shadow: 0 -8px 24px rgba(7, 17, 47, 0.05);
}
.disclaimer-check-row {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #2c3e50;
}
.disclaimer-input-row {
  display: flex;
  align-items: center;
  gap: 10px;
}
.disclaimer-input-row label { font-weight: 600; min-width: 90px; color: var(--primary-dark); margin: 0; }
.disclaimer-input-row input {
  flex: 1;
  padding: 10px 14px;
  border: 1px solid rgba(0,0,0,0.15);
  border-radius: 8px;
  transition: all 0.25s;
}
.disclaimer-input-row input:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 0.2rem rgba(25,118,210,0.18);
  outline: none;
}
.disclaimer-input-row input.is-invalid {
  border-color: var(--danger);
  background: rgba(220,84,117,0.06);
  animation: disclaimer-shake 0.4s;
}
.disclaimer-error {
  color: var(--danger);
  font-size: 0.85rem;
  font-weight: 500;
  display: none;
}
.disclaimer-error.show { display: block; }
@keyframes disclaimer-shake {
  0%, 100% { transform: translateX(0); }
  25% { transform: translateX(-6px); }
  75% { transform: translateX(6px); }
}
.disclaimer-actions { display: flex; justify-content: flex-end; gap: 10px; }
.disclaimer-actions button { padding: 10px 22px; border-radius: 8px; font-weight: 600; border: none; cursor: pointer; transition: all 0.2s; }
.btn-confirm-install { background: linear-gradient(135deg, #267de0, #2cb4e1); color: white; box-shadow: 0 4px 15px rgba(38,125,224,0.3); }
.btn-confirm-install:hover { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(38,125,224,0.4); }
.btn-cancel-install { background: rgba(0,0,0,0.06); color: #2c3e50; }
.btn-cancel-install:hover { background: rgba(0,0,0,0.12); }
@media (max-width: 560px) {
  .disclaimer-modal-header { gap: 6px; }
  .disclaimer-close { width: 34px; height: 34px; font-size: 1.2rem; }
  .disclaimer-modal-body { padding: 14px 12px; }
  .disclaimer-content { padding: 18px 16px; line-height: 1.75; border-radius: 10px; }
  .disclaimer-modal-footer { padding-top: 12px; padding-bottom: calc(12px + env(safe-area-inset-bottom)); }
  .disclaimer-check-row { align-items: flex-start; }
  .disclaimer-input-row { align-items: stretch; flex-direction: column; gap: 6px; }
  .disclaimer-input-row label { min-width: 0; }
  .disclaimer-actions { flex-wrap: wrap; }
  .disclaimer-actions button { flex: 1 1 130px; }
}
</style>
</head>
<body>
<div class="install-container">
  <div class="card">
    <header class="card-header">
      <div class="card-title">
        <i class="mdi mdi-cog-outline mr-2"></i>系统安装向导
      </div>
    </header>
    <div class="card-body">
      <div class="step-indicator">
        <?php foreach ($steps as $i => $step_info): ?>
        <div class="step <?= $step_info['active'] ? 'active' : '' ?> <?= $step_info['completed'] ? 'completed' : '' ?>">
          <div class="step-number"><?= $i ?></div>
          <div class="step-title"><?= htmlspecialchars($step_info['title']) ?></div>
          <?php if ($i < count($steps)): ?>
          <div class="step-connector"></div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>

      <?php if ($error): ?>
      <div class="alert alert-danger">
        <i class="mdi mdi-alert-circle-outline"></i>
        <?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>

      <form method="post" id="install-form">
        <input type="hidden" name="action" value="<?php
            echo $step == STEP_CHECK_ENV ? 'check_env'
               : ($step == STEP_DB_CONFIG ? 'db_config' : 'install_db');
        ?>">

        <?php if ($step == STEP_CHECK_ENV): ?>
        <?php
        $env_checks = huli_installer_env_checks();
        $env_total = count($env_checks);
        $env_req_failed = 0;
        $env_opt_failed = 0;
        foreach ($env_checks as $env_item) {
            if ($env_item['pass']) continue;
            if ($env_item['required']) $env_req_failed++;
            else $env_opt_failed++;
        }
        $env_passed = $env_total - $env_req_failed - $env_opt_failed;
        ?>
        <div class="env-check-box">
          <h5 class="mb-3"><i class="mdi mdi-server-security mr-2"></i>系统环境检测
            <small class="text-muted" style="float:right;font-size:0.85rem;font-weight:400;line-height:1.9;">已通过 <?= $env_passed ?> / <?= $env_total ?> 项</small>
          </h5>

          <?php if ($env_req_failed > 0): ?>
          <div class="alert alert-danger py-2"><i class="mdi mdi-alert-circle-outline mr-1"></i>共有 <?= $env_req_failed ?> 项必需项未通过，请根据下方提示处理后即可继续安装。</div>
          <?php elseif ($env_opt_failed > 0): ?>
          <div class="alert alert-warning py-2"><i class="mdi mdi-alert-outline mr-1"></i>必需项全部通过，但存在 <?= $env_opt_failed ?> 项可选项未启用，可继续安装（仅影响对应辅助功能）。</div>
          <?php else: ?>
          <div class="alert alert-success py-2"><i class="mdi mdi-check-circle-outline mr-1"></i>环境检测全部通过，可以继续下一步。</div>
          <?php endif; ?>

          <ul class="env-check-list" id="env-check-list">
            <?php foreach ($env_checks as $env_item):
              $env_ok = $env_item['pass'];
              $env_req = !empty($env_item['required']);
              $env_icon = $env_ok ? 'check-circle' : ($env_req ? 'close-circle' : 'alert-outline');
              $env_icon_cls = $env_ok ? 'check-success' : ($env_req ? 'check-danger' : 'check-warning');
            ?>
            <li class="env-check-item" data-pass="<?= ($env_ok || !$env_req) ? '1' : '0' ?>">
              <i class="mdi mdi-<?= $env_icon ?> env-check-icon <?= $env_icon_cls ?>"></i>
              <div>
                <strong><?= htmlspecialchars($env_item['name']) ?><?= $env_req ? '' : ' <small class="text-muted">(可选)</small>' ?></strong>
                <p class="text-muted"><?= htmlspecialchars($env_item['status']) ?><?= $env_ok ? '' : '<br>提示：' . htmlspecialchars($env_item['tip']) ?></p>
              </div>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>

        <?php elseif ($step == STEP_DB_CONFIG): ?>
        <div class="section-heading"><i class="mdi mdi-database-outline"></i><span>数据库连接</span></div>

        <div class="form-group mb-4">
          <label class="form-label"><i class="mdi mdi-server-network mr-2"></i>数据库主机</label>
          <input class="form-control" type="text" name="db_host" value="<?= htmlspecialchars($_SESSION['db_config']['host'] ?? '127.0.0.1') ?>" required>
          <small class="text-muted">通常是127.0.0.1或localhost</small>
        </div>

        <div class="form-group mb-4">
          <label class="form-label"><i class="mdi mdi-database mr-2"></i>数据库名称</label>
          <input class="form-control<?= $has_error('db_name') ?>" type="text" name="db_name" value="<?= htmlspecialchars($_SESSION['db_config']['name'] ?? '') ?>" required>
          <?php if ($f('db_name')): ?><div class="invalid-feedback"><i class="mdi mdi-alert-circle-outline mr-1"></i><?= $f('db_name') ?></div><?php endif; ?>
          <small class="text-muted">请确保数据库已存在（不存在请先在 MySQL 中创建）</small>
        </div>

        <div class="form-group mb-4">
          <label class="form-label"><i class="mdi mdi-account mr-2"></i>数据库用户名</label>
          <input class="form-control<?= $has_error('db_user') ?>" type="text" name="db_user" value="<?= htmlspecialchars($_SESSION['db_config']['user'] ?? '') ?>" required>
          <?php if ($f('db_user')): ?><div class="invalid-feedback"><i class="mdi mdi-alert-circle-outline mr-1"></i><?= $f('db_user') ?></div><?php endif; ?>
        </div>

        <div class="form-group mb-4">
          <label class="form-label"><i class="mdi mdi-key mr-2"></i>数据库密码</label>
          <input class="form-control" type="password" name="db_pwd" value="<?= htmlspecialchars($_SESSION['db_config']['pwd'] ?? '') ?>">
        </div>

        <div class="section-heading"><i class="mdi mdi-account-cog-outline"></i><span>管理员账户</span></div>

        <div class="form-row">
          <div class="form-group mb-4">
            <label class="form-label">登录账号</label>
            <input class="form-control<?= $has_error('admin_username') ?>" type="text" name="admin_username" value="<?= htmlspecialchars($_SESSION['install_config']['admin_username'] ?? 'admin') ?>" required>
            <?php if ($f('admin_username')): ?><div class="invalid-feedback"><i class="mdi mdi-alert-circle-outline mr-1"></i><?= $f('admin_username') ?></div><?php else: ?><small class="text-muted">2-32 位字母、数字或下划线</small><?php endif; ?>
          </div>
          <div class="form-group mb-4">
            <label class="form-label">管理员昵称</label>
            <input class="form-control<?= $has_error('admin_nickname') ?>" type="text" name="admin_nickname" value="<?= htmlspecialchars($_SESSION['install_config']['admin_nickname'] ?? '管理员') ?>" required>
            <?php if ($f('admin_nickname')): ?><div class="invalid-feedback"><i class="mdi mdi-alert-circle-outline mr-1"></i><?= $f('admin_nickname') ?></div><?php endif; ?>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group mb-4">
            <label class="form-label">管理员邮箱</label>
            <input class="form-control<?= $has_error('admin_email') ?>" type="email" name="admin_email" value="<?= htmlspecialchars($_SESSION['install_config']['admin_email'] ?? '') ?>" required placeholder="如 admin@example.com">
            <?php if ($f('admin_email')): ?><div class="invalid-feedback"><i class="mdi mdi-alert-circle-outline mr-1"></i><?= $f('admin_email') ?></div><?php else: ?><small class="text-muted">用于接收系统通知、找回密码等，请填写真实有效邮箱</small><?php endif; ?>
          </div>
          <div class="form-group mb-4">
            <label class="form-label">后台目录名</label>
            <input class="form-control<?= $has_error('admin_path') ?>" type="text" name="admin_path" value="<?= htmlspecialchars($_SESSION['install_config']['admin_path'] ?? 'admin') ?>" required>
            <?php if ($f('admin_path')): ?><div class="invalid-feedback"><i class="mdi mdi-alert-circle-outline mr-1"></i><?= $f('admin_path') ?></div><?php else: ?><small class="text-muted">安装后后台地址为 /此目录名/，请勿使用保留名（install/api/assets/common/template/user）</small><?php endif; ?>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group mb-4">
            <label class="form-label">登录密码</label>
            <input class="form-control<?= $has_error('admin_password') ?>" type="password" name="admin_password" required minlength="6">
            <?php if ($f('admin_password')): ?><div class="invalid-feedback"><i class="mdi mdi-alert-circle-outline mr-1"></i><?= $f('admin_password') ?></div><?php endif; ?>
          </div>
          <div class="form-group mb-4">
            <label class="form-label">确认密码</label>
            <input class="form-control<?= $has_error('admin_password_confirm') ?>" type="password" name="admin_password_confirm" required minlength="6">
            <?php if ($f('admin_password_confirm')): ?><div class="invalid-feedback"><i class="mdi mdi-alert-circle-outline mr-1"></i><?= $f('admin_password_confirm') ?></div><?php endif; ?>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group mb-4">
            <label class="form-label">管理员 QQ</label>
            <input class="form-control" type="text" name="admin_qq" value="<?= htmlspecialchars($_SESSION['install_config']['admin_qq'] ?? '') ?>" placeholder="填写后显示 QQ 头像">
            <small class="text-muted">不填则使用默认 QQ 头像</small>
          </div>
        </div>

        <div class="section-heading"><i class="mdi mdi-email-fast-outline"></i><span>SMTP 邮件配置</span><small>必填，用于系统邮件通知与免责声明回执</small></div>

        <div class="form-row">
          <div class="form-group mb-4">
            <label class="form-label">SMTP 主机 <span style="color:var(--danger)">*</span></label>
            <input class="form-control<?= $has_error('mail_smtp_host') ?>" type="text" name="mail_smtp_host" value="<?= htmlspecialchars($_SESSION['install_config']['smtp_host'] ?? '') ?>" placeholder="smtp.example.com" required>
            <?php if ($f('mail_smtp_host')): ?><div class="invalid-feedback"><i class="mdi mdi-alert-circle-outline mr-1"></i><?= $f('mail_smtp_host') ?></div><?php endif; ?>
          </div>
          <div class="form-group mb-4">
            <label class="form-label">SMTP 端口 <span style="color:var(--danger)">*</span></label>
            <input class="form-control<?= $has_error('mail_smtp_port') ?>" type="number" name="mail_smtp_port" value="<?= htmlspecialchars($_SESSION['install_config']['smtp_port'] ?? '465') ?>" required>
            <?php if ($f('mail_smtp_port')): ?><div class="invalid-feedback"><i class="mdi mdi-alert-circle-outline mr-1"></i><?= $f('mail_smtp_port') ?></div><?php endif; ?>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group mb-4">
            <label class="form-label">加密方式 <span style="color:var(--danger)">*</span></label>
            <select class="form-control" name="mail_smtp_secure" required>
              <option value="ssl" <?= ($_SESSION['install_config']['smtp_secure'] ?? 'ssl') === 'ssl' ? 'selected' : '' ?>>SSL</option>
              <option value="tls" <?= ($_SESSION['install_config']['smtp_secure'] ?? 'ssl') === 'tls' ? 'selected' : '' ?>>TLS</option>
            </select>
            <?php if ($f('mail_smtp_secure')): ?><div class="invalid-feedback"><i class="mdi mdi-alert-circle-outline mr-1"></i><?= $f('mail_smtp_secure') ?></div><?php endif; ?>
          </div>
          <div class="form-group mb-4">
            <label class="form-label">SMTP 用户名 <span style="color:var(--danger)">*</span></label>
            <input class="form-control<?= $has_error('mail_smtp_user') ?>" type="text" name="mail_smtp_user" value="<?= htmlspecialchars($_SESSION['install_config']['smtp_user'] ?? '') ?>" required>
            <?php if ($f('mail_smtp_user')): ?><div class="invalid-feedback"><i class="mdi mdi-alert-circle-outline mr-1"></i><?= $f('mail_smtp_user') ?></div><?php endif; ?>
          </div>
        </div>

        <div class="form-group mb-4">
          <label class="form-label">SMTP 密码 <span style="color:var(--danger)">*</span></label>
          <input class="form-control<?= $has_error('mail_smtp_pass') ?>" type="password" name="mail_smtp_pass" value="<?= htmlspecialchars($_SESSION['install_config']['smtp_pass'] ?? '') ?>" required>
          <?php if ($f('mail_smtp_pass')): ?><div class="invalid-feedback"><i class="mdi mdi-alert-circle-outline mr-1"></i><?= $f('mail_smtp_pass') ?></div><?php else: ?><small class="text-muted">授权码或登录密码均可，安装完成后会自动发送一封免责声明回执邮件至开发者邮箱</small><?php endif; ?>
        </div>

        <div class="section-heading"><i class="mdi mdi-shield-account-outline"></i><span>Cloudflare 人机验证</span><small>默认使用测试密钥，正式上线前请替换</small></div>

        <div class="form-group mb-4">
          <label class="form-check-label">
            <input class="form-check-input" type="checkbox" name="turnstile_enabled" value="1" <?= (!empty($_SESSION['install_config']['turnstile_enabled']) || empty($_SESSION['install_config'])) ? 'checked' : '' ?>>
            启用 Cloudflare Turnstile 人机验证（启用后须填写下方两个 Key）
          </label>
        </div>

        <div class="form-row">
          <div class="form-group mb-4">
            <label class="form-label">Turnstile Site Key</label>
            <input class="form-control" type="text" name="turnstile_site_key" value="<?= htmlspecialchars($_SESSION['install_config']['turnstile_site_key'] ?? '3x00000000000000000000FF') ?>">
          </div>
          <div class="form-group mb-4">
            <label class="form-label">Turnstile Secret Key</label>
            <input class="form-control" type="text" name="turnstile_secret_key" value="<?= htmlspecialchars($_SESSION['install_config']['turnstile_secret_key'] ?? '1x0000000000000000000000000000000AA') ?>">
          </div>
        </div>
        <?php if ($f('turnstile_keys')): ?>
        <div class="alert alert-danger"><i class="mdi mdi-alert-circle-outline mr-1"></i><?= $f('turnstile_keys') ?></div>
        <?php else: ?>
        <div class="text-muted small mb-3">当前默认使用 Cloudflare 官方测试密钥，任何环境均可通过验证。正式上线前请在 https://dash.cloudflare.com 创建 Turnstile 站点并替换为您自己的密钥。</div>
        <?php endif; ?>

        <?php elseif ($step == STEP_INSTALL_DB): ?>
        <div class="terminal-container">
          <h5 class="mb-3"><i class="mdi mdi-console-line mr-2"></i>安装终端</h5>
          <div class="terminal" id="install-terminal">
            <?php if (!empty($_SESSION['install_log'])): ?>
              <?php
              $log_lines = explode("\n", $_SESSION['install_log']);
              foreach ($log_lines as $line):
                $line = trim($line);
                if (empty($line)) continue;
                $class = 'terminal-line';
                if (strpos($line, '✓') === 0) {
                  $class .= ' terminal-success';
                } elseif (strpos($line, '✗') === 0 || strpos($line, '错误') !== false) {
                  $class .= ' terminal-error';
                } elseif (strpos($line, '⚠') === 0) {
                  $class .= ' terminal-warning';
                } elseif (strpos($line, '>') === 0) {
                  $class .= ' terminal-prompt';
                } else {
                  $class .= ' terminal-info';
                }
              ?>
              <div class="<?= $class ?>"><?= htmlspecialchars($line) ?></div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="terminal-line terminal-prompt">> 准备开始安装...</div>
              <div class="terminal-line terminal-prompt">> 点击「开始安装」按钮继续</div>
              <div class="terminal-line terminal-info">点击按钮后将弹出免责声明，确认后方可继续。</div>
            <?php endif; ?>
          </div>
        </div>

        
        <input type="hidden" name="disclaimer_agree" id="disclaimer_agree_flag" value="">
        <input type="hidden" name="disclaimer_text" id="disclaimer_text_flag" value="">

        <div class="disclaimer-modal-mask" id="disclaimer-modal">
          <div class="disclaimer-modal">
            <div class="disclaimer-modal-header">
              <i class="mdi mdi-shield-alert-outline" style="font-size:1.5rem;"></i>
              <h5>免责声明与使用条款</h5>
              <button type="button" class="disclaimer-close" id="disclaimer-close" title="关闭" aria-label="关闭">
                <i class="mdi mdi-close"></i>
              </button>
            </div>
            <div class="disclaimer-modal-body">
              <div class="disclaimer-content"><?= htmlspecialchars(huli_installer_disclaimer_text()) ?></div>
            </div>
            <div class="disclaimer-modal-footer">
              <div class="disclaimer-check-row">
                <input type="checkbox" id="disclaimer-checkbox">
                <label for="disclaimer-checkbox" style="margin:0;cursor:pointer;">我已阅读并同意上述全部条款</label>
              </div>
              <div class="disclaimer-input-row">
                <label for="disclaimer-input">确认输入：</label>
                <input type="text" id="disclaimer-input" placeholder="请输入「我已同意」" autocomplete="off">
              </div>
              <div class="disclaimer-error" id="disclaimer-error"><i class="mdi mdi-alert-circle-outline mr-1"></i>请勾选复选框并准确输入「我已同意」</div>
              <div class="disclaimer-actions">
                <button type="button" class="btn-cancel-install" id="disclaimer-cancel">取消</button>
                <button type="button" class="btn-confirm-install" id="disclaimer-confirm">同意并开始安装</button>
              </div>
            </div>
          </div>
        </div>

        <?php elseif ($step == STEP_COMPLETE): ?>
        <div class="text-center">
          <i class="mdi mdi-check-circle success-icon"></i>
          <h3 class="mt-3">安装成功！</h3>
          <p class="lead text-muted">系统已成功安装，您可以开始使用了</p>
          <div class="credentials-box">
            <h5 class="mb-4"><i class="mdi mdi-account-key mr-2"></i>管理员账号信息</h5>
            <div class="credential-item">
              <i class="mdi mdi-account-circle credential-icon"></i>
              <span class="credential-label">用户名</span>
              <span class="credential-value"><?= htmlspecialchars($_SESSION['installed_admin']['admin_username'] ?? '已设置') ?></span>
            </div>
            <div class="credential-item">
              <i class="mdi mdi-key credential-icon"></i>
              <span class="credential-label">初始密码</span>
              <span class="credential-value">安装时设置的密码</span>
            </div>
            <div class="credential-item">
              <i class="mdi mdi-email-check-outline credential-icon" style="color: <?= !empty($_SESSION['receipt_sent']) ? 'var(--success)' : 'var(--warning)' ?>;"></i>
              <span class="credential-label">回执邮件</span>
              <span class="credential-value"><?= !empty($_SESSION['receipt_sent']) ? '已发送至 yanzhengapi@163.com（含免责声明）' : '发送失败，请检查 SMTP 配置（不影响安装）' ?></span>
            </div>
            <div class="credential-item">
              <i class="mdi mdi-alert-circle credential-icon" style="color: var(--warning);"></i>
              <span class="credential-label">安全提示</span>
              <span class="credential-value">请登录后立即修改密码</span>
            </div>
          </div>
          <div class="mt-4">
            <a href="../" class="btn btn-primary mr-3">
              <i class="mdi mdi-home mr-2"></i>前往首页
            </a>
            <a href="../<?= htmlspecialchars($_SESSION['installed_admin']['admin_path'] ?? 'admin') ?>/" class="btn btn-outline-primary">
              <i class="mdi mdi-settings mr-2"></i>前往后台
            </a>
          </div>
          <div class="security-alert mt-4">
            <h5><i class="mdi mdi-security mr-2"></i>安全提示</h5>
            <p class="mb-0">为了系统安全，请立即删除或重命名 install 目录</p>
          </div>
        </div>
        <?php endif; ?>

        <hr class="my-4">
        <div class="d-flex justify-content-between">
          <?php if ($step > STEP_CHECK_ENV && $step < STEP_COMPLETE): ?>
          <a href="?step=<?= $step - 1 ?>" class="btn btn-outline-secondary">
            <i class="mdi mdi-arrow-left mr-2"></i>上一步
          </a>
          <?php else: ?>
          <span></span>
          <?php endif; ?>

          <?php if ($step < STEP_COMPLETE): ?>
          <button type="submit" class="btn btn-install" id="submit-btn">
            <?= $step == STEP_INSTALL_DB ? '开始安装' : '下一步' ?>
            <i class="mdi mdi-arrow-right ml-2"></i>
          </button>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function() {
  var form = document.getElementById('install-form');
  var submitBtn = document.getElementById('submit-btn');
  var actionInput = form ? form.querySelector('input[name="action"]') : null;

  if (form && submitBtn) {
    form.addEventListener('submit', function(e) {
      
      if (actionInput && actionInput.value === 'install_db') {
        e.preventDefault();
        openDisclaimer();
        return;
      }
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<i class="mdi mdi-loading mdi-spin mr-2"></i>处理中...';
    });
  }

  var envList = document.getElementById('env-check-list');
  if (envList && submitBtn) {
    var failedItems = envList.querySelectorAll('.env-check-item[data-pass="0"]');
    if (failedItems.length > 0) {
      submitBtn.disabled = true;
      submitBtn.title = '请先解决上方未通过的环境检测项';
    }
  }

  
  var modal = document.getElementById('disclaimer-modal');
  var checkbox = document.getElementById('disclaimer-checkbox');
  var input = document.getElementById('disclaimer-input');
  var errorEl = document.getElementById('disclaimer-error');
  var confirmBtn = document.getElementById('disclaimer-confirm');
  var cancelBtn = document.getElementById('disclaimer-cancel');
  var agreeFlag = document.getElementById('disclaimer_agree_flag');
  var textFlag = document.getElementById('disclaimer_text_flag');

  function openDisclaimer() {
    if (!modal) return;
    checkbox.checked = false;
    input.value = '';
    input.classList.remove('is-invalid');
    errorEl.classList.remove('show');
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
  }
  function closeDisclaimer() {
    if (!modal) return;
    modal.classList.remove('show');
    document.body.style.overflow = '';
  }

  if (confirmBtn) {
    confirmBtn.addEventListener('click', function() {
      var ok = checkbox.checked && input.value.trim() === '我已同意';
      if (!ok) {
        input.classList.add('is-invalid');
        errorEl.classList.add('show');
        setTimeout(function() { input.classList.remove('is-invalid'); }, 600);
        return;
      }
      agreeFlag.value = '1';
      textFlag.value = '我已同意';
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<i class="mdi mdi-loading mdi-spin mr-2"></i>安装中...';
      form.submit();
    });
  }
  if (cancelBtn) {
    cancelBtn.addEventListener('click', closeDisclaimer);
  }
  var closeBtn = document.getElementById('disclaimer-close');
  if (closeBtn) {
    closeBtn.addEventListener('click', closeDisclaimer);
  }
  if (modal) {
    modal.addEventListener('click', function(e) {
      if (e.target === modal) closeDisclaimer();
    });
  }
  if (input) {
    input.addEventListener('input', function() {
      input.classList.remove('is-invalid');
      errorEl.classList.remove('show');
    });
  }
  if (checkbox) {
    checkbox.addEventListener('change', function() {
      errorEl.classList.remove('show');
    });
  }

  
  var terminal = document.getElementById('install-terminal');
  if (terminal) terminal.scrollTop = terminal.scrollHeight;

  
  var firstInvalid = document.querySelector('.form-control.is-invalid');
  if (firstInvalid) {
    try { firstInvalid.focus(); } catch (e) {}
  }
})();
</script>
</body>
</html>
<?php
}

showInstallPage($current_step, $error, $field_errors);
