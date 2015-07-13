<?php
function engine_api_create_slug_action(){
    global $_PARAMS;
    global $_DATA;
    global $IS_API_CALL;
    
    $str = ! empty($_PARAMS["str"]) ? $_PARAMS["str"] : null;
    
    $_DATA["html"] = "";
    
    if ($str){
        $_DATA["html"] = glog_codify($str);
    };
    
    // $IS_API_CALL = true;
}
