<?php

define("SHOW_DATE_WITH_SECONDS", 1);
define("SHOW_DATE_TIME_DIFF", 2);
define("SHOW_DATE_TIME_DIFF_ONLY", 4);
define("SHOW_DATE_AGO", 8);


function add_data(FormData $data, $comment = null){

    // Validate


    if ($data->is_valid){

        $added_id = db_add($data->db_table, $data->changes, $comment);

        if ( ! $added_id ){
            $dbh = db_set($data->db_table);
            $error = $dbh->errorInfo();
            if ($error[1] == 19){
                set_session_msg($error[2],"error");
            };
            dosyslog(__FUNCTION__ . ": WARNING: ".get_callee().": Ошибка db_add().");
        };
    }else{

        dosyslog(__FUNCTION__ . ": WARNING: ".get_callee().": Данные не валидны.");
        $added_id = false;
        // foreach($data->errors as $field_name => $err){
            // set_session_msg($err["msg"], "error");
        // };

    };

    if ( $added_id ) return array(true, $added_id);
    else return array(false, "fail");

}
function dosyslog_data_changes($data_before){
    global $_DATA;

    if (!empty($_DATA)) {
        $added   = array_diff(array_keys($_DATA), array_keys($data_before));
        dosyslog("_DATA: DEBUG:" . get_callee() . " Data added: " . implode(", ", $added) . ".");
    }else{
        dosyslog("_DATA: DEBUG:" . get_callee() . " No data added.");
    }

};
function edit_data(FormData $data, $id="", $comment = null){
	global $CFG;

    if ( ! $id){
        $id = ! empty($data->id) ? $data->id : null;
    };


    if (!$id){
        dosyslog(__FUNCTION__.": FATAL ERROR: Mandatory parameter 'id' is not set. Check pages XML and edit form template.");
        die("Code: ef-" . __LINE__);
    };


    if ( $data->is_valid ){

        // dump($data->changes,"changes");die(__FUNCTION__);

        list($res, $reason) = db_edit($data->db_table, $id, $data->changes, $comment);
    }else{
        dosyslog(__FUNCTION__ . ": WARNING: ".get_callee().": Данные не валидны.");
        $res = false;
        $reason = "fail";
    }

    return array($res, $reason);

};
function get_auth_types(){
    global $CFG;

    if ( ! empty($CFG["AUTH"]["auth_types"]) ){
        $auth_types = explode(" ", $CFG["AUTH"]["auth_types"]); foreach($auth_types as $k=>$v) $auth_types[$k] = trim($v);
    }else{
        $auth_types = array("simple");
    };
    dosyslog(__FUNCTION__.": DEBUG: Auth_types: ".implode(", ",$auth_types));

    // Init auth_types
    foreach ($auth_types as $auth_type){
        $init_func = "auth_".$auth_type."_init";
        if (function_exists($init_func)){
            call_user_func($init_func);
        };
    };


    return $auth_types;
}
function get_filename($name, $ext = "") {//
	$result = glog_translit($name);

	$result = str_replace(array("+","&"," ",",",":",";",".",",","/","\\","(",")","'","\""),array("_plus_","_and_","-","-","-","-"),$result);

	$result = strtolower($result);

	$result = urlencode($result);

	$result .= $ext ;

	return $result;
};
function month_name($month_num, $genitive = false){
    return glog_month_name($month_num, $genitive ? "genitive" : false);
}

