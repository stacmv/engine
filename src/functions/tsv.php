<?php
function import_tsv($filename, $convertToUTF8=false, $returnHeaderOnly = false){

    $file = glog_file_read($filename);
 
    $res = false;
    if (!$file){
        dosyslog(__FUNCTION__."(".__LINE__."): ошибка: не найден или пустой файл '".$filename."'");
    }else{
        $res = import_tsv_string($file);
    };
    
    return $res;
};

function import_tsv_string($tsv_str, $convertToUTF8=false, $returnHeaderOnly = false, $stripComments = true){
    $tsv = parse_tsv(trim($tsv_str), "\t"); 
        
    $header = $tsv[0];
    if ($returnHeaderOnly) return $header;
    
        
    $n_columns = count($header);
    $res = array_map(function($row) use ($header, $n_columns, $tsv_str){

        while ( $n_columns > count($row) ){ // hack for last row which may contain less elements than header
            $row[] = "";
        };
       
        return array_combine($header, $row);
    },array_slice($tsv,1));
    
       
    
    // strip commented lines (which begins with ";")
    if ($stripComments){
        foreach($res as $k=>$v){
            if ( $v[$header[0]] && ($v[$header[0]]{0} == ";") ){
                unset($res[$k]);
            };
        };
    };
    
    return $res;
}

// See: http://php.net/manual/ru/function.str-getcsv.php#111665
function parse_tsv ($tsv_string, $delimiter = ",", $skip_empty_lines = true, $trim_fields = true){
    $enc = preg_replace('/(?<!")""/', '!!Q!!', $tsv_string);
    $enc = preg_replace_callback(
        '/"(.*?)"/s',
        function ($field) {
            return urlencode(/*utf8_encode*/($field[1]));
        },
        $enc
    );
    $lines = preg_split($skip_empty_lines ? ($trim_fields ? '/( *\R)+/s' : '/\R+/s') : '/\R/s', $enc);
    
    
        
    return array_map(
        function ($line) use ($delimiter, $trim_fields) {
            $fields = $trim_fields ? array_map('trim', explode($delimiter, $line)) : explode($delimiter, $line);
            return array_map(
                function ($field) {
                    // TODO : test UTF-8/non-UTF-8 strings here;
                    $res  = str_replace('!!Q!!', '"', /*utf8_decode*/(urldecode($field)));
                    return $res;
                }, 
                $fields
            );
        },
        $lines
    );
}
