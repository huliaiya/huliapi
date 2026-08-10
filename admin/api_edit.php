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
$admin_id = $_SESSION['admin_id'];
$feedback_msg = ''; 
$feedback_type = ''; 
$page_title = '添加新接口';
$api = [
    'id' => null, 
    'name' => '', 
    'description' => '', 
    'endpoint' => '', 
    'type' => 'local', 
    'status' => 'normal',
    'visibility' => 'public', 
    'is_billable' => 0, 
    'price_per_call' => '0.01', 
    'points_per_call' => 1, 
    'remote_url' => '', 
    'method' => 'GET', 
    'file_path' => '', 
    'parameters' => '[]',
    'request_example' => '', 
    'response_format' => 'text', 
    'response_example' => '',
    'category_id' => null
];
$local_code = "<?php\n\nheader('Content-Type: application/json; charset=utf-8');\n\n\$response = ['code' => 200, 'message' => '你好，世界！', 'user_id' => \$auth_user_id ?? null];\n\necho json_encode(\$response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);";
$edit_mode = isset($_GET['id']) && ctype_digit($_GET['id']);

function calculateRelativePath($from, $to) {
    $from = rtrim(str_replace('\\', '/', realpath($from)), '/') . '/';
    $to = rtrim(str_replace('\\', '/', realpath($to)), '/') . '/';
    if (strpos($to, $from) === 0) {
        return trim(substr($to, strlen($from)), '/');
    }
    $fromParts = explode('/', trim($from, '/'));
    $toParts = explode('/', trim($to, '/'));
    $commonParts = [];
    foreach ($fromParts as $i => $part) {
        if (isset($toParts[$i]) && $part === $toParts[$i]) {
            $commonParts[] = $part;
        } else {
            break;
        }
    }
    $upLevels = count($fromParts) - count($commonParts);
    $relativePath = str_repeat('../', $upLevels);
    $relativePath .= implode('/', array_slice($toParts, count($commonParts)));
    return rtrim($relativePath, '/');
}

