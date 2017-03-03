<?php
// require PHP 5.4
define("FILE_CACHE_ENABLED", true);
define("FILE_CACHE_DELETE_FLAG", uniqid("file_cache_del"));
define("FILE_CACHE_DIR", ".cache/file_cache/");
define("FILE_CACHE_TTL_DEFAULT", 3600); // Time to live in seconds
function file_cache($value = null, $ttl = null){
        
    if ( ! FILE_CACHE_ENABLED ) return null;
    
    $key = glog_codify(_cache_key(), GLOG_CODIFY_FILENAME);
            
    if ( is_null($value) ){  // get from cache 
        return _file_cache("get", $key, null, $ttl === false ? false : true); // $ttl === false => ignore TTL when read cached data
    }else{                     // set into cache
        return _file_cache("set", $key, $value, $ttl);
    };

}
function file_cache_cleanup($time = ""){
    
    if (!$time) $time = time();
    
    $files  = glob(FILE_CACHE_DIR . "*");
    if ($files){
        foreach($files as $file){
            // check TTL
            if ($time > get_file_cached_time($file)){
                unlink($file);
                dosyslog(__FUNCTION__.get_callee().": DEBUG: Инвалидация: ".$file." после ". get_file_cache_ttl() ." ceк.");
            };
        };
    };
}
function file_cache_del($key){
    _file_cache("delete", $key, null, null);
}
function file_cache_get($key, $ignoreTTL = false){
    $hash = glog_codify($key, GLOG_CODIFY_FILENAME);
    if ($hash !== $key){
        dosyslog(__FUNCTION__.get_callee().": WARNING: key:'".$key."' is not suitable for file name. Consider to change it.");
    }
    return _file_cache("get", $hash, null, $ignoreTTL);
};
function file_cache_get_filename($key, $ignoreTTL = false){
    $hash = glog_codify($key, GLOG_CODIFY_FILENAME);
    if ($hash !== $key){
        dosyslog(__FUNCTION__.get_callee().": WARNING: key:'".$key."' is not suitable for file name. Consider to change it.");
    }
    return _file_cache("get_filename", $hash, null, $ignoreTTL);
};
function file_cache_set($key, $value, $ttl = FILE_CACHE_TTL_DEFAULT){
    $hash = glog_codify($key, GLOG_CODIFY_FILENAME);
    if ($hash !== $key){
        dosyslog(__FUNCTION__.get_callee().": WARNING: key:'".$key."' is not suitable for file name. Consider to change it.");
    }
    return _file_cache("set", $hash, $value, $ttl);
}
function file_cached($key = null, $ignoreTTL = false){
    
    if ( ! FILE_CACHE_ENABLED ) return null;
        
    if ( is_null($key) ){
        $hash = glog_codify(_cache_key(), GLOG_CODIFY_FILENAME);
    }else{
        $hash = glog_codify($key, GLOG_CODIFY_FILENAME);
        if ($hash !== $key){
            dosyslog(__FUNCTION__.get_callee().": WARNING: key:'".$key."' is not suitable for file name. Consider to change it.");
        }
    }
    
    return ! is_null(_file_cache("get_filename", $hash, null, !$ignoreTTL));
}
function _file_cache($command, $key, $value, $ttl){
    static $file_cache = array();
    
    if (!is_dir(FILE_CACHE_DIR)) mkdir(FILE_CACHE_DIR, 0777, true);
    
    if (empty($file_cache)){
        $file_cache = file_cache_init();
    };
    
    switch($command){
        case "get": // get from cache 
        case "get_filename":
            if ( isset($file_cache[$key]) ){
                $key_file = $file_cache[$key];
                if (($ttl === false /* ignoreTTL */ ) || (time() <= get_file_cached_time($key_file))){
                    dosyslog(__FUNCTION__.get_callee().": DEBUG: Попадание: ".$key.".");
                    if ($command == "get"){
                        return glog_file_read( $key_file );
                    }else{
                        return $key_file;
                    };
                }else{
                    dosyslog(__FUNCTION__.get_callee().": DEBUG: Промах: ".$key.".");
                    return false;
                };
            }else{
                dosyslog(__FUNCTION__.get_callee().": DEBUG: Промах: ".$key.".");
                return null;
            };
            break;
        
        case "delete":
            if (isset($file_cache[$key])){
                @unlink($file_cache[$key]);
                unset($file_cache[$key]);
                dosyslog(__FUNCTION__.get_callee().": DEBUG: Принудительная инвалидация: ".$key);
            }else{
                dosyslog(__FUNCTION__.get_callee().": WARNING: Ошибка ключ '".$key."' отсутствует в кеше.");
            }
            return;
        
        case "set": // set into cache
            $key_file = get_file_cached_file($key, $ttl);
            if (file_put_contents($key_file, $value)){
                $file_cache[$key] = $key_file; 
                dosyslog(__FUNCTION__.get_callee().": NOTICE: Запись: ".$key." в " . $key_file . ", валиден до ".glog_isodate(get_file_cached_time($key_file), true).".");
            }else{
                dosyslog(__FUNCTION__.get_callee().": ERROR: Can not write cache file '". $key_file.". Data lost.");
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
function get_file_cached_time($file){
    
    // Filename format: basename . timestamp . extension
    
    $parts = pathinfo($file);
    $time  = pathinfo($parts["filename"], PATHINFO_EXTENSION);
    
    if (preg_match("/^\d+$/", $time)){
        return $time;
    }else{
        return time()+60; // if time to live for file is not set
    }
    
}
function get_file_cached_key($file){
    
    // Filename format: basename . timestamp . extension
    
    $basename = basename($file);
    
    $m = array();
    if (preg_match("/^(.+?)(?:\.(\d+))?\.([^.]+?)$/", $basename, $m)){
        return $m[1].".".$m[3];
    }else{
        return $basename;
    }
    
}
function get_file_cached_file($key, $ttl){
    
    $time_valid_for = (int) $ttl ? time() + $ttl : time() + FILE_CACHE_TTL_DEFAULT;
    
    $parts = pathinfo($key);
    
    if (!isset($parts["extension"])) $parts["extension"] = "cache";
    
    $file = FILE_CACHE_DIR . $parts["filename"] . "." . $time_valid_for . "." . $parts["extension"];
    
    return $file;
}    
    
function get_file_cached_versions($key){
    
    $parts = pathinfo($key);
    
        
    if (!$parts["extension"]) $parts["extension"] = "cache";
    
    $pattern = FILE_CACHE_DIR . $parts["filename"] . ".*.".$parts["extension"];
    
    
    
    $versions = glob($pattern);
    
    
    
    return $versions;
    
}
function file_cache_init(){
    
    $files = glob(FILE_CACHE_DIR."*", GLOB_NOSORT);
    if ($files) {
        $keys = array_map("get_file_cached_key", $files);
        $file_cache = array_combine($keys, $files);
        $invalid = array_diff($files, $file_cache);
        
        if (!empty($invalid)){
            array_map("unlink",$invalid);
        };
    }else{
        $file_cache = array();
    }
    return $file_cache;
        
}