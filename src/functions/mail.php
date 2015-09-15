<?php
function send_message($emailOrUserId, $template, $data, $options=""){
    global $CFG;
    global $_SITE;
    global $_USER; // for testing in DEV_MODE all emails will be sent to current user (supposed tester) email;
    
    $http_host = ! empty($_SERVER["HTTP_HOST"]) ? $_SERVER["HTTP_HOST"] : "";
    $ip = ! empty($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : "_unknown_";
    $qs = ! empty($_SERVER["QUERY_STRING"]) ? $_SERVER["QUERY_STRING"] : "";
    
    
    $log_msg_prolog = __FUNCTION__.get_callee() . ": INFO: ".$template." e-mail for user ".$emailOrUserId " ... ";
    
    // Check site settings
    if (isset($_SITE["site_notifications_to_send"])){ // site has any notification settings
        if (empty($_SITE["site_notifications_to_send"]) || ! in_array($template, $_SITE["site_notifications_to_send"])){
            dosyslog($log_msg_prolog . " will not be sent due to site notification settings.");
            return true;
        };
    };
    
    // Check user
    if (is_numeric($emailOrUserId)){
        
        $user = db_get("users", $emailOrUserId, DB_RETURN_DELETED);
        if (empty($user) ){
            dosyslog(__FUNCTION__.": User width id '".$emailOrUserId."' is not found. Message could not be sent.");
            return false;
        }elseif( empty($user["email"]) ){
            dosyslog(__FUNCTION__.": Email for user width id '".$emailOrUserId."' is not set.");
            return false;
        }elseif( ! filter_var($user["email"], FILTER_VALIDATE_EMAIL) ){
            dosyslog(__FUNCTION__.": Email for user width id '".$emailOrUserId."' is invalid: '".$user["email"]."'.");
            return false;
        };
        $email = $user["email"];
    }else{
        $email = $emailOrUserId;
        
    };
    
    // Check e-mail
    if( ! filter_var($email, FILTER_VALIDATE_EMAIL) ){
        dosyslog($log_msg_prolog . " will not be sent since specified email is not valid: '".$email."'.");
        if ($email == $emailOrUserId){ // wrong email is not in users db but somewhere else
            dosyslog(__FUNCTION__.get_callee() . ": ERROR: Specified email is not valid: '".$email."'.");
        };
        return false;
    };
    
    
    $message_id = md5($email . $template . serialize($data));
    $data["tracking_pixel_url"] = $CFG["URL"]["base"] . "/reg_msg_opened/" . $message_id . $CFG["URL"]["ext"];
    
    // parse template.
    $t = glog_render( cfg_get_filename("email_templates", $template.".htm"), $data );
    if (empty($t)){
        dosyslog(__FUNCTION__.": ERROR: Email template is empty.");
        if ($t == "") die("Code: df-".__LINE__); // убиваемся при ошибке конфигурирования (пустой шаблон), но работаем, если произошла ошибка чтения в продакшене
        return false;
    };
            
    $tmp = @explode("\n\n",$t,2);
    $subject = isset($tmp[0]) ? $tmp[0] : "";
    $message = isset($tmp[1]) ? $tmp[1] : "";
    $to = $email;
    
            
    if (!$subject){
        dosyslog(__FUNCTION__.": WARNING: Subject is not set in email template '".$template."'.");
        $subject = "Email from " . $http_host;
    };
    if (!$message) dosyslog(__FUNCTION__.": WARNING: Empty message body in template '".$template."'.");
    
    // //////
    if (DEV_MODE){
        $message = "Test message for: " . $email . "<hr><br>\n" . $message;
        $to = !empty($_USER["profile"]["email"]) ? $_USER["profile"]["email"] : null;
        if (!$to){
            die("Code: mail-".__LINE__."-Set_your_email_in_profile!");
        }
    };
    // //////
    
    
    $res = @mail($to, $subject, $message, "FROM:".$CFG["GENERAL"]["system_email"]."\nREPLY-TO:".$CFG["GENERAL"]["admin_email"]."\ncontent-type: text/html; charset=UTF-8");
    
    dosyslog($log_msg_prolog . " sending to email " . $email . "  " . ($to != $email ? "(really sent to " . $to . ")" : "") . " with message_id:" . $message_id . " ... " . ($res? "success" : "fail") . ". IP:" . $ip . ". Qusery string:'" . $qs . "'.");
     
    return $res;
};