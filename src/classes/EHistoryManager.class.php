<?php
abstract class EHistoryManager
{
    protected static $historyBuilders = array();
    
    public static function getHistory(EModel $model, $options = ""){
                
        if (isset(self::$historyBuilders[$model->repo_name])){
            return call_user_func(self::$historyBuilders[$model->repo_name],$model, $options);
        }else{
            return  array();
        };
    }
    
    public static function setHistoryBuilder($repository_name, $func){
        self::$historyBuilders[$repository_name] = $func;
        // dosyslog(__METHOD__.get_callee().": DEBUG: Set historyBuilder function for '".$repository_name."': '".serialize($func)."'.");
    }
    
}