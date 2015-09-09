<?php
function markdown($markdown){
    $parsedown = new Parsedown();
       
    return $parsedown->text($markdown);
}