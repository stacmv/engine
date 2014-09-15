<?php
function add_data_action($db_table=""){
    global $_PARAMS;
    global $_DATA;
    
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    $db_name = $_PARAMS["db_name"]."s";  //uri: add/account.html, but db = accounts; add/user => users.
    if (!$db_table) $db_table = $db_name;
    
    $result = "";
    
    // Проверка прав доступа 
        if ( ! user_has_access_by_ip() ){
            $S["action"] = "add"; // needed by set_session_msg();
            dosyslog(__FUNCTION__ . ": WARNING: Отказ в обслуживании.");
            $result = "deny";
        }
    //
    
    $table = db_get_table_from_xml($db_name, $db_table);
    
    $isDataValid = true;
    $data=array();
    foreach($table as $field){
        $type = (string) $field["type"];
        $name = (string) $field["name"];
        
        if($type=="file"){
            if ( ! is_dir(FILES_DIR) ) mkdir(FILES_DIR, 0777, true);
            if ($_PARAMS[$name] && !move_uploaded_file($_FILES[$name]["tmp_name"],$S[$name])){
                dosyslog(__FUNCTION__.": ERROR: Can not move uploaded file to storage path '".$S["name"]);
                die("Code: a-" . __LINE__);
            };           
        };
        
                        
        $res = validate_data($field, @$_PARAMS[$name], "add", $db_name, $db_table);
        if ($res[0]){
            if (!empty($res[1])) {
                $S["msg"][] = array(
                    "class"=> "alert alert-info",
                    "text"=>$res[1]
                );
            };
            if (!empty($res[2])){
                $data[$name] = $res[2];
            }else{
                
                if (!empty($_PARAMS[$name])){
                    switch($type){
                        case "list":
                            $data[$name] = implode(",",$_PARAMS[$name]);
                            break;
                        case "json":
                            $data[$name] = json_encode($_PARAMS[$name]);
                            break;
                        default:
                            $data[$name] = $_PARAMS[$name];
                    };
                };
            };
        }else{
            $isDataValid = false;
            dosyslog(__FUNCTION__ . ": WARNING: Поле '" . $name . "' = '".$_PARAMS[$name]."' не валидно.");
            if (!empty($res[1])) {
                if (!isset($_SESSION["msg"])) $_SESSION["msg"] = array();
                $_SESSION["msg"][] = array(
                    "class"=> "alert alert-error",
                    "text"=>$res[1]
                );
            };
        };
    };//foreach
    
     
    foreach($data as $what=>$v){
        $data[$what] = htmlspecialchars($data[$what]);
    };
    

    if ($isDataValid){
            
            $comment = get_db_comment($db_name . ($db_table != $db_name ? ":" . $db_table : ""),"add",$data);
            
            $added_id = db_add($db_name.".".$db_table, $data,$comment);
            if ( ! $added_id ){
                dosyslog(__FUNCTION__ . ": WARNING: Ошибка db_add().");
                $result = "fail";
            }else{
                $result = "success";
            };
            
    }else{
        dosyslog(__FUNCTION__ . ": WARNING: Данные не валидны.");
        $result = "fail";
    };   
    $S["action"] = "add"; // needed by set_session_msg();
    
    if ($result == "fail"){
        $_SESSION["to"] = array();
        foreach($data as $what=>$v){
             $_SESSION["to"][$what] = $data[$what]; // needed in store_userinput_in_session();
        };
    }else{
        unset($_SESSION["to"]);
    };   
    
    
    
    dosyslog(__FUNCTION__.": NOTICE: RESULT = ".$result);
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");

    $_DATA["db_name"] = $db_name;
    $_DATA["action"] = "add";
    $_DATA["result"] = $result;
    return ! empty($added_id) ? array(true, $added_id) : array(false, $result);
};

