SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET NAMES utf8mb4;

DROP TABLE IF EXISTS `huli_site_home_templates`;
CREATE TABLE `huli_site_home_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '模板ID',
  `name` varchar(50) NOT NULL COMMENT '模板名称',
  `folder` varchar(50) NOT NULL COMMENT '模板文件夹名',
  `is_active` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否激活',
  `thumbnail` varchar(255) DEFAULT NULL COMMENT '缩略图路径',
  `description` varchar(255) DEFAULT NULL COMMENT '模板描述',
  `created_at` datetime DEFAULT NULL COMMENT '上架时间',
  `updated_at` datetime DEFAULT NULL COMMENT '最后使用时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `folder` (`folder`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='首页模板管理表';

INSERT INTO `huli_site_home_templates` (`id`,`name`,`folder`,`is_active`,`thumbnail`,`description`,`created_at`,`updated_at`) VALUES
(1,'默认首页模板','1',0,'','系统默认首页模板','2026-04-04 00:00:00','2026-04-04 07:52:27'),
(2,'光年UI首页模板','2',1,'','光年UI首页模板','2026-04-04 00:00:00','2026-04-04 07:53:00');

DROP TABLE IF EXISTS `huli_site_user_templates`;
CREATE TABLE `huli_site_user_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '模板ID',
  `name` varchar(50) NOT NULL COMMENT '模板名称',
  `folder` varchar(50) NOT NULL COMMENT '模板文件夹名',
  `is_active` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否激活',
  `thumbnail` varchar(255) DEFAULT NULL COMMENT '缩略图路径',
  `description` varchar(255) DEFAULT NULL COMMENT '模板描述',
  `created_at` datetime DEFAULT NULL COMMENT '上架时间',
  `updated_at` datetime DEFAULT NULL COMMENT '最后更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `folder` (`folder`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='用户中心模板管理表';

INSERT INTO `huli_site_user_templates` (`id`,`name`,`folder`,`is_active`,`thumbnail`,`description`,`created_at`,`updated_at`) VALUES
(1,'默认用户中心模板','1',0,'','系统默认用户中心模板','2026-04-04 00:00:00','2026-04-04 09:01:57'),
(2,'光年UI用户中心模板','2',1,'','光年UI用户中心模板','2026-04-04 00:00:00','2026-04-04 09:03:22');

DROP TABLE IF EXISTS `huli_admins`;
CREATE TABLE `huli_admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '管理员ID',
  `username` varchar(50) NOT NULL COMMENT '管理员用户名',
  `nickname` varchar(100) NOT NULL DEFAULT '管理员' COMMENT '管理员昵称',
  `password` varchar(100) NOT NULL COMMENT '管理员密码',
  `email` varchar(100) NOT NULL COMMENT '管理员邮箱',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT '创建时间',
  `last_login` timestamp NULL DEFAULT NULL COMMENT '最后登录时间',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理员账户表';

INSERT INTO `huli_admins` (`id`,`username`,`password`,`email`,`created_at`,`last_login`,`status`) VALUES
(1,'admin','123456','www@dkewl.com','2026-04-04 00:00:00','2026-04-04 09:03:04',1);

DROP TABLE IF EXISTS `huli_advertisements`;
CREATE TABLE `huli_advertisements` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '广告ID',
  `title` varchar(100) NOT NULL COMMENT '广告标题',
  `link_url` varchar(255) NOT NULL COMMENT '广告链接',
  `contact` varchar(100) DEFAULT NULL COMMENT '联系方式',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active' COMMENT '状态',
  `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT '排序权重',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='广告位管理表';

DROP TABLE IF EXISTS `huli_announcements`;
CREATE TABLE `huli_announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '公告ID',
  `title` varchar(255) NOT NULL COMMENT '公告标题',
  `content` text NOT NULL COMMENT '公告内容',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT '创建时间',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否启用',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统公告表';

INSERT INTO `huli_announcements` (`id`,`title`,`content`,`created_at`,`is_active`) VALUES
(1,'欢迎使用huliapi API管理系统','如接口有失效，点击意见反馈，huliapi','2026-04-25 00:00:00',1);

DROP TABLE IF EXISTS `huli_api_categories`;
CREATE TABLE `huli_api_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '分类ID',
  `name` varchar(50) NOT NULL COMMENT '分类名称',
  `description` varchar(255) DEFAULT NULL COMMENT '分类描述',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='API接口分类表';

INSERT INTO `huli_api_categories` (`id`,`name`,`description`,`created_at`) VALUES
(1,'默认','默认分类','2026-04-04 00:00:00');

