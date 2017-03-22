<?php
class Thumbnail
{
    const NO_IMAGE_FILE = "assets/images/sample-thumb.png";
    private $thumb_url;
    
    public static function thumb_name($type, $uid, $uuid, $width ="", $height=""){
        return "thumb_".$type."__".$uid."__".$uuid.".jpg";
    }
    public static function uuid($type, $uid, $image_file){
        $m = array();
        $prefix = IMAGES_DIR . $type ."/".$uid;
        $suffix = basename($image_file);
        if (preg_match("|".$prefix."/([\w\d\-]+)/".$suffix."|", $image_file, $m)){
            $uuid = $m[1];
        }else{
            $uuid = "B64".base64_encode($image_file);
        };
        
        return $uuid;
    }
    
    public function __construct($full_image, $type, $uid){
        global $CFG;
        
        
        $uuid = self::uuid($type,$uid, $full_image);
        $thumb_name = self::thumb_name($type, $uid, $uuid, $CFG["IMAGE"]["width"], $CFG["IMAGE"]["height"]);
        if (file_cached($thumb_name, true)){
            $thumb = file_cache_get_filename($thumb_name, true);
            if ($full_image && filemtime($full_image) && filemtime($thumb)){
                if (filemtime($full_image) < filemtime($thumb)){
                    $this->thumb_url = $thumb;
                }
            }else{ // filemtime не рабоатет или нет оригинального файла, а минивтюра есть
                $this->thumb_url = $thumb;
            };
        }else{
        
            if ($full_image){
                $this->thumb_url = "image/".$type."/" . $uid. "/" . $uuid . "/" . $CFG["IMAGE"]["width"] . "/" . $CFG["IMAGE"]["height"] . $CFG["URL"]["ext"];
            }else{
                $this->thumb_url = self::NO_IMAGE_FILE;
            }
            
            
        };
    }
    
    public function __toString(){
        return $this->thumb_url;
    }
}