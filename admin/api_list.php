<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}
if (file_exists('../config.php')) {
    require_once '../config.php';
} else {
    die("出现错误！配置文件丢失。");
}
$username = htmlspecialchars($_SESSION['admin_username']);
$feedback_msg = '';
$feedback_type = '';
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    try {
        $id = intval($_GET['id']);
        if ($id <= 0) {
            throw new InvalidArgumentException('无效的接口ID');
        }
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $pdo->prepare("SELECT file_path FROM sl_apis WHERE id = ?");
        $stmt->execute([$id]);
        $api = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$api) {
            throw new Exception('接口不存在');
        }
        if (!empty($api['file_path'])) {
            $safe_path = realpath('../' . $api['file_path']);
            $base_dir = realpath('../API');
            if ($safe_path && $base_dir && strpos($safe_path, $base_dir) === 0 && file_exists($safe_path)) {
                if (!unlink($safe_path)) {
                    throw new Exception('无法删除接口文件');
                }
            }
        }
        $stmt = $pdo->prepare("DELETE FROM sl_apis WHERE id = ?");
        $result = $stmt->execute([$id]);
        if (!$result || $stmt->rowCount() === 0) {
            throw new Exception('数据库记录删除失败');
        }
        $_SESSION['feedback_msg'] = '接口已成功删除';
        $_SESSION['feedback_type'] = 'success';
    } catch (InvalidArgumentException $e) {
        $_SESSION['feedback_msg'] = '删除失败: 参数无效';
        $_SESSION['feedback_type'] = 'error';
    } catch (Exception $e) {
        $_SESSION['feedback_msg'] = '删除失败: ' . $e->getMessage();
        $_SESSION['feedback_type'] = 'error';
    }
    header('Location: api_list.php');
    exit;
}
$pdo = null;
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 10
        ]
    );
    if (isset($_POST['batch_action']) && isset($_POST['ids'])) {
        $ids = array_map('intval', $_POST['ids']);
        $ids = array_filter($ids, function($id) {
            return $id > 0;
        });
        if (empty($ids)) {
            throw new Exception('未选择任何有效接口');
        }
        switch ($_POST['batch_action']) {
            case 'activate':
                $stmt = $pdo->prepare("UPDATE sl_apis SET status = 'normal' WHERE id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")");
                $stmt->execute($ids);
                $_SESSION['feedback_msg'] = '已启用选中的 ' . $stmt->rowCount() . ' 个接口';
                break;
            case 'deactivate':
                $stmt = $pdo->prepare("UPDATE sl_apis SET status = 'error' WHERE id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")");
                $stmt->execute($ids);
                $_SESSION['feedback_msg'] = '已禁用选中的 ' . $stmt->rowCount() . ' 个接口';
                break;
            case 'delete':
                $stmt = $pdo->prepare("SELECT id, file_path FROM sl_apis WHERE id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")");
                $stmt->execute($ids);
                $apisToDelete = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $fileErrors = [];
                foreach ($apisToDelete as $api) {
                    if (!empty($api['file_path'])) {
                        $safe_path = realpath('../' . $api['file_path']);
                        $base_dir = realpath('../API');
                        if ($safe_path && $base_dir && strpos($safe_path, $base_dir) === 0 && file_exists($safe_path)) {
                            if (!unlink($safe_path)) {
                                $fileErrors[] = $api['id'];
                            }
                        }
                    }
                }
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = $pdo->prepare("DELETE FROM sl_apis WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                $deletedCount = $stmt->rowCount();
                $msg = "已删除选中的 $deletedCount 个接口";
                if (!empty($fileErrors)) {
                    $msg .= "，但部分接口文件删除失败（ID: " . implode(',', $fileErrors) . "）";
                }
                $_SESSION['feedback_msg'] = $msg;
                break;
            default:
                throw new Exception('未知的批量操作');
        }
        $_SESSION['feedback_type'] = 'success';
        header('Location: api_list.php');
        exit;
    }
    $searchParams = [];
    $whereClause = [];
    if (isset($_GET['name']) && !empty($_GET['name'])) {
        $searchParams[':name'] = '%' . $_GET['name'] . '%';
        $whereClause[] = "name LIKE :name";
    }
    if (isset($_GET['type']) && !empty($_GET['type'])) {
        $validTypes = ['local', 'remote'];
        if (in_array($_GET['type'], $validTypes)) {
            $searchParams[':type'] = $_GET['type'];
            $whereClause[] = "type = :type";
        } else {
            throw new Exception('无效的接口类型筛选条件');
        }
    }
    if (isset($_GET['status']) && !empty($_GET['status'])) {
        $validStatuses = ['normal', 'error', 'deprecated', 'maintenance'];
        if (in_array($_GET['status'], $validStatuses)) {
            $searchParams[':status'] = $_GET['status'];
            $whereClause[] = "status = :status";
        } else {
            throw new Exception('无效的接口状态筛选条件');
        }
    }
    if (isset($_GET['permission']) && !empty($_GET['permission'])) {
        $validPermissions = ['public', 'private', 'balance', 'points'];
        if (in_array($_GET['permission'], $validPermissions)) {
            $whereClause[] = "visibility = :visibility";
            $searchParams[':visibility'] = $_GET['permission'];
        } else {
            throw new Exception('无效的接口权限筛选条件');
        }
    }
    if (isset($_GET['category']) && !empty($_GET['category'])) {
        $categoryId = intval($_GET['category']);
        if ($categoryId > 0) {
            $searchParams[':category'] = $categoryId;
            $whereClause[] = "category_id = :category";
        } else {
            throw new Exception('无效的分类筛选条件');
        }
    }
    $recordsPerPage = 10;
    $currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $offset = ($currentPage - 1) * $recordsPerPage;
    $whereString = !empty($whereClause) ? "WHERE " . implode(" AND ", $whereClause) : "";
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM sl_apis $whereString");
    $countStmt->execute($searchParams);
    $totalRecords = $countStmt->fetchColumn();
    $totalPages = max(1, ceil($totalRecords / $recordsPerPage));
    if ($currentPage > $totalPages) {
        $currentPage = $totalPages;
        $offset = ($currentPage - 1) * $recordsPerPage;
    }
    $stmt_list = $pdo->prepare("SELECT * FROM sl_apis $whereString ORDER BY id DESC LIMIT :offset, :limit");
    $stmt_list->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt_list->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
    foreach ($searchParams as $key => $value) {
        $stmt_list->bindValue($key, $value);
    }
    $stmt_list->execute();
    $apis = $stmt_list->fetchAll(PDO::FETCH_ASSOC);
    if (isset($_SESSION['feedback_msg'])) {
        $feedback_msg = $_SESSION['feedback_msg'];
        $feedback_type = $_SESSION['feedback_type'];
        unset($_SESSION['feedback_msg'], $_SESSION['feedback_type']);
    }
} catch (PDOException $e) {
    $feedback_msg = '数据库操作失败，请联系管理员';
    $feedback_type = 'error';
    $apis = [];
    $totalPages = 1;
    $pdo = null;
} catch (Exception $e) {
    $feedback_msg = $e->getMessage();
    $feedback_type = 'error';
    $apis = [];
    $totalPages = 1;
}

