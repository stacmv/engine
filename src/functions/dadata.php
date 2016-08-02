<?php
function dadata_suggest_address($address_str, $count = 1){
    // Docs: https://dadata.ru/api/suggest/#response-address
    
    $url = "https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/address";
    
    $data = array(
        "query" => $address_str,
        "count" => $count,
    );
    
    $response = dadata_request($url, $data);
    
    if ($response && !empty($response["suggestions"])){
        return $response["suggestions"];
    }else{
        return array();
    };
}

function dadata_suggest_bank($bank_str){
    // Docs: https://dadata.ru/api/suggest/#response-bank
    
    $url = "https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/bank";
    
    $data = array(
        "query" => $bank_str,
    );
    
    $response = dadata_request($url, $data);
    
    if ($response && !empty($response["suggestions"])){
        return $response["suggestions"];
    }else{
        return array();
    };
}

function dadata_suggest_organization($org_name){
    // Docs: https://dadata.ru/api/suggest/#response-party
    
    $url = " https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/party";
    
    $data = array(
        "query" => $org_name,
    );
    
    $response = dadata_request($url, $data);
    
    if ($response && !empty($response["suggestions"])){
        return $response["suggestions"];
    }else{
        return array();
    };
}

function dadata_request($url, $data){
    global $CFG;
    
    $extra_headers = array(
        "Accept"        => "Accept: application/json",
        "Authorization" => "Authorization: Token " . $CFG["DADATA"]["token"],
    );
    
    $json =  glog_http_post($url, $data, true, "application/json", "", $extra_headers);
   
    if ($json){
        return json_decode($json, true);
    }else{
        dosyslog(__FUNCTION__.get_callee().": ERROR: Bad response for $url and '".json_encode($data)."'.");
        return array();
    }
    
}