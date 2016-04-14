<?php
// require PHP 5.4
define("CACHE_ENABLED", true);
define("CACHE_DELETE_FLAG", uniqid("cache_del"));
function cache($value = null){
    if ( ! CACHE_ENABLED ) return null;
        
    $dbt = debug_backtrace(false, 2);
    $function = $dbt[1]["function"];
    $args     = $dbt[1]["args"];
    $key      = $function . "::" . md5(serialize($args));
            
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
        $dbt = debug_backtrace(false, 2);
        
        $function = $dbt[1]["function"];
        $args     = $dbt[1]["args"];
        
        $hash     = $function . "::" . md5(serialize($args));
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


