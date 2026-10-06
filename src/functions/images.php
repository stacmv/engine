<?php
function get_images($repo_name, $field_name, $uid, $uuid =""){
    $images = array();

    if (substr($uuid,0,3) == "B64"){ // uuid = base64_encoded filename
        // Restore standard base64 characters: - → + , _ → /
        $b64 = substr($uuid,3);
        $b64 = str_replace('-', '+', $b64);
        $b64 = str_replace('_', '/', $b64);
        $images[] = base64_decode($b64);
        return $images;
    };

    if (!defined("IMAGES_DIR")) die("IMAGES_DIR is not defined.");


    $sub_dir = IMAGES_DIR.db_get_db_table($repo_name). "/" . $field_name . "/";
    $media_exts = "{jpg,png,mp4,mov,MOV,webm,avi}";

    // 1. Изображения, загруженные, условно, по FTP
    $images1 = glob($sub_dir . $uid ."/*." . $media_exts, GLOB_BRACE);
    $images = array_merge($images, $images1);

    // 2. Изображения, загруженные пользователем через админку - каждое в отдельном подкаталоге в виде uuid
    $images2 = array();
    if (!empty($uuid)){
        $image_dirs = array($sub_dir.$uid."/".$uuid);
    }else{
        $image_dirs = glob($sub_dir . $uid ."/*", GLOB_ONLYDIR);
    };


    if(!empty($image_dirs)){
        foreach($image_dirs as $image_dir){
            $images2 = array_merge($images2, glob($image_dir ."/*." . $media_exts, GLOB_BRACE));
        };
    };
    $images = array_merge($images, $images2);

    return $images;
}

function is_image($path){
    if (file_exists($path)){
        $a = getimagesize($path);
        $image_type = $a[2];

        if(in_array($image_type , array(IMAGETYPE_GIF , IMAGETYPE_JPEG ,IMAGETYPE_PNG , IMAGETYPE_BMP))){
            return true;
        }
    };
    return false;
}
