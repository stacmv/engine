<?php
function get_return_url(){
    global $CFG;
    
    return_url_init();
    
    if ( ! empty($_SESSION["return_url_stack"]) ){
        return $_SESSION["return_url_stack"][ count($_SESSION["return_url_stack"])-1 ];
    }elseif(userHasRight("access") && ! empty($_CFG["URL"]["dashboard"]) ){
        return $CFG["URL"]["base"] . $CFG["URL"]["dashboard"] . $CFG["URL"]["ext"];
    }else{
        return $CFG["URL"]["base"];
    };
    
}
function return_url_init(){
    if ( ! isset($_SESSION["return_url_stack"]) ||  ! is_array($_SESSION["return_url_stack"]) ){
        $_SESSION["return_url_stack"] = array();
    };
}
function return_url_push($url){
    return_url_init();
    array_push($_SESSION["return_url_stack"], $url);
};
function return_url_pop(){
    return_url_init();
    
    return array_pop($_SESSION["return_url_stack"]);
}
