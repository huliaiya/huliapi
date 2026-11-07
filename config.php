<?php
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', (int)(getenv('DB_PORT') ?: 3306));
define('DB_NAME', getenv('DB_NAME') ?: 'huliapi');
define('DB_USER', getenv('DB_USER') ?: 'huliapi');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');
define('ADMIN_PATH', 'admin');

 
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

function huli_is_admin_request() {
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($script === '') { return false; }
    $admin_path = '/' . trim((string)(defined('ADMIN_PATH') ? ADMIN_PATH : 'admin'), '/') . '/';
    return strpos($script, $admin_path) !== false;
}

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_hostname = huli_request_hostname();
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $source_hostname = '';
    $source_invalid = false;
    if ($origin) {
        $parsed_origin = parse_url($origin);
        if ($parsed_origin === false || !isset($parsed_origin['host'])) {
            $source_invalid = true;
        } else {
            $source_hostname = strtolower((string)$parsed_origin['host']);
        }
    } elseif ($referer) {
        $parsed_referer = parse_url($referer);
        if ($parsed_referer === false || !isset($parsed_referer['host'])) {
            $source_invalid = true;
        } else {
            $source_hostname = strtolower((string)$parsed_referer['host']);
        }
    }
    $host_conflict = ($source_hostname !== '' && $current_hostname !== '' && $source_hostname !== $current_hostname);
    $host_unknown = ($source_hostname !== '' && $current_hostname === '');
    // 后台写操作要求同源：缺少或无法解析 Origin/Referer 时直接拒绝，避免旧逻辑放行导致 CSRF。
    $admin_missing_source = (huli_is_admin_request() && $source_hostname === '' && !$source_invalid);
    $admin_bad_source = (huli_is_admin_request() && $source_invalid);
    if ($host_conflict || $host_unknown || $admin_missing_source || $admin_bad_source) {
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
    $js = <<<'JS'
<script>(function(){
if(window.__huliCsrfReady){return;}window.__huliCsrfReady=true;
var t=__TOKEN__;if(!t){return;}
function same(url){try{if(!url){return true;}var u=new URL(url,location.href);return u.origin===location.origin;}catch(e){return true;}}
function addField(f){try{if(!f||f.tagName!=='FORM'){return;}if((f.getAttribute('method')||'get').toLowerCase()!=='post'){return;}if(f.querySelector('input[name="_csrf"]')){return;}var e=document.createElement('input');e.type='hidden';e.name='_csrf';e.value=t;f.appendChild(e);}catch(e){}}
var forms=document.querySelectorAll('form');for(var i=0;i<forms.length;i++){addField(forms[i]);}
document.addEventListener('submit',function(ev){addField(ev.target);},true);
try{var osub=HTMLFormElement.prototype.submit;HTMLFormElement.prototype.submit=function(){addField(this);return osub.apply(this,arguments);};}catch(e){}
try{var of=window.fetch;if(of){window.fetch=function(input,init){try{init=init||{};var m=(init.method||(typeof input==='string'?'GET':(input&&input.method)||'GET')).toUpperCase();var url=(typeof input==='string')?input:(input&&input.url)||location.href;if(m!=='GET'&&m!=='HEAD'&&same(url)){var h=new Headers(init.headers||(typeof input!=='string'&&input.headers)||{});if(!h.has('X-CSRF-Token')){h.set('X-CSRF-Token',t);}init.headers=h;}}catch(e){}return of.apply(this,arguments);};}}catch(e){}
try{var oo=XMLHttpRequest.prototype.open,os=XMLHttpRequest.prototype.send;XMLHttpRequest.prototype.open=function(m,u){this.__huliM=(m||'GET').toUpperCase();this.__huliU=u;return oo.apply(this,arguments);};XMLHttpRequest.prototype.send=function(){try{if(this.__huliM&&this.__huliM!=='GET'&&this.__huliM!=='HEAD'&&same(this.__huliU)){this.setRequestHeader('X-CSRF-Token',t);}}catch(e){}return os.apply(this,arguments);};}catch(e){}
})();</script>
JS;
    return str_replace('__TOKEN__', $json, $js);
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

// 后台页面统一注入 CSRF 令牌脚本，使所有 POST 表单与 AJAX 请求自动携带令牌。
if (huli_is_admin_request() && session_status() === PHP_SESSION_ACTIVE) {
    ob_start(function ($html) {
        if (!is_string($html) || $html === '' || stripos($html, '</body>') === false) {
            return $html;
        }
        $script = huli_csrf_script();
        if ($script === '') { return $html; }
        return preg_replace('#</body>#i', $script . '</body>', $html, 1);
    });
}
