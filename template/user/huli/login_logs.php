<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$rootPath = dirname(__DIR__, 3);
define('ROOT_PATH', $rootPath . '/');
if (!file_exists(ROOT_PATH . 'config.php')) { die("系统错误：配置文件丢失。"); }
require_once ROOT_PATH . 'config.php';

$user_id = (int)$_SESSION['user_id'];
$username = $_SESSION['user_username'] ?? '';
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) { die("数据库连接失败"); }
$settings = $pdo->query("SELECT setting_key, setting_value FROM huli_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$site_name = $settings['site_name'] ?? 'huliapi';

$page = max(1, (int)($_GET['page'] ?? 1));
$page_size = 20;
$status = $_GET['status'] ?? '';
$keyword = trim($_GET['kw'] ?? '');
$where = ['user_id = ?']; $params = [$user_id];
if ($status !== '' && in_array($status, ['success','failed','locked'], true)) { $where[] = 'status = ?'; $params[] = $status; }
if ($keyword !== '') { $where[] = '(ip LIKE ? OR country LIKE ? OR region LIKE ? OR city LIKE ?)'; $kw = '%' . $keyword . '%'; array_push($params, $kw, $kw, $kw, $kw); }
$where_sql = 'WHERE ' . implode(' AND ', $where);

$total = (int)$pdo->prepare("SELECT COUNT(*) FROM huli_user_login_logs $where_sql")->execute($params) ? 0 : 0;
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM huli_user_login_logs $where_sql");
$count_stmt->execute($params);
$total = (int)$count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total / $page_size));
$offset = ($page - 1) * $page_size;

$list_stmt = $pdo->prepare("SELECT id, status, ip, country, region, city, isp, user_agent, login_at FROM huli_user_login_logs $where_sql ORDER BY login_at DESC LIMIT $page_size OFFSET $offset");
$list_stmt->execute($params);
$logs = $list_stmt->fetchAll(PDO::FETCH_ASSOC);

