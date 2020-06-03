<?php
//http://127.0.0.1/app/git/index.php?git=aaa/bbb.git
error_reporting(0);
require_once __DIR__ . "/funs.php";
$config = json_decode(file_get_contents(__DIR__ . "/config.json"), true);
if (!isset($_SERVER['PHP_AUTH_USER'])) {
    header('WWW-Authenticate: Basic realm="epii server git"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'shibai';
    exit;
} else {
    if (!($_SERVER['PHP_AUTH_USER'] == $config["username"] && $_SERVER['PHP_AUTH_PW'] == $config["password"])) {
        header('WWW-Authenticate: Basic realm="epii server git"');
        header('HTTP/1.0 401 Unauthorized');
        echo 'shibai';
        exit;
    }
}
server_start($config);
