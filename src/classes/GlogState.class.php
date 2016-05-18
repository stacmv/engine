<?php
class GlogState implements ArrayAccess, jsonSerializable
{
    private $state;
    private $stateMeta;
    private $statesConfig;
    
       
    public function __construct($state_field, $state = null){
        
        $file = cfg_get_filename("settings", $state_field . ".tsv");
        if (file_exists($file)){
            $tsv  = import_tsv($file);
            $this->statesConfig = arr_index($tsv, "value");
            
            if ($state){
                $this->set($state);
            }else{
                $available_state_values = array_keys($this->statesConfig);
                $default_state = reset($available_state_values);  // default state is the first one in config.
                $this->set($default_state);
            };
        };
    }
    
    public function add($state_value){
        if (isset($this->statesConfig[$state_value])){
            $this->state = $this->state | $state_value;
            $this->stateMeta[$state_value] = $this->statesConfig[$state_value];
        };
        return $this;
    }
    public function config(){
        return $this->statesConfig;
    }
    public function has($state_value){
        return $this->state & $state_value;
    }
    public function remove($state_value){
        $this->state = $this->state & ~$state_value;
        unset($this->stateMeta[$state_value]);
        return $this;
    }    
    public function set($state_value){
        if (isset($this->statesConfig[$state_value])){
            $this->state = $state_value;
            $this->stateMeta = array($this->statesConfig[$state_value]);
        }
        return $this;
    }
    
    public function value(){
        return $this->state;
    }
    
    public function meta($key, $returnArray = false){
        
        $res = array_reduce($this->stateMeta, function($res, $meta_record) use ($key){
            if (!empty($meta_record[$key])){
                $res[] = $meta_record[$key];
            }
            return $res;
        }, array());
       
        if ($returnArray){
            return $res;
        }else{
            return implode(", ", $res);
        };
    }
    
    public function __toString(){
        return $this->meta("caption");
    }

    /* ArrayAccess implementation for model attributes */
    public function offsetSet($offset, $value) {
        $set_method_name = "set".ucfirst($offset);
        if (isset($this->data[$offset])) {
            $this->data[$offset] = $value;
        }elseif (method_exists($this, $set_method_name)){
            $this->$set_method_name($value);
        } else {
            $this->data["extra"][$offset] = $value;
        }
    }

    public function offsetExists($offset) {
        return isset($this->$offset) || !empty($this->meta($offset));
    }

    public function offsetUnset($offset) {

    }

    public function offsetGet($offset) {
        if ($offset == "state") return $this->state;
        
        $res = $this->meta($offset);
        
        if ($res){
            return $res;
        }else{

            if (DEV_MODE && !isset($this->statesConfig[0][$offset])){
                dosyslog(__METHOD__.get_callee().": FATAL ERROR: Neither property '".$offset."' nor method '"."get".ucfirst($offset)."' are exists in class '".__CLASS__."'.");
                die("Code: ".__CLASS__."-".__LINE__."-".$offset);
            };
            return null;
        }
    }
    
    /* jsonSerializable implementation */
    public function jsonSerialize(){
        
        return array(
            "value"   => $this->state,
            "caption" => $this->meta("caption"),
        );
        
    }
    
}