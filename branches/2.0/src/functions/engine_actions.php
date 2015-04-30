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
    
    
    list($res, $added_id) = add_data( new FormData($db_table, $_PARAMS) );
    if ( (int) $added_id ){
        $reason = "success";
    }else{
        $reason = $added_id;
    };
    set_session_msg($db_table."_add_".$reason, $reason);
       
    if (! $res){
        $_SESSION["to"] = $_PARAMS["to"];
    }else{
        unset($_SESSION["to"]);
    };   
    
    dosyslog(__FUNCTION__.": NOTICE: RESULT = ".$reason);
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    
    if ($res){
        if ( ! is_null($redirect_on_success) ){
           $redirect_uri = $redirect_on_success ? $redirect_on_success : (!empty($CFG["URL"]["redirect_on_success_default"]) ? $CFG["URL"]["redirect_on_success_default"] : $db_table);
           redirect($redirect_uri);
        };
    }else{
        if ( ! is_null($redirect_on_fail) ){
            redirect($redirect_on_fail ? $redirect_on_fail : "form/add/".$_PARAMS["object"]);
        };
    };
    
    return array($res, $added_id);

};
function approve_application_action(){
    global $_PARAMS;
    global $_DATA;
    
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    
    
    
    $result = "";
    
    // Проверка прав доступа 
        if ( ! user_has_access_by_ip() ){
            dosyslog(__FUNCTION__ . ": WARNING: Отказ в обслуживании.");
            return array(false, "deny");
        }
    //
    
    if ( ! userHasRight("manager") ) return array(false, "deny");
    
    
    $application_data = $_PARAMS;
    
    // Сохранить измененные данные заявки
    list($res, $reason) = edit_data("applications", $application_data);
    set_session_msg("applications_edit_".$reason,$reason);
    if ($res){
        
        $application_data["from"] = $application_data["to"]; // заявка изменена
        // Создать пользователя-консультанта
        $user_data = $_PARAMS;
        $user_data["to"]["acl"] = "access||consultant";
        $user_data["to"]["is_consultant"] = "yes";
        unset($user_data["to"]["status"]);
        
        // убрать фото из добавляемых данных, т.е. файл реально не загружается
        unset($user_data["to"]["photo"]);
        list($res, $added_id) = add_data("users", $user_data);
        $reason = ((int)$added_id) ? "success" : $added_id;
        set_session_msg("users_add_".$reason,$reason);
        if ($res){
            $user_id = $added_id;
            
            // Переместить файл с фотографией в каталог пользователя
            if ( ! empty($application_data["to"]["photo"]) ){
                if ( file_exists($application_data["to"]["photo"]) ){
                    
                    $orig_filename  = pathinfo($application_data["to"]["photo"],PATHINFO_FILENAME);
                    $orig_extension = pathinfo($application_data["to"]["photo"],PATHINFO_EXTENSION);
                    if ( ! $orig_extension ) $orig_extension = "jpg";
                    
                    $user_dir = upload_get_dir("users", "", $user_id);
                    if ( ! is_dir($user_dir) ) mkdir($user_dir, 0777, true);
                    
                    $dest_name = $user_dir . get_filename($orig_filename."__".date("YmdHis"), ".".$orig_extension);
                    
                    if ( copy($application_data["to"]["photo"], $dest_name) ){
                    
                        // Update user photo in DB
                        $changes = array(
                            "photo" => array(
                                "from" => null,
                                "to"   => $dest_name
                            )
                        );
                        
                        list($res, $reason) = db_edit("users", $user_id, $changes, "Фотография пользователя id:".$user_id." взята из заявки id:".$application_data["to"]["id"]);
                        if ( ! $res ){
                            set_session_msg("user_set_photo_".$reason, $reason);
                            dosyslog(__FUNCTION__.": ERROR: Can not set user photo.");
                        };
                    
                    }else{
                        
                        set_session_msg("user_set_photo_copy_fail","error");
                        dosyslog(__FUNCTION__.": ERROR: Can not copy user id:".$user_id." photo from application id:".$application_data["to"]["id"].". Source file: '".$application_data["to"]["photo"]."', dest file: '".$dest_name."'.");
                        
                    }
                    
                }else{
                    set_session_msg("user_set_photo_not_exist","error");
                    dosyslog(__FUNCTION__.": ERROR: User id:".$user_id." photo from application id:".$application_data["to"]["id"]." is not exist. Source file: '".$application_data["to"]["photo"]."'.");
                };
            }else{
                dosyslog(__FUNCTION__.": ERROR: User id:".$user_id." photo is not set in application id:".$application_data["to"]["id"].".");
            };
        
            
            // Записать пароль в сессию
            $_SESSION["application_key_".$application_data["id"]] = $user_data["to"]["pass"];
            
            // Изменить статус заявки на одобренный
            $application_data["to"]["status"] = 16; // одобрено
            if ($application_data["from"]["status"] == 32){
                $application_data["to"]["isDeleted"] = null; // отмена удаления, если одобряемая заявка была до этого удалена
            };
            list($res, $reason) = edit_data("applications", $application_data);
            set_session_msg("applications_approve_".$reason,$reason);
        };
    };
    
    // Сохранить введенные данные в сессию
    if (! $res){
        $_SESSION["to"] = $_PARAMS["to"];
        set_session_msg("applications_approve_".$reason,"error");
    }else{
        unset($_SESSION["to"]);
    };   
    
    dosyslog(__FUNCTION__.": NOTICE: RESULT = ".$reason);
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    
    
    redirect("form/approve/".$_PARAMS["object"]."/".$_PARAMS["id"]); // при любом результате    
    
};
function confirm_email_action(){
    global $CFG;
    global $_PARAMS;
    global $_DATA;
    
    
    $code = @$_PARAMS["id"];
    
    if(empty($code)) {
        dosyslog(__FUNCTION__.": ERROR: Mandatory parameter id is not set. Check pages file.");
        return false;
    };

    $code_c = explode("...",$code);
    
    $id = $code_c[0];
    
    $application = db_get("applications", $id);
    if ($application){
        if ( ($application["status"] == 1) || ($application["status"] == 4) ){
            if ( ($md5 = md5($application["email"].date("Y-m-d",$application["created"]).$id) == $code_c[1]) && ($code_c[2] == $application["email"]) ){
                list($res,$reason) = db_edit("applications", $id, array("status"=>array("from"=>$application["status"],"to"=>4)), "Подтвержден e-mail '".@$application["email"]."'.");
                if ($res){
                    dosyslog(__FUNCTION__.": NOTICE: E-mail '".@$application["email"]."' confirmed with code: ".$code.".");
                    $application["status"] = 4;
                }else{
                    dosyslog(__FUNCTION__.": ERROR: E-mail '".@$application["email"]."' confirmation with code: ".$code.". is not registered in DB.");
                };
                
            }else{
                dosyslog(__FUNCTION__.": WARNING: Somebody (IP:'".@$_SERVER["REMOTE_ADDR"]."') tries to confirm e-mail '".@$code[2]."' for application (id: '".@$id."') with invalide code '".@$code."'.");
            };
        }else{
            dosyslog(__FUNCTION__.": NOTICE: Somebody (IP:'".@$_SERVER["REMOTE_ADDR"]."') tries to confirm e-mail '".@$code[2]."' for application (id: '".@$id."') which is already confirmed.");
        };
    }else{
        dosyslog(__FUNCTION__.": ERROR: Somebody (IP:'".@$_SERVER["REMOTE_ADDR"]."') tries to confirm e-mail '".@$code[2]."' for application (id: '".@$id."') which is not found in DB.");
    };
    
    
    $_DATA["application_id"] = $id;
    $_DATA["application_status"] = $application["status"];
    
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
  
    
    list($res, $reason) = edit_data( new FormData($db_table, $_PARAMS), $id);
    set_session_msg($db_table."_edit_".$reason);
    
    if (! $res){
        $_SESSION["to"] = $_PARAMS["to"];
    }else{
        unset($_SESSION["to"]);
    };   
    
    dosyslog(__FUNCTION__.": NOTICE: RESULT = ".$reason);


    if ($res){
        if ( ! is_null($redirect_on_success) ){
            $redirect_uri = $redirect_on_success ? $redirect_on_success : (!empty($CFG["URL"]["redirect_on_success_default"]) ? $CFG["URL"]["redirect_on_success_default"] : $db_table);
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
        redirect($redirect_on_success ? $redirect_on_success : $db_table);
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
    $db_table    = db_get_db_table($object_name);
    $id          = ! empty($_PARAMS["id"])       ? $_PARAMS["id"]         : null;
    $form_name   = ! empty($_PARAMS["form_name"]) ? $_PARAMS["form_name"] : $action."_".$object_name;

    // 
    $_PAGE["header"] = $_PAGE["title"] = _(ucfirst($action) . " " . $object_name);
    
    
    $_DATA["action"]      = $action;
    $_DATA["db_table"]    = $db_table;
    $_DATA["object_name"] = $object_name;
    
    form_prepare($db_table, $form_name, $id);
      
    
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
    
    $login = $_PARAMS["login"];
    $pass = $_PARAMS["pass"];
    $rights = $_PARAMS["rights"];
    
    if ( ! $login )  die("Code: ea-" . __LINE__);
    if ( ! $rights ) die("Code: ea-" . __LINE__);
    if ( ! $pass )   die("Code: ea-" . __LINE__);
    $pass = passwords_hash($pass);
    if ( ! $pass )   die("Code: ea-" . __LINE__);
    

    $res = db_select("users", "SELECT * FROM users LIMIT 1");
    
    if (empty($res)){
        if (db_add("users", new ChangesSet(array("to" => array("login"=>$login,"pass"=>$pass, "acl"=>$rights))), "Импортирован первый пользователь с логином '".$login."' и правами '".$rights."'.") ){
            echo "<h1>Пользователь импортирован</h1><p>Добавлен пользователь:</p><ul><li><b>login:</b> ".htmlspecialchars($login)."</li><li><b>Пароль:</b> ".htmlspecialchars($pass)."</li><li><b>Права: </b> ".htmlspecialchars($rights)."</li></ul>";
            dosyslog(__FUNCTION__.": WARNING: First user imported into db: user '".htmlspecialchars($login)."' with rights '".htmlspecialchars($rights)."' to db. IP:".$_SERVER["REMOTE_ADDR"]);
        }else{
            echo "<h1>Ошибка импорта пользователя</h1>";
            dosyslog(__FUNCTION__.": ERROR: Can not add user '".htmlspecialchars($login)."' with rights '".htmlspecialchars($rights)."' to db. IP:".@$_SERVER["REMOTE_ADDR"]);
        };
    }else{
        echo "<h1>Операция не доступна</h1>";
        dosyslog(__FUNCTION__.": ERROR: Attempt to import user '".htmlspecialchars($login)."' and rights '".htmlspecialchars($rights)."' to db. IP:".@$_SERVER["REMOTE_ADDR"]);
    };
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    die(__FUNCTION__);
};
function login_action(){
    global $CFG;

    if ( ! empty($CFG["URL"]["dashboard"]) ){
        $redirect_uri = $CFG["URL"]["dashboard"];
    }else{
        $redirect_uri = "";
    }

    redirect($redirect_uri);

}
function logout_action(){
    logout();
}
function not_auth_action(){
    $forbidden_template_file = "forbidden.htm";
    
    if (file_exists(TEMPLATES_DIR . $forbidden_template_file)){
        set_template_file("content", $forbidden_template_file);
    }else{
        set_content("content", "<h1>Доступ запрещен</h1>");
    };
    
    logout();
}
function not_logged_action(){
    global $CFG;
    global $_USER;
    
    $auth_type = !empty($_SESSION["auth_type"]) ? $_SESSION["auth_type"] : "http_basic";
    
    $not_logged_template_file = "not_logged_" . $auth_type . ".htm";
    
    if (file_exists(TEMPLATES_DIR . $not_logged_template_file)){
        set_template_file("content", $not_logged_template_file);
    }else{
        set_content("content", "<h1>Требуется авторизация</h1><p><a href='login".$CFG["URL"]["ext"]."'>Войти</a></p>");
    };
    
    logout();
}
function process_application_action(){
    global $_PARAMS;
    global $CFG;
    global $_DATA;
    
    $id = $_PARAMS["id"];
    if(!$id){
        dosyslog(__FUNCTION__.": FATAL ERROR: Mandatory parameter 'id' is not set.");
        die("Code: ea-" . __LINE__);
    };
    
    
    $application = db_get("applications", $id);
    if (empty($application)){
        dosyslog(__FUNCTION__.": ERROR: Application with id '".@$id."' is not found in applications DB.");
        return false;
    };
    
    $status = (int) $application["status"];
    
    $_DATA["application_id"] = $id;
    $_DATA["application_status"] = $status;
    
    
    
    switch($status){
        case 0: // если необходимые поля в заявке заполнены, изменить статус на следующий.
            if ( !empty($_SESSION["application_id"]) && ($id == $_SESSION["application_id"]) ) { // страницу статуса открывает тот, кто заполнил заявку.
                
                $table = db_get_table_schema("applications");
                $res = true;
                $empty_fields = array();
                foreach($table as $field){
                    if ( ! empty($field["required"]) && ($application[ $field["name"] ] === NULL) ){ // если хоть одно из обязательных полей не заполнено.
                        // dump($application,"application");
                        // die();
                        $res = false; 
                        $empty_fields[ $field["name"] ] = ! empty($field["label"]) ? $field["label"] : $field["name"];
                    };
                };
                if ($res) {
                    $md5 = md5($application["email"].date("Y-m-d",$application["created"]).$id);
                    $confirm_link = $CFG["URL"]["base"] . "confirm_email/".urlencode($id."...".$md5."...".$application["email"]).$CFG["URL"]["ext"];
                    if (send_message($application["email"], "confirm_email", array("confirm_link"=>$confirm_link, "cfg_app_name"=>$CFG["GENERAL"]["app_name"]))){
                        
                        list($res_edit, $reason) = db_edit("applications", $id, array("status"=>array("from"=>0,"to"=>1) ),  "Отправлено письмо со ссылкой подтверждения e-mail '".@$application["email"]."'.");
                        dosyslog(__FUNCTION__.": NOTICE: Confirmation link was sent to e-mail '".@$application["email"]."': ".$confirm_link.".");
                        
                        if ( $res ) $_DATA["application_status"] = 1;
                        
                    }else{

                        dosyslog(__FUNCTION__.": ERROR:  An error occur while sendinng confirmation link to e-mail '".@$application["email"]."'.");
                        set_session_msg("applications_process_email_error");
                        
                    };
                }else{
                    dosyslog(__FUNCTION__.": NOTICE: Confirmation link was NOT sent to e-mail '".@$application["email"]."'.");
                    dosyslog(__FUNCTION__.": NOTICE: Mandatory fields are not filled: '".implode(", ",$empty_fields)."'. Status remains unchanged: '".@$status."'.");
                    
                    if ( ! empty($empty_fields) ){
                        foreach($empty_fields as $field_name=>$field_label){
                            set_session_msg("Не заполнено обязательное поле '".$field_label."'.", "error");
                        };
                    }else{
                        set_session_msg("applications_edit_fail","error");
                    };
                    redirect("pub/form/edit/application/".$id);
                    
                };
                
                return $res;
            };
            break;
        
        case 2:  // email подтвержден

        };   
};
function redirect_action(){
    global $_REDIRECT_URI;
    return redirect($_REDIRECT_URI);
}
function register_application_action(){ // регистрация в БД новой заявки на регистрацию в партнерской программе.
    global $CFG;
    global $_PARAMS;
  
    redirect("pub/form/add/application");
    unset($_SESSION["to"]);
    unset($_SESSION["application_id"]);

};
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
function send_registration_approval_action(){
	global $_PARAMS;
    global $CFG;
    // Проверка прав доступа 
        if ( ! user_has_access_by_ip() ){
            dosyslog(__FUNCTION__ . ": WARNING: Отказ в обслуживании.");
            die("Отказ");
            return false;
        }
    //

	$login       = ! empty($_PARAMS["login"])       ? $_PARAMS["login"]       : null;
	$pass        = ! empty($_PARAMS["key"])         ? $_PARAMS["key"]         : null;
	$name        = ! empty($_PARAMS["name"])        ? $_PARAMS["name"]        : null;
	$email       = ! empty($_PARAMS["email"])       ? $_PARAMS["email"]       : null;
	$email_token = ! empty($_PARAMS["email_token"]) ? $_PARAMS["email_token"] : null;
	
    $valid_email_token = md5($email.substr(date("Y-m-d H:i"),0,-1));

	
	$HTML = "";
	
	if ( ! $email ){
		$HTML .= "<div class='alert alert-error'>Не указан e-mail.</div>";
	}elseif( $email_token !== $valid_email_token ){
		$HTML .= "<div class='alert alert-error'>Истекло разрешенное для отправки письма время.</div>";
	}elseif( empty($login) ){
		$HTML .= "<div class='alert alert-error'>Не указан логин.</div>";
	}elseif( empty($pass) ){
		$HTML .= "<div class='alert alert-error'>Не указан пароль.</div>";
	}elseif( empty($name) ){
		$HTML .= "<div class='alert alert-error'>Не указано имя пользователя.</div>";
	}else{
		if ( send_message($email, 'send_registration_approval', array("name"=>$name, "login"=>$login, "pass"=>$pass, "cfg_app_name"=>$CFG["GENERAL"]["app_name"], "login_url"=>$CFG["URL"]["base"] . "login" . $CFG["URL"]["ext"], "cfg_app_url"=>$CFG["URL"]["base"], "cfg_system_email"=>$CFG["GENERAL"]["system_email"])) ){
			$HTML .= "<div class='alert alert-success'>Письмо отправлено.</div>";
		}else{
			$HTML .= "<div class='alert alert-error'>Не удалось отправить письмо. <br>Отправьте письмо пользователю самостоятельно. <br><b>Сообщите администратору о проблемах с отправкой e-mail.</b></div>";
		};
	};
	
	exit($HTML);
};
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
    
    
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");  
 
    $_DATA["topmenu"] = set_topmenu();
    
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
};    

