<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
if (file_exists('../config.php')) { require_once '../config.php'; } else { die("出现错误！配置文件丢失。"); }
$username = isset($_SESSION['admin_username']) ? htmlspecialchars($_SESSION['admin_username'], ENT_QUOTES, 'UTF-8') : '';
$feedback_msg = '';
$feedback_type = '';
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    $columns = $pdo->query("SHOW COLUMNS FROM `huli_cdkeys`")->fetchAll(PDO::FETCH_COLUMN);
    $pdo->beginTransaction();
    if (!in_array('type', $columns)) {
        $pdo->exec("ALTER TABLE `huli_cdkeys` ADD COLUMN `type` ENUM('balance', 'points', 'membership') NOT NULL DEFAULT 'balance' AFTER `cdkey`");
    } else {
        $pdo->exec("ALTER TABLE `huli_cdkeys` CHANGE COLUMN `type` `type` ENUM('balance', 'points', 'membership') NOT NULL DEFAULT 'balance'");
    }
    if (!in_array('points', $columns)) {
        $pdo->exec("ALTER TABLE `huli_cdkeys` ADD COLUMN `points` INT NOT NULL DEFAULT 0 AFTER `balance`");
    }
    if (!in_array('membership_days', $columns)) {
        $pdo->exec("ALTER TABLE `huli_cdkeys` ADD COLUMN `membership_days` INT NOT NULL DEFAULT 0 AFTER `points`");
    }
    if (in_array('status', $columns)) {
        $statusColumn = $pdo->query("SELECT DATA_TYPE, COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'huli_cdkeys' AND COLUMN_NAME = 'status'")->fetch();
        if (strpos($statusColumn['COLUMN_TYPE'], 'unused') === false || strpos($statusColumn['COLUMN_TYPE'], 'used') === false) {
            $pdo->exec("ALTER TABLE `huli_cdkeys` CHANGE COLUMN `status` `status` ENUM('unused', 'used') NOT NULL DEFAULT 'unused'");
        }
    } else {
        $pdo->exec("ALTER TABLE `huli_cdkeys` ADD COLUMN `status` ENUM('unused', 'used') NOT NULL DEFAULT 'unused' AFTER `membership_days`");
    }
    if (!in_array('used_by_user_id', $columns)) {
        $pdo->exec("ALTER TABLE `huli_cdkeys` ADD COLUMN `used_by_user_id` INT NULL AFTER `status`");
    }
    if (!in_array('used_at', $columns)) {
        $pdo->exec("ALTER TABLE `huli_cdkeys` ADD COLUMN `used_at` DATETIME NULL AFTER `used_by_user_id`");
    }
    $pdo->commit();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action']) && $_POST['action'] === 'generate') {
            $count = filter_var($_POST['count'], FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1, 'max_range' => 1000]
            ]);
            $type = in_array($_POST['type'], ['balance', 'points', 'membership']) ? $_POST['type'] : 'balance';
            if ($type === 'balance') {
                $value = filter_var($_POST['balance'], FILTER_VALIDATE_FLOAT);
                if ($value === false || $value <= 0) {
                    throw new Exception('请输入有效的金额，必须大于0。');
                }
                $value = round($value, 2);
            } elseif ($type === 'points') {
                $value = filter_var($_POST['points'], FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1]
                ]);
                if ($value === false) {
                    throw new Exception('请输入有效的点数，必须为正整数。');
                }
            } else {
                $value = filter_var($_POST['membership_days'], FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1]
                ]);
                if ($value === false) {
                    throw new Exception('请输入有效的会员天数，必须为正整数。');
                }
            }
            if ($count === false) {
                throw new Exception('生成数量必须为1到1000之间的整数。');
            }
            $pdo->beginTransaction();
            $values = [];
            $placeholders = [];
            for ($i = 0; $i < $count; $i++) {
                $key = strtoupper(bin2hex(random_bytes(16)));
                $balance = ($type === 'balance') ? $value : 0;
                $points = ($type === 'points') ? $value : 0;
                $membership_days = ($type === 'membership') ? $value : 0;
                $values[] = $key;
                $values[] = $type;
                $values[] = $balance;
                $values[] = $points;
                $values[] = $membership_days;
                $placeholders[] = '(?, ?, ?, ?, ?)';
            }
            $stmt = $pdo->prepare("INSERT INTO huli_cdkeys (cdkey, type, balance, points, membership_days) VALUES " . implode(', ', $placeholders));
            $stmt->execute($values);
            $pdo->commit();
            $unit = $type === 'balance' ? '元' : ($type === 'points' ? '点' : '天');
            $_SESSION['feedback_msg'] = "成功生成了 {$count} 个价值 {$value}{$unit} 的卡密。";
            $_SESSION['feedback_type'] = 'success';
        } elseif (isset($_POST['action']) && $_POST['action'] === 'export') {
            $exportType = in_array($_POST['export_type'], ['all', 'used', 'unused']) ? $_POST['export_type'] : 'unused';
            $where = '';
            $params = [];
            if ($exportType !== 'all') {
                $where = "WHERE status = ?";
                $params[] = $exportType;
            }
            $stmt = $pdo->prepare("SELECT cdkey, type, balance, points FROM huli_cdkeys {$where} ORDER BY id DESC");
            $stmt->execute($params);
            $keys = $stmt->fetchAll();
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=cdkeys_export_' . date('YmdHis') . '.csv');
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($output, ['卡密', '类型', '价值']);
            foreach ($keys as $key) {
                $value = $key['type'] === 'balance' ?
                    '¥' . number_format($key['balance'], 2) :
                    $key['points'] . '点';
                fputcsv($output, [
                    $key['cdkey'],
                    $key['type'] === 'balance' ? '余额' : '点数',
                    $value
                ]);
            }
            fclose($output);
            exit;
        }
    } elseif (isset($_GET['action'])) {
        if ($_GET['action'] === 'delete' && isset($_GET['id'])) {
            $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
            if ($id === false) {
                throw new Exception('无效的卡密ID。');
            }
            $stmt = $pdo->prepare("DELETE FROM huli_cdkeys WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['feedback_msg'] = '卡密已成功删除。';
            $_SESSION['feedback_type'] = 'success';
        } elseif ($_GET['action'] === 'cleanup') {
            $stmt = $pdo->prepare("DELETE FROM huli_cdkeys WHERE status = 'unused'");
            $stmt->execute();
            $deleted_count = $stmt->rowCount();
            $_SESSION['feedback_msg'] = "成功清理了 {$deleted_count} 个未使用的卡密。";
            $_SESSION['feedback_type'] = 'success';
        } else {
            throw new Exception('无效的操作。');
        }
        header('Location: cdkeys.php');
        exit;
    }
    if (isset($_SESSION['feedback_msg'])) {
        $feedback_msg = $_SESSION['feedback_msg'];
        $feedback_type = $_SESSION['feedback_type'];
        unset($_SESSION['feedback_msg'], $_SESSION['feedback_type']);
    }
    $page = isset($_GET['page']) ? max(1, filter_var($_GET['page'], FILTER_VALIDATE_INT)) : 1;
    $limit = 20;
    $offset = ($page - 1) * $limit;
    $totalStmt = $pdo->query("SELECT COUNT(*) as total FROM huli_cdkeys");
    $total = $totalStmt->fetchColumn();
    $totalPages = max(1, ceil($total / $limit));
    $stmt = $pdo->prepare("
        SELECT c.*, u.username
        FROM huli_cdkeys c
        LEFT JOIN huli_users u ON c.used_by_user_id = u.id
        ORDER BY c.id DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $keys = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('卡密操作失败: ' . $e->getMessage());
    $feedback_msg = '操作失败，请稍后重试。';
    $feedback_type = 'error';
    $keys = [];
    $total = 0;
    $totalPages = 1;
    $page = 1;
}
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-touch-fullscreen" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<link rel="stylesheet" type="text/css" href="../assets/css/materialdesignicons.min.css">
<link rel="stylesheet" type="text/css" href="../assets/css/bootstrap.min.css">
<link rel="stylesheet" type="text/css" href="../assets/css/style.min.css">
<style>
    .copyable { cursor: pointer; position: relative; transition: background-color 0.2s; }
    .copyable:hover { background-color: #f8f9fa; }
    .copy-tooltip {
        position: absolute;
        top: -30px;
        left: 50%;
        transform: translateX(-50%);
        background-color: #333;
        color: #fff;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        opacity: 0;
        transition: opacity 0.3s;
        z-index: 100;
        white-space: nowrap;
    }
    .copyable:hover .copy-tooltip { opacity: 1; }
    .toast-container { position: fixed; top: 20px; right: 20px; z-index: 1100; }
    .badge-balance { background-color: #d1fae5; color: #065f46; }
    .badge-points { background-color: #dbeafe; color: #1e40af; }
    .badge-warning { background-color: #fef3c7; color: #92400e; }
    .table-responsive { overflow-x: auto; }
    @media (max-width: 768px) {
        .card-search .row > div {
            margin-bottom: 10px;
        }
        .btn-group {
            flex-wrap: wrap;
        }
    }
</style>
</head>
<body>
<div class="container-fluid">
    <div class="toast-container">
        <?php if ($feedback_msg): ?>
        <div class="toast show align-items-center text-white bg-<?php echo $feedback_type === 'success' ? 'success' : 'danger'; ?> border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <?php echo $feedback_msg; ?>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <header class="card-header">
                    <div class="card-title">卡密管理</div>
                    <div class="card-actions">
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#exportModal">
                            <i class="mdi mdi-download me-1"></i>导出卡密
                        </button>
                    </div>
                </header>
                <div class="card-body">
                    <div class="card-search mb-3">
                        <form class="search-form" method="post" action="cdkeys.php" role="form" id="generateForm">
                            <input type="hidden" name="action" value="generate">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <div class="row">
                                        <label for="type-select" class="col-sm-4 col-form-label">卡密类型</label>
                                        <div class="col-sm-8">
                                            <select class="form-select" name="type" id="type-select" required>
                                                <option value="balance">余额卡密</option>
                                                <option value="points">点数卡密</option>
                                                <option value="membership">会员卡密</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="row">
                                        <label for="count" class="col-sm-4 col-form-label">生成数量</label>
                                        <div class="col-sm-8">
                                            <input type="number" class="form-control" name="count" id="count" value="10"
                                                placeholder="生成数量" required min="1" max="1000">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3" id="value-input-group">
                                    <div class="row">
                                        <label for="balance" class="col-sm-4 col-form-label" id="value-label">面额 (元)</label>
                                        <div class="col-sm-8">
                                            <input type="number" step="0.01" class="form-control" name="balance" id="balance"
                                                value="10.00" placeholder="面额" required min="0.01">
                                            <input type="number" step="1" class="form-control d-none" name="points" id="points"
                                                value="100" placeholder="点数" required min="1">
                                            <input type="number" step="1" class="form-control d-none" name="membership_days" id="membership_days"
                                                value="30" placeholder="会员天数" required min="1">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-primary me-1">
                                        <i class="mdi mdi-plus-circle-outline me-1"></i>生成卡密
                                    </button>
                                    <a href="?action=cleanup" onclick="return confirm('确定要清理所有未使用的卡密吗？此操作不可恢复！');"
                                       class="btn btn-danger">
                                        <i class="mdi mdi-delete-outline me-1"></i>清理未使用
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th width="5%">#</th>
                                    <th width="20%">卡密 (CDKey)</th>
                                    <th width="10%">类型</th>
                                    <th width="10%">价值</th>
                                    <th width="10%">状态</th>
                                    <th width="15%">使用者</th>
                                    <th width="15%">使用时间</th>
                                    <th width="15%">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($keys)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">暂无卡密数据</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($keys as $index => $key): ?>
                                    <tr>
                                        <td><?php echo $offset + $index + 1; ?></td>
                                        <td class="copyable" onclick="copyToClipboard(this, '<?php echo htmlspecialchars($key['cdkey'], ENT_QUOTES, 'UTF-8'); ?>')">
                                            <code><?php echo htmlspecialchars($key['cdkey'], ENT_QUOTES, 'UTF-8'); ?></code>
                                            <span class="copy-tooltip">点击复制</span>
                                        </td>
                                        <td>
                                            <?php if ($key['type'] === 'balance'): ?>
                                                <span class="badge badge-balance">余额</span>
                                            <?php elseif ($key['type'] === 'points'): ?>
                                                <span class="badge badge-points">点数</span>
                                            <?php else: ?>
                                                <span class="badge badge-warning">会员</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($key['type'] === 'balance'): ?>
                                                ¥ <?php echo number_format($key['balance'], 2); ?>
                                            <?php elseif ($key['type'] === 'points'): ?>
                                                <?php echo number_format($key['points']); ?> 点
                                            <?php else: ?>
                                                <?php echo number_format($key['membership_days']); ?> 天
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($key['status'] === 'unused'): ?>
                                                <span class="badge bg-success">未使用</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">已使用</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $key['username'] ? htmlspecialchars($key['username'], ENT_QUOTES, 'UTF-8') : 'N/A'; ?></td>
                                        <td><?php echo $key['used_at'] ? date('Y-m-d H:i:s', strtotime($key['used_at'])) : 'N/A'; ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="?action=delete&id=<?php echo $key['id']; ?>"
                                                   onclick="return confirm('确定要删除这个卡密吗？此操作不可恢复！');"
                                                   class="btn btn-outline-danger" data-bs-toggle="tooltip" title="删除">
                                                    <i class="mdi mdi-delete"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($total > 0): ?>
                    <nav aria-label="分页导航">
                        <ul class="pagination justify-content-end mt-3">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="上一页">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            if ($startPage > 1) {
                                echo '<li class="page-item ' . ($page == 1 ? 'active' : '') . '">
                                    <a class="page-link" href="?page=1">1</a>
                                </li>';
                                if ($startPage > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }
                            for ($i = $startPage; $i <= $endPage; $i++) {
                                echo '<li class="page-item ' . ($page == $i ? 'active' : '') . '">
                                    <a class="page-link" href="?page=' . $i . '">' . $i . '</a>
                                </li>';
                            }
                            if ($endPage < $totalPages) {
                                if ($endPage < $totalPages - 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                echo '<li class="page-item ' . ($page == $totalPages ? 'active' : '') . '">
                                    <a class="page-link" href="?page=' . $totalPages . '">' . $totalPages . '</a>
                                </li>';
                            }
                            ?>
                            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="下一页">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exportModalLabel">导出卡密</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="cdkeys.php">
                <input type="hidden" name="action" value="export">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="exportType" class="form-label">选择导出类型</label>
                        <select class="form-select" id="exportType" name="export_type" required>
                            <option value="unused" selected>仅导出未使用的卡密</option>
                            <option value="used">仅导出已使用的卡密</option>
                            <option value="all">导出所有卡密</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary">导出CSV文件</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script type="text/javascript" src="../assets/js/jquery.min.js"></script>
<script type="text/javascript" src="../assets/js/popper.min.js"></script>
<script type="text/javascript" src="../assets/js/bootstrap.min.js"></script>
<script type="text/javascript" src="../assets/js/main.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    const typeSelect = document.getElementById('type-select');
    const valueLabel = document.getElementById('value-label');
    const balanceInput = document.querySelector('input[name="balance"]');
    const pointsInput = document.querySelector('input[name="points"]');
    const membershipDaysInput = document.querySelector('input[name="membership_days"]');
    function toggleInputFields() {
        if (typeSelect.value === 'balance') {
            valueLabel.textContent = '面额 (元)';
            balanceInput.classList.remove('d-none');
            pointsInput.classList.add('d-none');
            membershipDaysInput.classList.add('d-none');
            balanceInput.required = true;
            pointsInput.required = false;
            membershipDaysInput.required = false;
        } else if (typeSelect.value === 'points') {
            valueLabel.textContent = '点数';
            pointsInput.classList.remove('d-none');
            balanceInput.classList.add('d-none');
            membershipDaysInput.classList.add('d-none');
            pointsInput.required = true;
            balanceInput.required = false;
            membershipDaysInput.required = false;
        } else {
            valueLabel.textContent = '会员天数';
            membershipDaysInput.classList.remove('d-none');
            balanceInput.classList.add('d-none');
            pointsInput.classList.add('d-none');
            membershipDaysInput.required = true;
            balanceInput.required = false;
            pointsInput.required = false;
        }
    }
    typeSelect.addEventListener('change', toggleInputFields);
    toggleInputFields();
    const toasts = document.querySelectorAll('.toast');
    toasts.forEach(toast => {
        const bsToast = new bootstrap.Toast(toast, { delay: 5000 });
        bsToast.show();
        toast.addEventListener('hidden.bs.toast', function () {
            toast.remove();
        });
    });
});

function copyToClipboard(element, text) {
    navigator.clipboard.writeText(text).then(function() {
        showToast('success', '卡密已复制到剪贴板');
        element.classList.add('bg-light');
        setTimeout(function() {
            element.classList.remove('bg-light');
        }, 500);
    }, function(err) {
        showToast('danger', '复制失败，请手动复制');
        console.error('复制失败:', err);
    });
}

function showToast(type, message) {
    const toastContainer = document.querySelector('.toast-container');
    const toastHtml = `
        <div class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;
    toastContainer.insertAdjacentHTML('beforeend', toastHtml);
    const newToast = toastContainer.lastElementChild;
    const bsToast = new bootstrap.Toast(newToast, { delay: 3000 });
    bsToast.show();
    newToast.addEventListener('hidden.bs.toast', function() {
        newToast.remove();
    });
}
</script>
</body>
</html>
