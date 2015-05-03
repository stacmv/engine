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
    
    if ( filter_var($param_name, FILTER_VALIDATE_URL) ){   // передан URL
         $isUrl = true;
    }elseif( file_exists($param_name) && (strpos($param_name, FILES_DIR) === 0) ){ // передано имя ранее загруженного файла
         $isUrl = true;
    }else{// загружен новый файл ; $param_name - имя параметра в $_FILES
        $isUrl = false;
    };
    

    if ( ! $isUrl ){
        if ( ! empty($_FILES["to"]["name"][$param_name]) ){
            $orig_filename  = pathinfo($_FILES["to"]["name"][$param_name],PATHINFO_FILENAME);
            $orig_extension = pathinfo($_FILES["to"]["name"][$param_name],PATHINFO_EXTENSION);
            
            if ( ! $orig_extension ) $orig_extension = "txt";
            
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
    }else{  // загрузка файла с локального или  удаленного сервера
        
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
function upload_files(FormData $data, $storage=""){
    
    if ( ! $storage ) $storage = $data->db_table;
       
    if ( ! $data->form_name ){
        dosyslog(__FUNCTION__.get_callee() . ": FATAL ERROR: Mandatory paramerter data[form_name] is not set.");
        die("Code: upl-".__LINE__);
    };
    
    
    
    $fields = form_get_fields($data->db_table, $data->form_name);
    
    $file_fields = array_filter($fields, function($field){
        return $field["type"] == "file";
    });
    
    $files_uploaded = array();
    
    if ( ! empty($file_fields) ){
        foreach($file_fields as $field){
            list($res, $dest_file) = upload_file($field["name"], $storage);
            dosyslog(__FUNCTION__.get_callee().": DEBUG: Пытались загрузить файл для поля '".$field["name"]."' формы '".$data->form_name."'. ..." . ($res ? "успешно" : "безуспешно") . ".");
            if ( $res ){
                $files_uploaded[ $field["name"] ] = $dest_file;
            };
        }
    }else{
        dosyslog(__FUNCTION__.get_callee().": DEBUG: Форма '".$data->form_name."' не имеет поле типа 'file'.");
    };
    
    return $files_uploaded;
    
}