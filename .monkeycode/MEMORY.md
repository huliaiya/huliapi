# User Instruction Memory

This file records user instructions, preferences, and teachings for reference in future interactions.

## Format

### User Instruction Entry
User instruction entries should follow this format:

[User Instruction Summary]
- Date: [YYYY-MM-DD]
- Context: [Mentioned scenario or time]
- Instructions:
  - [Content of user teaching or instruction, described line by line]

### Project Knowledge Entry
Entries discovered by the Agent during task execution should follow this format:

[Project Knowledge Summary]
- Date: [YYYY-MM-DD]
- Context: Discovered by Agent while performing [specific task description]
- Category: [Operations & Deployment|Build Methods|Testing Methods|Troubleshooting & Debugging|Workflow & Collaboration|Environment Configuration]
- Instructions:
  - [Specific knowledge points, described line by line]

## Deduplication Strategy
- Before adding a new entry, check for similar or identical instructions.
- If a duplicate is found, skip the new entry or merge it with the existing one.
- When merging, update the context or date information.
- This helps avoid redundant entries and keeps the memory file tidy.

## Entries

[User Instruction Summary]
- Date: 2026-08-11
- Context: 全站视觉改造与安全检查
- Instructions:
  - 涉及页面改造时，需要同时检查 SQL 注入、XSS、CSRF、认证授权、敏感错误信息和输入校验。
  - 完成代码修改后，需要进行全量回归检查并说明无法执行的验证项。

[Project Knowledge Summary]
- Date: 2026-08-10
- Context: Discovered by Agent while implementing GitHub-based online update detection and the miao update channel branch
- Category: Workflow & Collaboration
- Instructions:
  - `main` 是完整分支，包含 `config.php` 和 `install/`，用于全新安装。
  - `miao` 是更新通道分支，代码与 main 同步但不含 `config.php` 和 `install/`；每次在 main 提交新代码后，需要同步合并到 miao 分支并推送。
  - 后台「更新检测」(`admin/update.php`) 通过 GitHub API 检测，优先读取 GitHub Releases，无 Release 时回退到 `miao` 分支最新提交的 SHA 和提交时间。
  - 检测有降级机制：GitHub API 未认证限流（60 次/小时）时，自动改用 releases/latest 的 302 重定向和 commits/{branch}.atom 的 Atom feed 解析，仍能获取最新版本和最近提交时间。
  - 更新下载来源是 `https://github.com/huliaiya/huliapi/archive/refs/heads/miao.zip`，覆盖时始终跳过 `config.php`、`install/` 和 `install.lock`，避免覆盖本地配置与已完成的安装。
  - 更新过程为 AJAX 分阶段执行（下载/解压/应用），前端显示进度条；更新完成后弹窗提示结果。
  - 若安装时自定义了后台目录（`ADMIN_PATH` 配置），更新完成后弹窗会提示手动将新下载的 `admin/` 目录文件覆盖到自定义后台目录，然后删除 `admin/` 目录。
  - 如需发布正式版本号，可在 GitHub 创建 tag 为 `v1.x.x` 的 Release，检测会优先显示 Release 版本号与发布时间。
