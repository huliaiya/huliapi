-- 已有站点升级脚本：为友链表增加「申请者联系邮箱」字段（选填）。
-- 用法：宝塔面板 phpMyAdmin 导入，或在站点目录执行：
--   mysql -u用户名 -p 数据库名 < upgrade_friend_links_email.sql
-- 幂等：重复执行不报错。站点代码也会在首次访问友链页面时自动补列。

DELIMITER $$
DROP PROCEDURE IF EXISTS upgrade_friend_links_email $$
CREATE PROCEDURE upgrade_friend_links_email()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'huli_friend_links'
          AND COLUMN_NAME = 'email'
    ) THEN
        ALTER TABLE `huli_friend_links`
            ADD COLUMN `email` varchar(100) DEFAULT NULL COMMENT '申请者联系邮箱（选填）' AFTER `user_id`;
    END IF;
END $$
DELIMITER ;
CALL upgrade_friend_links_email();
DROP PROCEDURE IF EXISTS upgrade_friend_links_email;