function getPermissionInfo($visibility, $price_per_call, $points_per_call)
{
    $info = [
        'text' => '',
        'class' => '',
        'type' => $visibility
    ];
    $price_per_call = is_numeric($price_per_call) ? $price_per_call : 0;
    $points_per_call = is_numeric($points_per_call) ? $points_per_call : 0;
    switch ($visibility) {
        case 'public':
            $info['text'] = '公开访问';
            $info['class'] = 'bg-emerald-100 text-emerald-800';
            break;
        case 'private':
            $info['text'] = '仅密钥访问';
            $info['class'] = 'bg-blue-100 text-blue-800';
            break;
        case 'balance':
            $info['text'] = '密钥+余额: ¥' . number_format($price_per_call, 2) . '/次';
            $info['class'] = 'bg-indigo-100 text-indigo-800';
            break;
        case 'points':
            $info['text'] = '密钥+点数: ' . $points_per_call . '点/次';
            $info['class'] = 'bg-amber-100 text-amber-800';
            break;
        default:
            $info['text'] = '未知权限';
            $info['class'] = 'bg-gray-100 text-gray-800';
    }
    return $info;
}

function getStatusBadge($status)
{
    switch ($status) {
        case 'normal':
            return '<span class="badge bg-emerald-100 text-emerald-800">正常</span>';
        case 'error':
            return '<span class="badge bg-rose-100 text-rose-800">异常</span>';
        case 'maintenance':
            return '<span class="badge bg-sky-100 text-sky-800">维护</span>';
        case 'deprecated':
            return '<span class="badge bg-gray-100 text-gray-800">失效</span>';
        default:
            return '<span class="badge bg-gray-100 text-gray-800">未知</span>';
    }
}

