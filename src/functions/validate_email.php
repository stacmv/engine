<?php
function validate_rule_email($key, $value, $rule_params,     FormData $form){
    return validate_field_type_email($key, $value, $form);
}
function validate_field_type_email($key, $value, FormData $form){

    $fields = form_get_fields($form->db_table, $form->form_name);
    $field = $fields[$key];
    
    
    if ( ($field["required"] == "required") || $value ){
	
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
                  // �������� � API
            };
        };
            
        if ( ! isset($res)){ // ���� ���������� �������� ����������� - ������� �������� e-mail
           $res = filter_var($value, FILTER_VALIDATE_EMAIL);
        }
        
    }else{
        $res = true;
    }
    
    return  $res == true ? "" : _t("validate_email_fail");

};
