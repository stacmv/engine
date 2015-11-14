<?php
function validate_field_type_phone($key, $value, $rule_params, ChangesSet $changes){
    
    $phone_cleared = glog_clear_phone($value); // номер телефона, только цифры.
    
    $res = preg_match("/^\d{10}$/",$phone_cleared);

    
    return $res ? "" : _t("validate_phone_fail");
};
