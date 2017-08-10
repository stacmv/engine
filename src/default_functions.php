<?php
define("ENGINE_SCOPE_ENGINE", 1);
define("ENGINE_SCOPE_APP", 2);
define("ENGINE_SCOPE_SITE", 4);
define("ENGINE_SCOPE_ALL", null);

if (!function_exists("cfg_get_filename")){
    function cfg_get_filename($type, $filename, $scope = ENGINE_SCOPE_ALL){
        // scope == ENGINE_SCOPE_ENGINE - get file from engine
        // scope == ENGINE_SCOPE_APP - get file from app
        // scope == ENGINE_SCOPE_SITE - get file from app's specific site
        // scope == ENGINE_SCOPE_ALL - get file from site, then if not found, from app then if not found get it from engine

        if (cached()) return cache();

        // Type whitelist
        $types = array("templates/form", "templates", "settings", "email_templates", "sms_templates", "classes" );
        if ( ! in_array($type, $types) ){
            dosyslog(__FUNCTION__.": FATAL ERROR: Unknown type '".$type."' for file '".$filename."'.");
            die("Code: df-".__LINE__);
        };



        $path = array();
        switch($scope){
        case ENGINE_SCOPE_ENGINE:
            $path[] = ENGINE_DIR;
            break;
        case ENGINE_SCOPE_APP:
            $path[] = APP_DIR;
            break;
        case ENGINE_SCOPE_SITE:
            $path[] = SITE_DIR;
            break;
        case ENGINE_SCOPE_ALL:
        default:
            $path[] = SITE_DIR;
            $path[] = APP_DIR;
            $path[] = ENGINE_DIR;
        };

        foreach($path as $p){
            $test_filename = $p . $type . "/" . $filename;
            if (file_exists($test_filename)){

                return cache($test_filename);
            };
        };

        return cache("");
    }
}
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
    function dosyslog($msg, $flush=false){
        if (function_exists("glog_dosyslog")){
            glog_dosyslog($msg, $flush);
        }else{
            die("Err: Neither app dosyslog() nor glog_dosyslog() are defined.");
        };
    };
};
if (!function_exists("find_page")){
    function find_page($uri){
        global $CFG;

        $pages = get_pages();


        if ($pages){

			// Find page by direct URI
            $page = get_page_by_uri($pages,$uri);

			// If not found find page by indirect (partial) URI
            if ( ! $page && ("/" != $uri) ) {

                if (!$page) {
                    // отбрасываем якорь
                    $tmp = explode("#",$uri,2);
                    if (count($tmp) == 2){
                        $uri = $tmp[0];
                        $page = get_page_by_uri($pages, $uri);
                    };
                };

                if (!$page) {
                    // отбрасываем GET параметры
                    $tmp = explode("?",$uri, 2);
                    if (count($tmp) == 2){
                        $uri = $tmp[0];
                        $page = get_page_by_uri($pages, $uri);
                    };
                };

                if (!$page) {
                    // двигаемся вверх по иерархии, к корню

                    while ( ("" != $uri) && !$page ){

                        $tmp = explode("/",$uri);
                        if (count($tmp)>1){
                            unset($tmp[count($tmp)-1]);
                            $uri = implode("/",$tmp);
                            $page = get_page_by_uri($pages,$uri);
                        }else{
                           break;
                        };

                    };
                };
			};

			// If not found find default page
			if ( ! $page ){
				if ( ! empty($CFG["URL"]["default"])){
					dosyslog(__FUNCTION__.get_callee().": DEBUG: Getting default page '".$CFG["URL"]["default"]."'.");
					$page = get_page_by_uri($pages, $CFG["URL"]["default"]);
				};
			};

			// If found set some META data
            if ( ! empty($page)){
                if (!isset($page["header"]) )      $page["header"] = isset($page["title"]) ? $page["title"] : "";
                if (!isset($page["description"]) ) $page["description"] = "";
                if (!isset($page["keywords"]) )    $page["keywords"] = "";
            };

        } else {
            dosyslog(__FUNCTION__.": FATAL ERROR: Can not load pages files.");
            die("Code: df-".__LINE__);
        };

        return $page;
    };
}
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
if (!function_exists("get_db_files")){
    function get_db_files(){
        // Если в разных файлах определены одинаковые базы, то используется те, что определены (в db_files) РАНЬШЕ

        static $db_files = null;

        if (is_null($db_files)){

            $db_files = array();
            $specific_dbs = glob(APP_DIR . "settings/*.db.xml");
            if ( ! empty($specific_dbs) ){
                foreach($specific_dbs as $file){
                    $start = strlen(APP_DIR . "settings/");
                    $length  = strlen($file) - strlen(".db.xml") - $start;
                    $key = substr($file, $start, $length);
                    $db_files[$key] = $file;
                };
                unset($file, $start,$length, $key);
            }


            $db_files["site"]   = cfg_get_filename("settings", "db.xml", ENGINE_SCOPE_SITE);
            $db_files["app"]    = cfg_get_filename("settings", "db.xml", ENGINE_SCOPE_APP);
            $db_files["engine"] = cfg_get_filename("settings", "db.xml", ENGINE_SCOPE_ENGINE);

            $db_files = array_filter($db_files, function($file){
                return file_exists($file);
            });

            dosyslog(__FUNCTION__.get_callee().": DEBUG: Db_files: ".implode(", ", array_values($db_files)));

        }

        return $db_files;

    }
}
if (!function_exists("get_gravatar")){
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
    function get_page_by_uri($pages, $uri){

		dosyslog(__FUNCTION__.get_callee().": DEBUG: Searching page with uri '".$uri."'.");

        if( isset($pages[$uri]) ){
           return $pages[$uri];
        }else{
            dosyslog(__FUNCTION__.get_callee().": DEBUG: Page with uri '".$uri."' not found.");
        };

        return false;
    };
};
if (!function_exists("get_page_files")){
    function get_page_files(){
        global $_SITE;
        // Если в разных файлах определены одинаковые страницы, то используется те, что определены (в pages_files) ПОЗЖЕ

        $pages_files = array(
            "engine_json" => cfg_get_filename("settings", "pages.json", ENGINE_SCOPE_ENGINE),
            "engine_xml"  => cfg_get_filename("settings", "pages.xml", ENGINE_SCOPE_ENGINE),
            "engine_api"  => cfg_get_filename("settings", "api.pages.xml", ENGINE_SCOPE_ENGINE),
            "image_xml"  => cfg_get_filename("settings", "image.pages.xml", ENGINE_SCOPE_ENGINE),
            "app_json"    => cfg_get_filename("settings", "pages.json", ENGINE_SCOPE_APP),
            "app_xml"     => cfg_get_filename("settings", "pages.xml", ENGINE_SCOPE_APP),
            "site_json"    => cfg_get_filename("settings", "pages.json", ENGINE_SCOPE_SITE),
            "site_xml"     => cfg_get_filename("settings", "pages.xml", ENGINE_SCOPE_SITE),
        );


        $pages_files = array_filter($pages_files, function($file){ return file_exists($file);});

        $extra_pages = array_merge(
                glob(ENGINE_DIR . "settings/*.pages.{json,xml}", GLOB_BRACE),
                glob(APP_DIR . "settings/*.pages.{json,xml}", GLOB_BRACE),
                glob(SITE_DIR . "settings/*.pages.{json,xml}", GLOB_BRACE)
        );
        if ( ! empty($extra_pages) ){
            foreach($extra_pages as $file){
                // $start = strlen(APP_DIR . "settings/");
                // $length  = strlen($file) - strlen(".pages.json") - $start;
                // $key = substr($file, $start, $length);
                $key = basename($file);
                $pages_files[$key] = $file;
            };
            unset($file, $start,$length, $key);
        }

         dosyslog(__FUNCTION__.get_callee().": DEBUG: Pages_files: ".implode(", ", array_values($pages_files)));

        // dump($pages_files,"page_files");die();
        return $pages_files;

    }
}
if (!function_exists("get_pages")){
    function get_pages(){
        static $pages;
        global $CFG;

        if (!empty($pages)) return $pages;


        $xml = false;

        $pages_files = get_page_files();

        $pages = array();
        foreach($pages_files as $app=>$file){

            if (pathinfo($file, PATHINFO_EXTENSION) == "json" ){

                $str = glog_file_read($file);
                if ($str){
                    $arr = json_decode_array($str, false); // false means "do not urldecode output";
                    if ( ! $arr ){
                        dosyslog(__FUNCTION__.": FATAL ERROR: Can not decode JSON file '".$file."'.");
                        die("Code: df-".__LINE__."-".$app."_pages_json");
                    }
                }else{
                    $arr = array();
                    dosyslog(__FUNCTION__.": FATAL ERROR: Can not read file '".$file."'.");
                    die("Code: df-".__LINE__."-".$file);
                }

                $pages_tmp  = array();
                if ( !empty($arr["pages"]) ){
                    foreach($arr["pages"] as $k=>$v){
                        $pages_tmp[ $v["uri"] ] = $v;
                    };
                    unset($k,$v);
                };
                if ( $pages_tmp ){
                    $pages = array_merge($pages, $pages_tmp);
                };
                unset($pages_tmp);

            }elseif(pathinfo($file, PATHINFO_EXTENSION) == "xml" ){

                $arr = xml_to_array( xml_load_file($file) );
                $pages_tmp = array();


                if ( ! empty($arr["page"]) ){
                    if ( ! isset($arr["page"][0]) ) $arr["page"] = array($arr["page"]);

                    foreach($arr["page"] as $page){
                        // Атрибуты
                        foreach($page["@attributes"] as $k=>$v){
                            $page[$k] = $v;
                        };
                        unset($k,$v);
                        unset($page["@attributes"]);

                        foreach(array("params","actions","templates","acl") as $node){
                            if ( isset($page[$node]) && ! is_array($page[$node]) ) $page[$node] = array($page[$node]);
                        };
                        unset($node);

                        $pages_tmp[ $page["uri"] ] = $page;
                    };
                    unset($page);


                }
                if ( $pages_tmp ) $pages = array_merge($pages, $pages_tmp);
                unset($pages_tmp);



            };
        }
        unset($app,$file);

        return $pages;
    };
};
if (!function_exists("get_rights_all")){
    function get_rights_all(){

        $tsv = import_tsv( cfg_get_filename("settings", "acl.tsv") );

        $rights = array();
        foreach($tsv as $v){
            $rights[ $v["acl"] ] = $v;
        };

        return $rights;
    };
}
if (!function_exists("get_user_login")){
    function get_user_login($user_id = ""){
        global $_USER;

        if ( ! $user_id ){
            if ( isset($_USER["login"])){
                $login = $_USER["login"];
            };
        }else{

            $user = db_get("users",$user_id);

            if (!empty($user["login"])){
                return $user["login"];
            }
        }

        return "";
    }
}
if (!function_exists("logout")){
    function logout(){
        global $_USER;
        unset($_SESSION["msg"]);
        unset($_SESSION["to"]);
        unset($_SESSION["authenticated"]);

        dosyslog(__FUNCTION__.": INFO: User '".$_USER->get_login()."' logged out.");

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
if (!function_exists("register_message_opened")){
    function register_message_opened($message_id){
        $ip = ! empty($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : "_unknown_";
        $qs = ! empty($_SERVER["QUERY_STRING"]) ? $_SERVER["QUERY_STRING"] : "";

        dosyslog(__FUNCTION__.get_callee().": INFO: Email with message_id:".$message_id." was opened by user at ip:".$ip.". Query string: '".$qs."'.");
    };
};
if (!function_exists("set_template_for_user")){
    function set_template_for_user(){
        global $_USER;
        global $_PAGE;

        if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
        if ($_USER["authenticated"]){
            if ( ! empty($_PAGE["templates"]["user"])){
                set_template_file("content", $_PAGE["templates"]["user"]);
            }else{
                dosyslog(__FUNCTION__.get_callee().": FATAL ERROR: template 'user' is not set for page '".$_PAGE["uri"]."'");
                die("Code: df-".__LINE__);
            }
        }else{
            if ( ! empty($_PAGE["templates"]["guest"])){
                set_template_file("content", $_PAGE["templates"]["guest"]);
                if ( ! empty($_PAGE["templates"]["page_guest"])){
                    set_template_file("page", $_PAGE["templates"]["page_guest"]);
                };
            }else{
                dosyslog(__FUNCTION__. get_callee() . " : FATAL ERROR: template 'guest' is not set for page '".$_PAGE["uri"]."'");
                die("Code: df-".__LINE__);
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
    function userHasRight($right, $login="", $object_accessed = null, $object_acl_field = "user_id"){
        global $_USER;

        if (cached()) return cache();


        $user = array();

        // проверка комбинации прав
          // ИЛИ - |
        if (strpos($right,"|") > 0){
            $OR_rights = explode("|", $right, 2);
            return cache( userHasRight($OR_rights[0], $login, $object_accessed, $object_acl_field) || userHasRight($OR_rights[1], $login, $object_accessed, $object_acl_field) );
          // И - ,
        }elseif(strpos($right,",") > 0){
            $AND_rights = explode(",", $right, 2);
            return cache( userHasRight($AND_rights[0], $login, $object_accessed, $object_acl_field) && userHasRight($AND_rights[1], $login, $object_accessed, $object_acl_field) );
        };

        $right = trim($right);


        if ( ! $login ){
            if ( empty($_USER["acl"]) ){
                if ( $_USER->is_authenticated() ){
                    dosyslog(__FUNCTION__.": ERROR: ".$_USER["login"].": права не заданы.");
                };
                return cache(false);
            };

            $login = $_USER->get_login();
            $user = $_USER->is_authenticated() ? $_USER : array();
        }else {
            $users = EUsers::find("login", $login);
            if (count($users)==0){
                dosyslog(__FUNCTION__.": WARNING: User with login '".$login."' is not found in DB.");
                return cache(false);
            }elseif(count($users)>1){
                dosyslog(__FUNCTION__.": ERROR: More than one user with login '".$login."' is found in DB.");
                return cache(false);
            }else{
                $user = $users[0];
            };
        };

        $user_rights = !empty($user) ? $user["acl"] : array();

        // Ownership of object_accessed
        if ($object_accessed){
            $owners = !empty($object_accessed[$object_acl_field]) ? $object_accessed[$object_acl_field] : array(); // this dediсated var is needed since $object_accessed may be an object and empty() works inproper with it;
            if (! empty($owners) &&
                (
                    (is_scalar($owners) && ($owners == $user["id"])) ||
                    (is_array($owners) && in_array($user["id"], $owners))
                )
            ){
                $user_rights[] = "owner";
            };
        };

        $res = !empty($user_rights) && in_array($right, $user_rights);

        dosyslog(__FUNCTION__.": DEBUG: ".$login." ".($res?"имеет право " : "не имеет права "). $right);

        return cache($res);
    };
};
if (!function_exists("user_has_access_by_ip")){
    function user_has_access_by_ip($user_id="", $login="", $ip=""){

        global $_USER;
        if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");

        return true; // Отключить проверку IP.

        if ( userHasRight("account") || $_USER["isGuest"] || ! isset($_USER )) return true; // Не ограничивать доступ партнееров, гостей и при вызове из "неинтернактивных" сценариев (lead.php, ...)

        if ( ! $ip ) $ip = ! empty($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : "";

        if ( ! $user_id && ! $login ){
            $user_id = $_USER["id"];
            if ($_USER["authenticated"]){
                $login   = $_USER["login"];
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
