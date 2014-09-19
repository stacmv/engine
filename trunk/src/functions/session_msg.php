<?php
function set_session_msg($message, $class="info", array $options=array() ){
    global $CFG;
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");

    $msg = array("class"=>"alert-info", "text"=>"");
    
    // Класс сообщения
    if ( $class && ($class != "info") ){
        switch($class){
        case "error":
        case "fail":
            $class="danger";
            break;
        default:
            $class="warning";
        };
        
        $msg["class"] = "alert-".$class;
    } 
    
    // Текст сообщения
    $predefined_messages = parse_ini_file(APP_DIR . "settings/messages_done.ini", true);
    $predefined_messages = $predefined_messages["RU"];
    
    if ( isset($predefined_messages[$message]) ) {  // msg это код предопределенного сообщения
        $msg["text"] = $predefined_messages[$message];
    }else{                                      // msg это произвольный текст (html)
        $msg["text"] = $message;
    };

    //
    
    if ( ! isset($_SESSION["msg"]) || ! is_array($_SESSION["msg"]) ) $_SESSION["msg"] = array();
    $_SESSION["msg"][] = $msg;
    
    dosyslog(__FUNCTION__.": DEBUG: ". get_callee() . ": Session message added: '[".$msg["class"]."] " . $msg["text"] . "'.");
    
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
   // dump($msg);die();    
   
};