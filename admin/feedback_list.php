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
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if (isset($_GET['action']) && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        switch ($_GET['action']) {
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM sl_feedback WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['feedback_msg'] = '反馈已成功删除。';
                break;
            case 'update_status':
                $status = $_GET['status'] ?? 'viewed';
                if (!in_array($status, ['viewed', 'resolved'])) {
                    $status = 'viewed';
                }
                $stmt = $pdo->prepare("UPDATE sl_feedback SET status = ? WHERE id = ?");
                $stmt->execute([$status, $id]);
                $_SESSION['feedback_msg'] = '反馈状态已更新。';
                break;
            case 'reply':
                if (isset($_POST['response'])) {
                    $response = $_POST['response'];
                    $stmt = $pdo->prepare("UPDATE sl_feedback SET response = ?, status = 'resolved', responded_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $stmt->execute([$response, $id]);
                    $stmt_feedback = $pdo->prepare("SELECT f.*, u.email FROM sl_feedback f LEFT JOIN sl_users u ON f.user_id = u.id WHERE f.id = ?");
                    $stmt_feedback->execute([$id]);
                    $feedback = $stmt_feedback->fetch(PDO::FETCH_ASSOC);
                    if ($feedback && !empty($feedback['email'])) {
                        require_once '../common/mail.php';
                        $stmt_settings = $pdo->query("SELECT setting_key, setting_value FROM sl_settings");
                        $settings = [];
                        while ($row = $stmt_settings->fetch(PDO::FETCH_ASSOC)) {
                            $settings[$row['setting_key']] = $row['setting_value'];
                        }
                        $site_name = $settings['site_name'] ?? 'huliapi';
                        $logo_url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/assets/images/logo-sidebar.png';
                        $current_year = date('Y');
                        $subject = '【' . $site_name . '】您的反馈已收到回复';
                        $body = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 15px; background-color: #f0f3f8; font-family: \'PingFang SC\', \'Microsoft YaHei\', sans-serif;">
<div style="max-width: 600px; margin: 0 auto; width: 100%; background-color: #ffffff; border-radius: 16px; box-shadow: 0 4px 20px rgba(32,102,255,0.08);">
    <div style="padding: 30px 20px; text-align: center; background: linear-gradient(135deg, #2066ff 0%, #1955d4 100%); border-radius: 16px 16px 0 0;">
        <img style="max-height: 45px; width: auto; max-width: 100%;" src="' . $logo_url . '" alt="' . $site_name . '" />
    </div>
    <div style="padding: 30px 20px;">
        <h1 style="color: #2066ff; font-size: 24px; margin: 0 0 25px; text-align: center; font-weight: bold;">反馈回复通知</h1>
        <p style="color: #333333; font-size: 15px; line-height: 1.8; margin: 0; font-weight: 600;">尊敬的用户：</p>
        <p style="color: #333333; font-size: 15px; line-height: 1.8; margin: 10px 0; font-weight: 600;">您的反馈已收到回复，内容如下：</p>
        <div style="background: linear-gradient(to right, #f8f9ff, #f0f5ff); border-radius: 12px; padding: 20px; margin: 20px 0; border: 1px solid rgba(32,102,255,0.1);">
            <p style="color: #666666; font-size: 14px; line-height: 1.8; margin: 8px 0;"><span style="display: inline-block; width: 100px;">您的反馈：</span> ' . nl2br(htmlspecialchars($feedback['content'])) . '</p>
            <p style="color: #666666; font-size: 14px; line-height: 1.8; margin: 8px 0;"><span style="display: inline-block; width: 100px;">我们的回复：</span> ' . nl2br(htmlspecialchars($response)) . '</p>
        </div>
        <div style="background-color: #f8f9fa; border-radius: 8px; padding: 15px; margin: 20px 0;">
            <p style="color: #666666; font-size: 13px; line-height: 1.6; margin: 0; font-weight: 600;"><span style="color: #2066ff;">●</span> 感谢您的反馈，我们会持续改进服务</p>
            <p style="color: #666666; font-size: 13px; line-height: 1.6; margin: 8px 0 0; font-weight: 600;"><span style="color: #2066ff;">●</span> 如有其他问题，欢迎再次联系我们</p>
        </div>
        <p style="color: #666666; font-size: 13px; line-height: 1.6; margin: 20px 0 0; font-weight: 600;">如有任何问题，请联系客服支持。</p>
    </div>
    <div style="padding: 20px 15px; background-color: #f8f9fa; border-radius: 0 0 16px 16px; border-top: 1px solid #eef0f5;">
                        <p style="color: #999999; font-size: 13px; text-align: center; margin: 0; line-height: 1.8; font-weight: 500;">本邮件由系统自动发送，请勿直接回复<br />Copyright © 2025-' . $current_year . ' huliapi 版权所有</p>
    </div>
</div>
</body>
</html>';
                        send_mail($feedback['email'], $subject, $body, $pdo);
                    }
                    $_SESSION['feedback_msg'] = '回复已成功发送。';
                }
                break;
        }
        $_SESSION['feedback_type'] = 'success';
        header('Location: feedback_list.php');
        exit;
    }
    if (isset($_SESSION['feedback_msg'])) {
        $feedback_msg = $_SESSION['feedback_msg'];
        $feedback_type = $_SESSION['feedback_type'];
        unset($_SESSION['feedback_msg'], $_SESSION['feedback_type']);
    }
    $results_per_page = 15;
    $total_results = $pdo->query("SELECT count(*) FROM sl_feedback")->fetchColumn();
    $total_pages = ceil($total_results / $results_per_page);
    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
    $page = max(1, min($page, $total_pages));
    $offset = ($page - 1) * $results_per_page;
    $stmt_list = $pdo->prepare("SELECT f.*, u.username, a.name as api_name FROM sl_feedback f LEFT JOIN sl_users u ON f.user_id = u.id LEFT JOIN sl_apis a ON f.api_id = a.id ORDER BY f.created_at DESC LIMIT :limit OFFSET :offset");
    $stmt_list->bindValue(':limit', $results_per_page, PDO::PARAM_INT);
    $stmt_list->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt_list->execute();
    $feedbacks = $stmt_list->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $feedback_msg = '数据库操作失败: ' . $e->getMessage();
    $feedback_type = 'error';
    $feedbacks = [];
}

