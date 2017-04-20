<?php
function get_value_caption($field, $value){
    static $tsv = null;

    if (is_null($tsv)){
        $tsv = import_tsv(cfg_get_filename("settings", $field.".tsv"));
        if ($tsv){
            $tsv = arr_index($tsv, "value");
        };
    };

    if (isset($tsv[$value]) ){
        return $tsv[$value]["caption"];
    }else{
        return "(".$value.")";
    };
}