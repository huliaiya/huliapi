<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (file_exists(dirname(__DIR__) . '/config.php')) {
    require_once dirname(__DIR__) . '/config.php';
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
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM huli_settings WHERE setting_key IN ('turnstile_site_key','turnstile_secret_key')");
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $keys['site_key'] = isset($rows['turnstile_site_key']) ? (string)$rows['turnstile_site_key'] : '';
        $keys['secret_key'] = isset($rows['turnstile_secret_key']) ? (string)$rows['turnstile_secret_key'] : '';
    } catch (Exception $e) {
    }
    return $keys;
}

function huli_turnstile_enabled()
{
    $keys = huli_turnstile_keys();
    return !empty($keys['site_key']) && !empty($keys['secret_key']);
}

function huli_turnstile_widget_html()
{
    if (!huli_turnstile_enabled()) {
        return '';
    }
    $site_key = htmlspecialchars(huli_turnstile_keys()['site_key'], ENT_QUOTES);
    return '<div class="cf-turnstile mb-3" data-sitekey="' . $site_key . '"></div>'
        . '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
}

function huli_turnstile_verify()
{
    if (!huli_turnstile_enabled()) {
        return true;
    }
    $keys = huli_turnstile_keys();
    $token = isset($_POST['cf-turnstile-response']) ? trim((string)$_POST['cf-turnstile-response']) : '';
    if ($token === '') {
        return false;
    }
    $post_data = array(
        'secret' => $keys['secret_key'],
        'response' => $token,
        'remoteip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : ''
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
        return false;
    }
    $data = json_decode($result, true);
    return isset($data['success']) && (bool)$data['success'];
}
