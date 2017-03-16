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

    $type   = $_PARAMS["type"];
    $uid    = $_PARAMS["uid"];
    $uuid    = $_PARAMS["uuid"];
    $width  = $_PARAMS["width"];
    $height = $_PARAMS["height"];
        
    
    
    
        
    $images = get_images($type, $uid, $uuid);
    if (!empty($images[0])){
        $image_file = $images[0];
    }else{
        $image_file = Thumbnail::NO_IMAGE_FILE;
    }
    
    $thumb_name = Thumbnail::thumb_name($type, $uid, $uuid, $width, $height);
      

    $check = true;
    // Check image 
    if (!file_exists($image_file)) $check = false;
    
    
        
    if ($check && ($width || $height)){
        
        try{
            $image = Image::make($image_file);
            
            $aspect_ratio = $image->width() / $image->height();
            
            if ($width && ! $height) $height = (int) ($width / $aspect_ratio);
            elseif ( ! $width && $height) $width = (int) ($height * $aspect_ratio);
            
            
            
            $image->fit($width, $height, function ($constraint) {
                $constraint->upsize();
            });
           
            $_RESPONSE["headers"]["Content-type"] = "image/jpeg";
            $_RESPONSE["body"] = file_cache_set($thumb_name, (string) $image->encode("jpg", 75), 60*60*24 );
        }catch(Exception $e){
            dump($image_file,"image");die();
            dosyslog(__FUNCTION__.": ERROR: Can not create thumbnal for '".$type."' '".$uid."' '".$width."x".$height."'. Error: ".$e->getMessage());
            $_RESPONSE["headers"]["Content-type"] = "image/jpeg";
            $_RESPONSE["body"] = file_get_contents(Product::NO_IMAGE_FILE);
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
    
    $type = $_PARAMS["type"];
    $uid  = $_PARAMS["uid"];
    
    $IS_AJAX = true;
    $_DATA = array();
    clear_actions();
   
    
    
    if (!$type) return;
    if (!$uid) return;
    
    $files_dir = IMAGES_DIR.db_get_db_table($type)."/".$uid;
    
    if (!is_dir($files_dir)) mkdir($files_dir, 0777, true);
    
    // -------------
    
    // Include the upload handler class
    // require_once "vendor/fineuploader/php-traditional-server/handler.php";

    $uploader = new UploadHandler();

    // Specify the list of valid extensions, ex. array("jpeg", "xml", "bmp")
    $uploader->allowedExtensions = array("jpg");

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
    
    $type = $_PARAMS["type"];
    $uid  = $_PARAMS["uid"];
    
    $IS_AJAX = true;
    $_DATA = array();
    clear_actions();
   
    
    
    if (!$type) return;
    if (!$uid) return;
    
    
    $files_dir = IMAGES_DIR.db_get_db_table($type)."/".$uid;
        
    // -------------
   
    // Include the upload handler class
    // require_once "vendor/fineuploader/php-traditional-server/handler.php";

    $uploader = new UploadHandler();

    

    $method = $_SERVER["REQUEST_METHOD"];
    // if ($method == "DELETE") {  // for delete file requests
        $result = $uploader->handleDelete($files_dir);
        $_DATA = $result;
    // }
}

function image_upload_session_action(){
    global $_PARAMS;
    global $_DATA;
    global $IS_AJAX;
    
    $type = $_PARAMS["type"];
    $uid  = $_PARAMS["uid"];
    
    $IS_AJAX = true;
    $_DATA = array();
    clear_actions();
    
        
    if (!$type) return;
    if (!$uid) return;
    
    $images = get_images($type, $uid);
        
    if (!$images) return;
    
    
    
    $_DATA = array_map(function($image) use ($type, $uid){
        return array(
            "name" => basename($image),
            "uuid" => Thumbnail::uuid($type, $uid, $image),
            "size" => filesize($image),
            "thumbnailUrl" => (string) new Thumbnail($image, $type, $uid),
        );
    }, $images);
    
}