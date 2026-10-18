<?php
// GitHub OAuth callback entry for user login.
$_GET['scope'] = 'user';
require __DIR__ . '/../../../common/gh_connect_callback.php';
