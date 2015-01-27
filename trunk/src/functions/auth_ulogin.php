<?php
function ulogin_action(){
    global $_PARAMS;
    global $CFG;
    
    $token = ! empty($_PARAMS["token"])  ? $_PARAMS["token"] : null;
    
     
    if ( $token ){
    
        $s = glog_http_get("http://ulogin.ru/token.php?token=".$token."&host=".$_SERVER["HTTP_HOST"], false);
        
        $ulogin_user = json_decode($s, true);
        
        if ($ulogin_user){
            
            $_SESSION["ulogin"]["user"] = $ulogin_user;
            
            if (strpos($_SERVER["HTTP_REFERER"], "form/edit/profile") > 0 ){ // привязка аккаунта соцсети - вернуться в профиль на вкладку settings
                $redirect_uri = substr(stristr($_SERVER["HTTP_REFERER"], "form/edit/profile"), 0, -1*strlen($CFG["URL"]["ext"]));

                dosyslog(__FUNCTION__.": INFO: User logged in as '".$ulogin_user["uid"]."' via '".$ulogin_user["network"]."' in order to link profiles.");
                
                if ( auth_ulogin_link_profile() ){
                    redirect( $redirect_uri, array(), "settings");
                }else{
                    redirect("index"); 
                }
                
                
            }else{  // вход через соцсеть
                redirect( ! empty($CFG["URL"]["dashboard"]) ? $CFG["URL"]["dashboard"] : "index" );
                dosyslog(__FUNCTION__.": INFO: User logged in as '".$ulogin_user["uid"]."' via '".$ulogin_user["network"]."'.");
            };
            
        }else{
            dosyslog(__FUNCTION__.": ERROR: Could not get or decode data from Ulogin.");
            set_session_msg("ulogin_fail","fail");
            redirect("index");
        }
    
    }else{
        dosyslog(__FUNCTION__.": ERROR: Mandatory parameter 'token' is not set.");
        redirect("index"); 
    }    
    
    auth_ulogin_set_auth_type();
    // dump($_SESSION);die(__FUNCTION__);
}
function ulogin_reload_action(){
    redirect($_SERVER["HTTP_REFERER"]);
};

