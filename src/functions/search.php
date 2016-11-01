<?php
function search(array $fields_to_search, $query, $max_results = 100){
    
    $limit = 500; // не запрашивать из БД более $limit независимо от $max_results;
    $all_found_ids = array();
    $res = array();
    
    foreach($fields_to_search as $db_table => $fields){
        foreach($fields as $field){
            list($result, $count) = db_search_substr($db_table, $field, $query, min($max_results,$limit));

            if (!empty($result)){
                foreach($result as $rec){
                    if (!isset($res[ $rec["id"] ])){
                        $res[ $rec["id"] ] = $rec;
                        $res[ $rec["id"] ]["_found_in_db"] = $db_table;
                        $res[ $rec["id"] ]["_found_in_field"] = $field;
                    };
                }
            }
            
            $all_found_ids = $all_found_ids +  array_keys($res);
            
            if (count($res) > $max_results ) break;
        };
    };
        
        
    return array("res"=>$res, "total"=>count($all_found_ids));
}
