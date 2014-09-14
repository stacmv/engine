<?php
/* ***********************************************************
**  ACTIONS
**
** ******************************************************** */
if (!function_exists("apply_template")) {
    function apply_template($template_name, $content_block = ""){
        global $_USER;
        global $_PAGE;
        global $CFG;
        global $_DATA;
        
        // dump($_DATA,"_DATA");
            
        if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
        
        dosyslog(__FUNCTION__.": NOTICE: Applying template '".$template_name."'.");
        
        if ( ! $content_block ) $content_block = $template_name;
        
        $HTML = "";
        
        if ( ! defined("TEMPLATES_DIR") ){
            dosyslog(__FUNCTION__.": FATAL ERROR: Templates directory is not set. Check define file.");
            die("Code: df-".__LINE__);
        };
        
        if ( empty($_PAGE["templates"]) ){
            dosyslog(__FUNCTION__.": FATAL ERROR: There no templates defined for page '".$_PAGE["uri"]."'. Check pages file.");
            die("Code: df-".__LINE__);
        };

        
        if ( ! empty($_PAGE["templates"][$template_name]) ){
            $template_file = $_PAGE["templates"][$template_name];
            $HTML = render_template($template_file, $_DATA);
        }else{
            dosyslog(__FUNCTION__.": FATAL ERROR: ".get_callee() . ": Template '".$template_name."' is not defined for page '".$_PAGE["uri"]."'. Check pages file.");
            die("Code: df-".__LINE__);
        };   
 
        set_content($content_block, $HTML);  
        if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
        return $HTML;
    };
};
if (!function_exists("dosyslog")){
    function dosyslog($msg){
              if (function_exists("glog_dosyslog")){
            glog_dosyslog($msg);
        }else{
            die("Err: Neither app dosyslog() nor glog_dosyslog() are defined.");
        };   
    };
};
if (!function_exists("get_content")){
    function get_content($block_name){
        global $S;
        global $CFG;
        global $_USER;
        global $_PAGE;
        
        static $blocks_chain = array();
        
        if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
        dosyslog(__FUNCTION__.": NOTICE: Getting content block '".$block_name."'.");
        
        if (in_array($block_name,$blocks_chain)) {
            return ""; // don't parse block if it contained in itself directly or indirectly.
        }else{
            array_push($blocks_chain, $block_name);
        };
           
        $HTML = "";
        if (!empty($_PAGE->contents)){
            $isFound = false;
            foreach($_PAGE["content"] as $xmlcontent){
                if($xmlcontent["name"] == $block_name){
                    $HTML .= (string) $xmlcontent;
                    $isFound = true;
                    dosyslog(__FUNCTION__.": NOTICE: Found block '".$block_name."' in page contents.");
                };
            };
        };
        
        if(!$HTML) {
            $HTML = apply_template($block_name);
        };
           
        if ($HTML){
            $res = preg_replace_callback("/%%([\w\d_\-\s]+)%%/",create_function('$m','return get_content($m[1]);'),$HTML); // all %%block%% replacing with result of get_content("block")
                
            $res = preg_replace_callback("/{cfg_(\w+)}/", create_function('$m', 'global $CFG; return $CFG["GENERAL"][$m[1]];'), $res);
            
            
            if ($res !== NULL) {
                $HTML = $res;
                dosyslog(__FUNCTION__.": NOTICE: Included in '".$block_name."' blocks parsed.");
            }else{
                dosyslog(__FUNCTION__.": ERROR: There is an error in preg_replace_callback() while parsing block '".$block_name."'.");
            };
        }else{
            dosyslog(__FUNCTION__.": ERROR: Content block '".$block_name."' for page '".$_PAGE["uri"]."' is not found.");
        };
        
        if (array_pop($blocks_chain) !== $block_name) {
            dosyslog(__FUNCTION__.": ERROR: Logic error in blocks chain.");
        };
           
       
        if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
        return $HTML;
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
if (!function_exists("get_page_rights")){
    function get_page_rights($page){ // input - page, output - rights list
        
        if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
        $rights = array();
        if ( ! empty($page["acl"]) ){
            if ( is_array($page["acl"]) ){
                $rights = $page["acl"];
            }else{
                dosyslog(__FUNCTION__.": FATAL ERROR: ACL for page '".$page["uri"]."' is ivalid. Check pages file.");
            }
        }else{
            dosyslog(__FUNCTION__.": NOTICE: ACL for page '".$page["uri"]."' is not set.");
        };
        if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
   
        return $rights;
    };    
};
if (!function_exists("get_pages")){
    function get_pages(){
        
        global $CFG;
        if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
        $xml = false;
        

        $pages_files = array(
            "app"    => APP_DIR    . "settings/pages.json",
            "engine" => ENGINE_DIR . "settings/pages.json",
        );
        
        $pages = array();
        foreach($pages_files as $app=>$file){
            $str = glog_file_read($file);
            if ($str){
                $arr = json_decode($str, true);
                if ( ! $arr ){
                    dosyslog(__FUNCTION__.": FATAL ERROR: Can not decode JSON file '".$file."'.");
                    die("Code: df-".__LINE__);
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
    function get_template_file($template_name){
        
        global $_PAGE;
        global $S;
        if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");    
        
        $template_file = "";
    
        if ( ! empty($_PAGE["templates"][$template_name]) ){
            $template_file = $_PAGE["templates"][$template_name];
        };
        
        if( ! $template_file) {
            dosyslog(__FUNCTION__.": WARNING: ".get_callee().": Template '".$template_name."' for page '".$_PAGE["uri"]."' is not found. Check pages file.");
        };
        
        dosyslog(__FUNCTION__.": NOTICE: Getting template '".$template_name."'... got '".$template_file."'.");
        if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
        
        return $template_file;
        
    }; // function
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
if (!function_exists("import_tsv")){
    function import_tsv($filename, $convertToUTF8=false, $returnHeaderOnly = false){
        

        $file = @file($filename);
     
        $res = false;
        if (!$file){
            dosyslog(__FUNCTION__."(".__LINE__."): ошибка: не найден или пустой файл '".$filename."'");
        }else{
            
            $header = explode("\t", trim($file[0]));
            if ($returnHeaderOnly) return $header;

            // $res = parse_csv(implode("\n", $file), "\t"); 
            
            $importer = new CsvImporter($filename, true);
            $res = $importer->get();
            
        };
        
        return $res;
    };
};
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
if (!function_exists("redirect")){
    function redirect($redirect_uri = ""){
       
        global $_RESPONSE;
        global $CFG;
        global $ISREDIRECT;
        if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
        
        $uri = $CFG["URL"]["base"];
        
        if ( $redirect_uri ) $uri .= $redirect_uri;
        
        $_RESPONSE["headers"] = array("Location"=>$uri);
        $_RESPONSE["body"] = "<a href='".$uri."'>Click here</a>";
        
        dosyslog(__FUNCTION__.": NOTICE: Prepare for  redirect to '".$uri."'.");
        
        $ISREDIRECT = true;
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
if (!function_exists("render_template")){
    function render_template($template_file, $data = array() ){
        global $CFG;
        global $_PAGE;
        global $_USER;
        
        if ( ! file_exists(TEMPLATES_DIR . $template_file)){
            dosyslog(__FUNCTION__.": FATAL ERROR: Template file '".$template_file."' is not found.");
            die("Code: df-".__LINE__);
        };
        
        if (is_array($data)) extract($data);
        ob_start();
            include TEMPLATES_DIR . $template_file;
            $HTML .= ob_get_contents();
        ob_end_clean();

        return $HTML;
    }
}
if (!function_exists("set_template_file")){
    function set_template_file($template_name,$template_file){
        global $_PAGE;

        if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb."); 
        dosyslog(__FUNCTION__.": NOTICE: Setting template '".$template_name."' < '".$template_file."'.");
    
		if ( empty($_PAGE["templates"])) {
            $_PAGE["templates"] = array();
        };
          
        $_PAGE["templates"][$template_name] = $template_file;
		
		
        if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    }; // function
};
if (!function_exists("set_content")){
    function set_content($block_name, $content){
        global $_PAGE;
        if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
        dosyslog(__FUNCTION__.": NOTICE: Setting content block '".$block_name."'.");
        
        if (empty($_PAGE["content"])) $_PAGE["content"] = array();
        $_PAGE["content"][$block_name] = $content;
        
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
        
        $articles_dir = APPLICATION_DIR . "ARTICLES/";
        $template_name = "content";
        $isFound = false;
        
        
        foreach($_PAGE["templates"]->template as $xmltemplate){
            if ($xmltemplate["name"] == $template_name){
                $template = (string) $xmltemplate;
                if (file_exists($articles_dir . $template)){
                    $xmltemplate[0] = str_replace(APPLICATION_DIR, "../", $articles_dir) . $template;
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
                return false;
            };
            $user_rights = explode(",",$_USER["profile"]["acl"]);
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
                $user_rights = explode(",",$user["acl"]);
            };
        };
        $res = in_array($right, $user_rights);
        
        if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
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

class CsvImporter 
{ 
    private $fp; 
    private $parse_header; 
    private $header; 
    private $delimiter; 
    private $length; 
    //-------------------------------------------------------------------- 
    function __construct($file_name, $parse_header=false, $delimiter="\t", $length=8000) 
    { 
        $this->fp = fopen($file_name, "r"); 
        $this->parse_header = $parse_header; 
        $this->delimiter = $delimiter; 
        $this->length = $length; 
        // $this->lines = $lines; 

        if ($this->parse_header) 
        { 
           $this->header = fgetcsv($this->fp, $this->length, $this->delimiter); 
        } 

    } 
    //-------------------------------------------------------------------- 
    function __destruct() 
    { 
        if ($this->fp) 
        { 
            fclose($this->fp); 
        } 
    } 
    //-------------------------------------------------------------------- 
    function get($max_lines=0) 
    { 
        //if $max_lines is set to 0, then get all the data 

        $data = array(); 

        if ($max_lines > 0) 
            $line_count = 0; 
        else 
            $line_count = -1; // so loop limit is ignored 

        while ($line_count < $max_lines && ($row = fgetcsv($this->fp, $this->length, $this->delimiter)) !== FALSE) 
        { 
            if ($this->parse_header) 
            { 
                foreach ($this->header as $i => $heading_i) 
                { 
                    $row_new[$heading_i] = @$row[$i]; 
                } 
                $data[] = $row_new; 
            } 
            else 
            { 
                $data[] = $row; 
            } 

            if ($max_lines > 0) 
                $line_count++; 
        } 
        return $data; 
    } 
    //-------------------------------------------------------------------- 

} 