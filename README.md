# huliapi 安装版（main 分支）

版本：1.6.0  
运行环境：PHP 8.0+、MySQL 5.7+ 或 MariaDB 10.3+

`main` 分支是 huliapi 的完整安装分支，用于首次部署和初始化站点。这个分支包含安装入口、数据库初始化 SQL、默认配置文件和完整业务代码。

---

## 分支说明

| 分支 | 用途 | 说明 |
|------|------|------|
| `main` | 安装版 | 用于首次部署，包含 `config.php`、`install/`、初始化 SQL 和完整安装入口。 |
| `miao` | 更新版 | 用于已安装站点更新，保留运行配置，避免覆盖本地安装状态。 |

首次部署使用 `main` 分支。已有站点更新使用 `miao` 分支。

---

## 功能概览

- API 接口管理与分类管理。
- 用户注册、登录、余额、套餐、充值和卡密兑换。
- 管理员后台仪表盘、订单管理、用户管理、接口管理。
- 邮件群发与定时群发。
- 多通道推送通知，支持邮件、企业微信、钉钉、飞书、Bark、Webhook。
- 管理员系统级推送配置与用户级独立推送配置。
- 用户登录日志、管理员登录日志与安全审计。
- Cloudflare Turnstile 人机验证。
- 首页模板和用户中心模板管理。

---

## 环境要求

- PHP 8.0 或更高版本。
- MySQL 5.7+ 或 MariaDB 10.3+。
- Nginx 或 Apache。
- PHP 扩展：PDO、pdo_mysql、curl、openssl、mbstring、gd。
- 推荐启用 PHP OPcache。

---

## 安装流程

### 1. 克隆安装版分支

```bash
git clone -b main https://github.com/huliaiya/huliapi.git
cd huliapi
```

### 2. 配置 Web 服务器

将站点根目录指向项目目录。Nginx 示例：

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/huliapi;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 3. 设置目录权限

```bash
chmod -R 755 .
chmod 644 config.php
```

根据服务器环境，确保 Web 运行用户可写入安装过程需要更新的配置文件。

### 4. 运行安装向导

浏览器访问：

```text
http://your-domain.com/install/
```

按安装向导填写数据库连接信息、站点信息和管理员账号。

### 5. 安装完成处理

安装完成后会生成安装锁。生产环境建议限制 `install/` 访问权限，或在确认后移除安装入口以提高安全性。

---

## 安装版文件组成

`main` 分支主要包含：

- `config.php`：默认配置文件，安装流程会写入数据库连接等配置。
- `install/`：安装入口与初始化 SQL。
- `admin/`：后台管理功能。
- `API/`：接口入口。
- `common/`：公共函数、支付、推送、安全、模板管理逻辑。
- `template/home/huli/`：首页模板。
- `template/user/huli/`：用户中心模板。
- `assets/`：静态资源。
- `cli/`：定时任务脚本。

---

## 更新方式

已安装站点请通过后台「系统管理 -> 系统更新」执行更新。更新来源为 `miao` 分支。

更新版分支用于覆盖业务代码和模板文件，同时保留本地配置和安装状态。

手动更新时请使用 `miao` 分支，并保留现有站点的 `config.php` 和安装状态文件。

---

## 本版本重点功能

- 管理员系统级多通道推送配置。
- 用户级独立推送通知配置。
- 支持邮件、企业微信、钉钉、飞书、Bark、Webhook 六类通道。
- 用户中心新增推送通知设置页。
- 用户登录日志页支持 IP、地理位置、ISP、UA 横向滚动查看。
- 登录成功后记录 IP 查询接口调用。
- 首页模板目录统一为 `template/home/huli/`。
- 用户中心模板目录统一为 `template/user/huli/`。
- 图形验证码已移除，仅保留 Cloudflare Turnstile。

---

## 首次配置清单

安装完成后进入后台「系统设置」检查以下内容：

- Turnstile Site Key 和 Secret Key。两个 Key 必须来自同一个 Turnstile 站点，并在 Cloudflare 的 Hostname Management 中授权本站域名。
- SMTP 主机、端口、账号、密码和加密方式。
- 支付配置。
- ICP 备案号和公安备案号。
- favicon URL。
- 推送通道配置。
- 首页模板和用户中心模板。

