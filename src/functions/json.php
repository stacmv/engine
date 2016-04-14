<?php
function json_encode_array($arr){
    return urldecode(json_encode( json_array_urlencode($arr) ));
};
function json_decode_array($str, $urldecode = true){
    $arr = @json_decode($str , true);
    
    switch (json_last_error()) {
        case JSON_ERROR_NONE:
        break;
        case JSON_ERROR_DEPTH:
            dosyslog(__FUNCTION__.get_callee() . ": ERROR: JSON: Достигнута максимальная глубина стека");
        break;
        case JSON_ERROR_STATE_MISMATCH:
            dosyslog(__FUNCTION__.get_callee() . ": ERROR: JSON: Некорректные разряды или не совпадение режимов");
        break;
        case JSON_ERROR_CTRL_CHAR:
            dosyslog(__FUNCTION__.get_callee() . ": ERROR: JSON: Некорректный управляющий символ");
        break;
        case JSON_ERROR_SYNTAX:
            dosyslog(__FUNCTION__.get_callee() . ": ERROR: JSON: Синтаксическая ошибка, не корректный JSON");
        break;
        case JSON_ERROR_UTF8:
            dosyslog(__FUNCTION__.get_callee() . ": ERROR: JSON: Некорректные символы UTF-8, возможно неверная кодировка");
        break;
        default:
            dosyslog(__FUNCTION__.get_callee() . ": ERROR: JSON: Неизвестная ошибка");
        break;
    }
   
    
    return $urldecode ? json_array_urldecode($arr) : $arr;
};
function json_array_urlencode($arr){
    
    if(is_array($arr)){
        foreach($arr as $k=>$v){
        
            if (is_array($v)){
                $arr[$k] = json_array_urlencode($v);
            }elseif( ! is_numeric($v) ){
                $arr[$k] = urlencode(addslashes($v));
            };
        };
    }else{
        $arr = urlencode($arr);
    }

    return $arr;
};
function json_array_urldecode($arr){
    
    if (is_array($arr)){
        foreach($arr as $k=>$v){
        
            if (is_array($v)){
                $arr[$k] = json_array_urldecode($v);
            }else{
                $arr[$k] = stripslashes(urldecode($v));
            };
        };
    }else{
        $arr = urldecode($arr);
    };

    return $arr;
};
