<?php
class ChangesSet {
    private $to = array();
    private $from = array();
        
    public function __construct(array $to = array(), array $from = array() ){
        
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
