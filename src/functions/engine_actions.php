<?php
function add_data_action($db_table="", $redirect_on_success="", $redirect_on_fail=""){
    global $_PARAMS;
    global $_DATA;
    global $CFG;
    global $_PAGE;
    global $IS_AJAX;

    if (!$db_table){
        $db_table = db_get_db_table($_PARAMS["object"]);  //uri: add/account.html, but db = accounts; add/user => users.
    };

    $result = "";
    $params = $_PARAMS;

    // On Before Add Hook
    $callback = db_get_meta($db_table, "onbeforeadd");
    if ($callback){
        if (function_exists($callback)){
            $callback_params = array(
                "args"     => func_get_args(),
                "params"   => $params,
            );
            $callback_res = call_user_func($callback, $callback_params);
            if (false === $callback_res["res"]){
                return array($callback_res[$res], $callback_res["reason"]);
            }else{
                $params = $callback_res["params"];
            }

        }else{
            dosyslog(__FUNCTION__.": ERROR: OnBeforeAdd callback function for db table '".$db_table."' is not defined: '".$callback."'.");
        }
    }


    //
    $formdata = new FormData($db_table, $params);


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


    $_DATA["res"] = $res;
    $_DATA["reason"] = $reason;
    if ($res){
        $_DATA["added_id"] = $added_id;
        if ( ! is_null($redirect_on_success) ){
            if ($redirect_on_success){
               $redirect_uri = $redirect_on_success;
            }else{
                if ($_PAGE["uri"] == "pub/add"){
                    // Форма заполнена в публичной части
                    if (db_get_meta($db_table, "pub_add_success_redirect")){
                        $redirect_uri = db_get_meta($db_table, "pub_add_success_redirect");
                    }elseif(!empty($CFG["URL"]["on_success"])){
                        $redirect_uri = $CFG["URL"]["on_success"];
                    }else{
                        $redirect_uri = "index";
                    };

                }else{
                    // Форма заполнена в ажминке
                    if (db_get_meta($db_table, "add_success_redirect")){
                        $redirect_uri = db_get_meta($db_table, "add_success_redirect");
                    }elseif(!empty($CFG["URL"]["redirect_on_success_default"])){
                        $redirect_uri =  $CFG["URL"]["redirect_on_success_default"];
                    }elseif(db_get_name($db_table) == db_get_table($db_table)){
                        $redirect_uri = db_get_meta($db_table, "model_uri_prefix") . db_get_name($db_table);
                    }else{
                        $redirect_uri = db_get_meta($db_table, "model_uri_prefix") . $db_table;
                    };
                };
            };

            if ($IS_AJAX) $_DATA["redirect_uri"] = $redirect_uri;
            else redirect($redirect_uri);
        };
    }else{
        if ( ! is_null($redirect_on_fail) ){
            $redirect_uri = $redirect_on_fail ? $redirect_on_fail : "form/add/".$_PARAMS["object"];
            if ($IS_AJAX) $_DATA["redirect_uri"] = $redirect_uri;
            else redirect($redirect_uri);
        };
    };


    // On After Add Hook
    $callback = db_get_meta($db_table, "onafteradd");
    if ($callback){
        if (function_exists($callback)){
            $callback_params = array(
                "args"     => func_get_args(),
                "formdata" => $formdata,
                "res"      => $res,
                "reason"   => $reason,
                "added_id" => $added_id,
            );
            call_user_func($callback, $callback_params);
        }else{
            dosyslog(__FUNCTION__.": ERROR: OnAfterAdd callback function for db table '".$db_table."' is not defined: '".$callback."'.");
        }
    }


    return array($res, $reason);

};
function add_user_application_action(){
    global $CFG;

    list($res, $added_id) = add_data_action("user_applications", "form/login", "form/signup");

    // if ($res){

        // $user_application = db_get("user_applications", $added_id);

        // $md5 = md5($user_application["email"].date("Y-m-d",$user_application["created"]).$added_id);
        // $confirm_link = $CFG["URL"]["base"] . "confirm_email/".urlencode($added_id."...".$md5."...".$user_application["email"]).$CFG["URL"]["ext"];

        // $data = array(
            // "cfg_app_name" => $CFG["GENERAL"]["app_name"],
            // "cfg_app_url"  => $CFG["URL"]["base"],
            // "confirm_link" => $confirm_link,
        // );

        // if (send_message($user_application["email"], "signup.confirm_email", $data)){
            // dosyslog(__FUNCTION__.": NOTICE: Confirmation link was sent to e-mail '".@$application["email"]."': ".$confirm_link.".");
        // }else{
            // dosyslog(__FUNCTION__.": ERROR:  An error occur while sendinng confirmation link to e-mail '".@$application["email"]."'.");
        // };

    // }


}
function approve_user_application_action(){
    global $_PARAMS;

    $id = ! empty($_PARAMS["id"]) ? $_PARAMS["id"] : null;

    if ($id && userHasRight("access,manager")){
        $user_application = db_get("user_applications",$id);
        if ($user_application){
            $user_application["acl"] = array("access");
            $_SESSION["to"] = $user_application;
            redirect("form/add/user");
            return;
        }
    }

    die("No way!");

}
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
    global $IS_AJAX;

    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");

    $params = $_PARAMS;

    $id = ! empty($params["id"]) ? $params["id"] : null;
    if ( ! $id ){
        dosyslog(__FUNCTION__.": FATAL ERROR: Mandatory parameter 'id' is not set. Check form or pages file.");
        die("Code: ea-".__LINE__);
    };
    if ( ! $db_table ){
        $object = $params["object"];
        $db_table = db_get_db_table($params["object"]);;  //uri: edit/account.html, but db = accounts; edit/user => users.
    }else{
        $object = db_get_obj_name($db_table);
    }

    if ( ! $object ){
        dosyslog(__FUNCTION__.": FATAL ERROR: Mandatory parameter 'object' is not set. Check form or pages file.");
        die("Code: ea-".__LINE__);
    };

    // On Before Edit Hook
    $callback = db_get_meta($db_table, "onbeforeedit");
    if ($callback){
        if (function_exists($callback)){
            $callback_params = array(
                "args"     => func_get_args(),
                "params"   => $params,
            );
            $callback_res = call_user_func($callback, $callback_params);
            if (false === $callback_res["res"]){
                return array($callback_res[$res], $callback_res["reason"]);
            }else{
                $params = $callback_res["params"];
            }

        }else{
            dosyslog(__FUNCTION__.": ERROR: OnBeforeEdit callback function for db table '".$db_table."' is not defined: '".$callback."'.");
        }
    }
    //



    $formdata = new FormData($db_table, $params);

    if ($formdata->is_valid){

        list($res, $reason) = edit_data($formdata, $id);
        set_session_msg($db_table."_edit_".$reason);
    }else{
        $res = false;
        $reason = "validation_error";
        set_session_msg($db_table."_edit_".$reason, "fail");
    }

    if (! $res){
        $_SESSION["to"] = $params["to"];
        $_SESSION["form_errors"] = $formdata->errors;
    }else{
        unset($_SESSION["to"]);
        unset($_SESSION["form_errors"]);
    };

    dosyslog(__FUNCTION__.": NOTICE: RESULT = ".$reason);


    if ($IS_AJAX){

        $_DATA["result"] = $res;
        $_DATA["reason"] = $reason;
        clear_actions();

    }else{

        if ($res){
            if ( ! is_null($redirect_on_success) ){
                if ($redirect_on_success){
                   $redirect_uri = $redirect_on_success;
                }elseif (db_get_meta($db_table, "edit_success_redirect")){
                    $redirect_uri = db_get_meta($db_table, "edit_success_redirect");
                }elseif(!empty($CFG["URL"]["redirect_on_success_default"])){
                    $redirect_uri =  $CFG["URL"]["redirect_on_success_default"];
                }elseif(db_get_name($db_table) == db_get_table($db_table)){
                    $redirect_uri = db_get_meta($db_table, "model_uri_prefix") . db_get_name($db_table);
                }else{
                    $redirect_uri = db_get_meta($db_table, "model_uri_prefix") . $db_table;
                };
                redirect($redirect_uri);
            };
        }else{
            if ( ! is_null($redirect_on_fail) ){
                redirect($redirect_on_fail ? $redirect_on_fail : "form/edit/".$params["object"] ."/".$params["id"]);
            };
        };
    }

    // On After Edit Hook
    $callback = db_get_meta($db_table, "onafteredit");
    if ($callback){
        if (function_exists($callback)){
            $callback_params = array(
                "args"     => func_get_args(),
                "formdata" => $formdata,
                "res"      => $res,
                "reason"   => $reason,
            );
            call_user_func($callback, $callback_params);
        }else{
            dosyslog(__FUNCTION__.": ERROR: OnAfterEdit callback function for db table '".$db_table."' is not defined: '".$callback."'.");
        }
    }

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
       if ( ! is_null($redirect_on_success) ){
            if ($redirect_on_success){
               $redirect_uri = $redirect_on_success;
            }elseif (db_get_meta($db_table, "edit_success_redirect")){
                $redirect_uri = db_get_meta($db_table, "edit_success_redirect");
            }elseif(!empty($CFG["URL"]["redirect_on_success_default"])){
                $redirect_uri =  $CFG["URL"]["redirect_on_success_default"];
            }elseif(db_get_name($db_table) == db_get_table($db_table)){
                $redirect_uri = db_get_meta($db_table, "model_uri_prefix") . db_get_name($db_table);
            }else{
                $redirect_uri = db_get_meta($db_table, "model_uri_prefix") . $db_table;
            };
            redirect($redirect_uri);
        };
    }else{
        redirect($redirect_on_fail ? $redirect_on_fail : "form/edit/".$_PARAMS["object"]."/".$id);
    };

    // On After Delete Hook
    $callback = db_get_meta($db_table, "onafterdelete");
    if ($callback){
        if (function_exists($callback)){
            $params = array(
                "args"     => func_get_args(),
                "formdata" => $formdata,
                "res"      => $res,
                "reason"   => $reason,
                "added_id" => $added_id,
            );
            call_user_func($callback, $params);
        }else{
            dosyslog(__FUNCTION__.": ERROR: OnAfterDelete callback function for db table '".$db_table."' is not defined: '".$callback."'.");
        }
    }

    return array($res, $reason);
};
function form_action($is_public=false){
    global $_PARAMS;
    global $_PAGE;
    global $_DATA;


    $action      = ! empty($_PARAMS["action"])   ? $_PARAMS["action"]     : null;
    $object_name = ! empty($_PARAMS["object"])   ? $_PARAMS["object"]     : null;

    if ( ! $action || ! $object_name ) {
        dosyslog(__FUNCTION__.get_callee().": FATAL ERROR: Mandatory parameter 'action', 'object_name' or both is not set. Check pages config for '".$_PAGE["uri"]."' or corresponding action.");
        die("Code: ea-".__LINE__);
    };

    $repo_name    = db_get_db_table($object_name);
    $id          = ! empty($_PARAMS["id"])       ? $_PARAMS["id"]         : null;
    $form_name   = ! empty($_PARAMS["form_name"]) ? $_PARAMS["form_name"] : $action."_".$object_name;

    //
    if ( empty($_PAGE["title"]) ) $_PAGE["title"] = _t(ucfirst($action) . " " . $object_name);
    if ( empty($_PAGE["header"])) $_PAGE["header"] = $_PAGE["title"];


    $_DATA["action"]      = $action;
    $_DATA["repo_name"]    = $repo_name;
    $_DATA["object_name"] = $object_name;
    $_DATA["form_name"]   = $form_name;
    $_DATA["form_action_link"] = form_get_action_link($form_name, $is_public);


    if ($id && ($action != "add") ){
        $_DATA["object"] = db_get($repo_name, $id, DB_RETURN_DELETED);
    }else{
        $_DATA["object"] = array();
    };

    if ($id) $_DATA["id"] = $id;

    $_DATA["fields_form"] = form_prepare($repo_name, $form_name, $_DATA["object"]);
    // if ($_DATA["object"]){
        // $_DATA["object"] = form_prepare_view_item($_DATA["object"], $_DATA["fields_form"]);
    // }

    // Подготовка дополнительных данных для формы
    if ( function_exists("form_prepare_" . $form_name) ){
        $_DATA["fields_form"] = call_user_func("form_prepare_" . $form_name, $_DATA["fields_form"], $id);
    }
    if (function_exists("set_objects_action")){
        set_objects_action($form_name);
    }
    //

    $_DATA["fields_form"][] = form_prepare_field( array("type"=>"string", "form_template"=>"hidden", "name"=>"form_name"), true, $form_name);

    // ACL
    $_DATA["fields_form"] = array_filter($_DATA["fields_form"], "check_form_field_acl");


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
		die("Code: ea-".__LINE__."-form_template".(DEV_MODE ? "-".$form_template : "") );
	}
}
function import_data_action(){
    global $_PARAMS;

    $repo_name = $_PARAMS["repo_name"];
    $tsv       = $_PARAMS["tsv"];

    $data = import_tsv_string($tsv);

    $repository = Repository::create($repo_name);
    $res = $repository->import($data);

    $redirect_uri = $repository->uri_prefix . $repository->repo_name;

    redirect($redirect_uri);

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

    redirect($redirect_uri ? $redirect_uri : "index");

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
    global $_URI;

    logout();
    redirect("form/login");
    return_url_clear();
    return_url_push($_URI);

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

function set_topmenu_action(){
    global $_DATA;

    $_DATA["topmenu"] = get_topmenu();

};
function show_data_action(){
    global $_PARAMS;
    global $_DATA;
    global $_PAGE;

    $repo_name = ! empty($_PARAMS["model"]) ? $_PARAMS["model"] : null;
    if (!$repo_name) {
        die("Code: ea-".__LINE__."-model");
    };

    $id    = ! empty($_PARAMS["id"]) ? (int) $_PARAMS["id"] : null;
    $mode  = $id ? "item" : "list";
    $obj_name = db_get_obj_name($repo_name);
    $form_name = $id ? "show_".$obj_name : "list_".$repo_name;

    dosyslog(__FUNCTION__.": DEBUG: Start getting data from DB.");
    $repository = Repository::create($repo_name, $form_name);
    if ($mode == "list"){ //
        // Фильтрация данных
        if (!empty($_DATA["show_data_where"])) $repository->where($_DATA["show_data_where"]);
        if (!empty($_DATA["show_data_uri_params"])){ // дополнительные URL параметры для Pager'а
          $uri_params = $_DATA["show_data_uri_params"];
        }else{
          $uri_params = array();
        }
        if (!empty($_DATA["show_data_url_template"])){ // дополнительные URL параметры для Pager'а
          $url_template = $_DATA["show_data_url_template"];
        }else{
          $url_template = "";
        }

        // Сортировка
        if (!empty($_PAGE["params"]["sort"])){ // sorting enabled
            if (strpos($_PARAMS["sort"], ":") !== false){
                list($sort_key, $sort_direction) = explode(":", $_PARAMS["sort"],2);
                $sort_direction = strtoupper($sort_direction) == "DESC" ? "DESC" : "ASC";
            }else{
                $sort_key = $_PARAMS["sort"];
                $sort_direction = "ASC";
            };

            if ($sort_key){
                $repository->orderBy(array($sort_key => $sort_direction));
                $uri_params["sort"] = implode(":", array($sort_key, $sort_direction));
            }
        };

        // Паджинация
        if (isset($_PAGE["params"]["page"])){ // pagination enabled
            $page = !empty($_PARAMS["page"]) ? $_PARAMS["page"] : 1;
            $items_per_page = db_get_meta($repo_name, "items_per_page") or $items_per_page = 20;
            $_DATA["pager"] = $repository->getPager($items_per_page, $page, $uri_params, $url_template);

            $repository->limit($items_per_page)->offset($items_per_page * ($page-1));
        };
        //

        $_DATA["items"] = $repository->fetchAll();

    }else{
        $_DATA["items"] = $repository->load($id)->fetchAll();
    };

    dosyslog(__FUNCTION__.": DEBUG: Got data from DB.");

    $_DATA["fields"] = array_filter(form_get_fields($repo_name, $form_name), "check_form_field_acl");


    dosyslog(__FUNCTION__.": DEBUG: Field filtered.");
    $_DATA["items"]  = array_filter($_DATA["items"], function($item) use ($repo_name){
            return check_data_item_acl($item, $repo_name);
    });

    dosyslog(__FUNCTION__.": DEBUG: Items ACL checked.");


    $_DATA["items"] = array_map(function($item) use ($mode){
        $view = View::getView($item);
        return $view->prepare($mode);
    },$_DATA["items"]);

    dosyslog(__FUNCTION__.": DEBUG: Data view prepared.");

    if (empty($_PAGE["title"])){
        $_PAGE["title"] = $_PAGE["header"] = db_get_meta($repo_name, "comment");
    };

    if ( empty($_PAGE["templates"]["content"]) && ! empty($_PAGE["templates"][$mode]) ){
        set_template_file("content", $_PAGE["templates"][$mode]);
    };

    $_DATA["model_name"] = $obj_name;
    $_DATA["form_name"]  = $form_name;
    $_DATA["repo_name"]  = $repo_name;

    dosyslog(__FUNCTION__.": DEBUG: Finished.");

}
function show_data_state_period_action()
{
    global $_PARAMS;
    global $_DATA;
    global $CFG;
    global $_PAGE;

    $repo_name = $_PARAMS["model"];

    $state      = isset($_PARAMS["state"]) ? $_PARAMS["state"] : null;
    $period = isset($_PARAMS["period"]) ? $_PARAMS["period"] : (is_null($state) ? date("Y-m") : null);
    $date_field = isset($_PARAMS["date_field"]) ? $_PARAMS["date_field"] : "created";

    $correct_displayed_period = false;
    if (! is_null($state) && is_null($period)) {
        $end_date = glog_isodate(db_get_max($repo_name, "created"));
        $start_date = glog_isodate(db_get_min($repo_name, "created"));
        $correct_displayed_period = true;
    } else {
        $start_date = month_start_date($period);
        $end_date   = month_end_date($period);
    }

    switch($date_field){
      case "date":
        $_DATA["show_data_where"] = "date >= '".$start_date."' AND date <= '".$end_date . "'" . ($state ? " AND lead_state = ".db_quote($state)  : "");
        break;
      case "created":
      default:
    $_DATA["show_data_where"] = "created >= ".strtotime($start_date)." AND created <= ".strtotime($end_date . " 23:59:59") . ($state ? " AND lead_state = ".db_quote($state) : "");
        break;
    };

    if ($period) $_DATA["show_data_uri_params"]["period"] = $period;
    if ($state)  $_DATA["show_data_uri_params"]["state"]  = $state;

    show_data_action();

    if ($_DATA["items"]){
        $_DATA["items"] = arr_index($_DATA["items"]);

        if ($correct_displayed_period) { // отображать период на основе выборки заявок, а не запроса.
            $ids = array_keys($_DATA["items"]);
            $start_date = glog_isodate($_DATA["items"][$ids[count($ids)-1]]["created"]);  // заявки отсортированы по дате по убыванию.
            $end_date   = glog_isodate($_DATA["items"][$ids[0]]["created"]);
        }
    }


    $_DATA["start_date"] = $start_date;
    $_DATA["end_date"]   = $end_date;




    // Навигация по месяцам
    if ($period){
        $_DATA["months_nav"] = $_DATA["nav"] = array(
            "current" => $period,
            "prev" => month_prev($period),
            "next" => month_next($period),
            "uri"  => db_get_meta($repo_name, "model_uri_prefix") . $repo_name . $CFG["URL"]["ext"],
        );
    };

    // Заголовок страницы
    $_PAGE["header"] = $_PAGE["title"] . ($state ? " в статусе '".get_value_caption("lead_state", $state) : "") . ($period ? " за ".month_name($period) : "");

    // Перезагрузка страницы не нужна
    $_DATA["auto_reload"] = false;
}
function show_login_form_action(){
    global $_DATA;
    global $CFG;

    $auth_types = get_auth_types();
    $_DATA["auth_type"] = isset($auth_types[0]) ? $auth_types[0] : "simple";

    // return_url_push($CFG["URL"]["dashboard"]);


    // There no users yet -- link to add first user
    if ( ! db_get_count("users") ){
        $_DATA["import_first_user"] = true;
    };

}
function show_signup_form_action(){
    global $_PARAMS;
    $db_tables = db_get_tables("user_applications");

    if ( ! in_array("user_applications", $db_tables) ){  // registration is not supported in app.
        dosyslog(__FUNCTION__.": FATAL ERROR: Attempt to load signup form!");
        die("<h3>" . _t("Registration closed.") . "</h3>");
    };

    $_PARAMS["action"] = "add";
    $_PARAMS["object"] = "user_application";
    $_PARAMS["form_name"] = "add_user_application";

    $is_public = true;

    form_action(true);

}

