<?php
@error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
@ini_set('display_errors', 'Off');

$root = dirname(__DIR__);
if (!file_exists($root . '/config.php')) { fwrite(STDERR, "config.php missing\n"); exit(2); }
require_once $root . '/config.php';

$apply = in_array('--yes', $argv ?? [], true);

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";port=" . (defined('DB_PORT') ? DB_PORT : 3306) . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) {
    fwrite(STDERR, "DB error: " . $e->getMessage() . "\n");
    exit(3);
}

$stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'huli_update_jobs'");
if (!(int)$stmt->fetchColumn()) {
    fwrite(STDOUT, "legacy table huli_update_jobs not found; nothing to do\n");
    exit(0);
}

$count = (int)$pdo->query("SELECT COUNT(*) FROM `huli_update_jobs`")->fetchColumn();
$target = 'huli_update_jobs_legacy_' . date('Ymd_His');

fwrite(STDOUT, "legacy table rows={$count}\n");
fwrite(STDOUT, "target table={$target}\n");

if (!$apply) {
    fwrite(STDOUT, "dry-run; add --yes to rename the legacy table\n");
    exit(0);
}

$exists = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
$exists->execute([$target]);
if ((int)$exists->fetchColumn()) {
    fwrite(STDERR, "target table already exists\n");
    exit(4);
}

$pdo->exec("RENAME TABLE `huli_update_jobs` TO `{$target}`");
fwrite(STDOUT, "renamed huli_update_jobs to {$target}\n");
