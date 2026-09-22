<?php
if (!defined('HULI_UPDATER_LIB')) { define('HULI_UPDATER_LIB', 1); }

function huli_updater_logs_dir() {
    static $ready = false;
    $dir = dirname(__DIR__) . '/logs';
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    if (!$ready && is_dir($dir)) {
        $ready = true;
        $htaccess = $dir . '/.htaccess';
        if (!is_file($htaccess)) {
            @file_put_contents($htaccess, "Require all denied\nDeny from all\n");
        }
        $index = $dir . '/index.html';
        if (!is_file($index)) { @file_put_contents($index, ''); }
    }
    return $dir;
}

function huli_updater_safe_token($token) {
    return preg_replace('/[^a-f0-9]/', '', (string)$token);
}

function huli_updater_new_token() {
    try { return bin2hex(random_bytes(16)); }
    catch (Exception $e) { return md5(uniqid('', true) . mt_rand()); }
}

function huli_updater_task_file($token) {
    return huli_updater_logs_dir() . '/update_task_' . huli_updater_safe_token($token) . '.json';
}

function huli_updater_status_file($token) {
    return huli_updater_logs_dir() . '/update_status_' . huli_updater_safe_token($token) . '.json';
}

function huli_updater_worker_log_file($token) {
    return huli_updater_logs_dir() . '/update_worker_' . huli_updater_safe_token($token) . '.log';
}

function huli_updater_detail_log_file($token) {
    return huli_updater_logs_dir() . '/update_detail_' . huli_updater_safe_token($token) . '.log';
}

function huli_updater_detail_log($token, $line) {
    @file_put_contents(huli_updater_detail_log_file($token), '[' . date('H:i:s') . '] ' . $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function huli_updater_detail_log_clear($token) {
    @unlink(huli_updater_detail_log_file($token));
}

function huli_updater_lock_file($token) {
    return huli_updater_logs_dir() . '/update_' . huli_updater_safe_token($token) . '.lock';
}

function huli_updater_write_json($file, array $data) {
    $tmp = $file . '.tmp';
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    if ($json === false) { return false; }
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) { return false; }
    if (!@rename($tmp, $file)) { @unlink($tmp); return false; }
    return true;
}

function huli_updater_read_json($file) {
    if (!is_file($file)) { return null; }
    $raw = @file_get_contents($file);
    if ($raw === false || $raw === '') { return null; }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function huli_updater_write_status($token, array $patch) {
    $token = huli_updater_safe_token($token);
    if ($token === '') { return false; }
    $file = huli_updater_status_file($token);
    $current = huli_updater_read_json($file);
    if (!is_array($current)) { $current = []; }
    $data = array_merge($current, $patch);
    $data['updated_at'] = time();
    $data['token'] = $token;

    // 重要状态落库：既写文件(兼容 worker/旧版)，也写入数据库 huli_updater_jobs
    huli_updater_persist_status_db($token, $data);

    return huli_updater_write_json($file, $data);
}

/**
 * 将更新任务状态写入数据库（幂等 upsert，失败静默回退到文件不影响功能）。
 */
function huli_updater_persist_status_db($token, array $data) {
    try {
        $pdo = huli_updater_make_pdo();
        if (!$pdo) { return; }
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($json === false || strlen($json) > 100000) { return; }
        $stmt = $pdo->prepare("INSERT INTO huli_updater_jobs (token, payload, updated_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE payload = VALUES(payload), updated_at = VALUES(updated_at)");
        $stmt->execute([$token, $json, time()]);
    } catch (Throwable $e) {
        error_log('[updater] 状态落库失败: ' . $e->getMessage());
    }
}

/**
 * 更新完成时把结果写入 huli_update_history 便于后台审计（失败静默）。
 */
function huli_updater_record_history_db($token, array $data) {
    $status = (string)($data['status'] ?? '');
    if (!in_array($status, ['success', 'failed', 'finished', 'error'], true)) { return; }
    try {
        $pdo = huli_updater_make_pdo();
        if (!$pdo) { return; }
        $stmt = $pdo->prepare("INSERT INTO huli_update_history (token, new_version, old_version, result, detail, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $token,
            (string)($data['version'] ?? ''),
            (string)($data['old_version'] ?? ''),
            ($status === 'success' || $status === 'finished') ? 'success' : 'failed',
            mb_substr((string)($data['message'] ?? ''), 0, 2000),
        ]);
    } catch (Throwable $e) {
        error_log('[updater] 历史入库失败: ' . $e->getMessage());
    }
}

function huli_updater_read_status($token) {
    $token = huli_updater_safe_token($token);
    if ($token === '') { return null; }
    $status = huli_updater_read_json(huli_updater_status_file($token));
    if (!is_array($status)) { return null; }
    $age = !empty($status['updated_at']) ? (time() - (int)$status['updated_at']) : 0;
    $queued_stuck = (isset($status['stage']) && $status['stage'] === 'queued' && $age > 90);
    $status['stale'] = (!empty($status['status']) && $status['status'] === 'running' && ($queued_stuck || $age > 900));
    return $status;
}

function huli_updater_find_root($dir) {
    if (!is_dir($dir)) { return $dir; }
    $entries = scandir($dir);
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') { continue; }
        $p = $dir . '/' . $entry;
        if (is_dir($p) && file_exists($p . '/index.php') && is_dir($p . '/admin')) {
            return $p;
        }
    }
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..' || !is_dir($dir . '/' . $entry)) { continue; }
        return $dir . '/' . $entry;
    }
    return $dir;
}

