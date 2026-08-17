-- 爱发电主动查询充值升级脚本（可重复执行）
-- 用法: mysql -u<user> -p<pass> <dbname> < upgrade_afdian.sql

DELIMITER $$

DROP PROCEDURE IF EXISTS upgrade_afdian_orders $$
CREATE PROCEDURE upgrade_afdian_orders()
BEGIN
    -- 扩展订单表状态枚举，保证支持 canceled
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'huli_orders'
          AND COLUMN_NAME = 'status'
          AND COLUMN_TYPE NOT LIKE '%canceled%'
    ) THEN
        ALTER TABLE huli_orders MODIFY COLUMN `status` enum('pending','paid','canceled','failed') NOT NULL DEFAULT 'pending' COMMENT '支付状态';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'huli_orders' AND COLUMN_NAME = 'provider') THEN
        ALTER TABLE huli_orders ADD COLUMN `provider` varchar(20) NOT NULL DEFAULT 'afdian' COMMENT '支付提供方';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'huli_orders' AND COLUMN_NAME = 'provider_order_id') THEN
        ALTER TABLE huli_orders ADD COLUMN `provider_order_id` varchar(64) DEFAULT NULL COMMENT '爱发电订单号';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'huli_orders' AND COLUMN_NAME = 'provider_user_id') THEN
        ALTER TABLE huli_orders ADD COLUMN `provider_user_id` varchar(64) DEFAULT NULL COMMENT '爱发电用户ID';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'huli_orders' AND COLUMN_NAME = 'provider_amount') THEN
        ALTER TABLE huli_orders ADD COLUMN `provider_amount` decimal(10,2) DEFAULT NULL COMMENT '爱发电实付金额';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'huli_orders' AND COLUMN_NAME = 'provider_status') THEN
        ALTER TABLE huli_orders ADD COLUMN `provider_status` varchar(20) DEFAULT NULL COMMENT '爱发电原始状态';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'huli_orders' AND COLUMN_NAME = 'provider_paid_at') THEN
        ALTER TABLE huli_orders ADD COLUMN `provider_paid_at` timestamp NULL DEFAULT NULL COMMENT '爱发电支付时间';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'huli_orders' AND COLUMN_NAME = 'match_code') THEN
        ALTER TABLE huli_orders ADD COLUMN `match_code` varchar(32) DEFAULT NULL COMMENT '爱发电备注码';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'huli_orders' AND COLUMN_NAME = 'query_attempts') THEN
        ALTER TABLE huli_orders ADD COLUMN `query_attempts` int(11) NOT NULL DEFAULT 0 COMMENT '查询次数';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'huli_orders' AND COLUMN_NAME = 'last_queried_at') THEN
        ALTER TABLE huli_orders ADD COLUMN `last_queried_at` timestamp NULL DEFAULT NULL COMMENT '最近查询时间';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'huli_orders' AND COLUMN_NAME = 'fulfilled_at') THEN
        ALTER TABLE huli_orders ADD COLUMN `fulfilled_at` timestamp NULL DEFAULT NULL COMMENT '权益发放时间';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'huli_orders' AND COLUMN_NAME = 'failure_reason') THEN
        ALTER TABLE huli_orders ADD COLUMN `failure_reason` varchar(255) DEFAULT NULL COMMENT '失败原因';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'huli_orders' AND INDEX_NAME = 'uk_provider_order') THEN
        ALTER TABLE huli_orders ADD UNIQUE KEY `uk_provider_order` (`provider`,`provider_order_id`);
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'huli_orders' AND INDEX_NAME = 'uk_match_code') THEN
        ALTER TABLE huli_orders ADD UNIQUE KEY `uk_match_code` (`match_code`);
    END IF;
END $$

DELIMITER ;

CALL upgrade_afdian_orders();
DROP PROCEDURE IF EXISTS upgrade_afdian_orders;

-- 系统配置：替换易支付配置为爱发电配置（保留原配置值不覆盖，仅初始化缺失项）
INSERT IGNORE INTO `huli_settings` (`setting_key`,`setting_value`) VALUES
('afdian_user_id',''),
('afdian_token',''),
('afdian_page_url','');
