<?php

function archive(array $files,  $filename = ""){
    
    if (!$filename) $filename = md5(serialize($files));
    else $filename = basename($filename);
    
    $path = FILES_DIR . "zip/";
    if (!is_dir($path)) mkdir($path, 0777, true);
    
    $zip = new ZipArchive;
    
    dosyslog(__FUNCTION__.": DEBUG: Created archive '".$path . $filename."'.");
    if ($zip->open( $path . $filename, ZipArchive::CREATE )){
        foreach($files as $file){
            $zip->addFile($file, basename($file));
        };
        $zip->close();
        dosyslog(__FUNCTION__.": INFO: ".count($files)." Added to archive '".$path . $filename."'. File size: ". filesize($path . $filename) ." bytes."); 
        
        return $path . $filename;
    }else{
        dosyslog(__FUNCTION__.": ERROR: Could not create archive '".$path . $filename."'.");
        return false;
    }
    
}