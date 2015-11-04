<?php
class EUser implements ArrayAccess
{
    protected $_user = array();
    
    static function create_by_id($id){
        $user = new User;
        $user->init( db_get("users", $id) );
    }
    static function create_by_login($login){
        $user = new User;
        $user->init( db_find("users", "login", $login, DB_RETURN_ONE | DB_RETURN_ROW) );
    }
    
    function __construct(array $profile = array()){
        
        if ( ! empty($profile) ){
            $this->init($profile);
        }else{
            if ($this->is_authenticated()){
                $this->init( db_get("users", $_SESSION["authenticated"]) );
            };
        }
    }
    
    function get_id(){
        return isset($this->_user["profile"]["id"]) ? $this->_user["profile"]["id"] : null;
    }
    function get_login(){
        return isset($this->_user["profile"]["login"]) ? $this->_user["profile"]["login"] : null;
    }
    function get_username(){
        $username = "";
        if ( isset($this->_user["profile"]["name"]) ){
            $username = $this->_user["profile"]["name"];
        }elseif( isset($this->_user["profile"]["login"]) ){
            $username = _t("User") . $this->_user["profile"]["login"];
        }else{
            $username = _t("Unknown user");
        }
        return $username;
    }
    
    function is_authenticated(){
        return ! empty($_SESSION["authenticated"]) ;
    }
    
    protected function init(array $profile){
        $this->_user["profile"] = $profile;
    }
    
    
    
    // ArrayAccess implementation
    
    function offsetExists($offset){
        return isset($this->_user[$offset]);
        
    }
    function offsetGet($offset){
        return isset($this->_user[$offset]) ? $this->_user[$offset] : null;
    }
    function offsetSet($offset, $value){
        $this->_user[$offset] = $value;
    }
    function offsetUnset($offset){
        if (isset($this->_user[$offset])){
            unset($this->_user[$offset]);
        };
    }
    
}