<?php
function engine_modules(){
    return array_map("basename", engine_modules_dirs());
}
function engine_modules_dir(){
    return APP_DIR . "modules/";
}
function engine_modules_dirs(){
    $modules_dir = engine_modules_dir(). "*";
    $tmp = glob( $modules_dir, GLOB_ONLYDIR | GLOB_MARK);
    return $tmp;
}
function engine_modules_functions(){

    $modules_functions = array();
    $modules = engine_modules();

    foreach($modules as $module){
        $module_dir = engine_modules_dir() . $module . "/";
        $php_files = glob($module_dir  . "functions/*.php");
        foreach($php_files as $file_name){
            $modules_functions[ $module . "_module_" . basename($file_name,".php") ] = $file_name;
        };
    }

    return $modules_functions;

}


spl_autoload_register(function ($class_name){
    $class_file =  cfg_get_filename("classes", $class_name . ".class.php");
    if (file_exists($class_file)){ require_once $class_file; }
});
