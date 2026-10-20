<?php
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', (int)(getenv('DB_PORT') ?: 3306));
define('DB_NAME', getenv('DB_NAME') ?: 'huliapi');
define('DB_USER', getenv('DB_USER') ?: 'huliapi');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');
define('ADMIN_PATH', 'admin');

if (!defined('HULI_TRUSTED_PROXIES')) {
    define('HULI_TRUSTED_PROXIES', getenv('HULI_TRUSTED_PROXIES') ?: '127.0.0.1,::1');
}

if (!defined('HULI_LOG_DIR')) {
    define('HULI_LOG_DIR', __DIR__ . '/logs');
}
if (is_dir(HULI_LOG_DIR) && is_writable(HULI_LOG_DIR)) {
    @ini_set('log_errors', '1');
    @ini_set('error_log', HULI_LOG_DIR . '/php_error.log');
}

 
function huli_request_hostname() {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host === '') { $host = $_SERVER['SERVER_NAME'] ?? ''; }
    $host = trim((string)$host);
    if ($host === '') { return ''; }
    if ($host[0] === '[') {
        $end = strpos($host, ']');
        return $end === false ? '' : strtolower(substr($host, 0, $end + 1));
    }
    return strtolower(explode(':', $host)[0]);
}

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_hostname = huli_request_hostname();
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $source_hostname = '';
    if ($origin) {
        $parsed_origin = parse_url($origin);
        $source_hostname = strtolower((string)($parsed_origin['host'] ?? ''));
    } elseif ($referer) {
        $parsed_referer = parse_url($referer);
        $source_hostname = strtolower((string)($parsed_referer['host'] ?? ''));
    }
    $host_conflict = ($source_hostname !== '' && $current_hostname !== '' && $source_hostname !== $current_hostname);
    $host_unknown = ($source_hostname !== '' && $current_hostname === '');
    if ($host_conflict || $host_unknown) {
        header('HTTP/1.1 400 Bad Request');
        header('Content-Type: application/json; charset=utf-8');
        die(json_encode(['success' => false, 'message' => '安全提示：检测到跨站请求，已拒绝处理。'], JSON_UNESCAPED_UNICODE));
    }
}

function huli_csrf_token() {
    if (session_status() !== PHP_SESSION_ACTIVE) { return ''; }
    if (empty($_SESSION['huli_csrf_token']) || !is_string($_SESSION['huli_csrf_token'])) {
        $_SESSION['huli_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['huli_csrf_token'];
}

function huli_csrf_field() {
    $token = huli_csrf_token();
    if ($token === '') { return ''; }
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

function huli_csrf_valid() {
    if (session_status() !== PHP_SESSION_ACTIVE) { return false; }
    $known = $_SESSION['huli_csrf_token'] ?? '';
    $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($known) && $known !== '' && is_string($sent) && $sent !== '' && hash_equals($known, $sent);
}

function huli_csrf_require() {
    if (huli_csrf_valid()) { return; }
    header('HTTP/1.1 403 Forbidden');
    $wants_json = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
    $payload = ['success' => false, 'message' => '安全校验失败，请刷新页面后重试。'];
    if ($wants_json) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><p>安全校验失败，请返回上一页刷新后重试。</p>';
    }
    exit;
}

function huli_csrf_script() {
    $token = huli_csrf_token();
    if ($token === '') { return ''; }
    $json = json_encode($token, JSON_UNESCAPED_UNICODE);
    return '<script>(function(){var t=' . $json . ';if(!t){return;}'
        . 'var f=document.querySelectorAll(\'form\');for(var i=0;i<f.length;i++){var m=(f[i].getAttribute(\'method\')||\'get\').toLowerCase();if(m!==\'post\'){continue;}if(f[i].querySelector(\'input[name="_csrf"]\')){continue;}var e=document.createElement(\'input\');e.type=\'hidden\';e.name=\'_csrf\';e.value=t;f[i].appendChild(e);}'
        . 'var X=window.XMLHttpRequest&&XMLHttpRequest.prototype;if(X&&X.open&&X.send){var oo=X.open,os=X.send;X.open=function(m){this.__huliMethod=(m||\'GET\').toUpperCase();return oo.apply(this,arguments);};X.send=function(){try{if(this.__huliMethod&&this.__huliMethod!==\'GET\'&&this.__huliMethod!==\'HEAD\'){this.setRequestHeader(\'X-CSRF-Token\',t);}}catch(e){}return os.apply(this,arguments);};}'
        . 'if(window.fetch){var of=window.fetch;window.fetch=function(i,n){var m=((n&&n.method)||(i&&i.method)||\'GET\').toUpperCase();if(m!==\'GET\'&&m!==\'HEAD\'){n=n||{};try{var h=new Headers(n.headers||(i&&i.headers)||{});if(!h.has(\'X-CSRF-Token\')){h.set(\'X-CSRF-Token\',t);}n.headers=h;}catch(e){}}return of.call(this,i,n);};}'
        . '})();</script>';
}

function huli_post_action_button($url, $fields, $label, $class = 'btn btn-default btn-sm', $confirm = '', $attrs = '') {
    $h = '<form method="POST" action="' . htmlspecialchars((string)$url, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;margin:0;">';
    $h .= huli_csrf_field();
    foreach ((array)$fields as $k => $v) {
        $h .= '<input type="hidden" name="' . htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') . '">';
    }
    $onclick = $confirm !== '' ? " onclick=\"return confirm('" . htmlspecialchars($confirm, ENT_QUOTES, 'UTF-8') . "');\"" : '';
    $h .= '<button type="submit" class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"' . $onclick . ' ' . $attrs . '>' . $label . '</button></form>';
    return $h;
}
