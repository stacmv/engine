<?php
// PHP version

if (PHP_VERSION_ID <= 50300){
    die("Code: engine-checks-".__LINE__."-PHP_VERSION_ID:".PHP_VERSION_ID);
};

// Short tags needed
if ( ! ini_get("short_open_tag") ){
    
    die("Code: engine-checks-".__LINE__."-short_open_tag");
    
}