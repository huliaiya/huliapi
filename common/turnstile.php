<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (file_exists(dirname(__DIR__) . '/config.php')) {
    require_once dirname(__DIR__) . '/config.php';
}

/**
 * Cloudflare Turnstile 集成。
 *
 * 关键约束（来自官方文档）：
 * - token 有效期 300 秒，且只能被 siteverify 校验一次；
 * - 正式密钥拒绝测试 token，测试密钥拒绝正式 token；
 * - 正式 sitekey 必须在 Cloudflare 后台的 Hostname Management 中授权当前访问域名。
 */

define('HULI_TURNSTILE_TEST_SITE_KEYS', '|1x00000000000000000000AA|2x00000000000000000000AB|1x00000000000000000000BB|2x00000000000000000000BB|3x00000000000000000000FF|');
define('HULI_TURNSTILE_TEST_SECRET_KEYS', '|1x0000000000000000000000000000000AA|2x0000000000000000000000000000000AA|3x0000000000000000000000000000000AA|');

/**
 * 一次性读取全部 Turnstile 配置，避免重复建立数据库连接。
 * 所有取值都会 trim，防止后台粘贴密钥时带入空格或换行导致 invalid-input-secret。
 */
function huli_turnstile_settings()
{
    static $settings = null;
    if ($settings !== null) {
        return $settings;
    }
    $settings = array('enabled' => false, 'site_key' => '', 'secret_key' => '');
    if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER')) {
        return $settings;
    }
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . (defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4'),
            DB_USER,
            defined('DB_PASS') ? DB_PASS : ''
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $rows = $pdo->query(
            "SELECT setting_key, setting_value FROM huli_settings
             WHERE setting_key IN ('turnstile_enabled','turnstile_site_key','turnstile_secret_key')"
        )->fetchAll(PDO::FETCH_KEY_PAIR);
        $settings['site_key'] = isset($rows['turnstile_site_key']) ? trim((string)$rows['turnstile_site_key']) : '';
        $settings['secret_key'] = isset($rows['turnstile_secret_key']) ? trim((string)$rows['turnstile_secret_key']) : '';
        $switch_on = isset($rows['turnstile_enabled']) && trim((string)$rows['turnstile_enabled']) === '1';
        $settings['enabled'] = $switch_on && $settings['site_key'] !== '' && $settings['secret_key'] !== '';
    } catch (Exception $e) {
    }
    return $settings;
}

function huli_turnstile_enabled()
{
    $settings = huli_turnstile_settings();
    return $settings['enabled'];
}

function huli_turnstile_keys()
{
    $settings = huli_turnstile_settings();
    return array('site_key' => $settings['site_key'], 'secret_key' => $settings['secret_key']);
}

/**
 * 判断 sitekey 与 secret 是否属于同一套（都是测试密钥或都是正式密钥）。
 * 测试与正式混用时 Cloudflare 必然返回 invalid-input-response，这是最常见的配置错误。
 */
function huli_turnstile_key_pair_mismatch()
{
    $keys = huli_turnstile_keys();
    if ($keys['site_key'] === '' || $keys['secret_key'] === '') {
        return false;
    }
    $site_is_test = strpos(HULI_TURNSTILE_TEST_SITE_KEYS, '|' . $keys['site_key'] . '|') !== false;
    $secret_is_test = strpos(HULI_TURNSTILE_TEST_SECRET_KEYS, '|' . $keys['secret_key'] . '|') !== false;
    return $site_is_test !== $secret_is_test;
}

/**
 * 把 Cloudflare 的 error-codes 翻译成可直接展示给用户、同时便于管理员定位的提示。
 */
