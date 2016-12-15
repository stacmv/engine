<?php 
class ModelView extends View
{
    protected $model;
    public function __construct(EModel $model){
        parent::__construct($model->jsonSerialize());
        $this->model = $model;
    }
    
    public function getFields($view_name){
        
        switch ($view_name){
            case "historyView":
                return $this->model->fields;
                break;
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
    
    protected function historyView($options = ""){
        
        $model = $this->model;
        $fields = $this->getFields(__FUNCTION__);
        $view = array_map(function(HistoryModel $hist_rec) use ($model){
            
            $fields = array_filter(form_get_fields($model->repo_name, "all"), "check_form_field_acl"); // атрибуты основной модели.
            
            $timestamp  = $hist_rec["timestamp"];
            $subjectId  = $hist_rec["subjectId"];
            
            $state_carry = 0;
            
            $changes_html = "";
            
            $m = array();
            if ($hist_rec["action"] == "db_add"){
                $state = new GlogState($model->state_field, 0);
                $comment = _t(ucfirst($model->model_name) . " created");
            }elseif($hist_rec->isChanged($model->state_field)){
                $state_carry = $hist_rec->changes()->to[$model->state_field];
                $state = new GlogState($model->state_field, $state_carry);
                $comment = $hist_rec["comment"];

            }else{
                $state = new GlogState($model->state_field, $state_carry);
                $comment = $hist_rec["comment"];
                
                // Generate "Changes table"
                $labels = array_map(function($field){
                    return $field["label"];
                }, array_filter($fields, function($field){
                    return $field["label"];
                }));
                if ($hist_rec->isChanged()){
                    $changes_to   = $hist_rec->changes()->to;
                    $changes_from = $hist_rec->changes()->from;
                    ob_start();
                        include cfg_get_filename("templates", "glog.default.history_changes.htm");
                        $changes_html = ob_get_contents();
                    ob_end_clean();
                };
            };
            
            $hist = array_merge(
                $hist_rec->jsonSerialize(),
                array(
                    "timestamp" => $timestamp,
                    "state"     => $state,
                    "subjectId" => $subjectId,
                    "comment"   => $comment,
                    "changes_html" => $changes_html,
                )
            );
            
            return  $hist;
            
        }, $this->data["history"]);
        
        return $view;
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