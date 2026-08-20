<?php
require_once __DIR__ . '/../../../common/session_boot.php';
@error_reporting(0);
@ini_set('display_errors', 'Off');
$rootPath = dirname(__DIR__, 3);
define('ROOT_PATH', $rootPath . '/');
if (!file_exists(ROOT_PATH . 'config.php')) {
    die("系统错误：配置文件丢失。路径: " . ROOT_PATH . 'config.php');
}
require_once ROOT_PATH . 'config.php';
require_once ROOT_PATH . 'common/TemplateManager.php';
$homeTemplate = TemplateManager::getActiveHomeTemplate();
$homeTemplateBaseUrl = "/template/home/{$homeTemplate}/";
$userTemplate = TemplateManager::getActiveUserTemplate();
$userTemplateBaseUrl = "/template/User/{$userTemplate}/";
$apis = [];
$site_name = 'huliapi';
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt_apis = $pdo->query("SELECT * FROM huli_apis WHERE status = 'normal' ORDER BY id DESC");
    $apis = $stmt_apis->fetchAll(PDO::FETCH_ASSOC);
    $total_apis_all = (int)$pdo->query("SELECT COUNT(*) FROM huli_apis")->fetchColumn();
    $error_apis_count = (int)$pdo->query("SELECT COUNT(*) FROM huli_apis WHERE status = 'error'")->fetchColumn();
    $stmt_site = $pdo->query("SELECT setting_value FROM huli_settings WHERE setting_key = 'site_name'");
    $site_name = $stmt_site->fetchColumn() ?: 'huliapi';
} catch (PDOException $e) {
}
$announcement = null;
try {
    $stmt_announcement = $pdo->query("SELECT * FROM huli_announcements WHERE is_active = 1 ORDER BY created_at DESC LIMIT 1");
    $announcement = $stmt_announcement->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
}
$advertisements = [];
try {
    $stmt_ads = $pdo->query("SELECT id, title, link_url FROM huli_advertisements WHERE status = 'active' ORDER BY sort_order DESC");
    $advertisements = $stmt_ads->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
}
function getStatusBadge($status) {
    switch ($status) {
        case 'normal': return '<span class="badge bg-success">正常</span>';
        case 'error': return '<span class="badge bg-danger">异常</span>';
        case 'maintenance': return '<span class="badge bg-warning">维护</span>';
        default: return '<span class="badge bg-secondary">未知</span>';
    }
}
function getVisibilityAndBillingBadges($visibility, $is_billable, $price_per_call, $points_per_call) {
    $badges = [];
    if ($visibility === 'public') {
        $badges[] = '<span class="badge bg-green me-2"><i class="mdi mdi-earth me-1"></i>公开调用</span>';
    } else {
        $badges[] = '<span class="badge bg-info me-2"><i class="mdi mdi-key me-1"></i>密钥调用</span>';
    }
    if ($is_billable && isset($price_per_call)) {
        $price = number_format((float)$price_per_call, 2);
        $badges[] = "<span class='badge bg-purple me-2'><i class='mdi mdi-cash-multiple me-1'></i>余额计费: ¥{$price}/次</span>";
    } elseif (isset($points_per_call) && $points_per_call > 0) {
        $badges[] = "<span class='badge bg-amber me-2'><i class='mdi mdi-ticket-account me-1'></i>点数计费: {$points_per_call}点/次</span>";
    } else {
        $badges[] = '<span class="badge bg-gray me-2"><i class="mdi mdi-check-circle me-1"></i>免费调用</span>';
    }
    return implode('', $badges);
}
function getCallCountStyle($count) {
    $count = intval($count);
    if ($count > 500000) return ['color' => 'text-danger', 'icon' => '<i class="mdi mdi-fire text-danger flame-icon"></i>'];
    elseif ($count > 100000) return ['color' => 'text-danger', 'icon' => '<i class="mdi mdi-fire text-danger flame-icon"></i>'];
    elseif ($count > 50000) return ['color' => 'text-warning', 'icon' => '<i class="mdi mdi-fire text-warning flame-icon"></i>'];
    elseif ($count > 10000) return ['color' => 'text-warning', 'icon' => '<i class="mdi mdi-fire text-warning flame-icon"></i>'];
    elseif ($count > 1000) return ['color' => 'text-success', 'icon' => ''];
    return ['color' => 'text-muted', 'icon' => ''];
}
?>