function huli_turnstile_error_message($codes)
{
    $codes = (array)$codes;
    $map = array(
        'missing-input-secret'   => '人机验证配置有误：后台未填写 Turnstile Secret Key',
        'invalid-input-secret'   => '人机验证配置有误：Turnstile Secret Key 不正确，请核对后台填写的密钥',
        'missing-input-response' => '未检测到人机验证结果，请完成验证后再提交',
        'invalid-input-response' => '人机验证未通过：验证令牌无效。请确认后台的 Site Key 与 Secret Key 来自同一个 Turnstile 站点，且当前域名已在 Cloudflare 中授权',
        'bad-request'            => '人机验证请求格式错误，请稍后重试',
        'timeout-or-duplicate'   => '人机验证已过期或被重复提交，请重新完成验证后再提交',
        'internal-error'         => 'Cloudflare 人机验证服务暂时不可用，请稍后重试',
    );
    foreach ($codes as $code) {
        if (isset($map[$code])) {
            return $map[$code];
        }
    }
    return '人机验证失败，请重新完成验证后重试';
}

/**
 * 记录并返回最近一次校验失败的原因，供各业务入口直接回显。
 */
function huli_turnstile_last_error($message = null)
{
    static $last = '';
    if ($message !== null) {
        $last = (string)$message;
    }
    return $last;
}

function huli_turnstile_siteverify_request($body)
{
    $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
        $result = curl_exec($ch);
        if ($result === false) {
            error_log('[turnstile] siteverify 网络错误: ' . curl_error($ch));
        }
        curl_close($ch);
        return $result;
    }
    $context = stream_context_create(array('http' => array(
        'method' => 'POST',
        'header' => 'Content-Type: application/x-www-form-urlencoded',
        'content' => $body,
        'timeout' => 10
    )));
    return @file_get_contents($url, false, $context);
}

/**
 * 服务端校验单个 token。返回 ['ok'=>bool, 'reason'=>'', 'raw'=>[...]]。
 * raw 始终包含 Cloudflare 的原始响应字段（success/error-codes/hostname 等）。
 * 即使 Turnstile 未启用，也会返回 ok=true（无人机验证时不参与业务）。
 */
function huli_turnstile_verify_token($token, &$reason = null)
{
    $reason = '';
    huli_turnstile_last_error('');
    if (!huli_turnstile_enabled()) {
        return array('ok' => true, 'reason' => '', 'raw' => array('disabled' => true));
    }
    if (huli_turnstile_key_pair_mismatch()) {
        $reason = '人机验证配置有误：Site Key 与 Secret Key 一个是测试密钥、一个是正式密钥，两者必须成对使用';
        huli_turnstile_last_error($reason);
        error_log('[turnstile] 配置错误：测试密钥与正式密钥混用');
        return array('ok' => false, 'reason' => $reason, 'raw' => array('config' => 'key_pair_mismatch'));
    }
    $token = trim((string)$token);
    if ($token === '') {
        $reason = '未检测到人机验证结果，请等待验证组件加载完成并通过验证后再提交';
        huli_turnstile_last_error($reason);
        error_log('[turnstile] 未收到 cf-turnstile-response token');
        return array('ok' => false, 'reason' => $reason, 'raw' => array('error-codes' => array('missing-input-response')));
    }
    if (strlen($token) > 2048) {
        $reason = '人机验证令牌格式异常，请刷新页面重试';
        huli_turnstile_last_error($reason);
        error_log('[turnstile] token 超过 2048 字符上限');
        return array('ok' => false, 'reason' => $reason, 'raw' => array('error-codes' => array('invalid-input-response')));
    }
    $keys = huli_turnstile_keys();
    $post_data = array(
        'secret' => $keys['secret_key'],
        'response' => $token,
        'idempotency_key' => huli_turnstile_uuid_v4()
    );
    $body = http_build_query($post_data);
    $data = null;
    $raw_response = '';
    for ($attempt = 1; $attempt <= 2; $attempt++) {
        $raw_response = huli_turnstile_siteverify_request($body);
        if ($raw_response === false || $raw_response === '') {
            error_log('[turnstile] siteverify 请求失败（无响应），第 ' . $attempt . ' 次');
            $data = array('success' => false, 'error-codes' => array('internal-error'));
            continue;
        }
        $decoded = json_decode($raw_response, true);
        if (!is_array($decoded)) {
            error_log('[turnstile] siteverify 响应无法解析: ' . substr((string)$raw_response, 0, 200));
            $data = array('success' => false, 'error-codes' => array('internal-error'));
            continue;
        }
        $data = $decoded;
        if (!empty($data['success'])) {
            break;
        }
        $codes = isset($data['error-codes']) ? (array)$data['error-codes'] : array();
        if (!in_array('internal-error', $codes, true)) {
            break;
        }
    }
    if (!empty($data['success'])) {
        return array('ok' => true, 'reason' => '', 'raw' => is_array($data) ? $data : array());
    }
    $codes = isset($data['error-codes']) ? (array)$data['error-codes'] : array();
    $reason = huli_turnstile_error_message($codes);
    huli_turnstile_last_error($reason);
    error_log('[turnstile] siteverify 验证失败 error-codes: ' . ($codes ? implode(',', $codes) : 'unknown'));
    return array('ok' => false, 'reason' => $reason, 'raw' => is_array($data) ? $data : array('error-codes' => $codes));
}

