<?php
class HistoryRepository extends Repository
{
    
    protected static function _getRepositoryClassName($repository_name){
        return __CLASS__;
    }
    protected static function _getModelClassName($repository_name){
        return "HistoryModel";
    }
    
}