<?php
require_once __DIR__ . '/../../../common/session_boot.php';
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

$country_emoji = [
    '中国'=>'🇨🇳','China'=>'🇨🇳','CN'=>'🇨🇳','香港'=>'🇭🇰','Hong Kong'=>'🇭🇰','HK'=>'🇭🇰','澳门'=>'🇲🇴','MO'=>'🇲🇴',
    '台湾'=>'🇹🇼','TW'=>'🇹🇼','美国'=>'🇺🇸','United States'=>'🇺🇸','US'=>'🇺🇸','日本'=>'🇯🇵','Japan'=>'🇯🇵','JP'=>'🇯🇵',
    '韩国'=>'🇰🇷','KR'=>'🇰🇷','英国'=>'🇬🇧','GB'=>'🇬🇧','德国'=>'🇩🇪','DE'=>'🇩🇪','法国'=>'🇫🇷','FR'=>'🇫🇷',
    '俄罗斯'=>'🇷🇺','RU'=>'🇷🇺','加拿大'=>'🇨🇦','CA'=>'🇨🇦','澳大利亚'=>'🇦🇺','AU'=>'🇦🇺','新加坡'=>'🇸🇬','SG'=>'🇸🇬',
];

function huli_emoji_for_country($c, $map) {
    $c = trim((string)$c);
    if ($c === '') return '🌍';
    if (isset($map[$c])) return $map[$c];
    foreach ($map as $k => $v) { if (mb_stripos($c, $k) !== false) return $v; }
    return '🌍';
}

function huli_parse_ua($ua) {
    $ua = (string)$ua;
    if ($ua === '') return ['os'=>'未知系统','browser'=>'未知浏览器','os_icon'=>'mdi-monitor','browser_icon'=>'mdi-help-circle-outline'];
    $os = '未知系统'; $os_icon = 'mdi-monitor';
    if (preg_match('/Windows NT 10/i', $ua)) { $os = 'Windows 10/11'; $os_icon = 'mdi-microsoft-windows'; }
    elseif (preg_match('/Windows NT 6\.3/i', $ua)) { $os = 'Windows 8.1'; $os_icon = 'mdi-microsoft-windows'; }
    elseif (preg_match('/Windows NT 6\.2/i', $ua)) { $os = 'Windows 8'; $os_icon = 'mdi-microsoft-windows'; }
    elseif (preg_match('/Windows NT 6\.1/i', $ua)) { $os = 'Windows 7'; $os_icon = 'mdi-microsoft-windows'; }
    elseif (preg_match('/Windows/i', $ua)) { $os = 'Windows'; $os_icon = 'mdi-microsoft-windows'; }
    elseif (preg_match('/Mac OS X|Macintosh/i', $ua)) {
        if (preg_match('/iPhone|iPad|iPod/i', $ua)) { $os = preg_match('/iPad/i', $ua) ? 'iPadOS' : 'iOS'; $os_icon = 'mdi-apple'; }
        else { $os = 'macOS'; $os_icon = 'mdi-apple'; }
    }
    elseif (preg_match('/Android/i', $ua)) { $os = 'Android'; $os_icon = 'mdi-android'; }
    elseif (preg_match('/Linux/i', $ua)) { $os = 'Linux'; $os_icon = 'mdi-linux'; }

    $browser = '未知浏览器'; $browser_icon = 'mdi-help-circle-outline';
    $browsers = [
        ['Edge','Edge','mdi-microsoft-edge'],
        ['EdgA|Edgi|Edg\\/|Edg\\.','Edge','mdi-microsoft-edge'],
        ['OPR|Opera','Opera','mdi-microsoft-edge'],
        ['Chrome','Chrome','mdi-google-chrome'],
        ['Firefox','Firefox','mdi-firefox'],
        ['Safari','Safari','mdi-apple-safari'],
        ['MSIE|Trident','IE','mdi-microsoft-edge'],
    ];
    foreach ($browsers as $b) {
        if (preg_match('/' . $b[0] . '/i', $ua)) { $browser = $b[1]; $browser_icon = $b[2]; break; }
    }
    return compact('os','browser','os_icon','browser_icon');
}

