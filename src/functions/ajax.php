<?php
function is_ajax(){
    if (!empty($_SERVER["HTTP_X_REQUESTED_WITH"]) && ($_SERVER["HTTP_X_REQUESTED_WITH"] == "XMLHttpRequest") ){
        return true;
    }else{
        return false;
    };
}