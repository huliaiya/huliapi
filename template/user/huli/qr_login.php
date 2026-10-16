<?php
require_once __DIR__ . '/../../../common/session_boot.php';
require_once __DIR__ . '/../../../common/qq_scan_session.php';

$isAdmin = isset($_GET['scope']) && $_GET['scope'] === 'admin';
$poll_url = $isAdmin ? '/admin/qr_poll.php' : '/template/user/huli/qr_poll.php';
$confirm_path = $isAdmin ? '/admin/login.php' : '/template/user/huli/login.php';
$back_url = $confirm_path;
?>
<!DOCTYPE html>
<html lang="zh">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>QQ 空间扫码登录</title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { background:#f5f7fb; display:flex; justify-content:center; align-items:center; min-height:100vh; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; }
  .card { background:#fff; border-radius:18px; box-shadow:0 24px 60px rgba(15,23,42,0.12); padding:32px; width:360px; text-align:center; }
  .card h3 { font-size:18px; color:#1e293b; margin-bottom:8px; }
  .card p.sub { font-size:13px; color:#64748b; line-height:1.6; margin-bottom:18px; }
  .qr-wrap { width:240px; height:240px; margin:0 auto 18px; border:1px solid #edf1f7; border-radius:12px; overflow:hidden; background:#fff; display:flex; align-items:center; justify-content:center; }
  .qr-wrap img { width:240px; height:240px; object-fit:contain; }
  .status-box { min-height:28px; font-size:14px; color:#334155; margin-bottom:14px; display:flex; align-items:center; justify-content:center; gap:8px; }
  .dot { width:8px; height:8px; border-radius:50%; display:inline-block; flex-shrink:0; }
  .dot.waiting { background:#f59e0b; }
  .dot.scanned { background:#3b82f6; }
  .dot.success { background:#10b981; }
  .dot.expired { background:#ef4444; }
  .btn-row { display:flex; gap:10px; justify-content:center; margin-top:8px; flex-wrap:wrap; }
  .btn { padding:10px 22px; border-radius:999px; font-size:14px; text-decoration:none; display:inline-block; }
  .btn-primary { background:#1f6feb; color:#fff; }
  .btn-primary:disabled { background:#cbd5e1; cursor:not-allowed; }
  .btn-ghost { background:#eef2f7; color:#334155; }
  .spinner { width:16px; height:16px; border:2px solid #e2e8f0; border-top-color:#1f6feb; border-radius:50%; animation:spin .6s linear infinite; display:inline-block; }
  @keyframes spin { to { transform:rotate(360deg); } }
</style>
</head>
<body>
<div class="card">
  <h3>QQ 空间扫码登录</h3>
  <p class="sub">使用手机 QQ 扫描下方二维码，扫码成功后可直接二次验证登录。若二维码过期请刷新页面。</p>

  <div class="qr-wrap" id="qrWrap">
    <div class="spinner" style="width:32px;height:32px"></div>
  </div>

  <div class="status-box" id="statusBox">
    <span class="dot waiting"></span> 等待扫码…
  </div>

  <div class="btn-row">
    <a class="btn btn-ghost" href="<?php echo htmlspecialchars($back_url); ?>">返回登录页</a>
  </div>
</div>

<script>
(function(){
  var pollTimer = null;
  var sid = '';
  var pollUrl = <?php echo json_encode($poll_url); ?>;
  var confirmPath = <?php echo json_encode($confirm_path); ?>;
  var uin = '';

  function setStatus(code, uinVal) {
    var box = document.getElementById('statusBox');
    var labelMap = { waiting:'等待扫码…', scanned:'已扫码，请在手机上确认…', success:'登录成功，正在跳转…', expired:'二维码已过期，请刷新页面' };
    var dot = document.createElement('span');
    dot.className = 'dot ' + code;
    var txt = document.createElement('span');
    txt.textContent = labelMap[code] || code;
    box.innerHTML = '';
    box.appendChild(dot);
    box.appendChild(txt);
    if (code === 'success') {
      if (uinVal) {
        setTimeout(function(){ location.href = confirmPath + '?mode=qq&qq=' + encodeURIComponent(uinVal); }, 800);
      }
    }
  }

  function startPoll() {
    fetch(pollUrl + '?act=poll&sid=' + encodeURIComponent(sid))
      .then(function(r){ return r.json(); })
      .then(function(d){
        if (d.status === 'invalid' || d.status === 'unknown') {
          clearInterval(pollTimer);
          setStatus('expired');
          return;
        }
        if (d.status === 'success') {
          uin = d.uin || uin || '';
          if (!uin) {
            clearInterval(pollTimer);
            setStatus('success');
            pollTimer = setTimeout(startPoll, 800);
            return;
          }
          clearInterval(pollTimer);
          setStatus('success', uin);
        } else if (d.status === 'scanned') {
          setStatus('scanned');
        }
      })
      .catch(function(){});
  }

  fetch(pollUrl + '?act=new')
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (!d.sid) { setStatus('expired'); return; }
      sid = d.sid;
      var wrap = document.getElementById('qrWrap');
      wrap.innerHTML = '<img src="data:image/png;base64,' + d.qr_png + '" alt="QR Code">';
      pollTimer = setInterval(startPoll, 2000);
    })
    .catch(function(){ setStatus('expired'); });
})();
</script>
</body>
</html>
