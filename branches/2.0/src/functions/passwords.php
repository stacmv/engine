<?php
function passwords_hash($pass){

    if ( (PHP_VERSION_ID >= 50500) && (function_exists("password_hash") ) ) {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
    }else{
        $salt = substr(time(),-2) . substr(uniqid(),-2);
        $hash = $salt . md5($salt.md5($pass));
    };

    return $hash;
};
function passwords_verify($pass, $hash){

    
    if ( (PHP_VERSION_ID >= 50500) && (function_exists("password_hash") ) ) {
        if (substr($hash, 0, 7) == "$2y$10$"){
            return password_verify($pass, $hash);
        }else{
            $salt = substr($hash,0,4);
            return $hash === $salt . md5($salt.md5($pass));
        };
    }else{
        $salt = substr($hash,0,4);
        return $hash === $salt . md5($salt.md5($pass));
    }; 
    
}
function password_test(){

    $pass = time();
    
    $hash = passwords_hash($pass);
    
    
    
    $valid = passwords_verify($pass, $hash);
    $invalid = passwords_verify("wq4344", $hash);
    
    dump($valid,"should be TRUE");
    dump($invalid,"should be FALSE");
}
