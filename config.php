<?php
date_default_timezone_set('Asia/Shanghai');
define('DB_HOST','127.0.0.1');define('DB_NAME','huliapi');define('DB_USER','huliapi');define('DB_PASS','huliapi');define('DB_CHARSET','utf8mb4');define('ADMIN_PATH','admin');

// 防御 CSRF 跨站请求伪造：校验 POST 请求的 Origin 和 Referer
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_host = $_SERVER['HTTP_HOST'] ?? '';
    if ($current_host) {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $source_host = '';
        if ($origin) {
            $parsed_origin = parse_url($origin);
            $source_host = $parsed_origin['host'] ?? '';
            if (!empty($parsed_origin['port'])) {
                $source_host .= ':' . $parsed_origin['port'];
            }
        } elseif ($referer) {
            $parsed_referer = parse_url($referer);
            $source_host = $parsed_referer['host'] ?? '';
            if (!empty($parsed_referer['port'])) {
                $source_host .= ':' . $parsed_referer['port'];
            }
        }
        if ($source_host !== '' && strtolower($source_host) !== strtolower($current_host)) {
            header('HTTP/1.1 400 Bad Request');
            header('Content-Type: application/json; charset=utf-8');
            die(json_encode(['success' => false, 'message' => '安全提示：检测到跨站请求，已拒绝处理。'], JSON_UNESCAPED_UNICODE));
        }
    }
}