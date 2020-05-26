<?php
//http://127.0.0.1/app/git/index.php?git=aaa/bbb.git
error_reporting(0);
require_once __DIR__."/funs.php";
$config = json_decode(file_get_contents(__DIR__."/config.json"),true);
server_start($config);