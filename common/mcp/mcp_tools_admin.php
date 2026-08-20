<?php
if (!defined('HULI_MCP_TOOLS_ADMIN')) { define('HULI_MCP_TOOLS_ADMIN', 1); }

require_once __DIR__ . '/mcp_lib.php';

function huli_mcp_admin_pdo() {
    return huli_mcp_pdo();
}

function huli_mcp_admin_register($name, $description, $schema, $callable) {
    huli_mcp_register_tool($name, $description, $schema, function ($ctx, $args) use ($callable) {
        return call_user_func($callable, huli_mcp_admin_pdo(), $args);
    });
}

huli_mcp_admin_register('get_system_stats', '获取系统核心运营数据：今日/昨日调用、总调用量、用户数、API 数、订单数、待处理反馈等', [
    'type' => 'object',
    'properties' => new stdClass(),
], function ($pdo, $args) {
    return [
        'today_calls' => (int)$pdo->query("SELECT COUNT(*) FROM huli_api_logs WHERE DATE(request_time) = CURDATE()")->fetchColumn(),
        'yesterday_calls' => (int)$pdo->query("SELECT COUNT(*) FROM huli_api_logs WHERE DATE(request_time) = CURDATE() - INTERVAL 1 DAY")->fetchColumn(),
        'total_calls' => (int)$pdo->query("SELECT COALESCE(SUM(total_calls), 0) FROM huli_apis")->fetchColumn(),
        'today_income' => (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM huli_orders WHERE status = 'paid' AND DATE(created_at) = CURDATE()")->fetchColumn(),
        'total_users' => (int)$pdo->query("SELECT COUNT(*) FROM huli_users")->fetchColumn(),
        'today_new_users' => (int)$pdo->query("SELECT COUNT(*) FROM huli_users WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
        'active_users' => (int)$pdo->query("SELECT COUNT(*) FROM huli_users WHERE status = 'active'")->fetchColumn(),
        'total_apis' => (int)$pdo->query("SELECT COUNT(*) FROM huli_apis")->fetchColumn(),
        'normal_apis' => (int)$pdo->query("SELECT COUNT(*) FROM huli_apis WHERE status = 'normal'")->fetchColumn(),
        'today_success_orders' => (int)$pdo->query("SELECT COUNT(*) FROM huli_orders WHERE status = 'paid' AND DATE(created_at) = CURDATE()")->fetchColumn(),
        'pending_orders' => (int)$pdo->query("SELECT COUNT(*) FROM huli_orders WHERE status = 'pending'")->fetchColumn(),
        'pending_feedback' => (int)$pdo->query("SELECT COUNT(*) FROM huli_feedback WHERE status = 'pending'")->fetchColumn(),
    ];
});

huli_mcp_admin_register('list_users', '查询用户列表，支持按关键词搜索（用户名/邮箱/QQ）和分页', [
    'type' => 'object',
    'properties' => [
        'keyword' => ['type' => 'string', 'description' => '搜索关键词'],
        'page' => ['type' => 'integer', 'description' => '页码，默认 1'],
        'page_size' => ['type' => 'integer', 'description' => '每页条数，默认 20，最大 100'],
    ],
], function ($pdo, $args) {
    $page = isset($args['page']) ? max(1, (int)$args['page']) : 1;
    $size = isset($args['page_size']) ? max(1, min(100, (int)$args['page_size'])) : 20;
    $keyword = isset($args['keyword']) ? trim((string)$args['keyword']) : '';
    $where = '';
    $params = [];
    if ($keyword !== '') {
        $where = "WHERE username LIKE ? OR email LIKE ? OR qq LIKE ?";
        $kw = '%' . $keyword . '%';
        $params = [$kw, $kw, $kw];
    }
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM huli_users $where");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    $offset = ($page - 1) * $size;
    $stmt = $pdo->prepare("SELECT id, username, email, qq, status, balance, points, membership_level, call_count, created_at FROM huli_users $where ORDER BY id DESC LIMIT $offset, $size");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return [
        'total' => $total,
        'page' => $page,
        'page_size' => $size,
        'pages' => $total > 0 ? (int)ceil($total / $size) : 0,
        'users' => $rows,
    ];
});

huli_mcp_admin_register('get_user_detail', '获取单个用户的完整信息：账号资料、余额、积分、会员、调用统计、最近订单与交易', [
    'type' => 'object',
    'properties' => [
        'id' => ['type' => 'integer', 'description' => '用户 ID'],
    ],
    'required' => ['id'],
], function ($pdo, $args) {
    $stmt = $pdo->prepare("SELECT * FROM huli_users WHERE id = ?");
    $stmt->execute([(int)$args['id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        throw new RuntimeException('用户不存在');
    }
    $uid = (int)$user['id'];
    $user['today_calls'] = (int)$pdo->query("SELECT COUNT(*) FROM huli_api_logs WHERE user_id = $uid AND DATE(request_time) = CURDATE()")->fetchColumn();
    $user['total_calls'] = (int)$pdo->query("SELECT COUNT(*) FROM huli_api_logs WHERE user_id = $uid")->fetchColumn();
    $user['orders'] = $pdo->query("SELECT order_id, amount, status, provider, created_at FROM huli_orders WHERE user_id = $uid ORDER BY created_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    $user['transactions'] = $pdo->query("SELECT id, type, amount, description, status, created_at FROM huli_transactions WHERE user_id = $uid ORDER BY created_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    return $user;
});

huli_mcp_admin_register('adjust_user_balance', '调整指定用户的余额（可为正数充值，负数扣减）。调整会写入余额变动记录', [
    'type' => 'object',
    'properties' => [
        'user_id' => ['type' => 'integer', 'description' => '用户 ID'],
        'amount' => ['type' => 'number', 'description' => '调整金额，正数增加、负数减少'],
        'reason' => ['type' => 'string', 'description' => '调整原因，会记录在变动明细中'],
    ],
    'required' => ['user_id', 'amount'],
], function ($pdo, $args) {
    $uid = (int)$args['user_id'];
    $amount = round((float)$args['amount'], 2);
    $reason = isset($args['reason']) ? trim((string)$args['reason']) : 'MCP 管理员调整';
    if ($amount == 0) {
        throw new RuntimeException('调整金额不能为 0');
    }
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT balance FROM huli_users WHERE id = ? FOR UPDATE");
        $stmt->execute([$uid]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            $pdo->rollBack();
            throw new RuntimeException('用户不存在');
        }
        $newBalance = round($user['balance'] + $amount, 2);
        if ($newBalance < 0) {
            $pdo->rollBack();
            throw new RuntimeException('调整后余额不能为负数，当前余额 ' . $user['balance']);
        }
        $pdo->prepare("UPDATE huli_users SET balance = ? WHERE id = ?")->execute([$newBalance, $uid]);
        $pdo->prepare("INSERT INTO huli_transactions (user_id, type, amount, description, status) VALUES (?, 'admin_adjust', ?, ?, 'completed')")->execute([$uid, $amount, $reason]);
        $pdo->commit();
        return ['user_id' => $uid, 'old_balance' => $user['balance'], 'new_balance' => $newBalance, 'amount' => $amount, 'reason' => $reason];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
});

huli_mcp_admin_register('adjust_user_points', '调整指定用户的积分（可为正数增加，负数扣减）', [
    'type' => 'object',
    'properties' => [
        'user_id' => ['type' => 'integer', 'description' => '用户 ID'],
        'points' => ['type' => 'integer', 'description' => '调整积分，正数增加、负数减少'],
        'reason' => ['type' => 'string', 'description' => '调整原因'],
    ],
    'required' => ['user_id', 'points'],
], function ($pdo, $args) {
    $uid = (int)$args['user_id'];
    $points = (int)$args['points'];
    $reason = isset($args['reason']) ? trim((string)$args['reason']) : 'MCP 管理员调整';
    if ($points == 0) {
        throw new RuntimeException('调整积分不能为 0');
    }
    $stmt = $pdo->prepare("SELECT points FROM huli_users WHERE id = ?");
    $stmt->execute([$uid]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        throw new RuntimeException('用户不存在');
    }
    $newPoints = $user['points'] + $points;
    if ($newPoints < 0) {
        throw new RuntimeException('调整后积分不能为负数，当前积分 ' . $user['points']);
    }
    $pdo->prepare("UPDATE huli_users SET points = ? WHERE id = ?")->execute([$newPoints, $uid]);
    return ['user_id' => $uid, 'old_points' => (int)$user['points'], 'new_points' => $newPoints, 'points' => $points, 'reason' => $reason];
});

huli_mcp_admin_register('set_user_status', '设置用户账号状态（active 正常 / banned 封禁 / pending 待审核 / inactive 停用）', [
    'type' => 'object',
    'properties' => [
        'user_id' => ['type' => 'integer', 'description' => '用户 ID'],
        'status' => ['type' => 'string', 'enum' => ['active', 'banned', 'pending', 'inactive'], 'description' => '目标状态'],
    ],
    'required' => ['user_id', 'status'],
], function ($pdo, $args) {
    $uid = (int)$args['user_id'];
    $status = (string)$args['status'];
    $allowed = ['active', 'banned', 'pending', 'inactive'];
    if (!in_array($status, $allowed, true)) {
        throw new RuntimeException('无效的状态值');
    }
    $stmt = $pdo->prepare("SELECT id, username, status FROM huli_users WHERE id = ?");
    $stmt->execute([$uid]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        throw new RuntimeException('用户不存在');
    }
    $pdo->prepare("UPDATE huli_users SET status = ? WHERE id = ?")->execute([$status, $uid]);
    return ['user_id' => $uid, 'username' => $user['username'], 'old_status' => $user['status'], 'new_status' => $status];
});

huli_mcp_admin_register('list_apis', '查询平台 API 列表，可按状态过滤，含调用量、计费信息', [
    'type' => 'object',
    'properties' => [
        'status' => ['type' => 'string', 'enum' => ['normal', 'error', 'maintenance', 'deprecated'], 'description' => '按状态过滤'],
        'page' => ['type' => 'integer', 'description' => '页码，默认 1'],
        'page_size' => ['type' => 'integer', 'description' => '每页条数，默认 50，最大 100'],
    ],
], function ($pdo, $args) {
    $page = isset($args['page']) ? max(1, (int)$args['page']) : 1;
    $size = isset($args['page_size']) ? max(1, min(100, (int)$args['page_size'])) : 50;
    $where = '';
    $params = [];
    if (!empty($args['status'])) {
        $where = "WHERE status = ?";
        $params = [(string)$args['status']];
    }
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM huli_apis $where");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    $offset = ($page - 1) * $size;
    $stmt = $pdo->prepare("SELECT id, name, endpoint, method, type, status, visibility, is_billable, price_per_call, points_per_call, total_calls, created_at FROM huli_apis $where ORDER BY id DESC LIMIT $offset, $size");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return ['total' => $total, 'page' => $page, 'page_size' => $size, 'apis' => $rows];
});

huli_mcp_admin_register('set_api_status', '修改 API 运行状态（normal 正常 / error 异常 / maintenance 维护中 / deprecated 已废弃）', [
    'type' => 'object',
    'properties' => [
        'api_id' => ['type' => 'integer', 'description' => 'API ID'],
        'status' => ['type' => 'string', 'enum' => ['normal', 'error', 'maintenance', 'deprecated'], 'description' => '目标状态'],
    ],
    'required' => ['api_id', 'status'],
], function ($pdo, $args) {
    $apiId = (int)$args['api_id'];
    $status = (string)$args['status'];
    $allowed = ['normal', 'error', 'maintenance', 'deprecated'];
    if (!in_array($status, $allowed, true)) {
        throw new RuntimeException('无效的状态值');
    }
    $stmt = $pdo->prepare("SELECT id, name, status FROM huli_apis WHERE id = ?");
    $stmt->execute([$apiId]);
    $api = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$api) {
        throw new RuntimeException('API 不存在');
    }
    $pdo->prepare("UPDATE huli_apis SET status = ? WHERE id = ?")->execute([$status, $apiId]);
    return ['api_id' => $apiId, 'name' => $api['name'], 'old_status' => $api['status'], 'new_status' => $status];
});

huli_mcp_admin_register('list_orders', '查询订单列表，可按状态过滤', [
    'type' => 'object',
    'properties' => [
        'status' => ['type' => 'string', 'enum' => ['pending', 'paid', 'canceled', 'failed'], 'description' => '按订单状态过滤'],
        'page' => ['type' => 'integer', 'description' => '页码，默认 1'],
        'page_size' => ['type' => 'integer', 'description' => '每页条数，默认 20，最大 100'],
    ],
], function ($pdo, $args) {
    $page = isset($args['page']) ? max(1, (int)$args['page']) : 1;
    $size = isset($args['page_size']) ? max(1, min(100, (int)$args['page_size'])) : 20;
    $where = '';
    $params = [];
    if (!empty($args['status'])) {
        $where = "WHERE o.status = ?";
        $params = [(string)$args['status']];
    }
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM huli_orders o $where");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    $offset = ($page - 1) * $size;
    $stmt = $pdo->prepare("SELECT o.order_id, o.user_id, u.username, o.amount, o.status, o.payment_method, o.provider, o.created_at, o.paid_at FROM huli_orders o LEFT JOIN huli_users u ON u.id = o.user_id $where ORDER BY o.id DESC LIMIT $offset, $size");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return ['total' => $total, 'page' => $page, 'page_size' => $size, 'orders' => $rows];
});

huli_mcp_admin_register('get_server_status', '获取服务器运行状态：PHP 版本、数据库版本、系统信息、磁盘使用、内存等', [
    'type' => 'object',
    'properties' => new stdClass(),
], function ($pdo, $args) {
    $status = [
        'php_version' => PHP_VERSION,
        'php_sapi' => PHP_SAPI,
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? '',
        'system' => php_uname('s') . ' ' . php_uname('r') . ' ' . php_uname('m'),
        'db_server' => 'MySQL',
    ];
    try {
        $status['db_version'] = $pdo->query("SELECT VERSION()")->fetchColumn();
    } catch (Throwable $e) {
        $status['db_version'] = 'unknown';
    }
    if (function_exists('disk_free_space') && is_string(PHP_OS_FAMILY) && PHP_OS_FAMILY !== 'Windows') {
        $diskFree = @disk_free_space('/');
        $diskTotal = @disk_total_space('/');
        if ($diskFree !== false && $diskTotal !== false && $diskTotal > 0) {
            $status['disk_total_gb'] = round($diskTotal / 1073741824, 2);
            $status['disk_free_gb'] = round($diskFree / 1073741824, 2);
            $status['disk_usage_percent'] = round((1 - $diskFree / $diskTotal) * 100, 1);
        }
    }
    $memFile = '/proc/meminfo';
    if (is_readable($memFile)) {
        $memInfo = [];
        foreach (file($memFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (preg_match('/^(\w+):\s+(\d+)/', $line, $m)) {
                $memInfo[$m[1]] = (int)$m[2] * 1024;
            }
        }
        if (isset($memInfo['MemTotal'])) {
            $status['memory_total_mb'] = round($memInfo['MemTotal'] / 1048576, 1);
            if (isset($memInfo['MemAvailable'])) {
                $status['memory_available_mb'] = round($memInfo['MemAvailable'] / 1048576, 1);
                $status['memory_usage_percent'] = round((1 - $memInfo['MemAvailable'] / $memInfo['MemTotal']) * 100, 1);
            }
        }
    }
    $load = function_exists('sys_getloadavg') ? sys_getloadavg() : false;
    if ($load) {
        $status['load_avg'] = array_map(function ($v) {
            return round($v, 2);
        }, $load);
    }
    return $status;
});

huli_mcp_admin_register('list_feedback', '查询用户反馈列表，可过滤待处理状态', [
    'type' => 'object',
    'properties' => [
        'status' => ['type' => 'string', 'enum' => ['pending', 'viewed', 'resolved'], 'description' => '按状态过滤'],
        'page' => ['type' => 'integer', 'description' => '页码，默认 1'],
        'page_size' => ['type' => 'integer', 'description' => '每页条数，默认 20，最大 100'],
    ],
], function ($pdo, $args) {
    $page = isset($args['page']) ? max(1, (int)$args['page']) : 1;
    $size = isset($args['page_size']) ? max(1, min(100, (int)$args['page_size'])) : 20;
    $where = '';
    $params = [];
    if (!empty($args['status'])) {
        $where = "WHERE f.status = ?";
        $params = [(string)$args['status']];
    }
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM huli_feedback f $where");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    $offset = ($page - 1) * $size;
    $stmt = $pdo->prepare("SELECT f.id, f.user_id, u.username, f.content, f.status, f.created_at FROM huli_feedback f LEFT JOIN huli_users u ON u.id = f.user_id $where ORDER BY f.id DESC LIMIT $offset, $size");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return ['total' => $total, 'page' => $page, 'page_size' => $size, 'feedback' => $rows];
});

huli_mcp_admin_register('respond_feedback', '回复用户反馈，回复后反馈状态变为已解决', [
    'type' => 'object',
    'properties' => [
        'feedback_id' => ['type' => 'integer', 'description' => '反馈 ID'],
        'response' => ['type' => 'string', 'description' => '回复内容'],
    ],
    'required' => ['feedback_id', 'response'],
], function ($pdo, $args) {
    $id = (int)$args['feedback_id'];
    $response = trim((string)$args['response']);
    if ($response === '') {
        throw new RuntimeException('回复内容不能为空');
    }
    $stmt = $pdo->prepare("SELECT id, user_id, content, status FROM huli_feedback WHERE id = ?");
    $stmt->execute([$id]);
    $fb = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$fb) {
        throw new RuntimeException('反馈不存在');
    }
    $pdo->prepare("UPDATE huli_feedback SET response = ?, status = 'resolved', responded_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$response, $id]);
    return ['feedback_id' => $id, 'user_id' => $fb['user_id'], 'status' => 'resolved'];
});

huli_mcp_admin_register('list_cdkeys', '查询卡密列表，可按状态（未使用/已使用）过滤', [
    'type' => 'object',
    'properties' => [
        'status' => ['type' => 'string', 'enum' => ['unused', 'used'], 'description' => '按状态过滤'],
        'page' => ['type' => 'integer', 'description' => '页码，默认 1'],
        'page_size' => ['type' => 'integer', 'description' => '每页条数，默认 20，最大 100'],
    ],
], function ($pdo, $args) {
    $page = isset($args['page']) ? max(1, (int)$args['page']) : 1;
    $size = isset($args['page_size']) ? max(1, min(100, (int)$args['page_size'])) : 20;
    $where = '';
    $params = [];
    if (!empty($args['status'])) {
        $where = "WHERE c.status = ?";
        $params = [(string)$args['status']];
    }
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM huli_cdkeys c $where");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    $offset = ($page - 1) * $size;
    $stmt = $pdo->prepare("SELECT c.id, c.cdkey, c.type, c.balance, c.points, c.membership_days, c.status, c.used_by_user_id, u.username AS used_by, c.created_at FROM huli_cdkeys c LEFT JOIN huli_users u ON u.id = c.used_by_user_id $where ORDER BY c.id DESC LIMIT $offset, $size");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return ['total' => $total, 'page' => $page, 'page_size' => $size, 'cdkeys' => $rows];
});

huli_mcp_admin_register('create_cdkey', '批量生成充值卡密，支持余额/积分/会员天数三种类型', [
    'type' => 'object',
    'properties' => [
        'type' => ['type' => 'string', 'enum' => ['balance', 'points', 'membership'], 'description' => '卡密类型'],
        'value' => ['type' => 'number', 'description' => '卡密价值：balance 为金额(元)、points 为积分、membership 为会员天数'],
        'count' => ['type' => 'integer', 'description' => '生成数量，1-1000，默认 1'],
    ],
    'required' => ['type', 'value'],
], function ($pdo, $args) {
    $type = (string)$args['type'];
    if (!in_array($type, ['balance', 'points', 'membership'], true)) {
        throw new RuntimeException('无效的卡密类型');
    }
    $count = isset($args['count']) ? (int)$args['count'] : 1;
    if ($count < 1 || $count > 1000) {
        throw new RuntimeException('生成数量必须为 1-1000 的整数');
    }
    $balance = $points = $membershipDays = 0;
    if ($type === 'balance') {
        $balance = round((float)$args['value'], 2);
        if ($balance <= 0) {
            throw new RuntimeException('金额必须大于 0');
        }
    } elseif ($type === 'points') {
        $points = (int)$args['value'];
        if ($points <= 0) {
            throw new RuntimeException('积分必须为正整数');
        }
    } else {
        $membershipDays = (int)$args['value'];
        if ($membershipDays <= 0) {
            throw new RuntimeException('会员天数必须为正整数');
        }
    }
    $pdo->beginTransaction();
    try {
        $values = [];
        $placeholders = [];
        for ($i = 0; $i < $count; $i++) {
            $key = strtoupper(bin2hex(random_bytes(16)));
            $values[] = $key;
            $values[] = $type;
            $values[] = $balance;
            $values[] = $points;
            $values[] = $membershipDays;
            $placeholders[] = '(?, ?, ?, ?, ?)';
        }
        $stmt = $pdo->prepare("INSERT INTO huli_cdkeys (cdkey, type, balance, points, membership_days) VALUES " . implode(', ', $placeholders));
        $stmt->execute($values);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    $unit = $type === 'balance' ? '元' : ($type === 'points' ? '积分' : '天会员');
    return ['created' => $count, 'type' => $type, 'value' => $type === 'balance' ? $balance : ($type === 'points' ? $points : $membershipDays), 'unit' => $unit];
});

huli_mcp_admin_register('list_billing_plans', '查询充值套餐列表（全量，含未上架），支持按状态过滤', [
    'type' => 'object',
    'properties' => [
        'is_active' => ['type' => 'boolean', 'description' => '仅返回上架中的套餐'],
    ],
], function ($pdo, $args) {
    $where = '';
    if (!empty($args['is_active'])) {
        $where = "WHERE is_active = 1";
    }
    $stmt = $pdo->query("SELECT id, name, description, price, billing_type, balance_to_add, points_to_add, membership_days, is_active, is_card, created_at FROM huli_billing_plans $where ORDER BY price ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return ['count' => count($rows), 'plans' => $rows];
});

huli_mcp_admin_register('set_billing_plan_status', '上架/下架充值套餐', [
    'type' => 'object',
    'properties' => [
        'plan_id' => ['type' => 'integer', 'description' => '套餐 ID'],
        'is_active' => ['type' => 'boolean', 'description' => 'true 上架 / false 下架'],
    ],
    'required' => ['plan_id', 'is_active'],
], function ($pdo, $args) {
    $planId = (int)$args['plan_id'];
    $isActive = !empty($args['is_active']) ? 1 : 0;
    $stmt = $pdo->prepare("SELECT id, name, is_active FROM huli_billing_plans WHERE id = ?");
    $stmt->execute([$planId]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$plan) {
        throw new RuntimeException('套餐不存在');
    }
    $pdo->prepare("UPDATE huli_billing_plans SET is_active = ? WHERE id = ?")->execute([$isActive, $planId]);
    return ['plan_id' => $planId, 'name' => $plan['name'], 'is_active' => (bool)$isActive];
});

huli_mcp_admin_register('list_announcements', '查询平台公告列表', [
    'type' => 'object',
    'properties' => [
        'is_active' => ['type' => 'boolean', 'description' => '仅返回已启用公告'],
    ],
], function ($pdo, $args) {
    $where = '';
    if (!empty($args['is_active'])) {
        $where = "WHERE is_active = 1";
    }
    $stmt = $pdo->query("SELECT id, title, content, is_active, created_at FROM huli_announcements $where ORDER BY created_at DESC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return ['count' => count($rows), 'announcements' => $rows];
});

huli_mcp_admin_register('create_announcement', '发布一条平台公告', [
    'type' => 'object',
    'properties' => [
        'title' => ['type' => 'string', 'description' => '公告标题'],
        'content' => ['type' => 'string', 'description' => '公告内容'],
    ],
    'required' => ['title', 'content'],
], function ($pdo, $args) {
    $title = trim((string)$args['title']);
    $content = trim((string)$args['content']);
    if ($title === '' || $content === '') {
        throw new RuntimeException('标题和内容不能为空');
    }
    $stmt = $pdo->prepare("INSERT INTO huli_announcements (title, content, is_active) VALUES (?, ?, 1)");
    $stmt->execute([$title, $content]);
    return ['id' => (int)$pdo->lastInsertId(), 'title' => $title, 'is_active' => true];
});

huli_mcp_admin_register('set_announcement_status', '启用/停用平台公告', [
    'type' => 'object',
    'properties' => [
        'announcement_id' => ['type' => 'integer', 'description' => '公告 ID'],
        'is_active' => ['type' => 'boolean', 'description' => 'true 启用 / false 停用'],
    ],
    'required' => ['announcement_id', 'is_active'],
], function ($pdo, $args) {
    $id = (int)$args['announcement_id'];
    $isActive = !empty($args['is_active']) ? 1 : 0;
    $stmt = $pdo->prepare("SELECT id, title, is_active FROM huli_announcements WHERE id = ?");
    $stmt->execute([$id]);
    $ann = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ann) {
        throw new RuntimeException('公告不存在');
    }
    $pdo->prepare("UPDATE huli_announcements SET is_active = ? WHERE id = ?")->execute([$isActive, $id]);
    return ['announcement_id' => $id, 'title' => $ann['title'], 'is_active' => (bool)$isActive];
});

huli_mcp_admin_register('list_api_logs', '查询 API 调用日志，可按用户或结果过滤，包含计费信息', [
    'type' => 'object',
    'properties' => [
        'user_id' => ['type' => 'integer', 'description' => '按用户过滤'],
        'is_success' => ['type' => 'boolean', 'description' => '按调用结果过滤'],
        'page' => ['type' => 'integer', 'description' => '页码，默认 1'],
        'page_size' => ['type' => 'integer', 'description' => '每页条数，默认 20，最大 100'],
    ],
], function ($pdo, $args) {
    $page = isset($args['page']) ? max(1, (int)$args['page']) : 1;
    $size = isset($args['page_size']) ? max(1, min(100, (int)$args['page_size'])) : 20;
    $where = [];
    $params = [];
    if (!empty($args['user_id'])) {
        $where[] = "l.user_id = ?";
        $params[] = (int)$args['user_id'];
    }
    if (isset($args['is_success'])) {
        $where[] = "l.is_success = ?";
        $params[] = !empty($args['is_success']) ? 1 : 0;
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM huli_api_logs l $whereSql");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    $offset = ($page - 1) * $size;
    $sql = "SELECT l.id, l.api_id, a.name AS api_name, l.user_id, u.username, l.ip_address, l.request_time, l.response_code, l.is_success, l.billing_type, l.billing_amount FROM huli_api_logs l LEFT JOIN huli_apis a ON a.id = l.api_id LEFT JOIN huli_users u ON u.id = l.user_id $whereSql ORDER BY l.id DESC LIMIT $offset, $size";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return ['total' => $total, 'page' => $page, 'page_size' => $size, 'logs' => $rows];
});

huli_mcp_admin_register('list_transactions', '查询用户余额/积分变动记录（全平台），可按用户过滤', [
    'type' => 'object',
    'properties' => [
        'user_id' => ['type' => 'integer', 'description' => '按用户过滤'],
        'type' => ['type' => 'string', 'description' => '按交易类型过滤（如 recharge / consume / admin_adjust / cdkey_redeem）'],
        'page' => ['type' => 'integer', 'description' => '页码，默认 1'],
        'page_size' => ['type' => 'integer', 'description' => '每页条数，默认 20，最大 100'],
    ],
], function ($pdo, $args) {
    $page = isset($args['page']) ? max(1, (int)$args['page']) : 1;
    $size = isset($args['page_size']) ? max(1, min(100, (int)$args['page_size'])) : 20;
    $where = [];
    $params = [];
    if (!empty($args['user_id'])) {
        $where[] = "t.user_id = ?";
        $params[] = (int)$args['user_id'];
    }
    if (!empty($args['type'])) {
        $where[] = "t.type = ?";
        $params[] = (string)$args['type'];
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM huli_transactions t $whereSql");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    $offset = ($page - 1) * $size;
    $sql = "SELECT t.id, t.user_id, u.username, t.type, t.amount, t.description, t.status, t.created_at FROM huli_transactions t LEFT JOIN huli_users u ON u.id = t.user_id $whereSql ORDER BY t.id DESC LIMIT $offset, $size";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return ['total' => $total, 'page' => $page, 'page_size' => $size, 'transactions' => $rows];
});

huli_mcp_admin_register('get_mcp_stats', '获取 MCP 服务请求统计：今日请求数、总请求数、成功率、按方法/工具/角色分布、最近 7 天趋势', [
    'type' => 'object',
    'properties' => new stdClass(),
], function ($pdo, $args) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS huli_mcp_logs (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT,
      request_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      role ENUM('user','admin') NOT NULL,
      user_id INT UNSIGNED NOT NULL DEFAULT 0,
      username VARCHAR(64) NOT NULL DEFAULT '',
      method VARCHAR(64) NOT NULL DEFAULT '',
      tool_name VARCHAR(64) NULL DEFAULT NULL,
      ip_address VARCHAR(64) NOT NULL DEFAULT '',
      status ENUM('success','error','invalid') NOT NULL DEFAULT 'success',
      error_msg VARCHAR(500) NULL DEFAULT NULL,
      latency_ms INT UNSIGNED NOT NULL DEFAULT 0,
      PRIMARY KEY (id),
      KEY idx_request_time (request_time),
      KEY idx_role_time (role, request_time),
      KEY idx_user_time (role, user_id, request_time),
      KEY idx_method (method),
      KEY idx_tool (tool_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $today = (int)$pdo->query("SELECT COUNT(*) FROM huli_mcp_logs WHERE DATE(request_time) = CURDATE()")->fetchColumn();
    $yesterday = (int)$pdo->query("SELECT COUNT(*) FROM huli_mcp_logs WHERE DATE(request_time) = CURDATE() - INTERVAL 1 DAY")->fetchColumn();
    $total = (int)$pdo->query("SELECT COUNT(*) FROM huli_mcp_logs")->fetchColumn();
    $success = (int)$pdo->query("SELECT COUNT(*) FROM huli_mcp_logs WHERE status = 'success'")->fetchColumn();
    $error = (int)$pdo->query("SELECT COUNT(*) FROM huli_mcp_logs WHERE status IN ('error','invalid')")->fetchColumn();
    $successRate = $total > 0 ? round(($success / $total) * 100, 2) : 0.0;
    $byRole = $pdo->query("SELECT role, COUNT(*) AS cnt FROM huli_mcp_logs GROUP BY role")->fetchAll(PDO::FETCH_KEY_PAIR);
    $byMethod = $pdo->query("SELECT method, COUNT(*) AS cnt FROM huli_mcp_logs GROUP BY method ORDER BY cnt DESC")->fetchAll(PDO::FETCH_ASSOC);
    $byTool = $pdo->query("SELECT tool_name, COUNT(*) AS cnt FROM huli_mcp_logs WHERE tool_name IS NOT NULL GROUP BY tool_name ORDER BY cnt DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    $trend = $pdo->query("SELECT DATE(request_time) AS d, COUNT(*) AS cnt FROM huli_mcp_logs WHERE request_time >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(request_time) ORDER BY d ASC")->fetchAll(PDO::FETCH_ASSOC);
    return [
        'today_requests' => $today,
        'yesterday_requests' => $yesterday,
        'total_requests' => $total,
        'success_requests' => $success,
        'error_requests' => $error,
        'success_rate_percent' => $successRate,
        'by_role' => [
            'user' => (int)($byRole['user'] ?? 0),
            'admin' => (int)($byRole['admin'] ?? 0),
        ],
        'by_method' => $byMethod,
        'top_tools' => $byTool,
        'trend_7d' => $trend,
    ];
});

huli_mcp_admin_register('list_mcp_logs', '查询 MCP 请求历史日志，可按角色/方法/工具/状态/用户过滤', [
    'type' => 'object',
    'properties' => [
        'role' => ['type' => 'string', 'enum' => ['user', 'admin'], 'description' => '按角色过滤'],
        'method' => ['type' => 'string', 'description' => '按方法过滤（如 tools/call、initialize）'],
        'tool_name' => ['type' => 'string', 'description' => '按工具名过滤'],
        'status' => ['type' => 'string', 'enum' => ['success', 'error', 'invalid'], 'description' => '按状态过滤'],
        'user_id' => ['type' => 'integer', 'description' => '按用户 ID 过滤（管理员 ID 或用户 ID 均可）'],
        'page' => ['type' => 'integer', 'description' => '页码，默认 1'],
        'page_size' => ['type' => 'integer', 'description' => '每页条数，默认 20，最大 100'],
    ],
], function ($pdo, $args) {
    $page = isset($args['page']) ? max(1, (int)$args['page']) : 1;
    $size = isset($args['page_size']) ? max(1, min(100, (int)$args['page_size'])) : 20;
    $where = [];
    $params = [];
    if (!empty($args['role'])) {
        $where[] = "role = ?";
        $params[] = (string)$args['role'];
    }
    if (!empty($args['method'])) {
        $where[] = "method = ?";
        $params[] = (string)$args['method'];
    }
    if (!empty($args['tool_name'])) {
        $where[] = "tool_name = ?";
        $params[] = (string)$args['tool_name'];
    }
    if (!empty($args['status'])) {
        $where[] = "status = ?";
        $params[] = (string)$args['status'];
    }
    if (!empty($args['user_id'])) {
        $where[] = "user_id = ?";
        $params[] = (int)$args['user_id'];
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM huli_mcp_logs $whereSql");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    $offset = ($page - 1) * $size;
    $stmt = $pdo->prepare("SELECT id, request_time, role, user_id, username, method, tool_name, ip_address, status, error_msg, latency_ms FROM huli_mcp_logs $whereSql ORDER BY id DESC LIMIT $offset, $size");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return ['total' => $total, 'page' => $page, 'page_size' => $size, 'logs' => $rows];
});
