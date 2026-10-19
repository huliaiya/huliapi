<?php
// Verification code storage: the database row is authoritative, the session
// copy is only a fast path kept for backwards compatibility.
if (!defined('HULI_VERIFICATION_CODE_LIB')) { define('HULI_VERIFICATION_CODE_LIB', 1); }

function huli_verification_code_pdo(): ?PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) { return $pdo; }
    try {
        if (!defined('DB_HOST')) {
            $cfg = dirname(__DIR__) . '/config.php';
            if (file_exists($cfg)) { require_once $cfg; }
        }
        if (!defined('DB_HOST')) { return null; }
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';port=' . (defined('DB_PORT') ? DB_PORT : 3306)
            . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
            DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $pdo->exec("CREATE TABLE IF NOT EXISTS `huli_verification_codes` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `code_type` varchar(32) NOT NULL DEFAULT '',
            `email` varchar(255) NOT NULL DEFAULT '',
            `code_hash` char(64) NOT NULL DEFAULT '',
            `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
            `expires_at` int(10) unsigned NOT NULL DEFAULT 0,
            `created_at` int(10) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `idx_email_type` (`email`,`code_type`),
            KEY `idx_expires` (`expires_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $columns = $pdo->query("SHOW COLUMNS FROM `huli_verification_codes`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('attempts', $columns, true)) {
            $pdo->exec("ALTER TABLE `huli_verification_codes` ADD `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0 AFTER `code_hash`");
        }
        return $pdo;
    } catch (Throwable $e) {
        error_log('[verification_code] 数据库不可用: ' . $e->getMessage());
        return null;
    }
}

function huli_verification_code_store(string $type, string $email, string $code, int $ttl = 300): bool {
    $pdo = huli_verification_code_pdo();
    if (!$pdo) { return false; }
    try {
        $now = time();
        $pdo->prepare("DELETE FROM huli_verification_codes WHERE expires_at < ?")->execute([$now]);
        $pdo->prepare("INSERT INTO huli_verification_codes (code_type, email, code_hash, expires_at, created_at) VALUES (?, ?, ?, ?, ?)")
            ->execute([$type, strtolower(trim($email)), hash('sha256', strtolower(trim($code))), $now + $ttl, $now]);
        return true;
    } catch (Throwable $e) {
        error_log('[verification_code] 写入失败: ' . $e->getMessage());
        return false;
    }
}

function huli_verification_code_check(string $type, string $email, string $code): bool {
    $email = strtolower(trim($email));
    $code = strtolower(trim($code));
    if ($email === '' || $code === '') { return false; }
    $pdo = huli_verification_code_pdo();
    if (!$pdo) { return false; }
    try {
        $stmt = $pdo->prepare("SELECT id, code_hash, attempts FROM huli_verification_codes WHERE code_type = ? AND email = ? AND expires_at >= ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$type, $email, time()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { return false; }
        if ((int)$row['attempts'] >= 5) {
            $pdo->prepare("DELETE FROM huli_verification_codes WHERE id = ?")->execute([$row['id']]);
            return false;
        }
        if (!hash_equals((string)$row['code_hash'], hash('sha256', $code))) {
            $pdo->prepare("UPDATE huli_verification_codes SET attempts = attempts + 1 WHERE id = ?")->execute([$row['id']]);
            return false;
        }
        $pdo->prepare("DELETE FROM huli_verification_codes WHERE code_type = ? AND email = ?")->execute([$type, $email]);
        return true;
    } catch (Throwable $e) {
        error_log('[verification_code] 校验失败: ' . $e->getMessage());
        return false;
    }
}
