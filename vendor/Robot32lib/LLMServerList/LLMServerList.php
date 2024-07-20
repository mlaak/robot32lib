<?php
namespace Robot32lib\LLMServerList;


class LLMServerList{
    function getLLMFor($type,$crud=null,$history=null,$query=null){
        return $this->getLoginFor($type,$crud,$history,$query);
    }    

    //deprecated - use getLLMFor
    function getLoginFor($type,$crud=null,$history=null,$query=null){
        $size = 0;
        if($crud!=null)$size+=strlen(var_export($crud,true));
        if($history!=null)$size+=strlen(var_export($history,true));
        if($query!=null)$size+=strlen(var_export($query,true));

        if($type=="fast"){
            $url = "https://openrouter.ai/api/v1/chat/completions"; 
            $OPENROUTER_API_KEY = $GLOBALS["OPENROUTER_API_KEY"];
            $headers = [
                "Authorization: Bearer $OPENROUTER_API_KEY",
                "Content-Type: application/json"
            ];
            //$model = "mistralai/mixtral-8x7b-instruct";
            $model = "mistralai/mistral-7b-instruct-v0.3";

            $options =  [
                "temperature"=> 1,  
                "max_tokens"=> 8024,
                "top_p"=> 1,        
                "stream"=> true,
                "stop"=> null];

            return [["url"=>$url,"headers"=>$headers,"model"=>$model,'options'=>$options]];
        }
        else if($type=="smart"){
            $url = "https://openrouter.ai/api/v1/chat/completions";
            $OPENROUTER_API_KEY = $GLOBALS["OPENROUTER_API_KEY"];
            $headers = [
                "Authorization: Bearer $OPENROUTER_API_KEY",
                "Content-Type: application/json"
            ];
            $model = "mistralai/mixtral-8x7b-instruct";
            $options =  [
                "temperature"=> 1,  
                "max_tokens"=> 8024,
                "top_p"=> 1,        
                "stream"=> true,
                "stop"=> null];
            return [["url"=>$url,"headers"=>$headers,"model"=>$model,'options'=>$options]];

        }
    }
}