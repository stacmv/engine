<?php
class EPager implements ArrayAccess, jsonSerializable
{
    protected $pager;
    protected $current;
    protected $count;        // number of pages
    protected $url_template;
    protected $url_params;
    
    public function __construct($url_template, $items_count, $items_per_page, $current_page, array $url_params=array(), $width = 10){
        $k = max(1, floor(($width-3)/2));
        $n = ceil($items_count/$items_per_page);

        $pager = array();
            
        if ($current_page <= $k){
            $k1 = $k-$current_page;
            $k2 = $width - $current_page -2;
        }elseif($n - $current_page <= $k){
            $k1 = $width - ($n-$current_page) -3;
            $k2 = 0;
        }else{
            $k1 = $k;
            $k2 = $k-1;
        }
        
            
            
        for($i = $current_page - $k1; $i <= $current_page+$k2; $i++){
            if ( ($i>1) && ($i<$n) ){
                $pager[] = $i;
            }
        }

        if ( (count($pager) > 1) && ($pager[count($pager)-1] < $n-1) ){
            $pager[] = "...";
        };
        $pager[] = $n;
        
        if ($pager[0] > 2){
            array_unshift($pager, "...");
        };
        if ($pager[0] != 1){
            array_unshift($pager, 1);
        };
        
        
        $this->pager   = $pager;
        $this->count   = $n;
        $this->current = $current_page;
        $this->url_template = $url_template;
        $this->url_params   = $url_params;
    }
    
    public function url($page){
        $data = $this->url_params;
        $data["page"] = $page;
        
        return glog_render_string($this->url_template, $data);
    }

    public function getUrl(){
        $url = array();
        // Previous link
        if ($this->current > 1){
            $url[$this->current - 1] = $this->url($this->current - 1);
        };
        // visible pages links
        foreach($this->pager as $p){
            if (is_numeric($p)){
                $url[$p] = $this->url($p);
            };
        };
        // next link
        if ($this->current < $this->count){
            $url[$this->current + 1] = $this->url($this->current + 1);
        };

        return $url;
    }
 
    /* ArrayAccess implementation */
    public function offsetSet($offset, $value) {
        
    }

    public function offsetExists($offset) {
        return isset($this->$offset);
        
    }

    public function offsetUnset($offset) {
    }

    public function offsetGet($offset) {
        
        if (isset($this->$offset)) {
            return $this->$offset;
        }else{

            if ($offset == "url"){
                return $this->getUrl();
            };

            if (DEV_MODE){
                dosyslog(__METHOD__.get_callee().": FATAL ERROR: Neither property '".$offset."' nor method '"."get".ucfirst($offset)."' are exists in class '".__CLASS__."'.");
                die("Code: ".__CLASS__."-".__LINE__."-".$offset);
            };
            return null;
        }
    }
    
    /* jsonSerializable implementation */
    public function jsonSerialize(){
        
        $res = array();
        foreach( get_class_vars(__CLASS__) as $property => $default_value){
            $res[$property] = $this->$property;
        };
        
        $res["url"] = $this->getUrl();
        
        return $res;

    }
}