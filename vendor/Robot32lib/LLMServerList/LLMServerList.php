<?php
namespace Robot32lib\LLMServerList;

class LLMServerList{
    function getLoginFor($type){
        if($type=="fast"){
            $url = "https://openrouter.ai/api/v1/chat/completions"; 
            $OPENROUTER_API_KEY = $GLOBALS["OPENROUTER_API_KEY"];
            $headers = [
                "Authorization: Bearer $OPENROUTER_API_KEY",
                "Content-Type: application/json"
            ];
            return [["url"=>$url,"headers"=>$headers]];
        }
        else if($type=="smart"){
            $url = "https://openrouter.ai/api/v1/chat/completions";
            $OPENROUTER_API_KEY = $GLOBALS["OPENROUTER_API_KEY"];
            $headers = [
                "Authorization: Bearer $OPENROUTER_API_KEY",
                "Content-Type: application/json"
            ];
            return [["url"=>$url,"headers"=>$headers]];

        }
    }
}