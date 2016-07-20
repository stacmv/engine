<?php 
abstract class ERepository implements IteratorAggregate, jsonSerializable, Countable
{
    protected $repo_name;
    protected $fields;
    protected $id;
    protected $model_name;
    protected $uri_prefix;
    protected $storage;
    
    // abstract public function checkACL($model, $right);
    
    public static function create($repository_name, $form_name = "all"){
        $repo = self::_getInstance($repository_name);
        $repo->repo_name  = $repository_name;
        $repo->model_name = db_get_obj_name($repository_name);
        $repo->fields     = form_get_fields($repository_name, $form_name);
        $repo->uri_prefix = db_get_meta($repository_name, "model_uri_prefix");
        
        $storage_type  = db_get_meta($repository_name, "storage") or $storage_type = "Sqlite";
        $storage_class = $storage_type . "Storage";
        $repo->storage = new $storage_class($repository_name);
        return $repo;
    }
    public static function fields($repository_name, $form_name = "all"){
        return form_get_fields($repository_name, $form_name);
    }
    public function load($id){
        $item =  self::create($this->repo_name);
        
        if (is_a($this->storage, "IniFileStorage")){
            $item->where(function($record) use ($id){return $record["id"] == $id;});
        }else{
            $item->select("*")->where("id = $id");
        };
        
        return $item;
    }
    
    protected static function _getRepositoryClassName($repository_name){
        $a = explode(".", $repository_name);

        if ( (count($a) >=2 ) && ( $a[0] == $a[1] ) ){   // main table in db; table name == db name
            array_shift($a);
        };
        $a = array_map("ucfirst", $a);
        
        return implode("", $a);

    }
    protected static function _getModelClassName($repository_name){
        static $modelClass_exists = false;
        $a = explode(".", $repository_name);

        if ( (count($a) >=2 ) && ( $a[0] == $a[1] ) ){   // main table in db; table name == db name
            array_shift($a);
        };
        
        $a = array_map("_singular", $a);
        $a = array_map("ucfirst", $a);
        
        $modelClass = implode("", $a);
        
        // Ensure if class is defined
        if (!$modelClass_exists){
            $model = engine_utils_get_class_instance($modelClass, "ModelTemplate");
            if (is_a($model, $modelClass)) $modelClass_exists = true;
        };
        //
                
        return $modelClass;

    }
    protected static function _getInstance($repository_name){
        $class_name = static::_getRepositoryClassName($repository_name);
        
        $repo = engine_utils_get_class_instance($class_name, "RepositoryTemplate");
        
        return $repo;
    }
    protected static function _methods(){
        return get_class_methods(get_called_class());
    }
    
    public function fetch(){
        $row = $this->fetchAssoc();
        if (is_array($row) && !empty($row)){
            $modelClass = static::_getModelClassName($this->repo_name);
            return new $modelClass($row);
        }else{
            return null;
        }
    }
    public function fetchAssoc(){
        return $this->storage->fetchAssoc();
    }
    public function fetchAll(){
        
        $res = $this->fetchAllAssoc();
        if (!$res) return array();
        
        $modelClass = static::_getModelClassName($this->repo_name);
        
        foreach($res as $k=>$row){
            $res[$k] = new $modelClass($row);
        };
            
        return $res;
        
    }
    public function fetchAllAssoc(){
        return $this->storage->fetchAllAssoc();
    }
    
    public function findOne($id){
        return $this->load($id);
    }
    public function findWhere($whereClause){
        $repo_name = $this->repo_name;
        $res = $this->storage->where($whereClause)->fetchAllAssoc();
        return array_filter($res, function($item) use ($repo_name){
            return check_data_item_acl($item, $repo_name);
        });
    }
    public function groupBy($groupBy){
        $this->storage->groupBy($groupBy);
        return $this;
        
    }
    public function import($data, $options = 0){
        
        $import_id = uniqid("import");
        $res = array();
        
        $fields_import = self::fields($this->repo_name, "import_".$this->repo_name);
        
        // Data may have keys equal to field name or equal to field label
        $field_names  = array_keys($fields_import);
        $field_labels = array_map(function($field){
            return $field["label"];
        }, $fields_import);
        $field_labels = array_filter($field_labels); // in case field has no label
        
        $map = array_merge( array_combine($field_labels, $field_names), array_combine($field_names, $field_names) );
        
        // Map data keys
        $data = array_map(function($record) use ($map){
            $mapped = array();
            foreach($record as $k=>$v){
                if (!empty($map[$k])){
                    $mapped[$map[$k]] = $v;
                };
            };
            return $mapped;
        }, $data);
        
        
        $this->storage->beginTransaction();
        foreach($data as $k=>$record){
            $modelClass = static::_getModelClassName($this->repo_name);
            $model = new $modelClass($record);
            $res[$k] = $this->insert($model, $import_id);
        };
        $this->storage->commit();
        
        return $res;
    }
    public function insert(EModel $model, $comment = ""){
        return $this->storage->create($this->repo_name, $model->changes(), $comment);
    }
    public function limit($limit){
        $this->storage->limit($limit);
        return $this;
    }
    public function offset($offset){
        $this->storage->offset($offset);
        return $this;
    }
    public function orderBy(array $orderBy){
        $this->storage->orderBy($orderBy);
        return $this;
        
    }
    public function select($select){
        $this->storage->select($select);
        return $this;
    }
    public function set(array $set){
        return $this->storage->set($set);
    }
    public function update(EModel $model, $comment=""){
        return $this->storage->update($this->repo_name . "/" . $model["id"], $model->changes(), $comment);
    }
    public function where($whereClause, $value = "", $operator = ""){
        
        if (is_string($whereClause) && $value){
            switch(strtolower($operator)){
            case "in":
                if (is_scalar($value)){
                    $this->storage->where($whereClause . " IN (" . db_quote($value) . ")");
                }else{
                    $this->storage->where($whereClause . " IN (" . implode(", ", array_map("db_quote", $value)) . ")");
                }
                break;
            default:
                $this->storage->where($whereClause . " = " . db_quote(db_prepare_value($value, $this->fields[$whereClause]["type"])));
                break;
            };
        }else{
            $this->storage->where($whereClause);
        }

        return $this;
    }
    public function __get($key){
        if ($key == "repo_name") return $this->repo_name;
        if ($key == "model_name") return $this->model_name;
        if ($key == "fields") return $this->fields;
        if ($key == "uri_prefix") return $this->uri_prefix;
        
        
        
        dosyslog(__METHOD__ . get_callee() . ": FATAL ERROR: Property '".$key."' is not available in class '".__CLASS__."'.");
        die("Code: ".__CLASS__."-".__LINE__."-".$key);
        
    }
    
    /* jsonSerializable implementation */
    public function jsonSerialize(){
        
        $res =  array_map(function($item){
            return $item->jsonSerialize();
        }, $this->fetchAll());
        
        foreach(static::_methods() as $method){
            if ("getIterator" == $method) continue; // IteratorAggregate method, not own
            if ( ! preg_match("/^get([A-Z].+)$/", $method, $matches)) continue;
            $res[strtolower($matches[1])] = $this->$method();
        };
        
        return $res;

    }
    
    
    /* IteratorAggregate implementation */
    public function getIterator() {
        return new ArrayIterator($this->fetchAll());
    }
    
    /* Countable implementation */
    public function count(){
        $tmp = $this->select("count(*)")->fetchAssoc();
        $count = $tmp["count(*)"];
        return $count;
    }
}