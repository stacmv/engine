<?php
function _t($msg){
    static $tsv;
    
    if ( empty($tsv) ){
        $tmp = import_tsv(cfg_get_filename("settings", "messages_comments.tsv"));
        
        $tsv = array();
        foreach($tmp as $row){
            $tsv[ $row["name"] ] = $row;
        };
    };
    
    return isset($tsv[$msg]["caption"]) ? $tsv[$msg]["caption"] : $msg;
}