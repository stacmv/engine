<?php

define("SHOW_DATE_WITH_SECONDS", 1);
define("SHOW_DATE_TIME_DIFF", 2);
define("SHOW_DATE_TIME_DIFF_ONLY", 4);
define("SHOW_DATE_AGO", 8);


function add_data($db_table, $data){
    
    if ( ! isset($data["to"]) ){
        dosyslog(__FUNCTION__.": Data array does not have  item 'to'.");
        die("Code: ef-".__LINE__);
    };
    
    $data = parse_post_data($data,"add");
    $data = $data["to"];
    
    $table = db_get_table_schema($db_table);
    
    $isDataValid = true;
    
    foreach($table as $field){
        $type = (string) $field["type"];
        $name = (string) $field["name"];
        
        if($type=="file"){
            
            if ( ! $data[$name] ) continue;
            
            $storage_name = $db_table;
            if ( filter_var($data[$name], FILTER_VALIDATE_URL) ){   // передан URL
                list($res, $dest_file) = upload_file($data[$name], $storage_name, $isUrl = true);
            }elseif( file_exists($data[$name]) && (strpos($data[$name], FILES_DIR) === 0) ){ // передано имя ранее загруженного файла
                list($res, $dest_file) = upload_file($data[$name], $storage_name, $isUrl = true);
            }else{// загружен новый файл
                list($res, $dest_file) = upload_file($name, $storage_name);
            };
            
            if ($res){
                $msg = "upload_file_success";
                $data[$name] = $dest_file;
            }else{
                $msg = "upload_file_".$dest_file;
                $isDataValid = false;
            };
            
            set_session_msg($msg);
            
        }else{
                    
            if ( ! isset($data[$name]) ) $data[$name] = null;
            $validate_result = validate_data($field, $data[$name], "add", $db_table);
       
            $res = $validate_result[0];
            $msg = $validate_result[1];
            $proposed_value = isset($validate_result[2]) ? $validate_result[2] : null;
        
            if ($res){
                if ( ! empty($msg)) {
                   set_session_msg($msg, "info");
                };
            
                if ( ! empty($proposed_value) ){
                    $data[$name] = $proposed_value;
                };
            }else{
                $isDataValid = false;
                dosyslog(__FUNCTION__ . ": WARNING: Поле '" . $name . "' = '".@$data[$name]."' не валидно.");
                if (!empty($msg)) {
                    set_session_msg($msg, "error");
                };
            };

        };
        
    };//foreach
       

    $added_id = false;
    if ($isDataValid){
            
            $added_id = db_add($db_table, $data);
            if ( ! $added_id ){
                dosyslog(__FUNCTION__ . ": WARNING: ".get_callee().": Ошибка db_add().");
            };
            
    }else{
        dosyslog(__FUNCTION__ . ": WARNING: ".get_callee().": Данные не валидны.");
    };   
    
    if ( $added_id ) return array(true, $added_id);
    else return array(false, "fail");

}
function dosyslog_data_changes($data_before){
    global $_DATA;
    
    if (!empty($_DATA)) {
        $added   = array_diff(array_keys($_DATA), array_keys($data_before));
        dosyslog("_DATA: DEBUG:" . get_callee() . " Data added: " . implode(", ", $added) . ".");
    }else{
        dosyslog("_DATA: DEBUG:" . get_callee() . " No data added.");
    }
    
};
function edit_data($db_table, $data, $id="", array $err_msg=array()){
	global $CFG;
    
    if (! $id) $id = ! empty($data["id"]) ? $data["id"] : null;
    
    if (!$id){
        dosyslog(__FUNCTION__.": FATAL ERROR: Mandatory parameter 'id' is not set. Check pages XML and edit form template.");
        die("Code: ef-" . __LINE__);
    };
    
    $table = db_get_table_schema($db_table);
     
    $isDataValid = true;
    $changes=array();
    $data = parse_post_data($data, "edit");
    
    foreach($data["to"] as $key=>$value){
        $type = "";
        foreach($table as $field){ // есть ли такое поле в таблице БД?
            if ($field["name"] == $key) {
                $type = $field["type"];
                $name = $field["name"];
                break;
            }
        };
        
        if ( ! $type){
            dosyslog(__FUNCTION__ . ": ERROR: Parameter '".$key."' does not found in '".$db_table."'.");
            $isDataValid = false;
           
            set_session_msg("Ошибка в поле '".htmlspecialchars($key)."'. Поле не существует.","error");
            break;
        }
        
        if($type == "file"){
            if (!empty($data["to"][$name])){
                $storage_name = $db_table;
                list($res, $dest_file) = upload_file($name, $storage_name);
                if ($res){
                    $msg = "upload_file_success";
                    $changes[$name]["from"] = $data["from"][$name];
                    $changes[$name]["to"] = $dest_file;
                }else{
                    $msg = "upload_file_".$dest_file;
                    $isDataValid = false;
                };
            };
        }else{;
        
            $validate_result = validate_data($field, $data["to"][$name], "edit", $db_table, isset($data["from"][$name]) ? $data["from"][$name] : "");
           
            $res = $validate_result[0];
            $msg = $validate_result[1];
            $proposed_value = isset($validate_result[2]) ? $validate_result[2] : null;
           
           
            if ($res){
                if (!empty($msg)) {
                   set_session_msg($msg, "info");
                };
                
                
                if (!empty($proposed_value)){
                    $changes[$name]["to"] = $proposed_value;
                    $changes[$name]["from"] = $data["from"][$name];
                   
                }else{
                    
                    if (array_key_exists($name, $data["to"])){
                        $changes[$name]["from"] = db_prepare_value($data["from"][$name], $type);
                        $changes[$name]["to"] = db_prepare_value($data["to"][$name], $type);
                    };
                    dosyslog(__FUNCTION__.": DEBUG: changes[".$name."] = ".json_encode($changes[$name]).".");
                };
            }else{
                $isDataValid = false;
                if (empty($msg)) $msg = "Ошибка в поле '". $field["name"]."'.";
                dosyslog(__FUNCTION__.": WARNING: ".$msg);
                set_session_msg($msg, "error");

            };
        };
        
        
        
    };//foreach
    unset($key, $value, $res);


    if ($isDataValid){
               
        list($res, $reason) = db_edit($db_table, $id, $changes);
        if (! $res) set_session_msg($db_table."_edit_".$reason, $reason);
                      
    }else{
        $res = false;
        $reason = "fail";
    };

    return array($res, $reason);
    
};
function parse_post_data($data, $action){

    // Обработка загружаемых файлов
    $files = array();
    if ( ! empty($_FILES["to"]["name"]) ){
        foreach($_FILES["to"]["name"] as $file_param_name=>$file_name){
            if ( $file_name ){
                $data["to"][$file_param_name] = $file_name;
                $files[] = $file_param_name."=".$file_name;
            };
        };
    };
    if ( $files ) dosyslog(__FUNCTION__.": DEBUG: ". get_callee().": Обнаружены загруженные файлы: '".implode(", ",$files)."'.");

    switch($action){
    case "add":
        if (isset($data["from"]["id"])) unset($data["from"]["id"]);
        if (isset($data["to"]["id"])) unset($data["to"]["id"]);

        if (isset($data["from"]["created"])) unset($data["from"]["created"]);
        if (isset($data["to"]["created"])) unset($data["to"]["created"]);
        if (isset($data["from"]["modified"])) unset($data["from"]["modified"]);
        if (isset($data["to"]["modified"])) unset($data["to"]["modified"]);
        break;
        
    case "edit":
        // Проверить, все ли поля имеют пару from и to (старое и новое значения)
        // TODO: Проверить, как это рабоатет, аозможно усилить защиту - удалять непарные элементы и т.п.
        $diff1 = array_diff(array_keys($data["to"]), array_keys($data["from"]));
        if ( ! empty($diff1) ) dosyslog(__FUNCTION__.": ERROR: Theese fields of 'to' are absent in 'from' data:" . implode(", ",$diff1).".");
        $diff2 = array_diff(array_keys($data["from"]), array_keys($data["to"]));
        if ( ! empty($diff2) ) dosyslog(__FUNCTION__.": ERROR: Theese fields of 'from' are absent in 'to' data:" . implode(", ",$diff2).".");
        
        // Убрать поля, значения которых не будут меняться (одинаковые)
        $deleted = array();
        foreach($data["to"] as $k=>$v){
            if ( ! isset($data["from"][$k])) continue;
            
            if ( $data["to"][$k] == $data["from"][$k] ){
                unset($data["to"][$k], $data["from"][$k]);
                $deleted[] =$k;
            };
        };
        if ( $deleted ) dosyslog(__FUNCTION__.": DEBUG: ". get_callee().": Удалены поля '".implode(", ",$deleted)."'.");
        
        if (isset($data["from"]["created"])) unset($data["from"]["created"]);
        if (isset($data["to"]["created"])) unset($data["to"]["created"]);
        if (isset($data["from"]["modified"])) unset($data["from"]["modified"]);
        if (isset($data["to"]["modified"])) unset($data["to"]["modified"]);
        
        dosyslog(__FUNCTION__.": DEBUG: ". get_callee().": Оставлены поля [to] '".implode(", ",array_keys($data["to"]))."'.");
    }
    
    
    // Для многострочных текстовых строк - заменить конец строки на \n;
    if ($action == "edit"){
        foreach($data["from"] as $k=>$v) if ( $v ) $data["from"][$k] = preg_replace('~\R~u', "\n", $v);
    };
    foreach($data["to"]   as $k=>$v) if ( $v ) $data["to"][$k]   = preg_replace('~\R~u', "\n", $v);
    
    
    return $data;
}
function redirect($redirect_uri = "", array $params = array(), $hash_uri = ""){
    global $_RESPONSE;
    global $CFG;
    global $ISREDIRECT;
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    
    $base_uri = $CFG["URL"]["base"];
    
    if ( $redirect_uri ) $redirect_uri .= $CFG["URL"]["ext"];

    if ( ! empty($params_uri) ) $redirect_uri .= "?" . http_build_query($params);
    if ( ! empty($hash_uri) )   $redirect_uri .= "#" . $hash_uri;

    
    $uri = $base_uri . $redirect_uri;
    
    $_RESPONSE["headers"] = array("Location"=>$uri);
    $_RESPONSE["body"] = "<a href='".$uri."'>Click here</a>";
    
    dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . ": Prepare for  redirect to '".$uri."'.");
    
    $ISREDIRECT = true;
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
};
function show_date($timestamp, $options=0){

    if ( ! is_numeric($timestamp) ){
        $timestamp = strtotime( glog_isodate($timestamp, true) );
    };


    if ( $options & SHOW_DATE_TIME_DIFF_ONLY ){
        $date_str = time_diff( $timestamp, time() );
        if ( $options & SHOW_DATE_AGO ){            
            if ( $date_str != "только что" ){
                $date_str .= " назад";
            };
        };
        return $date_str;
    }else{
        $date_str = glog_rusdate(date("Y-m-d H:i",$timestamp), true);
   
        if ( ! ($options & SHOW_DATE_WITH_SECONDS) ){ // дата-время без секунд
            $date_str = substr($date_str, 0, 16);
        };
        
        if ( $options & SHOW_DATE_TIME_DIFF ){
            $time_diff = time_diff( $timestamp, time() );
            if ( $options & SHOW_DATE_AGO ){            
                if ( $time_diff != "только что" ){
                    $time_diff .= " назад";
                };
            };
            $date_str .= " <nobr class='time_diff'>(". $time_diff . ")</nobr>";
        };
        return $date_str;
    };
}
function time_diff($from, $to){
    if ( ! is_numeric($from) ) $from = strtotime($from);
    if ( ! is_numeric($to) )   $to   = strtotime($to);
    
    $diff = round(abs($to - $from) / 60 / 60); // hours
    
    if ( $diff > 366 * 24) {
        $y = floor($diff / 24 / 366);
        $diff_msg = glog_get_num_with_unit($y, "год", "года", "лет");
    }elseif( ( $diff > 31 * 24) && ( $diff % 30 < 10 ) ){
        $m = floor($diff / 24 / 31);
        $diff_msg = $m . " мес.";
    }elseif ( $diff > 23 ){
        $d = floor($diff/24);
        $h = $diff = $d * 24;
        $diff_msg = $d . " дн.";
    }elseif ( $diff > 1 ){
        $diff_msg = $diff . " час.";
    }else{
        
        $diff = abs($to - $from) ; // seconds
    
        if ( $diff < 30 ){
            $diff_msg = "только что";
        }elseif( $diff <= 60 ){
            $diff_msg = "менее минуты";
        }else{
        
            $m = round($diff / 60);
            $diff_msg = $m . " мин.";
        }
        
        ;
    }
    
    return $diff_msg;
}
