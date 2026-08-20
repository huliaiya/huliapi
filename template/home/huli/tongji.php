<?php
@error_reporting(0);
@ini_set('display_errors', 'Off');
date_default_timezone_set('Asia/Shanghai');
header('Content-Type: text/html; charset=utf-8');
require_once '../../../config.php';
$pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET, DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
$today = date("Y-m-d");
$yesterday = date("Y-m-d", strtotime("-1 day"));
$page_today = isset($_GET['page_today']) ? max(1, intval($_GET['page_today'])) : 1;
$page_total = isset($_GET['page_total']) ? max(1, intval($_GET['page_total'])) : 1;
$limit = 20;
$offset_today = ($page_today - 1) * $limit;
$offset_total = ($page_total - 1) * $limit;
$today_start = $today . ' 00:00:00';
$today_end = date('Y-m-d', strtotime('+1 day', strtotime($today))) . ' 00:00:00';
$yesterday_start = $yesterday . ' 00:00:00';
$yesterday_end = $today_start;

if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM huli_api_logs WHERE request_time >= ? AND request_time < ?");
    $stmt->execute([$today_start, $today_end]);
    $tt = (int)$stmt->fetchColumn();
    $stmt->execute([$yesterday_start, $yesterday_end]);
    $ty = (int)$stmt->fetchColumn();
    if ($ty == 0) { $p = 100; $pc = 'up'; }
    else {
        $raw = round(($tt - $ty) / $ty * 100, 1);
        $p = max(-100, min(100, $raw));
        $pc = $p >= 0 ? 'up' : 'down';
    }
    $ptext = $p >= 0 ? ('+' . $p . '%') : ($p . '%');
    $sql_today_ajax = "SELECT t.api_id, a.name, t.cnt AS today_calls
              FROM (SELECT api_id, COUNT(*) AS cnt
                    FROM huli_api_logs
                    WHERE request_time >= ? AND request_time < ?
                    GROUP BY api_id
                    HAVING cnt >= 1) t
              JOIN huli_apis a ON a.id = t.api_id
              ORDER BY t.cnt DESC LIMIT ?";
    $stmt = $pdo->prepare($sql_today_ajax);
    $stmt->bindValue(1, $today_start); $stmt->bindValue(2, $today_end); $stmt->bindValue(3, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $today_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $y_map_ajax = [];
    $stmt = $pdo->prepare("SELECT api_id, COUNT(*) AS cnt FROM huli_api_logs WHERE request_time >= ? AND request_time < ? GROUP BY api_id");
    $stmt->execute([$yesterday_start, $yesterday_end]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) { $y_map_ajax[$r['api_id']] = (int)$r['cnt']; }
    $tbody_today = '';
    if (empty($today_rows)) {
        $tbody_today = '<tr><td colspan="4" class="empty">今日暂无API调用记录</td></tr>';
    } else {
        foreach ($today_rows as $i => $row) {
            $t = (int)$row['today_calls'];
            $y = $y_map_ajax[$row['api_id']] ?? 0;
            if ($y == 0) { $cp = 100; $cls = 'up'; }
            else {
                $raw = round(($t - $y) / $y * 100, 1);
                $cp = max(-100, min(100, $raw));
                $cls = $cp >= 0 ? 'up' : 'down';
            }
            $show = $cp >= 0 ? ('+' . $cp . '%') : ($cp . '%');
            $no = $i + 1;
            $tbody_today .= '<tr><td class="rank">' . $no . ($no <= 3 ? '<span class="hot">🔥</span>' : '') . '</td><td class="name">' . htmlspecialchars($row['name']) . '</td><td class="calls">' . number_format($t) . '</td><td class="rate ' . $cls . '">' . $show . '</td></tr>';
        }
    }
    $sql_total_ajax = "SELECT id, name, total_calls FROM huli_apis WHERE total_calls >= 1 ORDER BY total_calls DESC LIMIT ?";
    $stmt = $pdo->prepare($sql_total_ajax);
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $total_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $tbody_total = '';
    if (empty($total_rows)) {
        $tbody_total = '<tr><td colspan="3" class="empty">暂无API总调用记录</td></tr>';
    } else {
        foreach ($total_rows as $i => $row) {
            $no = $i + 1;
            $tbody_total .= '<tr><td class="rank">' . $no . ($no <= 3 ? '<span class="hot">🔥</span>' : '') . '</td><td class="name">' . htmlspecialchars($row['name']) . '</td><td class="calls">' . number_format($row['total_calls']) . '</td></tr>';
        }
    }
    echo json_encode([
        'total_today' => $tt,
        'total_yday' => $ty,
        'percent' => $p,
        'percent_text' => $ptext,
        'percent_class' => $pc,
        'today_rows' => $tbody_today,
        'total_rows' => $tbody_total,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM huli_api_logs WHERE request_time >= ? AND request_time < ?");
$stmt->execute([$today . ' 00:00:00', date('Y-m-d', strtotime('+1 day', strtotime($today))) . ' 00:00:00']);
$total_today = $stmt->fetchColumn() ?: 0;
$stmt->execute([$yesterday . ' 00:00:00', $today . ' 00:00:00']);
$total_yday = $stmt->fetchColumn() ?: 0;
if ($total_yday == 0) {
    $percent = 100;
    $percent_class = 'up';
} else {
    $raw_percent = round(($total_today - $total_yday) / $total_yday * 100, 1);
    if ($raw_percent > 100) {
        $percent = 100;
    } elseif ($raw_percent < -100) {
        $percent = -100;
    } else {
        $percent = $raw_percent;
    }
    $percent_class = $percent >= 0 ? 'up' : 'down';
}
$y_map = [];
$today_start = $today . ' 00:00:00';
$today_end = date('Y-m-d', strtotime('+1 day', strtotime($today))) . ' 00:00:00';
$yesterday_start = $yesterday . ' 00:00:00';
$yesterday_end = date('Y-m-d', strtotime('+1 day', strtotime($yesterday))) . ' 00:00:00';
$stmt = $pdo->prepare("SELECT api_id, COUNT(*) AS cnt FROM huli_api_logs WHERE request_time >= ? AND request_time < ? GROUP BY api_id");
$stmt->execute([$yesterday_start, $yesterday_end]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    $y_map[$r['api_id']] = $r['cnt'];
}
$sql_today = "SELECT t.api_id, a.name, t.cnt AS today_calls
              FROM (SELECT api_id, COUNT(*) AS cnt
                    FROM huli_api_logs
                    WHERE request_time >= ? AND request_time < ?
                    GROUP BY api_id
                    HAVING cnt >= 1) t
              JOIN huli_apis a ON a.id = t.api_id
              ORDER BY t.cnt DESC
              LIMIT ?, ?";
$stmt = $pdo->prepare($sql_today);
$stmt->bindValue(1, $today_start);
$stmt->bindValue(2, $today_end);
$stmt->bindValue(3, $offset_today, PDO::PARAM_INT);
$stmt->bindValue(4, $limit, PDO::PARAM_INT);
$stmt->execute();
$today_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
$sql_count_today = "SELECT COUNT(*) FROM (SELECT api_id FROM huli_api_logs WHERE request_time >= ? AND request_time < ? GROUP BY api_id HAVING COUNT(*) >= 1) AS sub";
$stmt = $pdo->prepare($sql_count_today);
$stmt->execute([$today_start, $today_end]);
$count_today = $stmt->fetchColumn();
$total_pages_today = ceil($count_today / $limit);
$sql_total = "SELECT id, name, total_calls FROM huli_apis WHERE total_calls >= 1 ORDER BY total_calls DESC LIMIT ?, ?";
$stmt = $pdo->prepare($sql_total);
$stmt->bindValue(1, $offset_total, PDO::PARAM_INT);
$stmt->bindValue(2, $limit, PDO::PARAM_INT);
$stmt->execute();
$total_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
$count_total = $pdo->query("SELECT COUNT(*) FROM huli_apis WHERE total_calls >= 1")->fetchColumn();
$total_pages_total = ceil($count_total / $limit);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,user-scalable=no">
<title>API调用排行榜 - huliapi</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:system-ui,-apple-system,Segoe UI,Roboto}
body{background:#f0f2f5;padding:15px;color:#333;font-size:14px}
.container{max-width:800px;margin:0 auto}
.card{background:#fff;border-radius:10px;padding:15px;margin-bottom:12px;box-shadow:0 1px 2px rgba(0,0,0,0.06)}
.title{text-align:center;font-size:16px;font-weight:600;margin-bottom:12px}
.subtitle{font-size:14px;font-weight:500;margin-bottom:8px}
.sum-box{display:flex;gap:10px;margin-bottom:10px}
.sum-item{flex:1;background:#f8f9fa;padding:10px;border-radius:8px;text-align:center}
.sum-label{font-size:12px;color:#6c757d}
.sum-num{font-size:16px;font-weight:600;color:#007bff}
.sum-percent{font-size:14px;font-weight:bold;margin-top:4px}
.table{width:100%;border-collapse:collapse;font-size:12px}
.table th{padding:8px 6px;text-align:center;background:#f8f9fa;color:#495057;font-weight:500;border-bottom:1px solid #dee2e6}
.table td{padding:8px 6px;text-align:center;border-bottom:1px solid #eee}
.table tr:hover{background:#f8f9fa}
.rank{width:50px;font-weight:600}
.name{text-align:left}
.calls{width:90px;color:#007bff;font-weight:500}
.rate{width:80px;font-weight:bold}
.empty{text-align:center;padding:12px;color:#6c757d;font-size:12px}
.nav-box{text-align:center;margin-bottom:10px}
.nav-btn{display:inline-block;padding:6px 12px;margin:0 4px;border-radius:6px;background:#fff;border:1px solid #dee2e6;cursor:pointer;font-size:12px}
.nav-btn:hover{background:#f8f9fa}
.pagination{text-align:center;margin-top:10px}
.pagination a{display:inline-block;padding:6px 10px;margin:0 3px;border-radius:6px;background:#fff;border:1px solid #dee2e6;color:#333;text-decoration:none;font-size:12px}
.pagination a.active{background:#007bff;color:#fff;border-color:#007bff}
.refresh-bar{text-align:center;margin-top:8px;font-size:11px;color:#6c757d}
.hot{color:#ff7700;font-size:14px;margin-left:4px}
.up{color:#28a745}
.down{color:#dc3545}
</style>
</head>
<body>
<div class="container">
<div class="card">
<h1 class="title">📊 API调用排行榜</h1>
<div class="nav-box">
<button class="nav-btn" onclick="document.getElementById('today').scrollIntoView({behavior:'smooth'})">📈 今日调用</button>
<button class="nav-btn" onclick="document.getElementById('total').scrollIntoView({behavior:'smooth'})">📊 总调用</button>
</div>
<div class="sum-box">
<div class="sum-item">
<div class="sum-label">今日总调用</div>
<div class="sum-num" id="today-total"><?=number_format($total_today)?></div>
</div>
<div class="sum-item">
<div class="sum-label">昨日总调用</div>
<div class="sum-num" id="yesterday-total"><?=number_format($total_yday)?></div>
</div>
<div class="sum-item">
<div class="sum-label">环比昨日</div>
<div class="sum-percent <?=$percent_class?>" id="percent"><?php echo $percent >= 0 ? "+".$percent : $percent; ?>%</div>
</div>
</div>
</div>
<div class="card" id="today">
<h2 class="subtitle">📈 今日调用排行</h2>
<table class="table">
<thead>
<tr><th class="rank">排名</th><th class="name">API名称</th><th class="calls">今日调用</th><th class="rate">较比昨日</th></tr>
</thead>
<tbody id="today-tbody">
<?php if(empty($today_list)): ?>
<tr><td colspan="4" class="empty">今日暂无API调用记录</td></tr>
<?php endif; ?>
<?php foreach($today_list as $i=>$row): ?>
<?php
$no = ($page_today-1)*$limit+$i+1;
$t = $row['today_calls'];
$y = $y_map[$row['api_id']] ?? 0;
if($y == 0){
    $p = 100;
    $cls = 'up';
}else{
    $raw = round(($t - $y)/$y*100,1);
    if ($raw > 100) {
        $p = 100;
    } elseif ($raw < -100) {
        $p = -100;
    } else {
        $p = $raw;
    }
    $cls = $p >= 0 ? 'up' : 'down';
}
$show = $p >=0 ? "+".$p."%" : $p."%";
?>
<tr>
<td class="rank"><?=$no?><?=$no<=3?'<span class="hot">🔥</span>':''?></td>
<td class="name"><?=htmlspecialchars($row['name'])?></td>
<td class="calls"><?=number_format($t)?></td>
<td class="rate <?=$cls?>"><?=$show?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<div class="pagination">
<?php if($page_today>1): ?>
<a href="?page_today=<?=$page_today-1?>&page_total=<?=$page_total?>">上一页</a>
<?php endif; ?>
<?php for($p=1;$p<=$total_pages_today;$p++): ?>
<a href="?page_today=<?=$p?>&page_total=<?=$page_total?>" class="<?=$p==$page_today?'active':''?>"><?=$p?></a>
<?php endfor; ?>
<?php if($page_today<$total_pages_today): ?>
<a href="?page_today=<?=$page_today+1?>&page_total=<?=$page_total?>">下一页</a>
<?php endif; ?>
</div>
</div>
<div class="card" id="total">
<h2 class="subtitle">📊 总调用排行</h2>
<table class="table">
<thead>
<tr><th class="rank">排名</th><th class="name">API名称</th><th class="calls">总调用</th></tr>
</thead>
<tbody id="total-tbody">
<?php if(empty($total_list)): ?>
<tr><td colspan="3" class="empty">暂无API总调用记录</td></tr>
<?php endif; ?>
<?php foreach($total_list as $i=>$row): ?>
<?php $no = ($page_total-1)*$limit+$i+1; ?>
<tr>
<td class="rank"><?=$no?><?=$no<=3?'<span class="hot">🔥</span>':''?></td>
<td class="name"><?=htmlspecialchars($row['name'])?></td>
<td class="calls"><?=number_format($row['total_calls'])?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<div class="pagination">
<?php if($page_total>1): ?>
<a href="?page_today=<?=$page_today?>&page_total=<?=$page_total-1?>">上一页</a>
<?php endif; ?>
<?php for($p=1;$p<=$total_pages_total;$p++): ?>
<a href="?page_today=<?=$page_today?>&page_total=<?=$p?>" class="<?=$p==$page_total?'active':''?>"><?=$p?></a>
<?php endfor; ?>
<?php if($page_total<$total_pages_total): ?>
<a href="?page_today=<?=$page_today?>&page_total=<?=$page_total+1?>">下一页</a>
<?php endif; ?>
</div>
</div>
<div class="refresh-bar">⏱️ 数据每10秒自动刷新</div>
</div>
<script>
async function refreshRank() {
    try {
        const resp = await fetch('?ajax=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const data = await resp.json();
        document.getElementById('today-total').textContent = data.total_today.toLocaleString();
        document.getElementById('yesterday-total').textContent = data.total_yday.toLocaleString();
        document.getElementById('percent').textContent = data.percent_text;
        document.getElementById('percent').className = 'sum-percent ' + data.percent_class;
        document.getElementById('today-tbody').innerHTML = data.today_rows;
        document.getElementById('total-tbody').innerHTML = data.total_rows;
    } catch (e) { console.error('刷新失败:', e); }
}
setInterval(refreshRank, 10000);
</script>
</body>
</html>
