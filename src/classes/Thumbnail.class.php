<?php
class Thumbnail
{
    
    private $thumb_url;
    
    public static function thumb_name($type, $uid, $uuid, $width ="", $height=""){
        if ($type && $uid){
            return "thumb_".$type."__".$uid."__".$uuid.".jpg";
        }else{
            return "thumb_".$width."__".$height."__".$uuid.".jpg";
        }
    }
    public static function no_image_file(){
        global $CFG;

        return $CFG["IMAGE"]["no_image_file"];
    }
    public static function uuid($type, $uid, $image_file){
        if ($type && $uid){
            $m = array();
            $prefix = IMAGES_DIR . $type ."/".$uid;
            $suffix = basename($image_file);
            if (preg_match("|".$prefix."/([\w\d\-]+)/".$suffix."|", $image_file, $m)){
                $uuid = $m[1];
            }else{
                $uuid = "B64".base64_encode($image_file);
            };
        }else{
            $uuid = "B64".base64_encode($image_file);
        };
        
        return $uuid;
    }
    
    public function __construct($full_image, $type, $uid, $width = null, $height=null){
        global $CFG;
        $uuid = self::uuid($type,$uid, $full_image);
        $width = !is_null($width) ? $width : $CFG["IMAGE"]["width"];
        $height = !is_null($height) ? $height : $CFG["IMAGE"]["height"];

        $thumb_name = self::thumb_name($type, $uid, $uuid, $width, $height);
        if (file_cached($thumb_name, true)){
            $thumb = file_cache_get_filename($thumb_name, true);
            if ($full_image && (!filter_var($full_image, FILTER_VALIDATE_URL) && filemtime($full_image) ) && filemtime($thumb)){
                if (filemtime($full_image) < filemtime($thumb)){
                    $this->thumb_url = $thumb;
                }else{
                    $this->thumb_url = $this->create_thumb_uri($type, $uid, $uuid, $width, $height);
                }
            }else{ // filemtime не рабоатет или нет оригинального файла, а минивтюра есть
                $this->thumb_url = $thumb;
            }
        }else{
        
            if ($full_image){
                $this->thumb_url = $this->create_thumb_uri($type, $uid, $uuid, $width, $height);
            }else{
                $this->thumb_url = self::no_image_file();
            }
            
            
        };
    }
    
    public function __toString(){
        return $this->thumb_url;
    }

    private function create_thumb_uri($type, $uid, $uuid, $width, $height){
        global $CFG;
        
        return implode("/", array(
            "image",
             $uuid,
             $width,
             $height,
             $type,
             $uid,
             $CFG["URL"]["ext"],
        ));
    }
}