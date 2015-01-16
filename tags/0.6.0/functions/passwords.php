<?php
function passwords_hash($pass){

    $salt = substr(time(),-2) . substr(uniqid(),-2);
    
    $hash = $salt . md5($salt.md5($pass));

    return $hash;
};
function passwords_verify($pass, $hash){
    
    $salt = substr($hash,0,4);
    
    return $hash === $salt . md5($salt.md5($pass));
}

function passwords_test(){

    $pass = time();
    
    $hash = passwords_hash($pass);
    
    
    
    $valid = passwords_verify($pass, $hash);
    $invalid = passwords_verify("wq4344", $hash);
    
    dump($valid,"should be TRUE");
    dump($invalid,"should be FALSE");
}
