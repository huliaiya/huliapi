<?php
require_once __DIR__ . '/../session_boot.php';
@error_reporting(0);
@ini_set('display_errors', '0');
header('Content-Type: text/plain; charset=utf-8');
http_response_code(410);
echo 'notify_debug.php 已禁用：该调试接口已被移除，请使用 notify.php 处理支付回调。';
