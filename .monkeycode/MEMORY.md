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