管理员启用某个推送通道后，用户中心才会显示对应配置项。

---

## 推送通知说明

推送配置分为两层：

- 管理员系统级配置：后台「系统设置 -> 基础设置」底部配置各通道基础参数。
- 用户级配置：用户中心「推送通知」页面配置个人接收地址和触发事件。

通道能力：

- 邮件：管理员可测试，用户端提供接收配置。
- 企业微信：支持 Webhook。
- 钉钉：支持 Webhook。
- 飞书：支持 Webhook。
- Bark：支持 Device Key。
- Webhook：支持自定义 HTTP 回调地址。

---

## 模板说明

当前模板目录使用语义化命名：

- 首页模板：`template/home/huli/`
- 用户中心模板：`template/user/huli/`

数据库模板表中的 `folder` 字段必须与目录名称一致。旧版本中的纯数字目录名已经统一迁移为 `huli`。

---

## 安全说明

- 后台登录使用 bcrypt 密码哈希。
- 登录成功后刷新 Session ID，降低会话固定风险。
- 数据库操作使用 PDO 预处理语句。
- 输出内容使用 `htmlspecialchars()` 转义。
- 人机验证使用 Cloudflare Turnstile。
- 管理员登录日志和用户登录日志独立记录。

## MCP 服务

huliapi 内置 Model Context Protocol (MCP) 服务，支持 Streamable HTTP 与 SSE 两种传输协议，可供 Claude Desktop、Cursor、Dify 等支持 MCP 的客户端接入。

### 服务地址

`/mcp.php`（例如 `https://你的域名/mcp.php`），协议版本 `2024-11-05`，使用 Bearer Token 鉴权。

### 用户端与管理员端隔离

- **用户 MCP**：在「用户中心 - MCP 配置」页面生成 Token。包含账户信息查询、API 调用、调用统计、交易记录、订单查询、套餐浏览、卡密兑换、反馈提交、公告查看等 14 个用户工具。
- **管理员 MCP**：在「管理后台 - MCP 配置」页面生成 Token。包含系统统计、用户管理、余额/点数调整、API 管理、订单查看、卡密生成/管理、套餐管理、公告发布、反馈回复、调用日志、MCP 请求统计与历史日志查询、交易查询等 23 个管理工具。
- 两种 Token 完全隔离：用户 Token 调用管理工具会返回 `Unknown tool`，反之亦然。Token 仅以 SHA-256 哈希形式存储于数据库，明文只在生成时展示一次。

### 客户端配置示例

```json
{
  "mcpServers": {
    "huliapi": {
      "url": "https://你的域名/mcp.php",
      "headers": {
        "Authorization": "Bearer 你的Token"
      }
    }
  }
}
```

---

## 常见问题

### 应该用哪个分支安装？

首次安装使用 `main` 分支。

### 已经安装过的站点应该用哪个分支更新？

已有站点使用 `miao` 分支更新。

### 安装后无法连接数据库怎么办？

检查 `config.php` 中的数据库主机、数据库名、用户名、密码和字符集配置。

### 安装后首页模板加载失败怎么办？

检查数据库 `huli_site_home_templates.folder` 是否为 `huli`，并确认 `template/home/huli/` 存在。

### 用户中心推送通知入口看不到怎么办？

需要管理员先在后台启用对应推送通道，用户端才会显示可配置项。

### 邮件发送失败怎么办？

在后台「系统设置」检查 SMTP 配置，并使用后台测试功能验证邮件发送能力。

### Turnstile 验证不通过怎么办？

按以下顺序排查：

1. 生产环境需要在后台替换为真实的 Cloudflare Turnstile Site Key 和 Secret Key。
2. 两个 Key 必须来自同一个 Turnstile 站点。测试密钥与正式密钥混用时校验必然失败。
3. 在 Cloudflare Turnstile 站点的 Hostname Management 中添加本站实际访问域名。未授权的域名或直接用 IP 访问都会被拒绝。
4. 粘贴 Key 时不要带入空格或换行。

验证失败时页面会显示 Cloudflare 返回的具体原因，服务端同时向 PHP error_log 写入 `[turnstile]` 前缀的日志，可据此定位。
