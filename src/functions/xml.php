<?php
function xml_load_file($file){
    libxml_use_internal_errors(true);
    $sxe = simplexml_load_file($file);
    if (!$sxe) {
        foreach(libxml_get_errors() as $error) {
            dosyslog(__FUNCTION__.": FATAL ERROR: ".get_callee() .": XML ERROR in file '".$file."': " . trim($error->message) );
            die("Code: ex-".__LINE__);
        }
        return false;
    }

    return $sxe;
}

// function xml_to_array ( $xmlObject, $out = array () ){
    // foreach ( (array) $xmlObject as $index => $node )
        // $out[$index] = ( is_object ( $node ) ) ? xml_to_array ( $node ) : $node;
    // return $out;
// };

function xml_to_array ( $xml ){
    $json = json_encode($xml);
    return json_decode($json,true);
};