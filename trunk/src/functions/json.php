<?php
function json_encode_array($arr){
    return urldecode(json_encode( json_array_urlencode($arr) ));
};
function json_decode_array($str){
    $arr = @json_decode($str , true);
    return json_array_urldecode($arr);
};
function json_array_urlencode($arr){
    
    if(is_array($arr)){
        foreach($arr as $k=>$v){
        
            if (is_array($v)){
                $arr[$k] = json_array_urlencode($v);
            }else{
                $arr[$k] = urlencode($v);
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
                $arr[$k] = urldecode($v);
            };
        };
    }else{
        $arr = urldecode($arr);
    };

    return $arr;
};
