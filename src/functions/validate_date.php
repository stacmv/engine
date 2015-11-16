<?php
function validate_rule_date_future($key,$value, $rule_params, FormData $form){
    
    $res = validate_field_type_date($key,$value, $form);
    
    if ($res != ""){
        return $res;
    }else{
        return  $value >= glog_isodate() ? "" : _t("validate_date_future_fail");
    };
    
}
function validate_field_type_date($key, $value, FormData $form){
    return $value == glog_isodate(strtotime($value)) ? "" : _t("validate_date_fail");
}
