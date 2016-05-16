<?php

function _quote($str){
    
    return "&laquo;" . $str . "&raquo;";
    
}
function _t($msg){
    static $tsv;
    
    if ( empty($tsv) ){
        $tmp = import_tsv(cfg_get_filename("settings", "messages_comments.tsv"));
        
        $tsv = array();
        if ( ! empty($tmp)) {
            foreach($tmp as $row){
                $tsv[ $row["name"] ] = $row;
            };
        };
    };
    
    return isset($tsv[$msg]["caption"]) ? $tsv[$msg]["caption"] : $msg;
}

function _plural($word){
    
    // Array $_plural is from https://github.com/cakephp/cakephp/blob/master/src/Utility/Inflector.php
    // TODO Support irregular words and _singular()
    
    static $_plural = array(
        '/(s)tatus$/i' => '\1tatuses',
        '/(quiz)$/i' => '\1zes',
        '/^(ox)$/i' => '\1\2en',
        '/([m|l])ouse$/i' => '\1ice',
        '/(matr|vert|ind)(ix|ex)$/i' => '\1ices',
        '/(x|ch|ss|sh)$/i' => '\1es',
        '/([^aeiouy]|qu)y$/i' => '\1ies',
        '/(hive)$/i' => '\1s',
        '/(chef)$/i' => '\1s',
        '/(?:([^f])fe|([lre])f)$/i' => '\1\2ves',
        '/sis$/i' => 'ses',
        '/([ti])um$/i' => '\1a',
        '/(p)erson$/i' => '\1eople',
        '/(?<!u)(m)an$/i' => '\1en',
        '/(c)hild$/i' => '\1hildren',
        '/(buffal|tomat)o$/i' => '\1\2oes',
        '/(alumn|bacill|cact|foc|fung|nucle|radi|stimul|syllab|termin)us$/i' => '\1i',
        '/us$/i' => 'uses',
        '/(alias)$/i' => '\1es',
        '/(ax|cris|test)is$/i' => '\1es',
        '/s$/' => 's',
        '/^$/' => '',
        '/$/' => 's',
    );
    
    if (cached()) return cache();
    
    foreach ($_plural as $rule => $replacement) {
        if (preg_match($rule, $word)) {
            $plural_word = preg_replace($rule, $replacement, $word);
            return cache($plural_word);
        }
    }
    
}