function huli_updater_rrmdir($dir) {
    if (!is_dir($dir)) { return; }
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') { continue; }
        $path = $dir . '/' . $item;
        if (is_dir($path)) { huli_updater_rrmdir($path); } else { @unlink($path); }
    }
    @rmdir($dir);
}

function huli_updater_download_urls($url) {
    $urls = [$url];
    if (preg_match('#^https://github\.com/([^/]+/[^/]+)/archive/refs/heads/(.+)\.zip$#', $url, $m)) {
        $urls[] = 'https://codeload.github.com/' . $m[1] . '/zip/refs/heads/' . $m[2];
    }
    return array_values(array_unique($urls));
}

function huli_updater_download_once($url, $dest, $progress = null) {
    $fp = @fopen($dest, 'w+b');
    if (!$fp) { throw new Exception('无法创建临时文件，请检查临时目录权限。'); }
    $ch = curl_init(str_replace(' ', '%20', $url));
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_FAILONERROR, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 600);
    curl_setopt($ch, CURLOPT_LOW_SPEED_LIMIT, 1024);
    curl_setopt($ch, CURLOPT_LOW_SPEED_TIME, 60);
    curl_setopt($ch, CURLOPT_USERAGENT, 'huliapi-updater');
    if (is_callable($progress)) {
        curl_setopt($ch, CURLOPT_NOPROGRESS, false);
        curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function ($res, $dltotal, $dlnow) use ($progress) {
            if ($dltotal > 0) { $progress((int)floor($dlnow * 100 / $dltotal)); }
            return 0;
        });
    } else {
        curl_setopt($ch, CURLOPT_NOPROGRESS, true);
    }
    $ok = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    fclose($fp);
    if (!$ok || $http < 200 || $http >= 300) {
        @unlink($dest);
        $msg = $err !== '' ? $err : ('HTTP ' . $http);
        if ($http >= 400) { $msg = 'HTTP ' . $http . '（可能是 GitHub 限流或链接失效）'; }
        throw new Exception('下载更新包失败: ' . $msg);
    }
    if (@filesize($dest) <= 0) { @unlink($dest); throw new Exception('下载的更新包为空，请稍后重试。'); }
}

