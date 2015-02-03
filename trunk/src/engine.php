<?php
require ENGINE_DIR . "settings/checks.php";
require ENGINE_DIR . "settings/version.php";

// controller
function APPLYPAGETEMPLATE(){
    global $_RESPONSE;
    global $_PAGE;
    global $CFG;
    global $IS_IFRAME_MODE;
    
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");

    if (empty($_PAGE["title"])) $_PAGE["title"] = $CFG["GENERAL"]["app_name"];
    
    if (! empty($_PAGE["templates"]["page"]) ){
        if (empty($_PAGE["templates"]["content"]) ) set_template_for_user();
        if ($IS_IFRAME_MODE){
            if ( ! empty($_PAGE["templates"]["iframe"]) ){
                $_RESPONSE["body"] = get_content("iframe");
            }else{
                dosyslog(__FUNCTION__.": ERROR: Could not find template 'iframe' for page '".$_PAGE["uri"]."' requested in iframe_mode.");
                $_RESPONSE["body"] = get_content("page");
            }
        }else{
            $_RESPONSE["body"] = get_content("page");
        };
    }else{
        die("Code: e-".__LINE__."-page_tmpl");
    };
    
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
};
function AUTENTICATE(){
    global $_USER;
    
    $_USER["authenticated"]  = false;
        
    if ( !empty($_USER["profile"]["id"]) ) {

        $auth_types = get_auth_types();
        
        foreach($auth_types as $auth_type){
            if ( ! isset($_SESSION[$auth_type]["user_id"]) ) continue;
        
            if ( empty($_SESSION[$auth_type]["authenticated"]) ) {
       
                $authenticate_function = "auth_" . $auth_type . "_authenticate";
                if ( function_exists($authenticate_function) ){
                    $_SESSION[$auth_type]["authenticated"] = call_user_func($authenticate_function);
                    if ($_SESSION[$auth_type]["authenticated"]){
                        $_USER["authenticated"] = time();
                    }
                }else{
                    dosyslog(__FUNCTION__.": FATAL ERROR: Function '".$authenticate_function." is not defined.");
                    die("Code: e-".__LINE__."-authenticate");
                };
            }else{
                $_USER["authenticated"] = time();
            }
            
            dosyslog(__FUNCTION__.": INFO: User '".$_USER["profile"]["login"]." (user_id:".$_USER["profile"]["id"].") athenticated via '".$auth_type."' since ".date("c",$_SESSION[$auth_type]["authenticated"]).".");
        };
            
    }else{
        $_USER["authenticated"] = false;
        $_SESSION["authenticated"] = false;
    };
           
    if ($_USER["authenticated"]){
        dosyslog(__FUNCTION__.": INFO: User authenticated.");
    }else{
        dosyslog(__FUNCTION__.": INFO: User NOT authenticated.");
    };
    
	return $_USER;
    
};
function AUTHORIZE(){
    global $_PAGE;
    global $_USER;
	  
    if ( empty($_PAGE["acl"]) ){
        $authorized = true;
    }else{
        
        if ( ! $_USER["authenticated"]){
            $authorized = false;
        }else{
            $authorized = true;
            foreach($_PAGE["acl"] as $right){
                if ( ! userHasRight( $right ) ){
                    dosyslog(__FUNCTION__.": User '".$_USER["profile"]["login"]."' has not right '" . $right. "' for page '" . $_PAGE["uri"]."'.");
                    $authorized = false;
                    break;
                };
            };
        };
    };
    
    if ( ! $authorized) {
        if ( ! empty($_USER["profile"])){
            dosyslog(__FUNCTION__.": WARNING: User '".$_USER["profile"]["login"]."' (user_id:".$_USER["profile"]["id"].") not authorized for page '" . $_PAGE["uri"]."'.");
        }else{
            dosyslog(__FUNCTION__.": WARNING: User not authorized for page '" . $_PAGE["uri"]."'.");
        };
        
        // ///////////////////////////
        if ($_USER["authenticated"]) $_PAGE["actions"] = array("NOT_AUTH");
        else $_PAGE["actions"] = array("NOT_LOGGED");
        // ///////////////////////////
        
    }else{
        if ( ! empty($_USER["profile"])){
            dosyslog(__FUNCTION__.": INFO: User '".$_USER["profile"]["login"]."' (user_id:".$_USER["profile"]["id"].") authorized for page '" . $_PAGE["uri"]."'.");
        }else{
            dosyslog(__FUNCTION__.": INFO: User authorized for page '" . $_PAGE["uri"]."'.");
        };
    }
    
   return $_USER;
   
};
function DOACTION(){
    global $_ACTIONS;
    global $_PAGE;

    //if (TEST_MODE) echo "<br>\n SETACTION done.";
    
    $action = array_shift($_ACTIONS);

    dosyslog(__FUNCTION__.": NOTICE: Action: ".$action);
  
    $function = $action . "_action";
    
    if ( function_exists($function) ){
        $tmp = call_user_func($function);
    }else{  
        dosyslog(__FUNCTION__.": FATAL ERROR: Function '".$function."' is not defined.  URI: '".$_PAGE["uri"]."'.");
        die("Code: e-".__LINE__."-".$function);
    };
    
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
};
function IDENTICATE(){
	global $_USER;
    global $_PAGE;
    global $_URI;
    global $CFG;
    global $_RESPONSE;
    
    $_USER = array();
    
    $preffered_auth_type_cookie_name   = "pat";
    $preffered_auth_type_cookie_period = 7*24*60*60; // in seconds
    
    
    $auth_types = get_auth_types();
    
    $preffered_auth_type = "http_basic";
    if ( ! empty($_COOKIE[ $preffered_auth_type_cookie_name ]) ){
        $preffered_auth_type = $_COOKIE[ $preffered_auth_type_cookie_name ];
    };
    if ( ! empty($_SESSION["auth_type"]) ){
        $preffered_auth_type = $_SESSION["auth_type"];
    };
    
    $ids =array();
    foreach($auth_types as $auth_type){
        $identicate_function = "auth_".$auth_type."_identicate";
        if (function_exists($identicate_function)){
            dosyslog(__FUNCTION__.": NOTICE: Identication procedure initiated. Auth_type: ".$auth_type);
            $_SESSION[$auth_type]["user_id"] = call_user_func($identicate_function); 
            if (! empty($_SESSION[$auth_type]["user_id"])){
                $ids[] = $_SESSION[$auth_type]["user_id"];
                dosyslog(__FUNCTION__.": INFO: Identicated user_id:".$_SESSION[$auth_type]["user_id"]." via ".$auth_type.".");
            };
        }else{
            dosyslog(__FUNCTION__.": FATAL ERROR: Identicate function '".$identicate_function."' is not defined.");
            die("Code: e-".__LINE__."-".$identicate_function);
        };
    };
    
    $ids = array_unique($ids);
    
    if (count($ids)>1){
        set_session_msg("Multiple logins are not supported.");
        dosyslog(__FUNCTION__.": ERROR: Multiple logins are not supported. Ids:'".json_encode($ids)."'.");
        $ids = array();
    };
    
    $user_id = !empty($ids[0]) ? $ids[0] : null;
        
    if ( $user_id ){   // пользователь прошел процедуру логина
        $user = db_get("users", $user_id);
        if ($user){
            $_USER["profile"] = $user;
            dosyslog(__FUNCTION__.": INFO: User identicated as '".$user["login"]."' (id:".$user["id"].").");
        }else{
            dosyslog(__FUNCTION__.": ERROR: User not identicated. User id '".$user_id."' not found.");
            $user_id = null;
        }
    }else{  // пользователь не прошел процедуру логина
    
        if ( ! empty($_PAGE["acl"]) ){  // страница с контролем доступа, требуется идентификация

            
            
            
            $login_function = "auth_".$preffered_auth_type."_login";
            if (function_exists($login_function)){
                dosyslog(__FUNCTION__.": INFO: Login procedure initiated. Auth_type: ".$preffered_auth_type);
                $_PAGE["actions"] = array( call_user_func($login_function) ); 

            }else{
                dosyslog(__FUNCTION__.": FATAL ERROR: Function '".$login_function."' is not defined. Could not identicate user for page '".$_PAGE["uri"]."'.");
                die("Code: e-".__LINE__."-login-".$preffered_auth_type);
            };
            
            $_RESPONSE["cookies"]["pat"] = array(
                "value"  => $preffered_auth_type,
                "expire" => time() + $preffered_auth_type_cookie_period,
                "path"   => "/",
                "domain" => $_SERVER["HTTP_HOST"],
            );
        }else{
            dosyslog(__FUNCTION__.": INFO: User not identicated. Public page.");
        };
    };   
        
    return $_USER;
};
function GETPAGE(){  // поиск страницы, соответствующей текущему URI
    global $_PAGE;
    global $_URI;
    global $CFG;
    global $_RESPONSE;
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    $uri = $_URI;

    $page = find_page($uri);

    if(!$page){
        dosyslog(__FUNCTION__.": WARNING: Page '".$_URI."' not found.");
        $_RESPONSE["headers"]["HTTP"] = "HTTP/1.0 404 Not Found";
        
        $page = find_page("error_404");
        if (!$page){
            dosyslog(__FUNCTION__.": WARNING: 404 ErrorPage not found.");
            $page = find_page("/");
        };
    };
    
    if (!$page) {
        dosyslog(__FUNCTION__.": FATAL ERROR: Can not find page for uri '".$_URI."' in pages files.");
        die("Code: e-".__LINE__);
    };
    
    
    
    $_PAGE = $page;
    
    dosyslog(__FUNCTION__.": INFO: ".$_PAGE["uri"]);
    
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
};
function GETURI(){
    global $CFG;
    global $_URI;
    
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    $uri = @$_GET["uri"];
    
    
    if (!$uri) $uri = "/";
    if ("index"==$uri) $uri = "/";
    if ( ("/"!=$uri) && ("/" == $uri{0}) ) $uri = substr($uri,1);

    $_URI = $uri;
    
    dosyslog(__FUNCTION__.": INFO: ".$_URI);
    
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
};
function HASNEXTACTION(){
    global $_ACTIONS;
    global $S;
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    
    if (isset($_ACTIONS)){
        if (isset($_ACTIONS[0])){
            $res = true;
        }else{ 
            $res = false;
            dosyslog(__FUNCTION__.": NOTICE: _ACTIONS list is empty.");;
        };
    }else{
        dosyslog(__FUNCTION__.": FATAL ERROR: _ACTIONS list is not set. SETDEFAULTACTIONS() have to be called before HASNEXTACTION().");
        die("Code: e-".__LINE__);
    };
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    return $res;
};
function SENDHEADERS(){
    
    global $_RESPONSE;
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
       
    if (isset($_RESPONSE["headers"])){
        $headers = (array) $_RESPONSE["headers"];
        //dump($headers,"headers");
        
        if (isset($headers["HTTP"])){
            header($headers["HTTP"]);
        };
        foreach ($headers as $name=>$content){
            if ("HTTP" !== $name) header($name.": ".$content);
        };
    };
    if (isset($_RESPONSE["cookies"])){
        foreach ($_RESPONSE["cookies"] as $name=>$cookie){
            setcookie($name,$cookie["value"],$cookie["expire"], $cookie["path"], $cookie["domain"]);
        };
    };
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
};
function SENDHTML(){
    global $_RESPONSE;
    
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    
    if (isset($_RESPONSE["body"])) echo $_RESPONSE["body"];
    
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
};
function SETACTIONLIST(){
    global $_PAGE;
    global $_ACTIONS;
    
     
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb."); 
    $_ACTIONS = array();
      
        
    if (empty($_PAGE["actions"])) {
        dosyslog(__FUNCTION__.": NOTICE: There are no actions set for page '".$_PAGE["uri"]."' in pages file.");
        return;
    };
    
    foreach($_PAGE["actions"] as $action){
        if ($action) {
            if (!in_array($action, $_ACTIONS)) {
                $_ACTIONS[] = $action;
            }else{
                dosyslog(__FUNCTION__.": ERROR: Dublicate action. Action '".$action."' of page '".$_PAGE["uri"]."' is not unique. Only first instance was added to action list.");
            };
        }else{  
            dosyslog(__FUNCTION__.": ERROR: Action '". $action_name."' of page '".$_PAGE["uri"]."' has no name. Check pages file.");
        };
    };        

    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
};
function SETPARAMS(){
    global $_URI;
    global $_PAGE;
    global $CFG;
    global $_PARAMS;
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    
    dosyslog(__FUNCTION__.": NOTICE: Start setting parameters for page: '" . $_PAGE["uri"] . "'.");
       
    if ( ! empty($_PAGE["params"]) ) {
    
        foreach ($_PAGE["params"] as $fparam_name=>$fparam){
            $tmp = NULL;
            switch ($fparam["source"]){
                case "config":
                    $tmp = isset($CFG[$fparam_name]) ? $CFG[$fparam_name] : null;
                    break;
                case "params":
                    $tmp = isset($_PARAMS[$fparam_name]) ? $_PARAMS[$fparam_name] : null;
                     break;
                case "get":
                    $regexp = ! empty($fparam["regexp"]) ? $fparam["regexp"] : null;
                    $pos = ! empty($fparam["pos"]) ? $fparam["pos"] : null;
                   
                    if ( ! $regexp && ! is_numeric($pos) ){
                        $tmp = isset($_GET[$fparam_name]) ? $_GET[$fparam_name] : null;
                    }else{
                        if ( ! $regexp || ! is_numeric($pos) ){
                            if ( ! $regexp ) dosyslog(__FUNCTION__.": ERROR: Mandatory attribute 'regexp' (" . $regexp . ") is invalid or is not set for parameter '" . $fparam_name . "'. URI: '" . $_URI . "'.");
                            if ( ! is_numeric($pos) ) dosyslog(__FUNCTION__.": ERROR: Mandatory attribute 'pos' (" . $pos . ") is invalid or is not set for parameter '" . $fparam_name . "'. URI: '" . $_URI . "'.");
                            break;
                        };
                        $m = array();
                        $res = preg_match($regexp,$_URI,$m);
                        if ($res){
                            if (isset($m[$pos])){
                                $tmp = $m[$pos];
                            }else{
                                dosyslog(__FUNCTION__.": WARNING: " . $pos . "th parameter can not be get from _URI (" . $_URI . ") via regexp '" . $regexp . "'. Parameter '" . $fparam_name . "'.");
                            };
                        } else {
                            dosyslog(__FUNCTION__.": WARNING: _URI (".$_URI.") does not match regexp '".$regexp."'. Parameter '" . $fparam_name . "'.");
                        };
                    };
                    break;
                case "post":
                    $tmp = isset($_POST[$fparam_name]) ? $_POST[$fparam_name] : null;
                    if ("file" == $fparam["type"]){
                        if ( !empty($_FILES[$fparam_name]["name"]) ){
                            $tmp = $_FILES[$fparam_name]["name"];
                        }else{
                            $tmp = filter_var($tmp, FILTER_VALIDATE_URL);
                            if ( ! $tmp ) $tmp = null;
                        };
                    };
                    // dump($tmp,$fparam_name);
                    break;
                case "request":
                    $tmp = isset($_REQUEST[$fparam_name]) ? $_REQUEST[$fparam_name] : null;
                    break;
                case "cookie":
                    $tmp = isset($_COOKIE[$fparam_name]) ? $_COOKIE[$fparam_name] : null;
                    break;
                case "server":
                    $tmp = isset($_SERVER[$fparam_name]) ? $_SERVER[$fparam_name] : null;
                    break;
                case "session":
                    $tmp = isset($_SESSION[$fparam_name]) ? $_SESSION[$fparam_name] : null;
                    break;
                case "function":
                    if (function_exists($fparam["function"])){
                        $tmp = call_user_func($param["function"]);
                    }else{  
                        dosyslog(__FUNCTION__.": ERROR: Function '".$fparam["function"]."' is not defined. Attribute 'function' of parameter '" . $fparam_name . "'. URI: '" . $_URI . "'.");
                    };
                    break;
                    
                default:
                    dosyslog(__FUNCTION__.": ERROR: Parameter source '" . $fparam["source"] . "' does not supported. Attribute 'source' of parameter '" . $fparam_name . "'. URI: '" . $_URI . "'.");
                    break;
            }; // switch
                        
            
            if ($tmp !== NULL){
                switch ($fparam["type"]){
                    case "number":
                        $tmp = is_numeric($tmp) ? $tmp : NULL;
                        if ($tmp == "0") $tmp = (int) 0;
                        if ($tmp === NULL) dosyslog(__FUNCTION__.": ERROR: Parameter '".@$fparam_name."' of type '".@$fparam["type"]."' does not satisfy to type requirements. Discarded. URI: '" . $_URI . "'.");
                        break;
                    case "file": //  here $tmp supposed to be file name.
                    case "string":
                    case "text":
                        $tmp = is_string($tmp) ? $tmp : NULL;
                        if ($tmp ===NULL) dosyslog(__FUNCTION__.": ERROR: Parameter '" . $fparam_name . "' of type '" . $fparam["type"]."' does not satisfy to type requirements. Discarded. URI: '" . $_URI . "'.");
                        break;
                    case "date":
                        $timestamp = strtotime($tmp);
                        if ( ($timestamp !==false) && ($timestamp !== -1) ) { // PHP до 5.1 возвращает -1 в случае ошибки, более новые версии - false.
                            $tmp = $timestamp;
                        } else {
                            $tmp = NULL;
                            dosyslog(__FUNCTION__.": ERROR: Parameter '" . $fparam_name . "' of type '" . $fparam["type"] . "' does not satisfy to type requirements. Discarded. URI: '" . $_URI . "'.");
                        };
                        break;
                    case "timestamp":
                        if ( in_array( $tmp, array("1", "yes", "y", "Y", "on", "true") ) ){
                            $tmp = time();
                        }else{   // Check if value is valid timestamp, if not (i.e it's string "yes", "on", ... ) generate current timestamp
                            list($month, $day, $year) = explode("/", date("m/d/Y", $tmp));
                            if ( ! checkdate($month, $day, $year) ){
                                $tmp = time();
                            };
                        };
                        break;
                    case "json":
                        
                        if (is_string($tmp)){
                            $tmp = json_decode($tmp,true); // json_decode returns NULL in case of errors.
                        }else{
                            $tmp = json_decode(json_encode($tmp), true);
                        };
                        
                        if ($tmp === NULL) dosyslog(__FUNCTION__.": ERROR: Parameter '" . $fparam_name . "' of type '" . $fparam["type"] . "' does not satisfy to type requirements. Discarded. URI: '" . $_URI . "'.");
                        break;
                    case "list":
                        if (is_string($tmp)){
                            $tmp = explode(DB_LIST_DELIMITER,$tmp); foreach($tmp as $kl=>$vl) $tmp[$kl] = trim($vl);
                        }else{
                            $tmp = explode(DB_LIST_DELIMITER,implode(",",$tmp)); foreach($tmp as $kl=>$vl) $tmp[$kl] = trim($vl);
                        };
                        
                        if (!$tmp){
                            $tmp = NULL;
                            dosyslog(__FUNCTION__.": ERROR: Parameter '". $fparam_name . "' of type '" . $fparam["type"] . "' does not satisfy to type requirements. Discarded. URI: '" . $_URI ."'.");
                        };
                        break;
                    case "array":
                        if (!is_array($tmp)){
                            $tmp = NULL;
                            dosyslog(__FUNCTION__.": ERROR: Parameter '". $fparam_name . "' of type '" . $fparam["type"] . "' does not satisfy to type requirements (is not an array). Discarded. URI: '" . $_URI ."'.");
                        }
                        break;
                    case "phone":
                        if (function_exists("validate_phone")){
                            $tmp = validate_phone($tmp);
                        }else{
                            $tmp = (is_string($tmp) || is_numeric($tmp)) ? $tmp : NULL;
                        };
                         if ($tmp ===NULL) dosyslog(__FUNCTION__.": ERROR: Parameter '" . $fparam_name . "' of type '" . $fparam["type"] . "' does not satisfy to type requirements. Discarded. URI: '" . $_URI . "'.");
                        break;
                    case "email":
                        $tmp = filter_var($tmp, FILTER_VALIDATE_EMAIL);
                        if ( ! $tmp ) $tmp = null;
                        
                         if ($tmp === null) dosyslog(__FUNCTION__.": ERROR: Parameter '" . $fparam_name . "' of type '" . $fparam["type"] . "' does not satisfy to type requirements. Discarded. URI: '" . $_URI . "'.");
                        break;        
                    case "name":
                        if (function_exists("validate_name")){
                            $tmp = validate_name($tmp);
                        }else{
                            $tmp = (is_string($tmp) && ((int)$tmp == 0) ) ? $tmp : NULL;
                        };
                        if ($tmp ===NULL) dosyslog(__FUNCTION__.": ERROR: Parameter '" . $fparam_name . "' of type '" . $fparam["type"] . "' does not satisfy to type requirements. Discarded. URI: '" . $_URI . "'.");
                        break;

                    default:
                        dosyslog(__FUNCTION__.": ERROR: Parameter '" . $fparam_name . "' has type '" . $fparam["type"] . "' which is unsupported. Discarded. URI: '" . $_URI."'.");
                        $tmp = NULL;
                }; // switch
            }; // if

            // dump($tmp,"type checked: param[".$fparam_name."]");
            
            
            $_PARAMS[$fparam_name] = $tmp;
            
            if ($fparam_name == "to") $_SESSION["to"] = $tmp; // сохраняем ввод пользователя в сессию
            
        }; // foreach
    }; // if
    
    if ( ! empty($_PARAMS) ){
        dosyslog(__FUNCTION__.": DEBUG: Params set: {". urldecode(http_build_query($_PARAMS)) . "} for page '".$_PAGE["uri"]."'.");
    }else{
        dosyslog(__FUNCTION__.": DEBUG: No params are set for page '".$_PAGE["uri"]."'.");
    }
    
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    return $_PARAMS;
};




