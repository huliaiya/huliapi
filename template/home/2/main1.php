<?php
@session_start();
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
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt_apis = $pdo->query("SELECT * FROM huli_apis ORDER BY id DESC");
    $apis = $stmt_apis->fetchAll(PDO::FETCH_ASSOC);
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
<style>
.api-card {
    transition: all 0.3s ease;
    border: 1px solid #dee2e6;
    border-radius: 0.5rem;
    height: 100%;
}
.api-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
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
}
.search-icon {
    position: absolute;
    z-index: 10;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: #6c757d;
    pointer-events: none;
}
.api-search-box {
    border-radius: 50px;
    padding-left: 35px;
    height: 40px;
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
.floating-sidebar-btn {
    position: fixed;
    right: 30px;
    bottom: 30px;
    z-index: 999;
}
.offcanvas-start {
    width: 300px;
}
.search-box {
    border-bottom: 1px solid #dee2e6;
}
.sidebar-categories {
    height: calc(100vh - 120px);
    overflow-y: auto;
}
.sidebar-category {
    border-bottom: 1px solid #f1f1f1;
}
.sidebar-category-header {
    padding: 12px 16px;
    background-color: #f8f9fa;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.sidebar-category-header:hover {
    background-color: #e9ecef;
}
.sidebar-category-header i {
    transition: transform 0.3s;
}
.sidebar-category-header.collapsed i {
    transform: rotate(-90deg);
}
.sidebar-category-content {
    background-color: #fff;
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease;
}
.sidebar-category-content.show {
    max-height: 10000px;
}
.sidebar-api-item {
    padding: 10px 16px 10px 30px;
    cursor: pointer;
    border-left: 3px solid transparent;
    transition: all 0.2s;
}
.sidebar-api-item:hover {
    background-color: #f8f9fa;
    border-left-color: #4a69bd;
}
.sidebar-api-item.active {
    background-color: #e9f5ff;
    border-left-color: #4a69bd;
}
.sidebar-api-name {
    font-size: 14px;
    margin-bottom: 2px;
}
.sidebar-api-endpoint {
    font-size: 12px;
    color: #6c757d;
}
.sidebar-api-billing {
    font-size: 11px;
    color: #6b7280;
    margin-top: 2px;
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
            <a rel="nofollow" href="tencent://message/?uin=326284281&Site=qq&Menu=yes" class="ad-color-0 ad-placeholder">
                🔥 文字广告位招租
            </a>
        </li>
        <li class="ad-item">
            <a rel="nofollow" href="http://shanhe.kim/api/qq/qqmp.php?qq=3962364640" class="ad-color-1 ad-placeholder">
                📢 5元/月 点击咨询
            </a>
        </li>
    <?php endif; ?>
</ul>
<div class="row mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <span class="avatar-md rounded-circle bg-white bg-opacity-25 avatar-box">
                        <i class="mdi mdi-api fs-4"></i>
                    </span>
                    <span class="fs-4 scroll-numbers"><?php echo count($apis); ?></span>
                </div>
                <div class="text-end">API总数</div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card bg-pink text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <span class="avatar-md rounded-circle bg-white bg-opacity-25 avatar-box">
                        <i class="mdi mdi-arrow-up-bold fs-4"></i>
                    </span>
                    <span class="fs-4 scroll-numbers"><?php echo array_sum(array_column($apis, 'total_calls')); ?></span>
                </div>
                <div class="text-end">总调用量</div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <span class="avatar-md rounded-circle bg-white bg-opacity-25 avatar-box">
                        <i class="mdi mdi-check-circle-outline fs-4"></i>
                    </span>
                    <span class="fs-4 scroll-numbers"><?php echo count(array_filter($apis, function($api) { return $api['status'] === 'normal'; })); ?></span>
                </div>
                <div class="text-end">可用API</div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card bg-danger text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <span class="avatar-md rounded-circle bg-white bg-opacity-25 avatar-box">
                        <i class="mdi mdi-alert-circle-outline fs-4"></i>
                    </span>
                    <span class="fs-4 scroll-numbers"><?php echo count(array_filter($apis, function($api) { return $api['status'] === 'error'; })); ?></span>
                </div>
                <div class="text-end">异常 API</div>
            </div>
        </div>
    </div>
</div>
<div class="card mb-4">
    <div class="card-body p-3">
        <div class="position-relative">
            <i class="mdi mdi-magnify search-icon fs-5"></i>
            <input type="text" id="api-search-input" class="form-control api-search-box ps-4" placeholder="搜索API接口名称或描述..." onkeyup="filterAPIs()">
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
        <div class="card h-100 api-card">
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
                    <a href="<?= $homeTemplateBaseUrl ?>doc.php?id=<?php echo $api['id']; ?>" class="btn btn-sm btn-primary">
                        查看详情
                    </a>
                </div>
            </div>
        </div>
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
<div class="floating-sidebar-btn">
    <button class="btn btn-primary rounded-circle p-3" data-bs-toggle="offcanvas" data-bs-target="#apiSidebar">
        <i class="mdi mdi-menu"></i>
    </button>
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
$(document).ready(function() {
    $('.scroll-numbers').scrollNumbers();
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
<a href="https://icp.gov.moe/?keyword=20265230" target="_blank">萌ICP备20265230号</a>
</script>
</body>
</html>
