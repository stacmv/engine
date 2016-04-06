<?php
function validate_rule_phone($key, $value, $rule_params, FormData $form){
    return validate_field_type_phone($key, $value, $form);
}
function validate_field_type_phone($key, $value, FormData $form){
    
    $fields = form_get_fields($form->db_table, $form->form_name);
    $field = $fields[$key];
    
    
    if ( ($field["required"] == "required") || $value ){
        
        $phoneUtil = \libphonenumber\PhoneNumberUtil::getInstance();
        
        try {
            $phoneProto = $phoneUtil->parse($value, "RU");
            $res= $phoneUtil->isValidNumber($phoneProto);
        } catch (\libphonenumber\NumberParseException $e) {
            $res = false;
        }
        
    }else{
        $res = true;
        
    }
        
    return $res ? "" : _t("validate_phone_fail");
};
