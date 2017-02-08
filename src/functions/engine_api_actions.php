<?php
function engine_api_create_slug_action(){
    global $_PARAMS;
    global $_DATA;
    global $IS_API_CALL;
    
    $str = ! empty($_PARAMS["str"]) ? $_PARAMS["str"] : null;
    
    $_DATA["html"] = "";
    
    if ($str){
        if ( (int) $str && strlen( (string) (int) $str) <= 3){
            $str = glog_str_from_num( (int) $str) . " " .  ltrim(substr($str, strlen( (string) (int) $str)));
        };
        $_DATA["html"] = glog_codify($str);
    };
    
    // $IS_API_CALL = true;
}

function engine_api_upload_action(){
    global $_PARAMS;
    global $_DATA;
    global $IS_API_CALL;
    global $IS_AJAX;
    global $CFG;
    
   
    
    if (!empty($_FILES["to"]["name"])){
        $param_name = key($_FILES["to"]["name"]);
    
        if (is_string($param_name)){
    
            $res = upload_file($param_name,"api");
    
            if ($res[0]){ // success
                $filename = $res[1];
                
                $_DATA = array(
                    "initialPreview" => array(
                        array(
                            $filename,
                        ),
                    ),
                    "initialPreviewConfig" => array(
                        array(
                            "caption" => basename($filename),
                            "url" => $CFG["URL"]["base"]."engine/api/delete_file/".md5($filename).$CFG["URL"]["ext"],
                        ),
                    )
                );
            
            }else{
                
                $_DATA = array(
                    "error" => $res[1],
                );
                
            };
            
        } else {
            $_DATA = array(
                "error" => "Wrong param_name",
            );
        }
        
    } else {
        $_DATA = array(
            "error" => "No file",
        );
    }
            
    if (!$IS_AJAX){
         $IS_API_CALL = true;
        echo json_encode($_DATA);
    };
    
}