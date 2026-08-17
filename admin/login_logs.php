<?php
require_once __DIR__ . '/../common/session_boot.php';
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
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if ($_POST['action'] === 'clear' && isset($_POST['range'])) {
            $range = $_POST['range'];
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
    $where = [];
    $params = [];
    if ($status === 'success' || $status === 'failed') { $where[] = 'status = ?'; $params[] = $status; }
    if ($keyword !== '') {
        $where[] = '(actor_name LIKE ? OR ip LIKE ? OR city LIKE ? OR region LIKE ?)';
        $kw = '%' . $keyword . '%';
        array_push($params, $kw, $kw, $kw, $kw);
    }
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
        COUNT(DISTINCT ip) AS uniq_ip
        FROM huli_login_logs WHERE login_at > DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch();
} catch (Exception $e) {
    error_log('[login_logs.php] 加载失败: ' . $e->getMessage());
    $feedback_msg = '加载失败，请稍后重试。';
    $feedback_type = 'error';
    $logs = []; $stats = ['total'=>0,'ok'=>0,'fail'=>0,'uniq_ip'=>0]; $total=0; $totalPages=1; $page=1;
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
<link rel="stylesheet" type="text/css" href="../assets/css/materialdesignicons.min.css">
<link rel="stylesheet" type="text/css" href="../assets/css/bootstrap.min.css">
<link rel="stylesheet" type="text/css" href="../assets/css/style.min.css">
</head>
<body>
<div class="container-fluid">
  <div class="row">
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-1">登录日志</h2>
                <div class="text-muted small">仅统计后台管理员的登录记录</div>
            </div>
            <div class="d-flex gap-2">
                <?php echo huli_post_action_button('login_logs.php', ['action' => 'clear', 'range' => '30d'], '清理 30 天前', 'btn btn-outline-warning btn-sm', '确定清理 30 天前的登录日志吗？'); ?>
                <?php echo huli_post_action_button('login_logs.php', ['action' => 'clear', 'range' => 'all'], '清空全部', 'btn btn-outline-danger btn-sm', '确定清空全部登录日志吗？此操作不可恢复！'); ?>
            </div>
        </div>
        <?php if ($feedback_msg): ?>
            <div class="alert alert-<?php echo $feedback_type === 'success' ? 'success' : 'danger'; ?>"><?php echo $feedback_msg; ?></div>
        <?php endif; ?>
        <div class="row g-3 mb-3">
            <?php
            $stat_cards = [
                ['label' => '近 7 天登录', 'value' => (int)($stats['total'] ?? 0), 'icon' => 'mdi-login-variant', 'color' => 'primary'],
                ['label' => '登录成功', 'value' => (int)($stats['ok'] ?? 0), 'icon' => 'mdi-check-circle-outline', 'color' => 'success'],
                ['label' => '登录失败', 'value' => (int)($stats['fail'] ?? 0), 'icon' => 'mdi-close-circle-outline', 'color' => 'danger'],
                ['label' => '独立 IP', 'value' => (int)($stats['uniq_ip'] ?? 0), 'icon' => 'mdi-ip-network', 'color' => 'warning'],
            ];
            foreach ($stat_cards as $c): ?>
            <div class="col-md">
                <div class="card border-0 shadow-sm">
                    <div class="card-body d-flex align-items-center">
                        <div class="me-3 d-flex align-items-center justify-content-center rounded" style="width:48px;height:48px;background:rgba(108,182,255,.16);color:#2879ba;font-size:24px;">
                            <i class="mdi <?php echo $c['icon']; ?>"></i>
                        </div>
                        <div>
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
                <form class="row g-2 mb-3" method="get">
                    <div class="col-md-3">
                        <select name="status" class="form-select">
                            <option value="">全部状态</option>
                            <option value="success" <?php echo $status === 'success' ? 'selected' : ''; ?>>成功</option>
                            <option value="failed" <?php echo $status === 'failed' ? 'selected' : ''; ?>>失败</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <input type="text" name="keyword" class="form-control" placeholder="按账号 / IP / 城市搜索" value="<?php echo htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary w-100" type="submit"><i class="mdi mdi-magnify"></i> 搜索</button>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>时间</th>
                                <th>账号</th>
                                <th>状态</th>
                                <th>IP 地址</th>
                                <th>地理位置</th>
                                <th>网络</th>
                                <th>UA</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr><td colspan="7" class="text-center text-muted py-4">暂无登录日志</td></tr>
                            <?php else: foreach ($logs as $l): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($l['login_at']); ?></td>
                                    <td><?php echo htmlspecialchars($l['actor_name'] ?: '-'); ?></td>
                                    <td>
                                        <?php if ($l['status'] === 'success'): ?>
                                            <span class="badge bg-success">成功</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">失败</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><code><?php echo htmlspecialchars($l['ip']); ?></code></td>
                                    <td><?php echo htmlspecialchars(trim(($l['country'] ?? '') . ' ' . ($l['region'] ?? '') . ' ' . ($l['city'] ?? ''))); ?></td>
                                    <td><small class="text-muted"><?php echo htmlspecialchars($l['isp'] ?? ''); ?></small></td>
                                    <td><small class="text-muted" title="<?php echo htmlspecialchars($l['user_agent']); ?>"><?php echo htmlspecialchars(mb_substr($l['user_agent'], 0, 40)); ?><?php echo mb_strlen($l['user_agent']) > 40 ? '…' : ''; ?></small></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($total > 0): ?>
                <nav aria-label="分页">
                    <ul class="pagination justify-content-end mt-3">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>"><a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status); ?>&keyword=<?php echo urlencode($keyword); ?>">&laquo;</a></li>
                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status); ?>&keyword=<?php echo urlencode($keyword); ?>"><?php echo $i; ?></a></li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>"><a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status); ?>&keyword=<?php echo urlencode($keyword); ?>">&raquo;</a></li>
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
