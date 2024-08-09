<?php 
namespace Robot32lib\GPTlib;

class GPTlib{
    const choices = 'choices';
    const code = 'code';
    const content = 'content';
    const cost = 'cost';
    const data = 'data';
    const delta = 'delta';
    const error = 'error';
    const error_code = 'error_code';
    const headers = 'headers';
    const id = 'id';
    const json = 'json';
    const message = 'message';
    const messages = 'messages';
    const model = 'model';
    const options = 'options';
    const stream = 'stream';
    const text = 'text';
    const url = 'url';

    private $llm_try_list = [];
    private $url= "";
    private $headers = [];
    private $current_data = "";
    private $data_chunks = [];
    private $default_model = "";
    private $options = [];
    private $TEMP = [];

    private $partial = false;

    public $history = [];
    public $returned = [];
    public $abort_on_user_abort = true;
    
    function __construct($url=null, $headers = null){
        if(is_array($url)){
            $this->url = $url[0][self::url];
            $this->headers = $url[0][self::headers];
            $this->llm_try_list = $this->url;
            $this->default_model = $url[0][self::model];
        }
        else {
            $this->url = $url;
            $this->headers = $headers;
        }
        $this->current_data = "";
    }
    
    function setHistory($history, $prev = []){
        $this->history = array_merge($prev, $this->fixHistory($history));
    }
    function setOptions($options){ $this->options = $options; }
    function setPartial($partial){ $this->partial = $partial; }

    function chat($query, $model=null, $options=[], $streaming_func=null){
        TTD("Chat");
        $modnr = 0; 
        while(true){
            
            $ch = $this->chatStart($query, $model, $options, $streaming_func,$modnr);
            $response = TTX( $this->curl_exec($ch) );                  
            $r = $this->chatEnd($response,true);

            if($r[self::error_code]){
                TTD("Got error","error code",$r[self::error_code]);

                if(is_array($model) && $modnr<count($model)-1){
                    $modnr++;   

                    continue; //got error, lets try next model
                } 
            }
            break;
        }
        return TTX($r);
    }


    function chatStart($query, $model=null, $options=[], $streaming_func=null,$modnr=0){
        TTD("chatStart","query",$query,"model",$model,"options",$options);
        $url = $this->url;
        $headers = $this->headers;

        if($model==null)$model = $this->default_model;
       
        if($options==null || count($options)==0)$options = $this->options;
        $opts = [];
        $default = isset($model[$modnr][self::options]) ? $model[$modnr][self::options] : []; 


        //!-means important option (use to force overwrite)
        //!option over !default over option over default
        foreach($default as $k=>$v)if($k[0]!="!")$opts[$k] = $v;
        foreach($options as $k=>$v)if($k[0]!="!")$opts[$k] = $v;
        foreach($default as $k=>$v)if($k[0]=="!")$opts[substr($k,1)] = $v;
        foreach($options as $k=>$v)if($k[0]=="!")$opts[substr($k,1)] = $v;
        $options = $opts;
       
        if(is_array($model)){
            if(isset($model[$modnr][self::url]))$url = $model[$modnr][self::url];
            if(isset($model[$modnr][self::headers]))$headers = $model[$modnr][self::headers];
            if(isset($model[$modnr][self::model]))$model = $model[$modnr][self::model];
        }

        


        
        $this->TEMP = [];
        $this->TEMP["streaming_func"] = $streaming_func;
        $this->TEMP["options"] = $options;
        $this->TEMP["url"] = $url;
 
        // ********************** INIT CURL ***************************
        $ch = $this->curl_init();
        $this->TEMP["ch"] = $ch;
        $this->curl_setopt($ch, CURLOPT_URL, $url);
        $this->curl_setopt($ch, CURLOPT_POST, 1);
        $this->curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $this->curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
        $this->curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        $this->curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        $this->curl_setopt($ch, CURLOPT_LOW_SPEED_LIMIT, 1);
        $this->curl_setopt($ch, CURLOPT_LOW_SPEED_TIME, 5);
        
       
        curl_setopt($ch, CURLOPT_NOPROGRESS, false);
        curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function($ch,$dload_size=0,$dloaded=0,$upl_size=0,$uloaded=0){
            if($this->abort_on_user_abort && connection_aborted())return 1; //abort
            else return 0;
        });


       
        // *********************  QUERY AND HISTORY ********************* 
        $data = $options;
        $data[self::model] = $model;       

        //messages = history + current query
        $data[self::messages] = $this->history;
        if($query!==null && $query!==""){
            $data[self::messages][] = ["role" => "user","content" => $query];
            if($this->partial!==false){
                $data[self::messages][] = ["role" => "assistant","content" => $this->partial];
            }
        }
        
