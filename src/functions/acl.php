<?php
function check_data_item_acl($item, $db_table){
    static $right = array();
    if (!isset($right[$db_table])){
        $right[$db_table] = db_get_meta($db_table, "acl");
    }
    if (! $right[$db_table]) return true;
    return userHasRight($right[$db_table], "", $item);
}
function check_form_field_acl($field){
    if ( ! isset($field["acl"])) return true;
    return userHasRight($field["acl"]);
}