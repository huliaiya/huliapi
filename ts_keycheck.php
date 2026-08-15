<?php
@error_reporting(0);
@ini_set('display_errors', 'Off');
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="zh"><head><meta charset="utf-8"><title>Turnstile 密钥诊断</title></head><body style="font-family:monospace;background:#111;color:#0f0;padding:20px;">';
echo '<h3>Turnstile 线上密钥诊断</h3>';

$keys = array();
if (file_exists(__DIR__ . '/config.php')) {
    require __DIR__ . '/config.php';
    $dbok = false;
    if (class_exists('mysqli')) {
        $m = @new mysqli(defined('DB_HOST') ? DB_HOST : '127.0.0.1', defined('DB_USER') ? DB_USER : '', defined('DB_PASS') ? DB_PASS : '', defined('DB_NAME') ? DB_NAME : '');
        if (!$m->connect_errno) {
            $dbok = true;
            $res = $m->query("SELECT setting_key, setting_value FROM huli_settings WHERE setting_key IN ('turnstile_enabled','turnstile_site_key','turnstile_secret_key')");
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $keys[$row['setting_key']] = $row['setting_value'];
                }
            } else {
                echo '<p style="color:#f55">DB 查询失败: ' . $m->error . '</p>';
            }
            $m->close();
        } else {
            echo '<p style="color:#f55">mysqli 连接失败: ' . $m->connect_error . '</p>';
        }
    } else {
        echo '<p style="color:#f55">未安装 mysqli 扩展</p>';
    }
} else {
    echo '<p style="color:#f55">未找到 config.php（请放在站点根目录）</p>';
}

echo '<h4>数据库已保存值</h4>';
$secret = isset($keys['turnstile_secret_key']) ? $keys['turnstile_secret_key'] : '';
$site   = isset($keys['turnstile_site_key']) ? $keys['turnstile_site_key'] : '';
$en     = isset($keys['turnstile_enabled']) ? $keys['turnstile_enabled'] : '';
echo '<p>turnstile_enabled: <b>' . htmlspecialchars($en) . '</b></p>';
echo '<p>turnstile_site_key: <b>' . htmlspecialchars($site) . '</b>（长度 ' . strlen($site) . '）</p>';
echo '<p>turnstile_secret_key: 前8位 <b>' . htmlspecialchars(substr($secret, 0, 8)) . '</b>（长度 ' . strlen($secret) . '）</p>';

echo '<h4>用 DB 中的 Secret 探测 siteverify</h4>';
if ($secret !== '') {
    $post = http_build_query(array('secret' => $secret, 'response' => 'FAKE_TOKEN_FOR_TEST'));
    $ctx = stream_context_create(array('http' => array(
        'method' => 'POST',
        'header' => 'Content-Type: application/x-www-form-urlencoded',
        'content' => $post,
        'timeout' => 15,
        'ignore_errors' => true
    )));
    $raw = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $ctx);
    echo '<p>siteverify(伪造token) 原始返回:</p><pre style="border:1px solid #333;padding:10px;">' . htmlspecialchars((string)$raw) . '</pre>';
    $dec = json_decode($raw, true);
    if (is_array($dec) && isset($dec['error-codes'])) {
        $ec = $dec['error-codes'];
        if (in_array('invalid-input-secret', $ec)) {
            echo '<p style="color:#f55">=> DB 里的 Secret 不被 Cloudflare 接受（该 Secret 无效或不属于任何站点）</p>';
        } elseif (in_array('invalid-input-response', $ec)) {
            echo '<p style="color:#ff0">=> DB 里的 Secret 有效且可被 Cloudflare 识别（密钥对正常；失败原因在 token/域名环节）</p>';
        } else {
            echo '<p style="color:#5f5">=> 返回码: ' . htmlspecialchars(json_encode($ec)) . '</p>';
        }
    }
} else {
    echo '<p style="color:#f55">DB 中未取到 turnstile_secret_key</p>';
}

echo '<h4>当前访问域名</h4>';
echo '<p>HTTP_HOST: <b>' . htmlspecialchars(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '') . '</b></p>';
echo '<p>SERVER_NAME: <b>' . htmlspecialchars(isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '') . '</b></p>';

echo '</body></html>';
