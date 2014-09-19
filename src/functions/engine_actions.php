<?php
function add_data_action($db_table=""){
    global $_PARAMS;
    global $_DATA;
    
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    
    if (!$db_table){
        $db_table = $_PARAMS["object"]."s";  //uri: add/account.html, but db = accounts; add/user => users.
    };
    
    $result = "";
    
    // Проверка прав доступа 
        if ( ! user_has_access_by_ip() ){
            dosyslog(__FUNCTION__ . ": WARNING: Отказ в обслуживании.");
            return array(false, "deny");
        }
    //
    
    
    list($res, $reason) = add_data($db_table, $_PARAMS);
    
       
    if (! $res){
        $_SESSION["to"] = $_PARAMS["to"];
    }else{
        unset($_SESSION["to"]);
    };   
    
    dosyslog(__FUNCTION__.": NOTICE: RESULT = ".$reason);
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    
    if ($res){
        redirect($db_table);
    }else{
        redirect("form/add/".$_PARAMS["object"]);
    };

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
    
    list($res, $reason) = edit_data("applications", $_PARAMS);
    set_session_msg("applications_edit_".$reason,$reason);
    if (! $res){
        
    
    }else{
        $_PARAMS["to"]["acl"] = "access||consultant";
        unset($_PARAMS["to"]["status"]);
        list($res, $reason) = add_data("users", $_PARAMS);
        if (! $res){
            set_session_msg("users_edit_".$reason,"error");
        }
    };
    
       
    if (! $res){
        $_SESSION["to"] = $_PARAMS["to"];
        set_session_msg("applications_approve_".$reason,"error");
    }else{
        unset($_SESSION["to"]);
    };   
    
    dosyslog(__FUNCTION__.": NOTICE: RESULT = ".$reason);
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    
    if ($res){
        redirect("applications");
    }else{
        redirect("form/approve/".$_PARAMS["object"]."/".$_PARAMS["id"]);
    };
    
    
    
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
    
    $object = ! empty($_PARAMS["object"]) ? $_PARAMS["object"] : null;
    if ( ! $object ){
        dosyslog(__FUNCTION__.": FATAL ERROR: Mandatory parameter 'object' is not set. Check form or pages file.");
        die("Code: ea-".__LINE__);
    };
    
	$err_msg_prefix = "";
    if (userHasRight("manager")){
		switch ($object){
			case "user": $err_msg_prefix = "Данные пользователя: ";break;
			case "account": $err_msg_prefix = "Данные партнера: ";break;
			case "application": $err_msg_prefix = "Данные заявки: ";break;
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
		switch ($object){
			case "user": $err_msg_prefix = "Личные данные: ";break;
			case "account": $err_msg_prefix = "Платежные данные: ";break;
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

    list($res, $reason) = edit_data($object."s", $_PARAMS, "", $err_msg);
    
    if (! $res){
        $_SESSION["to"] = $_PARAMS["to"];
        set_session_msg($object."s_edit_".$reson);
    }else{
        unset($_SESSION["to"]);
    };   
    
    dosyslog(__FUNCTION__.": NOTICE: RESULT = ".$reason);
    

    if ($res){
        $redirect_uri = $object."s";
        if ( ($object == "application") ){
            $redirect_uri = "process_application/".$_PARAMS["id"];
        };
        redirect($redirect_uri);
    }else{
        redirect("form/edit/".$_PARAMS["object"] ."/".$_PARAMS["id"]);
    };
    
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
};
function delete_data_action(){
    global $_PARAMS;
    global $_DATA;
    
    
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    
    // Проверка прав доступа 
        if ( ! user_has_access_by_ip() ){
            return array(false,"deny");
        }
    //
    
    
	$id = ! empty($_PARAMS["id"]) ? $_PARAMS["id"] : null;
	if (!$id){
		dosyslog(__FUNCTION__.": FATAL ERROR: Mandatory parameter id is not set. Check form.");
		die("Code: ea-".__LINE__);
	};
	
    $db_table = $_PARAMS["object"] . "s"; 
    $result = "fail";
    
    $confirm = ! empty($_PARAMS["confirm"]) ? $_PARAMS["confirm"] : null;
    
    if ($confirm){
        
		$item = db_get($db_table, $id);
		if ($item){
			list($res, $reason) = db_delete($db_table, $id, get_db_comment($db_name, "delete", $item) );
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
        redirect($db_table);
    }else{
        redirect("form/edit/".$_PARAMS["object"]);
    };

    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
};
function form_action(){
    global $_PARAMS;
    global $_DATA;
    
    $action = $_PARAMS["action"];
    $object = $_PARAMS["object"];
    $form_name = $action."_".$object;
    $db_name = $object ."s";
    
    set_objects_action($form_name);
    
    if ( $action == "add" ){
        $_DATA["fields_form"] = form_prepare($db_name, $form_name);
    }else{
        if ( ! isset($_DATA[$object]) ){
            die("Code: ea-".__LINE__);
        };
        $_DATA["fields_form"] = form_prepare($db_name, $form_name, $_DATA[$object]);
    };
    
    set_template_file("content", $form_name . "_form.htm");
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
    $pass = password_hash($pass);
    if ( ! $pass )   die("Code: ea-" . __LINE__);
    

    $res = db_select("users", "SELECT * FROM users LIMIT 1");
    
    if (empty($res)){
        if (db_add("users", array("login"=>$login,"pass"=>$pass, "acl"=>$rights), "Импортирован первый пользователь с логином '".$login."' и правами '".$rights."'.") ){
            $_DATA["html"] = "<h1>Пользователь импортирован</h1><p>Добавлен пользователь:</p><ul><li><b>login:</b> ".htmlspecialchars($login)."</li><li><b>Пароль:</b> ".htmlspecialchars($pass)."</li><li><b>Права: </b> ".htmlspecialchars($rights)."</li></ul>";
            dosyslog(__FUNCTION__.": WARNING: First user imported into db: user '".htmlspecialchars($login)."' with rights '".htmlspecialchars($rights)."' to db. IP:".$_SERVER["REMOTE_ADDR"]);
        }else{
            $_DATA["html"] = "<h1>Ошибка импорта пользователя</h1>";
            dosyslog(__FUNCTION__.": ERROR: Can not add user '".htmlspecialchars($login)."' with rights '".htmlspecialchars($rights)."' to db. IP:".@$_SERVER["REMOTE_ADDR"]);
        };
    }else{
        $_DATA["html"] = "<h1>Операция не доступна</h1>";
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
    
    logout();
}
function process_application_action(){
    global $_PARAMS;
    global $CFG;
    global $_DATA;
    
    $id = $_PARAMS["id"];
    if(!$id){
        dosyslog(__FUNCTION__.": FATAL ERROR: Mandatory parameter 'objectId' is not set.");
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
                    if ( ! empty($field["required"]) && ($application[ $field["name"] ] == NULL) ){ // если хоть одно из обязательных полей не заполено.
                        // dump($application,"application");
                        // die();
                        $res = false; 
                        $empty_fields[] = (string)$field["name"];
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
  
	$S["object"] = "application"; // without 's' at the end.
    $status = 0;     
    
    
    $data = array("status"=>$status);
    $comment = "Начата регистрация нового партнера.";
        
    $added_id = db_add("applications", $data, $comment);
    
    if ($added_id){
        $_SESSION["application_id"] = $added_id;
        redirect("form/edit/application/" . $added_id);
        unset($_SESSION["to"]);
    }else{
        redirect("process_application");
        unset($_SESSION["application_id"]);
    };
    

};
function send_registration_approval_action(){
	
	global $S;
	global $DONTSHOWERRORS;
	
    // Проверка прав доступа 
        if ( ! user_has_access_by_ip() ){
            dosyslog(__FUNCTION__ . ": WARNING: Отказ в обслуживании.");
            die("Отказ");
            return false;
        }
    //
    
	$email = @$S["email"];
	$email_token = @$S["email_token"];
	$valid_email_token = md5($email.substr(date("Y-m-d H:i"),0,-1));
	$login = @$S["login"];
	$pass = @$S["key"];
	$name = @$S["name"];
	
	$HTML = "";
	
	if (!$email){
		$HTML .= "<div class='alert alert-error'>Не указан e-mail.</div>";
	}elseif($email_token!==$valid_email_token){
		$HTML .= "<div class='alert alert-error'>Истекло разрешенное для отправки письма время.</div>";
	}elseif(empty($login)){
		$HTML .= "<div class='alert alert-error'>Не указан логин.</div>";
	}elseif(empty($pass)){
		$HTML .= "<div class='alert alert-error'>Не указан пароль.</div>";
	}elseif(empty($name)){
		$HTML .= "<div class='alert alert-error'>Не указано имя пользователя.</div>";
	}else{
		if (send_message($email, 'send_registration_approval', array("name"=>$name, "login"=>$login, "pass"=>$pass, "cfg_app_name"=>$CFG["GENERAL"]["app_name"], "login_url"=>$CFG["app_url"] . $CFG["login_uri"] . $CFG["URL"]["ext"], "cfg_app_url"=>$CFG["app_url"], "cfg_system_email"=>$CFG["GENERAL"]["system_email"]))){
			$HTML .= "<div class='alert alert-success'>Письмо отправлено.</div>";
		}else{
			$HTML .= "<div class='alert alert-error'>Не удалось отправить письмо. <br>Отправьте письмо пользователю самостоятельно. <br><b>Сообщите администратору о проблемах с отправкой e-mail.</b></div>";
		};
	};
	
	$DONTSHOWERRORS = true;
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
function set_template_for_user(){
    global $_USER;
    global $_PAGE;
    
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    if ($_USER["isUser"] && !$_USER["isGuest"]){
        if ( ! empty($_PAGE["templates"]["user"])){
            set_template_file("content", $_PAGE["templates"]["user"]);
        }else{
            dosyslog(__FUNCTION__.": FATAL ERROR: template 'user' is not set for page '".$_PAGE["uri"]."'");
            die("Code: ea-".__LINE__);
        }
    }else{
        if ( ! empty($_PAGE["templates"]["guest"])){
            set_template_file("content", $_PAGE["templates"]["guest"]);
            if ( ! empty($_PAGE["templates"]["page_guest"])){
                set_template_file("page", get_template_file("page_guest"));
            };
        }else{
            dosyslog(__FUNCTION__.": FATAL ERROR: template 'guest' is not set for page '".$_PAGE["uri"]."'");
            die("Code: ea-".__LINE__);
        }
    };

    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
};

