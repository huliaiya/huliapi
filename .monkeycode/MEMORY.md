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

[Project Knowledge Summary]
- Date: 2026-08-10
- Context: Discovered by Agent while implementing GitHub-based online update detection and the miao update channel branch
- Category: Workflow & Collaboration
- Instructions:
  - `main` 是完整分支，包含 `config.php` 和 `install/`，用于全新安装。
  - `miao` 是更新通道分支，代码与 main 同步但不含 `config.php` 和 `install/`；每次在 main 提交新代码后，需要同步合并到 miao 分支并推送。
  - 后台「更新检测」(`admin/update.php`) 通过 GitHub API 检测，优先读取 GitHub Releases，无 Release 时回退到 `miao` 分支最新提交的 SHA 和提交时间。
  - 更新下载来源是 `https://github.com/huliaiya/huliapi/archive/refs/heads/miao.zip`，覆盖时始终跳过 `config.php`、`install/` 和 `install.lock`，避免覆盖本地配置与已完成的安装。
  - 如需发布正式版本号，可在 GitHub 创建 tag 为 `v1.x.x` 的 Release，检测会优先显示 Release 版本号与发布时间。
