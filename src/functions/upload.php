<?php
function upload_get_dir($storage_name){
    global $_USER;
    
    
    $dir = FILES_DIR . glog_codify($storage_name) ."/";
    if ( ! empty($_USER["profile"]["id"]) ){
        $dir .= glog_codify($_USER["profile"]["id"]) . "/";
    };
    
   
    if ( ! is_dir($dir) )  mkdir($dir, 0777, true);
    
    if ( ! is_dir($dir) ){
        dosyslog(__FUNCTION__.": FATAL ERROR: Can not create dir '".$dir."' for storage '".$storage."'.");
        die("Code: eu-".__LINE__);
    };
    
    return $dir;
}
function upload_file($param_name, $storage_name){

    if ( empty($param_name) ){
        dosyslog(__FUNCTION__.": FATAL ERROR: Mandatody parameter param_name is not set.");
        die("Code: eu-" . __LINE__);
    };
    $upload_dir = upload_get_dir($storage_name);

    // dump($_FILES,"FILES");
    $orig_filename  = pathinfo($_FILES["to"]["name"][$param_name],PATHINFO_FILENAME);
    $orig_extension = pathinfo($_FILES["to"]["name"][$param_name],PATHINFO_EXTENSION);
    
    $dest_name = $upload_dir . get_filename($orig_filename."__".date("YmdHis"), ".".$orig_extension);
    
    if ( move_uploaded_file($_FILES["to"]["tmp_name"][$param_name],$dest_name) ){
        dosyslog(__FUNCTION__.": NOTICE: File for '".$param_name."' moved to storage path '".$dest_name."'.");
        
        return array(true, $dest_name);
        
    }else{
        dosyslog(__FUNCTION__.": ERROR: Can not move uploaded file for '".$param_name."' to storage path '".$dest_name);
        return array(false, "fail");
    };
};