function getCategoryName($pdo, $categoryId)
{
    if (!$categoryId || !$pdo) {
        return '<span class="text-muted">无分类</span>';
    }
    try {
        $stmt_cat = $pdo->prepare("SELECT name FROM sl_api_categories WHERE id = ?");
        $stmt_cat->execute([$categoryId]);
        $category_name = $stmt_cat->fetchColumn();
        return htmlspecialchars($category_name ?: '未知分类');
    } catch (Exception $e) {
        return '<span class="text-danger">获取失败</span>';
    }
}
$current_page = basename($_SERVER['PHP_SELF']);
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
.badge {
    font-weight: 500;
    padding: 0.15rem 0.4rem;
    border-radius: 4px;
    display: inline-block;
    white-space: nowrap;
    font-size: 12px;
    line-height: 1.4;
}
.bg-emerald-100 {
    background-color: #dcfce7;
}
.text-emerald-800 {
    color: #166534;
}
.bg-blue-100 {
    background-color: #dbeafe;
}
.text-blue-800 {
    color: #1e3a8a;
}
.bg-indigo-100 {
    background-color: #e0e7ff;
}
.text-indigo-800 {
    color: #3730a3;
}
.bg-amber-100 {
    background-color: #fef3c7;
}
.text-amber-800 {
    color: #7c2d12;
}
.bg-rose-100 {
    background-color: #fee2e2;
}
.text-rose-800 {
    color: #991b1b;
}
.bg-sky-100 {
    background-color: #e0f2fe;
}
.text-sky-800 {
    color: #0369a1;
}
.bg-gray-100 {
    background-color: #f3f4f6;
}
.text-gray-800 {
    color: #1f2937;
}
.alert-error-details {
    font-size: 0.875rem;
    margin-top: 0.5rem;
    padding: 0.5rem;
    background-color: rgba(220, 38, 38, 0.1);
    border-radius: 0.25rem;
}
.table td,
.table th {
    padding: 0.5rem 0.75rem;
    vertical-align: middle;
}
</style>
</head>
<body>
<div class="container-fluid">
  <div class="row">
    <div class="col-lg-12">
      <div class="card">
        <header class="card-header">
          <div class="card-title">API 接口列表</div>
          <div class="card-action">
            <a href="api_edit.php" class="btn btn-primary btn-sm"><i class="mdi mdi-plus"></i> 添加新接口</a>
          </div>
        </header>
        <div class="card-body">
          <?php if ($feedback_msg): ?>
          <div class="alert alert-<?php echo $feedback_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show mb-3">
            <?php echo htmlspecialchars($feedback_msg); ?>
            <?php if ($feedback_type === 'error' && isset($errorDetails)): ?>
            <div class="alert-error-details">
              错误详情: <?php echo htmlspecialchars($errorDetails); ?>
            </div>
            <?php endif; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
          <?php endif; ?>
          <div class="card-search mb-3">
            <form class="search-form" method="get" action="api_list.php" role="form">
              <div class="row">
                <div class="col-md-3">
                  <div class="row">
                    <label class="col-sm-4 col-form-label">接口名称</label>
                    <div class="col-sm-8">
                      <input type="text" class="form-control" name="name" value="<?php echo isset($_GET['name']) ? htmlspecialchars($_GET['name']) : ''; ?>" placeholder="请输入接口名称" />
                    </div>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="row">
                    <label class="col-sm-4 col-form-label">接口类型</label>
                    <div class="col-sm-8">
                      <select name="type" class="form-select">
                        <option value="" <?php echo !isset($_GET['type']) || empty($_GET['type']) ? 'selected' : ''; ?>>全部</option>
                        <option value="local" <?php echo isset($_GET['type']) && $_GET['type'] === 'local' ? 'selected' : ''; ?>>本地接口</option>
                        <option value="remote" <?php echo isset($_GET['type']) && $_GET['type'] === 'remote' ? 'selected' : ''; ?>>远程接口</option>
                      </select>
                    </div>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="row">
                    <label class="col-sm-4 col-form-label">接口状态</label>
                    <div class="col-sm-8">
                      <select name="status" class="form-select">
                        <option value="" <?php echo !isset($_GET['status']) || empty($_GET['status']) ? 'selected' : ''; ?>>全部</option>
                        <option value="normal" <?php echo isset($_GET['status']) && $_GET['status'] === 'normal' ? 'selected' : ''; ?>>正常</option>
                        <option value="error" <?php echo isset($_GET['status']) && $_GET['status'] === 'error' ? 'selected' : ''; ?>>异常</option>
                        <option value="deprecated" <?php echo isset($_GET['status']) && $_GET['status'] === 'deprecated' ? 'selected' : ''; ?>>失效</option>
                        <option value="maintenance" <?php echo isset($_GET['status']) && $_GET['status'] === 'maintenance' ? 'selected' : ''; ?>>维护中</option>
                      </select>
                    </div>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="row">
                    <label class="col-sm-4 col-form-label">接口权限</label>
                    <div class="col-sm-8">
                      <select name="permission" class="form-select">
                        <option value="" <?php echo !isset($_GET['permission']) || empty($_GET['permission']) ? 'selected' : ''; ?>>全部</option>
                        <option value="public" <?php echo isset($_GET['permission']) && $_GET['permission'] === 'public' ? 'selected' : ''; ?>>公开访问</option>
                        <option value="private" <?php echo isset($_GET['permission']) && $_GET['permission'] === 'private' ? 'selected' : ''; ?>>仅密钥访问</option>
                        <option value="balance" <?php echo isset($_GET['permission']) && $_GET['permission'] === 'balance' ? 'selected' : ''; ?>>密钥+余额计费</option>
                        <option value="points" <?php echo isset($_GET['permission']) && $_GET['permission'] === 'points' ? 'selected' : ''; ?>>密钥+点数计费</option>
                      </select>
                    </div>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="row">
                    <label class="col-sm-4 col-form-label">接口分类</label>
                    <div class="col-sm-8">
                      <select name="category" class="form-select">
                        <option value="" <?php echo !isset($_GET['category']) || empty($_GET['category']) ? 'selected' : ''; ?>>全部</option>
                        <?php
                        if ($pdo) {
                            try {
                                $stmt_cats = $pdo->query("SELECT * FROM sl_api_categories ORDER BY name");
                                while ($cat = $stmt_cats->fetch(PDO::FETCH_ASSOC)): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo isset($_GET['category']) && $_GET['category'] == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                        <?php
                                endwhile;
                            } catch (Exception $e) {
                                echo '<option value="" disabled>无法获取分类</option>';
                            }
                        } else {
                            echo '<option value="" disabled>数据库连接失败</option>';
                        }
                        ?>
                      </select>
                    </div>
                  </div>
                </div>
              </div>
              <div class="row mt-2">
                <div class="col-md-12 d-flex justify-content-between">
                  <div class="btn-group">
                    <button type="button" class="btn btn-success btn-sm me-1" id="batch-activate">
                      <i class="mdi mdi-check"></i> 启用
                    </button>
                    <button type="button" class="btn btn-warning btn-sm me-1" id="batch-deactivate">
                      <i class="mdi mdi-block-helper"></i> 禁用
                    </button>
                    <button type="button" class="btn btn-danger btn-sm" id="batch-delete">
                      <i class="mdi mdi-delete"></i> 删除
                    </button>
                  </div>
                  <div class="btn-group">
                    <button type="submit" class="btn btn-primary btn-sm me-1">搜索</button>
                    <button type="reset" id="reset-form" class="btn btn-default btn-sm">重置</button>
                  </div>
                </div>
              </div>
            </form>
          </div>
          <div class="table-responsive">
            <form id="batch-form" method="post" action="api_list.php">
              <input type="hidden" name="batch_action" id="batch-action-input">
              <table class="table table-bordered">
                <thead>
                  <tr>
                    <th width="40">
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="check-all">
                        <label class="form-check-label" for="check-all"></label>
                      </div>
                    </th>
                    <th>接口名称</th>
                    <th>调用地址</th>
                    <th width="100">类型</th>
                    <th width="160">权限详情</th>
                    <th width="100">状态</th>
                    <th width="100">接口分类</th>
                    <th width="120">调用次数</th>
                    <th width="120">操作</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($apis)): ?>
                  <tr>
                    <td colspan="9" class="text-center py-4 text-muted">
                      <i class="mdi mdi-information-outline me-1"></i> 暂无数据，请先添加一个接口
                    </td>
                  </tr>
                  <?php else: ?>
                    <?php foreach ($apis as $api):
                        $api = array_merge([
                            'visibility' => 'private',
                            'price_per_call' => 0,
                            'points_per_call' => 0,
                            'total_calls' => 0
                        ], $api);
                        $permission = getPermissionInfo(
                            $api['visibility'],
                            $api['price_per_call'],
                            $api['points_per_call']
                        );
                    ?>
                    <tr>
                      <td>
                        <div class="form-check">
                          <input type="checkbox" class="form-check-input ids" name="ids[]" value="<?php echo $api['id']; ?>" id="ids-<?php echo $api['id']; ?>">
                          <label class="form-check-label" for="ids-<?php echo $api['id']; ?>"></label>
                        </div>
                      </td>
                      <td><?php echo htmlspecialchars($api['name']); ?></td>
                      <td><code>/API/<?php echo htmlspecialchars(rawurldecode($api['endpoint'])); ?>.php</code></td>
                      <td>
                        <span class="badge bg-primary bg-opacity-10 text-primary">
                          <?php echo $api['type'] === 'local' ? '本地' : '远程'; ?>
                        </span>
                      </td>
                      <td>
                        <span class="badge <?php echo $permission['class']; ?>">
                          <?php echo $permission['text']; ?>
                        </span>
                      </td>
                      <td>
                        <?php echo getStatusBadge($api['status']); ?>
                      </td>
                      <td>
                        <?php echo getCategoryName($pdo, $api['category_id']); ?>
                      </td>
                      <td><?php echo number_format($api['total_calls']); ?></td>
                      <td>
                        <div class="btn-group btn-group-sm">
                          <a href="api_edit.php?id=<?php echo $api['id']; ?>" class="btn btn-default" data-bs-toggle="tooltip" title="编辑">
                            <i class="mdi mdi-pencil"></i>
                          </a>
                          <a href="javascript:void(0);"
                             class="btn btn-default delete-btn"
                             data-id="<?php echo $api['id']; ?>"
                             data-name="<?php echo htmlspecialchars($api['name']); ?>"
                             data-bs-toggle="tooltip"
                             title="删除">
                            <i class="mdi mdi-delete"></i>
                          </a>
                        </div>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </form>
            <div class="col-md-6">
              <nav class="float-end">
                <ul class="pagination">
                  <?php if ($currentPage > 1): ?>
                    <li class="page-item">
                      <a class="page-link" href="api_list.php?<?php echo http_build_query(array_merge($_GET, ['page' => $currentPage - 1])); ?>" aria-label="上一页">
                        <span aria-hidden="true">&laquo;</span>
                      </a>
                    </li>
                  <?php else: ?>
                    <li class="page-item disabled">
                      <span class="page-link" aria-label="上一页">
                        <span aria-hidden="true">&laquo;</span>
                      </span>
                    </li>
                  <?php endif; ?>
                  <?php
                  $visiblePages = 5;
                  $startPage = max(1, $currentPage - floor($visiblePages / 2));
                  $endPage = min($startPage + $visiblePages - 1, $totalPages);
                  if ($startPage > 1) {
                      echo '<li class="page-item"><a class="page-link" href="api_list.php?' . http_build_query(array_merge($_GET, ['page' => 1])) . '">1</a></li>';
                      if ($startPage > 2) {
                          echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                      }
                  }
                  for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <li class="page-item <?php echo $i == $currentPage ? 'active' : ''; ?>">
                      <a class="page-link" href="api_list.php?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                    </li>
                  <?php endfor; ?>
                  <?php if ($endPage < $totalPages): ?>
                    <?php if ($endPage < $totalPages - 1): ?>
                      <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                    <li class="page-item"><a class="page-link" href="api_list.php?<?php echo http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>"><?php echo $totalPages; ?></a></li>
                  <?php endif; ?>
                  <?php if ($currentPage < $totalPages): ?>
                    <li class="page-item">
                      <a class="page-link" href="api_list.php?<?php echo http_build_query(array_merge($_GET, ['page' => $currentPage + 1])); ?>" aria-label="下一页">
                        <span aria-hidden="true">&raquo;</span>
                      </a>
                    </li>
                  <?php else: ?>
                    <li class="page-item disabled">
                      <span class="page-link" aria-label="下一页">
                        <span aria-hidden="true">&raquo;</span>
                      </span>
                    </li>
                  <?php endif; ?>
                </ul>
              </nav>
            </div>
          </div>
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
  try {
    $('[data-bs-toggle="tooltip"]').tooltip();
  } catch (e) {
    console.error('初始化工具提示失败:', e);
  }
  $('#check-all').change(function() {
    $('.ids').prop('checked', $(this).prop('checked'));
  });
  $('.ids').change(function() {
    if (!$(this).prop('checked')) {
      $('#check-all').prop('checked', false);
    }
  });
  <?php if ($feedback_msg): ?>
  setTimeout(function() {
    $('.alert').fadeTo(500, 0).slideUp(500, function() {
      $(this).remove();
    });
  }, 3000);
  <?php endif; ?>
  $('#batch-activate').click(function() {
    batchAction('activate', '启用');
  });
  $('#batch-deactivate').click(function() {
    batchAction('deactivate', '禁用');
  });
  $('#batch-delete').click(function() {
    batchAction('delete', '删除');
  });
  $('.delete-btn').click(function() {
    const id = $(this).data('id');
    const name = $(this).data('name');
    if (id <= 0) {
      alert('无效的接口ID');
      return;
    }
    if (confirm(`确定要删除接口"${name}"吗？此操作不可恢复，并将删除服务器上的对应文件。`)) {
      try {
        const searchParams = new URLSearchParams(window.location.search);
        searchParams.set('action', 'delete');
        searchParams.set('id', id);
        window.location.href = `api_list.php?${searchParams.toString()}`;
      } catch (e) {
        console.error('构建删除URL失败:', e);
        alert('操作失败，请重试');
      }
    }
  });

  function batchAction(action, actionName) {
    const selectedIds = $('.ids:checked').map(function() {
      return $(this).val();
    }).get().filter(id => id > 0);
    if (selectedIds.length === 0) {
      alert('请至少选择一个接口');
      return false;
    }
    let confirmMsg = `确定要${actionName}选中的 ${selectedIds.length} 个接口吗？`;
    if (action === 'delete') {
      confirmMsg += '此操作不可恢复！';
    }
    if (confirm(confirmMsg)) {
      $('#batch-action-input').val(action);
      $('#batch-form').submit();
    }
  }
  $('#reset-form').click(function(e) {
    e.preventDefault();
    window.location.href = 'api_list.php';
  });
});
</script>
</body>
</html>