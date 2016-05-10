<?php
final class Glog
{
    const DEFAULT_STATE = 0;
    private $dateField;
    private $dateFieldType;
    private $filter;
    private $filterValue;
    private $filterClause;
    private $group;
    private $groupValue;
    private $id;
    private $itemName;
    private $model;
    private $repository;

    public function urlBuilder(EModel $item = null, $options = ""){
        
        
        $a_link = array();
        $a_link[] = $this->repository->uri_prefix . $this->repository->db_table;
        
        if ($this->filter){
            $a_link[] = $this->filter;
        }elseif($this->group){
            $a_link[] = "all";
        };
        if (!is_null($this->filterValue)){
            $a_link[] = $this->filterValue;
        }elseif($this->group){
            $a_link[] = "all";
        };
        if ($this->group){
            $a_link[] = $this->group;
        }else{
            $a_link[] = "none";
        }
        if (!is_null($this->groupValue)){
            $a_link[] = $this->groupValue;
        }else{
            $a_link[] = "all";
        };
        
        $id = !empty($options["id"]) ? $options["id"] : (!empty($item["id"]) ?  $item["id"] : "");
        if ($id){
            $a_link[] = $item["id"];
        };
        
        
        return implode("/",$a_link);
    }
    
    public function __construct(array $params = array()){
        foreach($params as $k=>$v){
            if ($k == "model"){
                $this->repository = ERepository::create($v);
            }elseif (property_exists(__CLASS__, $k)){
                $this->$k = $v;
            };
        };
        
        if ( ($this->filter == "all") || ($this->filterValue == "all") ){
            $this->filter == "all";
            $this->filterValue == "all";
        };
        
        if ($this->group == "none"){
            $this->group = null;
            $this->groupValue = null;
        }elseif($this->groupValue == "all"){
            $this->groupValue = null;
        };
        
        $this->itemName = $this->repository->model_name;
        $this->dateField      = isset($this->fields[$this->itemName . "_date"]) ? $this->itemName . "_date" : (isset($this->fields["date"]) ? "date" : "created");
        $this->dateFieldType = $this->fields[$this->dateField]["type"];
        
                
        $this->filterClause = $this->_filterClause();
        
        UrlManager::setUrlBuilder($this->repository->model_name, array($this, "urlBuilder"));
        
    }
    public function all(){
        
        $res = array();
        
        if ($this->filterClause){
            $res = $this->repository->where($this->filterClause)->fetchAll();
        }else{
            $res = $this->repository->fetchAll();
        }
        
        if ($res){
            $res = form_prepare_view($res, $this->repository->fields);
            if ( $this->filter && ( ($this->filter != "all") || ! is_null($this->groupValue)) ){
                return arr_group($res, $this->_field($this->group));
            }else{
                return array("all" => $res);
            };
        }else{
            return array();
        }
    }
    public function checkACL($item, $right = "show"){
        
        if (method_exists($this->repository, "checkACl")){
            return $this->repository->checkACL($item, $right);
        }else{
            return true;
        };
    }
    public function getCounts(){
        $group_field = $this->_field($this->group);
        $counts = $this->repository->select( array($group_field, "count(".$group_field.")") )->where($this->filterClause)->groupBy($group_field)->orderBy(array($group_field=>"ASC"))->fetchAllAssoc();
          
        if ($counts){
            $counts = array_reduce($counts, function($counts, $c) use ($group_field){
                $counts[$c[$group_field]] = $c["count(".$group_field.")"];
                return $counts;
            }, array());
        };
        
        return $counts;
        
    }
    public function getGroups(){
        $group_field = $this->_field($this->group);
        
        if (! $group_field ) return array(
            "all" => array(
                "value"   => "all",
                "caption" => _t("All ". $this->repository->db_table),
            ),
        );
        
        
        $groups = $this->repository->select( $group_field )->where($this->filterClause)->groupBy($group_field)->orderBy(array($group_field=>"ASC"))->fetchAll();
            
        if ($groups){
            if ($this->group == "state"){
                $groups = array_map(function($g)use($group_field){
                    if (! $g[$group_field]) $g[$group_field] = 0;
                    return $g;
                }, $groups);
            };
            
            $groups = form_prepare_view($groups, $this->repository->fields);
            
            $groups = array_map( function($g) use ($group_field){
                $res =  array();
                $res["value"] = $g[$group_field];
                $res["caption"] = isset($g["_".$group_field]) ? $g["_".$group_field] : $g[$group_field];
                return $res;
            }, $groups);
        };
        
        return $groups;
        
    }
    public function getHeader(){
        
        $header = _t(ucfirst($this->repository->db_table));
        
        if ($this->filter == "all"){
            $header = _t("All " . $this->repository->db_table);
        };
        
        switch ($this->group){
            case "state":
                if (is_null($this->groupValue)){
                    $header .= " " . _t("by state");
                }else{
                    $groups = arr_index($this->getGroups(),"value");
                    $header .= " " . _t("with state") . " '" . $groups[$this->groupValue]["caption"] . "'";
                };
                break;
        }
        
        
        return $header;
    }
    public function getItem($id){
        return new GlogItem($this->repository->findOne($id), $this);
    }
    
