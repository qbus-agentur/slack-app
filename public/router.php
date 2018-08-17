<?php
if (PHP_SAPI == 'cli-server') {
    error_reporting(-1);

    // To help the built-in PHP dev server, check if the request was actually for
    // something which should probably be served as a static file
    $url  = parse_url($_SERVER['REQUEST_URI']);
    $file = __DIR__ . $url['path'];
    if (is_file($file)) {
        return false;
    }
}
$_SERVER['SCRIPT_NAME'] = 'index.php';
include 'index.php';
