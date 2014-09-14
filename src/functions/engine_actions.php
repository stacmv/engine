<?php
function do_nothing_action(){
    // do nothing
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