function huli_updater_download($url, $dest, $progress = null) {
    $urls = huli_updater_download_urls($url);
    $last = '';
    foreach ($urls as $index => $u) {
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                huli_updater_download_once($u, $dest, $progress);
                return;
            } catch (Exception $e) {
                $last = $e->getMessage();
                if ($attempt === 0) { usleep(600000); }
            }
        }
    }
    throw new Exception($last !== '' ? $last : '下载更新包失败。');
}

function huli_updater_has_unzip_command() {
    if (!function_exists('exec')) { return false; }
    static $available = null;
    if ($available !== null) { return $available; }
    $out = [];
    $code = 1;
    @exec('command -v unzip 2>/dev/null', $out, $code);
    $available = ($code === 0 && !empty($out));
    return $available;
}

function huli_updater_unzip($zip, $extract) {
    if (!@mkdir($extract, 0755, true) && !is_dir($extract)) {
        throw new Exception('无法创建临时解压目录。');
    }
    if (class_exists('ZipArchive')) {
        $za = new ZipArchive;
        if ($za->open($zip) !== true) { throw new Exception('无法打开更新包文件。'); }
        $ok = $za->extractTo($extract);
        $za->close();
        if (!$ok) { throw new Exception('解压更新包失败。'); }
        return;
    }
    if (huli_updater_has_unzip_command()) {
        $cmd = 'unzip -oq ' . escapeshellarg($zip) . ' -d ' . escapeshellarg($extract) . ' 2>&1';
        $out = [];
        $code = 1;
        @exec($cmd, $out, $code);
        if ($code === 0) { return; }
        throw new Exception('解压更新包失败：' . trim(implode(' ', array_slice($out, 0, 3))));
    }
    throw new Exception('服务器不支持 ZipArchive，也没有可用的 unzip 命令。请安装 php-zip 扩展后重试。');
}

function huli_updater_collect_plan($root, $target, $admin_path, $admin_redirect) {
    $protected = ['config.php', 'install.lock', 'admin/fanghong_switch.txt'];
    $files = [];
    $dirs = [];
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iter as $item) {
        $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));
        if ($relative === '') { continue; }
        if (in_array($relative, $protected, true)) { continue; }
        if (strpos($relative, 'install/') === 0) { continue; }
        if ($admin_redirect && ($relative === 'admin' || strpos($relative, 'admin/') === 0)) {
            $relative = $admin_path . substr($relative, 5);
        }
        $dest = $target . '/' . $relative;
        if ($item->isDir()) {
            $dirs[$dest] = true;
            continue;
        }
        $files[] = ['src' => $item->getPathname(), 'dest' => $dest, 'relative' => $relative];
    }
    return ['files' => $files, 'dirs' => array_keys($dirs)];
}

