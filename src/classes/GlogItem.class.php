<?php
class GlogItem extends EModel implements ArrayAccess, jsonSerializable, IteratorAggregate
{
    private $id;
    private $glog;
    private $model;
    private $statesData;
    private $editable;
    private $state;
       
    
    
    private function stateField(){
        $stateField = isset($this->fields[$this->model_name . "_state"]) ? $this->model_name . "_state" : (isset($this->fields["state"]) ? "state" : null);
                
        if (!$stateField) {
            die("Code: ".__CLASS__."-".__LINE__."-stateField");
        };
        
        return $stateField;
    }

    public function historyBuilder(EModel $item = null, $options = ""){
        
    }
    public function __construct(EModel $model, Glog $glog){
        $this->model = $model;
        $this->glog = $glog;
        $this->id = $model["id"];
        
        $this->common_fields = $this->model->common_fields;
        $this->db_table = $this->model->db_table;
        $this->model_name = $this->model->model_name;
        $this->fields     = $this->model->fields;
        
        
        $stateField = $this->stateField();
        
        $file = cfg_get_filename("settings", $stateField . ".tsv");
        $tsv  = import_tsv($file);
        $this->statesData = arr_index($tsv, "value");
        
        $this->editable = true;
        $this->state = $this->statesData[$this->model[$stateField]];
        
        HistoryManager::setHistoryBuilder($this->glog->repository->model_name, array($this, "historyBuilder"));
        
    }
    public function checkACL($right){
        return $this->glog->repository->checkACL($this,$right);
    }
    public function controls(){
        $item = $this;
        
        return array_filter($this->statesData, function($state) use ($item){
            $right = $state["action"];
            return $item->glog->repository->checkACL($item->model,$right);
        });
    }
    public function history(){
        static $fields = null;
        
        if (is_numeric($item)) $id = $item;
        else $id = $item["id"];
        
        $history = db_find($this->repository->db_table.".history", "objectId", $id, DB_RETURN_ROW);
        $history = array_reverse($history);
        
        
        $hist = array();
        foreach($history as $hist_rec){
            $timestamp  = $hist_rec["timestamp"];
            $subjectId  = $hist_rec["subjectId"];
            
            $m = array();
            if ($hist_rec["action"] == "db_add"){
                $state = "0";
                $comment = _t(ucfirst($this->repository->model_name) . " created");
            }elseif(!empty($hist_rec["comment"]) && preg_match('/'.$this->repository->model_name.'_state = "(\d+)"/', $hist_rec["changes_to"], $m)){
                
                $state = $m[1];
                $comment = $hist_rec["comment"];

            }else{
                $state = _t(ucfirst($this->repository->model_name) . " changed");
                $comment = $hist_rec["comment"];
                if (is_null($fields)) $fields = form_get_fields($this->repository->db_table, "all");
                $comment .= "\nБыло:\n" . $hist_rec["changes_from"] . "\n\nСтало:\n" . $hist_rec["changes_to"];

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
    
    public function nav(){
                
        $nav = array(
            "prev"  => $this->_prev(),
            "start" => $this->_start(),
            "end"   => $this->_end(),
            "next"  => $this->_next(),
        );
        
        $nav["list"] = $this->_listUri();
        
        return $nav;
    }
    
    public function modify(array $to, array $from){
        $this->model = $this->model->modify($to, $from);
        return $this;
        
    }
    public function save($comment = ""){
        $this->model[$this->stateField()] = $this->state["value"];
        $this->model->save($comment);
        return $this;
    }
    
    public function setState($state_value){
        
        if (isset($this->statesData[$state_value])){
            $this->state = $this->statesData[$state_value];
        }
        
        // TODO Handle erroe here
        
        return $this;
    }
    
    private function _start(){
        $res = null;
        return $res;
    }
    
    public function __get($key){
        switch ($key){
            case "id": return $this->id; break;
            case "db_table": return $this->db_table; break;
            case "model_name": return $this->model_name; break;
            case "fields": return $this->model->fields; break;
        }
        
        dosyslog(__METHOD__ . get_callee() . ": FATAL ERROR: Property '".$key."' is not available in class '".__CLASS__."'.");
        die("Code: ".__CLASS__."-".__LINE__."-".$key);
    }
    
    private function _end(){
        $res = null;
        return $res;
    }
    private function _prev(){
        global $_USER;
        
        $res = null;

        $where_clause = $this->glog->filterClause ." AND (id < ".(int) $this->model["id"] .")";
        if (! userHasRight("manager")){
            $where_clause .= " AND (user_id = " . $_USER["id"].") ";
        };
        $res = $this->glog->repository->select("id")->where($where_clause)->orderBy(array("id"=>"DESC"))->limit(1)->fetchAssoc();
        if ($res){
            $res["uri"] = $this->model->getLink($res["id"]);
        }
    
        return $res;
    }
    private function _next(){
        global $_USER;
        
        $res = null;

        $where_clause = $this->glog->filterClause ." AND (id > ".(int) $this->id .")";
        if (! userHasRight("manager")){
            $where_clause .= " AND (user_id = " . $_USER["id"].") ";
        };
        $res = $this->glog->repository->select("id")->where($where_clause)->orderBy(array("id"=>"ASC"))->limit(1)->fetchAssoc();
        if ($res){
            $res["uri"] = $this->model->getLink($res["id"]);
        }
        
        
        return $res;
    }
    private function _listUri(){
        return array(
            "uri" => $this->glog->urlBuilder(),
            "caption" => _t("To the list"),
        );
    }

    /* ArrayAccess implementation for model attributes */
    public function offsetSet($offset, $value) {
        $this->model[$offset] = $value;
    }

    public function offsetExists($offset) {
        if ($offset == "editable") return true;
        return isset($this->model[$offset]);
        
    }

    public function offsetUnset($offset) {
        unset($this->model[$offset]);
        
    }

    public function offsetGet($offset) {
        
        if ($offset == "editable") return $this->editable;
        if ($offset == "state") return $this->state;
        
        return $this->model[$offset];
    }
    
    /* jsonSerializable implementation */
    public function jsonSerialize(){
        $res = $this->model->jsonSerialize();
        $res["editable"] = $this->editable;
        $res["state"] = $this->state;
        return $res;
    }
    
    /* IteratorAggregate implementation */
    public function getIterator() {
        return $this->model->getIterator();
    }
    
    
    
}