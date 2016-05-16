<?php 
abstract class ERepository implements IteratorAggregate, jsonSerializable
{
    protected $repo_name;
    protected $fields;
    protected $id;
    protected $model_name;
    protected $sql_hash;
    protected $sql_query;
    protected $sql_result;
    protected $sql_select;
    protected $sql_where;
    protected $sql_group_by;
    protected $sql_order_by;
    protected $sql_limit;
    protected $uri_prefix;
    
    // abstract public function checkACL($model, $right);
    
    public static function create($repository_name, $form_name = "all"){
        $class_name = self::_class_name($repository_name);
        $repo =  new $class_name;
        $repo->repo_name = $repository_name;
        $repo->model_name = db_get_obj_name($repository_name);
        $repo->fields   = form_get_fields($repository_name, $form_name);
        $repo->uri_prefix = db_get_meta($repository_name, "model_uri_prefix");
        return $repo;
    }
    public static function fields($repository_name, $form_name = "all"){
        return form_get_fields($repository_name, $form_name);
    }
    public function load($id){
        $item =  self::create($this->repo_name);
        $item->select("*")->where("id = $id");
        
        return $item;
    }
    public function history(){
        
        $history = db_find(db_get_name($this->repo_name).".history", "objectId", $id, DB_RETURN_ROW);
        $history = array_reverse($history);
        
        $hist = array();
        foreach($history as $hist_rec){
            $timestamp  = $hist_rec["timestamp"];
            $subjectId  = $hist_rec["subjectId"];
            
            
            $m = array();
            if ($hist_rec["action"] == "db_add"){
                $state = "0";
                $comment = _t(ucfirst($this->item_name) . " created");
            }elseif(!empty($hist_rec["comment"]) && preg_match('/'.$this->item_name.'_state = "(\d+)"/', $hist_rec["changes_to"], $m)){
                
                $state = $m[1];
                $comment = $hist_rec["comment"];

            }else{
                $state = _t(ucfirst($this->item_name) . " changed");
                $comment = $hist_rec["comment"];
                if (is_null($fields)) $fields = form_get_fields(LEADS_DB_NAME, "all");
                $comment .= "\nБыло:\n" . $hist_rec["changes_from"] . "\n\nСтало:\n" . $hist_rec["changes_to"];
                
                
                // $comment = htmlentities($comment);
                
                // dump(array_keys($fields), "keys");
                // dump(array_values(array_map(function($field){
                    // return $field["label"];
                // }, $fields)), "values");
                $labels = array_map(function($field){
                    return $field["label"];
                }, array_filter($fields, function($field){
                    return $field["label"];
                }));
                $comment = str_replace(array_keys($labels), array_values($labels), $comment);
            };
            
            $hist[] = array(
                "timestamp" => $timestamp,
                "state"     => $state,
                "subjectId" => $subjectId,
                "comment"   => $comment,
            );
            
        }
                
        return $hist;
    

    }
    private static function _class_name($repository_name){
        return str_replace(" ","_", ucfirst(str_replace("."," ", $repository_name)));
    }
    public function fetch(){
        $row = $this->fetchAssoc();
        if (is_array($row) && !empty($row)){
            $modelClass = self::_class_name($this->model_name);
            return new $modelClass($row);
        }else{
            return null;
        }
    }
    public function fetchAssoc(){
        if ( ! $this->sql_result){
            $this->createSql();
            $this->sql_result = db_select($this->repo_name, $this->sql_query);
        };
                
        
        if ($this->sql_result){
            list($k,$v) = each($this->sql_result);
            return $v;
        }else{
            return false;
        }
    }
    public function fetchAll(){
        
        $this->fetchAllAssoc();
        
        return array_map(function($row){
                $modelClass = self::_class_name($this->model_name);
                return new $modelClass($row);
            }, $this->sql_result);
        
    }
    public function fetchAllAssoc(){
        if ( ! $this->sql_result){
            $this->createSql();
            $this->sql_result = db_select($this->repo_name, $this->sql_query);
        };
        
        if ($this->sql_result){
            return $this->sql_result;
        }else{
            return false;
        }
    }
    
    public function findOne($id){
        $res = $this->where("id = " . (int) $id)->fetchAll();
        return array_shift($res);
    }
    public function findWhere($whereClause){
        $repo_name = $this->repo_name;
        $sql = "SELECT * FROM " . db_get_table($repo_name) . " WHERE " . $whereClause . ";";
        
        return array_filter(db_select($repo_name, $sql), function($item) use ($repo_name){
            return check_data_item_acl($item, $repo_name);
        });
    }
    public function groupBy($groupBy){
        $this->sql_group_by = $groupBy;

        $this->sql_result = null;
        return $this;
        
    }
    public function limit($limit){
        $this->sql_limit = (int) $limit;
        $this->sql_result = null;
        return $this;
    }
    public function orderBy(array $orderBy){
        $fields = $this->fields;
        
        $this->sql_order_by = array();
        foreach($orderBy as $field=>$sort_mode){
            if (in_array($field, array_keys($fields)) && in_array(strtoupper($sort_mode), array("ASC", "DESC"))){
                $this->sql_order_by[] = $field. " " . $sort_mode;
            };
        }
        $this->sql_result = null;
        return $this;
        
    }
    public function select($select){
        $fields  = $this->fields;
        if ($select == "*"){
            $this->sql_select = array_keys($fields);
        }else{
            if (is_string($select)){
                $select = array($select);
            };
            
            $this->sql_select = $select;
        }
        
        $this->sql_result = null;
        
        return $this;
    }
    public function update(EModel $model, $comment=""){
        // TODO error handling
        list($res, $reason) = db_edit($this->repo_name, $model["id"], $model->getChanges(), $comment);
        
        if (!$res){
            throw new Exception($reason);
        };
        return $res;
    }
    public function where($whereClause){
        $this->sql_where = $whereClause;
        $this->sql_result = null;
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
    protected function createSql(){
        global $_USER;
        
        if (!$_USER->is_authenticated()) die("Code: 403-ERepository".__LINE__);
        
        $this->sql_hash = md5(implode("::", array_map("serialize", array(
            $this->sql_select, $this->sql_where, $this->sql_order_by, $this->sql_limit
        ))));
        
        $sql = "SELECT ";
        if ($this->sql_select){
            $sql .= implode(", ", $this->sql_select);
        }else{
            $sql .= "*";
        }
        $sql .= " FROM " . db_get_table($this->repo_name) ." ";
        
        // Where
        if ( ! userHasRight("manager") ){
            $where_acl_addon = " (user_id = " . (int) $_USER["id"] . ") ";
        }        
        if ($this->sql_where || !empty($where_acl_addon))  $sql .= " WHERE ";
        if ($this->sql_where)                              $sql .= $this->sql_where;
        if ($this->sql_where && !empty($where_acl_addon))  $sql .= " AND ";
        if (!empty($where_acl_addon))                      $sql .= $where_acl_addon;
        
        // group by
        if ($this->sql_group_by){
            $sql .= " GROUP BY " . $this->sql_group_by . " ";
        }
        
        // Order by
        if ($this->sql_order_by){
            $sql .= " ORDER BY " . implode(", ", $this->sql_order_by). " ";
        }
        
        // Limit
        if ($this->sql_limit){
            $sql .= " LIMIT ".$this->sql_limit;
        }
        $sql .= ";";
        
        $this->sql_query = $sql;
        $this->sql_result = null;
        
        return $sql;
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