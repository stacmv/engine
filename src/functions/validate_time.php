<?php
function validate_field_type_time($key, $value, FormData $form){
    
    $fields = form_get_fields($form->db_table, $form->form_name);
    $field = $fields[$key];
    
    if ( ($field["required"] == "required") || $value ){
        $res = preg_match("/^([01]\d|2[0-3]):([0-5]\d)$/", $value)
    }else{
        $res = true;
    }
    
    return  $res == true ? "" : _t("validate_time_fail");
}
