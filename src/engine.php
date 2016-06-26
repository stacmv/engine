<?php
require ENGINE_DIR . "settings/checks.php";
require ENGINE_DIR . "settings/version.php";


// controller
function APPLYPAGETEMPLATE(){
    global $_RESPONSE;
    global $_PAGE;
    global $CFG;
    global $IS_IFRAME_MODE;
    global $IS_MOBILE;
    
    if (empty($_PAGE["title"])) $_PAGE["title"] = $CFG["GENERAL"]["app_name"];
    
    if (! empty($_PAGE["templates"]["page"]) ){
        if (empty($_PAGE["templates"]["content"]) ){
            if ( ! empty($_PAGE["templates"]["guest"]) ){
                set_template_for_user();
            };
        };
        if ($IS_IFRAME_MODE){
            if ( ! empty($_PAGE["templates"]["iframe"]) ){
                $_RESPONSE["body"] = get_content("iframe");
            }else{
                dosyslog(__FUNCTION__.": ERROR: Could not find template 'iframe' for page '".$_PAGE["uri"]."' requested in iframe_mode.");
                $_RESPONSE["body"] = get_content("page");
            }
        }elseif($IS_MOBILE && !empty($_PAGE["templates"]["page_mobile"])){
            $_RESPONSE["body"] = get_content("page_mobile");
        }else{
            $_RESPONSE["body"] = get_content("page");
        };
    }else{
        dosyslog(__FUNCTION__.get_callee().": FATAL ERROR: 'Page' templates is not set for page '".$_PAGE["uri"]."'. Check pages config.");
        die("Code: e-".__LINE__."-page_tmpl");
    };
    
    
};
function AUTHORIZE(){
    global $_PAGE;
    global $_USER;
    
    $_USER = new User();

    if ( empty($_PAGE["acl"]) ){
        $authorized = true;
    }else{
        
        if ( ! $_USER->is_authenticated()){
            $authorized = false;
        }else{
            $authorized = true;
            foreach($_PAGE["acl"] as $right){
                if ( ! userHasRight( $right ) ){
                    dosyslog(__FUNCTION__.": User '".$_USER->get_login()."' has not right '" . $right. "' for page '" . $_PAGE["uri"]."'.");
                    $authorized = false;
                    break;
                };
            };
        };
    };
    
    if ( ! $authorized) {
        dosyslog(__FUNCTION__.": WARNING: User not authorized for page '" . $_PAGE["uri"]."'.");

        // ///////////////////////////
        if ($_USER->is_authenticated() ){
            $_PAGE["actions"] = array("NOT_AUTH");
        }else{
            $_PAGE["actions"] = array("NOT_LOGGED");
        };
        // ///////////////////////////
        
    }else{   
        dosyslog(__FUNCTION__.": INFO: User " . $_USER->get_login() . " authorized for page '" . $_PAGE["uri"]."'.");
    };
    
    
   return $_USER;
   
};
function DOACTION(){
    global $_ACTIONS;
    global $_PAGE;

    //if (TEST_MODE) echo "<br>\n SETACTION done.";
    
    $action = array_shift($_ACTIONS);

    dosyslog(__FUNCTION__.": INFO: Action: ".$action);
  
    $function = $action . "_action";
    
    if ( function_exists($function) ){
        $tmp = call_user_func($function);
    }else{  
        dosyslog(__FUNCTION__.": FATAL ERROR: Function '".$function."' is not defined.  URI: '".$_PAGE["uri"]."'.");
        die("Code: e-".__LINE__."-".$function);
    };
    
    
};
function GETPAGE(){  // поиск страницы, соответствующей текущему URI
    global $_PAGE;
    global $_URI;

    
    

    $_PAGE = find_page($_URI);

    if( ! $_PAGE ){
        $_PAGE = response_404_page();
    };
    
    if ( ! $_PAGE ) {
        dosyslog(__FUNCTION__.": FATAL ERROR: Can not find page for uri '".$_URI."' in pages files.");
        die("Code: e-".__LINE__);
    };
    
    dosyslog(__FUNCTION__.": INFO: ".$_PAGE["uri"] . " for uri '".$_URI."'.");
    
    
};
function GETURI(){
    global $CFG;
    global $_URI;
    
    
    $uri = ! empty($_GET["uri"]) ? $_GET["uri"] : "/";
    
    if ("index"==$uri) $uri = "/";
    if ( ("/"!=$uri) && ("/" == $uri{0}) ) $uri = substr($uri,1);

    $_URI = $uri;
    
    dosyslog(__FUNCTION__.": DEBUG: ".$_URI);
    
    
};
function HASNEXTACTION(){
    global $_ACTIONS;
    global $S;
    
    
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
    
    return $res;
};
function SENDHEADERS(){
    
    global $_RESPONSE;
    
       
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
    
};
function SENDBODY(){
    global $_RESPONSE;
    
    if (isset($_RESPONSE["body"])) echo $_RESPONSE["body"];
    
    
};
function SETACTIONLIST(){
    global $_PAGE;
    global $_ACTIONS;
    global $_DEFAULT_ACTIONS;
    
     
     
    // $_DEFAULT_ACTIONS - action-функции, которые должны выполняться для каждой страницы. См. register_default_action() из engine_functions.php, которая может вызываться из actions.php.
    
    $_ACTIONS = array(); 
      
        
    if (empty($_PAGE["actions"])){
        $_PAGE["actions"] = array();
    };
    if ( ! empty($_DEFAULT_ACTIONS) ){
        $_PAGE["actions"] = array_merge($_PAGE["actions"], $_DEFAULT_ACTIONS);
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

    
};
function SETAJAXRESPONSEBODY(){
    global $IS_AJAX;
    global $_DATA;
    global $_RESPONSE;
    
    if ($IS_AJAX && empty($_RESPONSE["body"])){
        $_RESPONSE["headers"]["Content-type"] = "application/json";
        $_RESPONSE["body"] = json_encode($_DATA);
    };
    
}
function SETPARAMS(){
    global $_URI;
    global $_PAGE;
    global $CFG;
    global $_PARAMS;
    
        
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
                                if ( ! empty($fparam["required"]) && ($fparam["required"] == "required") ){
                                    dosyslog(__FUNCTION__.": WARNING: " . $pos . "th parameter can not be get from _URI (" . $_URI . ") via regexp '" . $regexp . "'. Parameter '" . $fparam_name . "'.");
                                };
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
                    if ( ! empty($fparam["param_name"]) ){
                        $tmp = isset($_REQUEST[$fparam["param_name"]]) ? $_REQUEST[$fparam["param_name"]] : null;
                    }else{
                        $tmp = isset($_REQUEST[$fparam_name]) ? $_REQUEST[$fparam_name] : null;
                    };
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
                    dosyslog(__FUNCTION__.": ERROR: Parameter source '" . @$fparam["source"] . "' does not supported. Attribute 'source' of parameter '" . $fparam_name . "'. URI: '" . $_URI . "'.");
                    break;
            }; // switch
                        
            
            if ($tmp !== NULL){
                switch ($fparam["type"]){
                    case "number":
                        $tmp = is_numeric($tmp) ? $tmp : NULL;
                        if ($tmp == "0") $tmp = (int) 0;
                        if ($tmp === NULL) dosyslog(__FUNCTION__.": ERROR: Parameter '".(!empty($fparam_name) ? $fparam_name : "_undefined_")."' of type '".(!empty($fparam["type"]) ? $fparam["type"] : "_undefined_")."' does not satisfy to type requirements. Discarded. URI: '" . $_URI . "'.");
                        break;
                    case "file": //  here $tmp supposed to be file name.
                        list($res, $dest_file) = upload_file($tmp, FILES_DIR);
                        if ($res) $tmp = $dest_file;
                        break;
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
        dosyslog(__FUNCTION__.": NOTICE: Params set: {". urldecode(http_build_query($_PARAMS)) . "} for page '".$_PAGE["uri"]."'.");
    }else{
        dosyslog(__FUNCTION__.": DEBUG: No params are set for page '".$_PAGE["uri"]."'.");
    }
    
    
    return $_PARAMS;
};



$start_microtime = isset($_SERVER['REQUEST_TIME_FLOAT']) ? $_SERVER['REQUEST_TIME_FLOAT'] : microtime(true);

// register autoload function for engine classes
spl_autoload_register(function ($class_name){$class_file =  ENGINE_DIR . "classes/" . engine_utils_get_class_filename($class_name); if (file_exists($class_file)){ require_once $class_file; } /*else throw new Exception($class_name)*/;});

// register_shutdown_function("shutdown");
session_start();

$DONTSHOWERRORS = false; // когда вывод ошибок недопустим (при вызове методов API, например, надо устанавливать эту переменную в true.
$ISERRORSREGISTERED = false; // зарегистрированы ли ошибки? Функция REGISTERERRORS() устанавливает переменную в true. Иначе, регистрация ошибок будет в shutdown().
$ISREDIRECT = false;
$IS_API_CALL = false;
$IS_IFRAME_MODE = ! empty($_GET["i"]) ? true : false;
$IS_MOBILE = is_mobile();
$IS_AJAX = is_ajax();


GETURI();
GETPAGE(); 
AUTHORIZE(); 
SETACTIONLIST();
SETPARAMS();

while(HASNEXTACTION()){

    DOACTION();

};


if($IS_AJAX && empty($_PAGE["templates"]["page"])){
    SETAJAXRESPONSEBODY();
}elseif ( ! $ISREDIRECT && ! $IS_API_CALL ){
    APPLYPAGETEMPLATE();  
}


SENDHEADERS();
SENDBODY();

dosyslog("ENGINE: INFO: Request processed within " . round(microtime(true) - $start_microtime, 4) ."s, memory used in peak: ".glog_convert_size(memory_get_peak_usage(true)).".");
