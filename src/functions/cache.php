<?php
// require PHP 5.4
define("CACHE_ENABLED", true);
define("CACHE_DELETE_FLAG", uniqid("cache_del"));
function cache($value = null){
    if ( ! CACHE_ENABLED ) return null;
        
    $key = _cache_key();
            
    if ( is_null($value) ){  // get from cache 
        return _cache($key);
    }else{                     // set into cache
        return _cache($key, $value);
    };

}
function cache_del($key){
    cache_set($key, CACHE_DELETE_FLAG);
}
function cache_get($key){
    return _cache($key);
};
function cache_set($key, $value){
    return _cache($key, $value);
}
function cached($key = null){
    if ( ! CACHE_ENABLED ) return null;
        
    if ( is_null($key) ){
        $hash = _cache_key();
    }else{
        $hash = $key;
    }
    
    return ! is_null(_cache($hash));
}
function _cache($key, $value = null){
    static $cache = array();
    
    if ( is_null($value) ){  // get from cache 
        if ( isset($cache[$key]) ){
            // dosyslog(__FUNCTION__.get_callee().": DEBUG: Попадание: ".$function."(".implode(", ", $args).").");
            return $cache[$key];
        }else{
            // dosyslog(__FUNCTION__.get_callee().": DEBUG: Промах: ".$function."(".implode(", ", $args).").");
            return null;
        };
    }else{                     // set into cache
        if ($value === CACHE_DELETE_FLAG){
            if (isset($cache[$key])){
                unset($cache[$key]);
            };
            return;
        }
        
        $cache[$key] = $value;
        // dosyslog(__FUNCTION__.get_callee().": DEBUG: Запись: ".$function."(".implode(", ", $args).").");
        return $value;
    };
}
function _cache_key(){
    global $_PARAMS;
    
    $dbt = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 3);
    $function    = $dbt[2]["function"];
    if (substr($function, -1*strlen("_action")) == "_action"){
        $args = $_PARAMS;
    }else{
        $args  = $dbt[2]["args"];
    };
    $args_hash   = !empty($args) ? md5(serialize($args)) : "";
    $object_hash = !empty($dbt[2]["object"]) ? md5($dbt[2]["class"] . spl_object_hash($dbt[2]["object"])) : "";  // possible risk: spl_object_hash() can reuse hash for another object after deleting the first one.
    
    $key         = $function . "::" . $object_hash . $args_hash;
    
    return $key;
}

