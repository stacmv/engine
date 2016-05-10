<?php
function check_data_item_acl($item, $db_table){
    $right = db_get_meta($db_table, "acl");
    if (! $right) return true;
    return userHasRight($right, "", $item);
}
function check_form_field_acl($field){
    if ( ! isset($field["acl"])) return true;
    return userHasRight($field["acl"]);
}