<?php
function upload_get_dir($storage_name, $folder = "", $user_id = ""){
       
    $dir = FILES_DIR . glog_codify($storage_name, GLOG_CODIFY_FILENAME) ."/";
        
    if ( ! empty($user_id) ){
        $dir .= glog_codify($user_id) . "/";
    };
    
    if ( $folder ){
        $dir .= glog_codify($folder, GLOG_CODIFY_FILENAME) . "/";
    };
    
   
    if ( ! is_dir($dir) )  mkdir($dir, 0777, true);
    
    if ( ! is_dir($dir) ){
        dosyslog(__FUNCTION__.": FATAL ERROR: Can not create dir '".$dir."' for storage '".$storage."'.");
        die("Code: eu-".__LINE__);
    };
    
    return $dir;
}
function upload_file($param_name, $storage_name){
    global $_PARAMS;
    
    if ( empty($param_name) ){
        dosyslog(__FUNCTION__.": FATAL ERROR: Mandatory parameter param_name is not set.");
        die("Code: eu-" . __LINE__);
    };
    $upload_dir = upload_get_dir($storage_name);
    
    if ( filter_var($param_name, FILTER_VALIDATE_URL) ){   // передан URL
         return upload_remote_file($param_name, $upload_dir);
    }elseif( file_exists($param_name) && (strpos($param_name, FILES_DIR) === 0) ){ // передано имя ранее загруженного файла
         return upload_local_file($param_name, $upload_dir);
    }else{// загружен новый файл ; $param_name - имя параметра в $_FILES
        
        $uploaded_files = array();
        
        // New uploaded files.
        if ( ! empty($_FILES["to"]["name"][$param_name]) ){
            
            if (is_array($_FILES["to"]["name"][$param_name])){
                
                foreach($_FILES["to"]["tmp_name"][$param_name] as $k=>$v){
                    if (! $v) continue;
                    if ($_FILES["to"]["error"][$param_name][$k]){
                        dosyslog(__FUNCTION__.get_callee().": DEBUG: File error for '".$v."': ".$_FILES["to"]["error"][$param_name][$k]);
                        var_dump($_FILES["to"]);
                    };
                    list($res, $dest_name) = upload_move_uploaded_file($v,$_FILES["to"]["name"][$param_name][$k],  $upload_dir);
                    if ($res){
                        dosyslog(__FUNCTION__.": NOTICE: File for '".$param_name."' moved to storage path '".$dest_name."'.");
                        $uploaded_files[] = $dest_name;
                    }else{
                        dosyslog(__FUNCTION__.": ERROR: Can not move uploaded file for '".$param_name."' to storage path '".$dest_name);    
                    };
                };
                
            }else{
                if ($_FILES["to"]["error"][$param_name]){
                    dosyslog(__FUNCTION__.get_callee().": DEBUG: File error for '".$param_name."': ".$_FILES["to"]["error"][$param_name]);
                };
                list($res, $dest_name) = upload_move_uploaded_file($_FILES["to"]["tmp_name"][$param_name], $_FILES["to"]["name"][$param_name], $upload_dir);
                if ($dest_name){
                    dosyslog(__FUNCTION__.": NOTICE: File for '".$param_name."' moved to storage path '".$dest_name."'.");
                    $uploaded_files[] = $dest_name;
                }else{
                    dosyslog(__FUNCTION__.": ERROR: Can not move uploaded file for '".$param_name."' to storage path '".$dest_name."'.");
                };
            }
            
        } elseif ( ! empty($_PARAMS["to"][$param_name]) ){ // Previously uploaded files or remote urls
            
            if(is_array($_PARAMS["to"][$param_name])){
                foreach($_PARAMS["to"][$param_name] as $k=>$v){
                    $res = false;
                    if ( filter_var($v, FILTER_VALIDATE_URL) ){   // передан URL
                        list($res, $dest_name) = upload_remote_file($v, $upload_dir);
                    }elseif( file_exists($v) && (strpos($v, FILES_DIR) === 0) ){ // передано имя ранее загруженного файла
                        list($res, $dest_name) = upload_local_file($v, $upload_dir);
                    };
                    if ($res) $uploaded_files[] = $dest_name;
                };
            }else{
                $res = false;
                if ( filter_var($_PARAMS["to"][$param_name], FILTER_VALIDATE_URL) ){   // передан URL
                    list($res, $dest_name) =  upload_remote_file($_PARAMS["to"][$param_name], $upload_dir);
                }elseif( file_exists($_PARAMS["to"][$param_name]) && (strpos($_PARAMS["to"][$param_name], FILES_DIR) === 0) ){ // передано имя ранее загруженного файла
                    list($res, $dest_name) = upload_local_file($_PARAMS["to"][$param_name], $upload_dir);
                };
                if ($res) $uploaded_files[] = $dest_name;
            }
        };
        
        
        
        // //        
        if ($uploaded_files){
            if (count($uploaded_files) > 1){
                return array(true, $uploaded_files);
            }else{
                return array(true, $uploaded_files[0]);
            }
        }else{
            return array(false, "fail");
        };
   
    };
};
function upload_files(FormData $data, $storage=""){
    
    if ( ! $storage ) $storage = $data->db_table;
       
    if ( ! $data->form_name ){
        dosyslog(__FUNCTION__.get_callee() . ": FATAL ERROR: Mandatory parameter data[form_name] is not set.");
        die("Code: upl-".__LINE__);
    };
    
    
    $files_uploaded = array();
    
    $fields = form_get_fields($data->db_table, $data->form_name);
    
    $file_fields = array_filter($fields, function($field){
        return ( ($field["type"] == "file") || (isset($_FILES["to"]["name"][$field["name"]])) ) ;
    });
    
    if ( ! empty($file_fields) ){
        dosyslog(__FUNCTION__.get_callee().": DEBUG: Определены имена полей с файлами для формы '".$data->form_name.": ".implode(", ", array_map(function($field){return $field["name"];}, $file_fields))." .");
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

function upload_move_uploaded_file($tmp_name, $name, $dest_dir){
    $orig_filename  = pathinfo($name,PATHINFO_FILENAME);
    $orig_extension = pathinfo($name,PATHINFO_EXTENSION);
      
    
    if ( ! $orig_extension ) $orig_extension = "txt";
    
    $dest_name = $dest_dir . get_filename($orig_filename."__".date("YmdHis"), ".".$orig_extension);
    
    if (is_uploaded_file($tmp_name)){
        if ( move_uploaded_file($tmp_name,$dest_name) ){
            dosyslog(__FUNCTION__.": NOTICE: File '".$tmp_name."' moved to storage path '".$dest_name."'.");
            return array(true, $dest_name);
        }else{
            dosyslog(__FUNCTION__.": ERROR: Can not move uploaded file '".$tmp_name."' to storage path '".$dest_name);
            dump($_FILES,"FILES");
            die(__FUNCTION__);
            return array(false, "fail");
        };
    }else{
        dosyslog(__FUNCTION__.": FATAL ERROR: Can not move file which was not uploaded '".$tmp_name."' to storage path '".$dest_name);
        die("Code: upl-".__LINE__);
    }
    
}
function upload_local_file($filename, $upload_dir){
    
    $orig_filename  = pathinfo($filename,PATHINFO_FILENAME);
    $orig_extension = pathinfo($filename,PATHINFO_EXTENSION);
    
    if ( (dirname($filename) == $upload_dir) && file_exists($filename) ){ // нужный файл уже есть в каталоге назначения (был загружен ранее)
        return array(true, $filename);
    }else{
        if (strpos($filename, FILES_DIR) === 0) { // файл был загружен, но в другой storage, возможно принадлежащий другому пользователю.
            dosyslog(__FUNCTION__ . get_callee() . ": WARNING: Possible attempt to steal file from '".$filename."' to '".$upload_dir."'. Using original file.");
            return array(true, $filename);
        }
    }
        
    if ( ! $orig_extension ) $orig_extension = "jpg";
        
    $dest_name = $upload_dir . get_filename($orig_filename."__".date("YmdHis"), ".".$orig_extension);
      
    
    if (file_put_contents( $dest_name, file_get_contents($param_name)) ){
        dosyslog(__FUNCTION__.": NOTICE: Downloaded file from '".$param_name."' and moved to storage path '".$dest_name."'.");
            return array(true, $dest_name);
    }else{
        dosyslog(__FUNCTION__.": ERROR: Can not download file from '".$param_name."' and save to storage path '".$dest_name);
        return array(false, "fail");
    };
    
}
function upload_remote_file($filename, $upload_dir){
    
    // TODO: добавить проверку доступности удаленного файла, его типа и размера.
    
    return upload_local_file($filename, $upload_dir);
}
