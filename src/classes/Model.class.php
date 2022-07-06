<?php
class Model extends EModel
{
    protected $repo_name;
    protected $model_name;
    
    public function __construct(array $data = array()){
        $this->model_name  = self::_get_model_name_for_class( get_called_class() );
        $this->repo_name   = self::_get_repo_name_for_model_name($this->model_name);
        
        parent::__construct($data);
    }
    
    public function getName(){
        if (isset($this->data["name"])){
            return $this->data["name"];
        }else{
            return "N/A". (!empty($this->data["id"]) ? " (id:".$this->data["id"].")": "");
        }
    }
    
    protected function _get_repo_name_for_model_name($model_name){
        return implode(".", array_map("_plural", explode(".", $model_name)));
    }
    protected function _get_model_name_for_class($class_name){
        $class_name = str_replace("_model", "", $class_name); // if plural and singular forms of the word are same, then model class has suffix "_model"
        return strtolower(implode(".", preg_split("/([[:upper:]][[:lower:]_]+)/", $class_name, -1, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY )));
    }
    
}