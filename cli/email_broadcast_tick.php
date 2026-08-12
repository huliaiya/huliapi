<?php
@error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
@ini_set('display_errors', 'Off');
$root = dirname(__DIR__);
if (!file_exists($root . '/config.php')) { fwrite(STDERR, "config.php missing\n"); exit(2); }
require_once $root . '/config.php';
require_once $root . '/common/email_broadcast_dispatcher.php';

$limit = isset($argv[1]) ? max(1, intval($argv[1])) : 5;
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) { fwrite(STDERR, "DB error: " . $e->getMessage() . "\n"); exit(3); }

$results = huli_broadcast_tick($pdo, $limit);
$count = 0;
foreach ($results as $r) {
    $count++;
    if (!empty($r['error'])) { fwrite(STDERR, "[id={$r['id']}] error: {$r['error']}\n"); }
    elseif (is_array($r['result'])) { fwrite(STDOUT, "[id={$r['id']}] sent={$r['result']['sent']}/{$r['result']['total']}\n"); }
}
fwrite(STDOUT, "dispatched={$count}\n");
exit(0);
