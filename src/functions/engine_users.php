<?php
if (!function_exists("get_user_name")){
    function get_user_name($user){
        if (cached()) return cached();

        if ($user === 0) return _t("System");
        if ( is_numeric($user) ) $user = db_get("users", $user);

        if ($user && !empty($user["name"])){
            return cache($user["name"]);
        }elseif($user && !empty($user["login"])){
            return cache(_t("User") . " " . $user["login"]);
        }else{
            return cache(_t("Unknown"));
        };
    }
}
if (!function_exists("get_user_email")){
    function get_user_email($user){
        if (cached()) return cached();

        if ($user === 0) return _t("System");
        if ( is_numeric($user) ) $user = db_get("users", $user);

        if ($user) {
            return cache($user["email"]);
        } else {
            return cache("");
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

        if (cached()) return cached();

        if ( ! $user_id ){
            if ( isset($_USER["sex"])){
                return $_USER["sex"];
            };
        }else{

            $user = db_get("users",$user_id);

            if (!empty($user["sex"])){
                return cache($user["sex"]);
            }
        }

        return cache("female");
    }
}

if (!function_exists("get_users_for_select")){
    function get_users_for_select(){
        $users = db_get("users", "all");

        return array_map(function($user){
            return array(
                "value" => $user["id"],
                "caption" => get_user_name($user),
            );
        }, $users);

    }
}