$stat_rows = $pdo->prepare("SELECT
  SUM(status='success') AS s_ok,
  SUM(status='failed') AS s_fail,
  SUM(status='locked') AS s_lock,
  COUNT(*) AS s_total,
  COUNT(DISTINCT ip) AS s_ip,
  COUNT(DISTINCT user_agent) AS s_ua
  FROM huli_user_login_logs WHERE user_id = ? AND login_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stat_rows->execute([$user_id]);
$s = $stat_rows->fetch(PDO::FETCH_ASSOC);
$s_ok7 = (int)($s['s_ok'] ?? 0);
$s_fail7 = (int)($s['s_fail'] ?? 0);
$s_lock7 = (int)($s['s_lock'] ?? 0);
$s_total7 = (int)($s['s_total'] ?? 0);
$s_ip7 = (int)($s['s_ip'] ?? 0);
$s_ua7 = (int)($s['s_ua'] ?? 0);

if (isset($_GET['action']) && $_GET['action'] === 'clear') {
    $keep = max(0, (int)($_GET['keep'] ?? 30));
    $pdo->prepare("DELETE FROM huli_user_login_logs WHERE user_id = ? AND login_at < DATE_SUB(NOW(), INTERVAL ? DAY)")->execute([$user_id, $keep]);
    header('Location: login_logs.php'); exit;
}

$status_label = ['success'=>'成功','failed'=>'失败','locked'=>'锁定'];
$status_color = ['success'=>'#10b981','failed'=>'#ef4444','locked'=>'#f59e0b'];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>我的登录日志 - <?php echo htmlspecialchars($site_name); ?></title>
<?php if (!empty($settings['favicon_url'])): ?><link rel="shortcut icon" type="image/x-icon" href="<?php echo htmlspecialchars($settings['favicon_url']); ?>"><?php endif; ?>
<style>
:root { --bg-color: 
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background:
  radial-gradient(circle at 18% 12%, rgba(186, 224, 255, .45), transparent 28rem),
  radial-gradient(circle at 82% 88%, rgba(196, 232, 240, .38), transparent 30rem),
  linear-gradient(135deg, 
  background-attachment: fixed; color: var(--text-normal); min-height: 100vh; line-height: 1.6; }

.sidebar-header { padding: 24px; border-bottom: 1px solid var(--border-color); }
.sidebar-logo { font-size: 22px; font-weight: 700; color: var(--text-dark); text-decoration: none; }
.user-info-panel { padding: 20px; text-align: center; border-bottom: 1px solid var(--border-color); }
.user-info-panel .avatar { width: 64px; height: 64px; border-radius: 50%; background: linear-gradient(135deg, 
.user-info-panel .username { font-size: 16px; font-weight: 600; color: var(--text-dark); }
.sidebar-nav { padding: 16px; flex-grow: 1; }
.nav-link { display: flex; align-items: center; padding: 12px 14px; border-radius: 10px; text-decoration: none; color: var(--text-normal); font-weight: 500; margin-bottom: 6px; transition: all .2s; }
.nav-link:hover { background: var(--primary-light); color: var(--primary-color); transform: translateX(2px); }
.nav-link.active { background: linear-gradient(135deg, var(--primary-color), 
.nav-link svg { margin-right: 10px; flex-shrink: 0; }
.sidebar-footer { padding: 20px; border-top: 1px solid var(--border-color); }
.btn-logout { display: block; width: 100%; text-align: center; padding: 12px; border-radius: 10px; background: linear-gradient(135deg, 
.btn-logout:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(239, 68, 68, .25); }

.main-header { display: flex; justify-content: space-between; align-items: center; padding: 16px 32px; background-color: rgba(255,255,255,.85); backdrop-filter: blur(12px); border-bottom: 1px solid var(--border-color); position: sticky; top: 0; z-index: 50; }

.content-wrapper { padding: 28px 32px; max-width: 1400px; margin: 0 auto; width: 100%; }
.page-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; }
.page-header h1 { font-size: 28px; font-weight: 800; color: var(--text-dark); margin: 0; }
.page-header .subtitle { color: var(--text-light); font-size: 14px; margin-top: 4px; }
.stat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; margin-bottom: 28px; }
.stat-card { background: rgba(255,255,255,.78); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); border-radius: 18px; border: 1px solid rgba(255,255,255,.6); box-shadow: 0 8px 28px rgba(64, 120, 180, .12); padding: 22px; transition: all .3s; position: relative; overflow: hidden; }
.stat-card:hover { transform: translateY(-3px); box-shadow: 0 14px 40px rgba(64, 120, 180, .22); }
.stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, transparent, currentColor, transparent); opacity: .6; }
.stat-card .label { font-size: 13px; color: var(--text-light); font-weight: 600; text-transform: uppercase; letter-spacing: .5px; }
.stat-card .value { font-size: 36px; font-weight: 800; margin-top: 6px; color: currentColor; }
.stat-card .icon { position: absolute; right: 18px; top: 50%; transform: translateY(-50%); font-size: 36px; opacity: .15; }
.stat-card.c-blue { color: 
.filter-bar { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 20px; padding: 16px 20px; background: rgba(255,255,255,.78); backdrop-filter: blur(14px); border-radius: 16px; border: 1px solid rgba(255,255,255,.6); box-shadow: 0 6px 20px rgba(64, 120, 180, .1); align-items: center; }
.filter-bar select, .filter-bar input[type=text] { height: 38px; padding: 0 12px; border-radius: 10px; border: 1px solid var(--border-color); background: 
.filter-bar select:focus, .filter-bar input[type=text]:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(59, 130, 246, .15); }
.filter-bar .btn { padding: 8px 16px; border-radius: 10px; border: none; font-weight: 600; cursor: pointer; transition: all .2s; font-size: 14px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
.btn-primary { background: linear-gradient(135deg, var(--primary-color), 
.btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(59, 130, 246, .35); }
.btn-ghost { background: rgba(255,255,255,.6); color: var(--text-normal); border: 1px solid var(--border-color); }
.btn-ghost:hover { background: 
.log-table { background: rgba(255,255,255,.82); backdrop-filter: blur(14px); border-radius: 18px; border: 1px solid rgba(255,255,255,.6); box-shadow: 0 8px 28px rgba(64, 120, 180, .12); overflow: hidden; }
.log-table table { width: 100%; border-collapse: collapse; }
.log-table thead { background: linear-gradient(135deg, rgba(238, 242, 255, .8), rgba(219, 234, 254, .6)); }
.log-table th { padding: 14px 16px; text-align: left; font-size: 13px; font-weight: 700; color: var(--text-dark); border-bottom: 2px solid rgba(59, 130, 246, .15); }
.log-table td { padding: 14px 16px; border-bottom: 1px solid rgba(229, 231, 235, .6); font-size: 14px; vertical-align: middle; }
.log-table tr:hover td { background: rgba(238, 242, 255, .35); }
.log-table tr:last-child td { border-bottom: none; }
.badge { display: inline-block; padding: 4px 10px; border-radius: 8px; font-size: 12px; font-weight: 700; }
.badge-success { background: rgba(16, 185, 129, .12); color: 
.badge-failed { background: rgba(239, 68, 68, .12); color: 
.badge-locked { background: rgba(245, 158, 11, .12); color: 
.geo { display: inline-flex; align-items: center; gap: 4px; color: var(--text-normal); }
.geo .country { font-weight: 600; color: var(--text-dark); }
.mono { font-family: 'Courier New', monospace; font-size: 13px; color: var(--text-dark); }
.ua { color: var(--text-light); font-size: 12px; max-width: 240px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.empty { padding: 60px 20px; text-align: center; color: var(--text-light); }
.pagination { display: flex; justify-content: center; gap: 6px; padding: 20px; }
.pagination a, .pagination span { padding: 8px 14px; border-radius: 8px; background: rgba(255,255,255,.7); border: 1px solid var(--border-color); color: var(--text-normal); text-decoration: none; font-weight: 600; font-size: 14px; transition: all .2s; }
.pagination a:hover { background: var(--primary-color); color: 
.pagination .current { background: linear-gradient(135deg, var(--primary-color), 
@media (max-width: 900px) { 
</style>
</head>
<body>
<div id="page-container">
<aside id="sidebar">
    <div class="sidebar-header"><a href="index.php" class="sidebar-logo"><?php echo htmlspecialchars($site_name); ?></a></div>
    <div class="user-info-panel">
        <div class="avatar"><?php echo mb_strtoupper(mb_substr($username ?: 'U', 0, 1)); ?></div>
        <div class="username"><?php echo htmlspecialchars($username ?: '用户'); ?></div>
    </div>
    <nav class="sidebar-nav">
        <a href="index.php" class="nav-link"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7A1 1 0 003 11h1v6a2 2 0 002 2h2a1 1 0 001-1v-3a1 1 0 011-1h2a1 1 0 011 1v3a1 1 0 001 1h2a2 2 0 002-2v-6h1a1 1 0 00.707-1.707l-7-7z"/></svg>用户中心</a>
        <a href="login_logs.php" class="nav-link active"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z"/></svg>我的登录日志</a>
    </nav>
    <div class="sidebar-footer"><a href="logout.php" class="btn-logout">安全退出</a></div>
</aside>
<div id="main-content">
    <header class="main-header">
        <button id="mobile-menu-btn" aria-label="menu"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm1 4a1 1 0 100 2h12a1 1 0 100-2H4z" clip-rule="evenodd"/></svg></button>
        <div style="font-size:14px;color:var(--text-light);">登录日志 / 安全审计</div>
    </header>
    <div class="content-wrapper">
        <div class="page-header">
            <div>
                <h1>我的登录日志</h1>
                <div class="subtitle">仅展示与本账号相关的登录记录，保留近 90 天内的数据</div>
            </div>
            <a href="login_logs.php?action=clear&keep=90" class="btn btn-ghost" onclick="return confirm('确认清理 90 天前的登录日志？')">清理 90 天前</a>
        </div>
        <div class="stat-grid">
            <div class="stat-card c-blue">
                <div class="label">近 7 天登录</div>
                <div class="value"><?php echo $s_total7; ?></div>
                <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z"/></svg>
            </div>
            <div class="stat-card c-green">
                <div class="label">近 7 天成功</div>
                <div class="value"><?php echo $s_ok7; ?></div>
                <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            </div>
            <div class="stat-card c-red">
                <div class="label">近 7 天失败</div>
                <div class="value"><?php echo $s_fail7; ?></div>
                <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
            </div>
            <div class="stat-card c-purple">
                <div class="label">独立 IP（7 天）</div>
                <div class="value"><?php echo $s_ip7; ?></div>
                <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 20 20" fill="currentColor"><path d="M2 5a2 2 0 012-2h12a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V5zm3.293 1.293a1 1 0 011.414 0L10 9.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/></svg>
            </div>
        </div>
        <form class="filter-bar" method="GET">
            <select name="status">
                <option value="">全部状态</option>
                <option value="success" <?php echo $status==='success'?'selected':''; ?>>成功</option>
                <option value="failed" <?php echo $status==='failed'?'selected':''; ?>>失败</option>
                <option value="locked" <?php echo $status==='locked'?'selected':''; ?>>锁定</option>
            </select>
            <input type="text" name="kw" placeholder="IP / 国家 / 地区 / 城市" value="<?php echo htmlspecialchars($keyword); ?>">
            <button type="submit" class="btn btn-primary">筛选</button>
            <a href="login_logs.php" class="btn btn-ghost">重置</a>
        </form>
        <div class="log-table">
            <table>
                <thead>
                    <tr>
                        <th>登录时间</th>
                        <th>状态</th>
                        <th>IP 地址</th>
                        <th>地理位置</th>
                        <th>网络 / ISP</th>
                        <th>设备</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="6" class="empty">暂无登录记录</td></tr>
                <?php else: foreach ($logs as $r): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($r['login_at']); ?></td>
                        <td><span class="badge badge-<?php echo htmlspecialchars($r['status']); ?>"><?php echo $status_label[$r['status']] ?? $r['status']; ?></span></td>
                        <td><span class="mono"><?php echo htmlspecialchars($r['ip']); ?></span></td>
                        <td><span class="geo"><span class="country"><?php echo htmlspecialchars($r['country']); ?></span> <?php echo htmlspecialchars($r['region']); ?> <?php echo htmlspecialchars($r['city']); ?></span></td>
                        <td><?php echo htmlspecialchars($r['isp']); ?></td>
                        <td><span class="ua" title="<?php echo htmlspecialchars($r['user_agent']); ?>"><?php echo htmlspecialchars($r['user_agent']); ?></span></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php
                $base_qs = http_build_query(array_filter(['status'=>$status,'kw'=>$keyword]));
                $prev = max(1, $page - 1); $next = min($total_pages, $page + 1);
                if ($page > 1) echo '<a href="?page='.$prev.'&'.$base_qs.'">上一页</a>';
                $start = max(1, $page - 2); $end = min($total_pages, $page + 2);
                for ($i = $start; $i <= $end; $i++) {
                    if ($i === $page) echo '<span class="current">'.$i.'</span>';
                    else echo '<a href="?page='.$i.'&'.$base_qs.'">'.$i.'</a>';
                }
                if ($page < $total_pages) echo '<a href="?page='.$next.'&'.$base_qs.'">下一页</a>';
                ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>
<script>
document.getElementById('mobile-menu-btn').addEventListener('click', function() {
    document.getElementById('sidebar').style.transform = (document.getElementById('sidebar').style.transform === 'translateX(0px)') ? 'translateX(-100%)' : 'translateX(0)';
});
</script>
</body>
</html>
