<?php
function validate_rule_fio($key, $value, $rule_params, FormData $form){
    $res = true;
    $aFio = explode(" ",$value);
    foreach($aFio as $name){
        $res = $res && (validate_field_type_name($key, $name, $form) == "");
    };

    $res = $res && (count($aFio) >= 2 ); // Фамилия, Имя, Отчество ( в т.ч. без отчества)

    return $res ? "" : _t("validate_fio_fail");
};
function validate_field_type_name($key, $value, FormData $form){

    $fields = form_get_fields($form->db_table, $form->form_name);
    $field = $fields[$key];


    if ( ($field["required"] == "required") || $value ){
        $res = validate_name($value);
    }else{
        $res = true;
    }

    return $res ? "" : sprintf(_t("validate_name_fail"), $field["label"], $field["label"]);
}

function validate_name($value){
    return preg_match("/^[АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯабвгдеёжзийклмнопрстуфхцчшщъыьэюя\-\s\.\"\(\)]+$/",$value);
}
