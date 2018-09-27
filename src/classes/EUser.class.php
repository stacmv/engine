<?php
class EUser extends Model implements ArrayAccess
{
    protected $db_table = "users";
    protected $model_name = "user";
    protected $data = array();

    // static function create_by_id($id){
        // $user = new User;
        // $user->init( db_get("users", $id) );
    // }
    // static function create_by_login($login){
        // $user = new User;
        // $user->init( db_find("users", "login", $login, DB_RETURN_ONE | DB_RETURN_ROW) );
    // }

    function __construct(array $profile = array()){

        if ( ! empty($profile) ){
            parent::__construct($profile);
        }else{
            if ($this->is_authenticated()){
                $this->data = db_get("users", $_SESSION["authenticated"]);
            };
        }
    }

    // function get_id(){
        // return isset($this->data["id"]) ? $this->data["id"] : null;
    // }
    function get_login(){
        return isset($this->data["login"]) ? $this->data["login"] : null;
    }
    function get_username(){
        $username = "";
        if ( isset($this->data["name"]) ){
            $username = $this->data["name"];
        }elseif( isset($this->data["login"]) ){
            $username = _t("User") . " " . $this->data["login"];
        }else{
            $username = _t("Unknown user");
        }
        return $username;
    }

    function is_authenticated(){
        if (isset($_SESSION["authenticated"])){
            dosyslog(__METHOD__.get_callee().": DEBUG: User 'authenticated' value: '".serialize($_SESSION["authenticated"])."'.");
        }elseif (isset($_COOKIE["auth_mobile_token"])){
            list($user_id, $logged_time, $token) = explode("||", $_COOKIE["auth_mobile_token"]);
            if ((int)$user_id){
                $user = EUsers::find_one("id", (int) $user_id);
                if ($user){
                    $reconstructed_token = sha1($user["id"].$logged_time.$user["pass"]);
                    if ($reconstructed_token == $token){
                        $_SESSION["authenticated"] = $user["id"];
                        dosyslog(__METHOD__.get_callee().": DEBUG: User authenticated by auth_mobile_token cookie valid from ".glog_isodate($logged_time, true).".");
                    };
                };
            };
        };
        return ! empty($_SESSION["authenticated"]) ;
    }




    // ArrayAccess implementation

    function offsetExists($offset){
        return isset($this->data[$offset]);

    }
    function offsetGet($offset){
        return isset($this->data[$offset]) ? $this->data[$offset] : null;
    }
    function offsetSet($offset, $value){
        $this->data[$offset] = $value;
    }
    function offsetUnset($offset){
        if (isset($this->data[$offset])){
            unset($this->data[$offset]);
        };
    }

}
