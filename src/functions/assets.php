<?php
function assets($command, $type, $resource=""){
    static $assets = array();
    
    switch ($command){
        case "add":
            switch($type){
                case "css-file":
                case "js-file":
                    if (!empty($resource) && is_string($resource)){
                        $hash = md5($resource);
                        $assets[$type][$hash] = (string) $resource;
                        return true;
                    }else{
                        dosyslog(__FUNCTION__.get_callee().": ERROR: empty resource of  asset type '".(string) $type."'.");
                    }
                    break;
                default:
                    dosyslog(__FUNCTION__.get_callee().": ERROR: unsupported asset type '".(string) $type."'.");
            }
            break;
        
        case "get":
            if (isset($assets[$type])){
                return $assets[$type];
            } else {
                dosyslog(__FUNCTION__.get_callee().": WARNING: no assets of  type '".(string) $type."' are found.");
                return array();
            }
            break;
        default:
            dosyslog(__FUNCTION__.get_callee().": ERROR: unsupported command '".(string) $command."'.");
    }
    
    return false;
}