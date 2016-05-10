<?php
function arr_index(array $arr, $key = "id"){
    
    return array_reduce($arr, function($arr, $item) use ($key){
        $arr[$item[$key]] = $item;
        return $arr;
    }, array());
    
}
function arr_group(array $arr, $key){
    
    return array_reduce($arr, function($arr, $item) use ($key){
        if (!isset($arr[$item[$key]])) $arr[$item[$key]] = array();
        $arr[$item[$key]][] = $item;
        return $arr;
    }, array());
    
}