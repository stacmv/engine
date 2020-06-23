<?php
function arr_extract(array $arr, $key = "id"){

    return array_map(function($item) use ($key){
        return isset($item[$key]) ? $item[$key] : null;
    }, $arr);

}
function arr_index(array $arr, $key = "id"){

    return array_reduce($arr, function($arr, $item) use ($key){
        $arr_key = arr__key($item[$key]);
        $arr[$arr_key] = $item;
        return $arr;
    }, array());

}

function arr_group(array $arr, $key){

    return array_reduce($arr, function($arr, $item) use ($key){
        $arr_key = arr__key($item[$key]);
        if (!isset($arr[$arr_key])) $arr[$arr_key] = array();
        $arr[$arr_key][] = $item;
        return $arr;
    }, array());

}

function arr_filter_keys(array $arr, array $keys){
  $res = array();
  foreach($arr as $k=>$v){
    if (in_array($k, $keys)){
      $res[$k] = $v;
    };
  };
  return $res;
}

function arr__key($key_value){
    $type = gettype($key_value);

    // Only integer and string keys are allowed as array keys
    switch($type){
        case "float":
        case "double":
            // by default PHP will cast double to integer, so data will bee lost (for irrational or big numbers)
            return (string) $key_value;
        case "integer":
        case "string":
        default:
            return $key_value;
    }
}