    public function __get($key){
        switch ($key){
            case "filterValue": return $this->filterValue; break;
            case "filterClause": return $this->filterClause; break;
            case "modelName": return $this->repository->db_table;break;
            case "itemName": return $this->itemName;break;
            case "fields": return $this->repository->fields;break;
            case "repository": return $this->repository; break;
            
        }
        
        dosyslog(__METHOD__ . get_callee() . ": FATAL ERROR: Property '".$key."' is not available in class '".__CLASS__."'.");
        die("Code: ".__CLASS__."-".__LINE__."-".$key);
    }
    private function _field($filter){
        switch($filter){
            case "date":
                return isset($this->repository->fields[$this->repository->model_name . "_date"]) ? $this->repository->model_name . "_date" : (isset($this->repository->fields["date"]) ? "date" : "created");
            case "state":
                return  isset($this->repository->fields[$this->repository->model_name . "_state"]) ? $this->repository->model_name . "_state" : (isset($this->repository->fields["state"]) ? "state" : null);
            default:
                return $filter;
        };
    }
    private function _filterClause(){
        $filter = $this->filter;
        $filter_value  = $this->filterValue;
        $filter_field = $this->_field($this->filter);
        $group  = $this->group;
        $group_value = $this->groupValue;
        $group_field = $this->_field($this->group);
        
       
        $where_clause =  "";
                
        if ($this->filter){
            switch($this->filter){
                case "all":
                    // do nothing
                    break;
                default:
                    $afv = explode(",",$this->filterValue);
                    if (count($afv) == 2){
                        $where_clause .= "(" .  $filter_field . " >= ".$afv[0] . " AND " . $filter_field . " <= ".$afv[1] . ")";
                    }else{
                        if (!empty($afv[0])){
                            $where_clause .= "(" . $filter_field . " = ".(is_numeric($afv[0]) ? $afv[0] : db_quote($afv[0])) . ")";
                        }else{
                            $where_clause .= "(" . $filter_field . " IS NULL " . ")";
                        }
                    }
            }
        };
        
        if ($group && ! is_null($group_value)){
            if ($where_clause) $where_clause .= " AND ";
            if ( ($group == "state") && ($group_value == 0) ){
                $where_clause .= "(" . $group_field . " = 0 OR " . $group_field . " IS NULL)";
            }else{
                $where_clause .= "(" . $group_field . " = " . (is_numeric($group_value) ? $group_value : db_quote($group_value)) . ")";
            };
        };
        
        
        return $where_clause;
        
    }
    private function _navigation(){
                
        $nav = array(
            "prev"  => $this->_prev(),
            "start" => $this->_start(),
            "end"   => $this->_end(),
            "next"  => $this->_next(),
        );
        
        if ($this->id){
            $nav["list"] = $this->_listUri();
        };
        
        return $nav;
    }
    private function _start(){
        $res = null;
        if ($this->filter && $this->filterValue){
            switch($this->filter){
                case "date":
                    $afv = explode(",",$this->filterValue);
                    if (isset($afv[0])){
                        return $afv[0];
                    }
                    break;
                case "state":
                    return $filter_value;
                    break;
            }
        }
        return $res;
    }
    private function _end(){
        $res = null;
        if ($this->filter && $this->filterValue){
            switch($this->filter){
                case "date":
                    $afv = explode(",",$this->filterValue);
                    if (isset($afv[1])){
                        return $afv[1];
                    }
                    break;
                case "state":
                    return $filter_value;
                    break;
            }
        }
        return $res;
    }
    private function _prev(){
        global $_USER;
        
        
        
        $res = null;
        if ($this->id){
            $where_clause = $this->filterClause ." AND (id < ".(int) $this->id .")";
            if (! userHasRight("manager")){
                $where_clause .= " AND (user_id = " . $_USER["id"].") ";
            };
            $res = $this->repository->select("id")->where($where_clause)->orderBy(array("id"=>"DESC"))->limit(1)->fetch();
            if ($res){
                $res["uri"] = $this->getItemLink($res["id"]);
            }
        }
        return $res;
    }
    private function _next(){
        global $_USER;
        
        $res = null;
        if ($this->id){
            $where_clause = $this->filterClause ." AND (id > ".(int) $this->id .")";
            if (! userHasRight("manager")){
                $where_clause .= " AND (user_id = " . $_USER["id"].") ";
            };
            $res = $this->repository->select("id")->where($where_clause)->orderBy(array("id"=>"ASC"))->limit(1)->fetch();
            if ($res){
                $res["uri"] = $this->getItemLink($res["id"]);
            }
        }
        return $res;
    }
    private function _listUri(){
        return array(
            "uri" => $this->urlBuilder(),
            "caption" => _t("To the list"),
        );
    }

}