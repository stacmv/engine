<?php
function show_money($value, $currency = "RUR", $lang = "ru"){
    if (is_numeric($value)){
        return number_format($value, 2, ",", " ");
    }else{
        return $value;
    }
}