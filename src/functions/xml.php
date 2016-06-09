<?php
define("XML_DONT_STOP_ON_ERRORS", true);
function xml_load_file($file, $dont_stop_on_errors = false){
    libxml_use_internal_errors(true);
    $sxe = simplexml_load_file($file);
    if (!$sxe) {
        foreach(libxml_get_errors() as $error) {
            $error_type = $dont_stop_on_errors ? "ERROR:" : "FATAL ERROR:";
            dosyslog(__FUNCTION__.": ".$error_type . get_callee() .": XML ERROR in file '".$file."': " . trim($error->message) );
            if ( ! $dont_stop_on_errors) die("Code: ex-".__LINE__);
            libxml_clear_errors();
        }
    }
    libxml_use_internal_errors(false);
    
    return $sxe;
}

function xml_to_array ( $xml ){
    $json = json_encode($xml);
    return json_decode($json,true);
};