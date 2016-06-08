<?php 
class SqliteStorage extends EStorage
{
    protected $dbh;
    protected $repo_name;
    protected $fields;
    protected $sql_hash;
    protected $sql_query;
    protected $sql_result;
    protected $sql_select;
    protected $sql_where;
    protected $sql_group_by;
    protected $sql_order_by;
    protected $sql_limit;
   
    public function __construct($repo_name){
        $this->dbh = db_set($repo_name);
        $this->repo_name = $repo_name;
        $this->fields = form_get_fields($repo_name, "all");
    }
    
    public function create($resource, ChangesSet $changes, $comment=""){
        // TODO error handling
        $db_table = $this->parseResource($resource)["path"];
        $res = db_add($db_table, $changes, $comment);
        
        if (!$res){
            throw new Exception("fail");
        };
        return $res;
    }
    public function read($resource, $comment = ""){
        $id = $this->parseResource($resource)["id"];
        if ($id){
            $this->where("id", $id);
            return $this->fetchAssoc();
        }else{
            return $this->fetchAllAssoc();
        };
        
    }
    public function delete($resource, $comment = ""){
        
        $id = $this->parseResource($resource)["id"];
        if ($id){
            $item = $this->read($resource);
            $item_deleted = $item;
            $item_deleted["deleted"] = time();
            return $this->update($resource, new ChangesSet($item_deleted, $item), $comment);
        }else{
            return false;
        };
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
                $modelClass = static::_getModelClassName($this->repo_name);
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
    public function insert(EModel $model, $comment = ""){
        // TODO error handling
        $res = db_add($this->repo_name, $model->getChanges(), $comment);
        
        if (!$res){
            throw new Exception("fail");
        };
        return $res;
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
    public function update($resource, ChangesSet $changes, $comment=""){
        // TODO error handling
        
        $db_table = $this->parseResource($resource)["path"];
        $id       = $this->parseResource($resource)["id"];
        
        if ($db_table && $id){
            list($res, $reason) = db_edit($db_table, $id, $changes, $comment);
            if (!$res){
                throw new Exception($reason);
            };
        }else{
            throw new Exception("wrong_params");
        }
        return $res;
    }
    public function where($whereClause){
        $this->sql_where = $whereClause;
        $this->sql_result = null;
        return $this;
    }
    
    protected function createSql(){
        global $_USER;
        
        
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
        if ( ! userHasRight("manager") && isset($this->fields["use_id"])){
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
    
}