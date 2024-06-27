<?php

if(PHP_SAPI == "cli"){
    for ($i = 1; $i < count($argv); $i++) {
        $arg = explode("=", $argv[$i]);
        $_REQUEST[trim($arg[0])] = trim($arg[1]);
    }
}


