<?php
require_once __DIR__ . '/../../../common/session_boot.php';
@error_reporting(0);
@ini_set('display_errors', 'Off');
require_once dirname(__DIR__, 3) . '/config.php';
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $withCategories = isset($_GET['with_categories']) && $_GET['with_categories'] == '1';
    if ($withCategories) {
        $categories = [];
        $stmtCategories = $pdo->query("SELECT * FROM huli_api_categories ORDER BY name");
        while ($category = $stmtCategories->fetch(PDO::FETCH_ASSOC)) {
            $stmtApis = $pdo->prepare("SELECT id, name, endpoint FROM huli_apis WHERE category_id = ? ORDER BY name");
            $stmtApis->execute([$category['id']]);
            $category['apis'] = $stmtApis->fetchAll(PDO::FETCH_ASSOC);
            $category['api_count'] = count($category['apis']);
            $categories[] = $category;
        }
        $stmtUncategorized = $pdo->query("SELECT id, name, endpoint FROM huli_apis WHERE category_id IS NULL OR category_id = 0 ORDER BY name");
        $uncategorized = $stmtUncategorized->fetchAll(PDO::FETCH_ASSOC);
        header('Content-Type: application/json');
        echo json_encode([
            'categories' => $categories,
            'uncategorized' => $uncategorized
        ]);
    } else {
        $stmt = $pdo->query("SELECT id, name, endpoint, is_billable, price_per_call, points_per_call, category_id FROM huli_apis WHERE status = 'normal'");
        $apis = $stmt->fetchAll(PDO::FETCH_ASSOC);
        header('Content-Type: application/json');
        echo json_encode($apis);
    }
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error']);
}