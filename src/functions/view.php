<?php
function prepare_view($data_item, $fields, array $options = array()){
    
    if (in_array("hide_empty", $options)){
        $data_item = array_filter($data_item);
    };
    
    $items = array($data_item);
    
    $items = form_prepare_view($items, $fields);
    
    return $items[0];
    
};