<?php
function get_geoip($ip = ""){
    
    if (!$ip) $ip = $_SERVER["REMOTE_ADDR"];
    
    $geo_data = xml_load_file("http://ipgeobase.ru:7020/geo?ip=" . $ip);
    if (is_a($geo_data, "SimpleXMLElement")){
        $res =  xml_to_array($geo_data);
        
        $res = $res["ip"];
        $res["ip"] = $res["@attributes"]["value"];
        unset($res["@attributes"]);
        
    }else{
        $res =  array();
    };
        
    return $res;
};