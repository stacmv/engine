<?php
abstract class EUrlManager
{
    protected static $urlBuilders = array();
    
    public static function getLink(EModel $model, $options = ""){
                
        if (isset(self::$urlBuilders[$model->repo_name])){
            return call_user_func(self::$urlBuilders[$model->repo_name],$model, $options);
        }else{
            $id = !empty($options["id"]) ? $options["id"] : $model["id"];
            return  db_get_meta($model->repo_name, "model_uri_prefix") . $model->repo_name . "/". $id;
        };
    }
    
    public static function setUrlBuilder($key, $func){
        self::$urlBuilders[$key] = $func;
        // dosyslog(__METHOD__.get_callee().": DEBUG: Set urlBuilder function for '".$key."': '".serialize($func)."'.");
    }
    
}