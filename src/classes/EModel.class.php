<?php
abstract class EModel implements ArrayAccess, jsonSerializable, IteratorAggregate
{
    protected $common_fields = array("id", "created", "modified", "deleted");
    protected $default_state = 0;
    protected $state_field;
    protected $repo_name;
    protected $model_name;
    
    protected $fields;
    protected $data;
    protected $data_before_changes;
    
    protected $one2many; // array($db_table => null | ERepository::create($db_table)
    

    
    public function __construct(array $data = array()){
        static $one2many = null;
        
        $this->fields = Repository::fields($this->repo_name);
        $this->data = array();
        $db_fields = array_merge(array_keys($this->fields), $this->common_fields);
        foreach($data as $k=>$v){
            if (in_array($k, $db_fields)){
                $this->data[$k] = $v;
            }else{
                $this->data["extra"][$k] = $v;
            }
        };
        
        $this->data_before_changes = $this->data;
        
        $this->state_field = isset($this->fields[$this->model_name . "_state"]) ? $this->model_name . "_state" : (isset($this->fields["state"]) ? "state" : null);
        
        // Related tables from the same db
        $db_table = $this->repo_name;
        
        
        if (db_get_name($db_table) == db_get_table($db_table)){
            
            if (is_null($one2many)){
                $tables = db_get_tables_list($db_table, $skipHistory = true);

                $foreign_key = $this->model_name . "_id";
                $one2many = array_filter($tables, function($dbt) use ($foreign_key){
                    $fields = Repository::fields($dbt);
                    return isset($fields[$foreign_key]);
                });
                
                $one2many = array_map(function($repo_name){
                    return ERepository::create($repo_name);
                }, $one2many);
            };
            $this->one2many = $one2many;
        }
        
    }
    
    public function checkACL($right){
        if ( ! userHasRight("access,owner|manager", "", $this)) return false;

        return userHasRight($right,"", $this);
    }
    
    public static function prepare_view($itemData, $fields, $strict = false){
        
        static $tsv = array();
        
        $item = array();
        
        foreach($itemData as $key => $value){
            
            if ($strict && ! isset($fields[$key])) continue;
            
            $item[$key] = $value;
            
            if ( (substr($key,-3) == "_id") || (substr($key,-4) == "_ids") ){
                $obj_name = (substr($key,-4) == "_ids") ? substr($key, 0,-4) : substr($key, 0,-3);
                $get_name_function = "get_".$obj_name."_name";
                if (function_exists($get_name_function)){
                    if ($fields[$key]["type"] == "list"){
                        $item["_".$key] = array_map(function($v) use($get_name_function){
                            return $v ? call_user_func($get_name_function, $v) : "";
                        }, $value);
                    }else{
                        $item["_".$key] = $value ? call_user_func($get_name_function, $value) : "";
                    };
                };
            }elseif(isset($fields[$key]["form_values"]) && ($fields[$key]["form_values"] == "tsv")){
                $tsv_file = cfg_get_filename("settings", $key.".tsv");

                if ( ! isset($tsv[$key]) ){
                    $tsv[$key] = array();
                    $tmp = import_tsv( $tsv_file );
                    foreach($tmp as $v){
                        $tsv[$key][ isset($v["value"]) ? $v["value"] : $v[$key] ] = $v["caption"];
                    };
                    unset($tmp, $v);
                };
                    
                if ($fields[$key]["type"] == "list"){
                    $item["_".$key] = array_map(function($v)use($tsv, $key, $tsv_file){
                        if (isset($tsv[$key][$v])){
                            return $tsv[$key][$v];
                        }else{
                            dosyslog(__METHOD__.get_callee().": WARNING: Caption for value '".json_encode_array($v)."' of field '".$key."' is not defined in '".$tsv_file."'.");
                            return $v;
                        };
                    }, $value);
                }else{
                
                    if (isset($tsv[$key][$value])){
                        $item["_".$key] = $tsv[$key][$value];
                    }else{
                        dosyslog(__METHOD__.get_callee().": WARNING: Caption for value '".json_encode_array($value)."' of field '".$key."' is not defined in '".$tsv_file."'.");
                    }
                };
            };
            
            
        }
        
        return $item;
        
    }
    
