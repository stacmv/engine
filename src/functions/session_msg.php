<?php
function set_session_msg($message, $class="info", array $options=array() ){
    global $CFG;
    global $IS_AJAX;
    global $_DATA;

    $lang = "RU";
    $msg = array("class"=>"alert-info", "text"=>"");

    // Класс сообщения
    if ( $class && ($class != "info") ){
        switch($class){
        case "success":
            $class="success";
            break;
        case "error":
        case "fail":
            $class="danger";
            break;
        case "no_changes":
            $class="info";
            break;
        default:
            $class="warning";
        };

        $msg["class"] = "alert-".$class;
    }

    // Текст сообщения

         // Core app messages
    $app_messages = parse_ini_file( cfg_get_filename("settings", "messages_done.ini"), true);
    $app_messages = $app_messages[$lang];
        // Modules' messages
    $modules_messages = array_reduce(engine_modules(), function($modules_messages, $module) use ($lang){
        $messages_file = cfg_get_filename("settings", "messages_done.ini", ENGINE_SCOPE_MODULE, $module);
        if ($messages_file){
            $module_messages = parse_ini_file( $messages_file, true);
            $module_messages = $module_messages[$lang];

            $modules_messages = $modules_messages + $module_messages;
        };
        return $modules_messages;
    }, array());

    $predefined_messages = $app_messages + $modules_messages; // module' messages does not overwrite app' messages.

    if ( isset($predefined_messages[$message]) ) {  // msg это код предопределенного сообщения
        $msg["text"] = $predefined_messages[$message];
        $msg["class"] .= " ".$message;
    }else{                                      // msg это произвольный текст (html)
        $msg["text"] = $message;
    };

    //

    if ($IS_AJAX && !isset($options["ignoreAjax"])){
        if ( ! isset($_DATA["msg"]) || ! is_array($_DATA["msg"]) ) $_DATA["msg"] = array();
        $_DATA["msg"][] = $msg;
    } else {
        if ( ! isset($_SESSION["msg"]) || ! is_array($_SESSION["msg"]) ) $_SESSION["msg"] = array();
        $_SESSION["msg"][] = $msg;
    };

    dosyslog(__FUNCTION__.": DEBUG: ". get_callee() . ": Session message added: '[".$msg["class"]."] " . $msg["text"] . "'.");

};
