<?php
class GlogItem extends Model implements ArrayAccess, jsonSerializable, IteratorAggregate
{
    private $id;
    private $model;
    private $editable;
    private $glog;
    
    
           
    
    
    

    public function historyBuilder(EModel $item = null, $options = ""){
        
    }
    public function __construct(Model $model, Glog $glog){
        $this->model = $model;
        $this->glog = $glog;
        $this->id = $model["id"];
        
        $this->common_fields = $this->model->common_fields;
        $this->repo_name = $this->model->repo_name;
        $this->model_name = $this->model->model_name;
        $this->fields     = $this->model->fields;
        
        
        $this->editable = true;
        $this->state    = new GlogState($model->state_field, $model->state);
        
        HistoryManager::setHistoryBuilder($this->glog->repository->model_name, array($this, "historyBuilder"));
        
    }
    public function checkACL($right){
        return $this->model->checkACL($right);
    }
    public function controls(){
        $item = $this;
        
        $controls =  arr_index(array_filter($this->state->config(), function($state) use ($item){
            return $item->checkACL($state["action"]);
        }), "action");
        
        return $controls;
    }
    public function history(){
        static $fields = null;
        
        $history = $this->getHistory();
                
        $history = array_reverse($history);
        
        return $history;
        
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
    
    public function modify(array $to, array $from = array()){
        $this->model = $this->model->modify($to, $from);
        return $this;
        
    }

    public function addState($state_value){
        $this->state->add($state_value);
        $this->model["state"] = $this->state->value();
        return $this;
    }
    public function inState($state_value){
        return $this->state->has($state_value);
    }
    public function removeState($state_value){
        $this->state = $this->state->remove($state_value);
        $this->model["state"] = $this->state->value();
        return $this;
    }    
    
    public function save($comment=""){
        try{
            $this->model = $this->model->save($comment);
        }catch(Exception $e){
            $err_msg = "Ошибка при сохранении данных. Код: ".$e->getMessage();
            dosyslog(__METHOD__.get_callee().": ERROR: ".$err_msg);
            set_session_msg($err_msg, "fail");
        };
        return $this;
    }

    public function setState($state_value){
        
        $this->state->set($state_value);
        $this->model["state"] = $state_value;
        
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
            case "state": return $this->state;break;
            case "state_field": return $this->model->state_field;break;
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