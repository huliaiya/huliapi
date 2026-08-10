<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
if (file_exists('../config.php')) { require_once '../config.php'; } else { die("出现错误！配置文件丢失。"); }
$username = htmlspecialchars($_SESSION['admin_username']);
$feedback_msg = ''; $feedback_type = '';
$status_map = [
    'active' => '正常',
    'banned' => '封禁',
    'inactive' => '未激活',
    'pending' => '待审核'
];
$level_map = [
    'normal' => '普通',
    'super'   => '超级'
];
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);   
    $columns = $pdo->query("SHOW COLUMNS FROM `sl_users`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('points', $columns)) {
        $pdo->exec("ALTER TABLE `sl_users` ADD `points` INT NOT NULL DEFAULT 0 AFTER `balance`;");
    }
    if (!in_array('membership_level', $columns)) {
        $pdo->exec("ALTER TABLE `sl_users` ADD `membership_level` VARCHAR(20) NOT NULL DEFAULT 'normal' AFTER `points`;");
    }
    if (!in_array('membership_expire', $columns)) {
        $pdo->exec("ALTER TABLE `sl_users` ADD `membership_expire` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `membership_level`;");
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['ids'])) {
        $ids = array_map('intval', $_POST['ids']);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        switch ($_POST['action']) {
            case 'enable':
                $stmt = $pdo->prepare("UPDATE sl_users SET status = 'active' WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                $_SESSION['feedback_msg'] = '已成功启用选中的用户。';
                break;
            case 'disable':
                $stmt = $pdo->prepare("UPDATE sl_users SET status = 'banned' WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                $_SESSION['feedback_msg'] = '已成功禁用选中的用户。';
                break;
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM sl_users WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                $_SESSION['feedback_msg'] = '已成功删除选中的用户。';
                break;
        }
        $_SESSION['feedback_type'] = 'success';
        header('Location: user_list.php');
        exit;
    }
    if (isset($_GET['action']) && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        switch ($_GET['action']) {
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM sl_users WHERE id = ?"); 
                $stmt->execute([$id]);
                $_SESSION['feedback_msg'] = '用户已成功删除。'; 
                break;
            case 'ban':
                $stmt = $pdo->prepare("UPDATE sl_users SET status = 'banned' WHERE id = ?"); 
                $stmt->execute([$id]);
                $_SESSION['feedback_msg'] = '用户已成功封禁。'; 
                break;
            case 'unban':
                $stmt = $pdo->prepare("UPDATE sl_users SET status = 'active' WHERE id = ?"); 
                $stmt->execute([$id]);
                $_SESSION['feedback_msg'] = '用户已成功解封。'; 
                break;
        }
        $_SESSION['feedback_type'] = 'success';
        header('Location: user_list.php'); 
        exit;
    }
    if(isset($_SESSION['feedback_msg'])){
        $feedback_msg = $_SESSION['feedback_msg']; 
        $feedback_type = $_SESSION['feedback_type'];
        unset($_SESSION['feedback_msg'], $_SESSION['feedback_type']);
    }
    $where = [];
    $params = [];
    if (!empty($_GET['username'])) {
        $where[] = "username LIKE ?";
        $params[] = '%' . $_GET['username'] . '%';
    }
    if (!empty($_GET['status'])) {
        $where[] = "status = ?";
        $params[] = $_GET['status'];
    }
    if (!empty($_GET['level'])) {
        $where[] = "membership_level = ?";
        $params[] = $_GET['level'];
    }
    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $results_per_page = 15;
    $total_results = $pdo->query("SELECT count(*) FROM sl_users $where_sql")->fetchColumn();
    $total_pages = ceil($total_results / $results_per_page);
    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
    $page = max(1, min($page, $total_pages));
    $offset = ($page - 1) * $results_per_page;
    $stmt_list = $pdo->prepare("SELECT * FROM sl_users $where_sql ORDER BY id DESC LIMIT :limit OFFSET :offset");
    if ($where_sql) {
        foreach ($params as $key => $value) {
            $stmt_list->bindValue($key + 1, $value);
        }
    }
    $stmt_list->bindValue(':limit', $results_per_page, PDO::PARAM_INT);
    $stmt_list->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt_list->execute();
    $users = $stmt_list->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { 
    $feedback_msg = '数据库操作失败: ' . $e->getMessage(); 
    $feedback_type = 'error'; 
    $users = []; 
}
$current_page_script = basename($_SERVER['PHP_SELF']);
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
    <style>
        .badge-active { background-color: #d1fae5; color: #065f46; }
        .badge-banned { background-color: #fee2e2; color: #991b1b; }
        .badge-inactive { background-color: #f3f4f6; color: #374151; }
        .badge-pending { background-color: #fef3c7; color: #92400e; }
        .badge-level-normal { background-color: #e5e7eb; color: #1f2937; }
        .badge-level-vip { background-color: #fef3c7; color: #92400e; }
        .badge-level-svip { background-color: #fce7f3; color: #9d174d; }
        .api-key { background-color: #f3f4f6; padding: 3px 6px; border-radius: 4px; font-family: monospace; }
        .form-check-input { position: relative; margin-left: 0; }
        .points-highlight { color: #1e40af; font-weight: 500; }
        .expire-permanent { color: #10b981; font-weight: 500; }
    </style>
</head>
<body>
<div class="container-fluid">
  <div class="row">
    <div class="col-lg-12">
      <div class="card">
        <header class="card-header">
            <div class="card-title">用户列表</div>
        </header>
        <div class="card-body">
          <div class="card-search mb-3">
            <form class="search-form" method="get" action="user_list.php" role="form">
              <div class="row g-2">
                <div class="col-md-4">
                  <div class="row">
                    <label class="col-sm-3 col-form-label">用户名</label>
                    <div class="col-sm-9">
                      <input type="text" class="form-control" name="username" value="<?php echo htmlspecialchars($_GET['username'] ?? ''); ?>" placeholder="请输入用户名" />
                    </div>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="row">
                    <label class="col-sm-4 col-form-label">状态</label>
                    <div class="col-sm-8">
                      <select name="status" class="form-select">
                        <option value="">全部</option>
                        <option value="active" <?php echo ($_GET['status'] ?? '') === 'active' ? 'selected' : ''; ?>>正常</option>
                        <option value="banned" <?php echo ($_GET['status'] ?? '') === 'banned' ? 'selected' : ''; ?>>封禁</option>
                        <option value="inactive" <?php echo ($_GET['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>未激活</option>
                        <option value="pending" <?php echo ($_GET['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>待审核</option>
                      </select>
                    </div>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="row">
                    <label class="col-sm-4 col-form-label">会员等级</label>
                    <div class="col-sm-8">
                      <select name="level" class="form-select">
                        <option value="">全部</option>
                        <option value="normal" <?php echo ($_GET['level'] ?? '') === 'normal' ? 'selected' : ''; ?>>普通</option>
                        <option value="vip" <?php echo ($_GET['level'] ?? '') === 'vip' ? 'selected' : ''; ?>>VIP</option>
                        <option value="svip" <?php echo ($_GET['level'] ?? '') === 'svip' ? 'selected' : ''; ?>>SVIP</option>
                      </select>
                    </div>
                  </div>
                </div>
                <div class="col-md-2">
                  <button type="submit" class="btn btn-primary me-1">搜索</button>
                  <button type="reset" class="btn btn-default">重置</button>
                </div>
              </div>
            </form>
          </div>
          <form id="batch-form" method="post" action="user_list.php">
            <div class="card-btns mb-3">
              <a class="btn btn-primary me-1" href="user_edit.php"><i class="mdi mdi-plus"></i> 添加新用户</a>
              <button type="submit" name="action" value="enable" class="btn btn-success me-1"><i class="mdi mdi-check"></i> 启用</button>
              <button type="submit" name="action" value="disable" class="btn btn-warning me-1"><i class="mdi mdi-block-helper"></i> 禁用</button>
              <button type="submit" name="action" value="delete" class="btn btn-danger" onclick="return confirm('确定要删除选中的用户吗？此操作不可恢复。');"><i class="mdi mdi-window-close"></i> 删除</button>
            </div>
            <div class="table-responsive">
              <table class="table table-bordered">
                <thead>
                  <tr>
                    <th width="50">
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="check-all">
                        <label class="form-check-label" for="check-all"></label>
                      </div>
                    </th>
                    <th>ID</th>
                    <th>用户名</th>
                    <th>邮箱</th>
                    <th>API Key</th>
                    <th>余额</th>
                    <th>点数</th>
                    <th>会员等级</th>
                    <th>会员有效期</th>
                    <th>状态</th>
                    <th width="150">操作</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($users)): ?>
                  <tr>
                    <td colspan="11" style="text-align:center; padding: 40px; color: #6b7280;">暂无用户数据。</td>
                  </tr>
                  <?php else: ?>
                  <?php foreach ($users as $user): ?>
                  <tr>
                    <td>
                      <div class="form-check">
                        <input type="checkbox" class="form-check-input ids" name="ids[]" value="<?php echo $user['id']; ?>" id="ids-<?php echo $user['id']; ?>">
                        <label class="form-check-label" for="ids-<?php echo $user['id']; ?>"></label>
                      </div>
                    </td>
                    <td><?php echo $user['id']; ?></td>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><span class="api-key"><?php echo htmlspecialchars($user['api_key']); ?></span></td>
                    <td>¥ <?php echo number_format($user['balance'], 3); ?></td>
                    <td><span class="points-highlight"><?php echo number_format($user['points']); ?></span></td>
                    <td>
                      <?php 
                        $level = $user['membership_level'] ?? 'normal';
                        $level_text = $level_map[$level] ?? $level;
                        $badge_class = '';
                        switch ($level) {
                            case 'vip': $badge_class = 'badge-level-vip'; break;
                            case 'svip': $badge_class = 'badge-level-svip'; break;
                            default: $badge_class = 'badge-level-normal';
                        }
                      ?>
                      <span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($level_text); ?></span>
                    </td>
                    <td>
                      <?php 
                        $expire = $user['membership_expire'] ?? 0;
                        if ($expire == 0) {
                            echo '<span class="expire-permanent">永久</span>';
                        } else {
                            echo date('Y-m-d H:i', $expire);
                            if ($expire < time()) {
                                echo ' <span class="badge badge-banned">已过期</span>';
                            }
                        }
                      ?>
                    </td>
                    <td>
                      <span class="badge <?php 
                        echo $user['status'] === 'active' ? 'badge-active' : 
                             ($user['status'] === 'banned' ? 'badge-banned' : 
                             ($user['status'] === 'pending' ? 'badge-pending' : 'badge-inactive')); 
                      ?>">
                        <?php echo $status_map[$user['status']] ?? $user['status']; ?>
                      </span>
                    </td>
                    <td>
                      <div class="btn-group btn-group-sm">
                        <a class="btn btn-default" href="user_edit.php?id=<?php echo $user['id']; ?>" data-bs-toggle="tooltip" title="编辑/调额/调整点数/等级"><i class="mdi mdi-pencil"></i></a>
                        <?php if ($user['status'] === 'active'): ?>
                          <a class="btn btn-warning" href="user_list.php?action=ban&id=<?php echo $user['id']; ?>" data-bs-toggle="tooltip" title="封禁"><i class="mdi mdi-block-helper"></i></a>
                        <?php else: ?>
                          <a class="btn btn-success" href="user_list.php?action=unban&id=<?php echo $user['id']; ?>" data-bs-toggle="tooltip" title="解封"><i class="mdi mdi-check"></i></a>
                        <?php endif; ?>
                        <a class="btn btn-danger" href="user_list.php?action=delete&id=<?php echo $user['id']; ?>" onclick="return confirm('确定要删除这个用户吗？此操作不可恢复。');" data-bs-toggle="tooltip" title="删除"><i class="mdi mdi-window-close"></i></a>
                      </div>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </form>
          <?php if ($total_pages > 1): ?>
          <ul class="pagination">
            <?php if ($page > 1): ?>
              <li class="page-item"><a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">上一页</a></li>
            <?php else: ?>
              <li class="page-item disabled"><span class="page-link">上一页</span></li>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
              <?php if ($i == $page): ?>
                <li class="page-item active"><span class="page-link"><?php echo $i; ?></span></li>
              <?php else: ?>
                <li class="page-item"><a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a></li>
              <?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
              <li class="page-item"><a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">下一页</a></li>
            <?php else: ?>
              <li class="page-item disabled"><span class="page-link">下一页</span></li>
            <?php endif; ?>
          </ul>
          <?php endif; ?>
          <?php if ($feedback_msg): ?>
          <div class="alert alert-<?php echo $feedback_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show mt-3">
            <?php echo htmlspecialchars($feedback_msg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<script type="text/javascript" src="../assets/js/jquery.min.js"></script>
<script type="text/javascript" src="../assets/js/popper.min.js"></script>
<script type="text/javascript" src="../assets/js/bootstrap.min.js"></script>
<script type="text/javascript" src="../assets/js/main.min.js"></script>
<script>
$(document).ready(function() {
    $('#check-all').change(function() {
        $('.ids').prop('checked', $(this).prop('checked'));
    });
    $('.ids').change(function() {
        if (!$(this).prop('checked')) {
            $('#check-all').prop('checked', false);
        } else {
            var allChecked = true;
            $('.ids').each(function() {
                if (!$(this).prop('checked')) {
                    allChecked = false;
                    return false;
                }
            });
            $('#check-all').prop('checked', allChecked);
        }
    });
    $('#batch-form').submit(function(e) {
          if ($('.ids:checked').length === 0) {
            alert('请至少选择一项进行操作！');
            e.preventDefault();
            return false;
        }
        if ($(this).find('button[type="submit"][name="action"][value="delete"]').is(':focus')) {
            return confirm('确定要删除选中的用户吗？此操作不可恢复。');
        }
        return true;
    });
    <?php if ($feedback_msg): ?>
    setTimeout(function() {
        $('.alert').alert('close');
    }, 3000);
    <?php endif; ?>
    $('[data-bs-toggle="tooltip"]').tooltip();
});
</script>
</body>
</html>