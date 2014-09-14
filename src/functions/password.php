<?php
function password_hash($pass){

    $salt = substr(time(),-2) . substr(uniqid(),-2);
    
    $hash = $salt . md5($salt.md5($pass));

    return $hash;
};
function password_verify($pass, $hash){
    
    $salt = substr($hash,0,4);
    
    return $hash === $salt . md5($salt.md5($pass));
}

function password_test(){

    $pass = time();
    
    $hash = password_hash($pass);
    
    
    
    $valid = password_verify($pass, $hash);
    $invalid = password_verify("wq4344", $hash);
    
    dump($valid,"should be TRUE");
    dump($invalid,"should be FALSE");
}
