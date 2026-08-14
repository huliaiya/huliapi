<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (file_exists(dirname(__DIR__) . '/config.php')) {
    require_once dirname(__DIR__) . '/config.php';
}

function huli_turnstile_enabled()
{
    static $enabled = null;
    if ($enabled !== null) {
        return $enabled;
    }
    $enabled = false;
    if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER')) {
        return $enabled;
    }
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . (defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4'),
            DB_USER,
            defined('DB_PASS') ? DB_PASS : ''
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $rows = $pdo->query("SELECT setting_key, setting_value FROM huli_settings WHERE setting_key IN ('turnstile_enabled','turnstile_site_key','turnstile_secret_key')")->fetchAll(PDO::FETCH_KEY_PAIR);
        $enabled = isset($rows['turnstile_enabled']) && (string)$rows['turnstile_enabled'] === '1'
            && !empty($rows['turnstile_site_key'])
            && !empty($rows['turnstile_secret_key']);
    } catch (Exception $e) {
    }
    return $enabled;
}

function huli_turnstile_keys()
{
    static $keys = null;
    if ($keys !== null) {
        return $keys;
    }
    $keys = array('site_key' => '', 'secret_key' => '');
    if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER')) {
        return $keys;
    }
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . (defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4'),
            DB_USER,
            defined('DB_PASS') ? DB_PASS : ''
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $pdo->query("SELECT setting_value FROM huli_settings WHERE setting_key = 'turnstile_site_key'");
        $sk = $stmt->fetchColumn();
        $stmt = $pdo->query("SELECT setting_value FROM huli_settings WHERE setting_key = 'turnstile_secret_key'");
        $ss = $stmt->fetchColumn();
        $keys['site_key'] = $sk !== false ? (string)$sk : '';
        $keys['secret_key'] = $ss !== false ? (string)$ss : '';
    } catch (Exception $e) {
    }
    return $keys;
}

function huli_turnstile_widget_html()
{
    static $widget_index = 0;
    static $script_printed = false;
    if (!huli_turnstile_enabled()) {
        return '';
    }
    $widget_index++;
    $widget_id = 'huli-turnstile-' . $widget_index;
    $site_key = huli_turnstile_keys()['site_key'];
    $html = '<div id="' . $widget_id . '" class="huli-turnstile mb-3"></div>';
    if (!$script_printed) {
        $html .= '<script>'
            . 'window.huliTurnstileWidgets=window.huliTurnstileWidgets||{};'
            . 'window.huliTurnstilePending=window.huliTurnstilePending||{};'
            . 'window.huliGetTurnstileResponse=window.huliGetTurnstileResponse||function(){'
            . 'var inputs=document.getElementsByName("cf-turnstile-response");'
            . 'for(var i=0;i<inputs.length;i++){if(inputs[i].value){return inputs[i].value;}}'
            . 'return "";'
            . '};'
            . 'window.huliResetTurnstiles=window.huliResetTurnstiles||function(){'
            . 'var inputs=document.getElementsByName("cf-turnstile-response");'
            . 'for(var i=0;i<inputs.length;i++){inputs[i].value="";}'
            . 'if(window.turnstile){'
            . 'for(var widgetId in window.huliTurnstileWidgets){'
            . 'if(Object.prototype.hasOwnProperty.call(window.huliTurnstileWidgets,widgetId)){window.turnstile.reset(window.huliTurnstileWidgets[widgetId]);}'
            . '}'
            . '}'
            . '};'
            . 'window.huliRenderOneTurnstile=window.huliRenderOneTurnstile||function(widgetId,siteKey){'
            . 'if(!window.turnstile||window.huliTurnstileWidgets[widgetId]!==undefined){return;}'
            . 'window.huliTurnstileWidgets[widgetId]=window.turnstile.render("#"+widgetId,{sitekey:siteKey});'
            . '};'
            . 'window.huliTryRenderTurnstiles=window.huliTryRenderTurnstiles||function(){'
            . 'if(!window.turnstile){return;}'
            . 'for(var widgetId in window.huliTurnstilePending){'
            . 'if(Object.prototype.hasOwnProperty.call(window.huliTurnstilePending,widgetId)){window.huliRenderOneTurnstile(widgetId,window.huliTurnstilePending[widgetId]);}'
            . '}'
            . '};'
            . 'window.huliOnTurnstileLoad=window.huliOnTurnstileLoad||function(){window.huliTryRenderTurnstiles();};'
            . 'window.huliRenderTurnstile=window.huliRenderTurnstile||function(widgetId,siteKey){'
            . 'window.huliTurnstilePending[widgetId]=siteKey;'
            . 'window.huliTryRenderTurnstiles();'
            . '};'
            . '</script>'
            . '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=huliOnTurnstileLoad" async defer></script>';
        $script_printed = true;
    }
    $html .= '<script>window.huliRenderTurnstile(' . json_encode($widget_id) . ',' . json_encode($site_key) . ');</script>';
    return $html;
}

function huli_turnstile_verify()
{
    if (!huli_turnstile_enabled()) {
        return true;
    }
    $keys = huli_turnstile_keys();
    $token = isset($_POST['cf-turnstile-response']) ? trim((string)$_POST['cf-turnstile-response']) : '';
    if ($token === '') {
        error_log('[turnstile] 未收到 cf-turnstile-response token');
        return false;
    }
    $post_data = array(
        'secret' => $keys['secret_key'],
        'response' => $token
    );
    $body = http_build_query($post_data);
    $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    $result = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $result = curl_exec($ch);
        if ($result === false) {
            error_log('[turnstile] siteverify 网络错误: ' . curl_error($ch));
        }
        curl_close($ch);
    } else {
        $context = stream_context_create(array('http' => array(
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => $body,
            'timeout' => 10
        )));
        $result = @file_get_contents($url, false, $context);
    }
    if ($result === false) {
        error_log('[turnstile] siteverify 请求失败（无响应）');
        return false;
    }
    $data = json_decode($result, true);
    $success = isset($data['success']) && (bool)$data['success'];
    if (!$success) {
        $codes = isset($data['error-codes']) ? implode(',', (array)$data['error-codes']) : 'unknown';
        error_log('[turnstile] siteverify 验证失败 error-codes: ' . $codes);
    }
    return $success;
}
