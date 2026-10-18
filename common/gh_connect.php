<?php
// GitHub OAuth 2.0 helper.
// Flow:
//   1. gh_connect_auth_url()      -> redirect to github.com/login/oauth/authorize
//   2. callback ?code=...         -> gh_connect_access_token()
//   3. gh_connect_user()          -> id / login / name / avatar
//   4. gh_connect_emails()        -> primary verified email (optional)
// Settings: ghconnect_enabled, ghconnect_client_id, ghconnect_client_secret, site_url
// The GitHub numeric id is stored in huli_users.gh_id / huli_admins.gh_id.

function gh_connect_setting(string $key, string $default = ''): string {
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
                "SELECT setting_key, setting_value FROM huli_settings WHERE setting_key IN ('ghconnect_enabled','ghconnect_client_id','ghconnect_client_secret','site_url')"
            )->fetchAll(PDO::FETCH_KEY_PAIR);
            $cache = is_array($rows) ? $rows : [];
        } catch (Throwable $e) {
            $cache = [];
        }
    }
    return isset($cache[$key]) && $cache[$key] !== '' ? (string)$cache[$key] : $default;
}

function gh_connect_enabled(): bool {
    return gh_connect_setting('ghconnect_enabled') === '1'
        && gh_connect_setting('ghconnect_client_id') !== ''
        && gh_connect_setting('ghconnect_client_secret') !== '';
}

function gh_connect_base_url(): string {
    $configured = gh_connect_setting('site_url');
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

function gh_connect_redirect_uri(string $scope): string {
    $path = $scope === 'admin'
        ? '/admin/gh_callback.php'
        : '/template/user/huli/gh_callback.php';
    return gh_connect_base_url() . $path;
}

function gh_connect_random_state(): string {
    $state = bin2hex(random_bytes(16));
    $_SESSION['gh_connect_state'] = $state;
    $_SESSION['gh_connect_state_at'] = time();
    return $state;
}

function gh_connect_check_state(string $state): bool {
    $saved = $_SESSION['gh_connect_state'] ?? '';
    $at = (int)($_SESSION['gh_connect_state_at'] ?? 0);
    unset($_SESSION['gh_connect_state'], $_SESSION['gh_connect_state_at']);
    if ($saved === '' || $state === '' || !hash_equals($saved, $state)) {
        return false;
    }
    if ($at > 0 && time() - $at > 600) {
        return false;
    }
    return true;
}

function gh_connect_take_action(): string {
    $action = $_SESSION['gh_connect_action'] ?? 'login';
    unset($_SESSION['gh_connect_action']);
    return $action === 'bind' ? 'bind' : 'login';
}

function gh_connect_auth_url(string $scope, string $action = 'login'): string {
    $state = gh_connect_random_state();
    if ($action === 'bind') {
        $_SESSION['gh_connect_action'] = 'bind';
    } else {
        unset($_SESSION['gh_connect_action']);
    }
    return 'https://github.com/login/oauth/authorize'
        . '?client_id=' . rawurlencode(gh_connect_setting('ghconnect_client_id'))
        . '&redirect_uri=' . rawurlencode(gh_connect_redirect_uri($scope))
        . '&state=' . rawurlencode($state)
        . '&scope=' . rawurlencode('read:user user:email')
        . '&allow_signup=true';
}

function gh_connect_http_request(string $method, string $url, array $headers = [], array $body = null): array {
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_HTTPHEADER => array_merge([
            'User-Agent: huliapi-github-oauth',
            'Accept: application/json',
        ], $headers),
    ];
    if (strtoupper($method) === 'POST') {
        $opts[CURLOPT_POST] = true;
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = http_build_query($body);
        }
    }
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($resp === false) {
        return ['ok' => false, 'error' => $err !== '' ? $err : '请求失败', 'body' => '', 'code' => $code];
    }
    return ['ok' => $code >= 200 && $code < 300, 'error' => '', 'body' => (string)$resp, 'code' => $code];
}

function gh_connect_access_token(string $code, string $scope): array {
    $r = gh_connect_http_request('POST', 'https://github.com/login/oauth/access_token', [], [
        'client_id' => gh_connect_setting('ghconnect_client_id'),
        'client_secret' => gh_connect_setting('ghconnect_client_secret'),
        'code' => $code,
        'redirect_uri' => gh_connect_redirect_uri($scope),
    ]);
    if (!$r['ok']) {
        return ['ok' => false, 'error' => '换取 access_token 失败：' . ($r['error'] !== '' ? $r['error'] : ('HTTP ' . $r['code']))];
    }
    $data = json_decode($r['body'], true);
    if (!is_array($data) || empty($data['access_token'])) {
        $msg = is_array($data) ? ($data['error_description'] ?? $data['error'] ?? $r['body']) : $r['body'];
        return ['ok' => false, 'error' => '换取 access_token 失败：' . $msg];
    }
    return ['ok' => true, 'access_token' => (string)$data['access_token']];
}

function gh_connect_user(string $access_token): array {
    $r = gh_connect_http_request('GET', 'https://api.github.com/user', [
        'Authorization: token ' . $access_token,
        'Accept: application/vnd.github+json',
    ]);
    if (!$r['ok']) {
        return ['ok' => false, 'error' => '获取 GitHub 用户信息失败：HTTP ' . $r['code']];
    }
    $data = json_decode($r['body'], true);
    if (!is_array($data) || empty($data['id'])) {
        return ['ok' => false, 'error' => '获取 GitHub 用户信息失败'];
    }
    return [
        'ok' => true,
        'id' => (string)$data['id'],
        'login' => (string)($data['login'] ?? ''),
        'name' => (string)($data['name'] ?? ''),
        'avatar' => (string)($data['avatar_url'] ?? ''),
        'email' => (string)($data['email'] ?? ''),
    ];
}

function gh_connect_primary_email(string $access_token): string {
    $r = gh_connect_http_request('GET', 'https://api.github.com/user/emails', [
        'Authorization: token ' . $access_token,
        'Accept: application/vnd.github+json',
    ]);
    if (!$r['ok']) {
        return '';
    }
    $list = json_decode($r['body'], true);
    if (!is_array($list)) {
        return '';
    }
    foreach ($list as $item) {
        if (!empty($item['primary']) && !empty($item['verified']) && !empty($item['email'])) {
            return (string)$item['email'];
        }
    }
    return '';
}
