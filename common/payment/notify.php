<?php
@error_reporting(0);
@ini_set('display_errors', 'Off');
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'code' => 410,
    'message' => '本站充值已切换为爱发电主动查询模式，此异步通知接口不再使用'
], JSON_UNESCAPED_UNICODE);
exit;