function auth_ulogin_authenticate(){
    global $_USER;
    
    if (empty($_SESSION["ulogin"]["user"])) return false;
    
    $ulogin_data = db_find("users.ulogin", "identity", $_SESSION["ulogin"]["user"]["identity"], DB_RETURN_ROW | DB_RETURN_ONE);
        
    $authenticated = ($ulogin_data["user_id"] == $_USER["profile"]["id"]);
    
    if ($authenticated){
        dosyslog(__FUNCTION__.": Notice: User '" . $_USER["profile"]["login"]."' (user_id:".$_USER["profile"]["id"].") authenticated via identity '", $_SESSION["ulogin"]["user"]["identity"] ."'.");
    }else{
        dosyslog(__FUNCTION__.": WARNING: User '" . $_USER["profile"]["login"]."' (user_id:".$_USER["profile"]["id"].") IS NOT authenticated via identity '", $_SESSION["ulogin"]["user"]["identity"] . " profile is not linked.");
    };
    
    return $authenticated ? time() : false;
}
function auth_ulogin_identicate(){
    
    $user_id = null;
    $ulogin_user = isset($_SESSION["ulogin"]["user"]) ? $_SESSION["ulogin"]["user"] : null;
    
    if ( empty($_SESSION["ulogin"]["user"]) || ! isset($ulogin_user["identity"]) ){
        dosyslog(__FUNCTION__.": NOTICE: User is NOT identicated.");
        return null;
    }; 
    
    $ulogin_data = db_find("users.ulogin", "identity", $ulogin_user["identity"], DB_RETURN_ROW | DB_RETURN_ONE);
    if ($ulogin_data){
        $user_id = $ulogin_data["user_id"];
        dosyslog(__FUNCTION__.": NOTICE: User identicated as user_id:".$user_id." by identity '".$ulogin_user["identity"]."'.");
    };
       
    return $user_id;
    
};
function auth_ulogin_link_profile(){
    
    $res = false;
    if (empty($_SESSION["http_basic"]["authenticated"])){
        dosyslog(__FUNCTION__.": NOTICE: User is not authenticated. Could not link ".$ulogin_user["network"] . " profile '" . $ulogin_user["identity"]."'.");
    }elseif (empty($_SESSION["ulogin"]["user"])){
        dosyslog(__FUNCTION__.": NOTICE: User is not authenticated. Could not link ".$ulogin_user["network"] . " profile '" . $ulogin_user["identity"]."'.");
    }else{
    
        $ulogin_user = $_SESSION["ulogin"]["user"];
        $user_id = $_SESSION["http_basic"]["user_id"];
        $user = db_get("users",$user_id);
        dosyslog(__FUNCTION__.": NOTICE: User identicated as user_id:".$user_id." by current http_basic authentification.");
        
        // добавить привязку социального профиля
        $data = array("to" => $ulogin_user);
        $data["to"]["user_id"] = $user_id;
        dosyslog(__FUNCTION__.": NOTICE: Linking ".$ulogin_user["network"] . " profile '" . $ulogin_user["identity"] . "' to user_id:" . $user_id . ".");
        list($res, $added_id) = add_data("users.ulogin", $data);
        $reason = is_numeric($added_id) ? "success" : "fail";
        
        
        set_session_msg("users.ulogin_link_profile_".$reason, $reason);
    };
        
    return $res;
}
function auth_ulogin_login(){
    
    auth_ulogin_set_auth_type();    
    return "ulogin_reload";
}
function auth_ulogin_logout(){
 
    if (isset($_SESSION["ulogin"])) unset($_SESSION["ulogin"]);
    dosyslog(__FUNCTION__.": NOTICE: User logged out.");
    
}
function auth_ulogin_set_auth_type(){
    $auth_type = "ulogin";
    dosyslog(__FUNCTION__.": DEBUG: Auth_type set to '".$auth_type."'.");
    return $_SESSION["auth_type"] = $auth_type;
}