    protected function getHistory($id = ""){

        if ($id){
            return HistoryManager::getHistory($this, array("id"=>$id));
        }else{
            return HistoryManager::getHistory($this);
        };
    }
    protected function getName(){
        return issset($this->data["name"]) ? $this->data["name"] : (isset($this->data["title"]) ? $this->data["title"] : _t("Unknown name"));
    }
    public function getChanges(){
        return new ChangesSet($this->data, $this->data_before_changes);
    }
    public function getLink($id = ""){
        if ($id){
            return UrlManager::getLink($this, array("id"=>$id));
        }else{
            return UrlManager::getLink($this);
        };
    }
    public function getState(){
        if (!$this->state_field || empty($this->data[$this->state_field]))  return $this->default_state;
        return $this->data[$this->state_field];
    }
    public function setState($state_value){
        if ($this->state_field) $this->data[$this->state_field] = $state_value;
        return isset($this->state_field);
    }
    
    
    public function changes(){
        return new ChangesSet($this->data, $this->data_before_changes);
    }
    public function whatChanged(){
        
        $cs = $this->changes();
        
        if ($cs && $cs->to){
            return array_keys($cs->to);
        }else{
            return array(); 
        }
    }
    public function isChanged($field = ""){
        if ($field){
            return in_array($field, $this->whatChanged());
        }else{
            return !is_null($this->changes());
        }
    }
    
    
    public function modify(array $to, array $from = array()){
        foreach($to as $k=>$v){
            $this->data[$k] = $v;
        }
        
        return $this;
    }
    
    public function save($comment=""){
        
        if ($this->isChanged()){
            $repository = Repository::create($this->repo_name);
            
            if (!empty($this->data["id"])){
                try{
                    if ($repository->update($this)){
                        $this->data_before_changes = $this->data;
                    };
                    
                }catch(Exception $e){
                    throw $e;
                }
                
            }else{
                $res = $repository->insert($this);
                if ($res){
                    $this->data["id"] = $res;
                    $this->data_before_changes = $this->data;
                };
            }
        }else{
            dosyslog(__METHOD__.get_callee().": WARNING: Model '".$this->model_name."(".$this->data["id"].")' is not changed. Not saved, so.");
        }
        
        return $this;
    }
    
    public function __get($key){
        if ($key == "repo_name")   return $this->repo_name;
        if ($key == "model_name")  return $this->model_name;
        if ($key == "fields")      return $this->fields;
        if ($key == "name")        return $this->getName();
        if ($key == "state")       return $this->getState();
        if ($key == "state_field") return $this->state_field;
        
        dosyslog(__METHOD__ . get_callee() . ": FATAL ERROR: Property '".$key."' is not available in class '".__CLASS__."'.");
        die("Code: ".__CLASS__."-".__LINE__."-".$key);
        
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
        return isset($this->data[$offset]) || isset($this->data["extra"][$offset]);
        
    }

    public function offsetUnset($offset) {
        if (isset($this->data[$offset])) {
            unset($this->data[$offset]);
        } else {
            unset($this->data["extra"][$offset]);
        }
        
    }

    public function offsetGet($offset) {
        
        if (array_key_exists($offset, $this->data)) {
            return $this->data[$offset];
        } elseif (isset($this->data["extra"][$offset])) {
            return $this->data["extra"][$offset];
        }elseif (method_exists($this, "get".ucfirst($offset)) ){
            return call_user_func(array("get".ucfirst($offset), $this));
        }elseif (in_array($offset, array_keys($this->fields))){
            return null;
        }else{
            if (DEV_MODE){
                dosyslog(__METHOD__.get_callee().": FATAL ERROR: Neither property '".$offset."' nor method '"."get".ucfirst($offset)."' are exists in class '".__CLASS__."'.");
                die("Code: ".__CLASS__."-".__LINE__."-".$offset);
            };
            return null;
        }
    }
    
    /* jsonSerializable implementation */
    public function jsonSerialize(){
        
        $item = $this->data;
                
        if (empty($this->data["link"]))     $item["link"]     = $this->getLink();
        if (empty($this->data["state"]))    $item["state"]    = $this->getState();
        
        if (empty($this->data["history"]) && ! is_a($this, "HistoryModel")){
            $item["history"]  = $this->getHistory();
        };
                
        return $item;
        
    }
    
    /* IteratorAggregate implementation */
    public function getIterator() {
        return new ArrayIterator($this->data);
    }
    
    
}