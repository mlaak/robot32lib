<?php
namespace Robot32lib\LLMServerList;

//if(!in_array($model, ['mistralai/mixtral-8x7b-instruct',"mistralai/mixtral-8x22b-instruct"])){
//    echo "Model not supported!";    
//}

class LLMServerList{
    function getLoginFor($type){
        if($type=="fast"){
            $url = "https://openrouter.ai/api/v1/chat/completions"; 
            $OPENROUTER_API_KEY = $GLOBALS["OPENROUTER_API_KEY"];
            $headers = [
                "Authorization: Bearer $OPENROUTER_API_KEY",
                "Content-Type: application/json"
            ];
            $model = "mistralai/mixtral-8x7b-instruct";
            return [["url"=>$url,"headers"=>$headers,"model"=>$model]];
        }
        else if($type=="smart"){
            $url = "https://openrouter.ai/api/v1/chat/completions";
            $OPENROUTER_API_KEY = $GLOBALS["OPENROUTER_API_KEY"];
            $headers = [
                "Authorization: Bearer $OPENROUTER_API_KEY",
                "Content-Type: application/json"
            ];
            $model = "mistralai/mixtral-8x7b-instruct";
            return [["url"=>$url,"headers"=>$headers,"model"=>$model]];

        }
    }
}