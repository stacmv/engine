<?php
function markdown($markdown){
    $parsedown = new Parsedown();
    $markdown = html_entity_decode($markdown); 
     
    return $parsedown->text($markdown);
    
}