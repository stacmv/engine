<?php
function arr_index(array $arr, $key = "id"){
    
    return array_reduce($arr, function($arr, $item) use ($key){
        $arr[$item[$key]] = $item;
        return $arr;
    }, array());
    
}