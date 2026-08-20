<?php
if (!defined('HULI_MCP_TOOLS_USER')) { define('HULI_MCP_TOOLS_USER', 1); }

require_once __DIR__ . '/mcp_lib.php';

function huli_mcp_user_row($ctx) {
    $pdo = huli_mcp_pdo();
    $stmt = $pdo->prepare("SELECT * FROM huli_users WHERE id = ?");
    $stmt->execute([$ctx['id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new RuntimeException('用户不存在');
    }
    return $row;
}

function huli_mcp_user_register($name, $description, $schema, $callable) {
    huli_mcp_register_tool($name, $description, $schema, function ($ctx, $args) use ($callable) {
        $user = huli_mcp_user_row($ctx);
        return call_user_func($callable, $user, $args);
    });
}

huli_mcp_user_register('get_account_info', '获取当前账号的完整信息：用户名、邮箱、余额、积分、会员等级、账号状态、累计调用次数等', [
    'type' => 'object',
    'properties' => new stdClass(),
], function ($user, $args) {
    return [
        'id' => (int)$user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'status' => $user['status'],
        'balance' => $user['balance'],
        'points' => (int)$user['points'],
        'membership_level' => $user['membership_level'],
        'membership_expire' => $user['membership_expire'],
        'call_count' => (int)$user['call_count'],
        'call_limit' => (int)$user['call_limit'],
        'expires_at' => $user['expires_at'],
        'created_at' => $user['created_at'],
    ];
});

huli_mcp_user_register('get_api_key', '获取当前账号的 API Key（用于调用本平台接口鉴权）', [
    'type' => 'object',
    'properties' => new stdClass(),
], function ($user, $args) {
    return ['api_key' => $user['api_key']];
});

huli_mcp_user_register('reset_api_key', '重置当前账号的 API Key。重置后旧 Key 立即失效，请妥善保存新 Key', [
    'type' => 'object',
    'properties' => new stdClass(),
], function ($user, $args) {
    $newKey = 'huliapi_' . bin2hex(random_bytes(16));
    $pdo = huli_mcp_pdo();
    $stmt = $pdo->prepare("UPDATE huli_users SET api_key = ? WHERE id = ?");
    $stmt->execute([$newKey, $user['id']]);
    return ['api_key' => $newKey, 'notice' => '旧 API Key 已失效，请立即更新你的调用配置'];
});

huli_mcp_user_register('list_apis', '列出当前可用的 API 列表（状态为正常的），包含接口名称、描述、调用示例', [
    'type' => 'object',
    'properties' => new stdClass(),
], function ($user, $args) {
    $pdo = huli_mcp_pdo();
    $stmt = $pdo->query("SELECT id, name, description, endpoint, method, request_example, response_format, price_per_call, points_per_call, is_billable, total_calls FROM huli_apis WHERE status = 'normal' ORDER BY total_calls DESC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return ['count' => count($rows), 'apis' => $rows];
});

huli_mcp_user_register('get_api_detail', '获取单个 API 的详细信息：参数列表、调用示例、返回示例', [
    'type' => 'object',
    'properties' => [
        'id' => ['type' => 'integer', 'description' => 'API ID'],
    ],
    'required' => ['id'],
], function ($user, $args) {
    $pdo = huli_mcp_pdo();
    $stmt = $pdo->prepare("SELECT * FROM huli_apis WHERE id = ? AND status = 'normal'");
    $stmt->execute([(int)$args['id']]);
    $api = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$api) {
        throw new RuntimeException('API 不存在或不可用');
    }
    $params = $api['parameters'] ? json_decode($api['parameters'], true) : [];
    return [
        'id' => (int)$api['id'],
        'name' => $api['name'],
        'description' => $api['description'],
        'endpoint' => $api['endpoint'],
        'method' => $api['method'],
        'parameters' => is_array($params) ? $params : [],
        'request_example' => $api['request_example'],
        'response_example' => $api['response_example'],
        'response_format' => $api['response_format'],
        'is_billable' => (bool)$api['is_billable'],
        'price_per_call' => $api['price_per_call'],
        'points_per_call' => (int)$api['points_per_call'],
    ];
});

huli_mcp_user_register('call_api', '以当前账号身份调用一个本地 API，自动附加 api_key 鉴权并走平台计费流程。参数 params 为传给接口的参数对象', [
    'type' => 'object',
    'properties' => [
        'endpoint' => ['type' => 'string', 'description' => 'API endpoint，例如 ip'],
        'params' => ['type' => 'object', 'description' => '接口参数，例如 {"ip":"1.2.3.4"}'],
        'method' => ['type' => 'string', 'enum' => ['GET', 'POST'], 'description' => '请求方法，默认 GET'],
    ],
    'required' => ['endpoint'],
], function ($user, $args) {
    $pdo = huli_mcp_pdo();
    $endpoint = trim((string)$args['endpoint']);
    $stmt = $pdo->prepare("SELECT * FROM huli_apis WHERE endpoint = ? AND type = 'local' LIMIT 1");
    $stmt->execute([$endpoint]);
    $api = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$api) {
        throw new RuntimeException('接口不存在或不允许通过 MCP 调用');
    }
    $params = isset($args['params']) && is_array($args['params']) ? $args['params'] : [];
    $method = strtoupper(isset($args['method']) ? $args['method'] : 'GET');
    if ($method !== 'POST') {
        $method = 'GET';
    }
    $params['api_key'] = $user['api_key'];

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $scheme = strtolower(trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]));
    }
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '127.0.0.1');
    $base = $scheme . '://' . $host;
    $url = rtrim($base, '/') . '/' . ltrim($api['file_path'], '/');

    $ch = curl_init();
    $curlOpts = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => false,
    ];
    if ($method === 'POST') {
        $curlOpts[CURLOPT_POST] = true;
        $curlOpts[CURLOPT_POSTFIELDS] = http_build_query($params);
    } else {
        $curlOpts[CURLOPT_URL] = $url . '?' . http_build_query($params);
    }
    curl_setopt_array($ch, $curlOpts);
    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);
    if ($response === false) {
        throw new RuntimeException('调用失败: ' . ($curlErr ?: '网络错误'));
    }
    $decoded = json_decode($response, true);
    return [
        'http_status' => $httpCode,
        'response' => is_array($decoded) ? $decoded : $response,
    ];
});

