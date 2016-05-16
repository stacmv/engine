<?php
class GlogItem extends Model implements ArrayAccess, jsonSerializable, IteratorAggregate
{
    private $id;
    private $glog;
    private $model;
    private $statesData;
    private $editable;
           
    
    
    

    public function historyBuilder(EModel $item = null, $options = ""){
        
    }
    public function __construct(EModel $model, Glog $glog){
        $this->model = $model;
        $this->glog = $glog;
        $this->id = $model["id"];
        
        $this->common_fields = $this->model->common_fields;
        $this->repo_name = $this->model->repo_name;
        $this->model_name = $this->model->model_name;
        $this->fields     = $this->model->fields;
        
        
        $stateField = $this->model->state_field;
        
        $file = cfg_get_filename("settings", $stateField . ".tsv");
        $tsv  = import_tsv($file);
        $this->statesData = arr_index($tsv, "value");
        
        $this->editable = true;
        $this->stateData = $this->getStateData();
        
        HistoryManager::setHistoryBuilder($this->glog->repository->model_name, array($this, "historyBuilder"));
        
    }
    public function checkACL($right){
        return $this->model->checkACL($right);
    }
    public function controls(){
        $item = $this;
        
        return array_filter($this->statesData, function($state) use ($item){
            return $item->checkACL($state["action"]);
        });
    }
    public function history(){
        static $fields = null;
        
        if (is_numeric($item)) $id = $item;
        else $id = $item["id"];
        
        $history = db_find($this->repository->repo_name.".history", "objectId", $id, DB_RETURN_ROW);
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
                if (is_null($fields)) $fields = form_get_fields($this->repository->repo_name, "all");
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

    public function addState($state_value){
        $this->model = $this->model->addState($state_value);
        return $this;
    }
    public function inState($state_value){
        return $this->model->inState($state_value);
    }
    public function removeState($state_value){
        $this->model = $this->model->removeState($state_value);
        return $this;
    }    
    
    public function save($comment=""){
        $this->model = $this->model->save($comment);
        return $this;
    }
    
    public function getStateData(){
        return $this->statesData[$this->model->state];
    }
    public function setStateData($state_value){
        
        if (isset($this->statesData[$state_value])){
            $this->model->state = $this->statesData[$state_value];
        }
        
        // TODO Handle error here
        
        return $this;
    }
    
    private function _start(){
        $res = null;
        return $res;
    }
    
    public function __get($key){
        switch ($key){
            case "id": return $this->id; break;
            case "repo_name": return $this->repo_name; break;
            case "model_name": return $this->model_name; break;
            case "fields": return $this->model->fields; break;
            case "state": return $this->getStateData();break;
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