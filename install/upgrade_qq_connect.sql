-- 已有站点升级脚本：新增 QQ 互联（OAuth 2.0）登录所需的 qq_openid 字段。
-- 用法：宝塔面板 phpMyAdmin 导入，或在站点目录执行：
--   mysql -u用户名 -p 数据库名 < upgrade_qq_connect.sql
-- 幂等：重复执行不报错。

DROP PROCEDURE IF EXISTS `huli_add_qq_openid_column`;
DELIMITER $$
CREATE PROCEDURE `huli_add_qq_openid_column`()
BEGIN
    -- huli_admins.qq_openid
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'huli_admins'
          AND COLUMN_NAME = 'qq_openid'
    ) THEN
        ALTER TABLE `huli_admins`
            ADD COLUMN `qq_openid` varchar(64) NOT NULL DEFAULT '' COMMENT 'QQ互联openid' AFTER `qq`;
    END IF;

    -- huli_users.qq_openid
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'huli_users'
          AND COLUMN_NAME = 'qq_openid'
    ) THEN
        ALTER TABLE `huli_users`
            ADD COLUMN `qq_openid` varchar(64) NOT NULL DEFAULT '' COMMENT 'QQ互联openid' AFTER `qq`;
    END IF;
END$$
DELIMITER ;
CALL `huli_add_qq_openid_column`();
DROP PROCEDURE IF EXISTS `huli_add_qq_openid_column`;
