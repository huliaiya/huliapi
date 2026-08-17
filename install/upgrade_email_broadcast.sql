-- 邮件群发灵活调度升级脚本（可重复执行）
-- 用法: mysql -u<user> -p<pass> <dbname> < upgrade_email_broadcast.sql

DELIMITER $$

DROP PROCEDURE IF EXISTS upgrade_email_broadcast $$
CREATE PROCEDURE upgrade_email_broadcast()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'huli_email_broadcasts' AND COLUMN_NAME = 'send_type') THEN
        ALTER TABLE huli_email_broadcasts ADD COLUMN `send_type` enum('once','daily') NOT NULL DEFAULT 'once' COMMENT '发送模式：once=仅一次 daily=每日定时';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'huli_email_broadcasts' AND COLUMN_NAME = 'last_run_at') THEN
        ALTER TABLE huli_email_broadcasts ADD COLUMN `last_run_at` datetime DEFAULT NULL COMMENT '最近一次实际发送时间';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'huli_email_broadcasts' AND COLUMN_NAME = 'last_error') THEN
        ALTER TABLE huli_email_broadcasts ADD COLUMN `last_error` varchar(500) DEFAULT NULL COMMENT '最近一次发送错误信息';
    END IF;
END $$

DELIMITER ;

CALL upgrade_email_broadcast();
DROP PROCEDURE IF EXISTS upgrade_email_broadcast;
