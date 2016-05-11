<?php
abstract class EHistoryManager
{
    protected static $historyBuilders = array();
    
    public static function getHistory(EModel $model, $options = ""){
                
        if (isset(self::$historyBuilders[$model->db_table])){
            return call_user_func(self::$historyBuilders[$model->db_table],$model, $options);
        }else{
            return  array();
        };
    }
    
    public static function setHistoryBuilder($model_db_table, $func){
        self::$historyBuilders[$model_db_table] = $func;
        // dosyslog(__METHOD__.get_callee().": DEBUG: Set historyBuilder function for '".$model_db_table."': '".serialize($func)."'.");
    }
    
}