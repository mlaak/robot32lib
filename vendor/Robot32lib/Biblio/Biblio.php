<?php
namespace Robot32lib\Biblio;

class Biblio{
    public $dict = [];
    public $dir = "";
    function __construct($dir){
        $this->dir = $dir;
        $index = file_get_contents("$dir/0index.txt");
        $index = str_replace("\r","",$index);
        $lines = explode("\n",$index);
        foreach($lines as $l){
            $parts = explode("=",trim($l));
            if(count($parts)==2){
                $words = explode(",",trim($parts[0]));
                $file =  trim($parts[1]);
                foreach($words as $word){
                    $this->dict[$word] = $file;
                }
            }
        }
    }
    function parseText($text){
        $files = [];
       // $words = str_word_count($text,1);
        $words = preg_split("/[\s,\.!\?]+/", $text);
        $prev_word = "";
        foreach($words as $word){
            $word = strtolower($word);
            if(isset($this->dict[$word])){
                $files[$this->dict[$word]] = true;
            }
            if(isset($this->dict["$prev_word $word"])){
                $files[$this->dict["$prev_word $word"]] = true;
            }
            
            $prev_word = $word;
        }
        return array_keys($files);
    }

    function getWisdom($text){
        $files = $this->parseText($text);
        $data = [];
        foreach($files as $file){
            $d = trim(file_get_contents($this->dir."/".$file));
            $data[] = $d;
        }
        return implode("\n\n",$data)."\n\n";
    }



}
