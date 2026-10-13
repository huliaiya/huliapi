<?php
$scope = $_GET['scope'] === 'admin' ? 'admin' : 'user';
$qq = preg_replace('/[^0-9]/', '', (string)($_GET['qq'] ?? ''));
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? '';
$base = $host ? $protocol . '://' . $host : '';
$target = $scope === 'admin' ? $base . '/admin/login.php?mode=qq&qq=' . $qq : $base . '/template/user/huli/login.php?mode=qq&qq=' . $qq;
$qr_payload = $qq !== '' ? $target : '';
?>
<!doctype html>
<html lang="zh">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>QQ 扫码登录</title>
  <style>
    body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f5f7fb; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
    .card { width: 340px; background: #fff; border-radius: 18px; box-shadow: 0 24px 60px rgba(15, 23, 42, 0.12); padding: 22px; text-align: center; }
    #qr { width: 240px; height: 240px; margin: 14px auto; display: block; border: 1px solid #edf1f7; border-radius: 12px; padding: 10px; }
    .muted { color: #64748b; font-size: 13px; line-height: 1.6; }
    .primary { display: inline-block; margin-top: 14px; padding: 10px 18px; border-radius: 999px; background: #1f6feb; color: #fff; text-decoration: none; font-size: 14px; }
    .ghost { display: inline-block; margin-top: 10px; padding: 9px 16px; border-radius: 999px; background: #eef2f7; color: #334155; text-decoration: none; font-size: 13px; }
  </style>
</head>
<body>
  <div class="card">
    <h3><?php echo $scope === 'admin' ? '后台 QQ 扫码登录' : '用户 QQ 扫码登录'; ?></h3>
    <div class="muted">
      <?php if ($qq !== ''): ?>
        扫码后将对 QQ 号 <strong><?php echo htmlspecialchars($qq); ?></strong> 发起二次验证，仍需输入对应账号密码：
      <?php else: ?>
        请在登录页点击“QQ 扫码模拟登录”并传入 QQ 号参数，例如：
        <?php echo $scope === 'admin' ? '<code>/admin/qr_login.php?scope=admin&qq=2209176666</code>' : '<code>/template/user/huli/qr_login.php?scope=user&qq=2209176666</code>'; ?>
      <?php endif; ?>
    </div>
    <div id="qr" class="qr-box" role="img" aria-label="QR Code"></div>
    <?php if ($qq !== ''): ?>
      <a class="primary" href="<?php echo htmlspecialchars($target); ?>">打开确认页登录</a><br>
    <?php endif; ?>
    <a class="ghost" href="<?php echo $scope === 'admin' ? '/admin/login.php' : '/template/user/huli/login.php'; ?>">返回登录页</a>
  </div>
  <script src="https://unpkg.com/qrcode@1.5.4/build/qrcode.min.js"></script>
  <script>
    (function () {
      var payload = <?php echo json_encode($qr_payload); ?>;
      var box = document.getElementById('qr');
      if (!payload) {
        box.innerHTML = '<div class="qr-empty">缺少 QQ 号参数</div>';
        return;
      }
      window.addEventListener('load', function () {
        if (typeof QRCode !== 'undefined' && QRCode.toCanvas) {
          var canvas = document.createElement('canvas');
          QRCode.toCanvas(canvas, payload, { width: 240, margin: 1, color: { dark: '#0f172a', light: '#ffffff' } }, function (err) {
            if (err) {
              box.innerHTML = '<div class="qr-empty">二维码生成失败，请直接点击“打开确认页登录”继续</div>';
              return;
            }
            canvas.style.width = '240px';
            canvas.style.height = '240px';
            canvas.style.border = '1px solid #edf1f7';
            canvas.style.borderRadius = '12px';
            canvas.style.padding = '10px';
            box.innerHTML = '';
            box.appendChild(canvas);
          });
        } else {
          box.innerHTML = '<div class="qr-empty">二维码库加载失败，请直接点击“打开确认页登录”继续</div>';
        }
      });
    })();
  </script>
</body>
</html>
