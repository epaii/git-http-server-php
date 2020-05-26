<?php


// uncomporess content
function gzBody($gzData)
{

    $encoding = isset($_SERVER['HTTP_CONTENT_ENCODING'])?$_SERVER['HTTP_CONTENT_ENCODING']:"";
    $gzip = ($encoding == 'gzip' || $encoding == 'x-gzip');
    if (!$gzip) {
        return $gzData;
    }
    $i = 10;
    $flg = ord(substr($gzData, 3, 1));
    if ($flg > 0) {
        if ($flg & 4) {
            list($xlen) = unpack('v', substr($gzData, $i, 2));
            $i = $i + 2 + $xlen;
        }
        if ($flg & 8) $i = strpos($gzData, "\0", $i) + 1;
        if ($flg & 16) $i = strpos($gzData, "\0", $i) + 1;
        if ($flg & 2) $i = $i + 2;
    }
    return gzinflate(substr($gzData, $i, -8));
}

function server_start($config)
{

    $writeLog = function ($obj) use ($config) {

        if(isset($config["debug"]) && $config["debug"])
        {
            if (is_scalar($obj)) {
                $msg = $obj;
            } else {
                $msg = var_export($obj,true);
            }
            file_put_contents($config["log_dir"]."/".date("Ymd").".log", $msg . PHP_EOL, FILE_APPEND);
        }
      
    };
    $uri = $_GET['git'];
    $name = explode(".git",$uri)[0].".git";
    $path =  $config["git_dir"].DIRECTORY_SEPARATOR.$name;
    $action = str_replace($name."/","",$uri);
    $writeLog("action:".$action);
    switch ($action) {
        case 'info/refs':
            $service = $_GET['service'];
            header('Content-type: application/x-' . $service . '-advertisement');
            $cmd = sprintf('git %s --stateless-rpc --advertise-refs %s', substr($service, 4), $path);
            $writeLog('cmd:' . $cmd);
            exec($cmd, $outputs);
            $serverAdvert = sprintf('# service=%s', $service);
            $length = strlen($serverAdvert) + 4;

            echo sprintf('%04x%s0000', $length, $serverAdvert);
            echo implode(PHP_EOL, $outputs);

            unset($outputs);
            break;
        case 'git-receive-pack':
        case 'git-upload-pack':
            $input = file_get_contents('php://input');

            // required to define the content's Content-type
            header(sprintf('Content-type: application/x-%s-result', $action));
            $input = gzBody($input);
            // writeLog("input:".$input);
            $cmd = sprintf('git %s --stateless-rpc %s', substr($action, 4), $path);
            $descs = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $writeLog('cmd:' . $cmd);
            $process = proc_open($cmd, $descs, $pipes);
            if (is_resource($process)) {
                fwrite($pipes[0], $input);
                fclose($pipes[0]);
                while (!feof($pipes[1])) {
                    $data = fread($pipes[1], 4096);
                    echo $data;
                }

                fclose($pipes[1]);
                fclose($pipes[2]);

                $return_value = proc_close($process);
            }

            // need to update server's /info/refs file when upload object
            if ($action == 'git-receive-pack') {
                $cmd = sprintf('git --git-dir %s update-server-info', $path);
                $writeLog('cmd:' . $cmd);
                exec($cmd);
            }
            break;
    }
}