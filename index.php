<?php
$installLockFile = __DIR__ . '/install/install.lock';
if (!file_exists($installLockFile)) {
    $configReady = file_exists(__DIR__ . '/config.php') && filesize(__DIR__ . '/config.php') > 0;
    if (!$configReady) {
        $isInstallPage = strpos($_SERVER['PHP_SELF'], 'install/') !== false;
        if (!$isInstallPage) {
            $installUrl = 'install/';
            header("Location: $installUrl");
            exit;
        }
    }
}
require_once 'config.php';
require_once 'common/TemplateManager.php';
TemplateManager::renderHome('index.php');