<?php
define("XML_DONT_STOP_ON_ERRORS", true);

function xml_load($resource_type, $resource, $dont_stop_on_errors = false){
    libxml_use_internal_errors(true);
    switch ($resource_type){
    case "file":
        $sxe = simplexml_load_file($resource);
        break;
    case "string":
        $sxe = simplexml_load_string($resource);
        break;
    default:
        dosyslog(__FUNCTION__.": ERROR: Wrong resource_type '".$resource_type."'. Should be 'file' or 'string'.");
        return null;
    };

    if (!$sxe) {
        foreach(libxml_get_errors() as $error) {
            $error_type = $dont_stop_on_errors ? "ERROR:" : "FATAL ERROR:";
            if ($resource_type == "file"){
                dosyslog(__FUNCTION__.": ".$error_type . get_callee() .": XML ERROR in file '".$resource."': " . trim($error->message) );
            }else{
                dosyslog(__FUNCTION__.": ".$error_type . get_callee() .": XML ERROR in string: " . trim($error->message) );
            };
            if ( ! $dont_stop_on_errors) die("Code: ex-".__LINE__."-".basename($resource).(DEV_MODE ? "-".trim($error->message) : ""));
            libxml_clear_errors();
        }
    }
    libxml_use_internal_errors(false);

    return $sxe;
}
function xml_load_file($file, $dont_stop_on_errors = false){
    return xml_load("file", $file, $dont_stop_on_errors);
}
function xml_load_string($str, $dont_stop_on_errors = false){
    return xml_load("string", $str, $dont_stop_on_errors);
}

function xml_to_array ( $xml ){
    $json = json_encode($xml);
    return json_decode($json,true);
};
