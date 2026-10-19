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
    // Prefer an explicit forwarded proto when a reverse proxy provides one.
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $fw = strtolower(trim(explode(',', (string)$_SERVER['HTTP_X_FORWARDED_PROTO'])[0]));
        if ($fw === 'https' || $fw === 'http') {
            return $fw . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        }
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_SSL']) !== 'off') {
        return 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
    if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)) {
        return 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
    // No protocol hints (e.g. behind a proxy that strips headers): assume https,
    // which is the correct choice for any real deployment.
    return 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

// Single callback entry shared by user and admin.
// GitHub OAuth Apps allow only one callback URL, so both scopes must use
// the same path; the originating scope is carried in the session instead.
function gh_connect_redirect_uri(string $scope = ''): string {
    return gh_connect_base_url() . '/admin/gh_callback.php';
}

function gh_connect_pdo(): ?PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    try {
        if (!defined('DB_HOST')) {
            $config = dirname(__DIR__) . '/config.php';
            if (file_exists($config)) {
                require_once $config;
            }
        }
        if (!defined('DB_HOST')) {
            return null;
        }
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';port=' . (defined('DB_PORT') ? DB_PORT : 3306)
            . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $pdo->exec("CREATE TABLE IF NOT EXISTS `huli_oauth_states` (
            `state` char(64) NOT NULL,
            `scope` varchar(16) NOT NULL DEFAULT 'user',
            `action` varchar(16) NOT NULL DEFAULT 'login',
            `account_id` int(11) NOT NULL DEFAULT 0,
            `created_at` int(10) unsigned NOT NULL,
            PRIMARY KEY (`state`),
            KEY `idx_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        return $pdo;
    } catch (Throwable $e) {
        error_log('[gh_connect] 数据库不可用: ' . $e->getMessage());
        return null;
    }
}

// Older installs predate the GitHub column, and a fresh install used to miss it
// in install.sql. Make sure the backing column and unique index exist before use.
function gh_connect_ensure_account_schema(PDO $pdo, string $table): void {
    if (!in_array($table, array('huli_users', 'huli_admins'), true)) {
        return;
    }
    static $checked = array();
    if (!empty($checked[$table])) {
        return;
    }
    $checked[$table] = true;
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE 'gh_id'");
        $stmt->execute();
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `gh_id` varchar(32) DEFAULT NULL COMMENT 'GitHub 数字ID'");
        }
        $idx = $pdo->prepare("SHOW INDEX FROM `$table` WHERE Key_name = 'uniq_gh_id'");
        $idx->execute();
        if (!$idx->fetch()) {
            try {
                $pdo->exec("ALTER TABLE `$table` ADD UNIQUE KEY `uniq_gh_id` (`gh_id`)");
            } catch (Throwable $e) {
                error_log('[gh_connect] 创建 gh_id 唯一索引失败（可能存在重复绑定）: ' . $e->getMessage());
            }
        }
    } catch (Throwable $e) {
        error_log('[gh_connect] 检查 gh_id 列失败: ' . $e->getMessage());
    }
}

// OAuth state is authoritative in the database: the GitHub round trip leaves
// the site and may land in a different browser session, so session-only storage
// silently loses the pending login/bind request.
function gh_connect_random_state(string $scope, string $action, int $account_id = 0): string {
    $state = bin2hex(random_bytes(16));
    $pdo = gh_connect_pdo();
    if ($pdo) {
        try {
            $pdo->prepare("DELETE FROM huli_oauth_states WHERE created_at < ?")->execute([time() - 900]);
            $pdo->prepare("INSERT INTO huli_oauth_states (state, scope, action, account_id, created_at) VALUES (?, ?, ?, ?, ?)")
                ->execute([$state, $scope === 'admin' ? 'admin' : 'user', $action === 'bind' ? 'bind' : 'login', $account_id, time()]);
        } catch (Throwable $e) {
            error_log('[gh_connect] 写入 state 失败: ' . $e->getMessage());
        }
    }
    $_SESSION['gh_connect_state'] = $state;
    $_SESSION['gh_connect_state_at'] = time();
    return $state;
}

function gh_connect_consume_state(string $state): ?array {
    $state = trim($state);
    if ($state === '') {
        return null;
    }
    $row = null;
    $pdo = gh_connect_pdo();
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT scope, action, account_id, created_at FROM huli_oauth_states WHERE state = ? LIMIT 1");
            $stmt->execute([$state]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            $pdo->prepare("DELETE FROM huli_oauth_states WHERE state = ?")->execute([$state]);
        } catch (Throwable $e) {
            $row = null;
        }
    }
    if (is_array($row)) {
        if (time() - (int)$row['created_at'] > 600) {
            return null;
        }
        unset($_SESSION['gh_connect_state'], $_SESSION['gh_connect_state_at']);
        return [
            'scope' => $row['scope'] === 'admin' ? 'admin' : 'user',
            'action' => $row['action'] === 'bind' ? 'bind' : 'login',
            'account_id' => (int)$row['account_id'],
        ];
    }
    $saved = $_SESSION['gh_connect_state'] ?? '';
    $at = (int)($_SESSION['gh_connect_state_at'] ?? 0);
    unset($_SESSION['gh_connect_state'], $_SESSION['gh_connect_state_at']);
    if ($saved === '' || !hash_equals($saved, $state) || ($at > 0 && time() - $at > 600)) {
        return null;
    }
    return [
        'scope' => (($_SESSION['gh_connect_scope'] ?? 'user') === 'admin') ? 'admin' : 'user',
        'action' => (($_SESSION['gh_connect_action'] ?? 'login') === 'bind') ? 'bind' : 'login',
        'account_id' => 0,
    ];
}

function gh_connect_check_state(string $state): bool {
    return gh_connect_consume_state($state) !== null;
}

function gh_connect_take_action(): string {
    $action = $_SESSION['gh_connect_action'] ?? 'login';
    unset($_SESSION['gh_connect_action']);
    return $action === 'bind' ? 'bind' : 'login';
}

function gh_connect_take_scope(): string {
    $scope = $_SESSION['gh_connect_scope'] ?? 'user';
    unset($_SESSION['gh_connect_scope']);
    return $scope === 'admin' ? 'admin' : 'user';
}

function gh_connect_auth_url(string $scope, string $action = 'login', int $account_id = 0): string {
    $state = gh_connect_random_state($scope, $action, $account_id);
    $_SESSION['gh_connect_scope'] = $scope === 'admin' ? 'admin' : 'user';
    if ($action === 'bind') {
        $_SESSION['gh_connect_action'] = 'bind';
    } else {
        unset($_SESSION['gh_connect_action']);
    }
    return 'https://github.com/login/oauth/authorize'
        . '?client_id=' . rawurlencode(gh_connect_setting('ghconnect_client_id'))
        . '&redirect_uri=' . rawurlencode(gh_connect_redirect_uri())
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

function gh_connect_access_token(string $code, string $scope = ''): array {
    $r = gh_connect_http_request('POST', 'https://github.com/login/oauth/access_token', [], [
        'client_id' => gh_connect_setting('ghconnect_client_id'),
        'client_secret' => gh_connect_setting('ghconnect_client_secret'),
        'code' => $code,
        'redirect_uri' => gh_connect_redirect_uri(),
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

// One-shot banner shown right after a password or GitHub login, so the user
// learns which credential was used to sign in.
function gh_connect_flash(string $scope, string $method): void {
    $_SESSION['huli_login_notice'] = ['method' => $method, 'at' => time()];
}

function gh_connect_take_flash(string $scope): ?array {
    $notice = $_SESSION['huli_login_notice'] ?? null;
    unset($_SESSION['huli_login_notice']);
    if (!is_array($notice) || empty($notice['method'])) {
        return null;
    }
    if (time() - (int)($notice['at'] ?? 0) > 120) {
        return null;
    }
    $method = $notice['method'] === 'github' ? 'github' : 'password';
    $who = $scope === 'admin' ? '后台' : '前台';
    $text = $method === 'github'
        ? '您已通过 GitHub 账号登录' . $who . '。'
        : '您已通过账号密码登录' . $who . '。';
    return ['method' => $method, 'text' => $text];
}
