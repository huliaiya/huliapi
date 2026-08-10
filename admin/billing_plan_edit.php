<?php
session_start();
error_reporting(0);
ini_set('display_errors', 0);
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}
require '../config.php';
$plan = [
    'id' => '',
    'name' => '',
    'description' => '',
    'price' => '0.00',
    'billing_type' => 'balance',
    'balance_to_add' => '0.00',
    'points_to_add' => 0,
    'membership_days' => 0,
    'is_active' => 1,
    'is_card' => 0
];
$edit_mode = !empty($_GET['id']);
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if ($edit_mode) {
        $stmt = $pdo->prepare("SELECT * FROM sl_billing_plans WHERE id=?");
        $stmt->execute([$_GET['id']]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC) ?: $plan;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id          = $_POST['id'] ?? 0;
        $name        = trim($_POST['name']);
        $description = trim($_POST['description']);
        $price       = (float)$_POST['price'];
        $billing_type= $_POST['billing_type'] ?? 'balance';
        $is_active   = (int)$_POST['is_active'];
        $is_card     = (int)$_POST['is_card'];
        $membership_days = (int)$_POST['membership_days'] ?? 0;
        if ($billing_type === 'balance') {
            $balance_to_add = (float)$_POST['balance_to_add'];
            $points_to_add  = 0;
            $membership_days = 0;
        } elseif ($billing_type === 'points') {
            $points_to_add  = (int)$_POST['points_to_add'];
            $balance_to_add = 0;
            $membership_days = 0;
        } else {
            $balance_to_add = 0;
            $points_to_add  = 0;
            $membership_days = (int)$_POST['membership_days'] ?? 0;
        }
        if ($id) {
            $stmt = $pdo->prepare("UPDATE sl_billing_plans SET 
                name=?, description=?, price=?, billing_type=?, balance_to_add=?, points_to_add=?, membership_days=?, is_active=?, is_card=? WHERE id=?");
            $stmt->execute([$name, $description, $price, $billing_type, $balance_to_add, $points_to_add, $membership_days, $is_active, $is_card, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO sl_billing_plans 
                (name, description, price, billing_type, balance_to_add, points_to_add, membership_days, is_active, is_card) 
                VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$name, $description, $price, $billing_type, $balance_to_add, $points_to_add, $membership_days, $is_active, $is_card]);
        }
        header("Location: billing_plans.php");
        exit;
    }
} catch (Exception $e) {
    echo "<script>alert('错误：".$e->getMessage()."');</script>";
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $edit_mode ? '编辑' : '添加'; ?>计费方案</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../assets/css/style.min.css">
    <style>
        .form-group{margin-bottom:1.5rem}
        .section-title{font-size:1.25rem;font-weight:600;margin:1.5rem 0 1rem;padding-bottom:0.5rem;border-bottom:1px solid #e9ecef}
        .radio-group{display:flex;gap:1.5rem;margin-bottom:1rem}
        .radio-item{display:flex;align-items:center;gap:0.5rem}
        .required-mark{color:#dc2626}
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <header class="card-header">
                    <div class="card-title"><?php echo $edit_mode ? '编辑计费方案' : '添加计费方案'; ?></div>
                </header>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="id" value="<?php echo $plan['id'] ?>">
                        <h5 class="section-title">基本信息</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>方案名称 <span class="required-mark">*</span></label>
                                    <input type="text" name="name" class="form-control" value="<?php echo $plan['name'] ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>销售价格 <span class="required-mark">*</span></label>
                                    <input type="number" step="0.01" name="price" class="form-control" value="<?php echo $plan['price'] ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>方案描述</label>
                            <textarea class="form-control" name="description" rows="3"><?php echo $plan['description'] ?></textarea>
                        </div>
                        <h5 class="section-title">计费类型</h5>
                        <div class="form-group">
                            <div class="radio-group">
                                <div class="radio-item">
                                    <input type="radio" name="billing_type" value="balance" id="b1" <?php echo $plan['billing_type']=='balance'?'checked':''?>>
                                    <label for="b1">余额方案</label>
                                </div>
                                <div class="radio-item">
                                    <input type="radio" name="billing_type" value="points" id="b2" <?php echo $plan['billing_type']=='points'?'checked':''?>>
                                    <label for="b2">点数方案</label>
                                </div>
                                <div class="radio-item">
                                    <input type="radio" name="billing_type" value="membership" id="b3" <?php echo $plan['billing_type']=='membership'?'checked':''?>>
                                    <label for="b3">超级会员方案</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-group" id="balance_box">
                            <label>获得余额</label>
                            <input type="number" step="0.01" name="balance_to_add" class="form-control" value="<?php echo $plan['balance_to_add'] ?>">
                        </div>
                        <div class="form-group" id="points_box">
                            <label>获得点数</label>
                            <input type="number" name="points_to_add" class="form-control" value="<?php echo $plan['points_to_add'] ?>">
                        </div>
                        <div class="form-group" id="membership_box">
                            <label>超级会员天数</label>
                            <input type="number" name="membership_days" class="form-control" value="<?php echo $plan['membership_days'] ?>">
                        </div>
                        <h5 class="section-title">高级设置</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>购买方式</label>
                                    <select name="is_card" class="form-select">
                                        <option value="0" <?php echo $plan['is_card']==0?'selected':''?>>直接到账</option>
                                        <option value="1" <?php echo $plan['is_card']==1?'selected':''?>>卡密模式</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>状态</label>
                                    <select name="is_active" class="form-select">
                                        <option value="1" <?php echo $plan['is_active']==1?'selected':''?>>上架</option>
                                        <option value="0" <?php echo $plan['is_active']==0?'selected':''?>>下架</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary"><?php echo $edit_mode ? '更新方案' : '创建方案'; ?></button>
                        <button type="button" class="btn btn-secondary ms-2" onclick="history.back()">取消返回</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
const b1 = document.getElementById('b1');
const b2 = document.getElementById('b2');
const b3 = document.getElementById('b3');
const balance_box = document.getElementById('balance_box');
const points_box = document.getElementById('points_box');
const membership_box = document.getElementById('membership_box');

function toggle() {
    if (b1.checked) {
        balance_box.style.display = 'block';
        points_box.style.display = 'none';
        membership_box.style.display = 'none';
    } else if (b2.checked) {
        balance_box.style.display = 'none';
        points_box.style.display = 'block';
        membership_box.style.display = 'none';
    } else {
        balance_box.style.display = 'none';
        points_box.style.display = 'none';
        membership_box.style.display = 'block';
    }
}
b1.addEventListener('change', toggle);
b2.addEventListener('change', toggle);
b3.addEventListener('change', toggle);
toggle();
</script>
</body>
</html>