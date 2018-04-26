<?php
function show_money($value, $html = true, $currency = "RUR", $lang = "ru"){
    if (is_numeric($value)){
        return number_format($value, 2, ",", ($html ? "&nbsp;" : " ") );
    }else{
        return $value;
    }
}

function money_percent($value, $percent){
    
    $value_in_cents = (double) $value *100;
    
    $percent_as_multiplier = (double) $percent / 100;
    
    $result_in_cents = $value_in_cents * $percent_as_multiplier;
    
    $result = round($result_in_cents / 100, 2, PHP_ROUND_HALF_EVEN);
    
    return $result;
    
}