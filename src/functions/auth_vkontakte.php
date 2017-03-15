<?php
function auth_vkontakte_action(){
    global $CFG;
    global $_USER;
    global $_PARAMS;
    
    $login =      ! empty($_PARAMS["login"])      ? $_PARAMS["login"]       : null;
    $pass =       ! empty($_PARAMS["pass"])       ? $_PARAMS["pass"]        : null;
    $return_url = ! empty($_PARAMS["return_url"]) ? $_PARAMS["return_url"]  : null;
        
    
    if ( ! empty($login) && ! empty($pass) ){
        $_SESSION["auth_vkontakte"]["ident"] = $login;
        $_SESSION["auth_vkontakte"]["pass"] = $pass;
        
        dosyslog(__FUNCTION__.": NOTICE: " .  (! empty($login) ? "User '" . $login . "'" : "visitor") . " entered credentials.");
        
    }else{
        unset($_SESSION["auth_vkontakte"]);
        unset($_SESSION["vkontakte"]);
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
function login_auth_vkontakte_action(){
    global $_URI;
    global $_DATA;
    global $_PAGE;
    
    dump($_PAGE);
    die();
    
    
}

function auth_vkontakte_authenticate($password_field="pass"){
    global $_USER;
    
    
    $authenticated = false;
    
    if ( isset($_SESSION["auth_vkontakte"]["ident"]) && isset($_SESSION["auth_vkontakte"]["pass"]) ){
		if (!empty($_SESSION["auth_vkontakte"]["user_id"])){
			
			if( ! empty($_USER["profile"]) && ! empty($_USER["profile"][$password_field]) ){
               
				if (
                    passwords_verify($_SESSION["auth_vkontakte"]["pass"], $_USER["profile"][$password_field]) &&
                    ( $_USER["profile"]["login"] === $_SESSION["auth_vkontakte"]["ident"])
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
function auth_vkontakte_identicate(){
    global $CFG;
    
    $identicated = false;
    $user_id = null;
    
    if ( ! empty($_SESSION["auth_vkontakte"]["ident"])){
        $identicated = true;
        dosyslog(__FUNCTION__.": DEBUG: User: ".$_SESSION["auth_vkontakte"]["ident"].". Identicated = true.");
    };
    
    if ( $identicated ){
        
        $user_id = db_find("users","login", $_SESSION["auth_vkontakte"]["ident"], DB_RETURN_ID | DB_RETURN_ONE);
                
        if ($user_id){
            $_SESSION["auth_vkontakte"]["user_id"] = $user_id;
        }else{
            $_SESSION["auth_vkontakte"]["user_id"] = NULL;
        }
    };
    
    return $user_id;
}
function auth_vkontakte_login(){
    auth_vkontakte_set_auth_type();
    return "login_auth_vkontakte";
}
function auth_vkontakte_logout(){
    
    unset($_SESSION["auth_vkontakte"]);
    unset($_SESSION["vkontakte"]);
    
    dosyslog(__FUNCTION__.": NOTICE: User logged out.");
    
}
function auth_vkontakte_set_auth_type(){
    $auth_type = "vkontakte";
    dosyslog(__FUNCTION__.": DEBUG: Auth_type set to '".$auth_type."'.");
    return $_SESSION["auth_type"] = $auth_type;
}