function do_nothing_action(){
    // do nothing
}
function edit_data_action(){
    global $_PARAMS;
    global $_DATA;
    global $CFG;
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    
    // Проверка прав доступа 
        if ( ! user_has_access_by_ip() ){
            dosyslog(__FUNCTION__ . ": WARNING: Отказ в обслуживании.");
            return array(false, "deny");
        }
    //
    
	$db_name = $_PARAMS["db_name"]."s";  //uri: add/account.html, but db = accounts; add/user => users.
	
    
    $err_msg_prefix = "";
    if (userHasRight("manager")){
		switch ($db_name){
			case "users": $err_msg_prefix = "Данные пользователя: ";break;
			case "accounts": $err_msg_prefix = "Данные партнера: ";break;
			case "applications": $err_msg_prefix = "Данные заявки: ";break;
			default: $err_msg_prefix = "";
		};
        $err_msg = array(
            "success" => $err_msg_prefix."Изменения сохранены",
            "wrong_id"=> $err_msg_prefix."Попытка изменения не существующего объекта.",
            "changes_conflict" => $err_msg_prefix."Конфликт: за время редактирования другой пользователь внес изменения.",
            "db_fail" => $err_msg_prefix."Ошибка БД.",
            "history_fail" => $err_msg_prefix."Ошибка журналирования.",
            "no_changes"=>$err_msg_prefix."Данные в БД не изменились."
        );
    }else{
		switch ($db_name){
			case "users": $err_msg_prefix = "Личные данные: ";break;
			case "accounts": $err_msg_prefix = "Платежные данные: ";break;
		};
        $err_msg = array(
            "success" => $err_msg_prefix."Изменения сохранены",
            "wrong_id"=> $err_msg_prefix."Произошла ошибка. Данные не могут быть сохранены. Обратитесь к менеджеру партнерской программы.",
            "changes_conflict" => $err_msg_prefix."Конфликт: за время редактирования другой пользователь внес изменения.",
            "db_fail" => $err_msg_prefix."Произошла ошибка. Обратитесь к менеджеру партнерской программы.",
            "history_fail" => $err_msg_prefix."Произошла ошибка.  Обратитесь к менеджеру партнерской программы.",
            "no_changes"=>$err_msg_prefix."Нет изменений."
        );
    };    
    
    $isDataValid = true;
    
    $id = ! empty($_PARAMS["id"]) ? $_PARAMS["id"] : null;
    
    if (!$id){
        dosyslog(__FUNCTION__.": FATAL ERROR: Mandatory parameter 'objectId' is not set. Check pages XML and edit form template.");
        die("Code: ea-" . __LINE__);
    };
    
    $table = db_get_table_from_xml($db_name);
     
    $isDataValid = true;
    $changes=array();
    $_PARAMS["to"] = (array) $_PARAMS["to"];
    $_PARAMS["from"] = (array) $_PARAMS["from"];    
    

    
    foreach($_PARAMS["to"] as $key=>$value){
        $type = "";
        foreach($table as $field){ // есть ли такое поле в таблице БД?
            if ( (string) (string) $field["name"] == $key) {
                $type = (string) $field["type"];
                $name = (string) (string) $field["name"];
                break;
            }
        };
        
        if ( ! $type){
            dosyslog(__FUNCTION__ . ": ERROR: Parameter '".$key."' does not found in DB.");
            $isDataValid = false;
           
            $S["msg"][] = array(
                "class"=> "alert alert-error",
                "text"=> "Ошибка в поле '".htmlspecialchars($key)."'. Поле не существует."
            );
            break;
        }
        
        if($type=="file"){
            if (!empty($_PARAMS[$name])){
                if ( ! is_dir(FILES_DIR) ) mkdir(FILES_DIR, 0777, true);
                if (move_uploaded_file($_FILES[$name]["tmp_name"],$_PARAMS[$name])){
                    dosyslog(__FUNCTION__.": NOTICE: File '".$name."' moved to storage path.");
                    $_PARAMS["to"][$name] = $_PARAMS[$name];
                }else{
                    dosyslog(__FUNCTION__.": ERROR: Can not move uploaded file to storage path '".$_PARAMS["name"]);
                    die("Code: ea-" . __LINE__);
                };
            };           
        };
        
        $res = validate_data($field, $_PARAMS["to"][$name], "edit", $db_name, $_PARAMS["from"][$name]);
        
        // dump($name,"name");
        // dump($type,"type");
        // dump(@$_PARAMS["to"][$name],"value");
        // dump($res,"validate");
        
        if ($res[0]){   
            if (!empty($res[1])) {
                if ( empty($_SESSION["msg"]) ) $_SESSION["msg"] = array();
                $_SESSION["msg"][] = array(
                    "class"=> "alert alert-info",
                    "text"=>$res[1]
                );
            };
            
            
            if (!empty($res[2])){
                $changes[$name]["to"] = $res[2];
                $changes[$name]["from"] = $_PARAMS["from"][$name];
               
            }else{
                
                if (isset($_PARAMS["to"][$name])){
                    $changes[$name]["from"] = $_PARAMS["from"][$name];
                    switch($type){
                        case "list":
                            $changes[$name]["to"] = implode(",",(array) $_PARAMS["to"][$name]);
                            break;
                        case "json":
                            $changes[$name]["to"] = json_encode($_PARAMS["to"][$name]);
                            break;
                        default:
                            $changes[$name]["to"] = $_PARAMS["to"][$name];
                    };
                };
            };
        }else{
            $isDataValid = false;
            if (empty($res[1])) $res[1] = "Ошибка в поле '".(string) $field["name"]."'.";
            
            if ( empty($_SESSION["msg"]) )$_SESSION["msg"] = array();
            $_SESSION["msg"][] = array(
                "class"=> "alert alert-error",
                "text"=>$res[1]
            );
        };
    };//foreach
    
    foreach ($changes as $what=>$v){
        $changes[$what]["from"] = htmlspecialchars($changes[$what]["from"]);
        $changes[$what]["to"] = htmlspecialchars($changes[$what]["to"]);
        if ($changes[$what]["from"] == $changes[$what]["to"]){
            unset($changes[$what]);
        };
    };
    

    if ($isDataValid){
            
            // dump($_PARAMS["from"],"from");
            // dump($_PARAMS["to"],"to");
            // dump($changes,"changes");
            

            $comment = get_db_comment($db_name,"edit",$changes);
            
            list($res, $reason) = db_edit($db_name, $id, $changes, $comment);
            
            if ($reason) {
                $tmp = array(
                    "class"=> "alert ".($reason=="success"?"alert-success":($reason=="no_changes"?"alert-info":"alert-error")),
                    "text"=> isset($err_msg[$reason]) ? $err_msg[$reason] : "Ошибка БД. Код ошибки: ".@$reason
                
                );
                switch(@$reason){
                    case "changes_conflict":
                    case "no_changes":
                        $tmp["actions"][] = array("action"=>"repeat_edit", "href" => "form/edit/".$S["db_name"]."/".$id.$CFG["URL"]["ext"], "caption" => "Повторить редактирование");
                        break;
                };                
                if ( empty($_SESSION["msg"]) )$_SESSION["msg"] = array();
                $_SESSION["msg"][] = $tmp;
            };
            
            
    }else{
        $reason = "fail";
    };   
    
    dosyslog(__FUNCTION__.": NOTICE: result: ".$res."; reason: ".$reason);
    
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    
    $_DATA["result"] = $reason;
    
    return array($res, $reason);
};

