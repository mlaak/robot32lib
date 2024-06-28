<?php 
namespace Robot32lib/GPTlib;

class GPTlib{
    private $url= "";
    private $headers = [];
    public $history = [];
    
    private $current_data = "";
    private $data_chunks = [];
    
    
    public $please_calc_cost = false;
    
    function __construct($url, $headers,$please_calc_cost=false){
        $this->url = $url;
        $this->headers = $headers;
        $this->please_calc_cost = $please_calc_cost;
        $this->current_data = "";
    }
    
    function setHistory($history, $prev = []){
        $this->history = array_merge($prev, $this->fixHistory($history));
    }
    
    function chat($query, $model, $options=[], $streaming_func=null){

        // ********************** INIT CURL ***************************
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
       
       
        // *********************  QUERY AND HISTORY ********************* 
        $data = $options;
        $data["model"] = $model;       
        $data['messages'] = $this->history;
        
        if($query!==null && $query!==""){
            $data['messages'][] = ["role" => "user","content" => $query];
        }
        
        // *********************** STREAMING *******************
        if($streaming_func!==null){  
            $data['stream'] = true;  
            $this->data_chunks = [];
            $this->current_data = "";
            
            curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($curl, $chunk) use($streaming_func) { 
                $this->processStreamingChunk($chunk,$streaming_func);
                return strlen($chunk); //required by CURLOPT_WRITEFUNCTION
            });    
        }
     
        // ******************** CURL EXECUTE *************************** 
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $response = curl_exec($ch);
  
        // ******************** CATCH ERRORS AND CLOSE CURL ************       
        $error = curl_errno($ch) ? curl_error($ch) : "";
        curl_close($ch);
        
        // ******************* PROCESS AND PACK OUTPUT ******************
        if($streaming_func!==null){
            $response = "[".implode(",",$this->data_chunks)."]";            
            $data = $this->combineChunks($this->data_chunks);            
            $text = $data['choices'][0]['delta']['content'] ?? "";
        }
        else {
            $data = @json_decode($response,TRUE);
            $text = $data['choices'][0]['message']['content'] ?? "";
        }
            
        // ******************* CALCULATE COST *****************************
        $cost = null;
        if($this->please_calc_cost){
            if(substr($this->url, 0, strlen("https://openrouter.ai")) === "https://openrouter.ai"){
                if(isset($data['id']))$cost = $this->openrouterCost($data['id'],3);   
            }
        }

        // ******************** RETURN ************************************
        return ["json"=>$response,"text"=>$text,"data"=>$data,"cost"=>$cost,"error"=>$error];
    }
    