huli_mcp_user_register('get_call_stats', '获取当前账号的调用统计：今日调用、总调用次数、最近调用记录', [
    'type' => 'object',
    'properties' => [
        'limit' => ['type' => 'integer', 'description' => '最近调用记录条数，默认 10'],
    ],
], function ($user, $args) {
    $pdo = huli_mcp_pdo();
    $limit = isset($args['limit']) ? max(1, min(50, (int)$args['limit'])) : 10;
    $today = (int)$pdo->query("SELECT COUNT(*) FROM huli_api_logs WHERE user_id = " . (int)$user['id'] . " AND DATE(request_time) = CURDATE()")->fetchColumn();
    $total = (int)$pdo->query("SELECT COUNT(*) FROM huli_api_logs WHERE user_id = " . (int)$user['id'])->fetchColumn();
    $stmt = $pdo->prepare("SELECT l.id, a.name AS api_name, l.request_time, l.is_success, l.response_code, l.billing_type, l.billing_amount FROM huli_api_logs l LEFT JOIN huli_apis a ON a.id = l.api_id WHERE l.user_id = ? ORDER BY l.request_time DESC LIMIT ?");
    $stmt->bindValue(1, (int)$user['id'], PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return [
        'today_calls' => $today,
        'total_calls' => $total,
        'recent_calls' => $recent,
    ];
});

huli_mcp_user_register('get_transactions', '获取当前账号的余额/积分变动记录（充值、消费、赠送等）', [
    'type' => 'object',
    'properties' => [
        'limit' => ['type' => 'integer', 'description' => '记录条数，默认 20'],
    ],
], function ($user, $args) {
    $pdo = huli_mcp_pdo();
    $limit = isset($args['limit']) ? max(1, min(50, (int)$args['limit'])) : 20;
    $stmt = $pdo->prepare("SELECT id, type, amount, description, status, created_at FROM huli_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->bindValue(1, (int)$user['id'], PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return ['count' => count($rows), 'transactions' => $rows];
});
