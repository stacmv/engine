<?php
function login_auth_simple_action(){
    global $CFG;
    global $_USER;
    global $_DATA;
    
    $_DATA["auth_type"] = "simple";
    
    if (!empty($_POST["login"]) && ! empty($_POST["pass"])){
        $_SESSION["auth_simple"]["ident"] = $_POST["login"];
        $_SESSION["auth_simple"]["pass"] = $_POST["pass"];
    }else{
        unset($_SESSION["auth_simple"]);
    }
    
    if ( empty($_SESSION["auth_simple"]["ident"]) || empty($_SESSION["auth_simple"]["pass"]) ){
        dosyslog(__FUNCTION__.": NOTICE: Send authorization request to ". (! empty($_POST["login"]) ? $_POST["login"] : "visitor.") );
        return;
        // will be displayed signin form as defined in pages config file.
    } else {
    
        dosyslog(__FUNCTION__.": NOTICE: " .  (! empty($_POST["login"]) ? "User '" . $_POST["login"] . "'" : "visitor") . " entered credentials.");
    
        if ( $_USER["authenticated"] ){
            if ( ! empty($CFG["URL"]["dashboard"]) ){
                $redirect_uri = $CFG["URL"]["dashboard"];
            }else{
                $redirect_uri = "index";
            }
            redirect($redirect_uri);
        }else{
            redirect("signin");
        };
    };
}

function auth_simple_authenticate($password_field="pass"){
    global $_USER;
    
    
    $authenticated = false;
    
    if ( isset($_SESSION["auth_simple"]["ident"]) && isset($_SESSION["auth_simple"]["pass"]) ){
		if (!empty($_SESSION["auth_simple"]["user_id"])){
			
			if( ! empty($_USER["profile"]) && ! empty($_USER["profile"][$password_field]) ){
               
				if (
                    passwords_verify($_SESSION["auth_simple"]["pass"], $_USER["profile"][$password_field]) &&
                    ( $_USER["profile"]["login"] === $_SESSION["auth_simple"]["ident"])
                   )
                {
                    $authenticated = true;
                    dosyslog(__FUNCTION__.": NOTICE: User '".$_USER["profile"]["login"]."' authenticated.");
				}else{
					dosyslog(__FUNCTION__.": NOTICE: Wrong password for login '".@$_USER["profile"]["login"]."' entered. User id: ".$_USER["profile"]["id"]);
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
function auth_simple_identicate(){
    global $CFG;
    
    $identicated = false;
    $user_id = null;
    
    if ( ! empty($_SESSION["auth_simple"]["ident"])){
        $identicated = true;
        dosyslog(__FUNCTION__.": DEBUG: User: ".$_SESSION["auth_simple"]["ident"].". Identicated = true.");
    };
    
    if ( $identicated ){
        
        $user_id = db_find("users","login", $_SESSION["auth_simple"]["ident"], DB_RETURN_ID | DB_RETURN_ONE);
                
        if ($user_id){
            $_SESSION["auth_simple"]["user_id"] = $user_id;
        }else{
            $_SESSION["auth_simple"]["user_id"] = NULL;
        }
    };
    
    return $user_id;
}
function auth_simple_login(){
    auth_simple_set_auth_type();
    return "login_auth_simple";
}
function auth_simple_logout(){
    
    unset($_SESSION["auth_simple"]);
    
    dosyslog(__FUNCTION__.": NOTICE: User logged out.");
    
}
function auth_simple_set_auth_type(){
    $auth_type = "simple";
    dosyslog(__FUNCTION__.": DEBUG: Auth_type set to '".$auth_type."'.");
    return $_SESSION["auth_type"] = $auth_type;
}