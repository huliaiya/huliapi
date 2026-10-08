-- 已有站点升级脚本：新增后台管理员「7天内自动登录」令牌表。
-- 用法：宝塔面板 phpMyAdmin 导入，或在站点目录执行：
--   mysql -u用户名 -p 数据库名 < upgrade_admin_remember.sql
-- 幂等：重复执行不报错。站点代码也会在检测到自动登录 Cookie 时自动建表。

CREATE TABLE IF NOT EXISTS `huli_admin_remember` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '令牌ID',
  `admin_id` int(11) NOT NULL COMMENT '管理员ID',
  `selector` char(16) NOT NULL COMMENT '选择器',
  `token_hash` char(64) NOT NULL COMMENT '验证令牌哈希',
  `expires_at` datetime NOT NULL COMMENT '过期时间',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT '创建时间',
  `ip` varchar(45) DEFAULT NULL COMMENT '签发IP',
  `user_agent` varchar(255) DEFAULT NULL COMMENT '签发UA',
  PRIMARY KEY (`id`),
  UNIQUE KEY `selector` (`selector`),
  KEY `admin_id` (`admin_id`),
  KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='后台管理员自动登录令牌表';
