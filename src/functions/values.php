<?php
function get_tsv_value($key, $field, $value){
    
    if (cached()) return cache();

    $tsv = import_tsv(cfg_get_filename("settings", $field.".tsv"));
    if ($tsv){
        $tsv = arr_index($tsv, "value");
    };

    if (isset($tsv[$value]) ){
        return cache(isset($tsv[$value][$key]) ? $tsv[$value][$key] : "(".$value.")");
    }else{
        return cache("(".$value.")");
    };
}

function get_value_caption($field, $value){
    return get_tsv_value("caption", $field, $value);
}