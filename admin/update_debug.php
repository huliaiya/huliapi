<?php
@session_start();
@error_reporting(E_ALL);
@ini_set('display_errors', 'On');
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
define('SENLIN_CLIENT_VERSION', '1.0.0');
define('UPDATE_API_URL', 'https://xiaoqi.icofun.cn/updates/api.php');
?>

<!DOCTYPE html>
<html lang="zh">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-touch-fullscreen" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<link rel="stylesheet" type="text/css" href="../assets/css/materialdesignicons.min.css">
<link rel="stylesheet" type="text/css" href="../assets/css/bootstrap.min.css">
<link rel="stylesheet" type="text/css" href="../assets/css/style.min.css">
    <style>
        body {
            background-color: #f0f2f5;
        }
        .diagnostic-container {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            margin: 40px auto;
            padding: 30px;
            max-width: 900px;
        }
        .diagnostic-step {
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
            margin-bottom: 15px;
        }
        .status {
            font-weight: bold;
        }
        .status.success {
            color: #16a34a;
        }
        .status.error {
            color: #dc2626;
        }
        pre {
            background-color: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            border-radius: 8px;
            white-space: pre-wrap;
            word-wrap: break-word;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="diagnostic-container">
        <h1 class="mb-4">在线更新 - 诊断工具</h1>
        <?php
        echo '<div class="diagnostic-step"><h2 class="h4">步骤 1: 初始环境检查</h2>';
        echo '<div class="row mb-2"><div class="col-md-4">PHP Version:</div><div class="col-md-8">' . PHP_VERSION . '</div></div>';
        echo '<div class="row mb-2"><div class="col-md-4">cURL 扩展:</div><div class="col-md-8">' . (extension_loaded('curl') ? '<span class="status success">已开启</span>' : '<span class="status error">未开启</span>') . '</div></div>';
        echo '</div>';
        echo '<div class="diagnostic-step"><h2 class="h4">步骤 2: 准备请求数据</h2>';
        $post_data = [
            'url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]",
            'ip' => $_SERVER['SERVER_ADDR'] ?? gethostbyname($_SERVER['SERVER_NAME']),
            'version' => SENLIN_CLIENT_VERSION
        ];
        echo '<div class="mb-3"><strong>目标更新服务器 URL:</strong><pre>' . UPDATE_API_URL . '</pre></div>';
        echo '<div class="mb-3"><strong>将要 POST 的数据:</strong><pre>' . print_r($post_data, true) . '</pre></div>';
        echo '</div>';
        echo '<div class="diagnostic-step"><h2 class="h4">步骤 3: 执行 cURL 请求</h2>';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, UPDATE_API_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        $api_response_str = curl_exec($ch);
        $curl_error_num = curl_errno($ch);
        $curl_error_msg = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        echo '<div class="row mb-2"><div class="col-md-4">cURL 错误号:</div><div class="col-md-8"><pre>' . $curl_error_num . '</pre></div></div>';
        echo '<div class="row mb-2"><div class="col-md-4">cURL 错误信息:</div><div class="col-md-8"><pre>' . ($curl_error_msg ?: '无') . '</pre></div></div>';
        echo '<div class="row mb-2"><div class="col-md-4">HTTP 状态码:</div><div class="col-md-8"><pre>' . $http_code . '</pre></div></div>';
        echo '<div class="mb-3"><strong>从服务器返回的原始响应内容:</strong><pre>' . htmlspecialchars($api_response_str ?: '无内容返回') . '</pre></div>';
        echo '</div>';
        $conclusion = '';
        if ($curl_error_num > 0) {
            $conclusion = "<div class='alert alert-danger'><span class='status error'>诊断结论：连接更新服务器失败。</span><p><strong>原因:</strong> " . htmlspecialchars($curl_error_msg) . "。这通常是由于您的服务器无法访问目标域名 (DNS问题) 或IP地址 (网络/防火墙问题) 导致的。请联系您的服务器管理员检查网络配置。</p></div>";
        } elseif ($http_code !== 200) {
            $conclusion = "<div class='alert alert-danger'><span class='status error'>诊断结论：连接成功，但更新服务器返回了错误。</span><p><strong>原因:</strong> 服务器返回了HTTP状态码 " . $http_code . "。常见原因如 404 Not Found 表示您更新服务器上的 /updates/api.php 文件不存在或路径错误；500 Internal Server Error 表示更新服务器的PHP代码出错了。</p></div>";
        } else {
            echo '<div class="diagnostic-step"><h2 class="h4">步骤 4: 解析JSON响应</h2>';
            $api_response = json_decode($api_response_str, true);
            if ($api_response === null) {
                echo '<div class="row mb-2"><div class="col-md-4">json_decode() 返回:</div><div class="col-md-8"><pre>NULL</pre></div></div>';
                echo '<div class="row mb-2"><div class="col-md-4">JSON 解析错误:</div><div class="col-md-8"><pre>' . json_last_error_msg() . '</pre></div></div>';
                $conclusion = "<div class='alert alert-danger'><span class='status error'>诊断结论：连接成功，但无法解析服务器返回的内容。</span><p><strong>原因:</strong> 更新服务器返回的不是有效的JSON格式。请检查返回的原始响应内容，它可能是一个HTML错误页面。</p></div>";
            } else {
                echo '<div class="mb-3"><strong>成功解析JSON:</strong><pre>' . print_r($api_response, true) . '</pre></div>';
                if($api_response['success']){
                     $conclusion = "<div class='alert alert-success'><span class='status success'>诊断结论：一切正常！</span><p>您的系统已成功连接到更新服务器并获取了有效的更新信息。如果更新页面仍有问题，可能是前端JavaScript的错误。</p></div>";
                } else {
                     $conclusion = "<div class='alert alert-danger'><span class='status error'>诊断结论：连接成功，但更新服务器API返回了业务错误。</span><p><strong>原因:</strong> " . htmlspecialchars($api_response['message']) . "</p></div>";
                }
            }
            echo '</div>';
        }
        echo '<div class="diagnostic-step"><h2 class="h4">最终诊断</h2>' . $conclusion . '</div>';
        ?>
    </div>
</div>
<script type="text/javascript" src="../assets/js/jquery.min.js"></script>
<script type="text/javascript" src="../assets/js/popper.min.js"></script>
<script type="text/javascript" src="../assets/js/bootstrap.min.js"></script>
</body>
</html>