<?php
error_reporting(E_ALL);
header('Content-Type: text/plain');
$now = time();
if ($now & 1) {
	http_response_code(503); // -> 503 gilt als Fehler
}
echo "NOW:$now\n";
