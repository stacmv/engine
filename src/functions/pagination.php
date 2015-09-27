<?php
function get_pager($items_count, $items_per_page, $current_page, $width = 10){
    
    $k = max(1, floor(($width-3)/2));
    $n = ceil($items_count/$items_per_page);

    $pager = array();
        
    if ($current_page <= $k){
        $k1 = $k-$current_page;
        $k2 = $width - $current_page -2;
    }elseif($n - $current_page <= $k){
        $k1 = $width - ($n-$current_page) -3;
        $k2 = 0;
    }else{
        $k1 = $k;
        $k2 = $k-1;
    }
    
        
        
    for($i = $current_page - $k1; $i <= $current_page+$k2; $i++){
        if ( ($i>1) && ($i<$n) ){
            $pager[] = $i;
        }
    }

    if ( (count($pager) > 1) && ($pager[count($pager)-1] < $n-1) ){
        $pager[] = "...";
    };
    $pager[] = $n;
    
    if ($pager[0] > 2){
        array_unshift($pager, "...");
    };
    if ($pager[0] != 1){
        array_unshift($pager, 1);
    };
    
    
    return array(
        "pager" => $pager,
        "count" => $n,
        "current" => $current_page,
    );
    
}