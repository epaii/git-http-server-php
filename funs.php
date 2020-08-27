<?php

// uncomporess content
function gzBody($gzData)
{

    $encoding = isset($_SERVER['HTTP_CONTENT_ENCODING']) ? $_SERVER['HTTP_CONTENT_ENCODING'] : "";
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
        if ($flg & 8) {
            $i = strpos($gzData, "\0", $i) + 1;
        }

        if ($flg & 16) {
            $i = strpos($gzData, "\0", $i) + 1;
        }

        if ($flg & 2) {
            $i = $i + 2;
        }

    }
    return gzinflate(substr($gzData, $i, -8));
}

function check($name, $config)
{
    if (!isset($_SERVER['PHP_AUTH_USER'])) {
        header('WWW-Authenticate: Basic realm="epii server git"');
        header('HTTP/1.0 401 Unauthorized');
        echo 'no auth';
        exit;
    } else {
        $ok = true;
        if (isset($config["username"]) && $config["username"]) {
            $ok = ($_SERVER['PHP_AUTH_USER'] == $config["username"]) && ($_SERVER['PHP_AUTH_PW'] == $config["password"]);
        } else if (isset($config["auth"])) {
            if (isset($config["auth"]["username"]) && $config["auth"]["username"]) {
                $ok = ($_SERVER['PHP_AUTH_USER'] == $config["auth"]["username"]) && ($_SERVER['PHP_AUTH_PW'] == $config["auth"]["password"]);
            } else if (!isset($config["auth"]['auth'][$name])) {
                $ok = false;
            } else {
                if (!in_array($_SERVER['PHP_AUTH_USER'], $config["auth"]['auth'][$name])) {
                    $ok = false;
                } else {
                    $ok = $_SERVER['PHP_AUTH_PW'] == $config["auth"]["user"][$_SERVER['PHP_AUTH_USER']];
                }

            }
        }

        if (!$ok) {
            header('WWW-Authenticate: Basic realm="epii server git"');
            header('HTTP/1.0 401 Unauthorized');
            echo 'shibai';
            exit;
        }
    }
}

function server_start($config)
{

    $writeLog = function ($obj) use ($config) {

        if (isset($config["debug"]) && $config["debug"]) {
            if (is_scalar($obj)) {
                $msg = $obj;
            } else {
                $msg = var_export($obj, true);
            }
            file_put_contents($config["log_dir"] . "/" . date("Ymd") . ".log", $msg . PHP_EOL, FILE_APPEND);
        }

    };
    $git_command = $config["git_command"];
    $uri = $_GET['git'];
    $name = explode(".git", $uri)[0] . ".git";
    check($name, $config);
    $path = $config["repos_dir"] . "/" . $name;

    $action = str_replace($name . "/", "", $uri);
    $writeLog("action:" . $action);
    switch ($action) {
        case 'info/refs':
            $service = $_GET['service'];
            $writeLog('service:' . $service);
            header('Content-type: application/x-' . $service . '-advertisement');
            $cmd = sprintf($git_command . ' %s --stateless-rpc --advertise-refs %s', substr($service, 4), $path);
            $writeLog('cmd:' . $cmd);
            exec($cmd, $outputs);
            //print_r($outputs);
            $serverAdvert = sprintf('# service=%s', $service);
            $length = strlen($serverAdvert) + 4;

            echo sprintf('%04x%s0000', $length, $serverAdvert);
            $content = implode("\n", $outputs);
            //$content= str_replace(base64_decode("AA==")," ",$content);
            echo $content;
            $writeLog('content:' . $content);
            unset($outputs);
            break;
        case 'git-receive-pack':
        case 'git-upload-pack':
            $input = file_get_contents('php://input');

            // required to define the content's Content-type
            header(sprintf('Content-type: application/x-%s-result', $action));
            $input = gzBody($input);
            // writeLog("input:".$input);
            $cmd = sprintf($git_command . ' %s --stateless-rpc %s', substr($action, 4), $path);
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
