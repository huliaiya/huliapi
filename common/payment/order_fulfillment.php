<?php
if (!defined('HULI_ORDER_FULFILLMENT_LOADED')) {
    define('HULI_ORDER_FULFILLMENT_LOADED', true);

    define('HULI_PAYMENT_TTL', 300);
    define('HULI_AFDIAN_ORDER_COLUMNS', [
        'provider' => "ALTER TABLE huli_orders ADD COLUMN `provider` varchar(20) NOT NULL DEFAULT 'afdian' COMMENT '支付提供方'",
        'provider_order_id' => "ALTER TABLE huli_orders ADD COLUMN `provider_order_id` varchar(64) DEFAULT NULL COMMENT '爱发电订单号'",
        'provider_user_id' => "ALTER TABLE huli_orders ADD COLUMN `provider_user_id` varchar(64) DEFAULT NULL COMMENT '爱发电用户ID'",
        'provider_amount' => "ALTER TABLE huli_orders ADD COLUMN `provider_amount` decimal(10,2) DEFAULT NULL COMMENT '爱发电实付金额'",
        'provider_status' => "ALTER TABLE huli_orders ADD COLUMN `provider_status` varchar(20) DEFAULT NULL COMMENT '爱发电原始状态'",
        'provider_paid_at' => "ALTER TABLE huli_orders ADD COLUMN `provider_paid_at` timestamp NULL DEFAULT NULL COMMENT '爱发电支付时间'",
        'match_code' => "ALTER TABLE huli_orders ADD COLUMN `match_code` varchar(32) DEFAULT NULL COMMENT '爱发电备注码'",
        'query_attempts' => "ALTER TABLE huli_orders ADD COLUMN `query_attempts` int(11) NOT NULL DEFAULT 0 COMMENT '查询次数'",
        'last_queried_at' => "ALTER TABLE huli_orders ADD COLUMN `last_queried_at` timestamp NULL DEFAULT NULL COMMENT '最近查询时间'",
        'fulfilled_at' => "ALTER TABLE huli_orders ADD COLUMN `fulfilled_at` timestamp NULL DEFAULT NULL COMMENT '权益发放时间'",
        'failure_reason' => "ALTER TABLE huli_orders ADD COLUMN `failure_reason` varchar(255) DEFAULT NULL COMMENT '失败原因'",
    ]);
    define('HULI_AFDIAN_ORDER_UNIQUE_INDEXES', [
        'uk_provider_order' => "CREATE UNIQUE INDEX `uk_provider_order` ON huli_orders (`provider`, `provider_order_id`)",
        'uk_match_code' => "CREATE UNIQUE INDEX `uk_match_code` ON huli_orders (`match_code`)",
    ]);

    function huli_ensure_afdian_order_columns(PDO $pdo)
    {
        try {
            $existing = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'huli_orders'")->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            return;
        }
        try {
            $statusColumn = $pdo->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'huli_orders' AND COLUMN_NAME = 'status'")->fetchColumn();
            if ($statusColumn && strpos($statusColumn, 'canceled') === false) {
                $pdo->exec("ALTER TABLE huli_orders MODIFY COLUMN `status` enum('pending','paid','canceled','failed') NOT NULL DEFAULT 'pending' COMMENT '支付状态'");
            }
        } catch (Exception $e) {
        }
        foreach (HULI_AFDIAN_ORDER_COLUMNS as $column => $sql) {
            if (!in_array($column, $existing, true)) {
                $pdo->exec($sql);
            }
        }
        try {
            $indexes = $pdo->query("SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'huli_orders'")->fetchAll(PDO::FETCH_COLUMN);
            foreach (HULI_AFDIAN_ORDER_UNIQUE_INDEXES as $name => $sql) {
                if (!in_array($name, $indexes, true)) {
                    $pdo->exec($sql);
                }
            }
        } catch (Exception $e) {
        }
    }

    function huli_get_afdian_config(PDO $pdo)
    {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM huli_settings WHERE setting_key IN ('afdian_user_id', 'afdian_token', 'afdian_page_url')");
        $config = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        return [
            'user_id' => trim($config['afdian_user_id'] ?? ''),
            'token' => trim($config['afdian_token'] ?? ''),
            'page_url' => trim($config['afdian_page_url'] ?? ''),
        ];
    }

    function huli_is_afdian_configured(array $config)
    {
        return $config['user_id'] !== '' && $config['token'] !== '' && $config['page_url'] !== '';
    }

    function huli_generate_match_code(PDO $pdo)
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $maxAttempts = 8;
        for ($i = 0; $i < $maxAttempts; $i++) {
            $code = 'R';
            for ($j = 0; $j < 7; $j++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM huli_orders WHERE match_code = ?");
            $stmt->execute([$code]);
            if ((int)$stmt->fetchColumn() === 0) {
                return $code;
            }
        }
        throw new Exception('备注码生成失败，请重试');
    }

    function huli_grant_plan_benefits(PDO $pdo, array $plan, $userId)
    {
        $updated = false;
        if ((float)$plan['balance_to_add'] > 0) {
            $stmt = $pdo->prepare("UPDATE huli_users SET balance = balance + ? WHERE id = ?");
            $stmt->execute([$plan['balance_to_add'], $userId]);
            $updated = true;
        }
        if ((int)$plan['points_to_add'] > 0) {
            $stmt = $pdo->prepare("UPDATE huli_users SET points = points + ? WHERE id = ?");
            $stmt->execute([$plan['points_to_add'], $userId]);
            $updated = true;
        }
        if ((int)$plan['membership_days'] > 0) {
            $stmt = $pdo->prepare("SELECT membership_expire FROM huli_users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user && $user['membership_expire'] && strtotime($user['membership_expire']) > time()) {
                $expireExpression = "DATE_ADD(membership_expire, INTERVAL ? DAY)";
            } else {
                $expireExpression = "DATE_ADD(NOW(), INTERVAL ? DAY)";
            }
            $stmt = $pdo->prepare("UPDATE huli_users SET membership_level = 'super', membership_expire = $expireExpression WHERE id = ?");
            $stmt->execute([$plan['membership_days'], $userId]);
            $updated = true;
        }
        return $updated;
    }

    function huli_mark_order_paid_manual(PDO $pdo, $orderId)
    {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT * FROM huli_orders WHERE id = ? AND status = 'pending' FOR UPDATE");
            $stmt->execute([intval($orderId)]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                $pdo->rollBack();
                return ['ok' => false, 'message' => '订单不存在或已处理'];
            }
            $stmt = $pdo->prepare("SELECT billing_type, balance_to_add, points_to_add, membership_days FROM huli_billing_plans WHERE id = ?");
            $stmt->execute([$order['plan_id']]);
            $plan = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$plan) {
                $pdo->rollBack();
                return ['ok' => false, 'message' => '找不到对应的计费方案'];
            }
            $granted = huli_grant_plan_benefits($pdo, $plan, $order['user_id']);
            if (!$granted) {
                $pdo->rollBack();
                return ['ok' => false, 'message' => '该套餐不包含可发放的权益'];
            }
            $stmt = $pdo->prepare("UPDATE huli_orders SET status = 'paid', paid_at = CURRENT_TIMESTAMP, payment_method = 'manual', fulfilled_at = CURRENT_TIMESTAMP, failure_reason = NULL WHERE id = ?");
            $stmt->execute([$order['id']]);
            $pdo->commit();
            return ['ok' => true, 'message' => '订单已标记为已支付，权益已发放'];
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[order_fulfillment.php] 入账失败: ' . $e->getMessage());
            return ['ok' => false, 'message' => '入账失败，请稍后重试'];
        }
    }

    function huli_fulfill_afdian_order(PDO $pdo, $orderId, array $afdianOrder)
    {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT * FROM huli_orders WHERE id = ? AND status = 'pending' FOR UPDATE");
            $stmt->execute([intval($orderId)]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                $pdo->rollBack();
                return ['ok' => false, 'status' => 'processed', 'message' => '订单不存在或已处理'];
            }

            $outTradeNo = trim((string)($afdianOrder['out_trade_no'] ?? ''));
            $paidAmount = (float)($afdianOrder['total_amount'] ?? 0) / 100;
            $expectedAmount = (float)$order['amount'];
            if (abs($paidAmount - $expectedAmount) > 0.01) {
                $pdo->rollBack();
                return ['ok' => false, 'status' => 'pending', 'message' => '爱发电实付金额与订单金额不一致'];
            }
            if ($outTradeNo !== '') {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM huli_orders WHERE provider = 'afdian' AND provider_order_id = ? AND id <> ?");
                $stmt->execute([$outTradeNo, $order['id']]);
                if ((int)$stmt->fetchColumn() > 0) {
                    $pdo->rollBack();
                    return ['ok' => false, 'status' => 'pending', 'message' => '该爱发电订单已被其他充值订单使用'];
                }
            }

            $stmt = $pdo->prepare("SELECT billing_type, balance_to_add, points_to_add, membership_days FROM huli_billing_plans WHERE id = ?");
            $stmt->execute([$order['plan_id']]);
            $plan = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$plan) {
                $pdo->rollBack();
                return ['ok' => false, 'status' => 'pending', 'message' => '计费方案不存在或已下架'];
            }

            $granted = huli_grant_plan_benefits($pdo, $plan, $order['user_id']);
            if (!$granted) {
                $pdo->rollBack();
                return ['ok' => false, 'status' => 'pending', 'message' => '该套餐不包含可发放的权益'];
            }

            $stmt = $pdo->prepare("UPDATE huli_orders SET status = 'paid', paid_at = CURRENT_TIMESTAMP, payment_method = 'afdian', provider = 'afdian', provider_order_id = ?, provider_user_id = ?, provider_amount = ?, provider_status = ?, provider_paid_at = ?, fulfilled_at = CURRENT_TIMESTAMP, failure_reason = NULL WHERE id = ?");
            $providerPaidAt = isset($afdianOrder['pay_time']) && $afdianOrder['pay_time'] !== '' ? date('Y-m-d H:i:s', (int)$afdianOrder['pay_time']) : null;
            $stmt->execute([
                $outTradeNo !== '' ? $outTradeNo : null,
                trim((string)($afdianOrder['user_id'] ?? '')),
                $paidAmount,
                (string)($afdianOrder['status'] ?? ''),
                $providerPaidAt,
                $order['id'],
            ]);
            $pdo->commit();
            return ['ok' => true, 'status' => 'paid', 'message' => '充值成功'];
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[order_fulfillment.php] 入账失败: ' . $e->getMessage());
            return ['ok' => false, 'status' => 'pending', 'message' => '入账失败，请稍后重试'];
        }
    }
}
