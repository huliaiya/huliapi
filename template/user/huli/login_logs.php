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
    $pdo = new PDO("mysql:host=" . DB_HOST . ";port=" . (defined('DB_PORT') ? DB_PORT : 3306) . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
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
:root{
    --h-bg:#f5f7fc;
    --h-surface:rgba(255,255,255,.86);
    --h-border:rgba(210,222,245,.65);
    --h-text:#1e293b;
    --h-muted:#7c8aa5;
    --h-accent:#4f6ef7;
    --h-radius:16px;
    --h-shadow:0 1px 2px rgba(16,38,86,.05),0 8px 24px rgba(16,38,86,.06);
}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:Inter,-apple-system,BlinkMacSystemFont,'Segoe UI','PingFang SC','Microsoft YaHei',sans-serif;background:radial-gradient(1200px 600px at 15% -10%,#e8efff 0%,transparent 55%),radial-gradient(900px 500px at 100% 0%,#eef6ff 0%,transparent 50%),var(--h-bg);color:var(--h-text);min-height:100vh;line-height:1.55;}
.content-wrapper{padding:26px 28px 40px;max-width:1180px;margin:0 auto;width:100%;}
.top-nav{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;}
.top-nav .back{color:var(--h-muted);text-decoration:none;font-size:13px;display:inline-flex;align-items:center;gap:6px;padding:7px 13px;border-radius:10px;border:1px solid transparent;transition:.2s;}
.top-nav .back:hover{background:#fff;border-color:var(--h-border);color:var(--h-accent);}
.top-nav .crumb{font-size:13px;color:#a3b0c5;}
.top-nav .crumb b{color:var(--h-text);font-weight:600;}
.page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;gap:16px;flex-wrap:wrap;}
.page-header .title-wrap{display:flex;align-items:flex-start;gap:14px;}
.page-header .title-icon{width:46px;height:46px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:24px;color:#fff;background:linear-gradient(135deg,#4f6ef7,#7f9bff);box-shadow:0 8px 20px rgba(79,110,247,.28);flex-shrink:0;}
.page-header h1{font-size:22px;font-weight:700;letter-spacing:-.2px;margin:0;}
.page-header .subtitle{color:var(--h-muted);font-size:13px;margin-top:4px;}

.panel{background:var(--h-surface);backdrop-filter:blur(16px);border:1px solid var(--h-border);border-radius:var(--h-radius);box-shadow:var(--h-shadow);}
.stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px;}
.stat-card{padding:18px 18px 16px;position:relative;overflow:hidden;transition:transform .18s,box-shadow .18s;}
.stat-card:hover{transform:translateY(-2px);box-shadow:0 2px 4px rgba(16,38,86,.06),0 14px 30px rgba(16,38,86,.09);}
.stat-card .stat-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;}
.stat-card .label{font-size:12px;color:var(--h-muted);font-weight:600;letter-spacing:.3px;}
.stat-card .tag{font-size:11px;color:#9aa7bd;background:#f1f5fd;border-radius:6px;padding:2px 7px;}
.stat-card .value{font-size:30px;font-weight:800;line-height:1;letter-spacing:-.5px;}
.stat-card .value small{font-size:13px;font-weight:600;color:var(--h-muted);margin-left:4px;}
.stat-card .bar{height:4px;border-radius:4px;background:#edf1fa;margin-top:14px;overflow:hidden;}
.stat-card .bar span{display:block;height:100%;border-radius:4px;width:0;transition:width .8s cubic-bezier(.4,0,.2,1);}
.stat-card.c-blue .value{color:#3d5cf0;}
.stat-card.c-blue .bar span{background:linear-gradient(90deg,#4f6ef7,#7f9bff);}
.stat-card.c-green .value{color:#0f9f6e;}
.stat-card.c-green .bar span{background:linear-gradient(90deg,#10b981,#5fd4a7);}
.stat-card.c-red .value{color:#e04545;}
.stat-card.c-red .bar span{background:linear-gradient(90deg,#ef4444,#ff9a9a);}
.stat-card.c-purple .value{color:#7c3aed;}
.stat-card.c-purple .bar span{background:linear-gradient(90deg,#8b5cf6,#c3aaff);}

.filter-bar{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:18px;padding:14px 16px;align-items:center;}
.filter-bar .field{position:relative;display:flex;align-items:center;}
.filter-bar .field i{position:absolute;left:12px;font-size:16px;color:#a3b0c5;pointer-events:none;}
.filter-bar select,.filter-bar input[type=text]{height:38px;border-radius:10px;border:1px solid var(--h-border);background:#fff;font-size:13px;color:var(--h-text);transition:.2s;}
.filter-bar select{padding:0 32px 0 12px;min-width:132px;appearance:none;background-image:url("data:image/svg+xml;charset=utf8,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='%23a3b0c5'%3E%3Cpath d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;}
.filter-bar input[type=text]{padding:0 14px 0 36px;min-width:210px;}
.filter-bar select:focus,.filter-bar input[type=text]:focus{outline:none;border-color:var(--h-accent);box-shadow:0 0 0 3px rgba(79,110,247,.13);}
.filter-bar .spacer{flex:1;}
.btn{padding:8px 15px;border-radius:10px;border:1px solid transparent;font-weight:600;cursor:pointer;font-size:13px;transition:.2s;text-decoration:none;display:inline-flex;align-items:center;gap:6px;white-space:nowrap;}
.btn i{font-size:16px;}
.btn-primary{background:linear-gradient(135deg,#4f6ef7,#6c8cff);color:#fff;box-shadow:0 4px 12px rgba(79,110,247,.25);}
.btn-primary:hover{transform:translateY(-1px);box-shadow:0 6px 16px rgba(79,110,247,.32);}
.btn-ghost{background:#fff;color:var(--h-muted);border-color:var(--h-border);}
.btn-ghost:hover{border-color:var(--h-accent);color:var(--h-accent);}
.btn-warn{background:#fff;color:#c2760a;border-color:#ffe1b0;}
.btn-warn:hover{background:#fff9ee;border-color:#f7b64d;}

.log-card{overflow:hidden;}
.log-table-wrap{overflow-x:auto;}
.log-table-wrap::-webkit-scrollbar{height:8px;}
.log-table-wrap::-webkit-scrollbar-thumb{background:#d3dcec;border-radius:4px;}
.log-table{width:100%;border-collapse:collapse;min-width:920px;}
.log-table thead th{padding:12px 16px;text-align:left;font-size:11.5px;font-weight:700;color:#7c8aa5;letter-spacing:.6px;text-transform:uppercase;background:#f7f9fe;border-bottom:1px solid var(--h-border);white-space:nowrap;}
.log-table td{padding:14px 16px;border-bottom:1px solid rgba(226,232,240,.6);font-size:13px;vertical-align:middle;}
.log-table tbody tr{transition:background .15s;cursor:default;}
.log-table tbody tr:hover td{background:#f7f9ff;}
.log-table tr:last-child td{border-bottom:none;}
.log-table tr.is-failed td:first-child{box-shadow:inset 3px 0 0 #f0a0a0;}
.log-table tr.is-locked td:first-child{box-shadow:inset 3px 0 0 #f3c478;}
.log-table tr.is-success td:first-child{box-shadow:inset 3px 0 0 #7fd3b1;}

.cell-time .time-main{font-weight:600;font-size:13px;}
.cell-time .time-sub{font-size:11px;color:#a3b0c5;margin-top:3px;font-variant-numeric:tabular-nums;}
.cell-time.ago .time-main{color:var(--h-accent);}

.chip{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:999px;font-size:11.5px;font-weight:700;}
.chip::before{content:'';width:6px;height:6px;border-radius:50%;background:currentColor;}
.chip-success{background:#eafaf2;color:#0f9f6e;}
.chip-failed{background:#fdeeee;color:#d93a3a;}
.chip-locked{background:#fff6e6;color:#c2760a;}

.cell-ip .ip-main{font-family:'JetBrains Mono','SF Mono',ui-monospace,monospace;font-size:12.5px;font-weight:600;background:#f3f6fd;border:1px solid #e6ecfa;padding:3px 9px;border-radius:8px;display:inline-block;color:#334155;}
.cell-geo .country-line{font-weight:600;display:flex;align-items:center;gap:7px;}
.cell-geo .flag{font-size:16px;line-height:1;}
.cell-geo .region{font-size:12px;color:var(--h-muted);margin-top:2px;}
.cell-geo .empty,.cell-isp .empty{color:#c8d2e2;font-size:12px;}
.cell-isp{max-width:210px;font-size:12px;color:var(--h-muted);}
.cell-ua{display:flex;align-items:center;gap:11px;}
.cell-ua .ua-icon{font-size:24px;color:var(--h-accent);flex-shrink:0;background:#eef2ff;border-radius:10px;width:38px;height:38px;display:flex;align-items:center;justify-content:center;}
.cell-ua .ua-os{font-weight:600;font-size:13px;}
.cell-ua .ua-browser{font-size:11.5px;color:var(--h-muted);margin-top:2px;display:flex;align-items:center;gap:5px;}
.cell-ua .ua-browser i{font-size:13px;color:#a3b0c5;}
.ua-full{display:none;}

.empty-state{padding:64px 20px;text-align:center;}
.empty-state .empty-icon{width:64px;height:64px;margin:0 auto 14px;border-radius:20px;background:#eef2ff;color:#9db1ff;display:flex;align-items:center;justify-content:center;font-size:30px;}
.empty-state .empty-text{color:#9aa7bd;font-size:14px;}

.pagination{display:flex;justify-content:space-between;align-items:center;gap:8px;padding:16px;border-top:1px solid var(--h-border);flex-wrap:wrap;}
.pagination .pages{display:flex;gap:6px;flex-wrap:wrap;}
.pagination a,.pagination span{padding:7px 12px;border-radius:9px;background:#fff;border:1px solid var(--h-border);color:var(--h-text);text-decoration:none;font-weight:600;font-size:13px;transition:.2s;min-width:36px;text-align:center;}
.pagination a:hover{border-color:var(--h-accent);color:var(--h-accent);}
.pagination .current{background:linear-gradient(135deg,#4f6ef7,#6c8cff);color:#fff;border-color:transparent;}
.pagination .meta{font-size:12.5px;color:var(--h-muted);}

@media(max-width:900px){
    .stat-grid{grid-template-columns:repeat(2,1fr);}
    .content-wrapper{padding:16px;}
    .page-header h1{font-size:19px;}
    .log-table thead{display:none;}
    .log-table,.log-table tbody,.log-table tr,.log-table td{display:block;width:100%;}
    .log-table{min-width:0;}
    .log-table-wrap{overflow-x:visible;}
    .log-table tbody tr{margin:0 12px 12px;padding:14px 16px;border-radius:14px;background:#fff;border:1px solid var(--h-border);box-shadow:0 1px 2px rgba(16,38,86,.04);}
    .log-table tbody tr:first-child{margin-top:12px;}
    .log-table tr.is-failed td:first-child,.log-table tr.is-locked td:first-child,.log-table tr.is-success td:first-child{box-shadow:none;}
    .log-table td{padding:9px 0;border-bottom:1px dashed rgba(226,232,240,.8);display:flex;align-items:center;gap:12px;}
    .log-table td:last-child{border-bottom:none;padding-bottom:0;}
    .log-table td:first-child{padding-top:0;}
    .log-table td::before{content:attr(data-label);flex-shrink:0;width:74px;font-size:11px;font-weight:700;color:#9aa7bd;letter-spacing:.3px;}
    .cell-time{flex-direction:column;align-items:flex-start;}
    .cell-time::before{display:none;}
    .cell-time .time-main{font-size:14px;}
    .pagination{justify-content:center;}
    .pagination .meta{width:100%;text-align:center;order:2;}
}
</style>
</head>
<body>
<div class="content-wrapper">
    <div class="top-nav">
        <a href="main.php" class="back"><i class="mdi mdi-arrow-left"></i> 返回用户中心</a>
        <div class="crumb">用户中心 / <b>登录日志</b></div>
    </div>
    <div class="page-header">
        <div class="title-wrap">
            <div class="title-icon"><i class="mdi mdi-shield-account-outline"></i></div>
            <div>
                <h1>我的登录日志</h1>
                <div class="subtitle">记录本账号的每次登录，发现陌生设备或地点请及时修改密码</div>
            </div>
        </div>
        <a href="login_logs.php?action=clear&keep=90" class="btn btn-warn" onclick="return confirm('确认清理 90 天前的登录日志？')"><i class="mdi mdi-broom"></i>清理 90 天前</a>
    </div>

    <?php
    $s_ok_rate = $s_total7 > 0 ? round($s_ok7 / $s_total7 * 100) : 0;
    $s_fail_rate = $s_total7 > 0 ? round($s_fail7 / $s_total7 * 100) : 0;
    ?>
    <div class="stat-grid">
        <div class="stat-card c-blue panel">
            <div class="stat-top"><span class="label">近 7 天登录</span><span class="tag">TOTAL</span></div>
            <div class="value"><?php echo $s_total7; ?><small>次</small></div>
            <div class="bar"><span data-w="100%"></span></div>
        </div>
        <div class="stat-card c-green panel">
            <div class="stat-top"><span class="label">登录成功</span><span class="tag"><?php echo $s_ok_rate; ?>%</span></div>
            <div class="value"><?php echo $s_ok7; ?><small>次</small></div>
            <div class="bar"><span data-w="<?php echo $s_ok_rate; ?>%"></span></div>
        </div>
        <div class="stat-card c-red panel">
            <div class="stat-top"><span class="label">登录失败</span><span class="tag"><?php echo $s_fail_rate; ?>%</span></div>
            <div class="value"><?php echo $s_fail7; ?><small>次</small></div>
            <div class="bar"><span data-w="<?php echo $s_fail_rate; ?>%"></span></div>
        </div>
        <div class="stat-card c-purple panel">
            <div class="stat-top"><span class="label">独立设备</span><span class="tag">7 天</span></div>
            <div class="value"><?php echo $s_ua7; ?><small>台</small></div>
            <div class="bar"><span data-w="<?php echo $s_ua7 > 0 ? min(100, $s_ua7 * 20) : 0; ?>%"></span></div>
        </div>
    </div>

    <form class="filter-bar panel" method="GET">
        <select name="status">
            <option value="">全部状态</option>
            <option value="success" <?php echo $status==='success'?'selected':''; ?>>登录成功</option>
            <option value="failed" <?php echo $status==='failed'?'selected':''; ?>>登录失败</option>
            <option value="locked" <?php echo $status==='locked'?'selected':''; ?>>账号锁定</option>
        </select>
        <div class="field">
            <i class="mdi mdi-magnify"></i>
            <input type="text" name="kw" placeholder="搜索 IP / 国家 / 地区 / 城市" value="<?php echo htmlspecialchars($keyword); ?>">
        </div>
        <button type="submit" class="btn btn-primary"><i class="mdi mdi-filter-outline"></i>筛选</button>
        <a href="login_logs.php" class="btn btn-ghost"><i class="mdi mdi-refresh"></i>重置</a>
        <div class="spacer"></div>
        <div class="meta" style="font-size:12.5px;color:var(--h-muted);">共 <?php echo $total; ?> 条记录</div>
    </form>

    <div class="log-card panel">
        <div class="log-table-wrap">
            <table class="log-table">
                <thead>
                    <tr>
                        <th style="width:140px;">登录时间</th>
                        <th style="width:100px;">状态</th>
                        <th style="width:150px;">IP 地址</th>
                        <th style="width:180px;">地理位置</th>
                        <th style="width:170px;">网络 / 运营商</th>
                        <th>设备</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="6"><div class="empty-state"><div class="empty-icon"><i class="mdi mdi-clipboard-text-outline"></i></div><div class="empty-text">暂无登录记录</div></div></td></tr>
                <?php else: foreach ($logs as $r):
                    $ua_key = $r['user_agent'];
                    if (!isset($ua_cache[$ua_key])) { $ua_cache[$ua_key] = huli_parse_ua($ua_key); }
                    $u = $ua_cache[$ua_key];
                    $flag = huli_emoji_for_country($r['country'], $country_emoji);
                    $ago = huli_format_login_time($r['login_at']);
                    $is_recent = (time() - strtotime($r['login_at'])) < 86400;
                    $row_class = 'is-' . (isset($status_label[$r['status']]) ? $r['status'] : 'success');
                ?>
                    <tr class="<?php echo htmlspecialchars($row_class); ?>">
                        <td class="cell-time <?php echo $is_recent ? 'ago' : ''; ?>" data-label="登录时间">
                            <div class="time-main"><?php echo $ago; ?></div>
                            <div class="time-sub"><?php echo htmlspecialchars($r['login_at']); ?></div>
                        </td>
                        <td data-label="状态"><span class="chip chip-<?php echo htmlspecialchars($r['status']); ?>"><?php echo $status_label[$r['status']] ?? $r['status']; ?></span></td>
                        <td class="cell-ip" data-label="IP 地址"><span class="ip-main"><?php echo htmlspecialchars($r['ip']); ?></span></td>
                        <td class="cell-geo" data-label="地理位置">
                            <?php if (!empty($r['country'])): ?>
                                <div class="country-line"><span class="flag"><?php echo $flag; ?></span><?php echo htmlspecialchars($r['country']); ?></div>
                                <?php if (!empty($r['region']) || !empty($r['city'])): ?>
                                    <div class="region"><?php echo htmlspecialchars(trim($r['region'] . ' ' . $r['city'])); ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="empty">未知</span>
                            <?php endif; ?>
                        </td>
                        <td class="cell-isp" data-label="运营商"><?php echo !empty($r['isp']) ? htmlspecialchars($r['isp']) : '<span class="empty">未知</span>'; ?></td>
                        <td class="cell-ua" data-label="设备">
                            <i class="mdi <?php echo $u['os_icon']; ?> ua-icon"></i>
                            <div class="ua-info">
                                <div class="ua-os"><?php echo htmlspecialchars($u['os']); ?></div>
                                <div class="ua-browser"><i class="mdi <?php echo $u['browser_icon']; ?>"></i><?php echo htmlspecialchars($u['browser']); ?></div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($total > 0): ?>
        <div class="pagination">
            <div class="meta">第 <?php echo $page; ?> / <?php echo $total_pages; ?> 页，共 <?php echo $total; ?> 条</div>
            <div class="pages">
            <?php
            $base_qs = http_build_query(array_filter(['status'=>$status,'kw'=>$keyword]));
            $qs = $base_qs !== '' ? '&' . $base_qs : '';
            if ($page > 1) echo '<a href="?page=' . max(1, $page - 1) . $qs . '"><i class="mdi mdi-chevron-left"></i></a>';
            $start_p = max(1, $page - 2); $end_p = min($total_pages, $page + 2);
            if ($start_p > 1) { echo '<a href="?page=1' . $qs . '">1</a>'; if ($start_p > 2) echo '<span style="border:none;background:transparent;color:#a3b0c5;">…</span>'; }
            for ($i = $start_p; $i <= $end_p; $i++) {
                if ($i === $page) echo '<span class="current">' . $i . '</span>';
                else echo '<a href="?page=' . $i . $qs . '">' . $i . '</a>';
            }
            if ($end_p < $total_pages) { if ($end_p < $total_pages - 1) echo '<span style="border:none;background:transparent;color:#a3b0c5;">…</span>'; echo '<a href="?page=' . $total_pages . $qs . '">' . $total_pages . '</a>'; }
            if ($page < $total_pages) echo '<a href="?page=' . min($total_pages, $page + 1) . $qs . '"><i class="mdi mdi-chevron-right"></i></a>';
            ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<script>
document.querySelectorAll('.stat-card .bar span').forEach(function(el){
    var w = el.getAttribute('data-w') || '0%';
    requestAnimationFrame(function(){ setTimeout(function(){ el.style.width = w; }, 60); });
});
</script>
</body>
</html>