DROP TABLE IF EXISTS `huli_market_items`;
CREATE TABLE `huli_market_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '商品ID',
  `api_id` int(11) NOT NULL COMMENT 'API接口ID',
  `price` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '商品价格',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否上架',
  `downloads` int(11) NOT NULL DEFAULT 0 COMMENT '购买次数',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT '创建时间',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `api_id` (`api_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='API市场商品表';

DROP TABLE IF EXISTS `huli_market_purchases`;
CREATE TABLE `huli_market_purchases` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '购买记录ID',
  `item_id` int(11) NOT NULL COMMENT '商品ID',
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `price` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '成交价格',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT '购买时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `item_user` (`item_id`,`user_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='API市场购买记录表';

DROP TABLE IF EXISTS `huli_api_logs`;
CREATE TABLE `huli_api_logs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '日志ID',
  `api_id` int(11) NOT NULL COMMENT 'API接口ID',
  `user_id` int(11) DEFAULT NULL COMMENT '用户ID',
  `ip_address` varchar(45) NOT NULL COMMENT '请求IP',
  `request_time` timestamp NOT NULL DEFAULT current_timestamp() COMMENT '请求时间',
  `response_code` int(4) NOT NULL COMMENT '响应状态码',
  `is_success` tinyint(1) NOT NULL COMMENT '是否成功',
  `billing_type` varchar(20) NOT NULL DEFAULT 'free' COMMENT '计费类型',
  `billing_amount` decimal(10,4) NOT NULL DEFAULT 0.0000 COMMENT '计费金额',
  PRIMARY KEY (`id`),
  KEY `api_id` (`api_id`),
  KEY `user_id` (`user_id`),
  KEY `request_time` (`request_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='API接口调用日志表';