/**********************************************************************************************************   
********************************************* HELPER FUNCTIONS ******************************************** 
***********************************************************************************************************/  
    
    private function combineChunks($chunks){
        $data = [];
        foreach($chunks as $j){ //with streaming, needed data is across many chunks, lets merge them 
                $d = json_decode($j,TRUE);
                $data = $this->mergeArrays($data,$d,true);
        } //good, now the data for streaming is the similar format as non streaming
        return $data;
    }
    
    
    
    private function processStreamingChunk($chunk, $streaming_func){
        $this->current_data.=$chunk;

        $lines = explode("\n",$this->current_data);
        if($lines[count($lines)-1]!=""){ // got an incomplete line
            $this->current_data = array_pop($lines); // put it back, the rest will come later
        }
        else {
            array_pop($lines); //correct chunk ends with one empty line
            $this->current_data = "";
        }
        
        foreach($lines as $l){
            //echo $l."\n"; //if you want to see whats going on here
            $json_str = "";
            if(substr($l,0,5)=="data:") $json_str = substr($l,5);
            if(substr(trim($json_str),0,1)=='{') $this->data_chunks[] = $json_str; 
            
            $json = json_decode($json_str,true);
            $streaming_func($json["choices"][0]['delta']['content'] ?? null,$json);
        }

    }
    
    
    
    
    
    // like array_merge_recursive but will overwrite strings (if they are not empty)
    // $merge_content will force merging all the text for 'content' key
    private function mergeArrays($arr1,$arr2,$merge_content=false){
        
        foreach($arr2 as $key=>$val){
            if(!array_key_exists($key,$arr1)){
                $arr1[$key] = $val;
            }
            else if(is_string($val) && $val!==""){
                if($merge_content && $key=="content"){
                    $arr1[$key].=$val;
                }
                else $arr1[$key] = $val;
            }
            else if(is_array($val) && !is_array($arr1[$key])){
                $arr1[$key] = $val;
            }
            else if(is_array($val) && is_array($arr1[$key])){
                $arr1[$key] = $this->mergeArrays($arr1[$key],$val,$merge_content);
            }
        }
        
        return $arr1;
    }
    
    
    /*
        Take the chat history in a simple format and convert it to a format suitable for LLMs
    
       Input: [["user:Give me a number."],["assistant:6"]];    
       Output: [["role" => "user","content" => "Give me a number."],["role" => "assistant","content" => "6"]]
       
       Input: [["user:Give me a number."],["ai:6"]];    
       Output: [["role" => "user","content" => "Give me a number."],["role" => "assistant","content" => "6"]]
       
       Input: "<chats><chat>user:Give me a number.</chat><chat>robot:6</chat></chats>"
       Output: [["role" => "user","content" => "Give me a number."],["role" => "assistant","content" => "6"]]
       
       Input:    '{"chat": ["user:Give me a number.","robot:6"]}';   
       Or Input: '["user:Give me a number.","robot:6"]';   
       Or Input: "user:Give me a number.;;assistant:6"]'  
       Output: [["role" => "user","content" => "Give me a number."],["role" => "assistant","content" => "6"]] 
       
      */
    private function fixHistory($history){
        if($history===null)return [];
        
        // convert xml or json or just a "chat;;chat;;chat" to an array
        if(is_string($history)){
            $history = trim($history);
            if(substr($history,0,1)=="{" || substr($history,0,1)=="["){ //json
                $history = json_decode($history,TRUE);
            }
            else if(substr($history,0,1)=="<"){ //xml
                $xml = simplexml_load_string($history,"SimpleXMLElement",LIBXML_NOCDATA);
                $history = json_decode(json_encode($xml),TRUE); //obj->assoc
            }
            else {
                $history = explode(";;",$history);
            }
        }
        
        //if a parent key exist for the list, get rid of it (tends to come with xml)        
        if(!array_key_exists(0,$history) && count(array_keys($history))>0){
            $keys = array_keys($history);
            $history = $history[$keys[0]];
        } 
        
        // ["user:question","ai:answer", ...] -->  [["role"=>"user", "content"=>"question"],["role"=>"assistant", "content"=>"answer"], ...]
        foreach($history as $key=>$val){
            if(is_string($val)){
                $role_content = explode(":",$val,2);
                $role = $role_content[0];
                $content = $role_content[1] ?? "";
                
                if(strtolower($role)=="client" || strtolower($role)=="user") $role = "user";
                if(strtolower($role)=="ai" || strtolower($role)=="assistant")$role = "assistant"; 
                
                $history[$key] = ["role" => $role,"content" => $content];
            }
        }     

        return $history;
    }
    
     public function openrouterCost($generation_id,$retry=0){
        $ch = curl_init();
        //yes link is hardcoded, it is a quick (but needed) addon. 
        //later (if it grows) you can seperate it to an addon module.       
        curl_setopt($ch, CURLOPT_URL, "https://openrouter.ai/api/v1/generation?id=$generation_id");
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $response = curl_exec($ch);
 
        $error = "";
        if (curl_errno($ch)) {
            $error = curl_error($ch); //cant do billing without it, this might be bad, throw?
            curl_close($ch);
            return null;
        }
        curl_close($ch);
        
        $data = json_decode($response,TRUE);
        
        if(isset($data['error']) && $retry>0){ //probably not yet ready, lets try again
            usleep(100000);
            return $this->openrouterCost($generation_id,$retry-1);    
        } 
       
        return $data['data']['total_cost'] ?? null;
    }

}
