<?php
function check_data_item_acl($item, $db_table){
    static $right = array();
    if (!isset($right[$db_table])){
        $right[$db_table] = db_get_meta($db_table, "acl");
    }
    if (! $right[$db_table]) return true;
    
    $item_acl_field = db_get_meta($db_table, "acl_field") or $item_acl_field = "user_id";
    
    
    return userHasRight($right[$db_table], "", $item, $item_acl_field);
}
function check_form_field_acl($field){
    if ( ! isset($field["acl"])) return true;
    return userHasRight($field["acl"]);
}