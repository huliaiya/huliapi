<?php
$installLockFile = __DIR__ . '/install/install.lock';
if (!file_exists($installLockFile)) {
    $isInstallPage = strpos($_SERVER['PHP_SELF'], 'install/') !== false;
    if (!$isInstallPage) {
        header("Location: install/");
        exit;
    }
}
require_once 'config.php';
require_once 'common/TemplateManager.php';
TemplateManager::renderHome('index.php');