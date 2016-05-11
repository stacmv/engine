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
                return form_get_fields($this->model->db_table, "list_".$this->model->db_table);
                break;
            case "itemView":
            default:
                return form_get_fields($this->model->db_table, "show_".$this->model->model_name);
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