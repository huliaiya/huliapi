<?php
// PHP 内置服务器路由：仅用于本地预览，生产环境请使用 Nginx/Apache 配置。
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri = is_string($uri) ? rawurldecode($uri) : '/';

if (preg_match('#^/\.well-known(?:/|$)#i', $uri)) {
    return false;
}

$blocked = preg_match('#(?:^|/)\.#', $uri)
    || preg_match('#^/logs(?:/|$)#i', $uri)
    || preg_match('#^/(?:cli|tests|deploy)(?:/|$)#i', $uri)
    || preg_match('#^/install/(?!$|index\.php$).*#i', $uri);

if ($blocked) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not Found';
    return true;
}

return false;
