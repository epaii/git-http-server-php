<?php
//http://127.0.0.1/app/git/index.php?git=aaa/bbb.git
error_reporting(0);
require_once __DIR__ . "/funs.php";
$config = json_decode(file_get_contents(__DIR__ . "/config.json"), true);
if(isset($config["auth"])){
    if(is_string($config["auth"])){
        if(file_exists($config["auth"])){
            $config["auth"] = json_decode(file_get_contents($config["auth"]),true);
        }
    }
}
 
server_start($config);
