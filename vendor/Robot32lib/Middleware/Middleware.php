<?php

if(PHP_SAPI == "cli"){
    for ($i = 1; $i < count($argv); $i++) {
        $arg = explode("=", $argv[$i]);
        $_REQUEST[trim($arg[0])] = trim($arg[1]);
    }
}

if(isset($_REQUEST['r_ression_id'])){
    $sess_id = bin2hex(hex2bin($_REQUEST['r_ression_id'])); //we make sure its hex
     
    if(file_exists("$BASE_DIR/working_data/sessions/$sess_id.txt")){
        $d = explode(",",file_get_contents("$BASE_DIR/working_data/sessions/$sess_id.txt"),2);
        $_ENV['R_USER_ID'] = trim($d[0]);
        $_ENV['R_USER_EMAIL'] = trim($d[1]);        
    }
}
