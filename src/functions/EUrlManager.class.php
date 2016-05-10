<?php
class EUrlManager
{
    protected static $urlBuilders = array();
    
    public static function getLink(EModel $model, $options = ""){
                
        if (isset(self::$urlBuilders[$model->db_table])){
            return call_user_func(self::$urlBuilders[$model->db_table],$model, $options);
        }else{
            $id = !empty($options["id"]) ? $options["id"] : $model["id"];
            return  db_get_meta($model->db_table, "model_uri_prefix") . $model->db_table . "/". $id;
        };
    }
    
    public static function setUrlBuilder($model_db_table, $func){
        self::$urlBuilders[$model_db_table] = $func;
        dosyslog(__METHOD__.get_callee().": DEBUG: Set urlBuilder function for '".$model_db_table."': '".serialize($func)."'.");
    }
    
}