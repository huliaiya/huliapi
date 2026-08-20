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

    $base = huli_mcp_public_url('');
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

huli_mcp_user_register('list_my_orders', '获取当前账号的订单列表（充值套餐订单），可按状态过滤', [
    'type' => 'object',
    'properties' => [
        'status' => ['type' => 'string', 'enum' => ['pending', 'paid', 'canceled', 'failed'], 'description' => '按订单状态过滤'],
        'limit' => ['type' => 'integer', 'description' => '记录条数，默认 20'],
    ],
], function ($user, $args) {
    $pdo = huli_mcp_pdo();
    $limit = isset($args['limit']) ? max(1, min(50, (int)$args['limit'])) : 20;
    $where = "user_id = ?";
    $params = [(int)$user['id']];
    if (!empty($args['status'])) {
        $where .= " AND status = ?";
        $params[] = (string)$args['status'];
    }
    $stmt = $pdo->prepare("SELECT order_id, plan_id, amount, status, payment_method, provider, created_at, paid_at FROM huli_orders WHERE $where ORDER BY id DESC LIMIT ?");
    $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
    foreach ($params as $i => $v) {
        $stmt->bindValue($i + 1, $v);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return ['count' => count($rows), 'orders' => $rows];
});

huli_mcp_user_register('list_billing_plans', '获取当前上架的充值套餐列表（余额充值/积分/会员套餐）', [
    'type' => 'object',
    'properties' => new stdClass(),
], function ($user, $args) {
    $pdo = huli_mcp_pdo();
    $stmt = $pdo->query("SELECT id, name, description, price, billing_type, balance_to_add, points_to_add, membership_days, is_card FROM huli_billing_plans WHERE is_active = 1 ORDER BY price ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return ['count' => count($rows), 'plans' => $rows];
});

huli_mcp_user_register('get_announcements', '获取平台公告列表（仅已发布状态）', [
    'type' => 'object',
    'properties' => new stdClass(),
], function ($user, $args) {
    $pdo = huli_mcp_pdo();
    $stmt = $pdo->query("SELECT id, title, content, created_at FROM huli_announcements WHERE is_active = 1 ORDER BY created_at DESC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return ['count' => count($rows), 'announcements' => $rows];
});

huli_mcp_user_register('submit_feedback', '向平台提交一条反馈/工单，可关联某个 API', [
    'type' => 'object',
    'properties' => [
        'content' => ['type' => 'string', 'description' => '反馈内容'],
        'type' => ['type' => 'string', 'enum' => ['api', 'general'], 'description' => '反馈类型，默认 general'],
        'api_id' => ['type' => 'integer', 'description' => '关联的 API ID（type 为 api 时可填）'],
        'contact' => ['type' => 'string', 'description' => '联系方式（可选）'],
    ],
    'required' => ['content'],
], function ($user, $args) {
    $content = trim((string)$args['content']);
    if ($content === '') {
        throw new RuntimeException('反馈内容不能为空');
    }
    $pdo = huli_mcp_pdo();
    $type = isset($args['type']) && $args['type'] === 'api' ? 'api' : 'general';
    $apiId = isset($args['api_id']) ? (int)$args['api_id'] : null;
    if ($type === 'api' && $apiId) {
        $stmt = $pdo->prepare("SELECT id FROM huli_apis WHERE id = ?");
        $stmt->execute([$apiId]);
        if (!$stmt->fetchColumn()) {
            throw new RuntimeException('关联的 API 不存在');
        }
    }
    $contact = isset($args['contact']) ? trim((string)$args['contact']) : '';
    $stmt = $pdo->prepare("INSERT INTO huli_feedback (user_id, api_id, type, content, contact, status) VALUES (?, ?, ?, ?, ?, 'pending')");
    $stmt->execute([(int)$user['id'], $apiId, $type, $content, $contact]);
    return ['id' => (int)$pdo->lastInsertId(), 'status' => 'pending', 'notice' => '反馈已提交，请等待管理员处理'];
});

huli_mcp_user_register('list_my_feedback', '获取当前账号提交的反馈列表及管理员回复', [
    'type' => 'object',
    'properties' => [
        'limit' => ['type' => 'integer', 'description' => '记录条数，默认 20'],
    ],
], function ($user, $args) {
    $pdo = huli_mcp_pdo();
    $limit = isset($args['limit']) ? max(1, min(50, (int)$args['limit'])) : 20;
    $stmt = $pdo->prepare("SELECT f.id, f.type, f.api_id, a.name AS api_name, f.content, f.contact, f.response, f.status, f.created_at, f.responded_at FROM huli_feedback f LEFT JOIN huli_apis a ON a.id = f.api_id WHERE f.user_id = ? ORDER BY f.id DESC LIMIT ?");
    $stmt->bindValue(1, (int)$user['id'], PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return ['count' => count($rows), 'feedback' => $rows];
});

huli_mcp_user_register('redeem_cdkey', '兑换充值卡密（CDKEY），卡密可包含余额/积分/会员天数，兑换成功后权益直接到账', [
    'type' => 'object',
    'properties' => [
        'cdkey' => ['type' => 'string', 'description' => '卡密兑换码'],
    ],
    'required' => ['cdkey'],
], function ($user, $args) {
    $cdkey = strtoupper(trim((string)$args['cdkey']));
    if ($cdkey === '') {
        throw new RuntimeException('卡密不能为空');
    }
    $pdo = huli_mcp_pdo();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM huli_cdkeys WHERE cdkey = ? FOR UPDATE");
        $stmt->execute([$cdkey]);
        $key = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$key) {
            $pdo->rollBack();
            throw new RuntimeException('卡密不存在');
        }
        if ($key['status'] === 'used') {
            $pdo->rollBack();
            throw new RuntimeException('卡密已被使用');
        }
        $granted = [];
        if ((float)$key['balance'] > 0) {
            $pdo->prepare("UPDATE huli_users SET balance = balance + ? WHERE id = ?")->execute([$key['balance'], (int)$user['id']]);
            $granted['balance'] = $key['balance'];
        }
        if ((int)$key['points'] > 0) {
            $pdo->prepare("UPDATE huli_users SET points = points + ? WHERE id = ?")->execute([(int)$key['points'], (int)$user['id']]);
            $granted['points'] = (int)$key['points'];
        }
        if ((int)$key['membership_days'] > 0) {
            $stmt = $pdo->prepare("SELECT membership_expire FROM huli_users WHERE id = ?");
            $stmt->execute([(int)$user['id']]);
            $u = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($u && $u['membership_expire'] && strtotime($u['membership_expire']) > time()) {
                $expireExpression = "DATE_ADD(membership_expire, INTERVAL ? DAY)";
            } else {
                $expireExpression = "DATE_ADD(NOW(), INTERVAL ? DAY)";
            }
            $pdo->prepare("UPDATE huli_users SET membership_level = 'super', membership_expire = $expireExpression WHERE id = ?")->execute([(int)$key['membership_days'], (int)$user['id']]);
            $granted['membership_days'] = (int)$key['membership_days'];
        }
        $pdo->prepare("UPDATE huli_cdkeys SET status = 'used', used_by_user_id = ?, used_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([(int)$user['id'], (int)$key['id']]);
        $desc = '兑换卡密 ' . $cdkey;
        if (isset($granted['balance'])) {
            $desc .= ' 余额 +' . $granted['balance'];
        }
        if (isset($granted['points'])) {
            $desc .= ' 积分 +' . $granted['points'];
        }
        if (isset($granted['membership_days'])) {
            $desc .= ' 会员 +' . $granted['membership_days'] . '天';
        }
        $pdo->prepare("INSERT INTO huli_transactions (user_id, type, amount, description, status) VALUES (?, 'cdkey_redeem', ?, ?, 'completed')")->execute([(int)$user['id'], isset($granted['balance']) ? $granted['balance'] : 0, $desc]);
        $pdo->commit();
        return ['cdkey' => $cdkey, 'granted' => $granted, 'status' => 'used'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
});
