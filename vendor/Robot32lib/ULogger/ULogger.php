<?php 
namespace Robot32lib\ULogger;

class ULogger{
    private $base_dir = "";
    function __construct($base_dir){
        $this->base_dir = $base_dir;
    }
    
    function log($query,$model,$response,$response_id,$tokens_in,$tokens_out,$cost){
        $BASE_DIR = $this->base_dir;
        $currentTime = time();
        $year = date('Y', $currentTime);
        $month = date('m', $currentTime);
        $day = date('d', $currentTime);
        $hour = date('H', $currentTime);
        $minute = date('i', $currentTime);
        $second = date('s', $currentTime);
        $time = "$year.$month.$day..$hour.$minute.$second";

        $filename = $time . "___" . microtime(true);
        $filename = str_replace(".", "_", $filename); // replace the decimal with an underscore
        file_put_contents($BASE_DIR."/collected_data/chats/".$filename.".txt", "Model: $model\n\n"."Query:\n".$query."\n\n\nResult:\n".$response."\n\nCost:".$cost);
    
        /*if(isset($_ENV['R_USER_ID'])){
            $userid = $_ENV['R_USER_ID'];
            $l = "$currentTime,$tokens_in,$tokens_out,$response_id,$cost";
            $l = str_pad($l,$tokens_in+$tokens_out);
            $l.="\n";
            if(!ctype_alnum($userid))exit("user id must be alphanumeric");
            file_put_contents($BASE_DIR."/working_data/tokensused/$userid.txt",$l,FILE_APPEND);    
        }*/
    
    
    
    }
    

}
