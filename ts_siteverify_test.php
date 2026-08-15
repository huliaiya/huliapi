<?php
@error_reporting(0);
@ini_set('display_errors', 'Off');
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('success' => false, 'message' => 'POST only'));
    exit;
}
$token = isset($_POST['token']) ? trim((string)$_POST['token']) : '';
if ($token === '') {
    echo json_encode(array('success' => false, 'message' => '缺少 token'));
    exit;
}
$secret = '';
if (file_exists(__DIR__ . '/config.php')) {
    require __DIR__ . '/config.php';
    if (class_exists('mysqli')) {
        $m = @new mysqli(defined('DB_HOST') ? DB_HOST : '127.0.0.1', defined('DB_USER') ? DB_USER : '', defined('DB_PASS') ? DB_PASS : '', defined('DB_NAME') ? DB_NAME : '');
        if (!$m->connect_errno) {
            $res = $m->query("SELECT setting_value FROM huli_settings WHERE setting_key='turnstile_secret_key'");
            if ($res && ($row = $res->fetch_row())) {
                $secret = (string)$row[0];
            }
            $m->close();
        }
    }
}
if ($secret === '') {
    echo json_encode(array('success' => false, 'message' => '未获取到 DB 中的 turnstile_secret_key'));
    exit;
}
$post = http_build_query(array(
    'secret' => $secret,
    'response' => $token,
    'idempotency_key' => vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4))
));
$context = stream_context_create(array('http' => array(
    'method' => 'POST',
    'header' => 'Content-Type: application/x-www-form-urlencoded',
    'content' => $post,
    'timeout' => 15,
    'ignore_errors' => true
)));
$raw = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $context);
if ($raw === false) {
    echo json_encode(array('success' => false, 'message' => '请求 Cloudflare 失败（无响应）'));
    exit;
}
echo $raw;
