-- 幂等升级脚本：为高频查询表补充缺失索引（2026-10-10）
-- 用法：mysql -u<user> -p <db> < install/upgrade_db_indexes.sql
-- 所有语句均为条件执行，可重复运行。

-- huli_apis：文档页/列表页按 endpoint 查单条 + 按 status 过滤是热点路径
SET @s = IF(EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='huli_apis' AND index_name='idx_endpoint_status'), 'SELECT 1', 'CREATE INDEX idx_endpoint_status ON huli_apis (endpoint, status)');
PREPARE stt FROM @s; EXECUTE stt; DEALLOCATE PREPARE stt;

-- huli_qps_logs：QPS 限流按 request_time 窗口聚合统计，已有单列索引，补 api_id 联合索引
SET @s = IF(EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='huli_qps_logs' AND index_name='idx_api_time'), 'SELECT 1', 'CREATE INDEX idx_api_time ON huli_qps_logs (api_id, request_time)');
PREPARE stt FROM @s; EXECUTE stt; DEALLOCATE PREPARE stt;

-- huli_transactions：用户账单/流水按 user_id + created_at 查询
SET @s = IF(EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='huli_transactions' AND index_name='idx_user_time'), 'SELECT 1', 'CREATE INDEX idx_user_time ON huli_transactions (user_id, created_at)');
PREPARE stt FROM @s; EXECUTE stt; DEALLOCATE PREPARE stt;

-- huli_feedback：后台按 user_id + status 筛选
SET @s = IF(EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='huli_feedback' AND index_name='idx_user_status'), 'SELECT 1', 'CREATE INDEX idx_user_status ON huli_feedback (user_id, status)');
PREPARE stt FROM @s; EXECUTE stt; DEALLOCATE PREPARE stt;

-- huli_cdkeys：兑换校验按 used_by_user_id 查询使用记录
SET @s = IF(EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='huli_cdkeys' AND index_name='idx_used_by'), 'SELECT 1', 'CREATE INDEX idx_used_by ON huli_cdkeys (used_by_user_id)');
PREPARE stt FROM @s; EXECUTE stt; DEALLOCATE PREPARE stt;

-- huli_market_items：市场列表按 api_id + status 筛选
SET @s = IF(EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='huli_market_items' AND index_name='idx_api_status'), 'SELECT 1', 'CREATE INDEX idx_api_status ON huli_market_items (api_id, status)');
PREPARE stt FROM @s; EXECUTE stt; DEALLOCATE PREPARE stt;
