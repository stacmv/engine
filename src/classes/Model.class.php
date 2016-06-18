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
    
    
    protected function _get_repo_name_for_model_name($model_name){
        return _plural($model_name);
    }
    protected function _get_model_name_for_class($class_name){
        return strtolower(implode(".", preg_split("/([[:upper:]][[:lower:]]+)/", $class_name, null, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY )));
    }
    
}