<?php 
class ModelView extends View
{
    private $model;
    public function __construct(EModel $model){
        parent::__construct($model->jsonSerialize());
        $this->model = $model;
    }
    
    public function getFields($view_name){
        
        switch ($view_name){
            case "listView":
                return array_filter(form_get_fields($this->model->repo_name, "list_".$this->model->repo_name),"check_form_field_acl");
                break;
            case "itemView":
            default:
                return array_filter(form_get_fields($this->model->repo_name, "show_".$this->model->model_name),"check_form_field_acl");
        };
    }
    
    protected function defaultView( $options = ""){
        if (!$options) $options = array();
        return $this->itemView($options);
    }
    
    protected function itemView(array $options = array()){
         
        $view =  $this->prepare_view($this->data, $this->getFields(__FUNCTION__));
        
        return $view;
        
    }
    
    protected function listView(array $options = array()){
        
        
        $view =  $this->prepare_view($this->data, $this->getFields(__FUNCTION__));
        
        return $view;
        
    }
    
}