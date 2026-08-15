<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
if (file_exists('../config.php')) { require_once '../config.php'; } else { die("出现错误！配置文件丢失。"); }
$username = htmlspecialchars($_SESSION['admin_username']);
$page_title = '登录日志';
$current_page = basename($_SERVER['PHP_SELF']);
$feedback_msg = '';
$feedback_type = '';
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
        if ($_GET['action'] === 'clear' && isset($_GET['range'])) {
            $range = $_GET['range'];
            $where = '';
            if ($range === '30d')  { $where = "WHERE login_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"; }
            elseif ($range === '90d')  { $where = "WHERE login_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"; }
            elseif ($range === 'all')  { $where = ''; }
            $del = $pdo->exec("DELETE FROM huli_login_logs {$where}");
            $_SESSION['feedback_msg'] = '已清理 ' . (int)$del . ' 条登录日志。';
            $_SESSION['feedback_type'] = 'success';
        }
        header('Location: login_logs.php');
        exit;
    }
    if (isset($_SESSION['feedback_msg'])) {
        $feedback_msg = $_SESSION['feedback_msg'];
        $feedback_type = $_SESSION['feedback_type'];
        unset($_SESSION['feedback_msg'], $_SESSION['feedback_type']);
    }
    $status = $_GET['status'] ?? '';
    $keyword = trim($_GET['keyword'] ?? '');
    $country_filter = trim($_GET['country'] ?? '');
    $where = [];
    $params = [];
    if ($status === 'success' || $status === 'failed') { $where[] = 'status = ?'; $params[] = $status; }
    if ($keyword !== '') {
        $where[] = '(actor_name LIKE ? OR ip LIKE ? OR city LIKE ? OR region LIKE ?)';
        $kw = '%' . $keyword . '%';
        array_push($params, $kw, $kw, $kw, $kw);
    }
    if ($country_filter !== '') { $where[] = 'country = ?'; $params[] = $country_filter; }
    $where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = 20;
    $offset = ($page - 1) * $limit;
    $stmt_total = $pdo->prepare("SELECT COUNT(*) FROM huli_login_logs {$where_sql}");
    $stmt_total->execute($params);
    $total = (int)$stmt_total->fetchColumn();
    $totalPages = max(1, ceil($total / $limit));
    $stmt = $pdo->prepare("SELECT id, actor_name, status, ip, country, region, city, isp, user_agent, login_at FROM huli_login_logs {$where_sql} ORDER BY login_at DESC, id DESC LIMIT {$limit} OFFSET {$offset}");
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
    $stats = $pdo->query("SELECT
        COUNT(*) AS total,
        SUM(status='success') AS ok,
        SUM(status='failed') AS fail,
        COUNT(DISTINCT user_agent) AS uniq_ua
        FROM huli_login_logs WHERE login_at > DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch();
    $country_list = $pdo->query("SELECT country, COUNT(*) AS c FROM huli_login_logs WHERE country IS NOT NULL AND country <> '' GROUP BY country ORDER BY c DESC LIMIT 30")->fetchAll();
} catch (Exception $e) {
    $feedback_msg = '加载失败: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    $feedback_type = 'error';
    $logs = []; $stats = ['total'=>0,'ok'=>0,'fail'=>0,'uniq_ua'=>0]; $total=0; $totalPages=1; $page=1; $country_list=[];
}

$status_label = ['success'=>'成功','failed'=>'失败','locked'=>'锁定'];
$status_color = ['success'=>'#10b981','failed'=>'#ef4444','locked'=>'#f59e0b'];

