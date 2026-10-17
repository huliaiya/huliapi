<?php
// QQ Connect (QQ 互联) OAuth 2.0 helper.
// Flow:
//   1. qq_connect_auth_url() -> redirect user to graph.qq.com authorize page
//   2. callback receives ?code=... -> qq_connect_access_token()
//   3. qq_connect_openid() -> per-app openid for the QQ account
//   4. qq_connect_userinfo() -> nickname / avatar (optional)
// Requires settings: qqconnect_enabled, qqconnect_appid, qqconnect_appkey
// The openid is stored in huli_users.qq_openid / huli_admins.qq_openid.

function qq_connect_setting(string $key, string $default = ''): string {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            if (!defined('DB_HOST') && file_exists(dirname(__DIR__) . '/config.php')) {
                require_once dirname(__DIR__) . '/config.php';
            }
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';port=' . (defined('DB_PORT') ? DB_PORT : 3306)
                . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
                DB_USER,
                DB_PASS
            );
            $rows = $pdo->query(
                "SELECT setting_key, setting_value FROM huli_settings WHERE setting_key IN ('qqconnect_enabled','qqconnect_appid','qqconnect_appkey','site_url')"
            )->fetchAll(PDO::FETCH_KEY_PAIR);
            $cache = is_array($rows) ? $rows : [];
        } catch (Throwable $e) {
            $cache = [];
        }
    }
    return isset($cache[$key]) && $cache[$key] !== '' ? (string)$cache[$key] : $default;
}

function qq_connect_enabled(): bool {
    return qq_connect_setting('qqconnect_enabled') === '1'
        && qq_connect_setting('qqconnect_appid') !== ''
        && qq_connect_setting('qqconnect_appkey') !== '';
}

function qq_connect_base_url(): string {
    $configured = qq_connect_setting('site_url');
    if ($configured !== '') {
        return rtrim($configured, '/');
    }
    $scheme = 'http';
    if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
        || !empty($_SERVER['HTTP_X_FORWARDED_PROTO'])
        || !empty($_SERVER['HTTP_X_FORWARDED_SSL'])) {
        $scheme = 'https';
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $fw = strtolower(trim(explode(',', (string)$_SERVER['HTTP_X_FORWARDED_PROTO'])[0]));
        if ($fw === 'https' || $fw === 'http') {
            $scheme = $fw;
        }
    }
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

function qq_connect_redirect_uri(string $scope): string {
    $path = $scope === 'admin'
        ? '/admin/qq_callback.php'
        : '/template/user/huli/qq_callback.php';
    return qq_connect_base_url() . $path;
}

function qq_connect_random_state(): string {
    $state = bin2hex(random_bytes(16));
    $_SESSION['qq_connect_state'] = $state;
    $_SESSION['qq_connect_state_at'] = time();
    return $state;
}

function qq_connect_take_action(): string {
    $action = $_SESSION['qq_connect_action'] ?? 'login';
    unset($_SESSION['qq_connect_action']);
    return $action === 'bind' ? 'bind' : 'login';
}

function qq_connect_check_state(string $state): bool {
    $saved = $_SESSION['qq_connect_state'] ?? '';
    $at = (int)($_SESSION['qq_connect_state_at'] ?? 0);
    unset($_SESSION['qq_connect_state'], $_SESSION['qq_connect_state_at']);
    if ($saved === '' || $state === '' || !hash_equals($saved, $state)) {
        return false;
    }
    if ($at > 0 && time() - $at > 600) {
        return false;
    }
    return true;
}

function qq_connect_auth_url(string $scope, string $action = 'login'): string {
    $state = qq_connect_random_state();
    if ($action === 'bind') {
        $_SESSION['qq_connect_action'] = 'bind';
    } else {
        unset($_SESSION['qq_connect_action']);
    }
    return 'https://graph.qq.com/oauth2.0/authorize'
        . '?response_type=code'
        . '&client_id=' . rawurlencode(qq_connect_setting('qqconnect_appid'))
        . '&redirect_uri=' . rawurlencode(qq_connect_redirect_uri($scope))
        . '&state=' . rawurlencode($state)
        . '&scope=get_user_info';
}

function qq_connect_http_get(string $url): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_HTTPHEADER => [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ],
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($body === false) {
        return ['ok' => false, 'error' => $err !== '' ? $err : '请求失败', 'body' => '', 'code' => $code];
    }
    return ['ok' => $code >= 200 && $code < 400, 'error' => '', 'body' => (string)$body, 'code' => $code];
}

// QQ returns either JSON (fmt=json) or querystring-ish text. Normalize to array.
function qq_connect_parse_response(string $raw): array {
    $raw = trim($raw);
    if ($raw === '') {
        return [];
    }
    if ($raw[0] === '{') {
        $json = json_decode($raw, true);
        return is_array($json) ? $json : [];
    }
    if (preg_match('/callback\(\s*(\{.*\})\s*\)\s*;?/s', $raw, $m)) {
        $json = json_decode($m[1], true);
        return is_array($json) ? $json : [];
    }
    $out = [];
    parse_str($raw, $out);
    return is_array($out) ? $out : [];
}

function qq_connect_access_token(string $code, string $scope): array {
    $url = 'https://graph.qq.com/oauth2.0/token'
        . '?grant_type=authorization_code'
        . '&client_id=' . rawurlencode(qq_connect_setting('qqconnect_appid'))
        . '&client_secret=' . rawurlencode(qq_connect_setting('qqconnect_appkey'))
        . '&code=' . rawurlencode($code)
        . '&redirect_uri=' . rawurlencode(qq_connect_redirect_uri($scope))
        . '&fmt=json';
    $r = qq_connect_http_get($url);
    if (!$r['ok']) {
        return ['ok' => false, 'error' => '换取 access_token 失败：' . $r['error']];
    }
    $data = qq_connect_parse_response($r['body']);
    if (empty($data['access_token'])) {
        return ['ok' => false, 'error' => '换取 access_token 失败：' . ($data['error_description'] ?? $r['body'])];
    }
    return ['ok' => true, 'access_token' => (string)$data['access_token']];
}

function qq_connect_openid(string $access_token): array {
    $url = 'https://graph.qq.com/oauth2.0/me?access_token=' . rawurlencode($access_token) . '&fmt=json';
    $r = qq_connect_http_get($url);
    if (!$r['ok']) {
        return ['ok' => false, 'error' => '获取 openid 失败：' . $r['error']];
    }
    $data = qq_connect_parse_response($r['body']);
    if (empty($data['openid'])) {
        return ['ok' => false, 'error' => '获取 openid 失败：' . ($data['error_description'] ?? $r['body'])];
    }
    return ['ok' => true, 'openid' => (string)$data['openid']];
}

function qq_connect_userinfo(string $access_token, string $openid): array {
    $url = 'https://graph.qq.com/user/get_user_info'
        . '?access_token=' . rawurlencode($access_token)
        . '&oauth_consumer_key=' . rawurlencode(qq_connect_setting('qqconnect_appid'))
        . '&openid=' . rawurlencode($openid)
        . '&fmt=json';
    $r = qq_connect_http_get($url);
    if (!$r['ok']) {
        return ['ok' => false, 'error' => $r['error']];
    }
    $data = qq_connect_parse_response($r['body']);
    if (isset($data['ret']) && (int)$data['ret'] !== 0) {
        return ['ok' => false, 'error' => $data['msg'] ?? '获取用户信息失败'];
    }
    return ['ok' => true, 'nickname' => (string)($data['nickname'] ?? ''), 'avatar' => (string)($data['figureurl_qq_2'] ?? ($data['figureurl_qq_1'] ?? ''))];
}
