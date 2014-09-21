<?php

// controller
function APPLYPAGETEMPLATE(){
    global $_RESPONSE;
    global $_PAGE;
    global $CFG;
    
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");

    if (empty($_PAGE["title"])) $_PAGE["title"] = $CFG["GENERAL"]["app_name"];
    
    if ( ! empty($_PAGE["templates"]["page"]) ){
        if (empty($_PAGE["templates"]["content"]) ) set_template_for_user();
        $_RESPONSE["body"] = get_content("page");
    }else{
        die("Code: e-".__LINE__."-page_tmpl");
    };
    
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
};
function AUTENTICATE(){
    global $_USER;
    
	if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    
	if ( ! isset($_USER["isUser"])) {
		dosyslog(__FUNCTION__.": FATAL ERROR: User is not IDENTICATEd. Function IDENTICATE() have to be called before AUTENTICATE(). User: '".serialize($user)."'.");
		die("Code: e-".__LINE__);
	};
	
	if (isset($_SERVER["PHP_AUTH_PW"])){
		if ($_USER["isUser"]){
			
			if($_USER["profile"]){
               
				if (password_verify($_SERVER["PHP_AUTH_PW"], $_USER["profile"]["pass"])) {
					$_USER["autentication_type"] = "loose";
                    unset($_SESSION["NOTLOGGED"]);
                    $_SESSION["LOGGEDAS"] = $_SERVER["PHP_AUTH_USER"];
                    dosyslog(__FUNCTION__.": NOTICE: User '".$_USER["profile"]["login"]."' authenticated.");
				}else{
					dosyslog(__FUNCTION__.": NOTICE: Wrong password for login '".@$_USER["profile"]["login"]."' entered. User id: ".$_USER["profile"]["id"]);
					$autentication_type = "none";
                    $_SESSION["NOTLOGGED"] = true;
                    unset($_SESSION["LOGGEDAS"]);
				};
			}else{
				dosyslog(__FUNCTION__.": ERROR: Can not get user profile for id '".$_USER["profile"]["id"]."'. User fallback to guest.");
				$_USER["isGuest"] = true;
				$_USER["autentication_type"] = "none";
                $_SESSION["NOTLOGGED"] = true;
                unset($_SESSION["LOGGEDAS"]);
			};		
		}else{
            dosyslog(__FUNCTION__.": NOTICE: Visitor is guest.");
			$_USER["autentication_type"] = "none";
		};
	}else{
        dosyslog(__FUNCTION__.": NOTICE: Visitor does not enter pass.");
		$_USER["autentication_type"] = "none";
	};
    

    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
   //dump($user,"user");
    
};
function AUTHORIZE(){
    global $_PAGE;
	global $_USER;
	
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    
    
	if ( ! isset($_USER["isUser"]) || ! isset($_USER["autentication_type"]) ) {
		dosyslog(__FUNCTION__.": FATAL ERROR: User is not IDENTICATEd and/or authenticated. Functions IDENTICATE() and AUTENTICATE() have to be called before AUTHORIZE(). User: '".serialize($_USER)."'.");
        dump($_USER);
		die("Code: e-".__LINE__);
	};
	
        
    // Проверка доступа к текущей странице
    
    $access = true;
       
    if ( ! empty($_PAGE["acl"]) ){
        if ( $_USER["autentication_type"] !== "none" ){
            foreach($_PAGE["acl"] as $right){
                $right = (string) $right;
                if ( ! userHasRight( $right ) ){
                    dosyslog(__FUNCTION__.": User '".$_USER["profile"]["login"]."' has not right '" . $right. "' for page '" . (string)$_PAGE["uri"]."'.");
                    $access = false;
                };
            };
        }
    }else{
        $_USER["autentication_type"] = "loose";
    };
    
    if (!$access) {
        $_USER["autentication_type"] = "none";
        $_USER["isGuest"] = true;
    };
    if ($_USER["isGuest"]) $_USER["isUser"] = false;
    
   
   if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
   //dump($_USER,"user");
};
function DOACTION(){
    global $_CURRENTACTION;
    global $_ACTIONS;
    global $_PAGE;
       
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    //if (TEST_MODE) echo "<br>\n SETACTION begin...";
    SETACTION();
    //if (TEST_MODE) echo "<br>\n SETACTION done.";
    
    $action = $_CURRENTACTION; // нужно для сообщение об ошибках.

    dosyslog(__FUNCTION__.": NOTICE: Action: ".$action);
  
    $function = $action . "_action";
    
    if ( function_exists($function) ){
        $tmp = call_user_func($function);
    }else{  
        dosyslog(__FUNCTION__.": FATAL ERROR: Function '".$function."' is not defined.  URI: '".$_PAGE["uri"]."'.");
        die("Code: e-".__LINE__);
    };
        
    array_shift($_ACTIONS);

    
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
};
function IDENTICATE(){
	global $_USER;
    global $_PAGE;
    global $CFG;
    global $_RESPONSE;
    
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
	$_USER = array("isUser"=>false, "isGuest"=>false, "isPartner"=>false, "isBot"=>false, "autentication_type"=>false);

    if (  ! empty($_PAGE["acl"]) &&
          ( !isset($_SERVER["PHP_AUTH_USER"]) || 
            ( ($_SERVER["PHP_AUTH_USER"] != @$_SESSION["LOGGEDAS"]) && ! empty($_SESSION["LOGGEDAS"]) )  ||
            isset($_SESSION["NOTLOGGED"])
          )
       ){ 
        $headers["WWW-Authenticate"] = 'Basic realm="' . ucfirst($CFG["GENERAL"]["codename"]) . ' - ' . date("M Y") . '"';
        $headers["HTTP"] = "HTTP/1.0 401 Unauthorized";
        $_RESPONSE["headers"] = $headers;
        unset($_SESSION["NOTLOGGED"]);
        SENDHEADERS();
        exit;
    };    
    
	if (!isset($_SERVER["PHP_AUTH_USER"]) || isset($_SESSION["NOTLOGGED"]) ){
        $_USER["isGuest"] = true;
    }else{
		dosyslog(__FUNCTION__ . ": User login:".$_SERVER["PHP_AUTH_USER"]);
		$supposedUsers = db_find("users", "login",$_SERVER["PHP_AUTH_USER"]);
        
	
		if (!empty($supposedUsers)){
			if (count($supposedUsers)>1) dosyslog(__FUNCTION__.": ERROR: User login '".$_SERVER["PHP_AUTH_USER"]."'.is not unique. Found ".count($supposedUsers)." users (should be 1). Ids: '".implode(", ",$supposedUsers)."'. Used first one - '".$supposedUsers[0]."'.");
			$user_profile = db_get("users", $supposedUsers[0]);
			if($user_profile){
				$_USER["isUser"] = true;
				$_USER["isGuest"] = false;
				$_USER["isBot"] = @$user_profile["isBot"];
				$_USER["profile"] = $user_profile;
			}else{
				dosyslog(__FUNCTION__.": ERROR: Can not get user profile for id '".$supposedUsers[0]."'. User fallback to guest.");
				$_USER["isGuest"] = true;
			};
		}else{
			$_USER["isGuest"] = true;
		};
	};
    
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    
};
function GETPAGE(){  // поиск страницы, соответствующей текущему URI
    global $_PAGE;
    global $_URI;
    global $CFG;
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    $uri = $_URI;
    
    $xml = get_pages();
    
    //dump($xml,"pages_xml");
    
    if ($xml){
        $_PAGE = false;
        $page = get_page_by_uri($xml,$uri);
        
        if ("/" != $uri) {
                        
            if (!$page) {
                // отбрасываем якорь
                $tmp = explode("#",$uri); // УТОЧНИТЬ: могут ли быть более одного # в URI.
                if (count($tmp) == 2){
                    $uri = $tmp[0];
                    $page = get_page_by_uri($xml, $uri);
                };
            };
            
            if (!$page) {
                // отбрасываем GET параметры
                $tmp = explode("?",$uri); // УТОЧНИТЬ: могут ли быть более одного ? в URI.
                if (count($tmp) == 2){
                    $uri = $tmp[0];
                    $page = get_page_by_uri($xml, $uri);
                };
            };
            
            if (!$page) {
                // двигаемся вверх по иерархии, к корню
                
                while ( ("" != $uri) && !$page ){
                    
                    $tmp = explode("/",$uri);
                    if (count($tmp)>1){
                        unset($tmp[count($tmp)-1]);
                        $uri = implode("/",$tmp);
                        $page = get_page_by_uri($xml,$uri);
                    }else{
                       break;
                    };
                };
            };
            
            if(!$page){
                dosyslog(__FUNCTION__.": WARNING: Page '".$_URI."' not found.");
                $page = get_page_by_uri($xml,"/");
            };
            
        };
    } else {
        dosyslog(__FUNCTION__.": FATAL ERROR: Can not load XML.");
        die("Code: e-".__LINE__);
    };
    
    if (!$page) {
        dosyslog(__FUNCTION__.": FATAL ERROR: Can not find page for uri '".$_URI."' in XML files.");
        die("Code: e-".__LINE__);
    };
    $_PAGE = $page;
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
};
function GETURI(){
    global $CFG;
    global $_URI;
    
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    $uri = @$_GET["uri"];
    
    if ( strpos($uri,$CFG["GENERAL"]["codename"]) === 0 ){  // убираем имя каталога из URI
        $uri = substr($uri, strlen($CFG["GENERAL"]["codename"]));
    }

    if (!$uri) $uri = "/";
    if ("index"==$uri) $uri = "/";
    if ( ("/"!=$uri) && ("/" == $uri{0}) ) $uri = substr($uri,1);

    $_URI = $uri;
    
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
function SETACTION(){
    global $_PAGE;
    global $_ACTIONS;
    global $_CURRENTACTION;
    global $CFG;
    static $loadedActions = array();
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    $_CURRENTACTION = false;
    $action = @$_ACTIONS[0];
    
    if ($action){
        if ( in_array($action, $_PAGE["actions"]) || in_array($action, array("NOT_AUTH") ) ) {
            $_CURRENTACTION = $action;
        }else{
            dosyslog(__FUNCTION__.": FATAL ERROR: Action '".$action."' is not found in page '".$_PAGE["uri"]."' actions. Check actions file.");
            die("Code: e-".__LINE__);
        };
    }else{
        dosyslog(__FUNCTION__.": FATAL ERROR:Actions list is empty!");
        die("Code: e-".__LINE__);
    };
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
};
function SETACTIONLIST(){
    global $_USER;
    global $_PAGE;
    global $_ACTIONS;
    global $CFG;
    global $_REDIRECT_URI;
     
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb."); 
    $_ACTIONS = array();
      
        
    if (empty($_PAGE["actions"])) {
        dosyslog(__FUNCTION__.": FATAL ERROR: There are no actions set for page '".$_PAGE["uri"]."' in pages file.");
        die("Code: e-".__LINE__);
    };
    
    
     
    if($_USER["autentication_type"] == "none"){
        $_ACTIONS[0] = "NOT_AUTH";
   }else{
    
       
        foreach($_PAGE["actions"] as $action){
            if ($action) {
                if (!in_array($action, $_ACTIONS)) {
                    $_ACTIONS[] = $action;
                }else{
                    dosyslog(__FUNCTION__.": ERROR: Dublicate action. Action '".$action."' of page '".$_PAGE["uri"]."' is not unique. Only first instance was added to action list.");
                };
            }else{  
                dosyslog(__FUNCTION__.": ERROR: Action '". $action_name."' of page '".$_PAGE["uri"]."' has no name. 'Name' attribute has to be set in XML file.");
            };
        };        
        
    };
    // dump($_ACTIONS,"_ACTIONS");  
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
};
function SETPARAMS(){
    global $_URI;
    global $_PAGE;
    global $CFG;
    global $_PARAMS;
    global $_CURRENTACTION;
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    
    $action = $_CURRENTACTION;
    if ( ! empty($action)) dosyslog(__FUNCTION__.": NOTICE: Start setting parameters for action: '". $action . "'.");
    else dosyslog(__FUNCTION__.": NOTICE: Start setting parameters for page: '" . $_PAGE["uri"] . "'.");
       
    if ( ! empty($_PAGE["params"]) ) {
    
        // dump($_POST,"_POST");
        // dump($_FILES,"_FILES");
        // dump($_PAGE->params,"params");
        
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
                            if ( ! $regexp ) dosyslog(__FUNCTION__.": ERROR: Mandatory attribute 'regexp' (" . $regexp . ") is invalid or is not set for parameter '" . $fparam_name . "' of action '" . $action . "'. URI: '" . $_URI . "'.");
                            if ( ! is_numeric($pos) ) dosyslog(__FUNCTION__.": ERROR: Mandatory attribute 'pos' (" . $pos . ") is invalid or is not set for parameter '" . $fparam_name . "' of action '" . $action . "'. URI: '" . $_URI . "'.");
                            break;
                        };
                        $m = array();
                        $res = preg_match($regexp,$_URI,$m);
                        if ($res){
                            if (isset($m[$pos])){
                                $tmp = $m[$pos];
                            }else{
                                dosyslog(__FUNCTION__.": WARNING: " . $pos . "th parameter can not be get from _URI (" . $_URI . ") via regexp '" . $regexp . "'. Parameter '" . $fparam_name . "' of action '" . $action . "'.");
                            };
                        } else {
                            dosyslog(__FUNCTION__.": WARNING: _URI (".$_URI.") does not match regexp '".$regexp."'. Parameter '" . $fparam_name . "' of action '" . $action . "'.");
                        };
                    };
                    break;
                case "post":
                    $tmp = isset($_POST[$fparam_name]) ? $_POST[$fparam_name] : null;
                    if ("file" == $fparam["type"]){
                        if ( !empty($_FILES[$fparam_name]["name"]) ){
                            $tmp = true;
                        }else{
                            $tmp = null;
                        };
                    };
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
                        dosyslog(__FUNCTION__.": ERROR: Function '".$fparam["function"]."' is not defined. Attribute 'function' of parameter '" . $fparam_name . "' of action '" . $action . "'. URI: '" . $_URI . "'.");
                    };
                    break;
                    
                default:
                    dosyslog(__FUNCTION__.": ERROR: Parameter source '" . $fparam["source"] . "' does not supported. Attribute 'source' of parameter '" . $fparam_name . "' of action '" . $action . "'. URI: '" . $_URI . "'.");
                    break;
            }; // switch
                        
            
            if ($tmp !== NULL){
                switch ($fparam["type"]){
                    case "number":
                        $tmp = is_numeric($tmp) ? $tmp : NULL;
                        if ($tmp === NULL) dosyslog(__FUNCTION__.": ERROR: Parameter '".@$fparam_name."' of type '".@$fparam["type"]."' does not satisfy to type requirements. Discarded. Action '". $action . "'. URI: '" . $_URI . "'.");
                        break;
                    case "file": //  here $tmp supposed to be file name.
                    case "string":
                    case "text":
                        $tmp = is_string($tmp) ? $tmp : NULL;
                        if ($tmp ===NULL) dosyslog(__FUNCTION__.": ERROR: Parameter '" . $fparam_name . "' of type '" . $fparam["type"]."' does not satisfy to type requirements. Discarded. Action '" . $action . "'. URI: '" . $_URI . "'.");
                        break;
                    case "date":
                        $timestamp = strtotime($tmp);
                        if ( ($timestamp !==false) && ($timestamp !== -1) ) { // PHP до 5.1 возвращает -1 в случае ошибки, более новые версии - false.
                            $tmp = $timestamp;
                        } else {
                            $tmp = NULL;
                            dosyslog(__FUNCTION__.": ERROR: Parameter '" . $fparam_name . "' of type '" . $fparam["type"] . "' does not satisfy to type requirements. Discarded. Action '" . $action . "'. URI: '" . $_URI . "'.");
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
                        
                        if ($tmp === NULL) dosyslog(__FUNCTION__.": ERROR: Parameter '" . $fparam_name . "' of type '" . $fparam["type"] . "' does not satisfy to type requirements. Discarded. Action '" . $action . "'. URI: '" . $_URI . "'.");
                        break;
                    case "list":
                        if (is_string($tmp)){
                            $tmp = explode(DB_LIST_DELIMITER,$tmp); foreach($tmp as $kl=>$vl) $tmp[$kl] = trim($vl);
                        }else{
                            $tmp = explode(DB_LIST_DELIMITER,implode(",",$tmp)); foreach($tmp as $kl=>$vl) $tmp[$kl] = trim($vl);
                        };
                        
                        if (!$tmp){
                            $tmp = NULL;
                            dosyslog(__FUNCTION__.": ERROR: Parameter '". $fparam_name . "' of type '" . $fparam["type"] . "' does not satisfy to type requirements. Discarded. Action '" . $action . "'. URI: '" . $_URI ."'.");
                        };
                        break;
                    case "array":
                        if (!is_array($tmp)){
                            $tmp = NULL;
                            dosyslog(__FUNCTION__.": ERROR: Parameter '". $fparam_name . "' of type '" . $fparam["type"] . "' does not satisfy to type requirements (is not an array). Discarded. Action '" . $action . "'. URI: '" . $_URI ."'.");
                        }
                        break;
                    case "phone":
                        if (function_exists("validate_phone")){
                            $tmp = validate_phone($tmp);
                        }else{
                            $tmp = (is_string($tmp) || is_numeric($tmp)) ? $tmp : NULL;
                        };
                         if ($tmp ===NULL) dosyslog(__FUNCTION__.": ERROR: Parameter '" . $fparam_name . "' of type '" . $fparam["type"] . "' does not satisfy to type requirements. Discarded. Action '" . $action . "'. URI: '" . $_URI . "'.");
                        break;
                    case "email":
                        $tmp = filter_var($tmp, FILTER_VALIDATE_EMAIL);
                        if ( ! $tmp ) $tmp = null;
                        
                         if ($tmp === null) dosyslog(__FUNCTION__.": ERROR: Parameter '" . $fparam_name . "' of type '" . $fparam["type"] . "' does not satisfy to type requirements. Discarded. Action '" . $action . "'. URI: '" . $_URI . "'.");
                        break;        
                    case "name":
                        if (function_exists("validate_name")){
                            $tmp = validate_name($tmp);
                        }else{
                            $tmp = (is_string($tmp) && ((int)$tmp == 0) ) ? $tmp : NULL;
                        };
                        if ($tmp ===NULL) dosyslog(__FUNCTION__.": ERROR: Parameter '" . $fparam_name . "' of type '" . $fparam["type"] . "' does not satisfy to type requirements. Discarded. Action '" . $action . "'. URI: '" . $_URI . "'.");
                        break;

                    default:
                        dosyslog(__FUNCTION__.": ERROR: Parameter '" . $fparam_name . "' has type '" . $fparam["type"] . "' which is unsupported. Discarded. Action '" . $action . "'. URI: '" . $_URI."'.");
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

if (!$ISREDIRECT){
   
    APPLYPAGETEMPLATE();
  
};


SENDHEADERS();
SENDHTML();

if (TEST_MODE) dosyslog("End script: NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
//if (TEST_MODE) echo "<br>\n Script done.";
