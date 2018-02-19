<?php
use Intervention\Image\ImageManagerStatic as Image;

function image_action(){
    global $IS_API_CALL;
    global $_RESPONSE;
    global $_PARAMS;
    global $CFG;


    $IS_API_CALL = true;
    clear_actions();

    Image::configure(array('driver' => 'gd'));

    $repo_name   = $_PARAMS["repo_name"];
    $field_name   = $_PARAMS["field_name"];
    $uid    = $_PARAMS["uid"];
    $uuid    = $_PARAMS["uuid"];
    $width  = $_PARAMS["width"];
    $height = $_PARAMS["height"];





    $images = get_images($repo_name, $field_name, $uid, $uuid);
    if (!empty($images[0])){
        $image_file = $images[0];
    }else{
        $image_file = Thumbnail::no_image_file();
    }

    $thumb_name = Thumbnail::thumb_name($repo_name, $field_name, $uid, $uuid, $width, $height);


    $check = true;
    // Check image
    if (!file_exists($image_file) && !filter_var($image_file, FILTER_VALIDATE_URL)) $check = false;



    if ($check && ($width || $height)){

        try{
            $image = Image::make($image_file);

            $aspect_ratio = $image->width() / $image->height();

            if ($width && ! $height){
                 $height = (int) ($width / $aspect_ratio);
                 $method = "resize";
            } elseif ( ! $width && $height) {
                $width = (int) ($height * $aspect_ratio);
                $method = "resize";
            }else{
                $method = "fit";
            }



            $image->$method($width, $height/*, function ($constraint) {
                $constraint->upsize();
            }*/);

            $_RESPONSE["headers"]["Content-type"] = "image/jpeg";
            $_RESPONSE["body"] = file_cache_set($thumb_name, (string) $image->encode("jpg", 75), 60*60*24 );
        }catch(Exception $e){
            dump($image_file,"image");die();
            dosyslog(__FUNCTION__.": ERROR: Can not create thumbnal for '".$repo_name."' '".$uid."' '".$width."x".$height."'. Error: ".$e->getMessage());
            $_RESPONSE["headers"]["Content-type"] = "image/jpeg";
            $_RESPONSE["body"] = file_get_contents(Thumbnail::no_image_file());
        }
    }else{
        $_RESPONSE["headers"]["HTTP"] = "HTTP/1.1 400 Bad Request";
        $_RESPONSE["body"] = "";
    }

}

function image_upload_action(){
    global $_PARAMS;
    global $_REPONSE;
    global $_DATA;
    global $IS_AJAX;

    $repo_name = $_PARAMS["repo_name"];
    $field_name = $_PARAMS["field_name"];
    $uid  = $_PARAMS["uid"];

    $IS_AJAX = true;
    $_DATA = array();
    clear_actions();



    if (!$repo_name) return;
    if (!$field_name) return;
    if (!$uid) return;

    $files_dir = IMAGES_DIR.db_get_db_table($repo_name)."/".$field_name."/".$uid;

    if (!is_dir($files_dir)) mkdir($files_dir, 0777, true);

    // -------------

    // Include the upload handler class
    // require_once "vendor/fineuploader/php-traditional-server/handler.php";

    $uploader = new UploadHandler();

    // Specify the list of valid extensions, ex. array("jpeg", "xml", "bmp")
    $uploader->allowedExtensions = array("jpg","png");

    // Specify max file size in bytes.
    $uploader->sizeLimit = null;

    // Specify the input name set in the javascript.
    $uploader->inputName = "qqfile"; // matches Fine Uploader's default inputName value by default

    // If you want to use the chunking/resume feature, specify the folder to temporarily save parts.
    $uploader->chunksFolder = ".cache";

    $method = $_SERVER["REQUEST_METHOD"];
    if ($method == "POST") {
        $_RESPONSE["headers"]["Content-Type"] = "text/plain";

        // Assumes you have a chunking.success.endpoint set to point here with a query parameter of "done".
        // For example: /myserver/handlers/endpoint.php?done
        if (isset($_GET["done"])) {
            $result = $uploader->combineChunks($files_dir);
        }
        // Handles upload requests
        else {
            // Call handleUpload() with the name of the folder, relative to PHP's getcwd()
            $result = $uploader->handleUpload($files_dir);

            // To return a name used for uploaded file you can use the following line.
            $result["uploadName"] = $uploader->getUploadName();
        }

        $_DATA = $result;

    }

    else {
        $_RESPONSE["headers"]["HTTP"] = "HTTP/1.0 405 Method Not Allowed";
    }

}

function image_upload_delete_action(){
    global $_PARAMS;
    global $_REPONSE;
    global $_DATA;
    global $IS_AJAX;

    $repo_name = $_PARAMS["repo_name"];
    $field_name = $_PARAMS["field_name"];
    $uid  = $_PARAMS["uid"];
    $uuid = $_PARAMS["uuid"];

    $IS_AJAX = true;
    $_DATA = array();
    clear_actions();



    if (!$repo_name) return;
    if (!$field_name) return;
    if (!$uid) return;


    $files_dir = IMAGES_DIR.db_get_db_table($repo_name)."/".$field_name."/".$uid;

    // -------------

    $err_response = array("success" => false,
        "error" => "File not found! Unable to delete.".$files_dir,
        "path" => $uuid
    );

    if (is_dir($files_dir . $uuid)){ // Файл был загружен через админку
        $uploader = new UploadHandler();

        $method = $_SERVER["REQUEST_METHOD"];
        $result = $uploader->handleDelete($files_dir);
    }else{ // Файл был загружен по FTP или другим способом


        if (substr($uuid, 0,4) == "/B64"){
            $image_file = base64_decode(substr($uuid,4));
            if (file_exists($image_file)){
                $result = unlink($image_file) ? array("success" => true, "uuid" => $uuid) : $err_response;
            }else{
                $result = $err_response;
            }
        }else{
            $result = $err_response;
        }
    }

    $_DATA = $result;
}

function image_upload_session_action(){
    global $_PARAMS;
    global $_DATA;
    global $IS_AJAX;

    $repo_name = $_PARAMS["repo_name"];
    $field_name = $_PARAMS["field_name"];
    $uid  = $_PARAMS["uid"];

    $IS_AJAX = true;
    $_DATA = array();
    clear_actions();


    if (!$repo_name) return;
    if (!$field_name) return;
    if (!$uid) return;

    $images = get_images($repo_name, $field_name, $uid);

    if (!$images) return;


    $_DATA = array_map(function($image) use ($repo_name, $field_name, $uid){
        return array(
            "name" => basename($image),
            "uuid" => Thumbnail::uuid($repo_name, $field_name, $uid, $image),
            "size" => filesize($image),
            "thumbnailUrl" => (string) new Thumbnail($image, $repo_name, $field_name, $uid),
        );
    }, $images);

}
