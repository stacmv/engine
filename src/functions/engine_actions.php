<?php
function add_data_action($db_table="", $redirect_on_success="", $redirect_on_fail=""){
    global $_PARAMS;
    global $_DATA;
    global $CFG;
    
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    
    if (!$db_table){
        $db_table = db_get_db_table($_PARAMS["object"]);  //uri: add/account.html, but db = accounts; add/user => users.
    };
    
    $result = "";
    
    // Проверка прав доступа 
        if ( ! user_has_access_by_ip() ){
            dosyslog(__FUNCTION__ . ": WARNING: Отказ в обслуживании.");
            return array(false, "deny");
        }
    //
    
    
    // 
    $formdata = new FormData($db_table, $_PARAMS);
    
        
    if ($formdata->is_valid){
    
        list($res, $added_id) = add_data( $formdata );
        if ( (int) $added_id ){
            $reason = "success";
        }else{
            $reason = $added_id;
        };
        set_session_msg($db_table."_add_".$reason, $reason);
        
    }else{
        $res = false;
        $reason = "validation_error";
        set_session_msg($db_table."_add_".$reason, "fail");
    }
       
    if (! $res){
        $_SESSION["to"] = $_PARAMS["to"];
        $_SESSION["form_errors"] = $formdata->errors;
    }else{
        unset($_SESSION["to"]);
        unset($_SESSION["form_errors"]);
    };   
    
    dosyslog(__FUNCTION__.": NOTICE: RESULT = ".$reason);
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    
    if ($res){
        if ( ! is_null($redirect_on_success) ){
           $redirect_uri = $redirect_on_success ? $redirect_on_success : (!empty($CFG["URL"]["redirect_on_success_default"]) ? $CFG["URL"]["redirect_on_success_default"] : str_replace(".","__", $db_table) );
           redirect($redirect_uri);
        };
    }else{
        if ( ! is_null($redirect_on_fail) ){
            redirect($redirect_on_fail ? $redirect_on_fail : "form/add/".$_PARAMS["object"]);
        };
    };
    
    return array($res, $added_id);

};
function do_nothing_action(){
    // do nothing special except ...
    
    global $_PARAMS;
    global $_DATA;
    
    $_DATA = $_PARAMS;
    
}
function edit_data_action($db_table="", $redirect_on_success="", $redirect_on_fail=""){
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
    $id = ! empty($_PARAMS["id"]) ? $_PARAMS["id"] : null;
    if ( ! $id ){
        dosyslog(__FUNCTION__.": FATAL ERROR: Mandatory parameter 'id' is not set. Check form or pages file.");
        die("Code: ea-".__LINE__);
    };
    
    if ( ! $db_table ){
        $object = $_PARAMS["object"];
        $db_table = db_get_db_table($_PARAMS["object"]);;  //uri: edit/account.html, but db = accounts; edit/user => users.
    }else{
        $object = db_get_obj_name($db_table);
    }
    
    if ( ! $object ){
        dosyslog(__FUNCTION__.": FATAL ERROR: Mandatory parameter 'object' is not set. Check form or pages file.");
        die("Code: ea-".__LINE__);
    };
  
    
    $formdata = new FormData($db_table, $_PARAMS);
    
    if ($formdata->is_valid){
    
        list($res, $reason) = edit_data($formdata, $id);
        set_session_msg($db_table."_edit_".$reason);
    }else{
        $res = false;
        $reason = "validation_error";
        set_session_msg($db_table."_edit_".$reason, "fail");
    }
    
    if (! $res){
        $_SESSION["to"] = $_PARAMS["to"];
        $_SESSION["form_errors"] = $formdata->errors;
    }else{
        unset($_SESSION["to"]);
        unset($_SESSION["form_errors"]);
    };   
    
    dosyslog(__FUNCTION__.": NOTICE: RESULT = ".$reason);


    if ($res){
        if ( ! is_null($redirect_on_success) ){
            $redirect_uri = $redirect_on_success ? $redirect_on_success : (!empty($CFG["URL"]["redirect_on_success_default"]) ? $CFG["URL"]["redirect_on_success_default"] : str_replace(".","__", $db_table));
            redirect($redirect_uri);
        };
    }else{
        if ( ! is_null($redirect_on_fail) ){
            redirect($redirect_on_fail ? $redirect_on_fail : "form/edit/".$_PARAMS["object"] ."/".$_PARAMS["id"]);
        };
    };
    
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    
    return array($res, $reason);
    
};
function delete_data_action($db_table="", $redirect_on_success="", $redirect_on_fail=""){
    global $_PARAMS;
    global $_DATA;
    
    
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    
    // Проверка прав доступа 
        if ( ! user_has_access_by_ip() ){
            return array(false,"deny");
        }
    //
    
    if (!$db_table){
        $db_table = db_get_db_table($_PARAMS["object"]);  //uri: add/account.html, but db = accounts; add/user => users.
    };
    
	$id = ! empty($_PARAMS["id"]) ? $_PARAMS["id"] : null;
	if (!$id){
		dosyslog(__FUNCTION__.": FATAL ERROR: Mandatory parameter id is not set. Check form.");
		die("Code: ea-".__LINE__);
	};
	
    $result = "fail";
    
    $confirm = ! empty($_PARAMS["confirm"]) ? $_PARAMS["confirm"] : null;
    $comment = ! empty($_PARAMS["comment"]) ? $_PARAMS["comment"] : null;
    
    if ($confirm){
        
		$item = db_get($db_table, $id,DB_RETURN_DELETED);
		if ($item){
			
            list($res, $reason) = db_delete($db_table, $id, $comment );
            set_session_msg($db_table."_delete_".$reason, $reason);
            
            if ($res){
                dosyslog(__FUNCTION__.": NOTICE: Object '".$_PARAMS["object"]."' width id '".$id."' is marked as deleted.");
            }else{
                dosyslog(__FUNCTION__.": ERROR: WARNING '".$_PARAMS["object"]."' width id '".$id."' is not deleted by reason '".$reason."'.");
            }
		}else{
			dosyslog(__FUNCTION__.": ERROR: Object '".$_PARAMS["object"]."' width id '".$id."' is not found in DB.");
		};
        
    }else{
        $res = false;
        $reason = "not_confirmed";
    }
    
    if (! $res){
        $_SESSION["to"] = $_PARAMS["to"];
    }else{
        unset($_SESSION["to"]);
    };   
    
    dosyslog(__FUNCTION__.": NOTICE: RESULT = ".$reason);
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    
    if ($res){
        redirect($redirect_on_success ? $redirect_on_success : str_replace(".","__", $db_table) );
    }else{
        redirect($redirect_on_fail ? $redirect_on_fail : "form/edit/".$_PARAMS["object"]."/".$id);
    };

    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    
    return array($res, $reason);
};
function form_action(){
    global $_PARAMS;
    global $_PAGE;
    global $_DATA;
    
    if (function_exists("set_topmenu_action")) set_topmenu_action();
    
    $action      = ! empty($_PARAMS["action"])   ? $_PARAMS["action"]     : null;
    $object_name = ! empty($_PARAMS["object"])   ? $_PARAMS["object"]     : null;
    
    if ( ! $action || ! $object_name ) {
        dosyslog(__FUNCTION__.get_callee().": FATAL ERROR: Mandatory parameter 'action', 'object_name' or both is not set. Check pages config for '".$_PAGE["uri"]."' or corresponding action.");
        die("Code: ea-".__LINE__);
    };
    
    $db_table    = db_get_db_table($object_name);
    $id          = ! empty($_PARAMS["id"])       ? $_PARAMS["id"]         : null;
    $form_name   = ! empty($_PARAMS["form_name"]) ? $_PARAMS["form_name"] : $action."_".$object_name;

    // 
    if ( empty($_PAGE["title"]) ) $_PAGE["title"] = _t(ucfirst($action) . " " . $object_name);
    if ( empty($_PAGE["header"])) $_PAGE["header"] = $_PAGE["title"];
    
    
    $_DATA["action"]      = $action;
    $_DATA["db_table"]    = $db_table;
    $_DATA["object_name"] = $object_name;
    
    if ($id && ($action != "add") ){
        $object = db_get($db_table, $id, DB_RETURN_DELETED);
    }else{
        $object = array();
    };
    
    $fields = form_prepare($db_table, $form_name, $object);
          
    // Подготовка дополнительных данных для формы
    if ( function_exists("form_prepare_" . $form_name) ){
        $fields = call_user_func("form_prepare_" . $form_name, $fields, $id);
    }
    if (function_exists("set_objects_action")){
        set_objects_action($form_name);
    }
    //
    
    $_DATA["object"]      = $object;
    $_DATA["fields_form"] = $fields;
    $_DATA["fields_form"][] = form_prepare_field( array("type"=>"string", "form_template"=>"hidden", "name"=>"form_name"), true, $form_name);
    
    
    $form_template = !empty($_PAGE["templates"][$form_name]) ? $_PAGE["templates"][$form_name] : null;
    if ( ! $form_template && (file_exists( cfg_get_filename("templates", $form_name . "_form.htm"))) ){
        $form_template = $form_name . "_form.htm";
    }elseif ( ! $form_template && (file_exists( cfg_get_filename("templates",  $action . "_form.htm"))) ){
        $form_template = $action . "_form.htm";
    };  

	if ($form_template){
		set_template_file("content", $form_template);
	}else{
		dosyslog(__FUNCTION__.": FATAL ERROR: Form template for form '".$form_name."' is not found.");
		die("Code: ea-".__LINE__."-form_template");
	}
}
function import_first_user_action(){
    global $_PARAMS;
    global $_DATA;
    
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    
    $ip = ! empty($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : "_unknown_";
    
    $login = $_PARAMS["login"];
    $pass = $_PARAMS["pass"];
    $rights = $_PARAMS["rights"];
    
    if ( ! $login )  die("Code: ea-" . __LINE__);
    if ( ! $rights ) die("Code: ea-" . __LINE__);
    if ( ! $pass )   die("Code: ea-" . __LINE__);
    // $pass = passwords_hash($pass);
    if ( ! $pass )   die("Code: ea-" . __LINE__);
    

    $res = db_select("users", "SELECT * FROM users LIMIT 1");
    
    if (empty($res)){
        if (db_add("users", new ChangesSet(array("login"=>$login,"pass"=>$pass, "acl"=>$rights)), "Импортирован первый пользователь с логином '".$login."' и правами '".$rights."'.") ){
            echo "<h1>Пользователь импортирован</h1><p>Добавлен пользователь:</p><ul><li><b>login:</b> ".htmlspecialchars($login)."</li><li><b>Пароль:</b> ".htmlspecialchars($pass)."</li><li><b>Права: </b> ".htmlspecialchars($rights)."</li></ul>";
            dosyslog(__FUNCTION__.": WARNING: First user imported into db: user '".htmlspecialchars($login)."' with rights '".htmlspecialchars($rights)."' to db. IP:".$ip);
        }else{
            echo "<h1>Ошибка импорта пользователя</h1>";
            dosyslog(__FUNCTION__.": ERROR: Can not add user '".htmlspecialchars($login)."' with rights '".htmlspecialchars($rights)."' to db. IP:".$ip);
        };
    }else{
        echo "<h1>Операция не доступна</h1>";
        dosyslog(__FUNCTION__.": ERROR: Attempt to import user '".htmlspecialchars($login)."' and rights '".htmlspecialchars($rights)."' to db. IP:".$ip);
    };
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    die(__FUNCTION__);
};
function login_simple_action(){
    global $CFG;
    global $_PARAMS;
    
    $login = ! empty($_PARAMS["login"]) ? $_PARAMS["login"] : null;
    $pass  = ! empty($_PARAMS["pass"])  ? $_PARAMS["pass"]  : null;
    
    // identicate
    $user = EUsers::find_one("login",$login);
    
    if ($user){
        // authenticate
        $authenticated = passwords_verify($pass, $user["pass"]);
        if ( ! $authenticated){
            dosyslog(__FUNCTION__.": WARNING: Provided wrong password for user with login '".$login."'.");
        }
    }else{
        dosyslog(__FUNCTION__.": WARNING: User with login '".$login."' is not exists.");
    }
    
    if ($user && $authenticated){
        // log in
        session_regenerate_id();
        $_SESSION["authenticated"] = $user["id"];
        dosyslog(__FUNCTION__.": INFO: User with login '".$login."' is logged in.");
    }else{
        set_session_msg("login_login_fail","error");
    }
    
    $redirect_uri = return_url_pop();

    if (! $redirect_uri && ! empty($CFG["URL"]["dashboard"]) ){
        $redirect_uri = $CFG["URL"]["dashboard"];
    };

    redirect($redirect_uri ? $redirect : "index");

}
function logout_action(){
    logout();
}
function not_auth_action(){
    $forbidden_template_file = "forbidden.htm";
    
    if (file_exists(cfg_get_filename("templates", $forbidden_template_file))){
        set_template_file("content", $forbidden_template_file);
    }else{
        set_content("content", "<h1>Доступ запрещен</h1>");
    };
    
    logout();
}
function not_logged_action(){
    global $CFG;
    global $_DATA;
    
    logout();
    redirect("form/login");
    
    // $auth_types = get_auth_types();
    // $auth_type = !empty($_SESSION["auth_type"]) ? $_SESSION["auth_type"] : $auth_types[0];
    
    // $not_logged_page_template  =  "not_logged_" . $auth_type . ".page.htm";
    // $not_logged_block_template = "not_logged_" . $auth_type . ".block.htm";
    // if ( file_exists(cfg_get_filename("templates", $not_logged_page_template)) ){
        // set_template_file("page", $not_logged_page_template);
    // }elseif( file_exists(cfg_get_filename("templates",$not_logged_block_template)) ){
        // set_template_file("content", $not_logged_block_template);
    // }else{
        // set_content("page", "<h1>Требуется авторизация</h1><p><a href='login".$CFG["URL"]["ext"]."'>Войти</a></p>");
    // };

    // $_DATA["auth_type"] = $auth_type;
    
    
}
function redirect_action(){
    global $_REDIRECT_URI;
    return redirect($_REDIRECT_URI);
}
function register_message_opened_action(){  // трекинг открытия отправленного ранее письма
    global $_PARAMS;
    global $_RESPONSE;
    global $IS_API_CALL;
    
    $message_id = ! empty($_PARAMS["message_id"]) ? $_PARAMS["message_id"] : null;
    
    if ($message_id){
        register_message_opened($message_id);
    }else{
    
        dosyslog(__FUNCTION__.": ERROR: Message_id is not set. Check pages config and email templates.");
    };
    
    $_RESPONSE["headers"]["HTTP"] = "HTTP/1.0 204 Tracked. " . (isset($message_id) ? $message_id : "" );
    $_RESPONSE["headers"]["Cache-Control"] = "no-cache, must-revalidate"; 
    $_RESPONSE["headers"]["Pragma"] = "no-cache"; 
    $_RESPONSE["headers"]["Content-type"] = "image/gif";
    
    $IS_API_CALL = true;

}
function send_registration_repetition_request_action(){
	global $CFG;
	global $_PARAMS;

    // Проверка прав доступа 
        if ( ! user_has_access_by_ip() ){
            dosyslog(__FUNCTION__ . ": WARNING: Отказ в обслуживании.");
            die("Отказ");
            return false;
        }
    //
	
	$email = ! empty($_PARAMS["email"]) ? $_PARAMS["email"] : null;
	$email_token = ! empty($_PARAMS["email_token"]) ? $_PARAMS["email_token"] : null;
	$valid_email_token = md5($email.substr(date("Y-m-d H:i"),0,-1));
	$date_str =  ! empty($_PARAMS["date_str"]) ? $_PARAMS["date_str"] : null;
	$phone =  ! empty($_PARAMS["phone"]) ? $_PARAMS["phone"] : null;
	$name = ! empty($_PARAMS["name"]) ? $_PARAMS["name"] : null;
    $app_id = ! empty($_PARAMS["app_id"]) ? $_PARAMS["app_id"] : null;
	
	$HTML = "";
	
	if ( ! $email ){
		$HTML .= "<div class='alert alert-error'>Не указан e-mail.</div>";
	}elseif( $email_token !== $valid_email_token ){
		$HTML .= "<div class='alert alert-error'>Истекло разрешенное для отправки письма время.</div>";
	}elseif( empty($phone) ){
		$HTML .= "<div class='alert alert-error'>Не указан телефон.</div>";
	}elseif( empty($date_str) ){
		$HTML .= "<div class='alert alert-error'>Не указана дата.</div>";
	}elseif( empty($name) ){
		$HTML .= "<div class='alert alert-error'>Не указано имя пользователя.</div>";
	}else{
		if (send_message($email, 'send_registration_repetition_request', array("name"=>$name, "phone"=>$phone, "date_str"=>$date_str, "cfg_app_name"=>$CFG["GENERAL"]["app_name"], "cfg_app_url"=>$CFG["URL"]["base"], "cfg_system_email"=>$CFG["GENERAL"]["system_email"]))){
			$HTML .= "<div class='alert alert-success'>Письмо отправлено.<p><a href='form/delete/application/".$app_id.".html'>Заявку можно удалить</a></p></div>";
		}else{
			$HTML .= "<div class='alert alert-error'>Не удалось отправить письмо. <br>Отправьте письмо пользователю самостоятельно. <br><b>Сообщите администратору о проблемах с отправкой e-mail.</b></div>";
		};
	};
	exit($HTML);
};
function set_topmenu_action(){
    global $_DATA;
     
    $_DATA["topmenu"] = get_topmenu();
    
};    
function show_data_action(){
    global $_PARAMS;
    global $_DATA;
    global $_PAGE;
    
    $id    = ! empty($_PARAMS["id"]) ? (int) $_PARAMS["id"] : null; 
    $model = ! empty($_PARAMS["model"]) ? $_PARAMS["model"] : null; 
    $mode  = $id ? "item" : "list";
    
    if ($model){
        $obj_name = db_get_obj_name($model);
        $_DATA["fields"] = form_get_fields($model,"add_" . $obj_name);
        $_DATA["item_name"] = $obj_name;
        if ($mode == "list"){ // 
            $get_all_function = "get_".$model;
            if (function_exists($get_all_function)){
                $_DATA["items"] = call_user_func($get_all_function, "all");
            }else{
                $_DATA["items"] = db_get($model, "all");
            };
            if ( empty($_PAGE["templates"]["content"]) && ! empty($_PAGE["templates"]["list"]) ){
                set_template_file("content", $_PAGE["templates"]["list"]);
            };
        }else{
            $get_item_function = "get" . $obj_name;
            if (function_exists($get_item_function)){
                $_DATA["item"] = call_user_func($get_item_function, $id);
            }else{
                $_DATA["item"] = db_get($model, $id);
            };
            if ( empty($_PAGE["templates"]["content"]) && ! empty($_PAGE["templates"]["item"]) ){
                set_template_file("content", $_PAGE["templates"]["item"]);
            };
        }
    }else{
        die("Code: ea-".__LINE__."-model");
    }
}
function show_login_form_action(){
    global $_DATA;
    global $CFG;
    
    $auth_types = get_auth_types();
    $_DATA["auth_type"] = isset($auth_types[0]) ? $auth_types[0] : "simple";
    
    if ( ! empty($_SERVER["HTTP_REFERER"]) && (strpos($_SERVER["HTTP_REFERER"], $CFG["URL"]["base"]) === 0) && ($_SERVER["HTTP_REFERER"] != $CFG["URL"]["base"] . "form/login" . $CFG["URL"]["ext"]) ){
        return_url_push($_SERVER["HTTP_REFERER"]);
    };
    
    // There no users yet -- link to add first user
    if ( ! db_get_count("users") ){
        $_DATA["import_first_user"] = true;
    };
    
}