function form_action(){

    set_topmenu_action();
    set_objects_action();
    set_template_for_form();

}
function import_first_user_action(){
    global $_PARAMS;
    
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    
    $login = $_PARAMS["login"];
    $pass = $_PARAMS["pass"];
    $rights = $_PARAMS["rights"];
    
    if ( ! $login )  die("Code: ea-" . __LINE__);
    if ( ! $rights ) die("Code: ea-" . __LINE__);
    if ( ! $pass )   die("Code: ea-" . __LINE__);
    $pass = password_hash($pass);
    if ( ! $pass )   die("Code: ea-" . __LINE__);
    

    $res = db_select("users", "SELECT * FROM users LIMIT 1");
    
    if (empty($res)){
        if (db_add("users", array("login"=>$login,"pass"=>$pass, "acl"=>$rights), "Импортирован первый пользователь с логином '".$login."' и правами '".$rights."'.") ){
            set_content("content","<h1>Пользователь импортирован</h1><p>Добавлен пользователь:</p><ul><li><b>login:</b> ".htmlspecialchars($login)."</li><li><b>Пароль:</b> ".htmlspecialchars($pass)."</li><li><b>Права: </b> ".htmlspecialchars($rights)."</li></ul>");
            dosyslog(__FUNCTION__.": WARNING: First user imported into db: user '".htmlspecialchars($login)."' with rights '".htmlspecialchars($rights)."' to db. IP:".$_SERVER["REMOTE_ADDR"]);
        }else{
            set_content("content","<h1>Ошибка импорта пользователя</h1>");
            dosyslog(__FUNCTION__.": ERROR: Can not add user '".htmlspecialchars($login)."' with rights '".htmlspecialchars($rights)."' to db. IP:".@$_SERVER["REMOTE_ADDR"]);
        };
    }else{
        set_content("content","<h1>Операция не доступна</h1>");
        dosyslog(__FUNCTION__.": ERROR: Attempt to import user '".htmlspecialchars($login)."' and rights '".htmlspecialchars($rights)."' to db. IP:".@$_SERVER["REMOTE_ADDR"]);
    };
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
};
function logout_action(){
    logout();
}
function not_auth_action(){
    global $_RESPONSE;
    
    $forbidden_template_file = "forbidden.htm";
    
    if (file_exists(TEMPLATES_DIR . $forbidden_template_file)){
        set_template_file("content", $forbidden_template_file);
    }else{
        set_content("page", "<h1>Доступ запрещен</h1>");
    };
}
function parse_post_data_action(){
    global $_PARAMS;
    global $_PAGE;
    
    if (TEST_MODE) $ERROR[] = __FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.";
    
    $action = $_PARAMS["action"];
           
    switch ($action){
        case "add":
            $db_name = $_PARAMS["db_name"]."s";  //uri: add/account.html, but db = accounts; add/user => users.
            $table = db_get_table_from_xml($db_name);
            foreach($table as $field){
                $type = (string) $field["type"];
                switch($type){
                    case "autoincrement":  // theese parameters set automaticaly, not from user input
                    case "timestamp":
                        break;
                    default:
                        
                        $_PAGE["params"][ (string) $field["name"] ] = array(
                            "type"=> $field["type"],
                            "source" => "post",
                        );
                    
                }; // switch
            }; //foreach
            break;
        case "edit":
            $_PAGE["params"]["id"] = array(
                "type" => "number",
                "source" => "post",
            );
            //
            $_PAGE["params"]["from"] = array(
                "type" => "array",
                "source" => "post",
            );
            //
            $_PAGE["params"]["to"] = array(
                "type" => "array",
                "source" => "post",
            );
            
            // Были ли переданы файлы вместе с формой?
            if (!empty($_FILES)){
                foreach($_FILES as $k=>$v){
                    $_PAGE["params"][ $k ] = array(
                        "type" => "file",
                        "source" => "post",
                    );
                };
            };            
            
            break;
        case "approve": // approve_application
            $tables = array("applications", "users", "accounts");
            foreach ($tables as $db_name){
                $table = db_get_table_from_xml($db_name);
                foreach($table as $field){
                    $type = (string) $field["type"];
                    switch($type){
                        case "autoincrement":  // theese parameters set automaticaly, not from user input
                        case "timestamp":
                            break;
                        default:
                            
                            $_PAGE["params"][ (string) $field["name"] ] = array(
                                "type" => $field["type"],
                                "source" => "post",
                            );
                        
                    }; // switch
                }; //foreach table
            }; // foreach tables
            break;
		case "delete": 
			$_PAGE["params"]["id"] = array(
                "type" => "number",
                "source" => "post",
            );
			//
			$_PAGE["params"]["confirm"] = array(
                "type" => "number",
                "source" => "post",
            );
			break;
    }; // switch
    
           
    SETPARAMS();
    if (TEST_MODE) $ERROR[] = __FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.";    
};
function redirect_action(){
    global $_REDIRECT_URI;
    return redirect($_REDIRECT_URI);
}

function set_template_for_user(){
    global $_USER;
    
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    if ($_USER["isUser"] && !$_USER["isGuest"]){
        set_template_file("content", get_template_file("user"));
    }else{
        set_template_file("content", get_template_file("guest"));
		if (get_template_file("page_guest")) set_template_file("page", get_template_file("page_guest"));
    };

    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
};

