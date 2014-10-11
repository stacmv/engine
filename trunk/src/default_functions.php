<?php
/* ***********************************************************
**  ACTIONS
**
** ******************************************************** */
if (!function_exists("check_application_already_in_db")){
    function check_application_already_in_db($application){
    
        $already_in_db = array();
        
        if ( empty($application) ){
            dosyslog(__FUNCTION__.": ERROR: Application id is not set.");
            die("Code: df-".__LINE__);
        };
            
       // Проверка уникальности имени
        
        $ids = db_find("users", "name", $application["name"]);
        if (!empty($ids)){
            $already_in_db["name"]["users"] = array();
            foreach($ids as $id){
                if ($id == $application["id"]) continue;
                $already_in_db["name"]["users"][$id] = get_username_by_id($id,true);
            };
        };
        unset($ids, $id);
        
        $ids = db_find("applications", "name",$application["name"]);
        if (!empty($ids)){
            $already_in_db["name"]["applications"] = array();
            foreach($ids as $id){
                if ($id != $application["id"]){
                    $already_in_db["name"]["applications"][] = $id;
                };
            };
        };
        unset($ids, $id);
        
        
        // Проверка уникальности телефона            
        $ids = db_find("users", "phone",$application["phone"]);
        if (!empty($ids)){
            $already_in_db["phone"]["users"] = array();
            foreach($ids as $id){
                if ($id == $application["id"]) continue;
                $already_in_db["phone"]["users"][] = get_username_by_id($id, true);
            };
        };
        unset($ids, $id);
        
        $ids = db_find("accounts", "phone",$application["phone"]);
        if (!empty($ids)){
            $already_in_db["phone"]["accounts"] = array();
            foreach($ids as $id){
                if ($id == $application["id"]) continue;
                $already_in_db["phone"]["accounts"][] = get_account_name_by_id($id);
            };
        };
        unset($ids, $id);
        
        $ids = db_find("applications", "phone",$application["phone"]);
        if (!empty($ids)){
            $already_in_db["phone"]["applications"] = array();
            foreach($ids as $id){
                if ($id != $application["id"]){
                    $already_in_db["phone"]["applications"][] = $id;
                };
            };
        };
        unset($ids, $id);
        
        // Проверка уникальности e-mail
        $ids = db_find("users", "email",$application["email"]);
        if (!empty($ids)){
            $already_in_db["email"]["users"] = array();
            foreach($ids as $id){
                if ($id == $application["id"]) continue;
                $already_in_db["email"]["users"][] = get_username_by_id($id, true);
            };
        };
        unset($ids, $id);
        
        $ids = db_find("applications", "email",$application["email"]);
        if (!empty($ids)){
            $already_in_db["email"]["applications"] = array();
            foreach($ids as $id){
                if ($id != $application["id"]){
                    $already_in_db["email"]["applications"][] = $id;
                };
            };
        };
        unset($ids, $id);
        
        return $already_in_db;
    }

}
if (!function_exists("dosyslog")){
    function dosyslog($msg){
              if (function_exists("glog_dosyslog")){
            glog_dosyslog($msg);
        }else{
            die("Err: Neither app dosyslog() nor glog_dosyslog() are defined.");
        };   
    };
};
if (!function_exists("get_application_statuses")){
    function get_application_statuses(){
        return array(
            4	=>	"подтвержденная",
            8	=>	"на модерации",
            1	=>	"не подтвержденная",
            0	=>	"попытка заполнения",
            16	=>	"одобренная",
            32	=>	"отклоненная"
        );
    };
};
if (!function_exists("get_gravatar")) {
    /**
     * Get either a Gravatar URL or complete image tag for a specified email address.
     *
     * @param string $email The email address
     * @param string $s Size in pixels, defaults to 80px [ 1 - 2048 ]
     * @param string $d Default imageset to use [ 404 | mm | identicon | monsterid | wavatar ]
     * @param string $r Maximum rating (inclusive) [ g | pg | r | x ]
     * @param boole $img True to return a complete IMG tag False for just the URL
     * @param array $atts Optional, additional key/value attributes to include in the IMG tag
     * @return String containing either just a URL or a complete image tag
     * @source http://gravatar.com/site/implement/images/php/
     */
    function get_gravatar( $email, $s = 80, $d = 'mm', $r = 'g', $img = false, $atts = array() ) {
        $url = 'http://www.gravatar.com/avatar/';
        $url .= md5( strtolower( trim( $email ) ) );
        $url .= "?s=$s&d=$d&r=$r";
        if ( $img ) {
            $url = '<img src="' . $url . '"';
            foreach ( $atts as $key => $val )
                $url .= ' ' . $key . '="' . $val . '"';
            $url .= ' />';
        }
        return $url;
    }   
};
if (!function_exists("get_page_by_uri")){
    function get_page_by_uri($xml, $uri){
        
        if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
        foreach($xml as $page){
            if($page["uri"] == $uri) return $page;
        };
        if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
        return false;
    };
};
if (!function_exists("get_pages")){
    function get_pages(){
        
        global $CFG;
        if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
        $xml = false;
        

        $pages_files = array(
            "engine" => ENGINE_DIR . "settings/pages.json",
            "app"    => APP_DIR    . "settings/pages.json",
        );
        
        $pages = array();
        foreach($pages_files as $app=>$file){
            $str = glog_file_read($file);
            if ($str){
                $arr = json_decode($str, true);
                if ( ! $arr ){
                    dosyslog(__FUNCTION__.": FATAL ERROR: Can not decode JSON file '".$file."'.");
                    die("Code: df-".__LINE__."-".$app."_pages_json");
                }
            }else{
                $arr = array();
                dosyslog(__FUNCTION__.": FATAL ERROR: Can not read file '".$file."'.");
                die("Code: df-".__LINE__);
            }
            
            if ( !empty($arr["pages"]) ){
                $pages_tmp  = array();
                foreach($arr["pages"] as $k=>$v){
                    $pages_tmp[ $v["uri"] ] = $v;
                };
                unset($k,$v);
            };
            if ( $pages_tmp ) $pages = array_merge($pages, $pages_tmp);
            unset($pages_tmp);
        }
        unset($app,$file);
        
        
                
        if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
     
        return $pages;
    };
};
if (!function_exists("get_template_file")){
    // function get_template_file($template_name){
        
        // global $_PAGE;
        // global $S;
        // if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");    
        
        // $template_file = "";
    
        // if ( ! empty($_PAGE["templates"][$template_name]) ){
            // $template_file = $_PAGE["templates"][$template_name];
        // };
        
        // if( ! $template_file) {
            // dosyslog(__FUNCTION__.": WARNING: ".get_callee().": Template '".$template_name."' for page '".$_PAGE["uri"]."' is not found. Check pages file.");
        // };
        
        // dosyslog(__FUNCTION__.": NOTICE: Getting template '".$template_name."'... got '".$template_file."'.");
        // if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
        
        // return $template_file;
        
    // }; // function
};
if (!function_exists("get_user_registered_ip")){
    function get_user_registered_ip($user_id="", $login="", $ip="", $register_new_ip = false){
        // $register_new_ip    Регистрировать новые IP, если БД нет записей по (user_id, login, ip)
        
        
        global $S;
        if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
                
        $ip_records = array();
        $result = array();
        
        $where = "";
        $limit = "";
        if ( userHasRight("manager") ){
            if ($user_id == "all") $user_id = "";
            if ($login == "all") $login = "";
            if ($ip == "all") $ip = "";
            
            
            if ($user_id || $login || $ip){
                
                if ( $user_id ) $where .= "user_id = '".sqlite_escape_string($user_id)."' ";
                if ( $login || $ip ) $where .= " AND ";
                if ( $login ) $where .= " login = '".sqlite_escape_string($login)."' ";
                if ( $ip ) $where .= " AND ";
                if ( $ip ) $where .= " ip = '".sqlite_escape_string($ip)."' ";
            };
                        
            if ( ($user_id || $login) && $ip ) $limit = " LIMIT 1";

        }else{
            
            $user_id = $_USER["profile"]["id"];
            if ($_USER["isUser"]){
                $login   = $_USER["profile"]["login"];
            }
            
            $where .= "user_id = '".sqlite_escape_string($user_id)."' ";
            $where .= " AND ";
            $where .= " login = '".sqlite_escape_string($login)."' ";
            if ( $ip ) $where .= " AND ";
            if ( $ip ) $where .= " ip = '".sqlite_escape_string($ip)."' ";
            
            if ( $ip ) $limit = " LIMIT 1";

        }
        
        if ( ! $where ) $where .= "isDeleted IS NULL";
        else $where .= "AND isDeleted IS NULL";
        
        $query = "SELECT * FROM ip WHERE ". $where . $limit . ";";
        
        
        $ip_records = db_select("users.ip", $query);
        
        if (empty($ip_records)){
            dosyslog(__FUNCTION__.": WARNING: No IP records found.");
            
            if ($register_new_ip){
                if ( register_user_ip($user_id, $login, $ip, false) ){
                    dosyslog(__FUNCTION__ . ": WARNING: Registered new IP (".$ip.") for user (".$user_id.", ".$login.").");
                    $result = get_user_registered_ip($user_id, $login, $ip, false);
                    
                }else{
                    dosyslog(__FUNCTION__ . ": ERROR: Failed registering new IP (".$ip.") for user (".$user_id.", ".$login.").");
                }
            };
            
        }else{
            if ( ($user_id || $login) && $ip ) $result = $ip_records[0];
            else $result = $ip_records;
        }
           
        if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
        return $result;   
    }
}
if (!function_exists("logout")){
    function logout(){
        
        global $S;
        if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
        
        unset($_SESSION["msg"]);
        unset($_SESSION["to"]);        
        $_SESSION["NOTLOGGED"] = true;
        unset($_SESSION["LOGGEDAS"]);
        if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    };
};
if (!function_exists("register_user_ip")) {
    function register_user_ip($user_id, $login, $ip){
        
        global $S;
        
        if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
        
        if ( ! $user_id || ! $login || ! ip ) return false;
        
        $user_id = (int) sqlite_escape_string($user_id);
        $login   = sqlite_escape_string($login);
        $ip      = sqlite_escape_string($ip);
        $allowed = "NULL";
        
        
        $query = "INSERT INTO ip (user_id , login, ip, allowed) VALUES ('" . $user_id . "', '" . $login . "', '" . $ip . "', ". $allowed . ");";
        
        $inserted_id = db_insert("users.ip", $query);
        if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
        return $inserted_id;
    }

}
if (!function_exists("send_message")){
    function send_message($emailOrUserId, $template, $data, $options=""){
        global $CFG;
        
        if (! defined("EMAIL_TEMPLATES_DIR") ){
            dosyslog(__FUNCTION__.": FATAL ERROR: Email templates dir is not defined. Emails could not be sent.");
            die("Code: df-".__LINE__);
        };
        
        if (is_numeric($emailOrUserId)){
            
            $user = db_get("users", $emailOrUserId);
            if (empty($user) || empty($user["email"])){
                dosyslog(__FUNCTION__.": User width id '".@$emailOrUserId."' is not found. Message could not be sent.");
                return false;
            };
            $email = $user["email"];
        }else{
            $email = $emailOrUserId;
        };
        
        $t = glog_file_read(EMAIL_TEMPLATES_DIR.$template.".htm");
        if (empty($t)){
            dosyslog(__FUNCTION__.": ERROR: Email template is empty or template file '".$template."' is not found in email templates dir.");
            if ($t == "") die("Code: df-".__LINE__); // убиваемся при ошибке конфигурирования (пустой шаблон), но работаем, если произошла ошибка чтения в продакшене
            return false;
        };
        
        // parse template.
        
        $t = str_replace("\r\n", "\n", $t);
        $t = str_replace("\r", "\n", $t);
        if (!empty($data) && is_array($data)){
            foreach($data as $k=>$v){
                $t = str_replace("%%".$k."%%", $v, $t);
            };
        };
        $t = preg_replace("/%%[^%]+%%/","",$t); // удаляем все placeholders для которых нет данных во входных параметрах.
        
        $tmp = @explode("\n\n",$t,2);
        $subject = @$tmp[0];
        $message = @$tmp[1];
        
        if (!$subject){
            dosyslog(__FUNCTION__.": WARNING: Subject is not set in email template '".$template."'.");
            $subject = "Email from ".@$_SERVER["HTTP_HOST"];
        };
        if (!$message) dosyslog(__FUNCTION__.": WARNING: Empty message body in template '".$template."'.");
        
        $res = @mail($email, $subject, $message, "FROM:".$CFG["GENERAL"]["system_email"]."\nREPLY-TO:".$CFG["GENERAL"]["admin_email"]."\ncontent-type: text/html; charset=UTF-8");
        
        dosyslog(__FUNCTION__."\t".@$template."\t".@$emailOrUserId."\t".$email."\t".($res? "success" : "fail")."\t".@$_SERVER["REMOTE_ADDR"]."\t".@$_SERVER["QUERY_STRING"], @LOGS_DIR."send_message.".date("Y-m-d").".log.txt");
        
        return $res;    
    };
};
if (!function_exists("set_template_for_user")){
    function set_template_for_user(){
        global $_USER;
        global $_PAGE;
        
        if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
        if ($_USER["isUser"] && !$_USER["isGuest"]){
            if ( ! empty($_PAGE["templates"]["user"])){
                set_template_file("content", $_PAGE["templates"]["user"]);
            }else{
                dosyslog(__FUNCTION__.": FATAL ERROR: template 'user' is not set for page '".$_PAGE["uri"]."'");
                die("Code: ea-".__LINE__);
            }
        }else{
            if ( ! empty($_PAGE["templates"]["guest"])){
                set_template_file("content", $_PAGE["templates"]["guest"]);
                if ( ! empty($_PAGE["templates"]["page_guest"])){
                    set_template_file("page", $_PAGE["templates"]["page_guest"]);
                };
            }else{
                dosyslog(__FUNCTION__.": FATAL ERROR: template 'guest' is not set for page '".$_PAGE["uri"]."'");
                die("Code: ea-".__LINE__);
            }
        };

        if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    };
};
if (!function_exists("show")){
    function show($var){
        global $CFG;
        
        if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
        if (function_exists("show_".$var)) return call_user_func("show_".$var);
        
        if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
        if (isset($CFG[$var])) return $CFG[$var];
        else return false;
        
    }
};
if (!function_exists("prepare_application_data")){
    function prepare_application_data($application_id){
        global $_DATA;
        
        $application = db_get("applications", $application_id);
        $already_in_db = array();
        
        if ( empty($application) ){
            dosyslog(__FUNCTION__.": ERROR: Application id is not set.");
            die("Code: df-".__LINE__);
        }else{
            
           // Проверка уникальности имени
            
            $ids = db_find("users", "name", $application["name"]);
            if (!empty($ids)){
                $already_in_db["name"]["users"] = array();
                foreach($ids as $id){
                    $already_in_db["name"]["users"][$id] = get_username_by_id($id,true);
                };
            };
            unset($ids, $id);
            
            $ids = db_find("applications", "name",$application["name"]);
            if (!empty($ids)){
                $already_in_db["name"]["applications"] = array();
                foreach($ids as $id){
                    if ($id !== $application["id"]){
                        $already_in_db["name"]["applications"][] = $id;
                    };
                };
            };
            unset($ids, $id);
            
            
            // Проверка уникальности телефона            
            $ids = db_find("users", "phone",$application["phone"]);
            if (!empty($ids)){
                $already_in_db["phone"]["users"] = array();
                foreach($ids as $id){
                    $already_in_db["phone"]["users"][] = get_username_by_id($id, true);
                };
            };
            unset($ids, $id);
            
            $ids = db_find("accounts", "phone",$application["phone"]);
            if (!empty($ids)){
                $already_in_db["phone"]["accounts"] = array();
                foreach($ids as $id){
                    $already_in_db["phone"]["accounts"][] = get_account_name_by_id($id);
                };
            };
            unset($ids, $id);
            
            $ids = db_find("applications", "phone",$application["phone"]);
            if (!empty($ids)){
                $already_in_db["phone"]["applications"] = array();
                foreach($ids as $id){
                    if ($id !== $application["id"]){
                        $already_in_db["phone"]["applications"][] = $id;
                    };
                };
            };
            unset($ids, $id);
            
            // Проверка уникальности e-mail
            $ids = db_find("users", "email",$application["email"]);
            if (!empty($ids)){
                $already_in_db["email"]["users"] = array();
                foreach($ids as $id){
                    $already_in_db["email"]["users"][] = get_username_by_id($id, true);
                };
            };
            unset($ids, $id);
            
            $ids = db_find("applications", "email",$application["email"]);
            if (!empty($ids)){
                $already_in_db["email"]["applications"] = array();
                foreach($ids as $id){
                    if ($id !== $application["id"]){
                        $already_in_db["email"]["applications"][] = $id;
                    };
                };
            };
            unset($ids, $id);
            
            // ----------------------------
        
        
            if (empty($_SESSION["to"])){ //если есть ранее введенные в форму данные, заменим ими данные, полученные из БД и покажем в полях формы.
                if(!isset($application)) $application = array();
                foreach($application as $k=>$v){
                    $_SESSION["to"][$k] = $v;
                };
            };
            
        };
        
        $_DATA["fields_form"] = form_prepare("applications", "edit_application"); // поля заявки, которые заполнил пользователь
        $_DATA["fields_db"]   = form_prepare("applications", "approve_application"); // поля, которые должны быть заполнены для регистрации (поля формы + поля, которые заполнит менеджер)
        
        $_DATA["application"] = $application;
        $_DATA["already_in_db"] = $already_in_db;
        $_DATA["statuses"] = get_application_statuses();
        
   };
};
if (!function_exists("show_forbiden")){
    function show_forbiden(){
        
        global $S;
        
        if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
        $_RESPONSE["headers"]["HTTP"] = "HTTP 1/0 403 Forbiden";
        set_content("page", "<h1>Доступ запрещен.</h1>");
        if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    };
};
if (!function_exists("show_page")){
    function show_page(){
        
        global $S;
        
        if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
        
        
        if (empty($_PAGE["templates"])){
            dosyslog(__FUNCTION__.": FATAL ERROR: There no templates defined for page '".$_PAGE["uri"]."'. Check pages file.");
            die();
        };
        
        $articles_dir = APP_DIR . "content/";
        $template_name = "content";
        $isFound = false;
        
        
        foreach($_PAGE["templates"]->template as $xmltemplate){
            if ($xmltemplate["name"] == $template_name){
                $template = (string) $xmltemplate;
                if (file_exists($articles_dir . $template)){
                    $xmltemplate[0] = str_replace(APP_DIR, "../", $articles_dir) . $template;
                }else{  
                    dosyslog(__FUNCTION__.": FATAL ERROR: Article file '".$articles_dir . $template."' is not found.");
                    die();
                };
                $isFound = true;
            };
            if ($isFound) break;
        };
        
        if ( ! $isFound ) dosyslog(__FUNCTION__.": WARNING: Template 'content' is not found in page templates. Check pages file.");
        
        if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    };
};
if (!function_exists("userHasRight")){
    function userHasRight($right,$login=""){
        global $_USER;
        $user = array();
        if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
        
        
        
        if ( ! $login ){
            if ( empty($_USER["profile"]["acl"]) ){
                if ( ! $_USER["isGuest"] ){
                    dosyslog(__FUNCTION__.": ERROR: ".$login.": права не заданы.");
                };
                return false;
            };
            $login = $_USER["profile"]["login"];
            $user_rights = $_USER["profile"]["acl"];
        }else {
            $users_ids = db_find("users", "login", $login);
            if (count($users_ids)==0){
                dosyslog(__FUNCTION__.": WARNING: User with login '".$login."' is not found in DB.");
                return false;
            }elseif(count($users_ids)>1){
                dosyslog(__FUNCTION__.": ERROR: More than one user with login '".$login."' is found in DB.");
                return false;
            }else{
                $user = db_get("users", $users_ids[0]);
                $user_rights = $user["acl"];
            };
        };
        $res = in_array($right, $user_rights);
        
        if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
        
        // dosyslog(__FUNCTION__.": DEBUG: ".$login." ".($res?"имеет право " : "не имеет права "). $right);
        
        return $res;
    };
};
if (!function_exists("user_has_access_by_ip")){
    function user_has_access_by_ip($user_id="", $login="", $ip=""){
        
        global $_USER;
        if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
        
        return true; // Отключить проверку IP.
        
        if ( userHasRight("account") || $_USER["isGuest"] || ! isset($_USER )) return true; // Не ограничивать доступ партнееров, гостей и при вызове из "неинтернактивных" сценариев (lead.php, ...)
        
        if ( ! $ip ) $ip = @$_SERVER["REMOTE_ADDR"];
        
        if ( ! $user_id && ! $login ){
            $user_id = $_USER["profile"]["id"];
            if ($_USER["isUser"]){
                $login   = $_USER["profile"]["login"];
            }
        }elseif( ! $user_id && $login ){
            $user = get_user_by_login($login);
            $user_id = $user["id"];
        }elseif( $user_id && ! $login ){
            $login = get_username_by_id($user_id, true);
        }
        
        $ip_record = get_user_registered_ip($user_id, $login, $ip, true);
        
        if ( empty($ip_record["user_id"]) || empty($ip_record["login"]) || empty($ip_record["ip"]) ){
            s($ip_record);
            die("Code: df- platform-security-issue-1");
        }
        
        if ( ($ip_record["user_id"] == $user_id) && ($ip_record["login"] == $login) && ($ip_record["ip"] == $ip) ){
        
            if ( ! empty($ip_record["allowed"]) && ( $ip_record["allowed"] < time() ) ) return true;
            else return false;            
            
        }else{
            die("Code: df- platform-security-issue-2");
        }
        
        return false;
    }
}

