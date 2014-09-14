<?php

// controller
function APPLYRESULT($state){
    // берет результаты выполнения последнего действия из текущего состояния и применяет к состоянию $state, которое было сохранено до выполнения действия.
    // действие может "испортить" состояние, поэтому перед действием состояние сохраняется на стэке, а потом восстанавливается (см. DOACTION()).
    
    global $S;
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    $action = $S["_CURRENTACTION"];

    $result = array(); // 
    
    foreach ($action->result->param as $fparam){
        $tmp = NULL;
        $fparam_name = (string) $fparam["name"];
        switch ($fparam["destination"]){
            case "state":
                $tmp = @$S[$fparam_name];
                 break;
            default:
                dosyslog(__FUNCTION__." ERROR: Parameter destination '".$fparam["destination"]."' does not supported. Attribute 'destination' of result parameter '".@$fparam_name."' of action '".@$action["name"]."'. URI: '".$S["_URI"]."'.");
                break;
        }; // switch 
        
        // ДОРАБОТАТЬ: этот фрагмент повторяет аналогичный из SETPARAMS. Лучше вынести в отдельную функцию.
        if ($tmp !== NULL){
            switch ($fparam["type"]){
                case "number":
                    $tmp = is_numeric($tmp) ? $tmp : NULL;
                    if ($tmp ===NULL) dosyslog(__FUNCTION__.": ERROR: Parameter '".@$fparam_name."' of type '".@$fparam["type"]."' does not satisfy to type requirements. Discarded. Action '".@$action["name"]."'. URI: '".$S["_URI"]."'.");
                    break;
                case "string":
                    $tmp = is_string($tmp) ? $tmp : NULL;
                    if ($tmp ===NULL) dosyslog(__FUNCTION__.": ERROR: Parameter '".@$fparam_name."' of type '".@$fparam["type"]."' does not satisfy to type requirements. Discarded. Action '".@$action["name"]."'. URI: '".$S["_URI"]."'.");
                    break;
                case "date":
                    $timestamp = strtotime($tmp);
                    if ( ($timestamp !==false) && ($timestamp !== -1) ) { // PHP до 5.1 возвращает -1 в случае ошибки, более новые версии - false.
                        $tmp = $timestamp;
                    } else {
                        $tmp = NULL;
                        dosyslog(__FUNCTION__.": ERROR: Parameter '".@$fparam_name."' of type '".@$fparam["type"]."' does not satisfy to type requirements. Discarded. Action '".@$action["name"]."'. URI: '".$S["_URI"]."'.");
                    };
                    break;
                case "json":
                    //dump($tmp,"tmp1");
                    if (is_string($tmp)){
                        $tmp = json_decode($tmp,true); // json_decode returns NULL in case of errors.
                    }else{
                        $tmp = json_decode(json_encode($tmp));
                    };
                    if ($tmp ===NULL) dosyslog(__FUNCTION__.": ERROR: Parameter '".@$fparam_name."' of type '".@$fparam["type"]."' does not satisfy to type requirements. Discarded. Action '".@$action["name"]."'. URI: '".$S["_URI"]."'.");
                    //dump($tmp,"tmp2");
                    break;
                case "phone":
                    if (function_exists("validate_phone")){
                        $tmp = validate_phone($tmp);
                    }else{
                        $tmp = (is_string($tmp) || is_numeric($tmp)) ? $tmp : NULL;
                    };
                     if ($tmp ===NULL) dosyslog(__FUNCTION__.": ERROR: Parameter '".@$fparam_name."' of type '".@$fparam["type"]."' does not satisfy to type requirements. Discarded. Action '".@$action["name"]."'. URI: '".$S["_URI"]."'.");
                    break;
                case "email":
                    if (function_exists("validate_email")){
                        $tmp = validate_email($tmp);
                    }else{
                        $tmp = (is_string($tmp) && (strpos($tmp,".") > strpos($tmp,"@")) ) ? $tmp : NULL;
                    };
                     if ($tmp ===NULL) dosyslog(__FUNCTION__.": ERROR: Parameter '".@$fparam_name."' of type '".@$fparam["type"]."' does not satisfy to type requirements. Discarded. Action '".@$action["name"]."'. URI: '".$S["_URI"]."'.");
                    break;        
                case "name":
                    if (function_exists("validate_name")){
                        $tmp = validate_name($tmp);
                    }else{
                        $tmp = (is_string($tmp) && ((int)$tmp == 0) ) ? $tmp : NULL;
                    };
                    if ($tmp ===NULL) dosyslog(__FUNCTION__.": ERROR: Parameter '".@$fparam_name."' of type '".@$fparam["type"]."' does not satisfy to type requirements. Discarded. Action '".@$action["name"]."'. URI: '".$S["_URI"]."'.");
                    break;
                case "xml": 
                    if (is_string($tmp)){
                        $tmp = simplexml_load_string($tmp);
                        if ($tmp === false){
                            dosyslog(__FUNCTION__.": ERROR : Parameter '".@$fparam_name."' of type ''xml' can not be parsed by simplexml. First 100 bytes: '".htmlspecialchars(substr($tmp,0,100))."'.");
                            $tmp = NULL;
                        };
                    }else{
                        if (is_object($tmp)){
                            if (get_class($tmp) !== "SimpleXMLElement"){
                                dosyslog(__FUNCTION__.": ERROR : Parameter '".@$fparam_name."' of type ''xml' is object but has not class SimpleXMLElement. The class is '".get_class($tmp)."'.");
                                $tmp = NULL;
                            };
                        }else{  
                            $tmp = NULL;
                        };
                    };
                    if ($tmp ===NULL) dosyslog(__FUNCTION__.": ERROR: Parameter '".@$fparam_name."' of type '".@$fparam["type"]."' does not satisfy to type requirements. Discarded. Action '".@$action["name"]."'. URI: '".$S["_URI"]."'.");
                    break;
                default:
                    dosyslog(__FUNCTION__.": ERROR: Parameter '".@$fparam_name."' has type '".@$fparam["type"]."' which is unsupported. Discarded. Action '".@$action["name"]."'. URI: '".$S["_URI"]."'.");
                    $tmp = NULL;
            }; // switch
        }; // if
        
        // ==============
        
        if ($tmp !== NULL){
            $result[$fparam_name] = $tmp;
        };
    }; // foreach
    
    
    //dump($result,"result");
    
    foreach ($result as $k=>$v) $state[$k] = $v;
    
    $S = $state;
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
};
function APPLYPAGETEMPLATE(){
    global $_RESPONSE;
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    $_RESPONSE["body"] = get_content("page");
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
};
function AUTENTICATE(){
    global $_USER;
    
	if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    
	if ( ! isset($_USER["isUser"])) {
		dosyslog(__FUNCTION__.": FATAL ERROR: User is not IDENTICATEd. Function IDENTICATE() have to be called before AUTENTICATE(). User: '".serialize($user)."'.");
		die("Code:".__LINE__);
	};
	
	if (isset($_SERVER["PHP_AUTH_PW"])){
		if ($_USER["isUser"]){
			
			if($_USER["profile"]){
               
				if ($_USER["profile"]["pass"] == md5(md5($_SERVER["PHP_AUTH_PW"]))) {
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
    
    
    $page_rights = get_page_rights($_PAGE);
	
	if ( ! isset($_USER["isUser"]) || ! isset($_USER["autentication_type"]) ) {
		dosyslog(__FUNCTION__.": FATAL ERROR: User is not IDENTICATEd and/or authenticated. Functions IDENTICATE() and AUTENTICATE() have to be called before AUTHORIZE(). User: '".serialize($_USER)."'.");
        dump($_USER);
		die("Code:".__LINE__);
	};
	
        
    // Проверка доступа к текущей странице
    
    $access = true;
       
    if ( ! empty($page_rights)){
        foreach($page_rights as $right){
            $right = (string) $right;
            if ( ! userHasRight( $right ) ){
                dosyslog(__FUNCTION__.": User '".$_USER["profile"]["login"]."' has not right '" . $right. "' for page '" . (string)$_PAGE["uri"]."'.");
                $access = false;
            };
        };
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
    
    global $S;
       
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    //if (TEST_MODE) echo "<br>\n SETACTION begin...";
    SETACTION();
    //if (TEST_MODE) echo "<br>\n SETACTION done.";
    
    dosyslog(__FUNCTION__.": NOTICE: Action: ".$S["_CURRENTACTION"]["name"]);
    
    $action = $S["_CURRENTACTION"]; // нужно для сообщение об ошибках.
      
    foreach ($S["_CURRENTACTION"]->operations->children() as $op){
        
        $op_type = (string) $op->getName();
        switch ($op_type){
            case "action":  //ДОРАБОТАТЬ: не протестиовано
                $S["_ACTIONS"] = array((string) $op);
                SETACTION();
                SETPARAMS();
                DOACTION();
                break;
            case "function":
                if (function_exists((string)$op["name"])){
                    $tmp = call_user_func((string)$op["name"]);
                }else{  
                    dosyslog(__FUNCTION__.": FATAL ERROR: Function '".@(string)$op["name"]."' is not defined. Operation '".@(string)$op["name"]."' of action '".@(string)$action["name"]."'. URI: '".$S["_URI"]."'.");
                    die("Code: ".__LINE__);
                };
                break;
            /*
            case "api":
                break;
            case "system":
                break;
            */
            default:
                dosyslog(__FUNCTION__." ERROR: Operation type '".$op_type."' does not supported. Operation '".@(string)$op["name"]."' of action '".@(string)$action["name"]."'. URI: '".$S["_URI"]."'.");
        }; //switch
        

        array_shift($S["_ACTIONS"]);
        
    }; //foreach
    
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
};
function IDENTICATE(){
	global $_USER;
    global $_PAGE;
    global $CFG;
    
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
	$_USER = array("isUser"=>false, "isGuest"=>false, "isPartner"=>false, "isBot"=>false, "autentication_type"=>false);
	$page_rights = get_page_rights($_PAGE);
    
    if (  ! empty($page_rights) &&
          ( !isset($_SERVER["PHP_AUTH_USER"]) || 
            ( ($_SERVER["PHP_AUTH_USER"] != @$_SESSION["LOGGEDAS"]) && ! empty($_SESSION["LOGGEDAS"]) )  ||
            isset($_SESSION["NOTLOGGED"])
          )
       ){ 
        $headers["WWW-Authenticate"] = 'Basic realm="' . $CFG["GENERAL"]["app_name"] . ' - ' . date("M Y") . '"';
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
				$_USER["isPartner"] = @$user_profile["partnerId"] ? true : false;
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
    global $S;
    global $CFG;
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    $uri = $S["_URI"];
    
    $xml = get_pages_xml();
    
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
                dosyslog(__FUNCTION__.": WARNING: Page '".$S["_URI"]."' not found.");
                $page = get_page_by_uri($xml,"/");
            };
            
        };
    } else {
        dosyslog(__FUNCTION__.": FATAL ERROR: Can not load XML.");
        die("Code:".__LINE__);
    };
    
    if (!$page) {
        dosyslog(__FUNCTION__.": FATAL ERROR: Can not find page for uri '".$S["_URI"]."' in XML files.");
        die("Code:".__LINE__);
    };
    $_PAGE = $page;
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
};
function GETURI(){
    
    global $S;
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    $uri = @$_GET["uri"];
    if (!$uri) $uri = "/";
    if ("index"==$uri) $uri = "/";
    if ( ("/"!=$uri) && ("/" == $uri{0}) ) $uri = substr($uri,1);
    
    $S["_URI"] = $uri;  
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
};
function HASNEXTACTION(){
    
    global $S;
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    $res = false;
    if (isset($S["_ACTIONS"])){
        if (isset($S["_ACTIONS"][0])){
            $res = true;
        }else{ 
            dosyslog(__FUNCTION__.": NOTICE: _ACTIONS list is empty.");;
        };
    }else{
        dosyslog(__FUNCTION__.": FATAL ERROR: _ACTIONS list is not set. SETDEFAULTACTIONS() have to be called before HASNEXTACTION().");
        die("Code:".__LINE__);
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
    global $S;
    global $CFG;
    static $loadedActions = array();
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    $S["_CURRENTACTION"] = false;
    $action_name = @$S["_ACTIONS"][0];
    
    if ($action_name){
        $action_files = array(
            "app"    => APP_DIR . "settings/actions.xml",
            "engine" => ENGINE_DIR . "settings/actions.xml",
        );
        foreach($action_files as $group=>$file){
            if (empty($loadedActions[$group])){
                $xml = simplexml_load_file($file);
                if ($xml){
                    $loadedActions[$group] = $xml;
                }else{
                    dosyslog(__FUNCTION__.": FATAL ERROR: Can not load and/parse " . $group ." actions file.");
                    die("Code:".__LINE__);
                };
            };
             
            
            foreach($loadedActions[$group]->action as $action){ //ДОРАБОТАТЬ: применить вместо цикла XPath
                if ($action_name == (string) $action["name"]) {
                    $S["_CURRENTACTION"] = $action;
                    break;
                };
            }; //foreach
            
            if ($S["_CURRENTACTION"] !== false) break;
        }; //foreach CFG
            
        if ($S["_CURRENTACTION"] === false) { // ДОРАБОТАТЬ: сделать поддержку действий не только в _PAGE, но и действий уровня приложения и уровня платформы.
            dosyslog(__FUNCTION__.": FATAL ERROR: Action '".$action_name."' is not found in page '".$_PAGE["uri"]."' actions. Check actions file.");
            die("Code:".__LINE__);
        };
            
    }else{
        dosyslog(__FUNCTION__.": FATAL ERROR:Actions list is empty!");
        die("Code:".__LINE__);
    };
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
};
function SETACTIONLIST(){
    global $_USER;
    global $_PAGE;
    global $S;
    global $CFG;
     if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb."); 
    $S["_ACTIONS"] = array();
      
        
    if (empty($_PAGE->actions)) {
        dosyslog(__FUNCTION__.": FATAL ERROR: There are no actions set for page '".$_PAGE["uri"]."' in pages XML file.");
        die("Code:".__LINE__);
    };
    
    
     
    if($_USER["autentication_type"] == "none"){
        if ($_USER["isPartner"]){     // ДОРАБОТАТЬ: вынести эту ветку в код APPLICATION
            $partnerUri = get_partnerUri_by_id($_USER["profile"]["partnerId"]);
            if ($S["_URI"] !== "cl/".$partnerUri.$CFG["URL"]["ext"] && userHasRight("access")) {
                $S["redirect_uri"] = "cl/".$partnerUri.$CFG["URL"]["ext"];
                $S["_ACTIONS"][0] = "REDIRECT";
            }else{
                $S["_ACTIONS"][0] = "NOT_AUTH";
            };
        }else{
            $S["_ACTIONS"][0] = "NOT_AUTH";
        };
        
        
    }else{
    
       
        foreach($_PAGE->actions->action as $action){
            $action_name = (string) $action;
            //dump($action_name, "action_name");
            if ($action_name) {
                if (!in_array($action_name, $S["_ACTIONS"])) {
                    $S["_ACTIONS"][] = $action_name;
                }else{
                    dosyslog(__FUNCTION__.": ERROR: Dublicate action. Action '".$action_name."' of page '".$_PAGE["uri"]."' is not unique. Only first instance was added to action list.");
                };
            }else{  
                dosyslog(__FUNCTION__.": ERROR: Action '". $action_name."' of page '".$_PAGE["uri"]."' has no name. 'Name' attribute has to be set in XML file.");
            };
        };        
        
    };
    // dump($S["_ACTIONS"],"_ACTIONS");  
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
};
function SETPARAMS(){
    global $_PAGE;
    global $CFG;
    global $S;
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    $action = @$S["_CURRENTACTION"];
    if ( ! empty($action)) dosyslog(__FUNCTION__.": NOTICE: Start setting parameters for action: '".(string) $action["name"] . "'.");
    else dosyslog(__FUNCTION__.": NOTICE: Start setting parameters for page: '".(string) $_PAGE["uri"] . "'.");
       
    if (!empty($_PAGE->params)) {
    
        // dump($_POST,"_POST");
        // dump($_FILES,"_FILES");
        // dump($_PAGE->params,"params");
        
        foreach ($_PAGE->params->param as $fparam){
            $tmp = NULL;
            $fparam_name = (string) $fparam["name"];
            switch ($fparam["source"]){
                case "config":
                    $tmp = @$CFG[$fparam_name];
                    break;
                case "state":
                    $tmp = @$S[$fparam_name];
                     break;
                case "get":
                    $regexp = @$fparam["regexp"];
                    $pos = (string) @$fparam["pos"];
                    if (!$regexp || !is_numeric($pos) ){
                        if (!$regexp) dosyslog(__FUNCTION__.": ERROR: Mandatory attribute 'regexp' (".@$regexp.") is invalid or is not set for parameter '".@$fparam_name."' of action '".@$action["name"]."'. URI: '".$S["_URI"]."'.");
                        if (!is_numeric($pos)) dosyslog(__FUNCTION__.": ERROR: Mandatory attribute 'pos' (".@$pos.") is invalid or is not set for parameter '".@$fparam_name."' of action '".@$action["name"]."'. URI: '".$S["_URI"]."'.");
                        break;
                    };
                    $m = array();
                    $res = preg_match($regexp,$S["_URI"],$m);
                    if ($res){
                        if (isset($m[$pos])){
                            $tmp = $m[$pos];
                        }else{
                            dosyslog(__FUNCTION__.": WARNING: ".@$pos."th parameter can not be get from _URI (".$S["_URI"].") via regexp '".$regexp."'. Parameter '".@$fparam_name."' of action '".@$action["name"]."'.");
                        };
                    } else {
                        dosyslog(__FUNCTION__.": WARNING: _URI (".$S["_URI"].") does not match regexp '".$regexp."'. Parameter '".@$fparam_name."' of action '".@$action["name"]."'.");
                    };
                    break;
                case "post":
                    $tmp = @$_POST[$fparam_name];
                    if ("file"==$fparam["type"]){
                        if ($_FILES[$fparam_name]["name"]){
                                                       
                            $tmp = FILES_DIR . get_filename(pathinfo($_FILES[$fparam_name]["name"],PATHINFO_FILENAME)."__".time(), ".".pathinfo($_FILES[$fparam_name]["name"],PATHINFO_EXTENSION));
                        }else{
                            $tmp = NULL;
                        };
                    };
                    break;
                case "request":
                    $tmp = @$_REQUEST[$fparam_name];
                    if ("file"==$fparam["type"]){
                        if ($_FILES[$fparam_name]["name"]){
                                                       
                            $tmp = FILES_DIR . get_filename(pathinfo($_FILES[$fparam_name]["name"],PATHINFO_FILENAME)."__".time(), ".".pathinfo($_FILES[$fparam_name]["name"],PATHINFO_EXTENSION));
                        }else{
                            $tmp = NULL;
                        };
                    };
                    break;
                case "cookie":
                    $tmp = @$_COOKIE[$fparam_name];
                    break;
                case "server":
                    $tmp = @$_SERVER[$fparam_name];
                    break;
                case "session":
                    $tmp = @$_SESSION[$fparam_name];
                    break;
                case "function":
                    if(isset($fparam["file"])){
                        if (file_exists($fparam["file"])){
                            require_once APP_DIR . $fparam["file"];
                        }else{
                            dosyslog(__FUNCTION__.": ERROR: File '".$fparam["file"]."' can not be found. Attribute 'file' of parameter '".@$fparam_name."' of action '".@$action["name"]."'. URI: '".$S["_URI"]."'.");
                        };
                    };
                    if (function_exists($fparam["function"])){
                        $tmp = call_user_func($param["function"]);
                    }else{  
                        dosyslog(__FUNCTION__.": ERROR: Function '".$fparam["function"]."' is not defined. Attribute 'function' of parameter '".@$fparam_name."' of action '".@$action["name"]."'. URI: '".$S["_URI"]."'.");
                    };
                    break;
                    
                default:
                    dosyslog(__FUNCTION__.": ERROR: Parameter source '".$fparam["source"]."' does not supported. Attribute 'source' of parameter '".@$fparam_name."' of action '".@$action["name"]."'. URI: '".$S["_URI"]."'.");
                    break;
            }; // switch
                        
            
            if ($tmp !== NULL){
                switch ($fparam["type"]){
                    case "number":
                        $tmp = is_numeric($tmp) ? $tmp : NULL;
                        if ($tmp === NULL) dosyslog(__FUNCTION__.": ERROR: Parameter '".@$fparam_name."' of type '".@$fparam["type"]."' does not satisfy to type requirements. Discarded. Action '".@$action["name"]."'. URI: '".$S["_URI"]."'.");
                        break;
                    case "file": //  here $tmp supposed to be file name.   
                    case "string":
                    case "text":
                        $tmp = is_string($tmp) ? $tmp : NULL;
                        if ($tmp ===NULL) dosyslog(__FUNCTION__.": ERROR: Parameter '".@$fparam_name."' of type '".@$fparam["type"]."' does not satisfy to type requirements. Discarded. Action '".@$action["name"]."'. URI: '".$S["_URI"]."'.");
                        break;
                    case "date":
                        $timestamp = strtotime($tmp);
                        if ( ($timestamp !==false) && ($timestamp !== -1) ) { // PHP до 5.1 возвращает -1 в случае ошибки, более новые версии - false.
                            $tmp = $timestamp;
                        } else {
                            $tmp = NULL;
                            dosyslog(__FUNCTION__.": ERROR: Parameter '".@$fparam_name."' of type '".@$fparam["type"]."' does not satisfy to type requirements. Discarded. Action '".@$action["name"]."'. URI: '".$S["_URI"]."'.");
                        };
                        break;
                    case "timestamp":
                        die("here");
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
                        
                        
                        if ($tmp ===NULL) dosyslog(__FUNCTION__.": ERROR: Parameter '".@$fparam_name."' of type '".@$fparam["type"]."' does not satisfy to type requirements. Discarded. Action '".@$action["name"]."'. URI: '".$S["_URI"]."'.");
                        break;
                    case "list":
                        if (is_string($tmp)){
                            $tmp = explode(",",$tmp); foreach($tmp as $kl=>$vl) $tmp[$kl] = trim($vl);
                        }else{
                            $tmp = explode(",",implode(",",$tmp)); foreach($tmp as $kl=>$vl) $tmp[$kl] = trim($vl);
                        };
                        
                        if (!$tmp){
                            $tmp = NULL;
                            dosyslog(__FUNCTION__.": ERROR: Parameter '".@$fparam_name."' of type '".@$fparam["type"]."' does not satisfy to type requirements. Discarded. Action '".@$action["name"]."'. URI: '".$S["_URI"]."'.");
                        };
                        break;
                        
                    case "phone":
                        if (function_exists("validate_phone")){
                            $tmp = validate_phone($tmp);
                        }else{
                            $tmp = (is_string($tmp) || is_numeric($tmp)) ? $tmp : NULL;
                        };
                         if ($tmp ===NULL) dosyslog(__FUNCTION__.": ERROR: Parameter '".@$fparam_name."' of type '".@$fparam["type"]."' does not satisfy to type requirements. Discarded. Action '".@$action["name"]."'. URI: '".$S["_URI"]."'.");
                        break;
                    case "email":
                        if (function_exists("validate_email")){
                            $tmp = validate_email($tmp);
                        }else{
                            $tmp = (is_string($tmp) && (strrpos($tmp,".") > strpos($tmp,"@")) ) ? $tmp : NULL;
                        };
                         if ($tmp ===NULL) dosyslog(__FUNCTION__.": ERROR: Parameter '".@$fparam_name."' of type '".@$fparam["type"]."' does not satisfy to type requirements. Discarded. Action '".@$action["name"]."'. URI: '".$S["_URI"]."'.");
                        break;        
                    case "name":
                        if (function_exists("validate_name")){
                            $tmp = validate_name($tmp);
                        }else{
                            $tmp = (is_string($tmp) && ((int)$tmp == 0) ) ? $tmp : NULL;
                        };
                        if ($tmp ===NULL) dosyslog(__FUNCTION__.": ERROR: Parameter '".@$fparam_name."' of type '".@$fparam["type"]."' does not satisfy to type requirements. Discarded. Action '".@$action["name"]."'. URI: '".$S["_URI"]."'.");
                        break;
                    case "xml": 
                        if (is_string($tmp)){
                            $tmp = simplexml_load_string($tmp);
                            if ($tmp === false){
                                dosyslog(__FUNCTION__.": ERROR : Parameter '".@$fparam_name."' of type ''xml' can not be parsed by simplexml. First 100 bytes: '".htmlspecialchars(substr($tmp,0,100))."'.");
                                $tmp = NULL;
                            };
                        }else{
                            if (is_object($tmp)){
                                if (get_class($tmp) !== "SimpleXMLElement"){
                                    dosyslog(__FUNCTION__.": ERROR : Parameter '".@$fparam_name."' of type ''xml' is object but has not class SimpleXMLElement. The class is '".get_class($tmp)."'.");
                                    $tmp = NULL;
                                };
                            }else{  
                                $tmp = NULL;
                            };
                        };
                        if ($tmp ===NULL) dosyslog(__FUNCTION__.": ERROR: Parameter '".@$fparam_name."' of type '".@$fparam["type"]."' does not satisfy to type requirements. Discarded. Action '".@$action["name"]."'. URI: '".$S["_URI"]."'.");
                        break;

                    default:
                        dosyslog(__FUNCTION__.": ERROR: Parameter '".@$fparam_name."' has type '".@$fparam["type"]."' which is unsupported. Discarded. Action '".@$action["name"]."'. URI: '".$S["_URI"]."'.");
                        $tmp = NULL;
                }; // switch
            }; // if

            // dump($tmp,"type checked: param[".$fparam_name."]");
            
            
            $S[$fparam_name] = $tmp;        
            
            
            // dump($tmp,"param[".$fparam_name."]");
            
        }; // foreach
    }; // if
   
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
   
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
