<?php
require_once __DIR__ . '/../session_boot.php';
@error_reporting(0);
@ini_set('display_errors', 'Off');
header('Content-Type: application/json; charset=utf-8');
function ts_json_response($success, $message, $extra = array()) {
    $payload = array_merge(array('success' => $success, 'message' => $message), $extra);
    echo json_encode($payload);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { ts_json_response(false, '无效的请求方式。'); }
if (empty($_SESSION['admin_id'])) { ts_json_response(false, '请先登录后台再执行测试。'); }
if (!file_exists('../../config.php')) { ts_json_response(false, '系统错误: 配置文件丢失。'); }
require_once '../../config.php';
require_once __DIR__ . '/../turnstile.php';

$site = isset($_POST['site_key']) ? (string)$_POST['site_key'] : '';
$secret = isset($_POST['secret_key']) ? (string)$_POST['secret_key'] : '';
$token = isset($_POST['cf-turnstile-response']) ? (string)$_POST['cf-turnstile-response'] : '';
$mode = isset($_POST['mode']) ? (string)$_POST['mode'] : 'keys';

$check = huli_turnstile_check_keys($site, $secret);
if (!$check['ok']) {
    ts_json_response(false, $check['msg']);
}

if ($mode !== 'e2e') {
    ts_json_response(true, $check['msg'], array('site_is_test' => $check['site_is_test']));
}

if ($token === '') {
    ts_json_response(false, '未检测到验证令牌：请先完成页面中的 Cloudflare 验证组件，再点击「提交验证」。');
}

$raw = huli_turnstile_siteverify_raw($secret, $token);
$codes = isset($raw['error-codes']) ? (array)$raw['error-codes'] : array();
$site_fp = strlen($site) >= 8 ? substr($site, 0, 8) . '...' . substr($site, -4) : $site;
$secret_fp = strlen($secret) >= 12 ? substr($secret, 0, 8) . '...' . substr($secret, -4) : $secret;
$submitted_fp = array('site' => $site_fp, 'secret' => $secret_fp);
$db_keys = huli_turnstile_keys();
$db_fp = array();
if (!empty($db_keys['site_key'])) {
    $db_fp['site'] = strlen($db_keys['site_key']) >= 12
        ? substr($db_keys['site_key'], 0, 8) . '...' . substr($db_keys['site_key'], -4)
        : $db_keys['site_key'];
}
if (!empty($db_keys['secret_key'])) {
    $db_fp['secret'] = strlen($db_keys['secret_key']) >= 12
        ? substr($db_keys['secret_key'], 0, 8) . '...' . substr($db_keys['secret_key'], -4)
        : $db_keys['secret_key'];
}
if (!empty($raw['success'])) {
    $hostname = isset($raw['hostname']) ? $raw['hostname'] : '';
    $msg = '验证通过！Cloudflare 返回 success=true';
    if ($hostname !== '') {
        $msg .= '（请求来源域名：' . $hostname . '）';
    }
    $msg .= '。当前密钥与域名授权均正常。';
    ts_json_response(true, $msg, array('raw' => $raw, 'submitted_fp' => $submitted_fp, 'db_fp' => $db_fp));
}

$msg = '验证未通过：' . huli_turnstile_error_message($codes);
if (in_array('invalid-input-secret', $codes, true)) {
    $msg .= '。请再次核对 Secret Key 是否与 Cloudflare 后台完全一致（建议重新复制粘贴）。';
} elseif (in_array('invalid-input-response', $codes, true)) {
    $msg .= '。该令牌已失效或对应密钥为测试密钥，请刷新页面后重新完成验证再试。';
} elseif (in_array('timeout-or-duplicate', $codes, true)) {
    $msg .= '。该令牌已过期或已被使用，请刷新页面后重新完成验证再试。';
} elseif (in_array('internal-error', $codes, true)) {
    $msg .= '。服务端请求 Cloudflare 失败，请检查服务器外网连通性后再试。';
}
ts_json_response(false, $msg, array('raw' => $raw, 'submitted_fp' => $submitted_fp, 'db_fp' => $db_fp));
