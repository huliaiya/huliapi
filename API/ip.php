<?php require_once __DIR__ . '/../common/security/api_auth.php';

header('Content-Type: application/json; charset=utf-8');

$ip = isset($_GET['ip']) ? trim($_GET['ip']) : '';
if ($ip !== '' && !filter_var($ip, FILTER_VALIDATE_IP)) {
    echo json_encode(['code' => 400, 'msg' => 'ip 参数不是合法的 IP 地址'], JSON_UNESCAPED_UNICODE);
    exit;
}

$url = 'https://whois.pconline.com.cn/ipJson.jsp?json=true';
if ($ip !== '') {
    $url .= '&ip=' . urlencode($ip);
}
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
$data = curl_exec($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErrno = curl_errno($ch);
curl_close($ch);

if ($curlErrno !== 0) {
    echo json_encode(['code' => 502, 'msg' => 'IP 归属地服务请求失败'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($httpCode !== 200 || !$data) {
    echo json_encode(['code' => 502, 'msg' => 'IP 归属地查询失败，请稍后重试'], JSON_UNESCAPED_UNICODE);
    exit;
}

$decoded = json_decode($data, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $data = mb_convert_encoding($data, 'UTF-8', 'GBK');
    $decoded = json_decode($data, true);
}
if (!is_array($decoded)) {
    echo json_encode(['code' => 502, 'msg' => 'IP 归属地数据解析失败'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['code' => 200, 'data' => $decoded], JSON_UNESCAPED_UNICODE);
