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
    return preg_match("/^[АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯабвгдеёжзийклмнопрстуфхцчшщъыьэюя\-\s]+$/",$value) ? "" : _t("validate_name_fail");
}