function clear_actions(){
    global $_ACTIONS;

    if (DEV_MODE){
        dosyslog(__FUNCTION__.get_callee().": DEBUG: Further ACTIONS cleared: '".json_encode($_ACTIONS)."'.");
    }else{
        dosyslog(__FUNCTION__.get_callee().": NOTICE: Further ACTIONS cleared.");
    };
    $_ACTIONS = array();
}
function redirect($redirect_uri = "", array $params = array(), $hash_uri = ""){
    global $_RESPONSE;
    global $CFG;
    global $ISREDIRECT;
    global $IS_IFRAME_MODE;


    if ($IS_IFRAME_MODE) $params["i"] = is_string($IS_IFRAME_MODE) ? $IS_IFRAME_MODE : "1";

    if ( $redirect_uri ){
        if ( ! filter_var($redirect_uri, FILTER_VALIDATE_URL) ){  // relative uri on this site, not external/full URL
             $uri = $CFG["URL"]["base"] . $redirect_uri . $CFG["URL"]["ext"];
        }else{
            $uri = $redirect_uri;
        };
    }else{
        $uri = $CFG["URL"]["base"];
    }

    if ( ! empty($params) ) $uri .= "?" . http_build_query($params);
    if ( ! empty($hash_uri) )   $uri .= "#" . $hash_uri;


    $_RESPONSE["headers"] = array("Location"=>$uri);
    $_RESPONSE["body"] = "<a href='".$uri."'>Click here</a>";

    dosyslog(__FUNCTION__.get_callee().": NOTICE: Prepare for  redirect to '".$uri."'.");

    $ISREDIRECT = true;

};
function redirect_301($redirect_uri = "", array $params = array(), $hash_uri = ""){
    global $_RESPONSE;

    redirect($redirect_uri, $params, $hash_uri);
    $_RESPONSE["headers"]["HTTP"] = "HTTP/1.1 301 Moved Permanently";
    dosyslog(__FUNCTION__.get_callee().": INFO: 301 redirect mode ON.");
};
function register_default_action($action, $prepend = false){ // регистрирует функцию, которая должна выполняться как action для каждой страницы
    global $_DEFAULT_ACTIONS;

    // Do not invoke inside this function any functions defined in other files since they may not be loaded yet.

    if ( ! isset($_DEFAULT_ACTIONS) ) $_DEFAULT_ACTIONS = array("before" => array(), "after" => array());

    if ($prepend){
        $_DEFAULT_ACTIONS["before"][] = $action;
    }else{
        $_DEFAULT_ACTIONS["after"][] = $action;
    }

}

function response_404_page(){
    global $_URI;
    global $_RESPONSE;

    dosyslog(__FUNCTION__.": WARNING: Page '".$_URI."' not found.");
    $_RESPONSE["headers"]["HTTP"] = "HTTP/1.0 404 Not Found";

    $page = find_page("error_404");
    if (!$page){
        dosyslog(__FUNCTION__.": WARNING: 404 ErrorPage not found.");
        $page = find_page("/");
    };

    return $page;
}
function show_date($timestamp, $options=0){

    if ( ! $timestamp ) return "";

    if ( ! is_numeric($timestamp) ){
        $timestamp = strtotime( glog_isodate($timestamp, true) );
    };


    if ( $options & SHOW_DATE_TIME_DIFF_ONLY ){
        $date_str = time_diff( $timestamp, time() );
        if ( $options & SHOW_DATE_AGO ){
            if ( $date_str != "только что" ){
                $date_str .= " назад";
            };
        };
        return $date_str;
    }else{
        $date_str = glog_rusdate(date("Y-m-d H:i",$timestamp), true);

        if ( ! ($options & SHOW_DATE_WITH_SECONDS) ){ // дата-время без секунд
            $date_str = substr($date_str, 0, 16);
        };

        if ( $options & SHOW_DATE_TIME_DIFF ){
            $time_diff = time_diff( $timestamp, time() );
            if ( $options & SHOW_DATE_AGO ){
                if ( $time_diff != "только что" ){
                    $time_diff .= " назад";
                };
            };
            $date_str .= " <nobr class='time_diff'>(". $time_diff . ")</nobr>";
        };
        return $date_str;
    };
}
function time_diff($from, $to){
    if ( ! is_numeric($from) ) $from = strtotime($from);
    if ( ! is_numeric($to) )   $to   = strtotime($to);

    $diff = round(abs($to - $from) / 60 / 60); // hours

    if ( $diff > 366 * 24) {
        $y = floor($diff / 24 / 366);
        $diff_msg = glog_get_num_with_unit($y, "год", "года", "лет");
    }elseif( ( $diff > 31 * 24) && ( $diff % 30 < 10 ) ){
        $m = floor($diff / 24 / 31);
        $diff_msg = $m . " мес.";
    }elseif ( $diff > 23 ){
        $d = floor($diff/24);
        $h = $diff = $d * 24;
        $diff_msg = $d . " дн.";
    }elseif ( $diff > 1 ){
        $diff_msg = $diff . " час.";
    }else{

        $diff = abs($to - $from) ; // seconds

        if ( $diff < 30 ){
            $diff_msg = "только что";
        }elseif( $diff <= 60 ){
            $diff_msg = "менее минуты";
        }else{

            $m = round($diff / 60);
            $diff_msg = $m . " мин.";
        }

        ;
    }

    return $diff_msg;
}
