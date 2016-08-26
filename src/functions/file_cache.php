<?php
// require PHP 5.4
define("FILE_CACHE_ENABLED", true);
define("FILE_CACHE_DELETE_FLAG", uniqid("file_cache_del"));
define("FILE_CACHE_DIR", DATA_DIR.".cache/file_cache/");
define("FILE_CACHE_TTL_DEFAULT", 3600); // Time to live in seconds
function file_cache($value = null){
        
    if ( ! FILE_CACHE_ENABLED ) return null;
    
    $key = glog_codify(_cache_key(), GLOG_CODIFY_FILENAME);
            
    if ( is_null($value) ){  // get from cache 
        return _file_cache($key);
    }else{                     // set into cache
        return _file_cache($key, $value);
    };

}
function file_cache_cleanup($time = ""){
    
    if (!$time) $time = time();
    
    $files  = glob(FILE_CACHE_DIR . "*");
    if ($files){
        foreach($files as $file){
            // check TTL
            if ($time - filemtime($file) > get_file_cache_ttl() ){
                unlink($file);
                dosyslog(__FUNCTION__.get_callee().": DEBUG: Инвалидация: ".$file." после ". get_file_cache_ttl() ." ceк.");
            };
        };
    };
}
function file_cache_del($key){
    file_cache_set($key, FILE_CACHE_DELETE_FLAG);
}
function file_cache_get($key){
    $hash = glog_codify($key, GLOG_CODIFY_FILENAME);
    if ($hash !== $key){
        dosyslog(__FUNCTION__.get_callee().": WARNING: key:'".$key."' is not suitable for file name. Consider to change it.");
    }
    return _file_cache($hash);
};
function file_cache_set($key, $value){
    $hash = glog_codify($key, GLOG_CODIFY_FILENAME);
    if ($hash !== $key){
        dosyslog(__FUNCTION__.get_callee().": WARNING: key:'".$key."' is not suitable for file name. Consider to change it.");
    }
    return _file_cache($hash, $value);
}
function file_cached($key = null){
    
    if ( ! FILE_CACHE_ENABLED ) return null;
        
    if ( is_null($key) ){
        $hash = glog_codify(_cache_key(), GLOG_CODIFY_FILENAME);
    }else{
        $hash = glog_codify($key, GLOG_CODIFY_FILENAME);
        if ($hash !== $key){
            dosyslog(__FUNCTION__.get_callee().": WARNING: key:'".$key."' is not suitable for file name. Consider to change it.");
        }
    }
    
    return ! is_null(_file_cache($hash));
}
function _file_cache($key, $value = null){
    static $file_cache = array();
    
    if (!is_dir(FILE_CACHE_DIR)) mkdir(FILE_CACHE_DIR, 0777, true);
    
    if (empty($file_cache)){
        $files = glob(FILE_CACHE_DIR."*", GLOB_NOSORT);
        if ($files) {
            $files = array_map("basename", $files);
            $file_cache = array_combine($files, array_fill(0,count($files), 0));
        }else{
            $file_cache = array();
        }
    };
    
    
    if ( is_null($value) ){  // get from cache 
        if ( isset($file_cache[$key]) ){
            
            if (file_exists(FILE_CACHE_DIR . $key)){
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
                dosyslog(__FUNCTION__.get_callee().": DEBUG: Предполагалась инвалидация: ".$key." после ". get_file_cache_ttl() ." ceк., но файл уже не существует.");
                return null;
            }
        }else{
            dosyslog(__FUNCTION__.get_callee().": DEBUG: Промах: ".$key.".");
            return null;
        };
    }else{
    
        if ($value === FILE_CACHE_DELETE_FLAG){ // delete from cache
        
            if (isset($file_cache[$key])){
                unlink(FILE_CACHE_DIR . $key);
                dosyslog(__FUNCTION__.get_callee().": DEBUG: Принудительная инвалидация: ".$key);
            }else{
                dosyslog(__FUNCTION__.get_callee().": WARNING: Ошибка ключ '".$key."' отсутствует в кеше.");
            }
            return;
        }
        
        // set into cache
        if (file_put_contents(FILE_CACHE_DIR . $key, $value)){
            $file_cache[$key] = 0; // value is a counter for key have been accessed/requested.
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


