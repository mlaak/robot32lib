<?php 
namespace Robot32lib\GPTlib;

class GPTlib{

    private $llm_try_list = [];
    private $url= "";
    private $headers = [];
    public $history = [];
    
    private $current_data = "";
    private $data_chunks = [];
    
    private $default_model = "";
    private $options = [];
    public $please_calc_cost = false;
    
    function __construct($url, $headers = null,$please_calc_cost=false){
        if(is_array($url)){
            $this->url = $url[0]['url'];
            $this->headers = $url[0]['headers'];
            $this->llm_try_list = $this->url;
            $this->default_model = $url[0]['model'];
        }
        else {
            $this->url = $url;
            $this->headers = $headers;
        }
        
        $this->please_calc_cost = $please_calc_cost;
        $this->current_data = "";
    }
    
    function setHistory($history, $prev = []){
        $this->history = array_merge($prev, $this->fixHistory($history));
    }

    function setOptions($options){
        $this->options = $options;
    }

    function curl_init(){return curl_init();}
    function curl_setopt($ch,$opt,$val){return curl_setopt($ch,$opt,$val);}
    function curl_exec($ch){return curl_exec($ch);}
    function curl_close($ch){return curl_close($ch);}
    function curl_error($ch){return curl_error($ch);}
    function curl_errno($ch){return curl_errno($ch);}
    
    function chat($query, $model=null, $options=[], $streaming_func=null){

        if($model==null)$model = $this->default_model;
        if($options==null || count($options)==0)$options = $this->options;
        
        // ********************** INIT CURL ***************************
        $ch = $this->curl_init();
        $this->curl_setopt($ch, CURLOPT_URL, $this->url);
        $this->curl_setopt($ch, CURLOPT_POST, 1);
        $this->curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        $this->curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
        $this->curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        $this->curl_setopt($ch, CURLOPT_TIMEOUT, 60);
       
       
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
            
            $this->curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($curl, $chunk) use($streaming_func) { 
                $this->processStreamingChunk($chunk,$streaming_func);
                return strlen($chunk); //required by CURLOPT_WRITEFUNCTION
            });    
        }
     
        // ******************** CURL EXECUTE *************************** 
        $this->curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $response = $this->curl_exec($ch);
  
        // ******************** CATCH ERRORS AND CLOSE CURL ************       
        $error_no = $this->curl_errno($ch);   
        $error = $error_no ? $this->curl_error($ch) : "";
        $this->curl_close($ch);
        
        if($streaming_func!==null && trim($this->current_data)!=""){  
             $this->processStreamingChunk("\n ",$streaming_func); //something left if buffer. Process that.
        }
        
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
        
        if(isset($data['error'])){
            $error = $data['error']['message'];
            $error_no = $data['error']['code'];
        }

        // ******************** RETURN ************************************
        return ["json"=>$response,"text"=>$text,"data"=>$data,"cost"=>$cost,"error"=>$error,"error_code"=>$error_no];
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
            if(substr($l,0,1)=="{") $json_str = $l;
            
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
        $ch = $this->curl_init();
        //yes link is hardcoded, it is a quick (but needed) addon. 
        //later (if it grows) you can seperate it to an addon module.       
        $this->curl_setopt($ch, CURLOPT_URL, "https://openrouter.ai/api/v1/generation?id=$generation_id");
        $this->curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        $this->curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
        $this->curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        $this->curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $response = $this->curl_exec($ch);
 
        $error = "";
        if ($this->curl_errno($ch)) {
            $error = $this->curl_error($ch); //cant do billing without it, this might be bad, throw?
            $this->curl_close($ch);
            return null;
        }
        $this->curl_close($ch);
        
        $data = json_decode($response,TRUE);
        
        if(isset($data['error']) && $retry>0){ //probably not yet ready, lets try again
            usleep(100000);
            return $this->openrouterCost($generation_id,$retry-1);    
        } 
       
        return $data['data']['total_cost'] ?? null;
    }

}
