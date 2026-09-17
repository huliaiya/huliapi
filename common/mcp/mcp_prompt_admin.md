# huliapi MCP 接入指令（管理员）

本文档由 huliapi 服务端固定文件提供，AI Agent 读取本文后请严格按照下方步骤完成 MCP 接入。

## MCP 服务信息
- 服务名称：`{{SERVER_NAME}}`
- 角色：`{{ROLE_NAME}}`
- 服务地址（支持 Streamable HTTP 与 SSE 双协议）：`{{MCP_URL}}`
- 鉴权方式：请求头 `Authorization: Bearer {{TOKEN}}`
- TLS：使用标准 HTTPS，无需额外处理证书
- 可用工具数量：`{{TOOL_COUNT}}`

## 可用工具（全量）

- `get_system_stats`：获取系统核心运营数据：今日/昨日调用、总调用量、用户数、API 数、订单数、待处理反馈等
- `list_users`：查询用户列表，支持按关键词搜索（用户名/邮箱/QQ）和分页
- `get_user_detail`：获取单个用户的完整信息：账号资料、余额、积分、会员、调用统计、最近订单与交易（不返回密码/api_key 等敏感字段）
- `adjust_user_balance`：调整指定用户的余额（可为正数充值，负数扣减）。调整会写入余额变动记录
- `adjust_user_points`：调整指定用户的积分（可为正数增加，负数扣减）
- `set_user_status`：设置用户账号状态（active 正常 / banned 封禁 / pending 待审核 / inactive 停用）
- `list_apis`：查询平台 API 列表，可按状态过滤，含调用量、计费信息
- `set_api_status`：修改 API 运行状态（normal 正常 / error 异常 / maintenance 维护中 / deprecated 已废弃）
- `list_orders`：查询订单列表，可按状态过滤
- `get_server_status`：获取服务器运行状态：PHP 版本、数据库版本、系统信息、磁盘使用、内存等
- `list_feedback`：查询用户反馈列表，可过滤待处理状态
- `respond_feedback`：回复用户反馈，回复后反馈状态变为已解决
- `list_cdkeys`：查询卡密列表，可按状态（未使用/已使用）过滤
- `create_cdkey`：批量生成充值卡密，支持余额/积分/会员天数三种类型
- `list_billing_plans`：查询充值套餐列表（全量，含未上架），支持按状态过滤
- `set_billing_plan_status`：上架/下架充值套餐
- `list_announcements`：查询平台公告列表
- `create_announcement`：发布一条平台公告
- `set_announcement_status`：启用/停用平台公告
- `list_api_logs`：查询 API 调用日志，可按用户或结果过滤，包含计费信息
- `list_transactions`：查询用户余额/积分变动记录（全平台），可按用户过滤
- `get_mcp_stats`：获取 MCP 服务请求统计：今日请求数、总请求数、成功率、按方法/工具/角色分布、最近 7 天趋势
- `list_mcp_logs`：查询 MCP 请求历史日志，可按角色/方法/工具/状态/用户过滤
- `update_system_to_latest`：将系统/客户端后台更新到 GitHub 最新可用版本：先检测当前版本与最新版本（续用 huli_detect_update_info），若有可用更新则创建后台更新任务（token）并立即返回；任务在后台逐步下载→解压→覆盖文件→更新版本号，执行完成后会向管理员邮箱发送电子邮件通知。返回 task token，可用 update_system_task_status 查询进度与最终结果。
- `update_system_task_status`：查询 update_system_to_latest 创建的系统更新任务进度与最终结果：返回任务状态（running/success/failed）、当前执行阶段、进度百分比、进度消息、已完成版本、当前/目标版本，以及是否已向管理员邮箱发送完成通知邮件与通知内容。

## 配置步骤
1. 确认你自身 Agent 的 MCP 配置方式（不确定时先查阅你自身的官方文档或搜索「<你的名称> MCP http 配置」，**严禁硬编码路径与格式**）。
2. 按你自身支持的 MCP 客户端配置格式，添加名为 `{{SERVER_NAME}}` 的 MCP 服务：
   - url：`{{MCP_URL}}`
   - 请求头 Authorization：`Bearer {{TOKEN}}`
3. 重启或重新加载 MCP 客户端，使配置生效。
4. 验证工具列表：应能列出 `{{TOOL_COUNT}}` 个工具，且无 TLS / 401 等错误。
5. 调用一个只读工具（例如 `get_system_stats` / `get_mcp_stats`）确认连通性。

## 验证
- [ ] 配置文件中 url、Authorization 与本文档一致
- [ ] 客户端能成功列出全部 `{{TOOL_COUNT}}` 个工具
- [ ] 至少一个只读工具可正常调用并返回数据
- [ ] 用户 Token 与管理员 Token 已严格隔离（用户 Token 调用管理员工具应返回 `Unknown tool`）

## 完成
配置成功且验证通过后，简短输出确认（含服务地址、工具数量、接入方式），并对所有敏感信息（Token、密钥、密码）做脱敏展示。
