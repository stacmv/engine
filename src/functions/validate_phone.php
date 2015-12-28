<?php
function validate_rule_phone($key, $value, $rule_params, FormData $form){
    return validate_field_type_phone($key, $value, $form);
}
function validate_field_type_phone($key, $value, FormData $form){
    
    $fields = form_get_fields($form->db_table, $form->form_name);
    $field = $fields[$key];
    
    
    if ( ($field["required"] == "required") || $value ){
    
        $phone_cleared = glog_clear_phone($value); // номер телефона, только цифры.
        
        
        $res = preg_match("/^\d{10}$/",$phone_cleared);
    }else{
        $res = true;
        
    }
        
    return $res ? "" : _t("validate_phone_fail");
};
