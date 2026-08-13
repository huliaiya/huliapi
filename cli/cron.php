<?php
@error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
@ini_set('display_errors', 'Off');
$root = dirname(__DIR__);
if (!file_exists($root . '/config.php')) { fwrite(STDERR, "config.php missing\n"); exit(2); }
require_once $root . '/config.php';
require_once $root . '/common/email_broadcast_dispatcher.php';
require_once $root . '/cli/query_pending_orders.php';

$jobs = [];
$jobs[] = ['name' => 'email_broadcast', 'run' => function ($pdo) {
    return huli_broadcast_tick($pdo, 5);
}];
$jobs[] = ['name' => 'afdian_query', 'run' => function ($pdo) {
    return huli_query_pending_afdian_orders($pdo, 50);
}];

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) { fwrite(STDERR, "DB error: " . $e->getMessage() . "\n"); exit(3); }

foreach ($jobs as $j) {
    $t0 = microtime(true);
    try {
        $out = ($j['run'])($pdo);
        $dt = round((microtime(true) - $t0) * 1000);
        $summary = '';
        if (is_array($out)) {
            $summary = array_is_list($out) ? count($out) . ' items' : json_encode($out);
        }
        fwrite(STDOUT, sprintf("[%s] OK %dms %s\n", $j['name'], $dt, $summary));
    } catch (Throwable $e) {
        $dt = round((microtime(true) - $t0) * 1000);
        fwrite(STDERR, sprintf("[%s] FAIL %dms %s\n", $j['name'], $dt, $e->getMessage()));
    }
}
exit(0);
