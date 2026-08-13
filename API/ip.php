<?php require_once __DIR__ . '/../common/security/api_auth.php';
require_once __DIR__ . '/../common/geo.php';

header('Content-Type: application/json; charset=utf-8');

$ip = isset($_GET['ip']) ? trim($_GET['ip']) : '';
if ($ip !== '' && !filter_var($ip, FILTER_VALIDATE_IP)) {
    echo json_encode(['code' => 400, 'msg' => 'ip 参数不是合法的 IP 地址'], JSON_UNESCAPED_UNICODE);
    exit;
}

$geo = huli_pconline_geo($ip);
if ($geo === null) {
    echo json_encode(['code' => 502, 'msg' => 'IP 归属地查询失败，请稍后重试'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['code' => 200, 'data' => $geo], JSON_UNESCAPED_UNICODE);
