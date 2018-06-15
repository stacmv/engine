<?php
class SqliteStorage extends EStorage
{
    protected $dbh;
    protected $repo_name;
    protected $db_table;
    protected $fields;
    protected $sql_hash;
    protected $sql_query;
    protected $sql_result;
    protected $sql_select;
    protected $sql_from;
    protected $sql_join;
    protected $sql_on;
    protected $sql_where;
    protected $sql_group_by;
    protected $sql_having;
    protected $sql_order_by;
    protected $sql_limit;
    protected $sql_offset;

    protected $_sql_in_join; // JOIN method called, ON method not yet called

    public function __construct($repo_name){
        $this->dbh = db_set($repo_name);
        $this->repo_name = $repo_name;
        $this->db_table = db_get_table($this->repo_name);
        $this->fields = form_get_fields($repo_name, "all");
    }

    public function beginTransaction(){
        return $this->dbh->beginTransaction();
    }
    public function commit(){
        return $this->dbh->commit();
    }
    public function rollback(){
        return $this->dbh->rollbak();
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
            $tmp =  array_values($this->sql_result);
            return $tmp[0];
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

    public function from($from){
        $this->sql_from = $from;

        $this->sql_result = null;
        return $this;
    }
    public function groupBy($groupBy){
        $this->sql_group_by = $groupBy;

        $this->sql_result = null;
        return $this;

    }
    public function having($having){
        $this->sql_having = $having;

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
    public function join($join){
        if (!$this->_sql_in_join){
            $this->sql_join .= " INNER JOIN " . $join;
            $this->_sql_in_join = true;

            $this->sql_result = null;
            return $this;
        }else{
            throw new Exception("wrong_call_chain_".__METHOD__);
        }
    }
    public function leftJoin($leftJoin){
        if (!$this->_sql_in_join){
            $this->sql_join .= " LEFT JOIN " . $leftJoin;
            $this->_sql_in_join = true;

            $this->sql_result = null;
            return $this;
        }else{
            throw new Exception("wrong_call_chain_".__METHOD__);
        }
    }
    public function limit($limit){
        $this->sql_limit = (int) $limit;
        $this->sql_result = null;
        return $this;
    }
    public function on($on){
        if ($this->_sql_in_join){
            $this->sql_join .= " ON " . $on . "";
            $this->_sql_in_join = false;

            $this->sql_result = null;
            return $this;
        }else{
            throw new Exception("wrong_call_chain_".__METHOD__);
        }
    }
    public function offset($offset){
        $this->sql_offset = (int) $offset;
        $this->sql_result = null;
        return $this;
    }
    public function orderBy(array $orderBy){

        $this->sql_order_by = array();
        foreach($orderBy as $field=>$sort_mode){
            if (in_array(strtoupper($sort_mode), array("ASC", "DESC"))){
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
    public function set(array $set){

        if (!$this->sql_where)  throw new Exception("wrong_call_chain_".__METHOD__);


        foreach ($set as $field=>$value){
            if (isset($this->fields[$field])){
                $set[$field] = db_prepare_value($value, $this->fields[$field]["type"]);
            }else{
                throw new Exception("wrong_param_".(string)$field);
            };
        };


        $sql = "UPDATE ";
        //
        if (!$this->sql_from){
            $sql .= db_get_table($this->repo_name) ." ";
        }else{
            $sql .= $this->sql_from;
        };


        // SET
        $sql .= " SET " . implode(", ", array_map(function($value, $field){
            return $field . " = " . (is_null($value) ? "NULL" : db_quote($value));
        }, $set, array_keys($set)));

        // Where
        if ( ! userHasRight("manager") && isset($this->fields["user_id"])){
            $where_acl_addon = " (user_id = " . (int) $_USER["id"] . ") ";
        }
        if ($this->sql_where || !empty($where_acl_addon))  $sql .= " WHERE ";
        if ($this->sql_where)                              $sql .= $this->sql_where;
        if ($this->sql_where && !empty($where_acl_addon))  $sql .= " AND ";
        if (!empty($where_acl_addon))                      $sql .= $where_acl_addon;


        $st = $this->dbh->prepare($sql);
        if ($st){
            if ($st->execute()){
                dosyslog(__METHOD__.get_callee().": NOTICE: SQL: ".$sql." ... success");
            }else{
                dosyslog(__METHOD__.get_callee().": NOTICE: SQL: ".$sql." ... fail");
            }
        }else{
            dosyslog(__METHOD__.get_callee().": NOTICE: Wrong SQL: ".$sql.". Error: ".db_error($this->dbh));
            throw new Exception("wrong_sql_query");
        }

        return $st->rowCount();

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

    public function reset(){
        // reset all sql attributes
        $properties = get_class_vars(__CLASS__);

        foreach($properties as $k=>$v){
            if (substr($k, 0, 4) == "sql_"){
                $this->$k = null;
            };
        };

    }

    protected function createSql(){
        $this->sql_hash = md5(implode("::", array_map("serialize", array(
            $this->sql_select, $this->sql_where, $this->sql_order_by, $this->sql_limit
        ))));

        $sql = "SELECT ";
        if ($this->sql_select){
            $sql .= implode(", ", $this->sql_select);
        }else{
            $sql .= "*";
        }

        if (!$this->sql_from){
            $sql .= " FROM " . $this->db_table ." ";
        }else{
            $sql .= " FROM " . $this->sql_from;
        };

        // Join
        if ($this->sql_join){
            if (!$this->_sql_in_join){
                $sql .= $this->sql_join;
            }else{
                throw new Exception("wrong_call_chain_".__METHOD__);
            }
        }

        // Where
        $sql_where = "";
        if ($this->sql_where || !empty($where_acl_addon))  $sql_where .= " WHERE ";
        if ($this->sql_where)                              $sql_where .= $this->sql_where;
        if ($this->sql_where && !empty($where_acl_addon))  $sql_where .= " AND ";
        if (!empty($where_acl_addon))                      $sql_where .= $where_acl_addon;

        // Do not return 'marked as deleted' records by default
        if (isset($this->fields["deleted"])){
            if (strpos($sql_where, "deleted") === false){
                if (!$sql_where) $sql_where .= " WHERE ".$this->db_table.".deleted IS NULL";
                else $sql_where .= " AND ".$this->db_table.".deleted IS NULL";
            };
        };

        $sql .= $sql_where;

        // dump($sql_where,"sql_where");
        // die($sql);

        // group by
        if ($this->sql_group_by){
            $sql .= " GROUP BY " . $this->sql_group_by . " ";
        }

        // Having
        if ($this->sql_having){
            $sql .= " HAVING " . $this->sql_having . " ";
        }

        // Order by
        if ($this->sql_order_by){
            $sql .= " ORDER BY " . implode(", ", $this->sql_order_by). " ";
        } elseif (!$this->sql_join) {
            $sql .= " ORDER BY created DESC ";
        }

        // Limit
        if ($this->sql_limit){
            $sql .= " LIMIT ".$this->sql_limit;
        }

        // Offset
        if ($this->sql_offset){
            $sql .= " OFFSET ".$this->sql_offset;
        }
        $sql .= ";";

        $this->sql_query = $sql;
        $this->sql_result = null;

        return $sql;
    }

}
