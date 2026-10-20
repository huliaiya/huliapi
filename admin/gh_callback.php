<?php
// Compatibility entry for older OAuth Apps still pointing at /admin/gh_callback.php.
// New installs should use the root entry /gh_callback.php.
require __DIR__ . '/../common/gh_connect_callback.php';
