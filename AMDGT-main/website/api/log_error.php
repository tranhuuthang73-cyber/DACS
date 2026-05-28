<?php
$err = $_GET['e'] ?? 'No error';
file_put_contents('js_errors.txt', $err . PHP_EOL, FILE_APPEND);
echo 'OK';
?>
