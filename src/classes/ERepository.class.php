<?php
abstract class ERepository implements IteratorAggregate, jsonSerializable, Countable
{
    protected $repo_name;
    protected $fields;
    protected $model_name;
    protected $uri_prefix;
    protected $storage;
    protected $where_clause;

    // abstract public function checkACL($model, $right);

    /**
     * Returns object of Repository sub-class for db_table $repository_name
     *
     * @param string $repository_name
     * @param string $form_name
     * @return Repository
     */
    public static function create($repository_name, $form_name = "all"){
        $repo = self::_getInstance($repository_name);
        $repo->repo_name  = $repository_name;
        $repo->model_name = db_get_obj_name($repository_name);
        $repo->fields     = static::fields($repository_name, $form_name);
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
    public function delete($id){
        return $this->storage->delete($this->repo_name."/".$id);
    }

    public function beginTransaction(){
        return $this->storage->beginTransaction();
    }
    public function commit(){
        return $this->storage->commit();
    }
    public function rollback(){
        return $this->storage->rollback();
    }

    public function getPager($items_per_page, $current_page, $uri_params = array(), $url_template = ""){
        global $CFG;

        if (!$url_template){
            $url_template = db_get_meta($this->repo_name, "model_uri_prefix") . $this->repo_name . $CFG["URL"]["ext"] . "?page=%%page%%";
        };
        if (!empty($uri_params)){
          $url_template .= "&".http_build_query($uri_params);
        };
        $items_count = db_get_count($this->repo_name, $this->where_clause);
        return new Pager($url_template, $items_count, $items_per_page, $current_page);
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

        if (strtolower($modelClass) ==strtolower($repository_name)){ // if singular and plural forms of the word are the same
            $modelClass .= "_model";
        };

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
    public function from($from){
        $this->storage->from($from);
        return $this;
    }
    public function groupBy($groupBy){
        $this->storage->groupBy($groupBy);
        return $this;

    }
    public function import(array $data, $options = 0){

        if (empty($data)) throw new Exception("Empty data for import.");

        $import_id = uniqid("import");
        $res = array();

        $fields_import = self::fields($this->repo_name, "import_".$this->repo_name);

        // Data may have keys equal to field name or equal to field label
        $field_names  = array_keys($fields_import);
        $field_labels = array_map(function($field){
            return $field["label"];
        }, $fields_import);
        $field_labels = array_filter($field_labels); // in case field has no label

        // Map data keys to DB fields
        $map = array_merge( array_combine($field_labels, array_keys($field_labels)), array_combine($field_names, $field_names) );

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


        $dbh = db_set($this->repo_name, array(
            "PRAGMA schema.synchronous = 0",
            "PRAGMA schema.journal_mode = OFF",
        ));
        $insert_sql = db_create_insert_query($this->repo_name, array_keys(array_values($data)[0]));
        $stmt = $dbh->prepare($insert_sql);
        $dbh->beginTransaction();
        foreach($data as $k=>$record){
            if (!isset($record["uid"]) && in_array("uid", $field_names)){
                $record["uid"] = glog_codify($record["name"]);
            };
            try{
                if (!$stmt->execute(array_values($record))){
                    throw new Exception("SQL Error:" . $stmt->errorInfo()[2] . ". Record: '".json_encode($record)."'.", 1);
                };
            }catch(Exception $e){
                if (strpos($e->getMessage(), "UNIQUE constraint failed: products.uid") !== false){
                    $res["errors"] = "ОШИБКА! Не уникальный артикул '".$record["uid"]."' в строке '".($k+1)."'. Товар пропущен и не добавлен в БД.";
                }else{
                    die($e->getMessage());
                };
            }
        };
        $dbh->commit();
        $dbh = null;

        return $res;
    }
    public function insert(EModel $model, $comment = ""){
        return $this->storage->create($this->repo_name, $model->changes(), $comment);
    }
    public function join($join){
        $this->storage->join($join);
        return $this;
    }
    public function limit($limit){
        $this->storage->limit($limit);
        return $this;
    }
    public function offset($offset){
        $this->storage->offset($offset);
        return $this;
    }
    public function on($on){
        $this->storage->on($on);
        return $this;
    }
    public function orderBy(array $orderBy){
        $this->storage->orderBy($orderBy);
        return $this;

    }
    public function reset(){
        $this->storage->reset();
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
                    $this->where_clause = $whereClause . " IN (" . db_quote($value) . ")";
                }else{
                    $this->where_clause = $whereClause . " IN (" . implode(", ", array_map("db_quote", $value)) . ")";
                }
                break;
            default:
                if (!empty($this->fields[$whereClause]) && ! empty($this->fields[$whereClause]["type"])){
                    if ($this->fields[$whereClause]["type"] == "boolean"){
                        $this->where_clause = $whereClause . " IS " . ((boolean) $value ? " NOT " : "") . " NULL ";
                        break;
                    }else{
                        $value = db_prepare_value($value, $this->fields[$whereClause]["type"]);
                    };
                };
                $this->where_clause = $whereClause . " = " . db_quote($value);
                break;
            };
        }else{
            $this->where_clause = $whereClause;
        }

        $this->storage->where($this->where_clause);

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
