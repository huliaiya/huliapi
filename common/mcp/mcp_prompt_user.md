# huliapi MCP 接入指令（用户）

本文档由 huliapi 服务端固定文件提供，AI Agent 读取本文后请严格按照下方步骤完成 MCP 接入。

## MCP 服务信息
- 服务名称：`{{SERVER_NAME}}`
- 角色：`{{ROLE_NAME}}`
- 服务地址（支持 Streamable HTTP 与 SSE 双协议）：`{{MCP_URL}}`
- 鉴权方式：请求头 `Authorization: Bearer {{TOKEN}}`
- TLS：使用标准 HTTPS，无需额外处理证书
- 可用工具数量：`{{TOOL_COUNT}}`

## 可用工具（全量）

- `get_account_info`：获取当前账号的完整信息：用户名、邮箱、余额、积分、会员等级、账号状态、累计调用次数等
- `get_api_key`：获取当前账号的 API Key（用于调用本平台接口鉴权）
- `reset_api_key`：重置当前账号的 API Key。重置后旧 Key 立即失效，请妥善保存新 Key
- `list_apis`：列出当前可用的 API 列表（状态为正常的），包含接口名称、描述、调用示例
- `get_api_detail`：获取单个 API 的详细信息：参数列表、调用示例、返回示例
- `call_api`：以当前账号身份调用一个本地 API，自动附加 api_key 鉴权并走平台计费流程。参数 params 为传给接口的参数对象
- `get_call_stats`：获取当前账号的调用统计：今日调用、总调用次数、最近调用记录
- `get_transactions`：获取当前账号的余额/积分变动记录（充值、消费、赠送等）
- `list_my_orders`：获取当前账号的订单列表（充值套餐订单），可按状态过滤
- `list_billing_plans`：获取当前上架的充值套餐列表（余额充值/积分/会员套餐）
- `get_announcements`：获取平台公告列表（仅已发布状态）
- `submit_feedback`：向平台提交一条反馈/工单，可关联某个 API
- `list_my_feedback`：获取当前账号提交的反馈列表及管理员回复
- `redeem_cdkey`：兑换充值卡密（CDKEY），卡密可包含余额/积分/会员天数，兑换成功后权益直接到账

## 配置步骤
1. 确认你自身 Agent 的 MCP 配置方式（不确定时先查阅你自身的官方文档或搜索「<你的名称> MCP http 配置」，**严禁硬编码路径与格式**）。
2. 按你自身支持的 MCP 客户端配置格式，添加名为 `{{SERVER_NAME}}` 的 MCP 服务：
   - url：`{{MCP_URL}}`
   - 请求头 Authorization：`Bearer {{TOKEN}}`
3. 重启或重新加载 MCP 客户端，使配置生效。
4. 验证工具列表：应能列出 `{{TOOL_COUNT}}` 个工具，且无 TLS / 401 等错误。
5. 调用一个只读工具（例如 `get_account_info` / `get_call_stats`）确认连通性。

## 验证
- [ ] 配置文件中 url、Authorization 与本文档一致
- [ ] 客户端能成功列出全部 `{{TOOL_COUNT}}` 个工具
- [ ] 至少一个只读工具可正常调用并返回数据
- [ ] 用户 Token 与管理员 Token 已严格隔离（用户 Token 调用管理员工具应返回 `Unknown tool`）

## 完成
配置成功且验证通过后，简短输出确认（含服务地址、工具数量、接入方式），并对所有敏感信息（Token、密钥、密码）做脱敏展示。