function huli_updater_apply($root, array $info, $progress = null, $token = '') {
    $target = dirname(__DIR__);
    $admin_path = (defined('ADMIN_PATH') && ADMIN_PATH !== '') ? ADMIN_PATH : 'admin';
    $admin_redirect = ($admin_path !== 'admin');

    $plan = huli_updater_collect_plan($root, $target, $admin_path, $admin_redirect);
    $files = $plan['files'];
    $total = count($files);
    $copied = 0;
    $skipped = 0;

    $made_dirs = [];
    foreach ($plan['dirs'] as $dir) {
        if (!is_dir($dir) && @mkdir($dir, 0755, true)) { $made_dirs[$dir] = true; }
    }

    $processed = 0;
    foreach ($files as $file) {
        $processed++;
        $dir = dirname($file['dest']);
        if (!isset($made_dirs[$dir])) {
            if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
            $made_dirs[$dir] = true;
        }
        $src = $file['src'];
        $dest = $file['dest'];
        if (is_file($dest) && @filesize($dest) === @filesize($src) && @md5_file($dest) === @md5_file($src)) {
            $skipped++;
            if ($token !== '') { huli_updater_detail_log($token, '跳过(无变化): ' . $file['relative']); }
        } else {
            if (!@copy($src, $dest)) {
                throw new Exception('复制文件失败: ' . $file['relative']);
            }
            @chmod($dest, 0644);
            $copied++;
            if ($token !== '') { huli_updater_detail_log($token, '已替换: ' . $file['relative']); }
        }
        if (is_callable($progress) && ($processed % 25 === 0 || $processed === $total)) {
            $progress($total > 0 ? (int)floor($processed * 100 / $total) : 100, $processed, $total);
        }
    }

    $repo = defined('SENLIN_CLIENT_REPO') ? SENLIN_CLIENT_REPO : 'huliaiya/huliapi';
    $repo_branch = defined('SENLIN_CLIENT_REPO_BRANCH') ? SENLIN_CLIENT_REPO_BRANCH : 'main';
    $update_branch = defined('SENLIN_CLIENT_UPDATE_BRANCH') ? SENLIN_CLIENT_UPDATE_BRANCH : 'miao';
    $new_version_content = "<?php\ndefine('SENLIN_CLIENT_VERSION', '" . addslashes($info['version']) . "');\n";
    if (!empty($info['published_at'])) {
        $new_version_content .= "define('SENLIN_CLIENT_RELEASE_DATE', '" . addslashes(date('Y-m-d', strtotime($info['published_at']))) . "');\n";
    }
    $new_version_content .= "define('SENLIN_CLIENT_REPO', '" . addslashes($repo) . "');\ndefine('SENLIN_CLIENT_REPO_BRANCH', '" . addslashes($repo_branch) . "');\ndefine('SENLIN_CLIENT_UPDATE_BRANCH', '" . addslashes($update_branch) . "');\n?>";
    if (@file_put_contents($target . '/common/version.php', $new_version_content) === false) {
        throw new Exception('无法自动更新本地版本号文件，请检查 /common/version.php 文件的权限。');
    }
    if (function_exists('opcache_invalidate')) { @opcache_invalidate($target . '/common/version.php', true); }

    $admin_msg = '';
    if ($admin_redirect) {
        $admin_msg = '检测到您的后台目录为 /' . $admin_path . '/，本次更新已自动将后台代码更新到该目录，无需手动处理。';
    }
    return [
        'admin_path_changed' => $admin_redirect,
        'admin_path' => $admin_path,
        'admin_msg' => $admin_msg,
        'copied' => $copied,
        'skipped' => $skipped,
        'total' => $total,
    ];
}

