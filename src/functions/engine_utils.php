<?php
function engine_utils_get_class_filename($class_name){
    return str_replace("\\", DIRECTORY_SEPARATOR , $class_name) . ".class.php";
}
function engine_utils_get_class_instance($class_name, $class_template){
    
    try{
        $instance =  new $class_name;
    }catch(Exception $e){
        if ($class_name == $e->getMessage()){
            $class_source = engine_utils_generate_class($class_name, $class_template);
            if ($class_source  &&
                engine_utils_deploy_class($class_source, APP_DIR . "classes/".engine_utils_get_class_filename($class_name))
            ){
                $instance =  new $class_name;
            }else{
                die(__METHOD__."-".__LINE__.(DEV_MODE ? "-".$e->getMessage() : ""));
            }
        }else{
            die(__METHOD__."-".__LINE__.(DEV_MODE ? "-".$e->getMessage() : ""));
        }
    };
    
    return $instance;
}

function engine_utils_generate_class($class_name, $class_template){
    
    $template = glog_file_read( cfg_get_filename("classes", engine_utils_get_class_filename($class_template), ENGINE_SCOPE_ENGINE) );
    $class_source = str_replace("class ".$class_template, "class ".$class_name, $template);
    
    $msg = "INFO: Code for class '" . $class_name . "' was generated using class '".$class_template."' as a template.";
    dosyslog(__FUNCTION__ . ": " . $msg);
    if (DEV_MODE){
        set_session_msg($msg, "info");
    };
    
    return $class_source;
}

function engine_utils_deploy_class($class_source, $class_filename){
    
    if ( ! file_exists($class_filename) ){
        if (file_put_contents($class_filename, $class_source)){
            $msg = "INFO: Class file " . $class_filename . " was deployed.";
            dosyslog(__FUNCTION__ . ": " . $msg);
            if (DEV_MODE){
                set_session_msg($msg, "info");
            };
            
            return true;
            
        }else{
            dosyslog(__FUNCTION__.": FATAL ERROR: Could not write to file '" . $class_filename."'.");
            die(__FUNCTION__."-".__LINE__);
        }
    }else{
        dosyslog(__FUNCTION__.": FATAL ERROR: Attempt to overwrite file '" . $class_filename."'.");
        die(__FUNCTION__."-".__LINE__);
    }
    return false;
}
