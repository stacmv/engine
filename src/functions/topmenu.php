<?php
function get_topmenu(){
    $menu = array();
    $menu_file = cfg_get_filename("settings", "menu.tsv");
    $tmp = import_tsv($menu_file);
    if (!$tmp){
        dosyslog(__FUNCTION__.": FATAL ERROR: Can not import menu file.");
        die("Code: df-".__LINE__."-topmenu");
    };
    
    // Skip commented lines
    $menu = array_filter($tmp, function($l){
        if (substr($l["href"],0,1) == ";"){
            dosyslog(__FUNCTION__.get_callee().": DEBUG: Commented menu item '".$l["href"]."' skipped.");
            return false;
        };
        return true;
    });
   
        
    // формирование topmenu для конкретного пользователя
    $topmenu = array();
   
    foreach($menu as $menuItem){
        $rights = ! empty($menuItem["rights"]) ? explode(",",$menuItem["rights"]) : array();
        if ( $rights ) {
            $isOk = false;
            if (!empty($rights)) foreach($rights as $right) $isOk = userHasRight( trim($right) );
        }else{
            $isOk = true;
        };
        
        if ($isOk) $topmenu[] = $menuItem;
    };
    
    return $topmenu;
};