<?php
function validate_rule_url($key, $value, $rule_params, FormData $form){
    
    $fields = form_get_fields($form->db_table, $form->form_name);
    $field = $fields[$key];

    if ( ($field["required"] == "required") || $value ){
        $res = ( filter_var($value, FILTER_VALIDATE_URL) && preg_match("/^https?:\/\//", $value) );
    }else{
        $res = true;
    }
    
    
    return $res ? "" : _t("validate_url_fail");
};