function hasAuthCode($content, $relativePath) {
    $pattern = '/<\?php\s*require_once\s+(__DIR__\s*\.\s*[\'"]\/' . preg_quote($relativePath, '/') . '\/api_auth\.php[\'"])\s*;\s*/i';
    return preg_match($pattern, $content) === 1;
}
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER, 
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $columns = $pdo->query("SHOW COLUMNS FROM `sl_apis`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('admin_id', $columns)) {
        $pdo->exec("ALTER TABLE `sl_apis` ADD `admin_id` INT NOT NULL AFTER `id`;");
    }
    if (!in_array('category_id', $columns)) {
        $pdo->exec("ALTER TABLE `sl_apis` ADD `category_id` INT NULL AFTER `admin_id`;");
    }
    if (!in_array('points_per_call', $columns)) {
        $pdo->exec("ALTER TABLE `sl_apis` ADD `points_per_call` INT NOT NULL DEFAULT 1 AFTER `price_per_call`;");
    }
    
    if ($edit_mode) {
        $page_title = '编辑接口';
        $id_to_edit = (int)$_GET['id'];
        $stmt_get = $pdo->prepare("SELECT * FROM sl_apis WHERE id = ? AND admin_id = ?"); 
        $stmt_get->execute([$id_to_edit, $admin_id]);
        $api = $stmt_get->fetch(PDO::FETCH_ASSOC);
        if (!$api) { 
            header('Location: api_list.php'); 
            exit; 
        }
        $api['endpoint'] = rawurldecode($api['endpoint']);
        $api['is_billable'] = isset($api['is_billable']) ? $api['is_billable'] : 0;
        $api['price_per_call'] = isset($api['price_per_call']) ? max('0.01', $api['price_per_call']) : '0.01';
        $api['points_per_call'] = isset($api['points_per_call']) ? max(1, (int)$api['points_per_call']) : 1;
        if (!in_array($api['visibility'], ['public', 'private', 'balance', 'points'])) {
            $api['visibility'] = 'public';
        }
        if ($api['type'] === 'local' && !empty($api['file_path']) && file_exists('../' . $api['file_path'])) {
            $content = file_get_contents('../' . $api['file_path']);
            $currentDir = dirname(__DIR__ . '/' . $api['file_path']);
            $projectRoot = realpath(__DIR__ . '/../');
            $relativePath = calculateRelativePath($currentDir, $projectRoot . '/common/security');
            $pattern = '/<\?php\s*require_once\s+(__DIR__\s*\.\s*[\'"]\/' . preg_quote($relativePath, '/') . '\/api_auth\.php[\'"])\s*;\s*/i';
            $local_code = preg_replace($pattern, '', $content, 1);
            $local_code = trim(str_replace('?>', '', $local_code));
        }
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['name'], $_POST['endpoint'], $_POST['type'], $_POST['status'], $_POST['visibility'])) {
            throw new Exception('提交的数据不完整');
        }
        $api_id = isset($_POST['id']) && ctype_digit($_POST['id']) ? (int)$_POST['id'] : null;
        $name = trim($_POST['name']);
        $endpoint_raw = trim($_POST['endpoint']);
        $type = $_POST['type'];
        $status = $_POST['status'];
        $visibility = $_POST['visibility'];
        $category_id = !empty($_POST['category_id']) && ctype_digit($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        
        if (empty($name) || empty($endpoint_raw)) { 
            throw new Exception('接口名称和调用地址不能为空。'); 
        }
        if (!in_array($visibility, ['public', 'private', 'balance', 'points'])) {
            throw new Exception('无效的权限设置');
        }
        $endpoint_clean = preg_replace('/\.+/', '', $endpoint_raw);
        $endpoint_parts = explode('/', $endpoint_clean);
        $filename = array_pop($endpoint_parts);
        $subdirectory = implode('/', $endpoint_parts);
        $api_dir = '../API' . (!empty($subdirectory) ? '/' . $subdirectory : '');
        if (!is_dir($api_dir)) { 
            mkdir($api_dir, 0755, true); 
        }
        $encoded_filename = rawurlencode($filename);
        $file_path = 'API/' . (!empty($subdirectory) ? $subdirectory . '/' : '') . $encoded_filename . '.php';
        $full_path = '../' . $file_path;
        switch ($visibility) {
            case 'public':
                $is_billable = 0;
                $price_per_call = '0.0000';
                $points_per_call = 0;
                break;
            case 'private':
                $is_billable = 0;
                $price_per_call = '0.0000';
                $points_per_call = 0;
                break;
            case 'balance':
                $is_billable = 1;
                $input_price = trim($_POST['price_per_call']);
                $price_value = $input_price === '' ? 0 : (float)$input_price;
                $price_per_call = number_format(max(0.01, $price_value), 4);
                $points_per_call = 0;
                break;
            case 'points':
                $is_billable = 0;
                $price_per_call = '0.0000';
                $input_points = trim($_POST['points_per_call']);
                $points_value = $input_points === '' ? 0 : (int)$input_points;
                $points_per_call = max(1, $points_value);
                break;
        }
        $currentDir = realpath(dirname($full_path));
        $projectRoot = realpath(__DIR__ . '/../');
        $relativePath = calculateRelativePath($currentDir, $projectRoot . '/common/security');
        $auth_bootstrap = "<?php require_once __DIR__ . '/{$relativePath}/api_auth.php';\n\n";
        $file_content = '';
        if ($type === 'local') {
            $user_code = $_POST['local_code'];
            $existingAuthCode = false;
            if (file_exists($full_path)) {
                $existingContent = file_get_contents($full_path);
                $existingAuthCode = hasAuthCode($existingContent, $relativePath);
            }
            $clean_code = $user_code;
            if (strpos(ltrim($clean_code), '<?php') === 0) {
                $clean_code = preg_replace('/^<\?php\s*/', '', $clean_code, 1);
            }
            if (!$existingAuthCode) {
                $file_content = $auth_bootstrap . $clean_code;
            } else {
                $file_content = ltrim($file_content, '<?php');
                $file_content = '<?php ' . $clean_code;
            }
            if (!preg_match('/\?>\s*$/', $file_content)) {
                $file_content .= "\n?>";
            }
            $remote_url = null; 
            $method = 'GET';
        } else {
            $remote_url = trim($_POST['remote_url']); 
            $method = $_POST['method'];
            if (empty($remote_url)) { 
                throw new Exception('远程接口地址不能为空。'); 
            }
            $proxy_script = "@error_reporting(0);\n\$remote_url = '" . addslashes($remote_url) . "';\n\$method = '" . $method . "';\n\$params = array_merge(\$_GET, \$_POST);\n\$ch = curl_init();\n";
            $proxy_script .= "if (\$method === 'GET' && !empty(\$params)) { \$remote_url .= (strpos(\$remote_url, '?') === false ? '?' : '&') . http_build_query(\$params); }\n";
            $proxy_script .= "curl_setopt(\$ch, CURLOPT_URL, \$remote_url);\ncurl_setopt(\$ch, CURLOPT_RETURNTRANSFER, true);\ncurl_setopt(\$ch, CURLOPT_FOLLOWLOCATION, true);\n";
            $proxy_script .= "if (\$method === 'POST') { curl_setopt(\$ch, CURLOPT_POST, true); curl_setopt(\$ch, CURLOPT_POSTFIELDS, http_build_query(\$params)); }\n";
            $proxy_script .= "\$headers = []; foreach (getallheaders() as \$h_name => \$h_value) { if (in_array(strtolower(\$h_name), ['user-agent', 'accept', 'accept-language'])) { \$headers[] = \$h_name . ': ' . \$h_value; } }\n";
            $proxy_script .= "curl_setopt(\$ch, CURLOPT_HTTPHEADER, \$headers);\n\$response = curl_exec(\$ch);\n\$http_code = curl_getinfo(\$ch, CURLINFO_HTTP_CODE);\n\$content_type = curl_getinfo(\$ch, CURLINFO_CONTENT_TYPE);\ncurl_close(\$ch);\n";
            $proxy_script .= "http_response_code(\$http_code);\nif(\$content_type) { header('Content-Type: ' . \$content_type); }\necho \$response;";
            $file_content = $auth_bootstrap . $proxy_script . "\n?>";
        }
        if (file_put_contents($full_path, $file_content) === false) { 
            throw new Exception('无法创建或写入接口文件，请检查API目录权限。'); 
        }
        $params_json = $_POST['parameters_json'];
        $params_data = json_decode($params_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $params_json = '[]';
        }
        if ($api_id) {
            $sql = "UPDATE sl_apis SET 
                    name=?, description=?, endpoint=?, type=?, status=?, 
                    visibility=?, is_billable=?, price_per_call=?, points_per_call=?,
                    remote_url=?, method=?, file_path=?, parameters=?, request_example=?, 
                    response_format=?, response_example=?, category_id=?,
                    updated_at=CURRENT_TIMESTAMP 
                    WHERE id=? AND admin_id = ?";
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                $name, trim($_POST['description']), $endpoint_raw, $type, $status, 
                $visibility, $is_billable, $price_per_call, $points_per_call,
                $remote_url, $method, $file_path, $params_json, 
                trim($_POST['request_example']), $_POST['response_format'], 
                trim($_POST['response_example']), $category_id,
                $api_id, $admin_id
            ]);
            if (!$result) {
                throw new Exception('数据库更新失败: ' . implode(' ', $stmt->errorInfo()));
            }
            $_SESSION['feedback_msg'] = '接口已成功更新';
        } else {
            $sql = "INSERT INTO sl_apis (
                    admin_id, name, description, endpoint, type, status, visibility, 
                    is_billable, price_per_call, points_per_call, remote_url, method, 
                    file_path, parameters, request_example, response_format, 
                    response_example, category_id, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $admin_id, $name, trim($_POST['description']), $endpoint_raw, $type, $status, 
                $visibility, $is_billable, $price_per_call, $points_per_call,
                $remote_url, $method, $file_path, $params_json, 
                trim($_POST['request_example']), $_POST['response_format'], 
                trim($_POST['response_example']), $category_id
            ]);
            $_SESSION['feedback_msg'] = '接口已成功添加。';
        }
        $_SESSION['feedback_type'] = 'success';
        header('Location: api_list.php');
        exit;
    }
    $categories = [];
    $stmt_cats = $pdo->query("SELECT * FROM sl_api_categories ORDER BY name");
    $categories = $stmt_cats->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { 
    $feedback_msg = '操作失败: ' . $e->getMessage(); 
    $feedback_type = 'error'; 
}
?>

