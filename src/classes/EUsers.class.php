<?php
class EUsers extends ERepository{
    const DB_TABLE = "users";
    
    static public function find($key, $value){
        return db_find(self::DB_TABLE, $key, $value, DB_RETURN_ROW);
    }
    static public function find_one($key, $value){
        return db_find(self::DB_TABLE, $key, $value, DB_RETURN_ROW | DB_RETURN_ONE);
    }
    static public function get(){
           
        $users = array();
        $users = db_get(self::DB_TABLE, "all");
        return $users;
    }
    static public function get_for_select(array $users = array()){
        
        if (empty($users)) $users = self::get_list();
        
        $values = array();
        
        foreach($users as $user){
            $values[] = array(
                "caption"=> !empty($user["name"]) ? $user["name"] : (!empty($user["id"]) ? self::get_username_by_id($user["id"]) : _t("Unknown")), // id might not be set if $user have not been read from db.
                "value"  => !empty($user["id"]) ? $user["id"] : "null",
            );
        };
       
        return $values;
        
    }
    static public function get_username_by_id($user_id, $getLogin=false){
        $user = db_get(self::DB_TABLE, $user_id);
        if ( ! $user){
            dosyslog(__FUNCTION__.get_callee() . ": WARNING: User with id '".$user_id."' is not found in DB.");
            return "";
        } else {
            if ($getLogin){
                return $user["login"];
            }else{
                return empty($user["name"]) ? "Пользователь ".$user["login"] : $user["name"];
            };
        };
    }

    static public function get_list($limit=""){
        $res = db_get_list(self::DB_TABLE, array("id","login"),$limit);
        return $res;
    }

    public function checkACL($item, $right){
        
        return userHasRight("manager");
        
    }
    
};