function huli_turnstile_verify(&$reason = null)
{
    $result = huli_turnstile_verify_token(isset($_POST['cf-turnstile-response']) ? $_POST['cf-turnstile-response'] : '', $reason);
    return $result['ok'];
}

function huli_turnstile_uuid_v4()
{
    try {
        $bytes = random_bytes(16);
    } catch (Exception $e) {
        $bytes = '';
        for ($i = 0; $i < 16; $i++) {
            $bytes .= chr(mt_rand(0, 255));
        }
    }
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

/**
 * 使用调用方传入的 secret 与 token 调用 siteverify，返回 Cloudflare 原始 JSON 解码结果。
 * 与 huli_turnstile_verify_token 的区别：不读取数据库密钥，用于后台端到端测试（表单可能未保存）。
 */
function huli_turnstile_siteverify_raw($secret, $token)
{
    $secret = trim((string)$secret);
    $token = trim((string)$token);
    $post_data = array(
        'secret' => $secret,
        'response' => $token,
        'idempotency_key' => huli_turnstile_uuid_v4()
    );
    $body = http_build_query($post_data);
    $data = null;
    for ($attempt = 1; $attempt <= 2; $attempt++) {
        $raw_response = huli_turnstile_siteverify_request($body);
        if ($raw_response === false || $raw_response === '') {
            $data = array('success' => false, 'error-codes' => array('internal-error'));
            continue;
        }
        $decoded = json_decode($raw_response, true);
        if (!is_array($decoded)) {
            $data = array('success' => false, 'error-codes' => array('internal-error'));
            continue;
        }
        $data = $decoded;
        if (!empty($data['success'])) {
            break;
        }
        $codes = isset($data['error-codes']) ? (array)$data['error-codes'] : array();
        if (!in_array('internal-error', $codes, true)) {
            break;
        }
    }
    return is_array($data) ? $data : array('success' => false, 'error-codes' => array('internal-error'));
}

/**
 * 检查一组密钥（不读数据库，用于后台检测表单当前填写的值）。
 * 返回 array('ok'=>bool, 'msg'=>string, 'site_is_test'=>bool, 'secret_is_test'=>bool)。
 */
function huli_turnstile_check_keys($site, $secret)
{
    $site = trim((string)$site);
    $secret = trim((string)$secret);
    if ($site === '' || $secret === '') {
        return array('ok' => false, 'msg' => '请先填写 Site Key 与 Secret Key 再检测。', 'site_is_test' => false, 'secret_is_test' => false);
    }
    $site_is_test = strpos(HULI_TURNSTILE_TEST_SITE_KEYS, '|' . $site . '|') !== false;
    $secret_is_test = strpos(HULI_TURNSTILE_TEST_SECRET_KEYS, '|' . $secret . '|') !== false;
    if (!preg_match('/^[0-9A-Za-z_-]{20,80}$/', $site)) {
        return array('ok' => false, 'msg' => '检测失败：Site Key 格式不合法。Cloudflare Site Key 形如 "0x4AAAAAA..."，请从 Cloudflare 后台重新复制。', 'site_is_test' => $site_is_test, 'secret_is_test' => $secret_is_test);
    }
    if (!preg_match('/^[0-9A-Za-z_-]{20,80}$/', $secret)) {
        return array('ok' => false, 'msg' => '检测失败：Secret Key 格式不合法。Cloudflare Secret Key 形如 "0x4AAAAAA..."，请从 Cloudflare 后台重新复制（密钥首尾不要带空格或换行）。', 'site_is_test' => $site_is_test, 'secret_is_test' => $secret_is_test);
    }
    if ($site_is_test !== $secret_is_test) {
        return array('ok' => false, 'msg' => '检测失败：Site Key 与 Secret Key 一个是测试密钥、一个是正式密钥，两者必须成对使用。Cloudflare 会直接返回 invalid-input-response。', 'site_is_test' => $site_is_test, 'secret_is_test' => $secret_is_test);
    }
    $msg = $site_is_test
        ? '配置校验通过：检测到 Cloudflare 官方测试密钥。Cloudflare 不会做真实校验，仅供本地联调；上线前请替换为正式密钥。'
        : '配置校验通过：密钥成对、格式合法。最终能否通过验证还取决于当前访问域名是否已在 Cloudflare 的 Hostname Management 中授权，请用下方「端到端测试」确认。';
    return array('ok' => true, 'msg' => $msg, 'site_is_test' => $site_is_test, 'secret_is_test' => $secret_is_test);
}

/**
 * 仅输出 Turnstile 运行时脚本与 api.js（不渲染任何 widget），且全局只输出一次。
 * 用于后台端到端测试等需要在浏览器端手动控制渲染的场景。
 */
function huli_turnstile_assets_html()
{
    static $script_printed = false;
    if ($script_printed) {
        return '';
    }
    $script_printed = true;
    return '<script>' . huli_turnstile_runtime_js() . '</script>'
        . '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=huliOnTurnstileLoad" async defer></script>';
}

/**
 * 渲染 widget。使用显式渲染并接管 token 生命周期，解决三类线上问题：
 * 1. token 超过 300 秒过期后仍被提交，导致 timeout-or-duplicate；
 * 2. 挑战尚未完成用户就点提交，导致后端收不到 token；
 * 3. 域名未授权、sitekey 无效等前端错误此前完全静默，无法定位。
 *
 * $force=true 时无视 Turnstile 启用状态也输出 widget，用于后台端到端测试。
 */
function huli_turnstile_widget_html($force = false)
{
    static $widget_index = 0;
    if (!$force && !huli_turnstile_enabled()) {
        return '';
    }
    $widget_index++;
    $widget_id = 'huli-turnstile-' . $widget_index;
    $site_key = huli_turnstile_keys()['site_key'];
    $html = '<div id="' . $widget_id . '" class="huli-turnstile mb-3"></div>';
    $html .= huli_turnstile_assets_html();
    $html .= '<script>window.huliRenderTurnstile(' . json_encode($widget_id) . ',' . json_encode($site_key) . ');</script>';
    return $html;
}

function huli_turnstile_runtime_js()
{
    return <<<'JS'
(function(){
if(window.huliTurnstile){return;}
var TOKEN_MAX_AGE=240000;
var READY_TIMEOUT=25000;
var CLIENT_ERRORS={
"110100":"人机验证配置有误：Site Key 无效，请核对后台填写的 Turnstile Site Key",
"110110":"人机验证配置有误：Site Key 不存在，请核对后台填写的 Turnstile Site Key",
"110200":"人机验证配置有误：当前域名未在 Cloudflare Turnstile 中授权，请在 Hostname Management 添加本站域名",
"110600":"人机验证超时，请重新完成验证（若反复出现请检查本机时间是否准确）",
"110620":"人机验证已超时，请重新完成验证",
"200100":"人机验证失败：本机时间不正确或页面被缓存，请校准系统时间后刷新重试",
"200500":"人机验证组件加载失败，请确认网络未拦截 challenges.cloudflare.com",
"400020":"人机验证配置有误：Site Key 无效，请核对后台填写的 Turnstile Site Key",
"400070":"人机验证配置有误：该 Site Key 已被停用，请在 Cloudflare 后台检查"
};
var FATAL_ERRORS={"110100":1,"110110":1,"110200":1,"200100":1,"400020":1,"400070":1};
var SDK_URL="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=huliOnTurnstileLoad";
var SDK_MAX_RELOADS=3;
var T={widgets:{},pending:{},tokens:{},readyCallbacks:[],lastError:"",fatal:false,sdkFailed:false,sdkReloads:0,sdkTimer:null,sdkNetworkError:0,sdkLoadedOnce:false};
window.huliTurnstile=T;
function sdkReady(){return !!(window.turnstile&&typeof window.turnstile.render==="function");}
function flushReadyCallbacks(){
var cbs=T.readyCallbacks;T.readyCallbacks=[];
for(var i=0;i<cbs.length;i++){try{cbs[i]();}catch(e){}}
}
function loadSdkScript(cacheBust){
var src=cacheBust?(SDK_URL+"&cb="+Date.now()):SDK_URL;
var sc=document.createElement("script");
sc.src=src;sc.async=true;sc.defer=true;sc.setAttribute("data-huli-sdk","1");
sc.onload=function(){T.sdkLoadedOnce=true;};
sc.onerror=function(){T.sdkNetworkError=(T.sdkNetworkError||0)+1;};
(document.head||document.documentElement).appendChild(sc);
}
function ensureSdkLoaded(){
if(sdkReady()){return;}
var hasScript=document.querySelector('script[data-huli-sdk="1"],script[src^="https://challenges.cloudflare.com/turnstile/"]');
if(!hasScript){loadSdkScript(false);}
if(T.sdkTimer){return;}
T.sdkTimer=setInterval(function(){
if(sdkReady()){
clearInterval(T.sdkTimer);T.sdkTimer=null;
T.sdkFailed=false;
window.huliTryRenderTurnstiles();
flushReadyCallbacks();
return;
}
if(T.sdkReloads>=SDK_MAX_RELOADS){
clearInterval(T.sdkTimer);T.sdkTimer=null;
if(!T.sdkFailed){T.sdkFailed=true;}
flushReadyCallbacks();
return;
}
T.sdkReloads++;
var old=document.querySelector('script[data-huli-sdk="1"],script[src^="https://challenges.cloudflare.com/turnstile/"]');
if(old){old.remove();}
loadSdkScript(true);
},8000);
}
window.huliReloadTurnstileSdk=function(){if(T.sdkTimer){clearInterval(T.sdkTimer);T.sdkTimer=null;}T.sdkFailed=false;T.sdkReloads=0;T.sdkNetworkError=0;T.sdkLoadedOnce=false;var old=document.querySelector('script[data-huli-sdk="1"],script[src^="https://challenges.cloudflare.com/turnstile/"]');if(old){old.remove();}loadSdkScript(false);ensureSdkLoaded();};
window.huliSdkDiagnostics=function(){return {ready:sdkReady(),attempts:T.sdkReloads,networkError:T.sdkNetworkError||0,loadedOnce:!!T.sdkLoadedOnce,failed:!!T.sdkFailed};};
function describeError(code){
code=String(code||"");
if(CLIENT_ERRORS[code]){return CLIENT_ERRORS[code];}
if(code.charAt(0)==="3"||code.charAt(0)==="6"){return "人机验证未通过（错误码 "+code+"），请重试或更换网络环境";}
return code?("人机验证出错（错误码 "+code+"），请重试"):"人机验证出错，请重试";
}
function syncInputs(token){
var inputs=document.getElementsByName("cf-turnstile-response");
for(var i=0;i<inputs.length;i++){inputs[i].value=token;}
}
function freshToken(){
for(var id in T.tokens){
if(!Object.prototype.hasOwnProperty.call(T.tokens,id)){continue;}
var entry=T.tokens[id];
if(entry&&entry.token&&(Date.now()-entry.ts)<TOKEN_MAX_AGE){return entry.token;}
}
return "";
}
window.huliGetTurnstileResponse=function(){
var token=freshToken();
if(token){syncInputs(token);}
return token;
};
window.huliTurnstileError=function(){return T.lastError;};
window.huliRenderOneTurnstile=function(widgetId,siteKey){
if(!sdkReady()||T.widgets[widgetId]!==undefined){return;}
var container=document.getElementById(widgetId);
if(!container){return;}
try{
T.widgets[widgetId]=window.turnstile.render("#"+widgetId,{
sitekey:siteKey,
"refresh-expired":"auto",
retry:"auto",
"retry-interval":2000,
callback:function(token){
T.lastError="";
T.fatal=false;
T.tokens[widgetId]={token:token,ts:Date.now()};
syncInputs(token);
},
"expired-callback":function(){
delete T.tokens[widgetId];
syncInputs("");
},
"timeout-callback":function(){
delete T.tokens[widgetId];
syncInputs("");
},
"error-callback":function(code){
delete T.tokens[widgetId];
syncInputs("");
T.lastError=describeError(code);
if(FATAL_ERRORS[String(code||"")]){
T.fatal=true;
var container=document.getElementById(widgetId);
if(container&&container.parentNode&&!container.parentNode.querySelector(".huli-turnstile-fatal")){
var banner=document.createElement("div");
banner.className="alert alert-danger small mt-2 mb-2 huli-turnstile-fatal";
banner.textContent=describeError(code);
container.parentNode.insertBefore(banner,container);
}
}
return true;
}
});
}catch(e){
T.lastError="人机验证组件初始化失败，请刷新页面重试";
}
};
window.huliTryRenderTurnstiles=function(){
if(!sdkReady()){return;}
for(var id in T.pending){
if(Object.prototype.hasOwnProperty.call(T.pending,id)){window.huliRenderOneTurnstile(id,T.pending[id]);}
}
};
window.huliOnTurnstileLoad=function(){window.huliTryRenderTurnstiles();flushReadyCallbacks();};
window.huliTurnstileReady=function(cb){
if(typeof cb!=="function"){return;}
if(sdkReady()){try{cb();}catch(e){}}else{T.readyCallbacks.push(cb);ensureSdkLoaded();}
};
window.huliRenderTurnstile=function(widgetId,siteKey){
T.pending[widgetId]=siteKey;
window.huliTryRenderTurnstiles();
if(!sdkReady()){ensureSdkLoaded();}
};
/**
 * 提交守卫：保证提交时携带的 token 一定是新鲜且未使用过的。
 * onReady(token) 在拿到可用 token 后调用；onFail(message) 在超时或出错时调用。
 */
window.huliTurnstileEnsureToken=function(onReady,onFail){
if(!document.querySelector(".huli-turnstile")){onReady("");return;}
var token=window.huliGetTurnstileResponse();
if(token){onReady(token);return;}
if(T.fatal){onFail(T.lastError||"人机验证组件加载失败，请刷新页面重试");return;}
if(sdkReady()){
for(var id in T.widgets){
if(!Object.prototype.hasOwnProperty.call(T.widgets,id)){continue;}
try{
if(window.turnstile.isExpired&&window.turnstile.isExpired(T.widgets[id])){window.turnstile.reset(T.widgets[id]);}
}catch(e){}
}
}
var waited=0;
var timer=setInterval(function(){
waited+=200;
var ready=window.huliGetTurnstileResponse();
if(ready){clearInterval(timer);onReady(ready);return;}
if(T.fatal){clearInterval(timer);onFail(T.lastError||"人机验证不可用，请刷新页面重试");return;}
if(waited>=READY_TIMEOUT){
clearInterval(timer);
onFail(T.lastError||"人机验证尚未完成，请点击验证组件完成验证后再提交");
}
},200);
};
/**
 * 一次性消费 token：提交后立即重置，避免同一 token 被重复提交触发 timeout-or-duplicate。
 */
window.huliTurnstileConsumed=function(){
T.tokens={};
syncInputs("");
if(!sdkReady()){return;}
for(var id in T.widgets){
if(!Object.prototype.hasOwnProperty.call(T.widgets,id)){continue;}
try{window.turnstile.reset(T.widgets[id]);}catch(e){}
}
};
})();
JS;
}
