<?php
function get_topmenu(array $badges = array()){
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

        if ($isOk){

            // badges
            if (isset($badges[$menuItem["href"]])){
                $menuItem["badge"] = $badges[$menuItem["href"]];
            }
            //

            if ( !empty($menuItem["parent"]) && isset($topmenu[ $menuItem["parent"] ]) ){
                if (empty($topmenu[ $menuItem["parent"] ]["submenu"])) $topmenu[ $menuItem["parent"] ]["submenu"] = array();
                $topmenu[ $menuItem["parent"] ]["submenu"][ $menuItem["href"] ] = $menuItem;
            }else{
                $topmenu[ $menuItem["href"] ] = $menuItem;
            };
        };
    };

    return $topmenu;
};