if (!isset($ERROR)) $ERROR = array();
if (!isset($CFG)) $CFG = array(); // CONFIG
if (!isset($S)) $S = array(); // STATE


// register_shutdown_function("shutdown");
session_start();

$DONTSHOWERRORS = false; // когда вывод ошибок недопустим (при вызове методов API, например, надо устанавливать эту переменную в true.
$ISERRORSREGISTERED = false; // зарегистрированы ли ошибки? Функция REGISTERERRORS() устанавливает переменную в true. Иначе, регистрация ошибок будет в shutdown().
$ISREDIRECT = false;
$IS_API_CALL = false;
$IS_IFRAME_MODE = ! empty($_GET["i"]) ? true : false;


// dump($_SESSION);

GETURI();
GETPAGE(); // поиск и получение объекта текущей страницы
IDENTICATE(); // идентификация - пользователь или гость
AUTENTICATE(); // аутентификация пользователя (проверка пароля)
AUTHORIZE(); // авторизация пользователя (создание спиcка разрешений ACL)
SETACTIONLIST();
SETPARAMS();

while(HASNEXTACTION()){

    DOACTION();

};

if ( ! $ISREDIRECT && ! $IS_API_CALL){
   
    APPLYPAGETEMPLATE();
  
};


SENDHEADERS();
SENDHTML();

if (TEST_MODE) dosyslog("End script: NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
//if (TEST_MODE) echo "<br>\n Script done.";