/**
* @param string $first_name
* @param string $last_name
* @param string $nickname
* @param string $bdate (string in format: dd.mm.yyyy)
* @param array $delimiters
* @return string
*/
function auth_ulogin_generateNickname($first_name, $last_name="", $nickname="", $bdate="", $delimiters=array('.', '_')) {
    $delim = array_shift($delimiters);

    $first_name = auth_ulogin_translitIt($first_name);
    $first_name_s = substr($first_name, 0, 1);

    $variants = array();
    if (!empty($nickname))
        $variants[] = $nickname;
    $variants[] = $first_name;
    if (!empty($last_name)) {
        $last_name = auth_ulogin_translitIt($last_name);
        $variants[] = $first_name.$delim.$last_name;
        $variants[] = $last_name.$delim.$first_name;
        $variants[] = $first_name_s.$delim.$last_name;
        $variants[] = $first_name_s.$last_name;
        $variants[] = $last_name.$delim.$first_name_s;
        $variants[] = $last_name.$first_name_s;
    }
    if (!empty($bdate)) {
        $date = explode('.', $bdate);
        $variants[] = $first_name.$date[2];
        $variants[] = $first_name.$delim.$date[2];
        $variants[] = $first_name.$date[0].$date[1];
        $variants[] = $first_name.$delim.$date[0].$date[1];
        $variants[] = $first_name.$delim.$last_name.$date[2];
        $variants[] = $first_name.$delim.$last_name.$delim.$date[2];
        $variants[] = $first_name.$delim.$last_name.$date[0].$date[1];
        $variants[] = $first_name.$delim.$last_name.$delim.$date[0].$date[1];
        $variants[] = $last_name.$delim.$first_name.$date[2];
        $variants[] = $last_name.$delim.$first_name.$delim.$date[2];
        $variants[] = $last_name.$delim.$first_name.$date[0].$date[1];
        $variants[] = $last_name.$delim.$first_name.$delim.$date[0].$date[1];
        $variants[] = $first_name_s.$delim.$last_name.$date[2];
        $variants[] = $first_name_s.$delim.$last_name.$delim.$date[2];
        $variants[] = $first_name_s.$delim.$last_name.$date[0].$date[1];
        $variants[] = $first_name_s.$delim.$last_name.$delim.$date[0].$date[1];
        $variants[] = $last_name.$delim.$first_name_s.$date[2];
        $variants[] = $last_name.$delim.$first_name_s.$delim.$date[2];
        $variants[] = $last_name.$delim.$first_name_s.$date[0].$date[1];
        $variants[] = $last_name.$delim.$first_name_s.$delim.$date[0].$date[1];
        $variants[] = $first_name_s.$last_name.$date[2];
        $variants[] = $first_name_s.$last_name.$delim.$date[2];
        $variants[] = $first_name_s.$last_name.$date[0].$date[1];
        $variants[] = $first_name_s.$last_name.$delim.$date[0].$date[1];
        $variants[] = $last_name.$first_name_s.$date[2];
        $variants[] = $last_name.$first_name_s.$delim.$date[2];
        $variants[] = $last_name.$first_name_s.$date[0].$date[1];
        $variants[] = $last_name.$first_name_s.$delim.$date[0].$date[1];
    }
    $i=0;

    $exist = true;
    while (true) {
        if ($exist = auth_ulogin_userExist($variants[$i])) {
            foreach ($delimiters as $del) {
                $replaced = str_replace($delim, $del, $variants[$i]);
                if($replaced !== $variants[$i]){
                    $variants[$i] = $replaced;
                    if(!$exist = auth_ulogin_userExist($variants[$i])){
                        break;
                    }
                }
            }
        }
        if ($i >= count($variants)-1 || !$exist)
            break;
        $i++;
    }

    if ($exist) {
        while ($exist) {
            $nickname = $first_name.mt_rand(1, 100000);
            $exist = auth_ulogin_userExist($nickname);
        }
        return $nickname;
    } else
        return $variants[$i];
}
function auth_ulogin_translitIt($str) {
    $tr = array(
        "А"=>"a","Б"=>"b","В"=>"v","Г"=>"g",
        "Д"=>"d","Е"=>"e","Ж"=>"j","З"=>"z","И"=>"i",
        "Й"=>"y","К"=>"k","Л"=>"l","М"=>"m","Н"=>"n",
        "О"=>"o","П"=>"p","Р"=>"r","С"=>"s","Т"=>"t",
        "У"=>"u","Ф"=>"f","Х"=>"h","Ц"=>"ts","Ч"=>"ch",
        "Ш"=>"sh","Щ"=>"sch","Ъ"=>"","Ы"=>"yi","Ь"=>"",
        "Э"=>"e","Ю"=>"yu","Я"=>"ya","а"=>"a","б"=>"b",
        "в"=>"v","г"=>"g","д"=>"d","е"=>"e","ж"=>"j",
        "з"=>"z","и"=>"i","й"=>"y","к"=>"k","л"=>"l",
        "м"=>"m","н"=>"n","о"=>"o","п"=>"p","р"=>"r",
        "с"=>"s","т"=>"t","у"=>"u","ф"=>"f","х"=>"h",
        "ц"=>"ts","ч"=>"ch","ш"=>"sh","щ"=>"sch","ъ"=>"y",
        "ы"=>"y","ь"=>"","э"=>"e","ю"=>"yu","я"=>"ya"
    );
    if (preg_match('/[^A-Za-z0-9\_\-]/', $str)) {
        $str = strtr($str,$tr);
        $str = preg_replace('/[^A-Za-z0-9\_\-\.]/', '', $str);
    }
    return $str;
}
function auth_ulogin_userExist($login){
    $logins = array_map( function($user){
        return $user["login"];
    }, get_users_list() );
    
    return in_array($login, $logins);
}
