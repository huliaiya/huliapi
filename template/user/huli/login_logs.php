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
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:Inter,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f4f6fb;color:#1e293b;min-height:100vh;line-height:1.5;}
.content-wrapper{padding:24px 28px;max-width:1200px;margin:0 auto;width:100%;}
.page-header{display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:20px;}
.page-header h1{font-size:22px;font-weight:700;color:#1e293b;margin:0;}
.page-header .subtitle{color:#94a3b8;font-size:13px;margin-top:4px;}
.stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px;}
.stat-card{background:rgba(255,255,255,.82);backdrop-filter:blur(12px);border-radius:14px;border:1px solid rgba(255,255,255,.5);box-shadow:0 1px 3px rgba(0,0,0,.04),0 4px 12px rgba(0,0,0,.04);padding:18px;position:relative;overflow:hidden;transition:box-shadow .2s;}
.stat-card:hover{box-shadow:0 4px 16px rgba(0,0,0,.06);}
.stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,transparent,currentColor,transparent);opacity:.6;}
.stat-card .label{font-size:12px;color:#64748b;font-weight:600;letter-spacing:.5px;}
.stat-card .value{font-size:32px;font-weight:800;margin-top:4px;color:currentColor;}
.stat-card .icon{position:absolute;right:16px;top:50%;transform:translateY(-50%);font-size:32px;opacity:.12;}
.stat-card.c-blue{color:#4f6ef7;}
.stat-card.c-green{color:#059669;}
.stat-card.c-red{color:#dc2626;}
.stat-card.c-purple{color:#7c3aed;}
.filter-bar{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:18px;padding:14px 18px;background:rgba(255,255,255,.82);backdrop-filter:blur(12px);border-radius:14px;border:1px solid rgba(255,255,255,.5);box-shadow:0 1px 3px rgba(0,0,0,.04),0 4px 12px rgba(0,0,0,.04);align-items:center;}
.filter-bar select,.filter-bar input[type=text]{height:36px;padding:0 11px;border-radius:9px;border:1px solid #e2e8f0;background:#fff;font-size:13px;color:#1e293b;transition:border-color .2s,box-shadow .2s;}
.filter-bar select:focus,.filter-bar input[type=text]:focus{outline:none;border-color:#4f6ef7;box-shadow:0 0 0 3px rgba(79,110,247,.12);}
.filter-bar .btn{padding:7px 14px;border-radius:9px;border:none;font-weight:600;cursor:pointer;font-size:13px;transition:opacity .2s;text-decoration:none;display:inline-flex;align-items:center;gap:6px;}
.btn-primary{background:linear-gradient(135deg,#4f6ef7,#6c8cff);color:#fff;}
.btn-primary:hover{opacity:.9;}
.btn-ghost{background:#fff;color:#64748b;border:1px solid #e2e8f0;}
.btn-ghost:hover{border-color:#4f6ef7;color:#4f6ef7;}
.btn-warn{background:linear-gradient(135deg,#f59e0b,#fb923c);color:#fff;border:1px solid transparent;box-shadow:0 1px 2px rgba(245,158,11,.25);}
.btn-warn:hover{opacity:.92;transform:translateY(-1px);box-shadow:0 4px 10px rgba(245,158,11,.3);}
.btn-warn svg{flex-shrink:0;}
.log-table{background:rgba(255,255,255,.82);backdrop-filter:blur(12px);border-radius:14px;border:1px solid rgba(255,255,255,.5);box-shadow:0 1px 3px rgba(0,0,0,.04),0 4px 12px rgba(0,0,0,.04);overflow-x:auto;overflow-y:hidden;}
.log-table::-webkit-scrollbar{height:8px;}
.log-table::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:4px;}
.log-table::-webkit-scrollbar-thumb:hover{background:#94a3b8;}
.log-table table{width:100%;border-collapse:collapse;min-width:880px;}
.log-table thead{background:linear-gradient(135deg,#eef1ff,#dce6ff);}
.log-table th{padding:12px 14px;text-align:left;font-size:12px;font-weight:700;color:#1e293b;border-bottom:2px solid rgba(79,110,247,.15);white-space:nowrap;}
.log-table td{padding:11px 14px;border-bottom:1px solid rgba(226,232,240,.6);font-size:13px;vertical-align:middle;color:#1e293b;}
.log-table tr:hover td{background:rgba(238,242,255,.35);}
.log-table tr:last-child td{border-bottom:none;}
.badge{display:inline-block;padding:3px 9px;border-radius:6px;font-size:11px;font-weight:700;}
.badge-success{background:#ecfdf5;color:#059669;}
.badge-failed{background:#fef2f2;color:#dc2626;}
.badge-locked{background:#fffbeb;color:#d97706;}
.mono{display:inline-block;max-width:200px;font-family:'Courier New',monospace;font-size:13px;color:#1e293b;white-space:nowrap;overflow-x:auto;vertical-align:middle;}
.mono::-webkit-scrollbar{height:4px;}
.mono::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:2px;}
.geo{display:inline-flex;align-items:center;gap:4px;color:#1e293b;}
.geo .country{font-weight:600;color:#1e293b;}
.isp{max-width:220px;display:inline-block;white-space:nowrap;overflow-x:auto;vertical-align:middle;color:#1e293b;}
.isp::-webkit-scrollbar{height:4px;}
.isp::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:2px;}
.geo-wrap{max-width:240px;display:inline-block;white-space:nowrap;overflow-x:auto;vertical-align:middle;}
.geo-wrap::-webkit-scrollbar{height:4px;}
.geo-wrap::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:2px;}
.ua{display:inline-block;max-width:260px;color:#64748b;font-size:12px;white-space:nowrap;overflow-x:auto;vertical-align:middle;}
.ua::-webkit-scrollbar{height:4px;}
.ua::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:2px;}
.empty{padding:50px 20px;text-align:center;color:#94a3b8;font-size:14px;}
.pagination{display:flex;justify-content:center;gap:6px;padding:16px;}
.pagination a,.pagination span{padding:7px 12px;border-radius:8px;background:rgba(255,255,255,.7);border:1px solid #e2e8f0;color:#1e293b;text-decoration:none;font-weight:600;font-size:13px;transition:all .2s;}
.pagination a:hover{border-color:#4f6ef7;color:#4f6ef7;}
.pagination .current{background:linear-gradient(135deg,#4f6ef7,#6c8cff);color:#fff;border-color:transparent;}
@media(max-width:900px){.stat-grid{grid-template-columns:repeat(2,1fr);}.content-wrapper{padding:16px;}}
</style>
</head>
<body>
<div class="content-wrapper">
    <div class="page-header">
        <div>
            <h1>我的登录日志</h1>
            <div class="subtitle">仅展示与本账号相关的登录记录，保留近 90 天内的数据</div>
        </div>
        <a href="login_logs.php?action=clear&keep=90" class="btn btn-warn" onclick="return confirm('确认清理 90 天前的登录日志？')"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>清理 90 天前</a>
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
                        <td><span class="geo-wrap"><span class="country"><?php echo htmlspecialchars($r['country']); ?></span> <?php echo htmlspecialchars($r['region']); ?> <?php echo htmlspecialchars($r['city']); ?></span></td>
                        <td><span class="isp"><?php echo htmlspecialchars($r['isp']); ?></span></td>
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
</body>
</html>
