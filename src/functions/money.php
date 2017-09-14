<?php
function show_money($value, $html = true, $currency = "RUR", $lang = "ru"){
    if (is_numeric($value)){
        return number_format($value, 2, ",", ($html ? "&nbsp;" : " ") );
    }else{
        return $value;
    }
}
