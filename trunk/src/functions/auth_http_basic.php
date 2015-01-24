<?php
function login_http_basic_action(){
    global $CFG;
    global $_RESPONSE;
    
    if (!empty($_SERVER["PHP_AUTH_USER"]) && ! empty($_SERVER["PHP_AUTH_USER"])){
        $_SESSION["http_basic"]["ident"] = $_SERVER["PHP_AUTH_USER"];
        $_SESSION["http_basic"]["pass"] = $_SERVER["PHP_AUTH_PW"];
    };
    
    if ( empty($_SESSION["http_basic"]["ident"]) || empty($_SESSION["http_basic"]["pass"]) || ! empty($_SESSION["http_basic"]["logged_out"])){
        dosyslog(__FUNCTION__.": NOTICE: Send authorization request to ". (! empty($_SERVER["PHP_AUTH_USER"]) ? $_SERVER["PHP_AUTH_USER"] : "visitor.") );
        
        $headers["WWW-Authenticate"] = 'Basic realm="' . ucfirst($CFG["GENERAL"]["codename"]) . ' - ' . date("M Y") . '"';
        $headers["HTTP"] = "HTTP/1.0 401 Unauthorized";
        $_RESPONSE["headers"] = $headers;
        if (isset($_SESSION["http_basic"]["logged_out"])) unset($_SESSION["http_basic"]["logged_out"]);
        SENDHEADERS();
        if ( file_exists(TEMPLATES_DIR . "not_logged_http_basic.htm") ){
            include(TEMPLATES_DIR . "not_logged_http_basic.htm");
        };
        exit;
    }
    
    
    if(isset($_SESSION["http_basic"]["logged_out"])) unset($_SESSION["http_basic"]["logged_out"]);
    
    dosyslog(__FUNCTION__.": NOTICE: " .  (! empty($_SERVER["PHP_AUTH_USER"]) ? "User '" . $_SERVER["PHP_AUTH_USER"] . "'" : "visitor") . " logged in.");
    
    if ( ! empty($CFG["URL"]["dashboard"]) ){
        $redirect_uri = $CFG["URL"]["dashboard"];
    }else{
        $redirect_uri = "index";
    }

    redirect($redirect_uri);
}

function auth_http_basic_authenticate($password_field="pass"){
    global $_USER;
    
    
    $authenticated = false;
    
    if ( isset($_SESSION["http_basic"]["ident"]) && isset($_SESSION["http_basic"]["pass"]) ){
		if (!empty($_SESSION["http_basic"]["user_id"])){
			
			if( ! empty($_USER["profile"]) && ! empty($_USER["profile"][$password_field]) ){
               
				if (
                    passwords_verify($_SESSION["http_basic"]["pass"], $_USER["profile"][$password_field]) &&
                    ( $_USER["profile"]["login"] === $_SESSION["http_basic"]["ident"])
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
function auth_http_basic_identicate(){
    global $CFG;
    
    $identicated = false;
    $user_id = null;
    
    if ( ! empty($_SESSION["http_basic"]["ident"])){
        $identicated = true;
        dosyslog(__FUNCTION__.": DEBUG: User: ".$_SESSION["http_basic"]["ident"].". Identicated = true.");
    };

    
    if (!empty($_SESSION["http_basic"]["logged_out"])){
        $identicated = false;
        dosyslog(__FUNCTION__.": DEBUG: [http_basic][logged_out]. Identicated = false.");
    };
        
    
    if ( $identicated ){
        
        $user_id = db_find("users","login", $_SERVER["PHP_AUTH_USER"], DB_RETURN_ID | DB_RETURN_ONE);
                
        if ($user_id){
            if (isset($_SESSION["http_basic"]["logged_out"])) unset($_SESSION["http_basic"]["logged_out"]);
            $_SESSION["http_basic"]["user_id"] = $user_id;
        };
    };
    
    return $user_id;
}
function auth_http_basic_login(){
    auth_http_basic_set_auth_type();
    return "login_http_basic";
}
function auth_http_basic_logout(){
    
    unset($_SESSION["http_basic"]);
    $_SESSION["http_basic"]["logged_out"] = true;
    
    dosyslog(__FUNCTION__.": NOTICE: User logged out.");
    
}
function auth_http_basic_set_auth_type(){
    $auth_type = "http_basic";
    dosyslog(__FUNCTION__.": DEBUG: Auth_type set to '".$auth_type."'.");
    return $_SESSION["auth_type"] = $auth_type;
}