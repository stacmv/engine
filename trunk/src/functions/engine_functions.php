<?php
function add_data($db_table, $data){
    
    $data = parse_post_data($data,"add");
    $data = $data["to"];
    
    $table = db_get_table_schema($db_table);
    
    $isDataValid = true;
    
    foreach($table as $field){
        $type = (string) $field["type"];
        $name = (string) $field["name"];
        
        if($type=="file"){
            if ( ! is_dir(FILES_DIR) ) mkdir(FILES_DIR, 0777, true);
            if (!empty($data[$name]) && !move_uploaded_file($_FILES[$name]["tmp_name"],$data[$name])){
                dosyslog(__FUNCTION__.": FATAL ERROR: Can not move uploaded file to storage path '".$data["name"]);
                die("Code: ef-" . __LINE__);
            };           
        };
        
        if ( ! isset($data[$name]) ) $data[$name] = null;
        $res = validate_data($field, $data[$name], "add", $db_table);
        
        if ($res[0]){
            if (!empty($res[1])) {
                $S["msg"][] = array(
                    "class"=> "alert alert-info",
                    "text"=>$res[1]
                );
            };
            if (!empty($res[2])){
                $data[$name] = $res[2];
            };
        }else{
            $isDataValid = false;
            dosyslog(__FUNCTION__ . ": WARNING: Поле '" . $name . "' = '".@$data[$name]."' не валидно.");
            if (!empty($res[1])) {
                set_session_msg($res[1], "error");
            };
        };
    };//foreach
       

    if ($isDataValid){
            
            $comment = get_db_comment($db_table,"add",$data);
            
            $added_id = db_add($db_table, $data,$comment);
            if ( ! $added_id ){
                dosyslog(__FUNCTION__ . ": WARNING: Ошибка db_add().");
            };
            
    }else{
        dosyslog(__FUNCTION__ . ": WARNING: Данные не валидны.");
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
            if ( (string) $field["name"] == $key) {
                $type = (string) $field["type"];
                $name = (string) $field["name"];
                break;
            }
        };
        
        if ( ! $type){
            dosyslog(__FUNCTION__ . ": ERROR: Parameter '".$key."' does not found in '".$db_table."'.");
            $isDataValid = false;
           
            $S["msg"][] = array(
                "class"=> "alert alert-error",
                "text"=> "Ошибка в поле '".htmlspecialchars($key)."'. Поле не существует."
            );
            break;
        }
        
        if($type=="file"){
            if (!empty($data[$name])){
                if ( ! is_dir(FILES_DIR) ) mkdir(FILES_DIR, 0777, true);
                if (move_uploaded_file($_FILES[$name]["tmp_name"],$data[$name])){
                    dosyslog(__FUNCTION__.": NOTICE: File '".$name."' moved to storage path.");
                    $data["to"][$name] = $data[$name];
                }else{
                    dosyslog(__FUNCTION__.": ERROR: Can not move uploaded file to storage path '".$data["name"]);
                    die("Code: ea-" . __LINE__);
                };
            };           
        };
        // dump($data["to"][$name], $name);
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
                
                if (isset($data["to"][$name])){
                    $changes[$name]["from"] = $data["from"][$name];
                    $changes[$name]["to"] = $data["to"][$name];
                    switch($type){
                        case "list":
                            if (is_array($changes[$name]["from"])){
                                $changes[$name]["from"] = implode(DB_LIST_DELIMITER,(array) $data["from"][$name]);
                            };
                            if (is_array($changes[$name]["to"])){
                                $changes[$name]["to"] = implode(DB_LIST_DELIMITER,(array) $data["to"][$name]);
                            };
                            break;
                        case "json":
                            if (is_array($changes[$name]["from"])){
                                $changes[$name]["from"] = json_encode($data["rom"][$name]);
                            };
                            if (is_array($changes[$name]["to"])){
                                $changes[$name]["to"] = json_encode($data["to"][$name]);
                            };
                            break;
                        default:
                            $changes[$name]["to"] = $data["to"][$name];
                    };
                };
            };
        }else{
            $isDataValid = false;
            if (empty($msg)) $msg = "Ошибка в поле '". $field["name"]."'.";
            set_session_msg($msg, "error");

        };
    };//foreach
    unset($key, $value, $res);
    
    foreach ($changes as $what=>$v){
        $changes[$what]["from"] = htmlspecialchars($changes[$what]["from"]);
        $changes[$what]["to"] = htmlspecialchars($changes[$what]["to"]);
        if ($changes[$what]["from"] == $changes[$what]["to"]){
            unset($changes[$what]);
        };
    };
    

    if ($isDataValid){

        $comment = get_db_comment($db_table,"edit",$changes);
        
        list($res, $reason) = db_edit($db_table, $id, $changes, $comment);
        
       
        if (! $res ) {
            $msg = array(
                "class"=> ($reason=="success"?"success":($reason=="no_changes"?"info":"error")),
                "text"=> isset($err_msg[$reason]) ? $err_msg[$reason] : "Ошибка БД. Код ошибки: ".@$reason
            
            );
           
            set_session_msg($msg["text"], $msg["class"]);
            unset($msg);
        };
            
            
    }else{
        $res = false;
        $reason = "fail";
    };

    return array($res, $reason);
    
};
function parse_post_data($data, $action){

    switch($action){
    case "add":
        if (isset($data["from"]["id"])) unset($data["from"]["id"]);
        if (isset($data["to"]["id"])) unset($data["to"]["id"]);
        // no break here between add and edit cases
    case "edit":
        if (isset($data["from"]["created"])) unset($data["from"]["created"]);
        if (isset($data["to"]["created"])) unset($data["to"]["created"]);
        if (isset($data["from"]["modified"])) unset($data["from"]["modified"]);
        if (isset($data["to"]["modified"])) unset($data["to"]["modified"]);
    }
    
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