<!DOCTYPE html>
<html lang="zh">
<head>
<link rel="canonical" href="https://api.ipojie.com">
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
<title>API大厅 - <?php echo htmlspecialchars($site_name ?? 'huliapi'); ?></title>
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-touch-fullscreen" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<link rel="stylesheet" type="text/css" href="../../../assets/css/materialdesignicons.min.css">
<link rel="stylesheet" type="text/css" href="../../../assets/css/bootstrap.min.css">
<link rel="stylesheet" type="text/css" href="../../../assets/css/style.min.css">
<link rel="stylesheet" type="text/css" href="../../../assets/css/liquid-glass.css">
<style>
.api-card {
    transition: all 0.3s ease;
    border: 1px solid rgba(180, 220, 245, .38);
    border-radius: 22px;
    height: 100%;
    background:
        linear-gradient(140deg, rgba(255, 255, 255, .55) 0%, rgba(214, 234, 250, .42) 50%, rgba(196, 224, 244, .35) 100%);
    box-shadow:
        0 8px 28px rgba(45, 100, 155, .10),
        inset 0 1px 0 rgba(255, 255, 255, .65);
    backdrop-filter: blur(18px) saturate(150%);
    -webkit-backdrop-filter: blur(18px) saturate(150%);
}
.api-card-link {
    display: block;
    color: inherit;
}
.api-card-link:hover {
    transform: translateY(-5px);
    box-shadow:
        0 18px 38px rgba(45, 100, 155, .20),
        inset 0 1px 0 rgba(255, 255, 255, .7);
    color: inherit;
}
.api-card-link:hover,
.api-card-link:focus {
    text-decoration: none;
}
.api-card .card-title,
.api-card .card-text,
.api-card .badge,
.api-card span,
.api-card h4 {
    pointer-events: none;
}
.announcement-bar {
    background-color: #e9f5ff;
    border-left: 4px solid #4a69bd;
}
.flame-icon {
    animation: flame-flicker 1s infinite alternate;
}
@keyframes flame-flicker {
    0% { opacity: 0.8; transform: scale(1); }
    100% { opacity: 1; transform: scale(1.1); }
}
.api-search-box {
    border-radius: 50px;
    padding-left: 40px;
    height: 44px;
    background:
        linear-gradient(140deg, rgba(255, 255, 255, .68) 0%, rgba(232, 244, 255, .48) 100%);
    border: 1px solid rgba(180, 220, 245, .55);
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, .85),
        0 4px 14px rgba(45, 100, 155, .08);
    backdrop-filter: blur(14px) saturate(140%);
    -webkit-backdrop-filter: blur(14px) saturate(140%);
}
.search-icon {
    position: absolute;
    z-index: 10;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #6c757d;
    pointer-events: none;
}
.sidebar-toggle-btn {
    height: 44px;
    padding: 0 18px;
    background: linear-gradient(135deg, rgba(108, 177, 245, .22), rgba(92, 197, 211, .22));
    border: 1px solid rgba(180, 220, 245, .6);
    color: #1f3a5f;
    font-weight: 600;
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border-radius: 22px;
    transition: all .25s;
    white-space: nowrap;
    box-shadow: 0 4px 14px rgba(45, 100, 155, .08);
}
.sidebar-toggle-btn:hover {
    background: linear-gradient(135deg, rgba(108, 177, 245, .35), rgba(92, 197, 211, .35));
    border-color: var(--glass-accent, #5d9fe8);
    color: var(--glass-accent, #5d9fe8);
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(93, 159, 232, .25);
}
.badge.bg-green {
    background-color: #10b981;
}
.badge.bg-amber {
    background-color: #f59e0b;
}
.badge.bg-gray {
    background-color: #6b7280;
}
.badge-container {
    margin-bottom: 0.5rem;
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
}
.badge {
    white-space: nowrap;
    font-size: 12px;
    padding: 0.2rem 0.4rem;
}
.offcanvas-start {
    width: 300px;
    background: linear-gradient(165deg, rgba(232, 244, 255, .92), rgba(214, 234, 250, .88));
    backdrop-filter: blur(22px) saturate(180%);
    -webkit-backdrop-filter: blur(22px) saturate(180%);
    border-right: 1px solid rgba(255, 255, 255, .55);
    box-shadow: 18px 0 44px rgba(33, 61, 105, .18);
}
.offcanvas-backdrop {
    background: linear-gradient(135deg, rgba(108, 177, 245, .12), rgba(92, 197, 211, .12));
    backdrop-filter: blur(6px) saturate(140%);
    -webkit-backdrop-filter: blur(6px) saturate(140%);
}
.offcanvas-backdrop.show {
    opacity: 1;
}
.offcanvas-header {
    background: linear-gradient(135deg, rgba(108, 177, 245, .18), rgba(92, 197, 211, .18));
    border-bottom: 1px solid rgba(255, 255, 255, .45);
}
.offcanvas-title {
    font-weight: 700;
    color: #1f3a5f;
}
.search-box {
    border-bottom: 1px solid rgba(180, 220, 245, .45);
    background: rgba(255, 255, 255, .4);
}
.search-box .form-control {
    background: rgba(255, 255, 255, .65);
    border: 1px solid rgba(180, 220, 245, .55);
    color: #1f3a5f;
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
}
.search-box .form-control:focus {
    background: rgba(255, 255, 255, .85);
    border-color: var(--glass-accent, #5d9fe8);
    box-shadow: 0 0 0 3px rgba(93, 159, 232, .18);
}
.sidebar-categories {
    height: calc(100vh - 120px);
    overflow-y: auto;
}
.sidebar-category {
    border-bottom: 1px solid rgba(255, 255, 255, .35);
}
.sidebar-category-header {
    padding: 12px 16px;
    background: rgba(255, 255, 255, .55);
    backdrop-filter: blur(10px) saturate(180%);
    -webkit-backdrop-filter: blur(10px) saturate(180%);
    font-weight: 600;
    color: #1f3a5f;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: background .25s;
}
.sidebar-category-header:hover {
    background: rgba(255, 255, 255, .78);
}
.sidebar-category-header i {
    transition: transform 0.3s;
}
.sidebar-category-header.collapsed i {
    transform: rotate(-90deg);
}
.sidebar-category-content {
    background: rgba(245, 250, 253, .35);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease;
}
.sidebar-category-content.show {
    max-height: 10000px;
}
.sidebar-api-item {
    padding: 12px 14px;
    cursor: pointer;
    border-left: 3px solid transparent;
    border-radius: 12px;
    margin: 3px 6px;
    background: rgba(255, 255, 255, .4);
    backdrop-filter: blur(10px) saturate(180%);
    -webkit-backdrop-filter: blur(10px) saturate(180%);
    transition: all 0.25s cubic-bezier(.4,0,.2,1);
}
.sidebar-api-item:hover {
    background: rgba(255, 255, 255, .72);
    border-left-color: var(--glass-accent, #5d9fe8);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(93, 159, 232, .2);
}
.sidebar-api-item.active {
    background: linear-gradient(135deg, rgba(108, 177, 245, .3), rgba(92, 197, 211, .3));
    border-left: 3px solid var(--glass-accent, #5d9fe8);
    box-shadow: 0 4px 16px rgba(93, 159, 232, .3);
}
.sidebar-api-name {
    font-size: 14px;
    margin-bottom: 3px;
    font-weight: 600;
    color: #1f3a5f;
    line-height: 1.3;
}
.sidebar-api-endpoint {
    font-size: 12px;
    color: #5b7794;
    line-height: 1.3;
}
.sidebar-api-billing {
    font-size: 11px;
    color: #4a6480;
    margin-top: 3px;
}
.no-sidebar-category {
    padding: 16px;
    color: #6c757d;
    text-align: center;
}
.ad-list {
    list-style: none;
    padding: 0;
    margin: 20px 0;
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
}
.ad-item {
    height: 36px;
    line-height: 36px;
    text-align: center;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    background: #ffffff;
    box-shadow: 0 1px 2px rgba(0,0,0,0.03);
    transition: all 0.2s ease;
    overflow: hidden;
}
.ad-item:hover {
    border-color: #adb5bd;
    box-shadow: 0 4px 8px rgba(0,0,0,0.05);
    transform: translateY(-1px);
}
.ad-item a {
    display: block;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    padding: 0 8px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.ad-color-0 { color: #0d6efd !important; }
.ad-color-1 { color: #198754 !important; }
.ad-color-2 { color: #dc3545 !important; }
.ad-color-3 { color: #fd7e14 !important; }
.ad-color-4 { color: #6f42c1 !important; }
.ad-color-5 { color: #d63384 !important; }
.ad-color-6 { color: #0dcaf0 !important; }
.ad-color-7 { color: #6c757d !important; }
.ad-placeholder {
    color: #0a4b7a !important;
    background: #f8f9fa;
}
.ad-placeholder:hover {
    background: #e9ecef;
}
.stats-card-row .card-body {
    display: flex;
    flex-direction: column;
    justify-content: center;
    min-height: 110px;
}
.stats-card-row .card-body > .d-flex {
    align-items: center;
}
.stats-card-row .card-body > .text-end {
    margin-top: 8px;
}
.stats-card {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 18px 20px;
    min-height: 96px;
    border-radius: 18px;
    border: 1px solid rgba(180, 220, 245, .45);
    background:
        linear-gradient(135deg, rgba(255, 255, 255, .62) 0%, rgba(220, 238, 252, .48) 100%);
    box-shadow:
        0 6px 22px rgba(45, 100, 155, .10),
        inset 0 1px 0 rgba(255, 255, 255, .65);
    backdrop-filter: blur(14px) saturate(160%);
    -webkit-backdrop-filter: blur(14px) saturate(160%);
    transition: box-shadow .25s ease;
    cursor: default;
    user-select: none;
}
.stats-card:hover {
    box-shadow:
        0 12px 28px rgba(45, 100, 155, .18),
        inset 0 1px 0 rgba(255, 255, 255, .75);
}
.stats-card:active {
    box-shadow:
        0 6px 22px rgba(45, 100, 155, .10),
        inset 0 1px 0 rgba(255, 255, 255, .65);
}
.stats-card-icon {
    flex: 0 0 auto;
    width: 52px;
    height: 52px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    box-shadow:
        inset 0 2px 4px rgba(255, 255, 255, .35),
        0 4px 10px rgba(45, 100, 155, .15);
}
.stats-card-icon > i {
    font-size: 26px;
    line-height: 1;
}
.stats-card-info {
    flex: 1 1 auto;
    display: flex;
    flex-direction: column;
    justify-content: center;
    min-width: 0;
}
.stats-card-value {
    font-size: 28px;
    font-weight: 700;
    line-height: 1.1;
    color: #0a4b7a;
    letter-spacing: -.5px;
}
.stats-card-label {
    font-size: 13px;
    color: #4a7290;
    margin-top: 4px;
    letter-spacing: .5px;
}
</style>
</head>
<body>
<div class="container-fluid py-4">
<ul class="ad-list">
    <?php if (!empty($advertisements)): ?>
        <?php foreach ($advertisements as $index => $ad):
            $colorIndex = $ad['id'] % 8;
        ?>
            <li class="ad-item">
                <a href="<?php echo htmlspecialchars($ad['link_url']); ?>" target="_blank" rel="nofollow" class="ad-color-<?php echo $colorIndex; ?>" title="<?php echo htmlspecialchars($ad['title']); ?>">
                    <?php echo htmlspecialchars($ad['title']); ?>
                </a>
            </li>
        <?php endforeach; ?>
    <?php else: ?>
        <li class="ad-item">
            <a rel="nofollow" href="https://t.me/huliaiya" class="ad-color-0 ad-placeholder">
                🔥 文字广告位招租
            </a>
        </li>
        <li class="ad-item">
            <a rel="nofollow" href="https://t.me/huliaiya" class="ad-color-1 ad-placeholder">
                📢 5元/月 点击咨询
            </a>
        </li>
    <?php endif; ?>
</ul>
<div class="row mb-4 stats-card-row g-3">
    <?php
    $stats = [
        ['label' => 'API 总数', 'value' => $total_apis_all, 'icon' => 'mdi-api', 'bg' => 'rgba(108, 178, 235, .85)', 'accent' => '#5ba4dc'],
        ['label' => '总调用量', 'value' => array_sum(array_column($apis, 'total_calls')), 'icon' => 'mdi-arrow-up-bold', 'bg' => 'rgba(235, 145, 195, .85)', 'accent' => '#d97ab1'],
        ['label' => '可用 API', 'value' => count($apis), 'icon' => 'mdi-check-circle-outline', 'bg' => 'rgba(140, 215, 175, .85)', 'accent' => '#7bc89e'],
        ['label' => '异常 API', 'value' => $error_apis_count, 'icon' => 'mdi-alert-circle-outline', 'bg' => 'rgba(235, 145, 160, .85)', 'accent' => '#e08899'],
    ];
    foreach ($stats as $s): ?>
    <div class="col-md-6 col-xl-3">
        <div class="stats-card">
            <div class="stats-card-icon" style="background: <?php echo $s['bg']; ?>;">
                <i class="mdi <?php echo $s['icon']; ?>"></i>
            </div>
            <div class="stats-card-info">
                <div class="stats-card-value scroll-numbers"><?php echo (int)$s['value']; ?></div>
                <div class="stats-card-label"><?php echo htmlspecialchars($s['label']); ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<div class="card mb-4">
    <div class="card-body p-3">
        <div class="d-flex align-items-center gap-2">
            <div class="position-relative flex-grow-1">
                <i class="mdi mdi-magnify search-icon fs-5"></i>
                <input type="text" id="api-search-input" class="form-control api-search-box ps-4" placeholder="搜索API接口名称或描述..." onkeyup="filterAPIs()">
            </div>
            <button type="button" class="btn btn-outline-primary sidebar-toggle-btn" data-bs-toggle="offcanvas" data-bs-target="#apiSidebar" aria-label="打开API分类导航">
                <i class="mdi mdi-format-list-bulleted-square me-1"></i>
                <span>分类</span>
            </button>
        </div>
    </div>
</div>
<div class="row" id="api-grid">
    <?php foreach ($apis as $api):
        $style = getCallCountStyle($api['total_calls']);
        $api['price_per_call'] = isset($api['price_per_call']) ? $api['price_per_call'] : 0;
        $api['points_per_call'] = isset($api['points_per_call']) ? $api['points_per_call'] : 0;
    ?>
    <div class="col-md-6 col-lg-4 mb-4 api-card-item" data-name="<?php echo htmlspecialchars(strtolower($api['name'])); ?>" data-desc="<?php echo htmlspecialchars(strtolower($api['description'])); ?>" data-category="<?php echo $api['category_id'] ?? '0'; ?>">
        <a href="<?= $homeTemplateBaseUrl ?>doc.php?id=<?php echo $api['id']; ?>" class="card h-100 api-card api-card-link text-decoration-none">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h4 class="card-title mb-0"><?php echo htmlspecialchars($api['name']); ?></h4>
                    <?php echo getStatusBadge($api['status']); ?>
                </div>
                <div class="badge-container">
                    <?php echo getVisibilityAndBillingBadges($api['visibility'], $api['is_billable'], $api['price_per_call'], $api['points_per_call']); ?>
                </div>
                <?php if (!empty($api['category_id'])): ?>
                    <?php
                    $stmtCat = $pdo->prepare("SELECT name FROM huli_api_categories WHERE id = ?");
                    $stmtCat->execute([$api['category_id']]);
                    $categoryName = $stmtCat->fetchColumn();
                    ?>
                    <span class="badge bg-light text-dark mb-2">
                        <i class="mdi mdi-tag-outline me-1"></i>
                        <?php echo htmlspecialchars($categoryName); ?>
                    </span>
                <?php endif; ?>
                <p class="card-text text-muted mb-3"><?php echo htmlspecialchars($api['description']); ?></p>
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="me-3 <?php echo $style['color']; ?>">
                            <i class="mdi mdi-counter me-1"></i>
                            <?php echo number_format($api['total_calls']); ?>
                            <?php echo $style['icon']; ?>
                        </span>
                        <span class="text-muted">
                            <i class="mdi mdi-format-list-bulleted-type me-1"></i>
                            <?php echo strtoupper($api['response_format'] ?? 'TEXT'); ?>
                        </span>
                    </div>
                    <span class="btn btn-sm btn-primary">
                        查看详情
                    </span>
                </div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>
</div>
<div class="offcanvas offcanvas-start" tabindex="-1" id="apiSidebar" aria-labelledby="apiSidebarLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="apiSidebarLabel">API分类导航</h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0">
        <div class="search-box p-2">
            <div class="position-relative">
                <i class="mdi mdi-magnify search-icon"></i>
                <input type="text" id="sidebar-search" class="form-control form-control-sm ps-4" placeholder="搜索接口...">
            </div>
        </div>
        <div class="sidebar-categories" id="sidebar-categories">
        </div>
    </div>
</div>
<script type="text/javascript" src="../../../assets/js/jquery.min.js"></script>
<script type="text/javascript" src="../../../assets/js/popper.min.js"></script>
<script type="text/javascript" src="../../../assets/js/bootstrap.min.js"></script>
<script type="text/javascript" src="../../../assets/js/scroll-numbers.js"></script>
<script>
function filterAPIs() {
    const input = document.getElementById('api-search-input');
    const filter = input.value.toLowerCase();
    const cards = document.querySelectorAll('.api-card-item');
    if (!filter.trim()) {
        cards.forEach(card => card.style.display = "block");
        return;
    }
    const escapedFilter = filter.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    let regex;
    try {
        regex = new RegExp(escapedFilter, 'i');
    } catch (e) {
        cards.forEach(card => card.style.display = "block");
        return;
    }
    cards.forEach(card => {
        const name = card.dataset.name || '';
        const desc = card.dataset.desc || '';
        card.style.display = (regex.test(name) || regex.test(desc)) ? "block" : "none";
    });
}
(function () {})();
$(document).ready(function() {
    function debounce(func, wait) {
        let timeout;
        return function() {
            const context = this, args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                func.apply(context, args);
            }, wait);
        };
    }
    const performSearch = debounce(function() {
        const searchTerm = $(this).val().trim();
        if (!searchTerm) {
            $('.api-card-item').show();
            return;
        }
        try {
            const regex = new RegExp(searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'i');
            $('.api-card-item').each(function() {
                const $card = $(this);
                const name = $card.data('name') || '';
                const desc = $card.data('desc') || '';
                $card.toggle(regex.test(name) || regex.test(desc));
            });
        } catch (e) {
            $('.api-card-item').show();
        }
    }, 300);
    const $searchInput = $('#api-search-input');
    $searchInput.on('input', performSearch);
    $searchInput[0].addEventListener('input', filterAPIs);
});
</script>
<script>
$(document).ready(function() {
    function loadSidebarCategories() {
        const $sidebar = $('#sidebar-categories');
        $sidebar.empty();
        const categories = {};
        const uncategorized = [];
        $('.api-card-item').each(function() {
            const $card = $(this);
            const apiId = $card.find('a').attr('href').split('id=')[1];
            const apiName = $card.data('name');
            const categoryId = $card.data('category') || '0';
            const badgeText = $card.find('.badge-container').text();
            let billingInfo = '免费';
            let billingClass = 'text-gray';
            if (badgeText.includes('余额计费')) {
                billingInfo = badgeText.match(/¥([\d.]+)\/次/)[0];
                billingClass = 'text-purple';
            } else if (badgeText.includes('点数计费')) {
                billingInfo = badgeText.match(/(\d+)点\/次/)[0];
                billingClass = 'text-amber';
            }
            const api = {
                id: apiId,
                name: apiName,
                billing: billingInfo,
                billingClass: billingClass
            };
            if (categoryId === '0') {
                uncategorized.push(api);
            } else {
                if (!categories[categoryId]) {
                    categories[categoryId] = {
                        id: categoryId,
                        name: $card.find('.badge.bg-light').text().replace(/.*\n\s*/, ''),
                        apis: []
                    };
                }
                categories[categoryId].apis.push(api);
            }
        });
        for (const catId in categories) {
            const cat = categories[catId];
            const $category = $(`
                <div class="sidebar-category">
                    <div class="sidebar-category-header" data-category="${cat.id}">
                        <span>${cat.name} (${cat.apis.length})</span>
                        <i class="mdi mdi-chevron-down"></i>
                    </div>
                    <div class="sidebar-category-content" id="sidebar-category-${cat.id}"></div>
                </div>
            `);
            $sidebar.append($category);
            const $content = $(`#sidebar-category-${cat.id}`);
            cat.apis.forEach(api => {
                $content.append(`
                    <div class="sidebar-api-item" data-id="${api.id}">
                        <div class="sidebar-api-name">${api.name}</div>
                        <div class="sidebar-api-billing ${api.billingClass}">${api.billing}</div>
                    </div>
                `);
            });
        }
        if (uncategorized.length > 0) {
            $sidebar.append(`
                <div class="sidebar-category">
                    <div class="sidebar-category-header" data-category="0">
                        <span>未分类API (${uncategorized.length})</span>
                        <i class="mdi mdi-chevron-down"></i>
                    </div>
                    <div class="sidebar-category-content show" id="sidebar-category-0"></div>
                </div>
            `);
            const $uncat = $('#sidebar-category-0');
            uncategorized.forEach(api => {
                $uncat.append(`
                    <div class="sidebar-api-item" data-id="${api.id}">
                        <div class="sidebar-api-name">${api.name}</div>
                        <div class="sidebar-api-billing ${api.billingClass}">${api.billing}</div>
                    </div>
                `);
            });
        }
        $('.sidebar-category-header').on('click', function() {
            const categoryId = $(this).data('category');
            $(this).toggleClass('collapsed');
            $(`#sidebar-category-${categoryId}`).toggleClass('show');
        });
        $('.sidebar-api-item').on('click', function() {
            const apiId = $(this).data('id');
            window.location.href = `doc.php?id=${apiId}`;
        });
        $('#sidebar-search').on('input', function() {
            const searchTerm = $(this).val().toLowerCase();
            if (!searchTerm) {
                $('.sidebar-api-item').show();
                $('.sidebar-category').show();
                return;
            }
            $('.sidebar-api-item').hide();
            $('.sidebar-api-item').each(function() {
                const name = $(this).find('.sidebar-api-name').text().toLowerCase();
                if (name.includes(searchTerm)) {
                    $(this).show();
                    $(this).closest('.sidebar-category').show();
                    $(this).closest('.sidebar-category-content').addClass('show');
                }
            });
            $('.sidebar-category').each(function() {
                const hasVisible = $(this).find('.sidebar-api-item:visible').length > 0;
                if (!hasVisible) $(this).hide();
            });
        });
    }
    loadSidebarCategories();
});
</script>
</body>
</html>
