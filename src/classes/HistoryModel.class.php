<?php
class HistoryModel extends EModel
{
    protected $repo_name;
    protected $model_name;
    
    public function __construct(array $data){
        
        if (!empty($data["db"])){
            $this->repo_name  = db_get_name($data["db"]) . ".history";
            $this->model_name = db_get_obj_name($data["db"]) . ".history";
        }else{
            throw new Exception("Empty HistoryModel not allowed.");
        }
                
        parent::__construct($data);
    }
    
    public function changes(){
        if (!empty($this->data["changes_to"])){
            return ChangesSet::createFromString($this->data["changes_to"], $this->data["changes_from"]);
        }else{
            return null;
        }
    }

    
}