        // ************* STREAMING (response comes in pieces) *******************
        if($streaming_func!==null){  
            $data[self::stream] = true;  
            $this->data_chunks = [];
            $this->current_data = "";
            
            $this->curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($curl, $chunk) use($streaming_func) { 
                //this is called every time we get a partial response (piece) from server
                $this->processStreamingChunk($chunk,$streaming_func);
                
                return strlen($chunk); //required by CURLOPT_WRITEFUNCTION
            });    
        }
        
        // *********************** CULR POSTFIELDS *******************
        $this->curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        return $ch;
    }

    function chatEnd($response,$close_handle=true){
        TTD("Chatend","response",$response);
        $ch = $this->TEMP["ch"];
        $streaming_func = $this->TEMP["streaming_func"];

        // ******************** CATCH HTTP ERRORS AND CLOSE CURL ************       
        $error_no = TTX( $this->curl_errno($ch) );   
        $error = TTX( $error_no ? $this->curl_error($ch) : "" );
        if($close_handle)$this->curl_close($ch);
        
        // ******************** CLEAN UP IF NEEDED *******************
        if($streaming_func!==null && trim($this->current_data)!=""){  
             $this->processStreamingChunk("\n ",$streaming_func); //something left in buffer. Process that.
        }
        
        // ******************* PROCESS AND PACK RESULT ******************
        if($streaming_func!==null){
            //lets combine the streaming partial responses
            //so we get one response similar to non-streaming
            $response = "[".implode(",",$this->data_chunks)."]";            
            $data = $this->combineChunks($this->data_chunks);            
            $text = $data[self::choices][0][self::delta][self::content] ?? "";
        }
        else {
            $data = @json_decode($response,TRUE);
            $text = $data[self::choices][0][self::message][self::content] ?? "";
        }

        if(isset($data[self::error])){ //response ok, but error returned in response
            $error = TTX( $data[self::error][self::message] ); //could be out of money, etc..
            $error_no = TTX( $data[self::error][self::code] );
        }
            
        // ******************* CALCULATE COST IF DESIRED *****************************
        $cost = null;
        if(isset($this->TEMP['calc_cost']) && $this->TEMP['calc_cost']){
            if(substr($this->TEMP['url'], 0, strlen("https://openrouter.ai")) === "https://openrouter.ai"){
                if(isset($data['id']))$cost = $this->openrouterCost($data['id'],3);   
            }
        }
        
        // ******************** RETURN ************************************
        $this->returned = [ self::json=>$response,
                            self::text=>$text,
                            self::data=>$data,
                            self::cost=>$cost,
                            self::error=>$error,
                            self::error_code=>$error_no];
        return $this->returned;
    }

    
        

/**********************************************************************************************************   
********************************************* HELPER FUNCTIONS ******************************************** 
***********************************************************************************************************/  
    //overwrite if you want to test for example
    function curl_init(){return curl_init();}
    function curl_setopt($ch,$opt,$val){
        TTD("CURL SETOPT","opt",$opt,"val",$val);
        return curl_setopt($ch,$opt,$val);
    }
    function curl_exec($ch){
        TTD("CURL EXEC");
        return TTX(curl_exec($ch));
    }
    function curl_close($ch){
        TTD("CURL CLOSE");
        return curl_close($ch);
    }
    function curl_error($ch){
        TTD("CURL ERROR");
        return TTX(curl_error($ch));
    }
    function curl_errno($ch){
        TTD("CURL ERRNO");
        return TTX(curl_errno($ch));
    }    


    private function combineChunks($chunks){
        TTD("Combine chunks");
        $data = [];
        foreach($chunks as $j){ //with streaming, needed data is across many chunks, lets merge them 
                $d = json_decode($j,TRUE);
                $data = $this->mergeArrays($data,$d,true);
        } //good, now the data for streaming is the similar format as non streaming
        return $data;
    }
    
    private function processStreamingChunk($chunk, $streaming_func){
        TTD("Processing Streamm Chunk");
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
            TTD("Line","l",$l);
            //echo $l."\n"; //if you want to see whats going on here
            $json_str = "";
            if(substr($l,0,5)=="data:") $json_str = substr($l,5);
            if(substr($l,0,1)=="{") $json_str = $l;
            
            if(substr(trim($json_str),0,1)=='{') $this->data_chunks[] = $json_str; 
                   
            $json = json_decode($json_str,true);
            $content = TTX( $json[self::choices][0][self::delta][self::content] ?? null );

            if($content!==null && $content!=="")$streaming_func($content,$json);
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
                if($merge_content && $key==self::content){
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
        TTX("fixHistory","history",$history);
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
        return TTX($history);
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