function huli_updater_run(array $info, $token) {
    $token = huli_updater_safe_token($token);
    $zip = rtrim(sys_get_temp_dir(), '/') . '/huli_update_' . $token . '.zip';
    $extract = rtrim(sys_get_temp_dir(), '/') . '/huli_extract_' . $token;
    $old_version = defined('SENLIN_CLIENT_VERSION') ? SENLIN_CLIENT_VERSION : '';
    $new_version = isset($info['version']) ? $info['version'] : '';
    $started_at = time();

    huli_updater_write_status($token, [
        'status' => 'running',
        'stage' => 'download',
        'percent' => 2,
        'message' => '正在下载更新包...',
        'version' => $new_version,
        'old_version' => $old_version,
        'started_at' => $started_at,
        'finished_at' => 0,
        'error' => '',
        'result' => null,
    ]);

    huli_updater_detail_log_clear($token);
    huli_updater_detail_log($token, '开始更新 ' . $old_version . ' -> ' . $new_version);
    huli_updater_detail_log($token, '下载更新包...');

    try {
        huli_updater_download($info['download_url'], $zip, function ($p) use ($token) {
            huli_updater_write_status($token, [
                'stage' => 'download',
                'percent' => max(2, min(45, 2 + (int)round($p * 0.43))),
                'message' => '正在下载更新包（' . $p . '%）...',
            ]);
        });
        huli_updater_detail_log($token, '下载完成.');
        huli_updater_write_status($token, ['stage' => 'extract', 'percent' => 55, 'message' => '正在解压更新文件...']);
        huli_updater_detail_log($token, '解压更新文件...');
        huli_updater_unzip($zip, $extract);
        huli_updater_detail_log($token, '解压完成.');

        huli_updater_write_status($token, ['stage' => 'apply', 'percent' => 70, 'message' => '正在应用更新...']);
        $root = huli_updater_find_root($extract);
        $apply = huli_updater_apply($root, $info, function ($p, $done, $total) use ($token) {
            huli_updater_write_status($token, [
                'stage' => 'apply',
                'percent' => max(70, min(98, 70 + (int)round($p * 0.28))),
                'message' => '正在应用更新（' . $done . '/' . $total . '）...',
            ]);
            if ($token !== '') { huli_updater_detail_log($token, '已处理 ' . $done . '/' . $total . ' 个文件'); }
        }, $token);

        $result = [
            'success' => true,
            'message' => '系统已成功更新到版本 ' . $new_version . '！',
            'version' => $new_version,
            'old_version' => $old_version,
            'admin_path_changed' => $apply['admin_path_changed'],
            'admin_path' => $apply['admin_path'],
            'admin_msg' => $apply['admin_msg'],
            'copied' => $apply['copied'],
            'skipped' => $apply['skipped'],
        ];
        huli_updater_detail_log($token, '更新完成: 成功更新到 ' . $new_version . ', 替换 ' . $apply['copied'] . ' 个文件, 跳过 ' . $apply['skipped'] . ' 个(无变化).');
    } catch (Exception $e) {
        huli_updater_detail_log($token, '更新失败: ' . $e->getMessage());
        $result = [
            'success' => false,
            'message' => $e->getMessage(),
            'version' => $new_version,
            'old_version' => $old_version,
            'admin_path_changed' => false,
            'admin_path' => (defined('ADMIN_PATH') && ADMIN_PATH !== '') ? ADMIN_PATH : 'admin',
            'admin_msg' => '',
        ];
    } finally {
        @unlink($zip);
        if (is_dir($extract)) { huli_updater_rrmdir($extract); }
    }

    huli_updater_write_status($token, [
        'status' => $result['success'] ? 'success' : 'failed',
        'stage' => 'finish',
        'percent' => 100,
        'message' => $result['message'],
        'finished_at' => time(),
        'error' => $result['success'] ? '' : $result['message'],
        'admin_path_changed' => $result['admin_path_changed'],
        'admin_path' => $result['admin_path'],
        'admin_msg' => $result['admin_msg'],
        'result' => $result,
        'notified' => false,
    ]);
    huli_updater_record_history_db($token, [
        'status' => $result['success'] ? 'success' : 'failed',
        'version' => $new_version,
        'old_version' => $old_version,
        'message' => $result['message'],
    ]);
    return $result;
}

function huli_updater_make_pdo() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";port=" . (defined('DB_PORT') ? DB_PORT : 3306) . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]
        );
        return $pdo;
    } catch (Throwable $e) {
        return null;
    }
}

function huli_updater_pick_admin_email(PDO $pdo, $admin_id = 0) {
    $admin_id = (int)$admin_id;
    if ($admin_id > 0) {
        try {
            $stmt = $pdo->prepare("SELECT email FROM huli_admins WHERE id = ?");
            $stmt->execute([$admin_id]);
            $email = trim((string)$stmt->fetchColumn());
            if ($email !== '') { return $email; }
        } catch (Exception $e) {
        }
    }
    try {
        $email = trim((string)$pdo->query("SELECT email FROM huli_admins WHERE email IS NOT NULL AND email <> '' ORDER BY id ASC LIMIT 1")->fetchColumn());
        if ($email !== '') { return $email; }
    } catch (Exception $e) {
    }
    return '';
}

