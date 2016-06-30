<?php
abstract class EHistoryManager
{
    protected static $historyBuilders = array();
    
    public static function getHistory(Model $model, $options = ""){
        
        if (isset(self::$historyBuilders[$model->repo_name])){
            return call_user_func(self::$historyBuilders[$model->repo_name],$model, $options);
        };
        return array();
    }
    
    public static function setHistoryBuilder($repository_name, $func){
        self::$historyBuilders[$repository_name] = $func;
        // dosyslog(__METHOD__.get_callee().": DEBUG: Set historyBuilder function for '".$repository_name."': '".serialize($func)."'.");
    }
    
    public static function getHistoryRepository(Model $model){
        
        $history_repo_name = $model->repo_name . ".history";
        try{
            $history_repo = HistoryRepository::create($history_repo_name);
            return $history_repo;
        } catch (Exception $e) {
            dosyslog(__METHOD__.get_calee().": ERROR: Can not get '".$history_repo_name."' repository.");
            return null;
        }
    }
    
    public static function defaultHistoryBuiler(Model $model, $options = ""){
        
        $repo = self::getHistoryRepository($model);
        $history = $repo->where("objectId",$model["id"])->orderBy(array("id"=>"DESC"))->fetchAll();
        return $history;
    }
}