function huli_format_login_time($ts) {
    $t = strtotime($ts);
    if (!$t) return htmlspecialchars($ts);
    $diff = time() - $t;
    if ($diff < 60) return '刚刚';
    if ($diff < 3600) { $m = (int)floor($diff/60); return $m . ' 分钟前'; }
    if ($diff < 86400) { $h = (int)floor($diff/3600); return $h . ' 小时前'; }
    if ($diff < 86400 * 7) { $d = (int)floor($diff/86400); return $d . ' 天前'; }
    return htmlspecialchars($ts);
}

$ua_cache = [];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>我的登录日志 - <?php echo htmlspecialchars($site_name); ?></title>
<link rel="stylesheet" type="text/css" href="../../../assets/css/materialdesignicons.min.css">
<link rel="stylesheet" type="text/css" href="../../../assets/css/bootstrap.min.css">
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:Inter,-apple-system,BlinkMacSystemFont,'Segoe UI','PingFang SC','Microsoft YaHei',sans-serif;background:linear-gradient(180deg,#f4f6fb 0%,#eef1f8 100%);color:#1e293b;min-height:100vh;line-height:1.5;}
.content-wrapper{padding:24px 28px;max-width:1200px;margin:0 auto;width:100%;}
.top-nav{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;}
.top-nav .back{color:#64748b;text-decoration:none;font-size:14px;display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:8px;transition:background .2s,color .2s;}
.top-nav .back:hover{background:#fff;color:#4f6ef7;}
.top-nav .crumb{font-size:13px;color:#94a3b8;}
.top-nav .crumb b{color:#1e293b;font-weight:600;}
.page-header{display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:20px;gap:16px;flex-wrap:wrap;}
.page-header h1{font-size:24px;font-weight:700;color:#1e293b;margin:0;letter-spacing:-.3px;}
.page-header .subtitle{color:#94a3b8;font-size:13px;margin-top:6px;}
.stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px;}
.stat-card{background:rgba(255,255,255,.85);backdrop-filter:blur(12px);border-radius:14px;border:1px solid rgba(255,255,255,.6);box-shadow:0 1px 3px rgba(0,0,0,.04),0 4px 12px rgba(0,0,0,.04);padding:18px;position:relative;overflow:hidden;transition:box-shadow .2s,transform .2s;}
.stat-card:hover{box-shadow:0 4px 16px rgba(0,0,0,.08);transform:translateY(-1px);}
.stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,transparent,currentColor,transparent);opacity:.55;}
.stat-card .label{font-size:12px;color:#64748b;font-weight:600;letter-spacing:.5px;}
.stat-card .value{font-size:30px;font-weight:800;margin-top:6px;color:currentColor;line-height:1.1;}
.stat-card .icon{position:absolute;right:14px;top:50%;transform:translateY(-50%);font-size:36px;opacity:.14;}
.stat-card.c-blue{color:#4f6ef7;}
.stat-card.c-green{color:#059669;}
.stat-card.c-red{color:#dc2626;}
.stat-card.c-purple{color:#7c3aed;}
.filter-bar{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:18px;padding:14px 18px;background:rgba(255,255,255,.82);backdrop-filter:blur(12px);border-radius:14px;border:1px solid rgba(255,255,255,.6);box-shadow:0 1px 3px rgba(0,0,0,.04),0 4px 12px rgba(0,0,0,.04);align-items:center;}
.filter-bar select,.filter-bar input[type=text]{height:36px;padding:0 12px;border-radius:9px;border:1px solid #e2e8f0;background:#fff;font-size:13px;color:#1e293b;transition:border-color .2s,box-shadow .2s;min-width:140px;}
.filter-bar select:focus,.filter-bar input[type=text]:focus{outline:none;border-color:#4f6ef7;box-shadow:0 0 0 3px rgba(79,110,247,.12);}
.filter-bar input[type=text]{min-width:200px;}
.filter-bar .btn{padding:7px 14px;border-radius:9px;border:none;font-weight:600;cursor:pointer;font-size:13px;transition:opacity .2s,transform .2s,box-shadow .2s;text-decoration:none;display:inline-flex;align-items:center;gap:6px;}
.btn-primary{background:linear-gradient(135deg,#4f6ef7,#6c8cff);color:#fff;}
.btn-primary:hover{opacity:.95;transform:translateY(-1px);box-shadow:0 4px 10px rgba(79,110,247,.25);}
.btn-ghost{background:#fff;color:#64748b;border:1px solid #e2e8f0;}
.btn-ghost:hover{border-color:#4f6ef7;color:#4f6ef7;}
.btn-warn{background:linear-gradient(135deg,#f59e0b,#fb923c);color:#fff;border:1px solid transparent;box-shadow:0 1px 2px rgba(245,158,11,.25);}
.btn-warn:hover{opacity:.92;transform:translateY(-1px);box-shadow:0 4px 10px rgba(245,158,11,.3);}
.btn-warn svg{flex-shrink:0;}
.log-card{background:rgba(255,255,255,.82);backdrop-filter:blur(12px);border-radius:14px;border:1px solid rgba(255,255,255,.6);box-shadow:0 1px 3px rgba(0,0,0,.04),0 4px 12px rgba(0,0,0,.04);overflow:hidden;}
.log-table-wrap{overflow-x:auto;}
.log-table-wrap::-webkit-scrollbar{height:8px;}
.log-table-wrap::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:4px;}
.log-table-wrap::-webkit-scrollbar-thumb:hover{background:#94a3b8;}
.log-table{width:100%;border-collapse:collapse;min-width:880px;}
.log-table thead{background:linear-gradient(135deg,#eef1ff,#dce6ff);}
.log-table th{padding:13px 16px;text-align:left;font-size:12px;font-weight:700;color:#1e293b;border-bottom:2px solid rgba(79,110,247,.18);white-space:nowrap;letter-spacing:.3px;}
.log-table td{padding:14px 16px;border-bottom:1px solid rgba(226,232,240,.55);font-size:13px;vertical-align:middle;color:#1e293b;}
.log-table tr:last-child td{border-bottom:none;}
.log-table tbody tr{transition:background .15s;}
.log-table tbody tr:hover td{background:rgba(238,242,255,.45);}
.cell-time .time-main{font-weight:600;color:#1e293b;font-size:13px;}
.cell-time .time-sub{font-size:11px;color:#94a3b8;margin-top:2px;}
.cell-time.ago .time-main{color:#4f6ef7;}
.cell-status .badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:6px;font-size:11px;font-weight:700;}
.badge-success{background:#ecfdf5;color:#059669;}
.badge-failed{background:#fef2f2;color:#dc2626;}
.badge-locked{background:#fffbeb;color:#d97706;}
.cell-ip .ip-main{font-family:'JetBrains Mono','SF Mono','Courier New',monospace;font-size:13px;font-weight:600;color:#1e293b;background:#f1f5f9;padding:2px 8px;border-radius:6px;display:inline-block;}
.cell-geo{font-size:13px;line-height:1.45;}
.cell-geo .country-line{font-weight:600;color:#1e293b;display:flex;align-items:center;gap:6px;}
.cell-geo .country-line .flag{font-size:16px;line-height:1;}
.cell-geo .region{font-size:12px;color:#64748b;margin-top:2px;}
.cell-geo .empty{color:#cbd5e1;font-size:12px;}
.cell-isp{max-width:220px;font-size:12px;color:#64748b;}
.cell-ua{display:flex;align-items:center;gap:10px;}
.cell-ua .ua-icon{font-size:24px;color:#4f6ef7;flex-shrink:0;}
.cell-ua .ua-info .ua-os{font-weight:600;color:#1e293b;font-size:13px;}
.cell-ua .ua-info .ua-browser{font-size:11px;color:#64748b;margin-top:2px;display:flex;align-items:center;gap:4px;}
.cell-ua .ua-info .ua-browser i{font-size:13px;color:#94a3b8;}
.cell-ua .ua-info .ua-full{display:none;}
.empty-state{padding:60px 20px;text-align:center;}
.empty-state .empty-icon{font-size:48px;color:#cbd5e1;margin-bottom:12px;}
.empty-state .empty-text{color:#94a3b8;font-size:14px;}
.pagination{display:flex;justify-content:center;gap:6px;padding:18px 16px;border-top:1px solid rgba(226,232,240,.55);}
.pagination a,.pagination span{padding:7px 12px;border-radius:8px;background:rgba(255,255,255,.7);border:1px solid #e2e8f0;color:#1e293b;text-decoration:none;font-weight:600;font-size:13px;transition:all .2s;min-width:36px;text-align:center;}
.pagination a:hover{border-color:#4f6ef7;color:#4f6ef7;transform:translateY(-1px);}
.pagination .current{background:linear-gradient(135deg,#4f6ef7,#6c8cff);color:#fff;border-color:transparent;}
@media(max-width:900px){.stat-grid{grid-template-columns:repeat(2,1fr);}.content-wrapper{padding:16px;}.page-header h1{font-size:20px;}}
</style>
</head>
<body>
<div class="content-wrapper">
    <div class="top-nav">
        <a href="main.php" class="back"><i class="mdi mdi-arrow-left"></i> 返回用户中心</a>
        <div class="crumb">用户中心 / <b>登录日志</b></div>
    </div>
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
            <i class="mdi mdi-login icon"></i>
        </div>
        <div class="stat-card c-green">
            <div class="label">近 7 天成功</div>
            <div class="value"><?php echo $s_ok7; ?></div>
            <i class="mdi mdi-check-circle-outline icon"></i>
        </div>
        <div class="stat-card c-red">
            <div class="label">近 7 天失败</div>
            <div class="value"><?php echo $s_fail7; ?></div>
            <i class="mdi mdi-close-circle-outline icon"></i>
        </div>
        <div class="stat-card c-purple">
            <div class="label">独立设备（7 天）</div>
            <div class="value"><?php echo $s_ua7; ?></div>
            <i class="mdi mdi-devices icon"></i>
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
        <button type="submit" class="btn btn-primary"><i class="mdi mdi-magnify"></i>筛选</button>
        <a href="login_logs.php" class="btn btn-ghost">重置</a>
    </form>
    <div class="log-card">
        <div class="log-table-wrap">
            <table class="log-table">
                <thead>
                    <tr>
                        <th style="width:130px;">登录时间</th>
                        <th style="width:90px;">状态</th>
                        <th style="width:140px;">IP 地址</th>
                        <th style="width:180px;">地理位置</th>
                        <th style="width:170px;">网络 / ISP</th>
                        <th>设备</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="6"><div class="empty-state"><i class="mdi mdi-clipboard-text-outline empty-icon"></i><div class="empty-text">暂无登录记录</div></div></td></tr>
                <?php else: foreach ($logs as $r):
                    $ua_key = $r['user_agent'];
                    if (!isset($ua_cache[$ua_key])) { $ua_cache[$ua_key] = huli_parse_ua($ua_key); }
                    $u = $ua_cache[$ua_key];
                    $flag = huli_emoji_for_country($r['country'], $country_emoji);
                    $ago = huli_format_login_time($r['login_at']);
                    $is_recent = (time() - strtotime($r['login_at'])) < 86400;
                ?>
                    <tr>
                        <td class="cell-time <?php echo $is_recent ? 'ago' : ''; ?>">
                            <div class="time-main"><?php echo $ago; ?></div>
                            <div class="time-sub"><?php echo htmlspecialchars($r['login_at']); ?></div>
                        </td>
                        <td class="cell-status"><span class="badge badge-<?php echo htmlspecialchars($r['status']); ?>"><?php echo $status_label[$r['status']] ?? $r['status']; ?></span></td>
                        <td class="cell-ip"><span class="ip-main"><?php echo htmlspecialchars($r['ip']); ?></span></td>
                        <td class="cell-geo">
                            <?php if (!empty($r['country'])): ?>
                                <div class="country-line"><span class="flag"><?php echo $flag; ?></span><?php echo htmlspecialchars($r['country']); ?></div>
                                <?php if (!empty($r['region']) || !empty($r['city'])): ?>
                                    <div class="region"><?php echo htmlspecialchars(trim($r['region'] . ' ' . $r['city'])); ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="empty">未知</span>
                            <?php endif; ?>
                        </td>
                        <td class="cell-isp"><?php echo !empty($r['isp']) ? htmlspecialchars($r['isp']) : '<span style="color:#cbd5e1;">未知</span>'; ?></td>
                        <td class="cell-ua">
                            <i class="mdi <?php echo $u['os_icon']; ?> ua-icon"></i>
                            <div class="ua-info">
                                <div class="ua-os"><?php echo htmlspecialchars($u['os']); ?></div>
                                <div class="ua-browser"><i class="mdi <?php echo $u['browser_icon']; ?>"></i><?php echo htmlspecialchars($u['browser']); ?></div>
                                <div class="ua-full" title="<?php echo htmlspecialchars($r['user_agent']); ?>"><?php echo htmlspecialchars($r['user_agent']); ?></div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
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
