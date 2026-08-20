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
        $where = "WHERE status = ?";
        $params = [(string)$args['status']];
    }
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM huli_orders $where");
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
        'status' => ['type' => 'string', 'enum' => ['pending', 'processed'], 'description' => '按状态过滤'],
        'page' => ['type' => 'integer', 'description' => '页码，默认 1'],
        'page_size' => ['type' => 'integer', 'description' => '每页条数，默认 20，最大 100'],
    ],
], function ($pdo, $args) {
    $page = isset($args['page']) ? max(1, (int)$args['page']) : 1;
    $size = isset($args['page_size']) ? max(1, min(100, (int)$args['page_size'])) : 20;
    $where = '';
    $params = [];
    if (!empty($args['status'])) {
        $where = "WHERE status = ?";
        $params = [(string)$args['status']];
    }
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM huli_feedback $where");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    $offset = ($page - 1) * $size;
    $stmt = $pdo->prepare("SELECT f.id, f.user_id, u.username, f.content, f.status, f.created_at FROM huli_feedback f LEFT JOIN huli_users u ON u.id = f.user_id $where ORDER BY f.id DESC LIMIT $offset, $size");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return ['total' => $total, 'page' => $page, 'page_size' => $size, 'feedback' => $rows];
});
