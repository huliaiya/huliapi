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

[Project Environment and Service Info]
- Date: 2026-08-15
- Context: Discovered by Agent while performing system configuration and verification
- Category: Operations & Deployment
- Instructions:
  - Local database is MariaDB, database name is huliapi, username and password are configured in `config.php` (`DB_USER` / `DB_PASS`); do not write plaintext credentials into memory files.
  - PHP Development Server runs on port 8000 via command `php -S 0.0.0.0:8000 -t /workspace`.
  - The install directory has been renamed to install.backup for security, and removed from git tracking.
  - Global CSRF defense is implemented at the top of config.php by checking POST request Origin/Referer headers (with ports stripped).
  - Code verification logs table huli_verification_code_logs is created in DB for IP and email verification code rate limiting.

[Project Knowledge Summary]
- Date: 2026-08-17
- Context: Discovered by Agent while performing smoke tests and rate-limit/billing concurrency verification
- Category: Environment Configuration
- Instructions:
  - This devbox has no curl command; use PHP cURL scripts (`php -r` or temp scripts) for HTTP connectivity tests.
  - QPS limit check uses `count > limit` (count == limit is allowed), so to verify a 429, prefill `request_count` in `huli_rate_limits` to the limit value first.
  - Billing concurrency (negative balance) can be verified end-to-end with a temporary billable API endpoint + test user, then 8-way parallel requests expecting 200xN + 402x(8-N) and balance exactly 0.

[Project Knowledge Summary]
- Date: 2026-09-03
- Context: Discovered by Agent while refactoring installer environment detection
- Category: Build Methods
- Instructions:
  - PHP CLI is not pre-installed in this workspace; install it with `DEBIAN_FRONTEND=noninteractive apt-get install -y php-cli` to run `php -l` syntax checks and `php -S` smoke tests.
  - The huliapi PHP codebase contains no PHP 8-only syntax, so the supported minimum is PHP 7.4; do not reintroduce 8.0.0+ gates when adding env checks.

[Project Knowledge Summary]
- Date: 2026-09-17
- Context: Discovered by Agent while fixing the admin Turnstile blank-page bug and pushing to both branches
- Category: Workflow & Collaboration
- Instructions:
  - Bug fixes must be applied and pushed to BOTH branches: `main` (install) and `miao` (update), because the two branches have unrelated histories and are maintained in parallel.
  - `main` and `miao` differ slightly in `admin/settings.php` (miao calls `huliReloadTurnstileSdk()` inside `loadE2EWidget`, main calls it in the reload button); when porting fixes use `git cherry-pick -n` so git auto-merges and preserves each branch's own style, then verify the branch-specific hunks remain intact.
  - The two branches also differ by `.trae-html-share-packages/admin/_diag.html.zip` (present only in main); leave that file alone when porting changes.
  - Both `main` and `miao` carry the same MCP subsystem (`common/mcp/mcp_lib.php`, `mcp_server.php`, `mcp_tools_admin.php`, `mcp_tools_user.php`, `admin/mcp.php`, root `mcp.php`) and the update engine (`common/github_update.php`, `common/updaters.php`); so MCP-tools and update-engine changes must be cherry-picked to both branches too.
  - `admin/update.php` provides the reference: line 9 requires `../common/github_update.php`, line 10 requires `../common/updaters.php`; engine functions use the singular prefix `huli_updaters_*`.
  - Careful: `git ls-tree --name-only <branch> common/` (no trailing recursion) emits child paths prefixed with `common/`, so grep patterns like `^mcp` miss the `common/mcp` dir; instead use `git ls-tree -r --name-only <branch>` to detect files anywhere in the tree.
