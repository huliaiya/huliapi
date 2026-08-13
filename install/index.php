<?php
session_start();
define('STEP_CHECK_ENV',1);define('STEP_DB_CONFIG',2);define('STEP_INSTALL_DB',3);define('STEP_COMPLETE',4);
$is_installed=file_exists('install.lock');if($is_installed&&basename($_SERVER['PHP_SELF'])!='install.php'&&(!isset($_GET['step'])||(int)$_GET['step']!=STEP_COMPLETE)){die('系统已安装，如需重新安装请删除install.lock文件');}
if(!file_exists('install.lock')&&(!isset($_GET['step'])||(int)$_GET['step']!==STEP_CHECK_ENV)&&(!isset($_GET['step'])||(int)$_GET['step']!=STEP_COMPLETE)){header("Location: ?step=".STEP_CHECK_ENV);exit;}
$current_step=isset($_GET['step'])?(int)$_GET['step']:STEP_CHECK_ENV;$error=null;$success_msg=null;$config_written_this_run=false;
if($_SERVER['REQUEST_METHOD']==='POST'){try{$action=$_POST['action']??'';if($action==='check_env'){checkEnvironment();$_SESSION['env_checked']=true;$current_step=STEP_DB_CONFIG;}elseif($action==='db_config'){if(empty($_SESSION['env_checked'])){throw new Exception('请先完成环境检测');}
 $db_host=trim($_POST['db_host']??'127.0.0.1');$db_name=trim($_POST['db_name']??'');$db_user=trim($_POST['db_user']??'');$db_pwd=$_POST['db_pwd']??'';$admin_username=trim($_POST['admin_username']??'admin');$admin_nickname=trim($_POST['admin_nickname']??'管理员');$admin_email=trim($_POST['admin_email']??'');$admin_qq=trim($_POST['admin_qq']??'');$admin_password=$_POST['admin_password']??'';$admin_password_confirm=$_POST['admin_password_confirm']??'';$admin_path=trim($_POST['admin_path']??'admin');$smtp_host=trim($_POST['mail_smtp_host']??'');$smtp_port_raw=trim($_POST['mail_smtp_port']??'');$smtp_port=$smtp_port_raw===''?465:(int)$smtp_port_raw;$smtp_secure=$_POST['mail_smtp_secure']??'ssl';$smtp_user=trim($_POST['mail_smtp_user']??'');$smtp_pass=$_POST['mail_smtp_pass']??'';$turnstile_site_key=trim($_POST['turnstile_site_key']??'');$turnstile_secret_key=trim($_POST['turnstile_secret_key']??'');if($db_name===''||$db_user===''){throw new Exception('数据库名和用户名不能为空');}if(!preg_match('/^[A-Za-z0-9_]{2,32}$/',$admin_username)){throw new Exception('管理员账号需要使用2-32位字母、数字或下划线');}if($admin_nickname===''){throw new Exception('管理员昵称不能为空');}if(!filter_var($admin_email,FILTER_VALIDATE_EMAIL)){throw new Exception('请输入有效的管理员邮箱');}if(strlen($admin_password)<6||$admin_password!==$admin_password_confirm){throw new Exception('管理员密码至少6位，且两次输入必须一致');}if(!preg_match('/^[A-Za-z][A-Za-z0-9_-]{1,31}$/',$admin_path)||in_array(strtolower($admin_path),['install','api','assets','common','template','user'])){throw new Exception('后台目录名需要使用安全的字母、数字、下划线或短横线');}if($smtp_host!==''&&($smtp_port<1||$smtp_port>65535)){throw new Exception('SMTP端口范围无效');}if(!in_array($smtp_secure,['ssl','tls'],true)){throw new Exception('SMTP加密方式无效');}if(($turnstile_site_key==='')!==($turnstile_secret_key==='')){throw new Exception('Cloudflare人机验证的Site Key与Secret Key需同时填写或同时留空');}
 $dsn="mysql:host={$db_host};charset=utf8mb4";$pdo=new PDO($dsn,$db_user,$db_pwd,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$stmt=$pdo->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?");$stmt->execute([$db_name]);if(!$stmt->fetch()){throw new Exception("数据库 {$db_name} 不存在，请先创建数据库");}
 $_SESSION['db_config']=['host'=>$db_host,'name'=>$db_name,'user'=>$db_user,'pwd'=>$db_pwd];$_SESSION['install_config']=['admin_username'=>$admin_username,'admin_nickname'=>$admin_nickname,'admin_email'=>$admin_email,'admin_qq'=>$admin_qq,'admin_password'=>$admin_password,'admin_path'=>$admin_path,'smtp_host'=>$smtp_host,'smtp_port'=>$smtp_port,'smtp_secure'=>$smtp_secure,'smtp_user'=>$smtp_user,'smtp_pass'=>$smtp_pass,'turnstile_site_key'=>$turnstile_site_key,'turnstile_secret_key'=>$turnstile_secret_key];$current_step=STEP_INSTALL_DB;}elseif($action==='install_db'){if(empty($_SESSION['db_config'])||empty($_SESSION['install_config'])){throw new Exception('安装配置丢失，请返回上一步重新配置');}
 $db=$_SESSION['db_config'];$install=$_SESSION['install_config'];$log="";$log.="> 正在生成数据库配置文件...\n";$configContent="<?php\ndefine('DB_HOST',".var_export($db['host'],true).");define('DB_NAME',".var_export($db['name'],true).");define('DB_USER',".var_export($db['user'],true).");define('DB_PASS',".var_export($db['pwd'],true).");define('DB_CHARSET','utf8mb4');define('ADMIN_PATH',".var_export($install['admin_path'],true).");";if(!file_put_contents('../config.php',$configContent)){throw new Exception('无法创建配置文件，请检查目录权限');}$config_written_this_run=true;$log.="✓ 配置文件生成成功\n";$log.="> 正在连接数据库...\n";$dsn="mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4";$pdo=new PDO($dsn,$db['user'],$db['pwd'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$log.="> 正在清理现有数据表...\n";$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");$tables=$pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);if(!empty($tables)){foreach($tables as $table){try{$pdo->exec("DROP TABLE `{$table}`");$log.="✓ 已删除表: {$table}\n";}catch(PDOException $e){$log.="⚠ 删除表失败: {$table} ({$e->getMessage()})\n";}}}
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");$log.="✓ 数据库清理完成\n";$log.="> 正在解析SQL文件...\n";$sql=@file_get_contents('install.sql');if(!$sql){throw new Exception('无法读取install.sql文件');}
$sql_commands=preg_split('/;\s*\n/',$sql);$log.="> 开始导入数据库结构 (共 ".count($sql_commands)." 条SQL语句)...\n";foreach($sql_commands as $command){$command=trim($command);if(!empty($command)){$table_name='';if(preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([^\s`(]+)/i',$command,$matches)){$table_name=$matches[1];}if(empty($table_name)&&preg_match('/INSERT\s+INTO\s+`?([^\s`]+)/i',$command,$matches)){$table_name=$matches[1];}if(empty($table_name)){$table_name='SQL命令';}
try{$start_time=microtime(true);$pdo->exec($command);$time_taken=round((microtime(true)-$start_time)*1000,2);$log.="✓ [{$table_name}] 执行成功 ({$time_taken}ms)\n";}catch(PDOException $e){if(strpos($e->getMessage(),'already exists')!==false){$log.="⚠ [{$table_name}] 表已存在 (跳过)\n";}else{$log.="✗ [{$table_name}] 执行失败: ".$e->getMessage()."\n";}}}}
 $log.="✓ 数据库导入完成\n";$adminStmt=$pdo->prepare("UPDATE huli_admins SET username = ?, password = ?, email = ?, qq = ?, nickname = ? WHERE id = 1");$adminStmt->execute([$install['admin_username'],password_hash($install['admin_password'],PASSWORD_DEFAULT),$install['admin_email'],$install['admin_qq'],$install['admin_nickname']]);$settingStmt=$pdo->prepare("UPDATE huli_settings SET setting_value = ? WHERE setting_key = ?");foreach([['mail_smtp_host',$install['smtp_host']],['mail_smtp_port',(string)$install['smtp_port']],['mail_smtp_secure',$install['smtp_secure']],['mail_smtp_user',$install['smtp_user']],['mail_smtp_pass',$install['smtp_pass']],['turnstile_site_key',$install['turnstile_site_key']],['turnstile_secret_key',$install['turnstile_secret_key']]] as $setting){$settingStmt->execute([$setting[1],$setting[0]]);}$log.="✓ 管理员和邮件配置保存成功\n";if($install['admin_path']!=='admin'){if(!is_dir('../admin')){throw new Exception('默认后台目录不存在，无法重命名');}if(file_exists('../'.$install['admin_path'])){throw new Exception('后台目录名已存在，请更换名称');}if(!rename('../admin','../'.$install['admin_path'])){throw new Exception('后台目录重命名失败，请检查目录权限');}$log.="✓ 后台目录已设置为 /{$install['admin_path']}/\n";}$log.="> 正在创建安装锁文件...\n";file_put_contents('install.lock','安装锁'.PHP_EOL.'安装完成时间: '.date('Y-m-d H:i:s'));$log.="✓ 安装锁文件创建成功\n";$_SESSION['install_log']=$log;$_SESSION['installed_admin']=$install;header("Location: ?step=".STEP_COMPLETE);exit;}}catch(Exception $e){$error=$e->getMessage();if($action==='install_db'&&$config_written_this_run){if(file_exists('../config.php')){@unlink('../config.php');}if(file_exists('install.lock')){@unlink('install.lock');}}}}
 function checkEnvironment(){if(version_compare(PHP_VERSION,'8.0.0','<')){throw new Exception('PHP版本需要8.0.0或更高，当前版本: '.PHP_VERSION);}
 $required_extensions=['pdo','pdo_mysql','curl','openssl','mbstring','gd','zip'];$missing=[];foreach($required_extensions as $ext){if(!extension_loaded($ext)){$missing[]=$ext;}}if(!empty($missing)){throw new Exception('缺少必需的PHP扩展: '.implode(', ',$missing));}
$check_dirs=['../','../API'];foreach($check_dirs as $dir){if(!is_writable($dir)){throw new Exception("目录/文件不可写: {$dir}");}}
$check_file='../config.php';if(file_exists($check_file)&&!is_writable($check_file)){throw new Exception("目录/文件不可写: {$check_file}");}}
function showInstallPage($step,$error=null){$steps=[STEP_CHECK_ENV=>['title'=>'环境检测','active'=>$step==STEP_CHECK_ENV,'completed'=>$step>STEP_CHECK_ENV],STEP_DB_CONFIG=>['title'=>'数据库配置','active'=>$step==STEP_DB_CONFIG,'completed'=>$step>STEP_DB_CONFIG],STEP_INSTALL_DB=>['title'=>'安装数据库','active'=>$step==STEP_INSTALL_DB,'completed'=>$step>STEP_INSTALL_DB],STEP_COMPLETE=>['title'=>'安装完成','active'=>$step==STEP_COMPLETE,'completed'=>false]];?>

<!DOCTYPE html>
<html lang="zh">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>系统安装向导</title>
<link rel="stylesheet" href="../assets/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/css/materialdesignicons.min.css">
<link rel="stylesheet" href="../assets/css/liquid-glass.css">
<style>
:root {
  --primary: #1976d2;
  --primary-light: #63a4ff;
  --primary-dark: #004ba0;
  --secondary: #26c6da;
  --success: #00c853;
  --warning: #ff9100;
  --error: #d50000;
  --bg: #f5f7fa;
  --card-bg: #ffffff;
  --text: #263238;
  --text-secondary: #607d8b;
  --gradient-main: linear-gradient(135deg, #1976d2 0%, #26c6da 100%);
}
body {
  background:
    radial-gradient(circle at 15% 20%, rgba(25,118,210,0.08) 0%, transparent 40%),
    radial-gradient(circle at 85% 80%, rgba(38,198,218,0.10) 0%, transparent 40%),
    linear-gradient(135deg, #eef4fb 0%, #f7f9fc 50%, #e9f5f8 100%);
  font-family: 'Segoe UI', 'PingFang SC', 'Microsoft YaHei', sans-serif;
  color: var(--text);
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}
.install-container {
  max-width: 900px;
  width: 100%;
  margin: 0 auto;
  animation: fadeIn 0.5s ease;
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}
.card {
  border: none;
  border-radius: 18px;
  box-shadow: 0 20px 60px rgba(0,40,100,0.12), 0 4px 12px rgba(0,0,0,0.06);
  overflow: hidden;
  background: var(--card-bg);
}
.card-header {
  background: var(--gradient-main);
  color: white;
  padding: 30px 25px;
  text-align: center;
  border-bottom: none;
  position: relative;
  overflow: hidden;
}
.card-header::before,
.card-header::after {
  content: '';
  position: absolute;
  border-radius: 50%;
  background: rgba(255,255,255,0.12);
}
.card-header::before {
  width: 180px;
  height: 180px;
  top: -70px;
  right: -40px;
}
.card-header::after {
  width: 120px;
  height: 120px;
  bottom: -60px;
  left: -30px;
}
.card-title {
  font-size: 1.8rem;
  font-weight: 600;
  margin: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  z-index: 1;
}
.card-body {
  padding: 34px 36px;
}
.step-indicator {
  display: flex;
  justify-content: center;
  margin-bottom: 40px;
  position: relative;
}
.step {
  display: flex;
  flex-direction: column;
  align-items: center;
  position: relative;
  padding: 0 25px;
  z-index: 2;
}
.step-number {
  width: 50px;
  height: 50px;
  border-radius: 50%;
  background: #e8edf3;
  color: var(--text-secondary);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: bold;
  font-size: 1.2rem;
  margin-bottom: 10px;
  transition: all 0.3s ease;
  border: 3px solid white;
  box-shadow: 0 3px 10px rgba(0,0,0,0.1);
}
.step.active .step-number {
  background: var(--primary);
  color: white;
  transform: scale(1.12);
  box-shadow: 0 0 0 6px rgba(25,118,210,0.15), 0 6px 16px rgba(25,118,210,0.35);
}
.step.completed .step-number {
  background: var(--success);
  color: white;
  box-shadow: 0 0 0 6px rgba(0,200,83,0.15);
}
.step-title {
  color: var(--text-secondary);
  font-size: 0.95rem;
  text-align: center;
  font-weight: 500;
  transition: all 0.3s ease;
}
.step.active .step-title {
  color: var(--primary);
  font-weight: 600;
}
.step.completed .step-title {
  color: var(--success);
}
.step-connector {
  position: absolute;
  top: 25px;
  left: -50%;
  width: 100%;
  height: 3px;
  background: #e0e0e0;
  z-index: 1;
  transition: all 0.3s ease;
}
.step:first-child .step-connector {
  display: none;
}
.step.completed .step-connector {
  background: var(--success);
}
.env-check-list {
  list-style: none;
  padding: 0;
  margin: 0;
}
.env-check-item {
  display: flex;
  align-items: center;
  padding: 12px 14px;
  border-bottom: 1px solid rgba(0,0,0,0.05);
  border-radius: 8px;
  transition: background 0.2s ease, transform 0.2s ease;
}
.env-check-item:hover {
  background: rgba(25,118,210,0.04);
}
.env-check-icon {
  margin-right: 15px;
  font-size: 1.5rem;
  min-width: 30px;
  transition: transform 0.3s ease;
}
.env-check-item:hover .env-check-icon {
  transform: scale(1.15);
}
.check-success { color: var(--success); }
.check-danger { color: var(--error); }
.env-check-box h5 { color: var(--primary-dark); font-weight: 600; }
.terminal {
  background: #1a2639;
  color: #e0e0e0;
  font-family: 'Courier New', monospace;
  padding: 20px;
  border-radius: 8px;
  max-height: 400px;
  overflow-y: auto;
  margin-bottom: 20px;
  line-height: 1.6;
  font-size: 14px;
  box-shadow: inset 0 0 10px rgba(0,0,0,0.5);
}
.terminal-line {
  margin-bottom: 8px;
  white-space: pre-wrap;
  word-break: break-word;
}
.terminal-success { color: var(--success); }
.terminal-error { color: var(--error); }
.terminal-warning { color: var(--warning); }
.terminal-info { color: var(--secondary); }
.terminal-prompt { color: var(--primary-light); }
.credentials-box {
  background: rgba(25,118,210,0.05);
  border-left: 4px solid var(--primary);
  padding: 20px;
  border-radius: 8px;
  margin: 25px 0;
}
.credential-item {
  display: flex;
  align-items: center;
  padding: 12px 0;
  border-bottom: 1px dashed rgba(0,0,0,0.1);
}
.credential-item:last-child {
  border-bottom: none;
}
.credential-icon {
  font-size: 1.4rem;
  margin-right: 15px;
  color: var(--primary);
}
.credential-label {
  font-weight: 500;
  min-width: 120px;
  color: var(--text);
}
.credential-value {
  font-weight: 600;
  color: var(--text);
}
.btn-install {
  background: var(--gradient-main);
  border: none;
  padding: 10px 25px;
  font-weight: 500;
  min-width: 150px;
  transition: all 0.3s ease;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(25,118,210,0.25);
}
.btn-install:hover {
  background: var(--gradient-main);
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(25,118,210,0.35);
  color: #fff;
}
.btn-install:disabled {
  opacity: 0.7;
  transform: none;
}
.success-icon {
  font-size: 6rem;
  color: var(--success);
  margin: 20px 0;
  animation: bounceIn 0.8s;
  filter: drop-shadow(0 6px 16px rgba(0,200,83,0.35));
}
.security-alert {
  background: rgba(213,0,0,0.05);
  border-left: 4px solid var(--error);
  padding: 15px;
  border-radius: 8px;
  margin-top: 25px;
}
.form-control {
  padding: 12px 15px;
  border-radius: 8px;
  border: 1px solid rgba(0,0,0,0.1);
  transition: all 0.3s ease;
}
.form-control:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 0.25rem rgba(25,118,210,0.25);
}
.alert {
  border-radius: 8px;
  padding: 15px;
}
.section-heading { display:flex; align-items:center; gap:8px; margin:24px 0 16px; padding-bottom:10px; border-bottom:1px solid #edf1f5; color:var(--primary-dark); font-weight:600; }
.section-heading i { font-size:1.3rem; }
.section-heading small { margin-left:auto; color:var(--text-secondary); font-weight:400; }
.form-row { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
.terminal-container h5 { color: var(--primary-dark); font-weight: 600; }
.credentials-box h5 { color: var(--primary-dark); font-weight: 600; }
@media (max-width: 768px) {
  .step {
    padding: 0 15px;
  }
  .step-number {
    width: 40px;
    height: 40px;
    font-size: 1rem;
  }
  .card-header {
    padding: 20px;
  }
  .card-body {
    padding: 20px;
  }
  .form-row { grid-template-columns:1fr; gap:0; }
  .section-heading small { display:none; }
}
</style>
<style>
/* Installation-only layers keep the setup flow legible over the shared glass material. */
html { background: #101a35; }
body {
  position: relative;
  isolation: isolate;
  overflow-x: hidden;
  padding: clamp(18px, 4vw, 54px);
  background:
    radial-gradient(ellipse 70% 60% at 0% 0%, rgba(78, 132, 255, .62), transparent 68%),
    radial-gradient(ellipse 60% 70% at 100% 0%, rgba(93, 177, 255, .42), transparent 66%),
    radial-gradient(ellipse 70% 60% at 50% 110%, rgba(38, 208, 194, .32), transparent 70%),
    linear-gradient(145deg, #111b38 0%, #1c2850 48%, #102d46 100%);
}
body::before,
body::after {
  position: fixed;
  z-index: -1;
  display: block;
  pointer-events: none;
  content: "";
  border-radius: 999px;
  filter: blur(4px);
}
body::before {
  width: min(44vw, 560px);
  height: min(44vw, 560px);
  top: -18vw;
  right: 8vw;
  background: radial-gradient(circle at 35% 35%, rgba(255,255,255,.35), rgba(91, 178, 255, .18) 38%, transparent 70%);
  box-shadow: 0 0 100px rgba(113, 132, 255, .3);
}
body::after {
  width: min(35vw, 420px);
  height: min(35vw, 420px);
  bottom: -16vw;
  left: 8vw;
  background: radial-gradient(circle at 45% 40%, rgba(91, 238, 221, .3), transparent 68%);
}
.install-container { max-width: 1080px; }
.install-container > .card {
  position: relative;
  overflow: hidden;
  border: 1px solid rgba(255,255,255,.28) !important;
  border-radius: 30px;
  background: linear-gradient(135deg, rgba(255,255,255,.2), rgba(255,255,255,.07)) !important;
  box-shadow: 0 34px 100px rgba(2, 8, 28, .42), inset 0 1px 0 rgba(255,255,255,.44), inset 0 -1px 0 rgba(255,255,255,.08);
  -webkit-backdrop-filter: blur(34px) saturate(155%);
  backdrop-filter: blur(34px) saturate(155%);
}
.install-container > .card::before {
  position: absolute;
  inset: 1px;
  z-index: 0;
  pointer-events: none;
  content: "";
  border-radius: 29px;
  background: linear-gradient(115deg, rgba(255,255,255,.22), transparent 24%, transparent 74%, rgba(255,255,255,.1));
}
.card-header,
.card-body { position: relative; z-index: 1; }
.card-header {
  padding: clamp(30px, 5vw, 56px) 28px 38px;
  background: linear-gradient(135deg, rgba(104, 168, 255, .34), rgba(70, 195, 231, .22) 55%, rgba(69, 224, 211, .18)) !important;
  border-bottom: 1px solid rgba(255,255,255,.2) !important;
}
.card-header::before { background: rgba(255,255,255,.11); box-shadow: 0 0 35px rgba(255,255,255,.16); }
.card-header::after { background: rgba(99, 237, 222, .12); }
.card-title { letter-spacing: .03em; text-shadow: 0 2px 20px rgba(24, 35, 88, .28); }
.card-body { padding: clamp(24px, 4vw, 48px); color: #eaf0ff; }
.step-indicator { margin: 0 auto 42px; max-width: 760px; }
.step { padding: 0 clamp(9px, 2.5vw, 30px); }
.step-number {
  position: relative;
  z-index: 2;
  border: 1px solid rgba(255,255,255,.32);
  background: rgba(255,255,255,.12);
  color: rgba(255,255,255,.72);
  box-shadow: inset 0 1px rgba(255,255,255,.3), 0 8px 22px rgba(5, 12, 45, .2);
}
.step.active .step-number { background: linear-gradient(135deg, #5d9dff, #42c8e8); box-shadow: 0 0 0 6px rgba(111, 177, 255, .18), 0 12px 28px rgba(62, 137, 231, .3); }
.step.completed .step-number { background: linear-gradient(135deg, #36d8c4, #4aa7ff); box-shadow: 0 0 0 6px rgba(61, 218, 198, .14), 0 12px 28px rgba(37, 164, 183, .28); }
.step-title, .text-muted, .form-text, small.text-muted { color: rgba(226, 235, 255, .7) !important; }
.step.active .step-title { color: #fff; }
.step.completed .step-title { color: #a8ffed; }
.step-connector { left: calc(50% + 27px); z-index: 0; width: calc(100% - 54px); background: rgba(255,255,255,.16); }
.step.completed .step-connector { background: linear-gradient(90deg, #39d9c4, #6aafff); }
.alert,
.env-check-box,
.credentials-box,
.security-alert,
.section-heading,
.terminal-container {
  border-color: rgba(255,255,255,.18) !important;
}
.alert { color: #fff; background: rgba(255, 102, 137, .16) !important; border: 1px solid rgba(255, 154, 177, .3) !important; }
.env-check-box, .credentials-box, .terminal-container { background: rgba(9, 20, 52, .2); border-radius: 20px; padding: 22px; }
.env-check-item { border-bottom-color: rgba(255,255,255,.1); }
.env-check-item:hover { background: rgba(255,255,255,.08); }
.env-check-box h5, .credentials-box h5, .terminal-container h5, .section-heading { color: #f5f8ff; }
.check-success { color: #68f2d2; }
.check-danger { color: #ff91ad; }
.form-label { color: #f5f8ff; font-weight: 600; }
.form-control, .form-select {
  min-height: 48px;
  color: #f5f8ff !important;
  border: 1px solid rgba(255,255,255,.2) !important;
  background: rgba(7, 17, 47, .3) !important;
  box-shadow: inset 0 1px rgba(255,255,255,.1), 0 8px 22px rgba(5, 12, 45, .1);
}
.form-control::placeholder { color: rgba(226,235,255,.42); }
.form-control:focus, .form-select:focus { border-color: rgba(124, 176, 255, .85) !important; background: rgba(12, 27, 68, .46) !important; box-shadow: 0 0 0 4px rgba(94, 145, 255, .16), inset 0 1px rgba(255,255,255,.18); }
.form-select option { color: #17233b; }
.section-heading { padding-bottom: 13px; border-bottom: 1px solid rgba(255,255,255,.16); }
.terminal { color: #dff7ff; border: 1px solid rgba(133, 234, 255, .22); border-radius: 18px; background: rgba(3, 10, 29, .7); box-shadow: inset 0 0 30px rgba(0,0,0,.26), 0 14px 30px rgba(3, 10, 29, .16); }
.terminal-success { color: #68f2d2; }
.terminal-error { color: #ff91ad; }
.terminal-warning { color: #ffd27b; }
.terminal-info, .terminal-prompt { color: #8fc8ff; }
.credential-item { border-bottom-color: rgba(255,255,255,.12); }
.credential-label, .credential-value { color: #f5f8ff; }
.credential-icon { color: #8fb5ff; }
.btn-install, .btn-primary { color: #fff !important; border: 1px solid rgba(255,255,255,.28) !important; background: linear-gradient(135deg, #4d97ff, #38c4e5) !important; box-shadow: 0 14px 28px rgba(65, 145, 220, .25), inset 0 1px rgba(255,255,255,.5); }
.btn-install:hover, .btn-primary:hover { filter: brightness(1.08); }
.btn-outline-primary, .btn-outline-secondary { color: #eaf0ff; border-color: rgba(255,255,255,.32); background: rgba(255,255,255,.08); }
.btn-outline-primary:hover, .btn-outline-secondary:hover { color: #17233b; background: rgba(255,255,255,.78); border-color: rgba(255,255,255,.8); }
.success-icon { color: #69f2d5; filter: drop-shadow(0 8px 22px rgba(64, 225, 202, .34)); }
.security-alert { color: #ffe5a7; background: rgba(255, 177, 66, .12); border-left: 3px solid #ffc66d !important; }
hr { border-color: rgba(255,255,255,.16); opacity: 1; }
@media (max-width: 560px) {
  body { align-items: flex-start; padding: 12px; }
  .install-container > .card { border-radius: 22px; }
  .card-body { padding: 18px 14px; }
  .step-indicator { margin-bottom: 28px; }
  .step { padding: 0 5px; }
  .step-number { width: 36px; height: 36px; font-size: .9rem; }
  .step-title { max-width: 70px; font-size: .72rem; }
  .step-connector { left: calc(50% + 19px); width: calc(100% - 38px); }
  .env-check-box, .credentials-box, .terminal-container { padding: 15px; }
  .d-flex.justify-content-between { gap: 12px; }
  .d-flex.justify-content-between .btn { flex: 1; }
}
@supports not ((backdrop-filter: blur(1px))) {
  .install-container > .card { background: #263763 !important; }
}
</style>
<style>
/* Keep the wizard light and high-contrast while retaining the layered glass finish. */
html { background: #eaf4ff; }
body {
  color: #20304d;
  background:
    radial-gradient(ellipse 70% 60% at 0% 0%, rgba(91, 169, 255, .34), transparent 68%),
    radial-gradient(ellipse 60% 70% at 100% 0%, rgba(102, 190, 255, .25), transparent 66%),
    radial-gradient(ellipse 70% 60% at 50% 110%, rgba(53, 218, 200, .2), transparent 70%),
    linear-gradient(145deg, #eaf3ff 0%, #f8f6ff 48%, #ebfbf8 100%);
}
body::before { background: radial-gradient(circle at 35% 35%, rgba(255,255,255,.72), rgba(103, 157, 255, .12) 38%, transparent 70%); box-shadow: 0 0 100px rgba(103, 157, 255, .2); }
body::after { background: radial-gradient(circle at 45% 40%, rgba(66, 210, 196, .2), transparent 68%); }
.install-container > .card {
  border-color: rgba(255,255,255,.78) !important;
  background: linear-gradient(135deg, rgba(255,255,255,.84), rgba(255,255,255,.55)) !important;
  box-shadow: 0 34px 100px rgba(49, 89, 145, .18), inset 0 1px 0 rgba(255,255,255,.95), inset 0 -1px 0 rgba(130, 162, 205, .12);
}
.install-container > .card::before { background: linear-gradient(115deg, rgba(255,255,255,.8), transparent 24%, transparent 74%, rgba(255,255,255,.38)); }
.card-header { background: linear-gradient(135deg, rgba(38, 125, 224, .96), rgba(44, 180, 225, .9) 56%, rgba(83, 208, 210, .86)) !important; }
.card-body { color: #20304d; }
.step-number { position: relative; z-index: 2; border-color: rgba(255,255,255,.92); background: #e0e9f5; color: #61718c; box-shadow: inset 0 1px rgba(255,255,255,.9), 0 8px 22px rgba(38, 82, 137, .14); }
.step.active .step-number { background: #5d9dff; color: #fff; }
.step.completed .step-number { background: #39cdb8; color: #fff; }
.step-connector { display: none !important; }
.step-title, .text-muted, .form-text, small.text-muted { color: #687a94 !important; }
.step.active .step-title { color: #2476cf; }
.step.completed .step-title { color: #159d8e; }
.step-connector { background: rgba(184, 199, 219, .7); }
.alert { color: #8d2948; background: rgba(255, 225, 233, .82) !important; border-color: rgba(226, 111, 143, .34) !important; }
.env-check-box, .credentials-box, .terminal-container { background: rgba(255,255,255,.34); }
.env-check-item { border-bottom-color: rgba(132, 157, 190, .16); }
.env-check-item:hover { background: rgba(255,255,255,.58); }
.env-check-box h5, .credentials-box h5, .terminal-container h5, .section-heading { color: #25446f; }
.check-success { color: #10ae93; }
.check-danger { color: #dc5475; }
.form-label { color: #294366; }
.form-control, .form-select { color: #20304d !important; border-color: rgba(119, 148, 188, .34) !important; background: rgba(255,255,255,.72) !important; box-shadow: inset 0 1px rgba(255,255,255,.95), 0 8px 22px rgba(43, 85, 135, .06); }
.form-control::placeholder { color: #8a9ab1; }
.form-control:focus, .form-select:focus { border-color: rgba(45, 134, 229, .72) !important; background: rgba(255,255,255,.92) !important; box-shadow: 0 0 0 4px rgba(45, 134, 229, .12), inset 0 1px rgba(255,255,255,.95); }
.section-heading { border-bottom-color: rgba(119, 148, 188, .2); }
.credential-item { border-bottom-color: rgba(119, 148, 188, .2); }
.credential-label, .credential-value { color: #294366; }
.credential-icon { color: #377fda; }
.btn-outline-primary, .btn-outline-secondary { color: #315477; border-color: rgba(91, 126, 171, .38); background: rgba(255,255,255,.58); }
.btn-outline-primary:hover, .btn-outline-secondary:hover { color: #21456e; background: rgba(255,255,255,.9); border-color: rgba(91, 126, 171, .58); }
.security-alert { color: #805c22; background: rgba(255, 244, 210, .72); border-left-color: #e9ad43 !important; }
hr { border-color: rgba(119, 148, 188, .22); }
@supports not ((backdrop-filter: blur(1px))) { .install-container > .card { background: rgba(255,255,255,.96) !important; } }
</style>
</head>
<body>
<div class="install-container">
  <div class="card">
    <header class="card-header">
      <div class="card-title">
        <i class="mdi mdi-cog-outline mr-2"></i>系统安装向导
      </div>
    </header>
    <div class="card-body">
      <div class="step-indicator">
        <?php foreach ($steps as $i => $step_info): ?>
        <div class="step <?= $step_info['active'] ? 'active' : '' ?> <?= $step_info['completed'] ? 'completed' : '' ?>">
          <div class="step-number"><?= $i ?></div>
          <div class="step-title"><?= $step_info['title'] ?></div>
          <?php if ($i < count($steps)): ?>
          <div class="step-connector"></div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php if ($error): ?>
      <div class="alert alert-danger">
        <i class="mdi mdi-alert-circle-outline"></i>
        <?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>
      <form method="post" id="install-form">
        <input type="hidden" name="action" value="<?php
            echo $step == STEP_CHECK_ENV ? 'check_env' : 
                 ($step == STEP_DB_CONFIG ? 'db_config' : 'install_db');
        ?>">
        <?php if ($step == STEP_CHECK_ENV): ?>
        <div class="env-check-box">
          <h5 class="mb-4"><i class="mdi mdi-server-security mr-2"></i>系统环境检测</h5>
          <ul class="env-check-list">
            <li class="env-check-item">
               <i class="mdi mdi-<?= version_compare(PHP_VERSION, '8.0.0', '>=') ? 'check-circle' : 'close-circle' ?> env-check-icon <?= version_compare(PHP_VERSION, '8.0.0', '>=') ? 'check-success' : 'check-danger' ?>"></i>
              <div>
                <strong>PHP版本</strong>
                 <p class="text-muted"><?= PHP_VERSION ?> (要求: 8.0.0+)</p>
              </div>
            </li>
            <?php
             $required_extensions = ['pdo', 'pdo_mysql', 'curl', 'openssl', 'mbstring', 'gd', 'zip'];
            foreach ($required_extensions as $ext): 
              $loaded = extension_loaded($ext);
            ?>
            <li class="env-check-item">
              <i class="mdi mdi-<?= $loaded ? 'check-circle' : 'close-circle' ?> env-check-icon <?= $loaded ? 'check-success' : 'check-danger' ?>"></i>
              <div>
                <strong><?= $ext ?>扩展</strong>
                <p class="text-muted"><?= $loaded ? '已安装' : '未安装' ?></p>
              </div>
            </li>
            <?php endforeach; ?>
            <?php
            $check_dirs = ['../', '../API'];
            $check_file_optional = '../config.php';
            $env_items = $check_dirs;
            $env_file_writable = true;
            if (file_exists($check_file_optional)) {
                $env_file_writable = is_writable($check_file_optional);
                $env_items[] = $check_file_optional;
            }
            foreach ($env_items as $dir):
              $writable = $dir === $check_file_optional ? $env_file_writable : is_writable($dir);
            ?>
            <li class="env-check-item">
              <i class="mdi mdi-<?= $writable ? 'check-circle' : 'close-circle' ?> env-check-icon <?= $writable ? 'check-success' : 'check-danger' ?>"></i>
              <div>
                <strong>目录权限</strong>
                <p class="text-muted"><?= $dir ?> (<?= $writable ? '可写' : '不可写' ?>)</p>
              </div>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php elseif ($step == STEP_DB_CONFIG): ?>
        <div class="section-heading"><i class="mdi mdi-database-outline"></i><span>数据库连接</span></div>
        <div class="form-group mb-4">
          <label class="form-label"><i class="mdi mdi-server-network mr-2"></i>数据库主机</label>
          <input class="form-control" type="text" name="db_host" value="<?= isset($_SESSION['db_config']['host']) ? htmlspecialchars($_SESSION['db_config']['host']) : '127.0.0.1' ?>" required>
          <small class="text-muted">通常是127.0.0.1或localhost</small>
        </div>
        <div class="form-group mb-4">
          <label class="form-label"><i class="mdi mdi-database mr-2"></i>数据库名称</label>
          <input class="form-control" type="text" name="db_name" value="<?= isset($_SESSION['db_config']['name']) ? htmlspecialchars($_SESSION['db_config']['name']) : '' ?>" required>
          <small class="text-muted">请确保数据库已存在</small>
        </div>
        <div class="form-group mb-4">
          <label class="form-label"><i class="mdi mdi-account mr-2"></i>数据库用户名</label>
          <input class="form-control" type="text" name="db_user" value="<?= isset($_SESSION['db_config']['user']) ? htmlspecialchars($_SESSION['db_config']['user']) : '' ?>" required>
        </div>
        <div class="form-group mb-4">
          <label class="form-label"><i class="mdi mdi-key mr-2"></i>数据库密码</label>
         <input class="form-control" type="password" name="db_pwd" value="<?= isset($_SESSION['db_config']['pwd']) ? htmlspecialchars($_SESSION['db_config']['pwd']) : '' ?>">
        </div>
        <div class="section-heading"><i class="mdi mdi-account-cog-outline"></i><span>管理员账户</span></div>
        <div class="form-row">
          <div class="form-group mb-4"><label class="form-label">登录账号</label><input class="form-control" type="text" name="admin_username" value="<?= htmlspecialchars($_SESSION['install_config']['admin_username'] ?? 'admin') ?>" required><small class="text-muted">2-32位字母、数字或下划线</small></div>
          <div class="form-group mb-4"><label class="form-label">管理员昵称</label><input class="form-control" type="text" name="admin_nickname" value="<?= htmlspecialchars($_SESSION['install_config']['admin_nickname'] ?? '管理员') ?>" required></div>
        </div>
        <div class="form-row">
          <div class="form-group mb-4"><label class="form-label">管理员邮箱</label><input class="form-control" type="email" name="admin_email" value="<?= htmlspecialchars($_SESSION['install_config']['admin_email'] ?? '') ?>" required placeholder="如 admin@example.com"><small class="text-muted">用于接收系统通知、推送提醒与找回密码，请填写可正常收信的邮箱</small></div>
          <div class="form-group mb-4"><label class="form-label">后台目录名</label><input class="form-control" type="text" name="admin_path" value="<?= htmlspecialchars($_SESSION['install_config']['admin_path'] ?? 'admin') ?>" required><small class="text-muted">安装后后台地址为 /此目录名/</small></div>
        </div>
        <div class="form-row">
          <div class="form-group mb-4"><label class="form-label">登录密码</label><input class="form-control" type="password" name="admin_password" required minlength="6"></div>
          <div class="form-group mb-4"><label class="form-label">确认密码</label><input class="form-control" type="password" name="admin_password_confirm" required minlength="6"></div>
        </div>
        <div class="form-row">
          <div class="form-group mb-4"><label class="form-label">管理员 QQ</label><input class="form-control" type="text" name="admin_qq" value="<?= htmlspecialchars($_SESSION['install_config']['admin_qq'] ?? '') ?>" placeholder="填写后显示QQ头像"><small class="text-muted">不填则使用默认QQ头像</small></div>
        </div>
        <div class="section-heading"><i class="mdi mdi-email-fast-outline"></i><span>SMTP 邮件配置</span><small>可留空，安装后可在后台设置</small></div>
        <div class="form-row">
          <div class="form-group mb-4"><label class="form-label">SMTP 主机</label><input class="form-control" type="text" name="mail_smtp_host" value="<?= htmlspecialchars($_SESSION['install_config']['smtp_host'] ?? '') ?>" placeholder="smtp.example.com"></div>
          <div class="form-group mb-4"><label class="form-label">SMTP 端口</label><input class="form-control" type="number" name="mail_smtp_port" value="<?= htmlspecialchars($_SESSION['install_config']['smtp_port'] ?? '465') ?>"></div>
        </div>
        <div class="form-row">
          <div class="form-group mb-4"><label class="form-label">加密方式</label><select class="form-control" name="mail_smtp_secure"><option value="ssl">SSL</option><option value="tls">TLS</option></select></div>
          <div class="form-group mb-4"><label class="form-label">SMTP 用户名</label><input class="form-control" type="text" name="mail_smtp_user" value="<?= htmlspecialchars($_SESSION['install_config']['smtp_user'] ?? '') ?>"></div>
        </div>
        <div class="form-group mb-4"><label class="form-label">SMTP 密码</label><input class="form-control" type="password" name="mail_smtp_pass" value="<?= htmlspecialchars($_SESSION['install_config']['smtp_pass'] ?? '') ?>"></div>
        <div class="section-heading"><i class="mdi mdi-shield-account-outline"></i><span>Cloudflare 人机验证</span><small>默认使用测试密钥，任何环境均可直接使用</small></div>
        <div class="form-row">
          <div class="form-group mb-4"><label class="form-label">Turnstile Site Key</label><input class="form-control" type="text" name="turnstile_site_key" value="<?= htmlspecialchars($_SESSION['install_config']['turnstile_site_key'] ?? '3x00000000000000000000FF') ?>"></div>
          <div class="form-group mb-4"><label class="form-label">Turnstile Secret Key</label><input class="form-control" type="text" name="turnstile_secret_key" value="<?= htmlspecialchars($_SESSION['install_config']['turnstile_secret_key'] ?? '1x0000000000000000000000000000000AA') ?>"></div>
        </div>
        <div class="text-muted small mb-3">当前默认使用 Cloudflare 官方测试密钥，任何环境均可通过验证。正式上线前请在 https://dash.cloudflare.com 创建 Turnstile 站点并替换为您自己的密钥</div>
        <?php elseif ($step == STEP_INSTALL_DB): ?>
        <div class="terminal-container">
          <h5 class="mb-3"><i class="mdi mdi-console-line mr-2"></i>安装终端</h5>
          <div class="terminal" id="install-terminal">
            <?php if (!empty($_SESSION['install_log'])): ?>
              <?php 
              $log_lines = explode("\n", $_SESSION['install_log']);
              foreach ($log_lines as $line): 
                $line = trim($line);
                if (empty($line)) continue;
                $class = 'terminal-line';
                if (strpos($line, '✓') === 0) {
                  $class .= ' terminal-success';
                } elseif (strpos($line, '✗') === 0 || strpos($line, '错误') !== false) {
                  $class .= ' terminal-error';
                } elseif (strpos($line, '⚠') === 0) {
                  $class .= ' terminal-warning';
                } elseif (strpos($line, '>') === 0) {
                  $class .= ' terminal-prompt';
                } else {
                  $class .= ' terminal-info';
                }
              ?>
              <div class="<?= $class ?>"><?= htmlspecialchars($line) ?></div>
              <?php endforeach; ?>
             <?php
             function check_github_host($host){if(function_exists('curl_init')){$ch=curl_init('https://'.$host);curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);curl_setopt($ch,CURLOPT_TIMEOUT,5);curl_setopt($ch,CURLOPT_NOBODY,true);curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);return $code>0&&$code<500;}else{$ctx=stream_context_create(['http'=>['timeout'=>5,'header'=>"User-Agent: huliapi-installer\r\n"],'ssl'=>['verify_peer'=>false,'verify_peer_name'=>false]]);$r=@get_headers('https://'.$host,0,$ctx);return is_array($r)&&preg_match('/200|301|302|403/',$r[0]);}}
             $gh_api=check_github_host('api.github.com');$gh_web=check_github_host('github.com');
             ?>
             <li class="env-check-item">
               <i class="mdi mdi-<?= $gh_api?'check-circle':'close-circle' ?> env-check-icon <?= $gh_api?'check-success':'check-danger' ?>"></i>
               <div><strong>GitHub API 连通性</strong><p class="text-muted">api.github.com (<?= $gh_api?'可连接':'无法连接' ?>) — 更新服务器</p></div>
             </li>
             <li class="env-check-item">
               <i class="mdi mdi-<?= $gh_web?'check-circle':'close-circle' ?> env-check-icon <?= $gh_web?'check-success':'check-danger' ?>"></i>
               <div><strong>GitHub 网站连通性</strong><p class="text-muted">github.com (<?= $gh_web?'可连接':'无法连接' ?>) — 源码仓库</p></div>
             </li>
            <?php else: ?>
              <div class="terminal-line terminal-prompt">> 准备开始安装...</div>
              <div class="terminal-line terminal-prompt">> 点击"开始安装"按钮继续</div>
            <?php endif; ?>
          </div>
        </div>
        <?php elseif ($step == STEP_COMPLETE): ?>
        <div class="text-center">
          <i class="mdi mdi-check-circle success-icon"></i>
          <h3 class="mt-3">安装成功！</h3>
          <p class="lead text-muted">系统已成功安装，您可以开始使用了</p>
          <div class="credentials-box">
            <h5 class="mb-4"><i class="mdi mdi-account-key mr-2"></i>管理员账号信息</h5>
            <div class="credential-item">
              <i class="mdi mdi-account-circle credential-icon"></i>
              <span class="credential-label">用户名</span>
               <span class="credential-value"><?= htmlspecialchars($_SESSION['installed_admin']['admin_username'] ?? '已设置') ?></span>
            </div>
            <div class="credential-item">
              <i class="mdi mdi-key credential-icon"></i>
              <span class="credential-label">初始密码</span>
               <span class="credential-value">安装时设置的密码</span>
            </div>
            <div class="credential-item">
              <i class="mdi mdi-alert-circle credential-icon" style="color: var(--warning);"></i>
              <span class="credential-label">安全提示</span>
              <span class="credential-value">请登录后立即修改密码</span>
            </div>
          </div>
          <div class="mt-4">
            <a href="../" class="btn btn-primary mr-3">
              <i class="mdi mdi-home mr-2"></i>前往首页
            </a>
            <a href="../<?= htmlspecialchars($_SESSION['installed_admin']['admin_path'] ?? 'admin') ?>/" class="btn btn-outline-primary">
              <i class="mdi mdi-settings mr-2"></i>前往后台
            </a>
          </div>
          <div class="security-alert mt-4">
            <h5><i class="mdi mdi-security mr-2"></i>安全提示</h5>
            <p class="mb-0">为了系统安全，请立即删除或重命名install目录</p>
          </div>
        </div>
        <?php endif; ?>
        <hr class="my-4">
        <div class="d-flex justify-content-between">
          <?php if ($step > STEP_CHECK_ENV && $step < STEP_COMPLETE): ?>
          <a href="?step=<?= $step-1 ?>" class="btn btn-outline-secondary">
            <i class="mdi mdi-arrow-left mr-2"></i>上一步
          </a>
          <?php else: ?>
          <span></span>
          <?php endif; ?>
          <?php if ($step < STEP_COMPLETE): ?>
          <button type="submit" class="btn btn-install" id="submit-btn">
            <?= $step == STEP_INSTALL_DB ? '开始安装' : '下一步' ?>
            <i class="mdi mdi-arrow-right ml-2"></i>
          </button>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>
</div>
<script src="../assets/js/jquery.min.js"></script>
<script>
$(document).ready(function() {
  $('form').on('submit', function() {
    $('#submit-btn').prop('disabled', true).html(
      $(this).find('input[name="action"]').val() === 'install_db' 
        ? '<i class="mdi mdi-loading mdi-spin mr-2"></i>安装中...' 
        : '<i class="mdi mdi-loading mdi-spin mr-2"></i>处理中...'
    );
  });
  var terminal = document.getElementById('install-terminal');
  if (terminal) {
    terminal.scrollTop = terminal.scrollHeight;
  }
});
</script>
</body>
</html>
<?php
}
showInstallPage($current_step, $error ?? null);
?>
