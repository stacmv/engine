<?php
function validate_field_type_phone($key, $value, FormData $form){
    
    $fields = form_get_fields($form->db_table, $form->form_name);
    $field = $fields[$key];
    
    
    if ( ($field["required"] == "required") || $value ){
    
        $phone_cleared = glog_clear_phone($value); // ����� ��������, ������ �����.
        
        $res = preg_match("/^\d{10}$/",$phone_cleared);
    }else{
        $res = true;
        
    }
        
    return $res ? "" : _t("validate_phone_fail");
};
