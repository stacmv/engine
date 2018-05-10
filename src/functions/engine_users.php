<?php
if (!function_exists("get_user_name")){
    function get_user_name($user){
        if ($user === 0) return _t("System");
        if ( is_numeric($user) ) $user = db_get("users", $user);

        if ($user && !empty($user["name"])){
            return $user["name"];
        }elseif($user && !empty($user["login"])){
            return _t("User") . " " . $user["login"];
        }else{
            return _t("Unknown");
        };
    }
}
