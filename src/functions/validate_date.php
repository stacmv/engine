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
    
    $fields = form_get_fields($form->db_table, $form->form_name);
    $field = $fields[$key];
    
    if ( ($field["required"] == "required") || $value ){
        $res = $value == glog_isodate(strtotime($value));
    }else{
        $res = true;
    }
    
    return  $res == true ? "" : _t("validate_date_fail");
}