DROP TABLE IF EXISTS `huli_apis`;
CREATE TABLE `huli_apis` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'API接口ID',
  `admin_id` int(11) NOT NULL COMMENT '创建管理员ID',
  `category_id` int(11) DEFAULT NULL COMMENT '分类ID',
  `name` varchar(100) NOT NULL COMMENT 'API名称',
  `description` text DEFAULT NULL COMMENT 'API描述',
  `endpoint` varchar(255) NOT NULL COMMENT '访问端点',
  `method` varchar(10) NOT NULL DEFAULT 'GET' COMMENT '请求方法',
  `type` enum('local','remote') NOT NULL DEFAULT 'local' COMMENT 'API类型',
  `file_path` varchar(255) DEFAULT NULL COMMENT '脚本路径',
  `remote_url` varchar(2048) DEFAULT NULL COMMENT '远程URL',
  `parameters` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '参数配置',
  `status` enum('normal','error','maintenance','deprecated') NOT NULL DEFAULT 'normal' COMMENT '状态',
  `total_calls` bigint(20) NOT NULL DEFAULT 0 COMMENT '累计调用',
  `visibility` varchar(20) NOT NULL DEFAULT 'public' COMMENT '可见性',
  `is_billable` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否计费',
  `price_per_call` decimal(10,4) DEFAULT 0.0000 COMMENT '调用价格',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT '创建时间',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '更新时间',
  `request_example` text DEFAULT NULL COMMENT '请求示例',
  `response_format` varchar(100) NOT NULL DEFAULT 'application/json' COMMENT '响应格式',
  `response_example` text DEFAULT NULL COMMENT '响应示例',
  `points_per_call` int(11) NOT NULL DEFAULT 1 COMMENT '消耗点数',
  PRIMARY KEY (`id`),
  UNIQUE KEY `endpoint` (`endpoint`),
  KEY `category_id` (`category_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='API接口管理表';

DROP TABLE IF EXISTS `huli_billing_plans`;
CREATE TABLE `huli_billing_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '方案ID',
  `name` varchar(255) NOT NULL COMMENT '套餐名称',
  `description` text DEFAULT NULL COMMENT '套餐描述',
  `price` decimal(10,2) NOT NULL COMMENT '套餐售价',
  `billing_type` varchar(20) NOT NULL DEFAULT 'balance' COMMENT '计费类型',
  `balance_to_add` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '充值余额',
  `points_to_add` int(11) NOT NULL DEFAULT 0 COMMENT '充值点数',
  `membership_days` int(11) NOT NULL DEFAULT 0 COMMENT '赠送会员天数',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否上架',
  `is_card` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否卡密',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='计费方案管理表';

INSERT INTO `huli_billing_plans` (`id`,`name`,`description`,`price`,`billing_type`,`balance_to_add`,`points_to_add`,`membership_days`,`is_active`,`is_card`,`created_at`) VALUES
(1,'默认套餐','默认的初始套餐',0.01,'balance',0.01,0,0,1,0,'2026-04-04 00:00:00'),
(2,'超级会员月卡','开通超级会员30天',29.90,'membership',0,0,30,1,0,'2026-04-04 00:00:00'),
(3,'超级会员季卡','开通超级会员90天',79.90,'membership',0,0,90,1,0,'2026-04-04 00:00:00'),
(4,'100点数','充值100点数',10.00,'points',0,100,0,1,0,'2026-04-04 00:00:00');

DROP TABLE IF EXISTS `huli_cdkeys`;
CREATE TABLE `huli_cdkeys` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'CDKEYID',
  `cdkey` varchar(32) NOT NULL COMMENT 'CDKEY码',
  `type` enum('balance','points','membership') NOT NULL DEFAULT 'balance' COMMENT '类型',
  `balance` decimal(10,2) NOT NULL COMMENT '包含余额',
  `points` int(11) NOT NULL DEFAULT 0 COMMENT '包含点数',
  `membership_days` int(11) NOT NULL DEFAULT 0 COMMENT '包含会员天数',
  `status` enum('unused','used') NOT NULL DEFAULT 'unused' COMMENT '状态',
  `used_by_user_id` int(11) DEFAULT NULL COMMENT '使用用户ID',
  `used_at` timestamp NULL DEFAULT NULL COMMENT '使用时间',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `cdkey` (`cdkey`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CDKEY管理表';

DROP TABLE IF EXISTS `huli_feedback`;
CREATE TABLE `huli_feedback` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '反馈ID',
  `user_id` int(11) DEFAULT NULL COMMENT '用户ID',
  `api_id` int(11) DEFAULT NULL COMMENT 'API接口ID',
  `type` enum('api','general') NOT NULL DEFAULT 'general' COMMENT '类型',
  `content` text NOT NULL COMMENT '反馈内容',
  `contact` varchar(255) DEFAULT NULL COMMENT '联系方式',
  `response` text DEFAULT NULL COMMENT '管理员回复',
  `status` enum('pending','viewed','resolved') NOT NULL DEFAULT 'pending' COMMENT '状态',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT '创建时间',
  `responded_at` timestamp NULL DEFAULT NULL COMMENT '回复时间',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户反馈表';

DROP TABLE IF EXISTS `huli_friend_links`;
CREATE TABLE `huli_friend_links` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '友链ID',
  `site_name` varchar(100) NOT NULL COMMENT '网站名称',
  `url` varchar(255) NOT NULL COMMENT '网站URL',
  `description` varchar(255) DEFAULT NULL COMMENT '网站描述',
  `logo` varchar(255) DEFAULT NULL COMMENT '网站LOGO',
  `user_id` int(11) DEFAULT NULL COMMENT '申请用户ID',
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending' COMMENT '审核状态',
  `is_hidden` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否隐藏',
  `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT '排序权重',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT '申请时间',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '更新时间',
  `reviewed_at` timestamp NULL DEFAULT NULL COMMENT '审核时间',
  `review_note` varchar(255) DEFAULT NULL COMMENT '审核备注',
  `status_check` enum('normal','broken') NOT NULL DEFAULT 'normal' COMMENT '链接状态检查结果',
  PRIMARY KEY (`id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='友情链接管理表';

DROP TABLE IF EXISTS `huli_orders`;
CREATE TABLE `huli_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '订单ID',
  `order_id` varchar(64) NOT NULL COMMENT '唯一订单号',
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `plan_id` int(11) DEFAULT NULL COMMENT '方案ID',
  `amount` decimal(10,2) NOT NULL COMMENT '订单金额',
  `status` enum('pending','paid','canceled','failed') NOT NULL DEFAULT 'pending' COMMENT '支付状态',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT '下单时间',
  `paid_at` timestamp NULL DEFAULT NULL COMMENT '支付时间',
  `payment_method` varchar(50) DEFAULT NULL COMMENT '支付方式',
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_id` (`order_id`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单管理表';

DROP TABLE IF EXISTS `huli_qps_logs`;
CREATE TABLE `huli_qps_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '日志ID',
  `api_id` int(11) NOT NULL COMMENT 'API接口ID',
  `user_id` int(11) DEFAULT NULL COMMENT '用户ID',
  `ip_address` varchar(45) NOT NULL COMMENT '请求IP',
  `limit_type` enum('api','user') NOT NULL COMMENT '限流类型',
  `limit_value` int(11) NOT NULL COMMENT 'QPS阈值',
  `current_count` int(11) NOT NULL COMMENT '当前请求数',
  `window_seconds` int(11) NOT NULL DEFAULT 30 COMMENT '时间窗口',
  `blocked` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否阻断',
  `request_time` datetime NOT NULL COMMENT '请求时间',
  PRIMARY KEY (`id`),
  KEY `idx_request_time` (`request_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='QPS限流日志表';

DROP TABLE IF EXISTS `huli_settings`;
CREATE TABLE `huli_settings` (
  `setting_key` varchar(255) NOT NULL COMMENT '设置键',
  `setting_value` longtext DEFAULT NULL COMMENT '设置值',
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统配置表';

INSERT INTO `huli_settings` (`setting_key`,`setting_value`) VALUES
('allow_registration','1'),
('allow_temp_key','1'),
('copyright_info','Copyright 2025-2026 huliapi'),
('epay_key','3n1d7ZaPH9hAPd7GUD8zd9Un8dWNhA38'),
('epay_pid','1714'),
('epay_url','https://pay.liohg.top/'),
('mail_forgot_enabled','1'),
('mail_reg_enabled','1'),
('mail_smtp_host','smtp.qq.com'),
('mail_smtp_pass','uprnfbmvtglpbbci'),
('mail_smtp_port','465'),
('mail_smtp_secure','ssl'),
('mail_smtp_user','726175127@qq.com'),
('payment_alipay_enabled','1'),
('payment_qqpay_enabled','1'),
('payment_wxpay_enabled','1'),
('site_description','huliapi致力于为用户提供稳定、高效的API接口服务'),
('site_name','huliapi'),
('temp_key_duration','24'),
('temp_key_limit','100'),
('enable_free_qps_limit','1'),
('qps_mode','database'),
('redis_host','127.0.0.1'),
('redis_port','6379'),
('redis_password',''),
('redis_database','0'),
('free_qps_seconds','1'),
('free_qps_limit','10'),
('enable_member_qps_limit','1'),
('member_qps_seconds','1'),
('member_qps_limit','20'),
('warn_points_threshold','5'),
('warn_balance_threshold','0.01'),
('enable_warn_notification','1'),
('enable_daily_points_notification','1'),
('enable_daily_points','0'),
('daily_free_points','100');

DROP TABLE IF EXISTS `huli_temp_key_logs`;
CREATE TABLE `huli_temp_key_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '日志ID',
  `ip_address` varchar(45) NOT NULL COMMENT 'IP地址',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `ip_address` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='临时密钥日志表';

DROP TABLE IF EXISTS `huli_transactions`;
CREATE TABLE `huli_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '交易ID',
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `type` varchar(50) NOT NULL COMMENT '交易类型',
  `amount` decimal(10,2) NOT NULL COMMENT '金额',
  `description` text NOT NULL COMMENT '交易描述',
  `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT '状态',
  `transaction_id` varchar(100) DEFAULT NULL COMMENT '交易ID',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='用户交易记录表';

DROP TABLE IF EXISTS `huli_users`;
CREATE TABLE `huli_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '用户ID',
  `username` varchar(50) NOT NULL COMMENT '用户名',
  `password` varchar(100) NOT NULL COMMENT '密码',
  `email` varchar(100) NOT NULL COMMENT '邮箱',
  `api_key` varchar(64) NOT NULL COMMENT 'API密钥',
  `call_count` bigint(20) NOT NULL DEFAULT 0 COMMENT '累计调用次数',
  `balance` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '账户余额',
  `points` int(11) NOT NULL DEFAULT 0 COMMENT '用户点数',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT '创建时间',
  `status` enum('active','banned','pending','inactive') NOT NULL DEFAULT 'pending' COMMENT '状态',
  `membership_level` enum('normal','super') NOT NULL DEFAULT 'normal' COMMENT '会员等级',
  `membership_expire` datetime NULL DEFAULT NULL COMMENT '会员有效期',
  `call_limit` int(11) NOT NULL DEFAULT 0 COMMENT '调用次数限制',
  `expires_at` datetime NULL DEFAULT NULL COMMENT '过期时间',
  `last_points_warn_date` date DEFAULT NULL COMMENT '最后点数提醒日期',
  `last_balance_warn_date` date DEFAULT NULL COMMENT '最后余额提醒日期',
  PRIMARY KEY (`id`),
  UNIQUE KEY `api_key` (`api_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户账户表';

DROP TABLE IF EXISTS `huli_daily_points_claim`;
CREATE TABLE `huli_daily_points_claim` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `claim_date` date NOT NULL COMMENT '领取日期',
  `points_granted` int(11) NOT NULL DEFAULT 0 COMMENT '赠送点数',
  `grant_reason` varchar(50) DEFAULT 'daily_auto' COMMENT '赠送原因',
  `claimed_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT '领取时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_date` (`user_id`,`claim_date`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='每日点数赠送记录表';

DROP TABLE IF EXISTS `huli_daily_stats`;
CREATE TABLE `huli_daily_stats` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '统计ID',
  `stat_date` date NOT NULL COMMENT '统计日期',
  `call_count` int(11) NOT NULL DEFAULT 0 COMMENT '调用次数',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT '创建时间',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `stat_date` (`stat_date`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='每日API调用统计表';

SET FOREIGN_KEY_CHECKS=1;