function getFeedbackStatusBadge($status) {
    switch ($status) {
        case 'pending': return '<span class="badge badge-yellow">待处理</span>';
        case 'viewed': return '<span class="badge badge-blue">已查看</span>';
        case 'resolved': return '<span class="badge badge-green">已解决</span>';
        default: return '<span class="badge badge-gray">未知</span>';
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
    <title>用户反馈 - huliapi</title>
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-touch-fullscreen" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="stylesheet" type="text/css" href="../assets/css/materialdesignicons.min.css">
    <link rel="stylesheet" type="text/css" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="../assets/css/style.min.css">
    <style>
        .content-cell {
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .badge-pending {
            background-color: #fef3c7;
            color: #92400e;
        }
        .badge-viewed {
            background-color: #dbeafe;
            color: #1e40af;
        }
        .badge-resolved {
            background-color: #dcfce7;
            color: #166534;
        }
    </style>
</head>
<body>
<div class="container-fluid">
  <div class="row">
    <div class="col-lg-12">
      <div class="card">
        <header class="card-header">
            <div class="card-title">用户反馈</div>
        </header>
        <div class="card-body">
          <?php if ($feedback_msg): ?>
          <div class="alert alert-<?php echo $feedback_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show mb-3">
            <?php echo htmlspecialchars($feedback_msg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
          <?php endif; ?>
          <div class="table-responsive">
            <table class="table table-bordered">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>反馈内容</th>
                  <th>类型</th>
                  <th>用户</th>
                  <th>联系方式</th>
                  <th>状态</th>
                  <th>操作</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($feedbacks)): ?>
                <tr>
                  <td colspan="7" class="text-center py-4 text-muted">暂无反馈记录</td>
                </tr>
                <?php else: ?>
                <?php foreach ($feedbacks as $item): ?>
                <tr>
                  <td><?php echo $item['id']; ?></td>
                  <td class="content-cell" title="<?php echo htmlspecialchars($item['content']); ?>">
                    <?php echo htmlspecialchars($item['content']); ?>
                  </td>
                  <td>
                    <?php if ($item['type'] === 'api'): ?>
                    <span class="badge bg-primary">接口问题</span>
                    <?php if ($item['api_name']): ?>
                    <small class="text-muted d-block"><?php echo htmlspecialchars($item['api_name']); ?></small>
                    <?php endif; ?>
                    <?php else: ?>
                    <span class="badge bg-info">意见建议</span>
                    <?php endif; ?>
                  </td>
                  <td><?php echo htmlspecialchars($item['username'] ?: '游客'); ?></td>
                  <td><?php echo htmlspecialchars($item['contact']); ?></td>
                  <td>
                    <?php if ($item['status'] === 'pending'): ?>
                      <span class="badge badge-pending">待处理</span>
                    <?php elseif ($item['status'] === 'viewed'): ?>
                      <span class="badge badge-viewed">已查看</span>
                    <?php else: ?>
                      <span class="badge badge-resolved">已解决</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="btn-group btn-group-sm">
                      <?php if ($item['status'] === 'pending'): ?>
                        <a class="btn btn-primary" href="?action=update_status&id=<?php echo $item['id']; ?>&status=viewed" data-bs-toggle="tooltip" title="标记为已查看">
                          <i class="mdi mdi-eye-check"></i>
                        </a>
                      <?php endif; ?>
                      <?php if ($item['status'] !== 'resolved'): ?>
                        <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#replyModal<?php echo $item['id']; ?>" data-bs-toggle="tooltip" title="回复">
                          <i class="mdi mdi-reply"></i>
                        </button>
                      <?php endif; ?>
                      <a class="btn btn-danger" href="?action=delete&id=<?php echo $item['id']; ?>" onclick="return confirm('确定要删除这条反馈吗？');" data-bs-toggle="tooltip" title="删除">
                        <i class="mdi mdi-delete"></i>
                      </a>
                    </div>
                    <div class="modal fade" id="replyModal<?php echo $item['id']; ?>" tabindex="-1" aria-labelledby="replyModalLabel<?php echo $item['id']; ?>" aria-hidden="true">
                      <div class="modal-dialog">
                        <div class="modal-content">
                          <div class="modal-header">
                            <h5 class="modal-title" id="replyModalLabel<?php echo $item['id']; ?>">回复反馈</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                          </div>
                          <form method="post" action="?action=reply&id=<?php echo $item['id']; ?>">
                            <div class="modal-body">
                              <div class="mb-3">
                                <label class="form-label">反馈内容</label>
                                <div class="form-control" style="min-height: 100px; background-color: #f8f9fa;">
                                  <?php echo htmlspecialchars($item['content']); ?>
                                </div>
                              </div>
                              <div class="mb-3">
                                <label for="response<?php echo $item['id']; ?>" class="form-label">回复内容</label>
                                <textarea class="form-control" id="response<?php echo $item['id']; ?>" name="response" rows="4" required></textarea>
                              </div>
                            </div>
                            <div class="modal-footer">
                              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                              <button type="submit" class="btn btn-primary">发送回复</button>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <?php if ($total_pages > 1): ?>
          <ul class="pagination justify-content-center mt-3">
            <?php if ($page > 1): ?>
              <li class="page-item">
                <a class="page-link" href="?page=<?php echo $page - 1; ?>">上一页</a>
              </li>
            <?php else: ?>
              <li class="page-item disabled">
                <span class="page-link">上一页</span>
              </li>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
              <?php if ($i == $page): ?>
                <li class="page-item active">
                  <span class="page-link"><?php echo $i; ?></span>
                </li>
              <?php else: ?>
                <li class="page-item">
                  <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                </li>
              <?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
              <li class="page-item">
                <a class="page-link" href="?page=<?php echo $page + 1; ?>">下一页</a>
              </li>
            <?php else: ?>
              <li class="page-item disabled">
                <span class="page-link">下一页</span>
              </li>
            <?php endif; ?>
          </ul>
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
$(function () {
  $('[data-bs-toggle="tooltip"]').tooltip();
  <?php if ($feedback_msg): ?>
  setTimeout(function() {
    $('.alert').alert('close');
  }, 3000);
  <?php endif; ?>
});
</script>
</body>
</html>
