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
if (!function_exists("get_user_login")){
    function get_user_login($user_id = ""){
        global $_USER;

        if ( ! $user_id ){
            if ( isset($_USER["login"])){
                $login = $_USER["login"];
            };
        }else{

            $user = db_get("users",$user_id);

            if (!empty($user["login"])){
                return $user["login"];
            }
        }

        return "";
    }
}
if (!function_exists("get_user_sex")){
    function get_user_sex($user_id = ""){
        global $_USER;

        if ( ! $user_id ){
            if ( isset($_USER["sex"])){
                $login = $_USER["sex"];
            };
        }else{

            $user = db_get("users",$user_id);

            if (!empty($user["sex"])){
                return $user["sex"];
            }
        }

        return "female";
    }
}
