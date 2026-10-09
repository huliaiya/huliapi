INSERT INTO huli_apis (admin_id, category_id, name, description, endpoint, method, type, file_path, parameters, status, visibility, is_billable, request_example, response_format, points_per_call)
SELECT 1, 1, '全站调用统计', '统计全站所有接口的全部调用量信息，包括汇总、按接口、按天、按用户与状态码分布', 'site_stats', 'GET', 'local', 'API/site_stats.php', '[{"name":"days","type":"int","required":"no","desc":"按天趋势的天数范围，默认30，最大365"}]', 'normal', 'public', 0, '/API/site_stats.php?days=30', 'application/json', 0
WHERE NOT EXISTS (SELECT 1 FROM huli_apis WHERE endpoint = 'site_stats');
