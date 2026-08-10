<?php
@session_start();
require_once dirname(__DIR__, 3) . '/config.php';
$response = ['logged_in' => false];
if (isset($_SESSION['user_id'])) {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
        $stmt = $pdo->prepare("SELECT id FROM sl_users WHERE id = ? AND status = 1");
        $stmt->execute([$_SESSION['user_id']]);
        $response['logged_in'] = (bool)$stmt->fetch();
    } catch (PDOException $e) {
        $response['logged_in'] = true; 
    }
}
header('Content-Type: application/json');
echo json_encode($response);
?>