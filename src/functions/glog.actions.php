<?php
function show_glog_action(){
    global $_PARAMS;
    
    $repo_name = ! empty($_PARAMS["repo_name"]) ? $_PARAMS["repo_name"] : null;
    $filter = ! empty($_PARAMS["filter"]) ? $_PARAMS["filter"] : null;
    $filterValue = ! is_null($_PARAMS["filterValue"]) ? $_PARAMS["filterValue"] : null;
    $group = ! empty($_PARAMS["group"]) ? $_PARAMS["group"] : null;
    $groupValue = ! is_null($_PARAMS["groupValue"]) ? $_PARAMS["groupValue"] : null;
    
    $id = ! empty($_PARAMS["id"]) ? $_PARAMS["id"] : null;
    
    $action = ! empty($_PARAMS["action"]) ? $_PARAMS["action"] : null;
    
    if ( ! $id && ! $filterValue && is_numeric($filter) ){  // uri: model/id
        $id = $filter;
        $filter = null;
    };
    
    if (!$filter)              $_PARAMS["filter"]      = "all";
    if (is_null($filterValue)) $_PARAMS["filterValue"] = "all";
    if (!$group)               $_PARAMS["group"]       = "none";
    if (is_null($groupValue))  $_PARAMS["groupValue"]  = "all";
    
    if ($id)                   $_PARAMS["id"] = $id;
    
    
    if ($id){
        $action_function = $repo_name . "_" . $action . "_action";

        if ($action && function_exists($action_function)){
            
            call_user_func($action_function);
        }else{
            show_glog_item_action();
        };
    }else{
        show_glog_list_action();
    }
}
function show_glog_list_action(){
    global $_PARAMS;
    global $_DATA;
    global $_PAGE;
    global $CFG;
       
                    
    $glog = new Glog($_PARAMS);
        
    $_DATA["repo_name"]      = $glog->repo_name;
    $_DATA["fields"]        = $glog->fields;
    $_DATA["model_name"]  = $glog->model_name;
    $_DATA["filter"]     = $glog->filter;
    $_DATA["nav"]        = $glog->nav();

    
        
    $_DATA["items_by_group"] = array_map(function($items){
        return array_map(function($item){
            $view = View::getView($item);
            return $view->prepare("list");
        }, $items);
    }, $glog->all());
    
    
    $_DATA["groups_data"] = $glog->getGroups();
      
    $_PAGE["header"]       = $glog->getHeader();
        
    // Шаблон страницы
    if ( empty($_PAGE["templates"]["content"]) && ! empty($_PAGE["templates"]["list"]) ){
        set_template_file("content", $_PAGE["templates"]["list"]);
    };
       
}
function show_glog_item_action(){
    global $_PARAMS;
    global $_DATA;
    global $_PAGE;
    global $CFG;
    
    $id = ! empty($_PARAMS["id"]) ? $_PARAMS["id"] : null;
    
    if ($id){

        $glogItem = (new Glog($_PARAMS))->getItem($id);
        
        $view = View::getView($glogItem);
        $_DATA["item"] = $view->prepare();
        
        $_DATA["repo_name"]  = $glogItem->repo_name;
        $_DATA["model_name"]  = $glogItem->model_name;
        $_DATA["fields"] = $view->getFields("item");
        
        // Навигация
        $_DATA["nav"] =  $glogItem->nav();
        
        
        
        // Модерация
        $_DATA["moderation_needed"] = !empty($glogItem["state"]["moderation_form"]);
        $_DATA["moderation_forms"]  = array($glogItem["state"]["moderation_form"]);
        
        
        
        // Операции
        $_DATA["controls"] = $glogItem->controls();
        
        // История
        $_DATA["history"] = $view->prepare("history");
        
        
        // Заголовок страницы
        $_PAGE["header"] = $_PAGE["title"] = _t(ucfirst($glogItem->model_name)). " #". $glogItem["id"] . " от " . glog_rusdate($glogItem["created"]);
        
        
        // Шаблон страницы
        if ( empty($_PAGE["templates"]["content"]) && ! empty($_PAGE["templates"]["item"]) ){
            set_template_file("content", $_PAGE["templates"]["item"]);
        };
      
    };
}