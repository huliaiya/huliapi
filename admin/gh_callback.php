<?php
// GitHub OAuth callback entry for admin login.
$_GET['scope'] = 'admin';
require __DIR__ . '/../common/gh_connect_callback.php';