function huli_updater_mail_body($site_name, array $result, array $info, $site_url) {
    $ok = !empty($result['success']);
    $old = isset($result['old_version']) ? $result['old_version'] : '';
    $new = isset($result['version']) ? $result['version'] : '';
    $color = $ok ? '#1a7f37' : '#c0392b';
    $title = $ok ? '系统更新成功' : '系统更新失败';
    $time = date('Y-m-d H:i:s');
    $message = isset($result['message']) ? $result['message'] : '';
    $admin_note = '';
    if (!empty($result['admin_path_changed']) && !empty($result['admin_msg'])) {
        $admin_note = '<p style="margin:10px 0;padding:10px 12px;background:#eef6ff;border:1px solid #cfe3ff;border-radius:8px;">' . htmlspecialchars($result['admin_msg'], ENT_QUOTES, 'UTF-8') . '</p>';
    }
    $copied = isset($result['copied']) ? (int)$result['copied'] : null;
    $skipped = isset($result['skipped']) ? (int)$result['skipped'] : null;
    $detail = '';
    if ($copied !== null) {
        $detail .= '<tr><td style="padding:6px 0;color:#666;">更新文件</td><td style="padding:6px 0;">写入 ' . $copied . ' 个，跳过 ' . $skipped . ' 个未变化文件</td></tr>';
    }
    return '<div style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Arial,sans-serif;max-width:560px;margin:0 auto;color:#222;">'
        . '<h2 style="color:' . $color . ';font-size:20px;margin:0 0 12px;">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h2>'
        . '<p style="margin:0 0 16px;line-height:1.7;">站点 <strong>' . htmlspecialchars($site_name, ENT_QUOTES, 'UTF-8') . '</strong> 的后台在线更新任务已结束。</p>'
        . '<table style="width:100%;border-collapse:collapse;font-size:14px;line-height:1.7;">'
        . '<tr><td style="padding:6px 0;color:#666;width:110px;">更新前版本</td><td style="padding:6px 0;">' . htmlspecialchars($old, ENT_QUOTES, 'UTF-8') . '</td></tr>'
        . '<tr><td style="padding:6px 0;color:#666;">更新后版本</td><td style="padding:6px 0;">' . htmlspecialchars($new, ENT_QUOTES, 'UTF-8') . '</td></tr>'
        . '<tr><td style="padding:6px 0;color:#666;">完成时间</td><td style="padding:6px 0;">' . $time . '</td></tr>'
        . $detail
        . '</table>'
        . '<p style="margin:16px 0;padding:12px 14px;background:#f7f7f9;border-radius:8px;line-height:1.7;">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>'
        . $admin_note
        . '<p style="margin:16px 0 0;font-size:13px;color:#888;">此邮件由系统自动发送，请勿直接回复。'
        . ($site_url !== '' ? '<br>站点地址：<a href="' . htmlspecialchars($site_url, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($site_url, ENT_QUOTES, 'UTF-8') . '</a>' : '')
        . '</p></div>';
}

function huli_updater_notify(PDO $pdo = null, $token, array $info, array $result, $admin_id = 0, $site_url = '') {
    $token = huli_updater_safe_token($token);
    if (!$pdo) { $pdo = huli_updater_make_pdo(); }
    if (!$pdo) {
        huli_updater_write_status($token, ['notified' => false, 'notify_message' => '数据库连接失败，未发送通知。']);
        return false;
    }
    $email = huli_updater_pick_admin_email($pdo, $admin_id);
    if ($email === '') {
        huli_updater_write_status($token, ['notified' => false, 'notify_message' => '管理员邮箱为空，未发送通知邮件。']);
        return false;
    }
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM huli_settings");
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Exception $e) {
        $settings = [];
    }
    if (empty($settings['mail_smtp_host']) || empty($settings['mail_smtp_user']) || empty($settings['mail_smtp_pass'])) {
        huli_updater_write_status($token, ['notified' => false, 'notify_message' => '站点未配置邮件，未发送通知邮件。']);
        return false;
    }
    require_once dirname(__DIR__) . '/common/mail.php';
    $site_name = isset($settings['site_name']) && $settings['site_name'] !== '' ? $settings['site_name'] : 'huliapi';
    $subject = '【' . $site_name . '】系统更新' . (!empty($result['success']) ? '成功' : '失败') . '通知';
    $body = huli_updater_mail_body($site_name, $result, $info, $site_url);
    $sent = false;
    try { $sent = (bool)send_mail($email, $subject, $body, $pdo); }
    catch (Exception $e) { $sent = false; }
    if ($sent) {
        huli_updater_write_status($token, ['notified' => true, 'notify_message' => '通知邮件已发送至 ' . $email]);
    } else {
        huli_updater_write_status($token, ['notified' => false, 'notify_message' => '通知邮件发送失败，请检查邮件设置。']);
    }
    return $sent;
}

