<?php
function engine_api_create_slug_action(){
    global $_PARAMS;
    global $_DATA;
    global $IS_API_CALL;
    
    $str = ! empty($_PARAMS["str"]) ? $_PARAMS["str"] : null;
    
    $_DATA["html"] = "";
    
    if ($str){
        if ( (int) $str && strlen( (string) (int) $str) <= 3){
            $str = glog_str_from_num( (int) $str) . " " .  ltrim(substr($str, strlen( (string) (int) $str)));
        };
        $_DATA["html"] = glog_codify($str);
    };
    
    // $IS_API_CALL = true;
}
