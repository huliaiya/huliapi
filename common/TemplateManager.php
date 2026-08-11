<?php
class TemplateManager {
    private static $pdo = null;
    private static function validFolder($folder, $type) {
        $folder = (string)$folder;
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $folder)) {
            return null;
        }
        $base = realpath(__DIR__ . '/../template/' . $type);
        $path = realpath(__DIR__ . '/../template/' . $type . '/' . $folder);
        return ($base && $path && strpos($path, $base . DIRECTORY_SEPARATOR) === 0 && is_dir($path)) ? $folder : null;
    }
    private static function getDb() {
        if (self::$pdo === null) {
            $dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            try {
                self::$pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                throw new PDOException($e->getMessage(), (int)$e->getCode());
            }
        }
        return self::$pdo;
    }
    public static function getActiveHomeTemplate() {
        try {
            $stmt = self::getDb()->prepare("SELECT folder FROM huli_site_home_templates WHERE is_active = 1 LIMIT 1");
            $stmt->execute();
            $result = $stmt->fetch();
            return self::validFolder($result['folder'] ?? '', 'home') ?: '1';
        } catch (Exception $e) {
            return '1';
        }
    }
    public static function getAllHomeTemplates() {
        try {
            $stmt = self::getDb()->query("SELECT * FROM huli_site_home_templates ORDER BY is_active DESC, name ASC");
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }
    public static function setActiveHomeTemplate($id) {
        try {
            self::getDb()->beginTransaction();
            $stmt = self::getDb()->prepare("UPDATE huli_site_home_templates SET is_active = 0");
            $stmt->execute();
            $stmt = self::getDb()->prepare("UPDATE huli_site_home_templates SET is_active = 1 WHERE id = ?");
            $success = $stmt->execute([$id]);
            self::getDb()->commit();
            return $success;
        } catch (Exception $e) {
            if (self::$pdo->inTransaction()) {
                self::$pdo->rollBack();
            }
            return false;
        }
    }
    public static function addHomeTemplate($name, $folder, $description = '') {
        try {
            $stmt = self::getDb()->prepare("INSERT INTO huli_site_home_templates (name, folder, description) VALUES (?, ?, ?)");
            return $stmt->execute([$name, $folder, $description]);
        } catch (Exception $e) {
            return false;
        }
    }
    public static function deleteHomeTemplate($id) {
        try {
            $stmt = self::getDb()->prepare("DELETE FROM huli_site_home_templates WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (Exception $e) {
            return false;
        }
    }
    public static function getActiveUserTemplate() {
        try {
            $stmt = self::getDb()->prepare("SELECT folder FROM huli_site_user_templates WHERE is_active = 1 LIMIT 1");
            $stmt->execute();
            $result = $stmt->fetch();
            return self::validFolder($result['folder'] ?? '', 'user') ?: '1';
        } catch (Exception $e) {
            return '1';
        }
    }
    public static function getAllUserTemplates() {
        try {
            $stmt = self::getDb()->query("SELECT * FROM huli_site_user_templates ORDER BY is_active DESC, name ASC");
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }
    public static function setActiveUserTemplate($id) {
        try {
            self::getDb()->beginTransaction();
            $stmt = self::getDb()->prepare("UPDATE huli_site_user_templates SET is_active = 0");
            $stmt->execute();
            $stmt = self::getDb()->prepare("UPDATE huli_site_user_templates SET is_active = 1 WHERE id = ?");
            $success = $stmt->execute([$id]);
            self::getDb()->commit();
            return $success;
        } catch (Exception $e) {
            if (self::$pdo->inTransaction()) {
                self::$pdo->rollBack();
            }
            return false;
        }
    }
    public static function addUserTemplate($name, $folder, $description = '') {
        try {
            $stmt = self::getDb()->prepare("INSERT INTO huli_site_user_templates (name, folder, description) VALUES (?, ?, ?)");
            return $stmt->execute([$name, $folder, $description]);
        } catch (Exception $e) {
            return false;
        }
    }
    public static function deleteUserTemplate($id) {
        try {
            $stmt = self::getDb()->prepare("DELETE FROM huli_site_user_templates WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (Exception $e) {
            return false;
        }
    }
    public static function getHomeTemplatePath() {
        return __DIR__ . '/../../template/home/' . self::getActiveHomeTemplate() . '/';
    }
    public static function getUserTemplatePath() {
        return __DIR__ . '/../../template/user/' . self::getActiveUserTemplate() . '/';
    }
    public static function getHomeTemplateUrl() {
        return '/template/home/' . self::getActiveHomeTemplate() . '/';
    }
    public static function getUserTemplateUrl() {
        return '/template/user/' . self::getActiveUserTemplate() . '/';
    }
    public static function renderUser($templateFile) {
        self::enforceActiveTemplate('user');
        $baseDir = realpath(__DIR__ . '/..');
        $activeTemplate = self::getActiveUserTemplate();
        $templatePath = $baseDir . '/template/user/' . $activeTemplate . '/' . $templateFile;
        $defaultPath = $baseDir . '/template/user/default/' . $templateFile;
        if (file_exists($templatePath)) {
            include $templatePath;
            return;
        }
        if (file_exists($defaultPath)) {
            include $defaultPath;
            return;
        }
        $availableTemplates = glob($baseDir . '/template/user/*', GLOB_ONLYDIR);
        $error = "模板加载失败\n\n";
        $error .= "尝试加载: ".htmlspecialchars($templateFile)."\n";
        $error .= "搜索路径:\n";
        $error .= "- 激活模板: ".htmlspecialchars($templatePath)."\n";
        $error .= "- 默认模板: ".htmlspecialchars($defaultPath)."\n\n";
        $error .= "当前存在的模板文件夹:\n";
        $error .= implode("\n", array_map('htmlspecialchars', $availableTemplates))."\n\n";
        $error .= "建议检查:\n";
        $error .= "1. 数据库huli_site_user_templates表中的folder字段值\n";
        $error .= "2. template/user/目录下的文件夹名称\n";
        $error .= "3. 文件权限(确保www-data可读)";
        throw new Exception($error);
    }
    public static function renderHome($templateFile) {
        self::enforceActiveTemplate('home');
        $baseDir = realpath(__DIR__ . '/..');
        $activeTemplate = self::getActiveHomeTemplate();
        $templatePath = $baseDir . '/template/home/' . $activeTemplate . '/' . $templateFile;
        $defaultPath = $baseDir . '/template/home/default/' . $templateFile;
        if (file_exists($templatePath)) {
            include $templatePath;
            return;
        }
        if (file_exists($defaultPath)) {
            include $defaultPath;
            return;
        }
        $availableTemplates = glob($baseDir . '/template/home/*', GLOB_ONLYDIR);
        $error = "模板加载失败\n\n";
        $error .= "尝试加载: ".htmlspecialchars($templateFile)."\n";
        $error .= "搜索路径:\n";
        $error .= "- 激活模板: ".htmlspecialchars($templatePath)."\n";
        $error .= "- 默认模板: ".htmlspecialchars($defaultPath)."\n\n";
        $error .= "当前存在的模板文件夹:\n";
        $error .= implode("\n", array_map('htmlspecialchars', $availableTemplates))."\n\n";
        $error .= "建议检查:\n";
        $error .= "1. 数据库huli_site_home_templates表中的folder字段值\n";
        $error .= "2. template/home/目录下的文件夹名称\n";
        $error .= "3. 文件权限(确保www-data可读)";
        throw new Exception($error);
    }
    public static function enforceActiveTemplate($templateType = 'home') {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return;
        }
        $requestUri = $_SERVER['REQUEST_URI'];
        if ($templateType === 'user') {
            $activeFolder = self::getActiveUserTemplate();
            $basePath = '/template/user/';
        } else {
            $activeFolder = self::getActiveHomeTemplate();
            $basePath = '/template/home/';
        }
        $pattern = '~^' . preg_quote($basePath, '~') . '(?!' . preg_quote($activeFolder, '~') . '/)([^/]+)/~';
        if (preg_match($pattern, $requestUri, $matches)) {
            $requestedFile = preg_replace($pattern, '', $requestUri);
            $redirectUrl = $basePath . $activeFolder . '/' . $requestedFile;
            $filePath = realpath(__DIR__ . '/../..' . $redirectUrl);
            if (file_exists($filePath)) {
                $ext = pathinfo($requestedFile, PATHINFO_EXTENSION);
                $staticExtensions = ['css', 'js', 'jpg', 'jpeg', 'png', 'gif', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'eot'];
                if (in_array(strtolower($ext), $staticExtensions)) {
                    header("Location: $redirectUrl", true, 301);
                    exit;
                }
                header("Location: $redirectUrl", true, 301);
                exit;
            }
        }
    }
}
