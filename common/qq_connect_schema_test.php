<?php
// QQ Connect schema verification (regression check for the missing qq_openid column).
// Run: php common/qq_connect_schema_test.php
// Exits 0 on success, 1 on failure.

$root = dirname(__DIR__);
$install_sql = file_get_contents($root . '/install/install.sql');
$upgrade_sql = file_get_contents($root . '/install/upgrade_qq_connect.sql');
$qq_connect_php = file_get_contents($root . '/common/qq_connect.php');

$failures = [];

// 1. install.sql must declare qq_openid in both huli_admins and huli_users.
foreach (['huli_admins', 'huli_users'] as $table) {
    // Extract the CREATE TABLE block for this table.
    if (!preg_match('/CREATE TABLE `' . preg_quote($table, '/') . '` \((.*?)\) ENGINE=/s', $install_sql, $m)) {
        $failures[] = "install.sql: CREATE TABLE for `{$table}` not found";
        continue;
    }
    if (strpos($m[1], '`qq_openid`') === false) {
        $failures[] = "install.sql: `{$table}` is missing the `qq_openid` column";
    }
}

// 2. upgrade_qq_connect.sql must exist and ALTER both tables.
foreach (['huli_admins', 'huli_users'] as $table) {
    if (strpos($upgrade_sql, "ALTER TABLE `{$table}`") === false
        || strpos($upgrade_sql, 'ADD COLUMN `qq_openid`') === false) {
        $failures[] = "upgrade_qq_connect.sql: missing ALTER TABLE for `{$table}`";
    }
}

// 3. qq_connect.php must provide the auto-migration helper.
if (strpos($qq_connect_php, 'function qq_connect_ensure_schema') === false) {
    $failures[] = 'qq_connect.php: qq_connect_ensure_schema() not found';
}
if (strpos($qq_connect_php, "COLUMN_NAME = 'qq_openid'") === false) {
    $failures[] = 'qq_connect.php: schema check for qq_openid not found';
}
if (strpos($qq_connect_php, "ADD COLUMN `qq_openid`") === false) {
    $failures[] = 'qq_connect.php: auto-migration ALTER for qq_openid not found';
}

if ($failures) {
    fwrite(STDERR, "QQ Connect schema verification FAILED:\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - {$f}\n");
    }
    exit(1);
}

echo "QQ Connect schema verification PASSED:\n";
echo "  - install.sql declares qq_openid in huli_admins and huli_users\n";
echo "  - upgrade_qq_connect.sql adds qq_openid to both tables (idempotent)\n";
echo "  - qq_connect.php auto-migrates the column on demand\n";
exit(0);
