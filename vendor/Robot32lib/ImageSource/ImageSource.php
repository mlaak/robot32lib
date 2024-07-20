<?php 
namespace Robot32lib\ImageSource;

class ImageSource{
    private $files = [];
    function __construct(){
        $list = file_get_contents(__DIR__."/pictures.locations.txt");
        $list = str_replace("\n\r","\n",$list);
        $list = str_replace("\r","\n",$list);
        $list = explode("\n",$list);
        foreach($list as $l){
            $e = explode("|",$l);
            if(count($e)==3){
                $this->files[$e[0]] = [$e[1],$e[2]];
            }
        }               
    }
    function getDataFromArchive($startByte, $length) {
        $filename = __DIR__."/pictures.archive";
        $file = fopen($filename, "r");
        if ($file === false) {
            return false;
        }
        // Move the file pointer to the start byte
        fseek($file, $startByte, SEEK_SET);
        // Read the data from the file
        $data = fread($file, $length);
        fclose($file);
        return $data;
    }

    function getPicture($name){
        if(!isset($this->file[$name]))return null;
        $start = $this->file[$name][0];
        $len = $this->file[$name][1];
        $data = $this->getDataFromArchive($start,$len);        
    }

    function getRandomPicture($name_contains=[]){
        $all_pictures = array_keys($this->files);
        if(count($name_contains)==0){
            $picture_pool = $all_pictures;
        }
        else {
            $picture_pool = [];
            foreach($all_pictures as $pic){
                foreach($name_contains as $str){
                    if(strpos($pic,$str)!==false){
                        $picture_pool[] = $pic;
                        break;
                    }
                }
            }
        }
        srand();
        $r = random_int(0,count($picture_pool)-1);
        return getPicture( $picture_pool[$r]);
    }
}
