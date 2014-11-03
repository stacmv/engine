<?php
function upload_get_dir($storage_name, $folder = "", $user_id = ""){
    global $_USER;
    
    
    $dir = FILES_DIR . glog_codify($storage_name) ."/";
    
    if ( empty($user_id) ){
        if ( ! empty($_USER["profile"]["id"]) ){
            $user_id = $_USER["profile"]["id"];
        };
    };
    
    if ( ! empty($user_id) ){
        $dir .= glog_codify($_USER["profile"]["id"]) . "/";
    };
    
    if ( $folder ){
        $dir .= glog_codify($folder) . "/";
    };
    
   
    if ( ! is_dir($dir) )  mkdir($dir, 0777, true);
    
    if ( ! is_dir($dir) ){
        dosyslog(__FUNCTION__.": FATAL ERROR: Can not create dir '".$dir."' for storage '".$storage."'.");
        die("Code: eu-".__LINE__);
    };
    
    return $dir;
}
function upload_file($param_name, $storage_name, $isUrl = false){

    if ( empty($param_name) ){
        dosyslog(__FUNCTION__.": FATAL ERROR: Mandatody parameter param_name is not set.");
        die("Code: eu-" . __LINE__);
    };
    $upload_dir = upload_get_dir($storage_name);

    if ( ! $isUrl ){
        if ( ! empty($_FILES["to"]["name"][$param_name]) ){
            $orig_filename  = pathinfo($_FILES["to"]["name"][$param_name],PATHINFO_FILENAME);
            $orig_extension = pathinfo($_FILES["to"]["name"][$param_name],PATHINFO_EXTENSION);
            
            if ( ! $orig_extension ) $orig_extension = "jpg";
            
            $dest_name = $upload_dir . get_filename($orig_filename."__".date("YmdHis"), ".".$orig_extension);
            
            if ( move_uploaded_file($_FILES["to"]["tmp_name"][$param_name],$dest_name) ){
                dosyslog(__FUNCTION__.": NOTICE: File for '".$param_name."' moved to storage path '".$dest_name."'.");
                
                return array(true, $dest_name);
            }else{
                dosyslog(__FUNCTION__.": ERROR: Can not move uploaded file for '".$param_name."' to storage path '".$dest_name);
                return array(false, "fail");
            };
        }else{
            return array(false, "no_file");
        };
    }else{  // загрузка файла с удаленного сервера
        
        $orig_filename  = pathinfo($param_name,PATHINFO_FILENAME);
        $orig_extension = pathinfo($param_name,PATHINFO_EXTENSION);
        
        if ( ! $orig_extension ) $orig_extension = "jpg";
        
        $dest_name = $upload_dir . get_filename($orig_filename."__".date("YmdHis"), ".".$orig_extension);
        
        // TODO: добавить проверку доступности удаленного файла, его типа и размера.
        if (file_put_contents( $dest_name, file_get_contents($param_name)) ){
            dosyslog(__FUNCTION__.": NOTICE: Downloaded file from '".$param_name."' and moved to storage path '".$dest_name."'.");
            
            
            return array(true, $dest_name);
        }else{
            dosyslog(__FUNCTION__.": ERROR: Can not download file from '".$param_name."' and save to storage path '".$dest_name);
            return array(false, "fail");
        };
    };
};