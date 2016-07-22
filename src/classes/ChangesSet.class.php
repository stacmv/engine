<?php
class ChangesSet {
    private $to = array();
    private $from = array();
    
    /**
     *  @throws Exception
     */
    public static function createFromString($to, $from = ""){
        /*
            Strings must follow format:
                "{key1}" = "{value1}"[\n
                "{key1}" = "{value1}"[\n
                ...]]
                
            [] - means "optional"
            {key} - string literal
            {value} - JSON literal
            "" - quotes are required
        */
        
        if ( ! $to ) throw new Exception("Parameter 'to' must be non-empty.");
        
        $str_to_arr = function($s){
            $a = explode("\n", $s);
            $a = array_reduce($a, function($a, $k_v_pair_str){
                $k_v_pair = explode("=", trim($k_v_pair_str));
                list($k, $v) = array_map("trim", $k_v_pair);
                
                $a[$k] = json_decode($v);
                return $a;
            }, array());
            return $a;
        };
        
        
        if (is_string($to)   && ! empty($to))   $changes_to   = $str_to_arr($to);
        if (is_string($from) && ! empty($from)) $changes_from = $str_to_arr($from);
        
        return new self($changes_to, $changes_from);
    }
    public function __construct(array $to = array(), array $from = null ){
        
        $this->to = $to;
        $this->from = $from;
        
    }
    
    public function &__get($key){
        if ( isset($this->$key) ) return $this->$key;
    }
    
    public function __set($key, array $value){
        if ( isset($this->$key) ) {
            $this->$key = $value;
        }
    }
    
}
