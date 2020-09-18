<?php
function login_telegram_action(){
    global $CFG;
    global $_PARAMS;
    global $_DATA;

    $uid = $_PARAMS["username"] ?? null;
    $hash = $_PARAMS["hash"] ?? null;

    // identicate
    $user = EUsers::find_one("telegram","@" . $uid);

    // Check signature
    $token = $CFG["TELEGRAM"]["bot_token"];
    $keys = array("id", "first_name", "last_name", "username", "photo_url", "auth_date");

    $auth_data = $_PARAMS;
    $data = [];
    if (isset($auth_data['hash'])) {
        foreach ($auth_data as $key => $value) {
            if (in_array($key, $keys)) {
                $data[] = $key."=".$value;
            }
        }
        sort($data);
        $data = implode("\n", $data);
        $secretKey = hash('sha256', $token, true);
        $hash = hash_hmac('sha256', $data, $secretKey);
    }


    if ($user){
        // authenticate
        $authenticated = hash_equals($hash, $auth_data['hash']);
        if ( ! $authenticated){
            dosyslog(__FUNCTION__.": ERROR: Provided wrong hash '".$hash."' for user with uid '".$uid."'. Hashed string: '".$hashed_str."'.");
        }
    }else{
        dosyslog(__FUNCTION__.": ERROR: User with uid '".$uid."' is not exists.");
    }

    if ($user && $authenticated){
        // log in
        session_regenerate_id();
        $_SESSION["authenticated"] = $user["id"];
        dosyslog(__FUNCTION__.": INFO: User with login '".$user["login"]."' is logged in via Telegram.");
    }else{
        set_session_msg("login_login_fail","error");
    }

    $redirect_uri = return_url_pop();

    if (! $redirect_uri && ! empty($CFG["URL"]["dashboard"]) ){
        $redirect_uri = $CFG["URL"]["dashboard"];
    };

    $_DATA = ['redirect' => ($redirect_uri ? $redirect_uri : "index") . $CFG["URL"]["ext"]];
}

function auth_telegram_action(){
    global $CFG;
    global $_USER;
    global $_PARAMS;

    $login =      ! empty($_PARAMS["login"])      ? $_PARAMS["login"]       : null;
    $pass =       ! empty($_PARAMS["pass"])       ? $_PARAMS["pass"]        : null;
    $return_url = ! empty($_PARAMS["return_url"]) ? $_PARAMS["return_url"]  : null;


    if ( ! empty($login) && ! empty($pass) ){
        $_SESSION["auth_telegram"]["ident"] = $login;
        $_SESSION["auth_telegram"]["pass"] = $pass;

        dosyslog(__FUNCTION__.": NOTICE: " .  (! empty($login) ? "User '" . $login . "'" : "visitor") . " entered credentials.");

    }else{
        unset($_SESSION["auth_telegram"]);
        unset($_SESSION["telegram"]);
    }


    if ( $return_url ){
        redirect($return_url);
    }else{
        if ( ! empty($CFG["URL"]["dashboard"]) ){
            $redirect_uri = $CFG["URL"]["dashboard"];
        }else{
            $redirect_uri = "index";
        }
            redirect($redirect_uri);
    };
}
function login_auth_telegram_action(){
    global $_URI;
    global $_DATA;
    global $_PAGE;

    dump($_PAGE);
    die();


}

function auth_telegram_authenticate($password_field="pass"){
    global $_USER;


    $authenticated = false;

    if ( isset($_SESSION["auth_telegram"]["ident"]) && isset($_SESSION["auth_telegram"]["pass"]) ){
		if (!empty($_SESSION["auth_telegram"]["user_id"])){

			if( ! empty($_USER["profile"]) && ! empty($_USER["profile"][$password_field]) ){

				if (
                    passwords_verify($_SESSION["auth_telegram"]["pass"], $_USER["profile"][$password_field]) &&
                    ( $_USER["profile"]["login"] === $_SESSION["auth_telegram"]["ident"])
                   )
                {
                    $authenticated = true;
                    dosyslog(__FUNCTION__.": NOTICE: User '".$_USER["profile"]["login"]."' authenticated.");
				}else{
					dosyslog(__FUNCTION__.": NOTICE: Wrong password for login '".(!empty($_USER["profile"]["login"]) ? $_USER["profile"]["login"] :"_unlnown_")."' entered. User id: ".$_USER["profile"]["id"]);
				};
			}else{
				dosyslog(__FUNCTION__.": ERROR: Can not get user profile for id '".$_USER["profile"]["id"]."'. User fallback to guest.");
			};
		}else{
            dosyslog(__FUNCTION__.": NOTICE: Visitor is guest.");
		};
	}else{
        dosyslog(__FUNCTION__.": NOTICE: Visitor does not enter pass.");
	};

    return $authenticated ? time() : false;
};
function auth_telegram_identicate(){
    global $CFG;

    $identicated = false;
    $user_id = null;

    if ( ! empty($_SESSION["auth_telegram"]["ident"])){
        $identicated = true;
        dosyslog(__FUNCTION__.": DEBUG: User: ".$_SESSION["auth_telegram"]["ident"].". Identicated = true.");
    };

    if ( $identicated ){

        $user_id = db_find("users","login", $_SESSION["auth_telegram"]["ident"], DB_RETURN_ID | DB_RETURN_ONE);

        if ($user_id){
            $_SESSION["auth_telegram"]["user_id"] = $user_id;
        }else{
            $_SESSION["auth_telegram"]["user_id"] = NULL;
        }
    };

    return $user_id;
}
function auth_telegram_login(){
    auth_telegram_set_auth_type();
    return "login_auth_telegram";
}
function auth_telegram_logout(){

    unset($_SESSION["auth_telegram"]);
    unset($_SESSION["telegram"]);

    dosyslog(__FUNCTION__.": NOTICE: User logged out.");

}
function auth_telegram_set_auth_type(){
    $auth_type = "telegram";
    dosyslog(__FUNCTION__.": DEBUG: Auth_type set to '".$auth_type."'.");
    return $_SESSION["auth_type"] = $auth_type;
}
