<?php

if(PHP_SAPI == "cli"){
    for ($i = 1; $i < count($argv); $i++) {
        $arg = explode("=", $argv[$i]);
        $_REQUEST[trim($arg[0])] = trim($arg[1]);
    }
}

if(isset($_REQUEST['r_ression_id']) || isset($_COOKIE['r_ression_id'])){
    $sess_id = bin2hex(hex2bin($_REQUEST['r_ression_id'] ?? $_COOKIE['r_ression_id'])); //we make sure its hex
     
    if(file_exists("$BASE_DIR/working_data/sessions/$sess_id.txt")){
        $d = explode(",",file_get_contents("$BASE_DIR/working_data/sessions/$sess_id.txt"),2);
        
        $_ENV['R_USER_TYPE'] = trim($d[0]);
        if(!ctype_alnum($_ENV['R_USER_TYPE']))$_ENV['R_USER_TYPE']="";
                
        $_ENV['R_USER_ID'] = trim($d[1]);
        if(!ctype_alnum($_ENV['R_USER_ID']))$_ENV['R_USER_ID']="";
        
        $_ENV['R_USER_EMAIL'] = @trim($d[2]);        
    }
}

if(!function_exists("rheader")){
    function rheader(string $header, bool $replace = true, int $response_code = 0){
        return header($header,$replace,$response_code);
    }
}

if(!function_exists("rheaders_sent")){
    function rheaders_sent(string &$filename = null, int &$line = null){
        return headers_sent($filename,$line);
    }        
}

