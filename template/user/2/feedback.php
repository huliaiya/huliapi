<?php
session_start();
error_reporting(0);
ini_set('display_errors', 'Off');
$rootPath = dirname(__DIR__, 3);
define('ROOT_PATH', $rootPath . '/');
if (!file_exists(ROOT_PATH . 'config.php')) { 
    die("系统错误：配置文件丢失。路径: " . ROOT_PATH . 'config.php'); 
}
require_once ROOT_PATH . 'config.php';
$apis = [];
$settings = [];
$is_logged_in = isset($_SESSION['user_id']);
$user_info = $is_logged_in ? ['username' => $_SESSION['user_username'], 'email' => $_SESSION['user_email']] : null;
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt_apis = $pdo->query("SELECT id, name FROM sl_apis ORDER BY name ASC");
    $apis = $stmt_apis->fetchAll(PDO::FETCH_ASSOC);
    $stmt_settings = $pdo->query("SELECT setting_key, setting_value FROM sl_settings");
    $settings = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) { /* silent fail */ }
$site_name = $settings['site_name'] ?? '白茶API';
$allow_temp_key = $settings['allow_temp_key'] ?? 1;
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
<link rel="stylesheet" type="text/css" href="../../../assets/css/materialdesignicons.min.css">
<link rel="stylesheet" type="text/css" href="../../../assets/css/bootstrap.min.css">
<link rel="stylesheet" type="text/css" href="../../../assets/css/style.min.css">
</head>
<body>
<div class="container-fluid">
  <div class="row">
    <div class="col-lg-12">
      <div class="card">
        <header class="card-header"><div class="card-title">意见反馈</div></header>
        <div class="card-body">
          <div class="alert alert-info">
            <i class="mdi mdi-information-outline"></i> 感谢您的宝贵意见，它将帮助我们不断改进。
          </div>
          <form id="feedback-form" method="post" class="form-horizontal">
            <div id="feedback-result"></div>
            <div class="mb-3">
              <label for="feedback_type" class="form-label">反馈类型</label>
              <select id="feedback_type" name="type" class="form-select">
                <option value="general">意见与建议</option>
                <option value="api">接口问题反馈</option>
              </select>
            </div>
            <div class="mb-3" id="api-select-group" style="display: none;">
              <label for="api_id" class="form-label">选择接口</label>
              <select id="api_id" name="api_id" class="form-select">
                <option value="">请选择一个接口</option>
                <?php foreach($apis as $api): ?>
                <option value="<?php echo $api['id']; ?>"><?php echo htmlspecialchars($api['name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-3">
              <label for="content" class="form-label">反馈内容</label>
              <textarea id="content" name="content" class="form-control" rows="5" placeholder="请详细描述您遇到的问题或建议..." required></textarea>
            </div>
            <div class="mb-3">
              <label for="contact" class="form-label">联系方式 (可选)</label>
              <input type="text" id="contact" name="contact" class="form-control" placeholder="邮箱或QQ，方便我们与您联系" value="<?php echo htmlspecialchars($user_info['email'] ?? ''); ?>">
            </div>
            <div class="form-actions">
              <button type="submit" class="btn btn-primary me-1">提交反馈</button>
              <button type="button" class="btn btn-default" onclick="history.back();">返回</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<script type="text/javascript" src="../../../assets/js/jquery.min.js"></script>
<script type="text/javascript" src="../../../assets/js/popper.min.js"></script>
<script type="text/javascript" src="../../../assets/js/bootstrap.min.js"></script>
<script type="text/javascript" src="../../../assets/js/main.min.js"></script>
<script>
$(document).ready(function() {
   $('#feedback_type').change(function() {
        $('#api-select-group').toggle(this.value === 'api');
    });
    $('#feedback-form').submit(function(e) {
        e.preventDefault();
        var form = $(this);
        var submitBtn = form.find('button[type="submit"]');
        submitBtn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> 提交中...');
        $.ajax({
            url: '../../../common/ajax/submit_feedback.php',
            type: 'POST',
            data: form.serialize(),
            dataType: 'json',
            success: function(data) {
                var alertClass = data.success ? 'alert-success' : 'alert-danger';
                $('#feedback-result').html('<div class="alert ' + alertClass + '">' + data.message + '</div>');
                if (data.success) {
                    form[0].reset();
                    $('#api-select-group').hide();
                }
            },
            error: function() {
                $('#feedback-result').html('<div class="alert alert-danger">提交失败，请检查网络后重试。</div>');
            },
            complete: function() {
                submitBtn.prop('disabled', false).text('提交反馈');
            }
        });
    });
});
</script>
</body>
</html>