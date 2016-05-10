<?php
function show_glog_action(){
    global $_PARAMS;
    
    $filter = ! empty($_PARAMS["filter"]) ? $_PARAMS["filter"] : null;
    $filterValue = ! is_null($_PARAMS["filterValue"]) ? $_PARAMS["filterValue"] : null;
    $group = ! empty($_PARAMS["group"]) ? $_PARAMS["group"] : null;
    $groupValue = ! is_null($_PARAMS["groupValue"]) ? $_PARAMS["groupValue"] : null;
    
    $id = ! empty($_PARAMS["id"]) ? $_PARAMS["id"] : null;
    
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
        show_glog_item_action();
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
        
    $_DATA["model"]      = $glog->modelName;
    $_DATA["fields"]     = form_get_fields($glog->modelName, "list_".$glog->modelName);
    $_DATA["item_name"]  = $glog->itemName;
    $_DATA["filter"]     = $glog->filterName;
    $_DATA["nav"]        = $glog->navigation;

    $_DATA["items_by_group"] = $glog->all();
        
    
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
        
        
        $_DATA["item"] = $glogItem;
        
        $_DATA["model"] = $glogItem->model_name;
        $_DATA["fields"] =$glogItem->fields;
        
        // Навигация
        $_DATA["nav"] =  $glogItem->nav();
        
        // Модерация
        $_DATA["moderation_needed"] = $glogItem->moderationNeeded();
        $_DATA["moderation_forms"]  = $glogItem->moderationForms();
        
        
        
        // Операции
        $_DATA["controls"] = $glogItem->controls();
        
        
        // Заголовок страницы
        $_PAGE["header"] = $_PAGE["title"] = _t(ucfirst($glogItem->model_name)). " #". $glogItem["id"] . " от " . glog_rusdate($glogItem["created"]);
        
        
        // Шаблон страницы
        if ( empty($_PAGE["templates"]["content"]) && ! empty($_PAGE["templates"]["item"]) ){
            set_template_file("content", $_PAGE["templates"]["item"]);
        };
      
    };
}