function huli_updater_find_php_cli() {
    $candidates = [];
    if (PHP_SAPI === 'cli' && defined('PHP_BINARY') && PHP_BINARY) { $candidates[] = PHP_BINARY; }
    if (defined('PHP_BINDIR') && PHP_BINDIR) { $candidates[] = rtrim(PHP_BINDIR, '/') . '/php'; }
    $candidates[] = '/usr/bin/php';
    $candidates[] = '/usr/local/bin/php';
    $candidates[] = '/usr/bin/php8.2';
    $candidates[] = '/usr/local/bin/php8.2';
    $candidates[] = '/www/server/php/82/bin/php';
    foreach ($candidates as $php) {
        if ($php && @is_executable($php)) { return $php; }
    }
    return '';
}

function huli_updater_spawn($token) {
    $token = huli_updater_safe_token($token);
    $script = dirname(__DIR__) . '/cli/update_worker.php';
    if (!is_file($script)) { return false; }
    $php = huli_updater_find_php_cli();
    if ($php === '') { return false; }
    $log = huli_updater_worker_log_file($token);
    $base = escapeshellarg($php) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($token);
    $redir = ' >> ' . escapeshellarg($log) . ' 2>&1 < /dev/null';
    if (PHP_OS_FAMILY !== 'Windows' && function_exists('exec')) {
        @exec('nohup ' . $base . $redir . ' &', $out, $code);
        return true;
    }
    if (function_exists('proc_open')) {
        if (PHP_OS_FAMILY === 'Windows') {
            $cmd = $base;
            $spec = [0 => ['file', 'NUL', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']];
        } else {
            $cmd = $base . $redir;
            $spec = [0 => ['file', '/dev/null', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']];
        }
        $proc = @proc_open($cmd, $spec, $pipes);
        if (is_resource($proc)) { return true; }
    }
    return false;
}

function huli_updater_run_task($token, array $task, $site_url = '') {
    $token = huli_updater_safe_token($token);
    if ($token === '' || empty($task['info'])) { return ['success' => false, 'message' => '更新任务信息不完整。']; }

    $lock = huli_updater_lock_file($token);
    $fp = @fopen($lock, 'c');
    if ($fp && !@flock($fp, LOCK_EX | LOCK_NB)) {
        @fclose($fp);
        return ['success' => false, 'message' => '该更新任务正在执行中。'];
    }

    try {
        $result = huli_updater_run($task['info'], $token);
        huli_updater_notify(null, $token, $task['info'], $result, isset($task['admin_id']) ? (int)$task['admin_id'] : 0, $site_url);
    } catch (Throwable $e) {
        $result = ['success' => false, 'message' => '更新任务执行异常: ' . $e->getMessage(), 'version' => isset($task['info']['version']) ? $task['info']['version'] : ''];
        huli_updater_write_status($token, [
            'status' => 'failed',
            'stage' => 'finish',
            'percent' => 100,
            'message' => $result['message'],
            'finished_at' => time(),
            'error' => $result['message'],
            'result' => $result,
        ]);
    }

    if ($fp) { @flock($fp, LOCK_UN); @fclose($fp); }
    return $result;
}
