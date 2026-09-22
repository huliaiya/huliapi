-- 已有站点升级脚本：为「在线更新」重要数据落库补两张表。
-- 用法：宝塔面板 phpMyAdmin 导入，或在站点目录执行：
--   mysql -u用户名 -p 数据库名 < upgrade_updater_db_tables.sql
-- 幂等：重复执行不报错。新装站点无需执行（install/install.sql 已内置）。

CREATE TABLE IF NOT EXISTS `huli_update_jobs` (
  `token` varchar(40) NOT NULL COMMENT '任务令牌',
  `payload` mediumtext NOT NULL COMMENT '任务 JSON（info/site_url/admin_id）',
  `updated_at` int(10) unsigned NOT NULL COMMENT '更新时间戳',
  PRIMARY KEY (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='在线更新任务（重要数据落库）';

CREATE TABLE IF NOT EXISTS `huli_update_history` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `token` varchar(40) NOT NULL COMMENT '任务令牌',
  `new_version` varchar(32) NOT NULL DEFAULT '' COMMENT '更新后版本',
  `old_version` varchar(32) NOT NULL DEFAULT '' COMMENT '更新前版本',
  `result` enum('success','failed') NOT NULL DEFAULT 'success' COMMENT '结果',
  `detail` varchar(2000) NOT NULL DEFAULT '' COMMENT '结果详情',
  `ip_address` varchar(45) NOT NULL DEFAULT '' COMMENT '触发 IP',
  `created_at` datetime NOT NULL COMMENT '完成时间',
  PRIMARY KEY (`id`),
  KEY `idx_created` (`created_at`),
  KEY `idx_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='在线更新历史（重要审计数据）';
