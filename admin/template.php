<?php
@session_start();
@error_reporting(0);
@ini_set('display_errors', 'Off');
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
if (file_exists('../config.php')) { require_once '../config.php'; } else { die("出现错误！配置文件丢失。"); }
require_once '../common/TemplateManager.php';
$username = htmlspecialchars($_SESSION['admin_username']);
$feedback_msg = ''; $feedback_type = ''; $page_title = '模板管理中心';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['set_home_active'])) {
            $templateId = (int)$_POST['home_template_id'];
            if (TemplateManager::setActiveHomeTemplate($templateId)) {
                $feedback_msg = "首页模板切换成功！";
                $feedback_type = "success";
            } else {
                $feedback_msg = "首页模板切换失败！";
                $feedback_type = "error";
            }
        } 
        elseif (isset($_POST['set_user_active'])) {
            $templateId = (int)$_POST['user_template_id'];
            if (TemplateManager::setActiveUserTemplate($templateId)) {
                $feedback_msg = "用户中心模板切换成功！";
                $feedback_type = "success";
            } else {
                $feedback_msg = "用户中心模板切换失败！";
                $feedback_type = "error";
            }
        }
        elseif (isset($_POST['add_home_template'])) {
            $name = trim($_POST['home_name']);
            $folder = trim($_POST['home_folder']);
            $description = trim($_POST['home_description']);
            if (!empty($name) && !empty($folder)) {
                if (TemplateManager::addHomeTemplate($name, $folder, $description)) {
                    $feedback_msg = "首页模板添加成功！";
                    $feedback_type = "success";
                } else {
                    $feedback_msg = "首页模板添加失败！";
                    $feedback_type = "error";
                }
            } else {
                $feedback_msg = "模板名称和文件夹名不能为空！";
                $feedback_type = "error";
            }
        }
        elseif (isset($_POST['add_user_template'])) {
            $name = trim($_POST['user_name']);
            $folder = trim($_POST['user_folder']);
            $description = trim($_POST['user_description']);
            if (!empty($name) && !empty($folder)) {
                if (TemplateManager::addUserTemplate($name, $folder, $description)) {
                    $feedback_msg = "用户中心模板添加成功！";
                    $feedback_type = "success";
                } else {
                    $feedback_msg = "用户中心模板添加失败！";
                    $feedback_type = "error";
                }
            } else {
                $feedback_msg = "模板名称和文件夹名不能为空！";
                $feedback_type = "error";
            }
        }
    } catch (Exception $e) {
        $feedback_msg = "操作失败: " . $e->getMessage();
        $feedback_type = "error";
    }
}
if (isset($_GET['delete'])) {
    $type = $_GET['type'] ?? '';
    $templateId = (int)$_GET['delete'];
    try {
        if ($type === 'home') {
            $success = TemplateManager::deleteHomeTemplate($templateId);
            $msg = "首页模板删除";
        } elseif ($type === 'user') {
            $success = TemplateManager::deleteUserTemplate($templateId);
            $msg = "用户中心模板删除";
        } else {
            $success = false;
        }
        if ($success) {
            $feedback_msg = $msg . "成功！";
            $feedback_type = "success";
        } else {
            $feedback_msg = $msg . "失败！";
            $feedback_type = "error";
        }
    } catch (Exception $e) {
        $feedback_msg = "操作失败: " . $e->getMessage();
        $feedback_type = "error";
    }
}
$homeTemplates = TemplateManager::getAllHomeTemplates();
$userTemplates = TemplateManager::getAllUserTemplates();
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
        .template-card {
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        .template-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .template-card.active {
            border-color: #7367F0;
        }
        .template-thumbnail {
            height: 180px;
            background-color: #f8f8f8;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .template-thumbnail img {
            max-width: 100%;
            max-height: 100%;
        }
        .badge-home {
            background-color: #28a745;
        }
        .badge-user {
            background-color: #17a2b8;
        }
    </style>
</head>
<body>
<div class="container-fluid">
  <div class="row">
    <div class="col-lg-12">
      <div class="card">
        <header class="card-header">
          <div class="card-title">模板管理中心</div>
          <small class="text-muted">管理网站首页和用户中心模板</small>
        </header>
        <div class="card-body">
          <?php if ($feedback_msg): ?>
          <div class="alert alert-<?php echo $feedback_type === 'success' ? 'success' : 'danger'; ?> mb-3">
            <?php echo htmlspecialchars($feedback_msg); ?>
          </div>
          <?php endif; ?>
          <ul class="nav nav-tabs" id="templateTabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" id="home-tab" data-bs-toggle="tab" data-bs-target="#home-templates" type="button" role="tab">首页模板</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="user-tab" data-bs-toggle="tab" data-bs-target="#user-templates" type="button" role="tab">用户中心模板</button>
            </li>
          </ul>
          <div class="tab-content" id="templateTabsContent">
            <div class="tab-pane fade show active" id="home-templates" role="tabpanel" aria-labelledby="home-tab">
              <div class="row mb-4 mt-3">
                <div class="col-md-6">
                  <h5>当前激活的首页模板</h5>
                  <form method="POST" class="mb-4">
                    <div class="input-group">
                      <select class="form-select" name="home_template_id" required>
                        <?php foreach ($homeTemplates as $template): ?>
                          <option value="<?php echo $template['id']; ?>" <?php echo $template['is_active'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($template['name']); ?> (<?php echo htmlspecialchars($template['folder']); ?>)
                          </option>
                        <?php endforeach; ?>
                      </select>
                      <button type="submit" name="set_home_active" class="btn btn-primary">应用</button>
                    </div>
                  </form>
                </div>
                <div class="col-md-6">
                  <h5>添加新首页模板</h5>
                  <form method="POST">
                    <div class="row">
                      <div class="col-md-6 mb-3">
                        <label class="form-label">模板名称</label>
                        <input type="text" class="form-control" name="home_name" required>
                      </div>
                      <div class="col-md-6 mb-3">
                        <label class="form-label">模板文件夹名</label>
                        <input type="text" class="form-control" name="home_folder" required>
                        <small class="text-muted">必须与template目录下的文件夹名一致</small>
                      </div>
                    </div>
                    <div class="mb-3">
                      <label class="form-label">模板描述</label>
                      <textarea class="form-control" name="home_description" rows="2"></textarea>
                    </div>
                    <button type="submit" name="add_home_template" class="btn btn-success">添加模板</button>
                  </form>
                </div>
              </div>
              <h5 class="mb-3">首页模板列表</h5>
              <div class="row">
                <?php foreach ($homeTemplates as $template): ?>
                  <div class="col-md-4 mb-4">
                    <div class="card template-card <?php echo $template['is_active'] ? 'active' : ''; ?>">
                      <div class="template-thumbnail">
                        <?php if (file_exists("../template/home/{$template['folder']}/preview.jpg")): ?>
                          <img src="../template/home/<?php echo $template['folder']; ?>/preview.jpg" alt="<?php echo $template['name']; ?>预览图">
                        <?php else: ?>
                          <i class="mdi mdi-image-off" style="font-size: 3rem; color: #ccc;"></i>
                        <?php endif; ?>
                      </div>
                      <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($template['name']); ?></h5>
                        <p class="card-text text-muted"><?php echo htmlspecialchars($template['description']); ?></p>
                        <div class="d-flex justify-content-between align-items-center">
                          <span class="badge badge-home">
                            <?php echo $template['is_active'] ? '已启用' : '未启用'; ?>
                          </span>
                          <div>
                            <?php if (!$template['is_active']): ?>
                              <a href="?type=home&delete=<?php echo $template['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('确定要删除此首页模板吗？')">删除</a>
                            <?php endif; ?>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="tab-pane fade" id="user-templates" role="tabpanel" aria-labelledby="user-tab">
              <div class="row mb-4 mt-3">
                <div class="col-md-6">
                  <h5>当前激活的用户中心模板</h5>
                  <form method="POST" class="mb-4">
                    <div class="input-group">
                      <select class="form-select" name="user_template_id" required>
                        <?php foreach ($userTemplates as $template): ?>
                          <option value="<?php echo $template['id']; ?>" <?php echo $template['is_active'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($template['name']); ?> (<?php echo htmlspecialchars($template['folder']); ?>)
                          </option>
                        <?php endforeach; ?>
                      </select>
                      <button type="submit" name="set_user_active" class="btn btn-primary">应用</button>
                    </div>
                  </form>
                </div>
                <div class="col-md-6">
                  <h5>添加新用户中心模板</h5>
                  <form method="POST">
                    <div class="row">
                      <div class="col-md-6 mb-3">
                        <label class="form-label">模板名称</label>
                        <input type="text" class="form-control" name="user_name" required>
                      </div>
                      <div class="col-md-6 mb-3">
                        <label class="form-label">模板文件夹名</label>
                        <input type="text" class="form-control" name="user_folder" required>
                        <small class="text-muted">必须与template目录下的文件夹名一致</small>
                      </div>
                    </div>
                    <div class="mb-3">
                      <label class="form-label">模板描述</label>
                      <textarea class="form-control" name="user_description" rows="2"></textarea>
                    </div>
                    <button type="submit" name="add_user_template" class="btn btn-success">添加模板</button>
                  </form>
                </div>
              </div>
              <h5 class="mb-3">用户中心模板列表</h5>
              <div class="row">
                <?php foreach ($userTemplates as $template): ?>
                  <div class="col-md-4 mb-4">
                    <div class="card template-card <?php echo $template['is_active'] ? 'active' : ''; ?>">
                      <div class="template-thumbnail">
                        <?php if (file_exists("../template/user/{$template['folder']}/preview.jpg")): ?>
                          <img src="../template/user/<?php echo $template['folder']; ?>/preview.jpg" alt="<?php echo $template['name']; ?>预览图">
                        <?php else: ?>
                          <i class="mdi mdi-image-off" style="font-size: 3rem; color: #ccc;"></i>
                        <?php endif; ?>
                      </div>
                      <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($template['name']); ?></h5>
                        <p class="card-text text-muted"><?php echo htmlspecialchars($template['description']); ?></p>
                        <div class="d-flex justify-content-between align-items-center">
                          <span class="badge badge-user">
                            <?php echo $template['is_active'] ? '已启用' : '未启用'; ?>
                          </span>
                          <div>
                            <?php if (!$template['is_active']): ?>
                              <a href="?type=user&delete=<?php echo $template['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('确定要删除此用户中心模板吗？')">删除</a>
                            <?php endif; ?>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
          <div class="alert alert-info mt-4">
            <h6><i class="mdi mdi-information-outline"></i> 使用说明</h6>
            <ol class="mb-0">
              <li>在网站根目录的<code>template</code>文件夹下创建模板文件夹</li>
              <li>区分首页模板和用户中心模板文件夹</li>
              <li>在此页面添加对应的模板信息</li>
              <li>选择并应用模板</li>
              <li>可以在模板文件夹中添加<code>preview.jpg</code>作为预览图</li>
            </ol>
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
    $('.btn-danger').on('click', function(e) {
        if (!confirm('确定要删除此模板吗？')) {
            e.preventDefault();
        }
    });
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab');
    if (tab) {
        const tabElement = $('#' + tab + '-tab');
        if (tabElement.length) {
            new bootstrap.Tab(tabElement).show();
        }
    }
});
</script>
</body>
</html>