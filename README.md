# huliapi - API 管理与销售平台

版本: 1.5.0 | PHP 8.0+ | MySQL 5.7+

基于 PHP 的 API 管理与销售系统，支持 API 接入、用户计费、卡密兑换、邮件群发等功能。

---

## 分支说明

| 分支 | 用途 | 备注 |
|------|------|------|
| `main` | **完整版/安装版** | 包含 `config.php`、`install/` 目录。首次部署用此分支。 |
| `miao` | **更新版/轻量版** | 不含 `config.php`、`install/`、`install.lock`。线上运行的项目通过管理后台「系统更新」自动拉取此分支。 |

更新机制: 后台「系统更新」从 `miao` 分支下载 ZIP 包，解压后覆盖项目文件，跳过 `config.php`/`install/`/`install.lock` 避免覆盖本地配置。

---

## 环境要求

- PHP 8.0 或更高版本
- MySQL 5.7 或 MariaDB 10.3+
- 推荐 Nginx/Apache
- PHP 扩展: PDO, pdo_mysql, curl, openssl, mbstring, gd

---

## 安装（main 分支）

1. **克隆仓库**
   ```bash
   git clone -b main https://github.com/huliaiya/huliapi.git
   cd huliapi
   ```

2. **配置 Web 服务器**
   将站点根目录指向项目目录。Nginx 示例:
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

3. **设置权限**
   ```bash
   chmod -R 755 .
   chmod 644 config.php
   ```

4. **运行安装向导**
   浏览器访问 `http://your-domain.com/install/`，按向导填写数据库信息和管理员账号。

5. **安装完成后**
   建议删除 `install/` 目录以提高安全性。

---

## 更新（线上项目）

已运行的项目在后台「系统管理 -> 系统更新」点击更新。更新从 `miao` 分支拉取，自动跳过 `config.php` 和 `install/`。

---

## 目录结构

```
├── admin/                     # 后台管理
│   ├── login.php              # 管理员登录
│   ├── index.php              # 仪表盘
│   ├── settings.php           # 系统设置（含Turnstile/SMTP/备案等）
│   ├── user_list.php          # 用户管理
│   ├── api_list.php           # API接口管理
│   ├── api_edit.php           # API添加/编辑
│   ├── order_list.php         # 订单管理
│   ├── cdkeys.php             # 卡密管理
│   ├── email_broadcasts.php   # 邮件群发列表
│   ├── email_broadcast_create.php  # 新建/编辑群发
│   ├── friend_links.php       # 友情链接审核
│   ├── billing_plans.php      # 计费方案
│   ├── temp_keys.php          # 临时密钥
│   ├── template.php           # 模板选择
│   ├── update.php             # 系统更新
│   └── ...
├── common/                    # 公共模块
│   ├── security/
│   │   └── api_auth.php       # API认证核心
│   ├── payment/               # 支付模块（Epay集成）
│   ├── PHPMailer/             # 邮件发送库
│   ├── ajax/                  # AJAX接口
│   │   ├── send_code.php      # 发送验证码
│   │   ├── get_temp_key.php   # 获取临时密钥
│   │   ├── buy_api.php        # 购买API
│   │   ├── redeem_cdkey.php   # 兑换卡密
│   │   └── ...
│   ├── turnstile.php          # Cloudflare Turnstile验证
│   ├── github_update.php      # GitHub更新服务
│   ├── TemplateManager.php    # 模板管理器
│   └── ...
├── template/                  # 前端模板
│   ├── home/1/                # 首页模板1
│   ├── home/2/                # 首页模板2（含友链申请）
│   ├── user/1/                # 用户中心模板1
│   └── user/2/                # 用户中心模板2
├── API/                       # 对外API接口（由 common/api_auth.php 保护）
├── assets/                    # 静态资源（CSS/JS/字体）
├── install/                   # 安装向导（仅main分支）
├── config.php                 # 数据库配置（仅main分支，安装时生成）
├── .user.ini                  # PHP自定义配置
└── README.md
```

---

## 核心功能

### API 管理
- 创建/编辑 API 接口，支持公开和私有模式
- 按请求次数或包月计费（点数/余额）
- 灵活的路由和代理模式

### 用户系统
- 邮箱验证码注册
- 注册开关（后台可控）
- 密码使用 bcrypt 加密存储
- Cloudflare Turnstile 人机验证

### 计费与支付
- 点数购买、卡密兑换
- 计费方案管理（日/周/月/季/年）
- Epay 支付集成（支付宝/微信）

### 电子邮件
- SMTP 邮件发送
- 邮件群发（支持 HTML 内容、定时发送、占位符替换）
- 注册/重置密码验证码邮件

### 安全特性
- 后台登录使用 bcrypt 密码哈希
- Turnstile 人机验证（测试密钥默认启用，生产环境需替换）
- SQL 注入: 全站 PDO 预处理语句
- XSS: 输出统一 `htmlspecialchars()` 转义
- 登录成功后 `session_regenerate_id()` 防会话固定

---

## 首次配置清单

部署后请在后台「系统设置」中完成以下配置:

| 配置项 | 说明 |
|--------|------|
| Turnstile Site Key | 替换测试密钥为 Cloudflare 真实密钥 |
| Turnstile Secret Key | 同上 |
| SMTP 配置 | 主机、端口、账号、密码、加密方式 |
| 支付配置 (Epay) | PID、Key、接口 URL |
| ICP 备案号 | 若需要 |
| 公安备案号 | 若需要 |
| favicon URL | 网站图标地址 |

---

## 常见问题

**Q: 安装后无法登录后台？**
A: 检查 `config.php` 数据库连接信息是否正确，确认 `huli_admins` 表有管理员记录。

**Q: 邮件发送失败？**
A: 在后台「系统设置 -> 邮件设置」填写正确的 SMTP 信息，并用「测试邮件」验证。

**Q: Turnstile 验证不通过？**
A: 默认使用 Cloudflare 测试密钥（始终通过）。生产环境需在后台设置中替换为真实密钥。

**Q: 用户注册收不到验证码？**
A: 确认 SMTP 配置正确，检查邮件是否被拦截到垃圾箱。

**Q: 如何切换模板？**
A: 后台「模板管理」选择模板，系统自动切换首页和用户中心样式。
