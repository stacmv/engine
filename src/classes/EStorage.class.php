<?php
abstract class EStorage
{
    abstract public function __construct($dsn);
    abstract public function create($resource, ChangesSet $changes, $comment = "");
    abstract public function read($resource, $comment = "");
    abstract public function update($resource, ChangesSet $changes, $comment = "");
    abstract public function delete($resource, $comment = "");
    
    protected function parseResource($resource){
        $a = explode("/", $resource);
        if (is_numeric($a[count($a)-1])){ // id provided
            $id = array_pop($a);
            $path = implode("/", $a);
        }else{
            $id = null;
            $path = $resource;
        }
        
        return array("path"=>$path, "id"=>$id);        
    }
    
}