<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
header('Content-Type: application/json; charset=utf-8');
function json_response($success, $message) {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}
if (!isset($_SESSION['user_id'])) { json_response(false, '请先登录。'); }
if (!isset($_POST['item_id'])) { json_response(false, '无效的请求。'); }
require_once '../../config.php';
$user_id = $_SESSION['user_id'];
$item_id = intval($_POST['item_id']);
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->beginTransaction();
    $stmt_user = $pdo->prepare("SELECT balance FROM huli_users WHERE id = ? FOR UPDATE");
    $stmt_user->execute([$user_id]);
    $user = $stmt_user->fetch();
    $stmt_item = $pdo->prepare("SELECT mi.*, a.name, a.description, a.endpoint as original_endpoint, a.method, a.type, a.file_path as original_file_path, a.parameters, a.remote_url, a.response_format, a.response_example FROM huli_market_items mi JOIN huli_apis a ON mi.api_id = a.id WHERE mi.id = ? AND mi.status = 1 FOR UPDATE");
    $stmt_item->execute([$item_id]);
    $item = $stmt_item->fetch(PDO::FETCH_ASSOC);
    if (!$user || !$item) { throw new Exception("用户或商品信息无效。"); }
    if ($user['balance'] < $item['price']) { throw new Exception("您的账户余额不足。"); }
    $stmt_check = $pdo->prepare("SELECT id FROM huli_market_purchases WHERE item_id = ? AND user_id = ?");
    $stmt_check->execute([$item_id, $user_id]);
    if ($stmt_check->fetch()) { throw new Exception("您已经购买过此接口。"); }
    $stmt_deduct = $pdo->prepare("UPDATE huli_users SET balance = balance - ? WHERE id = ?");
    $stmt_deduct->execute([$item['price'], $user_id]);
    $stmt_inc_downloads = $pdo->prepare("UPDATE huli_market_items SET downloads = downloads + 1 WHERE id = ?");
    $stmt_inc_downloads->execute([$item_id]);
    $stmt_purchase = $pdo->prepare("INSERT INTO huli_market_purchases (item_id, user_id, price) VALUES (?, ?, ?)");
    $stmt_purchase->execute([$item_id, $user_id, $item['price']]);
    $new_endpoint = 'u' . $user_id . '_' . rawurldecode($item['original_endpoint']) . '_' . substr(md5(uniqid()), 0, 4);
    $new_file_path = 'API/' . $new_endpoint . '.php';
    $original_content = file_get_contents('../../' . $item['original_file_path']);
    if(file_put_contents('../../' . $new_file_path, $original_content) === false){
        throw new Exception("自动安装接口文件失败，请检查/API/目录权限。");
    }
    $sql_clone = "INSERT INTO huli_apis (name, description, endpoint, method, type, file_path, parameters, remote_url, response_format, response_example, visibility, is_billable) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'private', 0)";
    $stmt_clone = $pdo->prepare($sql_clone);
    $stmt_clone->execute([
        $item['name'] . ' (已购)', $item['description'], $new_endpoint, $item['method'], $item['type'],
        $new_file_path, $item['parameters'], $item['remote_url'], $item['response_format'], $item['response_example']
    ]);
    $pdo->commit();
    json_response(true, '购买成功！接口已自动添加到您的API列表中。');
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('API 购买失败: ' . $e->getMessage());
    json_response(false, '购买失败，请稍后重试。');
}
?>