$country_emoji = [
    '中国'=>'🇨🇳','China'=>'🇨🇳','CN'=>'🇨🇳','香港'=>'🇭🇰','Hong Kong'=>'🇭🇰','HK'=>'🇭🇰','澳门'=>'🇲🇴','MO'=>'🇲🇴',
    '台湾'=>'🇹🇼','TW'=>'🇹🇼','美国'=>'🇺🇸','United States'=>'🇺🇸','US'=>'🇺🇸','日本'=>'🇯🇵','Japan'=>'🇯🇵','JP'=>'🇯🇵',
    '韩国'=>'🇰🇷','KR'=>'🇰🇷','英国'=>'🇬🇧','GB'=>'🇬🇧','德国'=>'🇩🇪','DE'=>'🇩🇪','法国'=>'🇫🇷','FR'=>'🇫🇷',
    '俄罗斯'=>'🇷🇺','RU'=>'🇷🇺','加拿大'=>'🇨🇦','CA'=>'🇨🇦','澳大利亚'=>'🇦🇺','AU'=>'🇦🇺','新加坡'=>'🇸🇬','SG'=>'🇸🇬',
    '马来西亚'=>'🇲🇾','MY'=>'🇲🇾','泰国'=>'🇹🇭','TH'=>'🇹🇭','越南'=>'🇻🇳','VN'=>'🇻🇳','印度'=>'🇮🇳','IN'=>'🇮🇳',
    '巴西'=>'🇧🇷','BR'=>'🇧🇷','意大利'=>'🇮🇹','IT'=>'🇮🇹','西班牙'=>'🇪🇸','ES'=>'🇪🇸','荷兰'=>'🇳🇱','NL'=>'🇳🇱',
    '瑞典'=>'🇸🇪','SE'=>'🇸🇪','瑞士'=>'🇨🇭','CH'=>'🇨🇭','波兰'=>'🇵🇱','PL'=>'🇵🇱','土耳其'=>'🇹🇷','TR'=>'🇹🇷',
    '乌克兰'=>'🇺🇦','UA'=>'🇺🇦','南非'=>'🇿🇦','ZA'=>'🇿🇦','阿根廷'=>'🇦🇷','AR'=>'🇦🇷','墨西哥'=>'🇲🇽','MX'=>'🇲🇽',
    '印度尼西亚'=>'🇮🇩','ID'=>'🇮🇩','菲律宾'=>'🇵🇭','PH'=>'🇵🇭',
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
    if (!$t) return ['rel' => htmlspecialchars($ts), 'abs' => ''];
    $diff = time() - $t;
    if ($diff < 60) $rel = '刚刚';
    elseif ($diff < 3600) $rel = (int)floor($diff/60) . ' 分钟前';
    elseif ($diff < 86400) $rel = (int)floor($diff/3600) . ' 小时前';
    elseif ($diff < 86400 * 7) $rel = (int)floor($diff/86400) . ' 天前';
    else $rel = date('m-d H:i', $t);
    return ['rel' => $rel, 'abs' => $ts];
}
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
<title>登录日志 - huliapi 后台</title>
<link rel="stylesheet" type="text/css" href="../assets/css/materialdesignicons.min.css">
<link rel="stylesheet" type="text/css" href="../assets/css/bootstrap.min.css">
<link rel="stylesheet" type="text/css" href="../assets/css/style.min.css">
<style>
.login-logs-page .stat-card {
    border: none;
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
    transition: transform 0.15s, box-shadow 0.15s;
}
.login-logs-page .stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 22px rgba(15, 23, 42, 0.1);
}
.login-logs-page .stat-icon {
    width: 48px; height: 48px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 12px;
    font-size: 24px;
}
.login-logs-page .stat-icon.primary { background: rgba(99, 102, 241, 0.12); color: #6366f1; }
.login-logs-page .stat-icon.success { background: rgba(16, 185, 129, 0.12); color: #10b981; }
.login-logs-page .stat-icon.danger  { background: rgba(239, 68, 68, 0.12); color: #ef4444; }
.login-logs-page .stat-icon.warning { background: rgba(245, 158, 11, 0.12); color: #f59e0b; }
.login-logs-page table thead th {
    background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
    color: #475569;
    font-weight: 600;
    font-size: 13px;
    border-bottom: 1px solid rgba(148, 163, 184, 0.3);
}
.login-logs-page .table > :not(caption) > * > * {
    padding: 12px 14px;
}
.login-logs-page .device-cell {
    display: flex;
    align-items: center;
    gap: 10px;
}
.login-logs-page .device-icon {
    width: 36px; height: 36px;
    display: flex; align-items: center; justify-content: center;
    background: rgba(99, 102, 241, 0.08);
    color: #6366f1;
    border-radius: 10px;
    font-size: 18px;
    flex-shrink: 0;
}
.login-logs-page .device-text {
    line-height: 1.25;
}
.login-logs-page .device-text .browser {
    font-weight: 500;
    color: #0f172a;
}
.login-logs-page .device-text .os {
    font-size: 12px;
    color: #64748b;
}
.login-logs-page .geo-cell {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.login-logs-page .geo-cell .country-line {
    font-weight: 500;
    color: #0f172a;
}
.login-logs-page .geo-cell .region-line {
    font-size: 12px;
    color: #64748b;
}
.login-logs-page .geo-flag {
    font-size: 18px;
    margin-right: 4px;
}
.login-logs-page .time-cell .rel {
    font-weight: 500;
    color: #0f172a;
}
.login-logs-page .time-cell .abs {
    font-size: 11px;
    color: #94a3b8;
    display: block;
}
.login-logs-page .status-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}
.login-logs-page .status-pill.success { background: rgba(16, 185, 129, 0.1); color: #047857; }
.login-logs-page .status-pill.failed  { background: rgba(239, 68, 68, 0.1); color: #b91c1c; }
.login-logs-page .ip-code {
    background: rgba(99, 102, 241, 0.08);
    color: #4f46e5;
    padding: 2px 8px;
    border-radius: 6px;
    font-size: 12px;
    font-family: 'SFMono-Regular', Consolas, monospace;
}
.login-logs-page .filter-bar {
    background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
    border: 1px solid rgba(148, 163, 184, 0.2);
    border-radius: 12px;
    padding: 14px 16px;
}
@media (max-width: 900px) {
    .login-logs-page .stat-cards { gap: 8px; }
    .login-logs-page .table th, .login-logs-page .table td { font-size: 12px; padding: 8px; }
}
</style>
</head>
<body>
<div class="container-fluid login-logs-page">
  <div class="row">
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-1"><i class="mdi mdi-history text-primary"></i> 登录日志</h2>
                <div class="text-muted small">仅统计后台管理员的登录记录</div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="?action=clear&range=30d" class="btn btn-outline-warning btn-sm" onclick="return confirm('确定清理 30 天前的登录日志吗？');"><i class="mdi mdi-broom"></i> 清理 30 天前</a>
                <a href="?action=clear&range=90d" class="btn btn-outline-warning btn-sm" onclick="return confirm('确定清理 90 天前的登录日志吗？');"><i class="mdi mdi-broom"></i> 清理 90 天前</a>
                <a href="?action=clear&range=all" class="btn btn-outline-danger btn-sm" onclick="return confirm('确定清空全部登录日志吗？此操作不可恢复！');"><i class="mdi mdi-delete-sweep"></i> 清空全部</a>
            </div>
        </div>
        <?php if ($feedback_msg): ?>
            <div class="alert alert-<?php echo $feedback_type === 'success' ? 'success' : 'danger'; ?>"><?php echo $feedback_msg; ?></div>
        <?php endif; ?>
        <div class="row g-3 mb-3 stat-cards">
            <?php
            $stat_cards = [
                ['label' => '近 7 天登录', 'value' => (int)($stats['total'] ?? 0), 'icon' => 'mdi-login-variant', 'cls' => 'primary'],
                ['label' => '登录成功', 'value' => (int)($stats['ok'] ?? 0), 'icon' => 'mdi-check-circle-outline', 'cls' => 'success'],
                ['label' => '登录失败', 'value' => (int)($stats['fail'] ?? 0), 'icon' => 'mdi-close-circle-outline', 'cls' => 'danger'],
                ['label' => '独立设备（7 天）', 'value' => (int)($stats['uniq_ua'] ?? 0), 'icon' => 'mdi-devices', 'cls' => 'warning'],
            ];
            foreach ($stat_cards as $c): ?>
            <div class="col-md">
                <div class="card stat-card">
                    <div class="card-body d-flex align-items-center">
                        <div class="stat-icon <?php echo $c['cls']; ?>"><i class="mdi <?php echo $c['icon']; ?>"></i></div>
                        <div class="ms-3">
                            <div class="text-muted small"><?php echo $c['label']; ?></div>
                            <div class="fs-4 fw-bold"><?php echo $c['value']; ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="card shadow-sm">
            <div class="card-body">
                <form class="row g-2 mb-3 filter-bar" method="get">
                    <div class="col-md-2">
                        <select name="status" class="form-select">
                            <option value="">全部状态</option>
                            <option value="success" <?php echo $status === 'success' ? 'selected' : ''; ?>>成功</option>
                            <option value="failed" <?php echo $status === 'failed' ? 'selected' : ''; ?>>失败</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="country" class="form-select">
                            <option value="">全部国家/地区</option>
                            <?php foreach ($country_list as $co): ?>
                                <option value="<?php echo htmlspecialchars($co['country']); ?>" <?php echo $country_filter === $co['country'] ? 'selected' : ''; ?>><?php echo huli_emoji_for_country($co['country'], $country_emoji) . ' ' . htmlspecialchars($co['country']) . ' (' . (int)$co['c'] . ')' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <input type="text" name="keyword" class="form-control" placeholder="按账号 / IP / 城市搜索" value="<?php echo htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary w-100" type="submit"><i class="mdi mdi-magnify"></i> 搜索</button>
                    </div>
                    <div class="col-md-1">
                        <a class="btn btn-outline-secondary w-100" href="login_logs.php"><i class="mdi mdi-refresh"></i></a>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>时间</th>
                                <th>账号</th>
                                <th>状态</th>
                                <th>IP 地址</th>
                                <th>地理位置</th>
                                <th>网络</th>
                                <th>设备 / 浏览器</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr><td colspan="7" class="text-center text-muted py-4">暂无登录日志</td></tr>
                            <?php else: foreach ($logs as $l):
                                $ua_info = huli_parse_ua($l['user_agent']);
                                $geo_country = trim((string)($l['country'] ?? ''));
                                $geo_region = trim((string)($l['region'] ?? ''));
                                $geo_city = trim((string)($l['city'] ?? ''));
                                $flag = huli_emoji_for_country($geo_country, $country_emoji);
                                $time_info = huli_format_login_time($l['login_at']);
                            ?>
                                <tr>
                                    <td class="time-cell">
                                        <span class="rel"><?php echo htmlspecialchars($time_info['rel']); ?></span>
                                        <span class="abs"><?php echo htmlspecialchars($time_info['abs']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($l['actor_name'] ?: '-'); ?></td>
                                    <td>
                                        <?php if ($l['status'] === 'success'): ?>
                                            <span class="status-pill success"><i class="mdi mdi-check"></i> 成功</span>
                                        <?php else: ?>
                                            <span class="status-pill failed"><i class="mdi mdi-close"></i> 失败</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="ip-code"><?php echo htmlspecialchars($l['ip']); ?></span></td>
                                    <td>
                                        <div class="geo-cell">
                                            <span class="country-line"><span class="geo-flag"><?php echo $flag; ?></span><?php echo htmlspecialchars($geo_country ?: '未知'); ?></span>
                                            <?php if ($geo_region !== '' || $geo_city !== ''): ?>
                                                <span class="region-line"><?php echo htmlspecialchars(trim($geo_region . ' ' . $geo_city)); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><small class="text-muted"><?php echo htmlspecialchars($l['isp'] ?? ''); ?></small></td>
                                    <td>
                                        <div class="device-cell">
                                            <div class="device-icon"><i class="mdi <?php echo $ua_info['browser_icon']; ?>"></i></div>
                                            <div class="device-text">
                                                <div class="browser"><?php echo htmlspecialchars($ua_info['browser']); ?></div>
                                                <div class="os"><i class="mdi <?php echo $ua_info['os_icon']; ?>"></i> <?php echo htmlspecialchars($ua_info['os']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($total > 0):
                    $query_base = http_build_query(array_filter(['status' => $status, 'keyword' => $keyword, 'country' => $country_filter]));
                    $query_sep = $query_base ? '&' : '';
                ?>
                <nav aria-label="分页">
                    <ul class="pagination justify-content-end mt-3 mb-0">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>"><a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $query_sep . $query_base; ?>">&laquo;</a></li>
                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>"><a class="page-link" href="?page=<?php echo $i; ?><?php echo $query_sep . $query_base; ?>"><?php echo $i; ?></a></li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>"><a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $query_sep . $query_base; ?>">&raquo;</a></li>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
  </div>
</div>
<script type="text/javascript" src="../assets/js/jquery.min.js"></script>
<script type="text/javascript" src="../assets/js/popper.min.js"></script>
<script type="text/javascript" src="../assets/js/bootstrap.min.js"></script>
</body>
</html>
