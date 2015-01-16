<?php
function auth_http_basic_identicate(){
    global $CFG;
    global $_RESPONSE;
    
    $identicated = false;
    $user_id = null;
    
    $guessed_user = ! empty($_SERVER["PHP_AUTH_USER"]) ? $_SERVER["PHP_AUTH_USER"] : "guest";
    
    if ( isset($_SERVER["PHP_AUTH_USER"]) ) {
        if ( ! isset($_SESSION["auth"]) && ! isset($_SESSION["auth_http_basic__logged_out"]) ){
            $_SESSION["auth"] = array(
                "auth_type" => "http_basic",
                "ident"     => $guessed_user,
            );
        };
        
        if (
            isset($_SESSION["auth"]) && ($_SESSION["auth"]["auth_type"]) &&
            ! empty($_SESSION["auth"]["ident"]) &&
            ($_SERVER["PHP_AUTH_USER"] == $_SESSION["auth"]["ident"])
           )
        {
            $identicated = true;
        };
    };
    
    if ( ! $identicated ){
        dosyslog(__FUNCTION__.": NOTICE: Send authorization request to ". $guessed_user );
        
        $headers["WWW-Authenticate"] = 'Basic realm="' . ucfirst($CFG["GENERAL"]["codename"]) . ' - ' . date("M Y") . '"';
        $headers["HTTP"] = "HTTP/1.0 401 Unauthorized";
        $_RESPONSE["headers"] = $headers;
        unset($_SESSION["auth"]);
        unset($_SESSION["auth_http_basic__logged_out"]);
        SENDHEADERS();
        if ( file_exists(TEMPLATES_DIR . "not_logged.htm") ){
            include(TEMPLATES_DIR . "not_logged.htm");
        };
        exit;
    }else{
        
        $user_ids = db_find("users","login", $guessed_user);
        
        if ($user_ids && ! empty($user_ids[0])) $user_id = $user_ids[0];
        else $user_id = null;
        
        if ($user_id){
            $_SESSION["auth"] = array(
                "auth_type" => "http_basic",
                "user_id"   => $user_id,
                "ident"     => $guessed_user,
            );
            if (isset($_SESSION["auth_http_basic__logged_out"])) unset($_SESSION["auth_http_basic__logged_out"]);
        }else{
            if (isset($_SESSION["auth"])) unset($_SESSION["auth"]);
        };
    };
    
    return $user_id;
}
function auth_http_basic_authenticate($password_field="pass"){
    global $_USER;
    
    
    $authenticated = false;
    
    if ( isset($_SERVER["PHP_AUTH_PW"]) ){
		if ($_USER["identicated"]){
			
			if( ! empty($_USER["profile"]) && ! empty($_USER["profile"][$password_field]) ){
               
				if (
                    passwords_verify($_SERVER["PHP_AUTH_PW"], $_USER["profile"][$password_field]) &&
                    ( $_SESSION["auth"]["ident"] === $_SERVER["PHP_AUTH_USER"] ) &&
                    ( $_SESSION["auth"]["ident"] === $_USER["profile"]["login"] )
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
    
    return $authenticated;
};
function auth_http_basic_logout(){
    
    $_SESSION["auth_http_basic__logged_out"] = true;
    dosyslog(__FUNCTION__.": NOTICE: User logged out.");
    
}
