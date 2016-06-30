<?php
// require PHP 5.4
define("FILE_CACHE_ENABLED", true);
define("FILE_CACHE_DELETE_FLAG", uniqid("file_cache_del"));
define("FILE_CACHE_DIR", DATA_DIR.".file_cache/");
define("FILE_CACHE_TTL_DEFAULT", 20); // Time to live in seconds
function file_cache($value = null){
    global $_PARAMS;
    
    if ( ! FILE_CACHE_ENABLED ) return null;
        
    $dbt = debug_backtrace(false, 2);
    $function = $dbt[1]["function"];
    if (substr($function, -1*strlen("_action")) == "_action"){
        $args = $_PARAMS;
    }else{
        $args     = $dbt[1]["args"];
    };
    $key = $function . "___" . md5(serialize($args));
            
    if ( is_null($value) ){  // get from cache 
        return _file_cache($key);
    }else{                     // set into cache
        return _file_cache($key, $value);
    };

}
function file_cache_del($key){
    file_cache_set($key, FILE_CACHE_DELETE_FLAG);
}
function file_cache_get($key){
    return _file_cache($key);
};
function file_cache_set($key, $value){
    return _file_cache($key, $value);
}
function file_cached($key = null){
    global $_PARAMS;
    
    if ( ! FILE_CACHE_ENABLED ) return null;
        
    if ( is_null($key) ){
        $dbt = debug_backtrace(false, 2);
        
        $function = $dbt[1]["function"];
        if (substr($function, -1*strlen("_action")) == "_action"){
            $args = $_PARAMS;
        }else{
            $args     = $dbt[1]["args"];
        };
        
        $hash     = $function . "___" . md5(serialize($args));
    }else{
        $hash = $key;
    }
    
    return ! is_null(_file_cache($hash));
}
function _file_cache($key, $value = null){
    static $file_cache = array();
    
    if (!is_dir(FILE_CACHE_DIR)) mkdir(FILE_CACHE_DIR, 0777, true);
    
    if (empty($file_cache)){
        $files = glob(FILE_CACHE_DIR."*", GLOB_NOSORT);
        if ($files) $files = array_map("basename", $files);
        $file_cache = array_combine($files, array_fill(0,count($files), 0));
    };
    
    
    if ( is_null($value) ){  // get from cache 
        if ( isset($file_cache[$key]) ){
            
            // check TTL
            if (time() - filemtime(FILE_CACHE_DIR . $key) <= get_file_cache_ttl() ){
                dosyslog(__FUNCTION__.get_callee().": DEBUG: Попадание: ".$key.".");
                $file_cache[$key]++;
                return glog_file_read( FILE_CACHE_DIR . $key );
            }else{
                unlink(FILE_CACHE_DIR . $key);
                dosyslog(__FUNCTION__.get_callee().": DEBUG: Инвалидация: ".$key." после ". get_file_cache_ttl() ." ceк.");
                return null;
            };
        }else{
            dosyslog(__FUNCTION__.get_callee().": DEBUG: Промах: ".$key.".");
            return null;
        };
    }else{                     // set into cache
        if ($value === FILE_CACHE_DELETE_FLAG){
            if (isset($file_cache[$key])){
                unlink(FILE_CACHE_DIR . $key);
            };
            return;
        }
        
        if (file_put_contents(FILE_CACHE_DIR . $key, $value)){
            $file_cache[$key] = 0;
            dosyslog(__FUNCTION__.get_callee().": DEBUG: Запись: ".$key.".");
        }else{
            dosyslog(__FUNCTION__.get_callee().": ERROR: Can not write cache file '".FILE_CACHE_DIR . $key.". Data lost.");
        }
        return $value;
    };
}
function get_file_cache_ttl(){
    global $CFG;
    
    if (!empty($CFG["FILE_CACHE"]) && !empty($CFG["FILE_CACHE"]["ttl"])){
        return (int) $CFG["FILE_CACHE"]["ttl"];
    }else{
        return FILE_CACHE_TTL_DEFAULT;
    };

}