<!DOCTYPE html>
<html lang="zh">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <link rel="stylesheet" type="text/css" href="../assets/css/materialdesignicons.min.css">
    <link rel="stylesheet" type="text/css" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="../assets/css/style.min.css">
    <style>
        .code-editor {
            font-family: 'Fira Code', 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.6;
            min-height: 300px;
        }
        .param-card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
            position: relative;
            transition: all 0.3s ease;
        }
        .param-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }
        .btn-remove-param {
            position: absolute;
            top: 10px;
            right: 10px;
            background: none;
            border: none;
            color: #ef4444;
            cursor: pointer;
            font-size: 24px;
            line-height: 1;
            padding: 0;
            z-index: 10;
        }
        .btn-remove-param:hover {
            color: #dc2626;
        }
        .api-type-options {
            display: flex;
            gap: 20px;
            padding: 8px 0;
        }
        .api-type-options label {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .permission-preview {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-weight: 500;
            margin-top: 8px;
            font-size: 12px;
        }
        .bg-emerald-100 { background-color: #dcfce7; }
        .text-emerald-800 { color: #166534; }
        .bg-blue-100 { background-color: #dbeafe; }
        .text-blue-800 { color: #1e3a8a; }
        .bg-indigo-100 { background-color: #e0e7ff; }
        .text-indigo-800 { color: #3730a3; }
        .bg-amber-100 { background-color: #fef3c7; }
        .text-amber-800 { color: #7c2d12; }
        .min-value-hint {
            color: #dc2626;
            font-size: 12px;
            margin-top: 4px;
            display: block;
        }
        .input-warning {
            border-color: #f59e0b !important;
        }
    </style>
</head>
<body>
<div class="container-fluid">        
    <div class="card">
        <header class="card-header">
            <div class="card-title"><?php echo $page_title; ?></div>
            <div class="card-action">
                <a href="api_list.php" class="btn btn-default btn-sm"><i class="mdi mdi-arrow-left"></i> 返回列表</a>
            </div>
        </header>
        <div class="card-body">
            <?php if ($feedback_msg): ?>
            <div class="alert alert-<?php echo $feedback_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show mb-3">
                <?php echo htmlspecialchars($feedback_msg); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            <form id="api-form" method="POST" action="api_edit.php<?php echo $edit_mode ? '?id='.$api['id'] : ''; ?>">
                <input type="hidden" name="id" value="<?php echo $api['id']; ?>">
                <input type="hidden" name="parameters_json" id="parameters_json">
                <div class="row">
                    <div class="mb-3 col-md-12">
                        <label for="category_id" class="form-label">接口分类</label>
                        <select id="category_id" name="category_id" class="form-select">
                            <option value="">-- 无分类 --</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $api['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <div class="mb-3">
                            <label for="name" class="form-label">接口名称</label>
                            <input type="text" id="name" name="name" class="form-control" placeholder="例如：随机一言" value="<?php echo htmlspecialchars($api['name']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">接口描述</label>
                            <textarea id="description" name="description" class="form-control code-editor" rows="3" placeholder="简单描述这个接口的功能和用途"><?php echo htmlspecialchars($api['description']); ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">接口类型</label>
                            <div class="api-type-options">
                                <label>
                                    <input type="radio" name="type" value="local" <?php echo $api['type'] === 'local' ? 'checked' : ''; ?>>
                                    本地接口
                                </label>
                                <label>
                                    <input type="radio" name="type" value="remote" <?php echo $api['type'] === 'remote' ? 'checked' : ''; ?>>
                                    套用接口
                                </label>
                            </div>
                        </div>
                        <div id="local-options">
                            <div class="mb-3">
                                <label for="local_code" class="form-label">接口代码 (PHP)</label>
                                <textarea id="local_code" name="local_code" class="form-control code-editor" rows="10"><?php echo htmlspecialchars($local_code); ?></textarea>
                                <small class="text-muted">系统会自动检测并添加必要的认证代码，无需手动引入</small>
                            </div>
                        </div>
                        <div id="remote-options" style="display:none;">
                            <div class="mb-3">
                                <label for="remote_url" class="form-label">远程接口地址</label>
                                <input type="url" id="remote_url" name="remote_url" class="form-control" placeholder="https://example.com/api" value="<?php echo htmlspecialchars($api['remote_url']); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="method" class="form-label">请求方法</label>
                                <select id="method" name="method" class="form-select">
                                    <?php foreach(['GET', 'POST'] as $m): ?>
                                    <option value="<?php echo $m; ?>" <?php echo $api['method'] === $m ? 'selected' : ''; ?>><?php echo $m; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">请求参数</label>
                            <div id="param-container" class="mb-2">
                                <?php if (!empty($api['parameters'])): ?>
                                    <?php foreach(json_decode($api['parameters'], true) as $param): ?>
                                    <div class="param-card" data-param-id="param-<?php echo uniqid(); ?>">
                                        <button type="button" class="btn-remove-param" aria-label="删除参数">&times;</button>
                                        <div class="param-grid">
                                            <div class="mb-3">
                                                <label class="form-label">参数名</label>
                                                <input type="text" class="form-control param-name" value="<?php echo htmlspecialchars($param['name'] ?? ''); ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">类型</label>
                                                <select class="form-select param-type">
                                                    <option value="string" <?php echo isset($param['type']) && $param['type'] === 'string' ? 'selected' : ''; ?>>String</option>
                                                    <option value="integer" <?php echo isset($param['type']) && $param['type'] === 'integer' ? 'selected' : ''; ?>>Integer</option>
                                                    <option value="boolean" <?php echo isset($param['type']) && $param['type'] === 'boolean' ? 'selected' : ''; ?>>Boolean</option>
                                                    <option value="array" <?php echo isset($param['type']) && $param['type'] === 'array' ? 'selected' : ''; ?>>Array</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">是否必填</label>
                                                <select class="form-select param-required">
                                                    <option value="yes" <?php echo isset($param['required']) && $param['required'] === 'yes' ? 'selected' : ''; ?>>必填</option>
                                                    <option value="no" <?php echo isset($param['required']) && $param['required'] === 'no' ? 'selected' : ''; ?>>可选</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">说明</label>
                                                <input type="text" class="form-control param-desc" value="<?php echo htmlspecialchars($param['desc'] ?? ''); ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <button type="button" id="add-param-btn" class="btn btn-outline-primary">添加参数</button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="status" class="form-label">接口状态</label>
                            <select id="status" name="status" class="form-select">
                                <?php foreach(['normal'=>'正常', 'error'=>'异常', 'maintenance'=>'维护', 'deprecated'=>'失效'] as $s_val => $s_text): ?>
                                <option value="<?php echo $s_val; ?>" <?php echo $api['status'] === $s_val ? 'selected' : ''; ?>><?php echo $s_text; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="endpoint" class="form-label">调用地址 (文件名)</label>
                            <div class="input-group">
                                <span class="input-group-text">/API/</span>
                                <input type="text" id="endpoint" name="endpoint" class="form-control" placeholder="可以包含子目录，如subdir/my_api" value="<?php echo htmlspecialchars($api['endpoint']); ?>" required>
                            </div>
                            <small class="text-muted">最终调用地址为: /API/子目录/文件名.php</small>
                        </div>
                        <div class="mb-3">
                            <label for="visibility" class="form-label">调用权限</label>
                            <select id="visibility" name="visibility" class="form-select">
                                <option value="public" <?php echo ($api['visibility'] === 'public') ? 'selected' : ''; ?>>公开调用 (无需密钥)</option>
                                <option value="private" <?php echo ($api['visibility'] === 'private') ? 'selected' : ''; ?>>密钥调用 (免费)</option>
                                <option value="balance" <?php echo ($api['visibility'] === 'balance') ? 'selected' : ''; ?>>密钥调用 (余额计费)</option>
                                <option value="points" <?php echo ($api['visibility'] === 'points') ? 'selected' : ''; ?>>密钥调用 (点数计费)</option>
                            </select>
                            <div id="permission-preview" class="permission-preview">
                                <?php
                                if ($api['visibility'] === 'public') {
                                    echo '<span class="permission-preview bg-emerald-100 text-emerald-800">公开访问</span>';
                                } elseif ($api['visibility'] === 'private') {
                                    echo '<span class="permission-preview bg-blue-100 text-blue-800">仅密钥访问</span>';
                                } elseif ($api['visibility'] === 'balance') {
                                    echo '<span class="permission-preview bg-indigo-100 text-indigo-800">密钥+余额: ¥' . number_format($api['price_per_call'], 2) . '/次</span>';
                                } elseif ($api['visibility'] === 'points') {
                                    echo '<span class="permission-preview bg-amber-100 text-amber-800">密钥+点数: ' . $api['points_per_call'] . '点/次</span>';
                                }
                                ?>
                            </div>
                        </div>
                        <div id="price-options" class="mb-3" style="display: <?php echo $api['visibility'] === 'balance' ? 'block' : 'none'; ?>">
                            <label for="price_per_call" class="form-label">每次调用价格</label>
                            <input type="number" step="0.01" id="price_per_call" name="price_per_call" class="form-control" value="<?php echo htmlspecialchars($api['price_per_call']); ?>">
                            <small class="text-muted">精确到小数点后四位</small>
                            <span class="min-value-hint">系统将自动处理为最低0.01</span>
                        </div>
                        <div id="points-options" class="mb-3" style="display: <?php echo $api['visibility'] === 'points' ? 'block' : 'none'; ?>">
                            <label for="points_per_call" class="form-label">每次调用消耗点数</label>
                            <input type="number" step="1" id="points_per_call" name="points_per_call" class="form-control" value="<?php echo htmlspecialchars($api['points_per_call']); ?>">
                            <small class="text-muted">每次调用消耗的用户点数，必须为整数</small>
                            <span class="min-value-hint">系统将自动处理为最低1点</span>
                        </div>
                        <div class="mb-3">
                            <label for="request_example" class="form-label">请求示例</label>
                            <input type="text" id="request_example" name="request_example" class="form-control" placeholder="/API/子目录/文件名.php?参数=值" value="<?php echo htmlspecialchars($api['request_example']); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="response_format" class="form-label">返回格式</label>
                            <select id="response_format" name="response_format" class="form-select">
                                <?php $formats = ['text', 'json', 'img', 'mp3','mp4']; foreach($formats as $f): ?>
                                <option value="<?php echo $f; ?>" <?php echo $api['response_format'] === $f ? 'selected' : ''; ?>><?php echo $f; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="response_example" class="form-label">返回示例</label>
                            <textarea id="response_example" name="response_example" class="form-control code-editor" rows="5" placeholder="留空则详情页实时请求"><?php echo htmlspecialchars($api['response_example']); ?></textarea>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary"><?php echo $edit_mode ? '更新接口' : '立即添加'; ?></button>
                            <a href="api_list.php" class="btn btn-outline-secondary">取消</a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<script type="text/javascript" src="../assets/js/jquery.min.js"></script>
<script type="text/javascript" src="../assets/js/popper.min.js"></script>
<script type="text/javascript" src="../assets/js/bootstrap.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const typeRadios = document.querySelectorAll('input[name="type"]');
    const localOptions = document.getElementById('local-options');
    const remoteOptions = document.getElementById('remote-options');

    function toggleApiTypeOptions() {
        const isLocal = document.querySelector('input[name="type"]:checked').value === 'local';
        localOptions.style.display = isLocal ? 'block' : 'none';
        remoteOptions.style.display = !isLocal ? 'block' : 'none';
    }
    typeRadios.forEach(radio => radio.addEventListener('change', toggleApiTypeOptions));
    toggleApiTypeOptions();
    const visibilitySelect = document.getElementById('visibility');
    const priceOptions = document.getElementById('price-options');
    const pointsOptions = document.getElementById('points-options');
    const permissionPreview = document.getElementById('permission-preview');
    const priceInput = document.getElementById('price_per_call');
    const pointsInput = document.getElementById('points_per_call');

    function syncPermissionUI() {
        const selectedValue = visibilitySelect.value;
        priceOptions.style.display = selectedValue === 'balance' ? 'block' : 'none';
        pointsOptions.style.display = selectedValue === 'points' ? 'block' : 'none';
        updatePermissionPreview(selectedValue);
    }

    function updatePermissionPreview(permissionType) {
        let previewHtml = '';
        switch(permissionType) {
            case 'public':
                previewHtml = '<span class="permission-preview bg-emerald-100 text-emerald-800">公开访问</span>';
                break;
            case 'private':
                previewHtml = '<span class="permission-preview bg-blue-100 text-blue-800">仅密钥访问</span>';
                break;
            case 'balance':
                const price = parseFloat(priceInput.value) || 0;
                const displayPrice = price < 0.01 ? `0.01 (系统自动调整)` : price.toFixed(4);
                previewHtml = `<span class="permission-preview bg-indigo-100 text-indigo-800">密钥+余额: ¥${parseFloat(displayPrice).toFixed(2)}/次</span>`;
                if (price < 0.01 && price !== 0) {
                    priceInput.classList.add('input-warning');
                } else {
                    priceInput.classList.remove('input-warning');
                }
                break;
            case 'points':
                const points = parseInt(pointsInput.value) || 0;
                const displayPoints = points < 1 ? `1 (系统自动调整)` : points;
                previewHtml = `<span class="permission-preview bg-amber-100 text-amber-800">密钥+点数: ${displayPoints}点/次</span>`;
                if (points < 1 && points !== 0) {
                    pointsInput.classList.add('input-warning');
                } else {
                    pointsInput.classList.remove('input-warning');
                }
                break;
        }
        permissionPreview.innerHTML = previewHtml;
    }
    visibilitySelect.addEventListener('change', function() {
        syncPermissionUI();
    });
    priceInput.addEventListener('input', function() {
        if (visibilitySelect.value === 'balance') {
            updatePermissionPreview('balance');
        }
    });
    pointsInput.addEventListener('input', function() {
        if (visibilitySelect.value === 'points') {
            updatePermissionPreview('points');
        }
    });
    syncPermissionUI();
    const paramContainer = document.getElementById('param-container');
    const addParamBtn = document.getElementById('add-param-btn');
    const paramsJsonInput = document.getElementById('parameters_json');
    document.addEventListener('click', function(e) {
        if (e.target?.classList.contains('btn-remove-param')) {
            e.preventDefault();
            const paramCard = e.target.closest('.param-card');
            if (paramCard) {
                paramCard.style.opacity = '0';
                setTimeout(() => paramCard.remove(), 300);
                updateParametersJson();
            }
        }
    });
    addParamBtn.addEventListener('click', function() {
        const card = document.createElement('div');
        card.className = 'param-card';
        card.dataset.paramId = 'param-' + Date.now();
        card.innerHTML = `
            <button type="button" class="btn-remove-param" aria-label="删除参数">&times;</button>
            <div class="param-grid">
                <div class="mb-3">
                    <label class="form-label">参数名</label>
                    <input type="text" class="form-control param-name">
                </div>
                <div class="mb-3">
                    <label class="form-label">类型</label>
                    <select class="form-select param-type">
                        <option value="string">String</option>
                        <option value="integer">Integer</option>
                        <option value="boolean">Boolean</option>
                        <option value="array">Array</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">是否必填</label>
                    <select class="form-select param-required">
                        <option value="yes">必填</option>
                        <option value="no" selected>可选</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">说明</label>
                    <input type="text" class="form-control param-desc">
                </div>
            </div>`;
        card.style.opacity = '0';
        paramContainer.appendChild(card);
        setTimeout(() => card.style.opacity = '1', 10);
    });

    function updateParametersJson() {
        const params = [];
        document.querySelectorAll('.param-card').forEach(card => {
            const name = card.querySelector('.param-name').value.trim();
            if (name) {
                params.push({
                    name: name,
                    type: card.querySelector('.param-type').value,
                    required: card.querySelector('.param-required').value,
                    desc: card.querySelector('.param-desc').value.trim()
                });
            }
        });
        paramsJsonInput.value = JSON.stringify(params);
    }
    paramContainer.addEventListener('input', function(e) {
        if (e.target.classList.contains('param-name') || e.target.classList.contains('param-desc')) {
            updateParametersJson();
        }
    });
    paramContainer.addEventListener('change', function(e) {
        if (e.target.classList.contains('param-type') || e.target.classList.contains('param-required')) {
            updateParametersJson();
        }
    });
    document.getElementById('api-form').addEventListener('submit', function(e) {
        updateParametersJson();
        let confirmMessage = '';
        if (visibilitySelect.value === 'balance') {
            const price = parseFloat(priceInput.value) || 0;
            if (price < 0.01) {
                confirmMessage += '余额将自动调整为最低0.01\n';
            }
        }
        if (visibilitySelect.value === 'points') {
            const points = parseInt(pointsInput.value) || 0;
            if (points < 1) {
                confirmMessage += '点数将自动调整为最低1点\n';
            }
        }
        if (confirmMessage) {
            confirmMessage += '\n是否继续提交？';
            if (!confirm(confirmMessage)) {
                e.preventDefault();
                return false;
            }
        }
    });
    updateParametersJson();
});
</script>
</body>
</html>