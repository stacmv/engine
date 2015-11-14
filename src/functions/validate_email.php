<?php
function validate_field_type_email($key, $value, $rule_params, ChangesSet $changes){
    
	
	if ( (substr($value, -8) != "@test.ru") && ! empty($CFG["VALIDATE"]["mailgun_key"]) ){
		$response = glog_http_get( "https://api:".$CFG["VALIDATE"]["mailgun_key"]."@api.mailgun.net/v2/address/validate?address=".urlencode($value) );
        if ( $response !== false ){
            $json = @json_decode($response, true );
                    
            if ( isset($json["is_valid"]) && ( $json["is_valid"] == 1 ) ){
                $res = true;
            }elseif( isset($json["is_valid"]) && ( $json["is_valid"] == 0 ) ){
                $res = false;
                
                if ( $return_supposed_email && ! empty($json["did_you_mean"]) ){
                    $res = $json["did_you_mean"];
                };
            }; 
        }else{
              // проблемы с API
        };
	};
		
	if ( ! isset($res)){ // если предыдущие проверки провалились - базовая проверка e-mail
       $res = filter_var($value, FILTER_VALIDATE_EMAIL);
	}
    
    return  $res === true ? "" : _t("validate_email_fail");

};
