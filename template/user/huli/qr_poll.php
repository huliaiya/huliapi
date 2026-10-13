<?php
require_once __DIR__ . '/../../../common/session_boot.php';
require_once __DIR__ . '/../../../common/qq_scan_session.php';

header('Content-Type: application/json; charset=utf-8');

$act = $_GET['act'] ?? '';
$cid = $_GET['sid'] ?? '';

if ($act === 'new') {
    $res = huli_qq_scan_new_session();
    if (!($res['ok'] ?? false)) {
        http_response_code(503);
        echo json_encode(['error' => $res['error'] ?? 'unknown']);
        exit;
    }
    echo json_encode(['sid' => $res['sid'], 'qr_png' => $res['qr_png']]);
    exit;
}

if ($act === 'poll') {
    if ($cid === '') { echo json_encode(['status' => 'invalid']); exit; }
    $res = huli_qq_scan_poll($cid);
    echo json_encode($res);
    exit;
}

echo json_encode(['error' => 'unknown action']);
