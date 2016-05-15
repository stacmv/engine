<?php
abstract class EModel implements ArrayAccess, jsonSerializable, IteratorAggregate
{
    protected $common_fields = array("id", "created", "modified", "deleted");
    protected $db_table;
    protected $model_name;
    
    protected $fields;
    protected $data;
    protected $data_before_changes;
    
    protected $one2many; // array($db_table => null | ERepository::create($db_table)
    

    
    public function __construct(array $data){
        
        $this->fields = form_get_fields($this->db_table, "all");
        $this->data = array();
        foreach($data as $k=>$v){
            if (in_array($k, array_merge(array_keys($this->fields), $this->common_fields))){
                $this->data[$k] = $v;
            }else{
                $this->data["extra"][$k] = $v;
            }
        };
        
        $this->data_before_changes = $this->data;
        
        // Related tables from the same db
        $db_table = $this->db_table;
        $tables = db_get_tables_list($this->db_table, $skipHistory = true);

        $foreight_key = db_get_obj_name($db_table) . "_id";
        $one2many = array_filter($tables, function($dbt) use ($foreight_key){
            $fields = form_get_fields($dbt,"all");
            return isset($fields[$foreight_key]);
        });
        
        $this->one2many = array_map(function($db_table){
            return ERepository::create($db_table);
        }, $one2many);
        
        
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
    public function getLink($id = ""){
        if ($id){
            return UrlManager::getLink($this, array("id"=>$id));
        }else{
            return UrlManager::getLink($this);
        };
    }
    
    public function modify(array $to, array $from){
        foreach($to as $k=>$v){
            $this->data[$k] = $v;
        }
        
        return $this;
    }
    
    public function save($comment=""){
        
        if (!empty($this->data["id"])){
            list($res, $reason) = db_edit($this->db_table, $this->data["id"], new ChangesSet($this->data, $this->data_before_changes), $comment);
        }else{
            $res = db_add($this->db_table, new ChangesSet($this->data), $comment);
            if ($res){
                $this->data["id"] = $res;
            };
        }
        
        return $this;
    }
    
    public function __get($key){
        if ($key == "db_table")   return $this->db_table;
        if ($key == "model_name") return $this->model_name;
        if ($key == "fields")     return $this->fields;
        if ($key == "name")       return $this->getName();
        
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
        
        if (isset($this->data[$offset])) {
            return $this->data[$offset];
        } elseif (isset($this->data["extra"][$offset])) {
            return $this->data["extra"][$offset];
        }elseif (method_exists($this, "get".ucfirst($offset)) ){
            return call_user_func(array("get".ucfirst($offset), $this));
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
                
        if (empty($this->data["link"]))     $item["link"]  = $this->getLink();
        if (empty($this->data["history"]))  $item["history"]  = $this->getHistory();
                
        return $item;
        
    }
    
    /* IteratorAggregate implementation */
    public function getIterator() {
        return new ArrayIterator($this->data);
    }
    
    
}