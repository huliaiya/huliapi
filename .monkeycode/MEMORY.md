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
  - Local database is MariaDB, database name is huliapi, username is huliapi, password is huliapi.
  - PHP Development Server runs on port 8000 via command `php -S 0.0.0.0:8000 -t /workspace`.
  - The install directory has been renamed to install.backup for security, and removed from git tracking.
  - Global CSRF defense is implemented at the top of config.php by checking POST request Origin/Referer headers (with ports stripped).
  - Code verification logs table huli_verification_code_logs is created in DB for IP and email verification code rate limiting.
