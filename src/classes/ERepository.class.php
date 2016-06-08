<?php 
abstract class ERepository implements IteratorAggregate, jsonSerializable
{
    protected $repo_name;
    protected $fields;
    protected $id;
    protected $model_name;
    protected $uri_prefix;
    protected $storage;
    
    // abstract public function checkACL($model, $right);
    
    public static function create($repository_name, $form_name = "all"){
        $class_name = static::_getRepositoryClassName($repository_name);
        $repo =  new $class_name;
        $repo->repo_name = $repository_name;
        $repo->model_name = db_get_obj_name($repository_name);
        $repo->fields   = form_get_fields($repository_name, $form_name);
        $repo->uri_prefix = db_get_meta($repository_name, "model_uri_prefix");
        
        $storage_type = db_get_meta($repository_name, "storage") or $storage_type = "Sqlite";
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
        $a = explode(".", $repository_name);

        if ( (count($a) >=2 ) && ( $a[0] == $a[1] ) ){   // main table in db; table name == db name
            array_shift($a);
        };
        
        $a = array_map("_singular", $a);
        $a = array_map("ucfirst", $a);
        
        return implode("", $a);

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
        
        return array_map(function($row){
                $modelClass = static::_getModelClassName($this->repo_name);
                return new $modelClass($row);
            }, $res);
        
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
        $this->storage->groupBy();
        return $this;
        
    }
    public function insert(EModel $model, $comment = ""){
        return $this->storage->create($this->repo_name, $model->getChanges(), $comment);
    }
    public function limit($limit){
        $this->storage->limit($limit);
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
    public function update(EModel $model, $comment=""){
        return $this->storage->update($this->repo_name . "/" . $model["id"], $model->getChanges(), $comment);
    }
    public function where($whereClause, $value = ""){
        if (is_a($this->storage, "IniFileStorage")){
            if (is_string($whereClause) && $value){
                $this->storage->where(function($record)use($whereClause,$value){
                    return $record[$whereClause] == $value;
                });
            }else{
                $this->storage->where($whereClause);
            }
        }else{
            if (is_string($whereClause) && $value){
                $this->storage->where($whereClause . " = " . db_prepare_value($value, $this->fields[$whereClause]["type"]));
            }else{
                $this->storage->where($whereClause);
            }
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
        
        

    }
    
    
    /* IteratorAggregate implementation */
    public function getIterator() {
        return new ArrayIterator($this->fetchAll());
    }
}