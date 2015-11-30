<?php
class CsvImporter 
{ 
    private $fp; 
    private $parse_header; 
    private $header; 
    private $delimiter; 
    private $length; 
    //-------------------------------------------------------------------- 
    function __construct($file_name, $parse_header=false, $delimiter="\t", $length=8000) 
    { 
        $this->fp = fopen($file_name, "r"); 
        $this->parse_header = $parse_header; 
        $this->delimiter = $delimiter; 
        $this->length = $length; 
        // $this->lines = $lines; 

        if ($this->parse_header) 
        { 
           $this->header = fgetcsv($this->fp, $this->length, $this->delimiter); 
        } 

    } 
    //-------------------------------------------------------------------- 
    function __destruct() 
    { 
        if ($this->fp) 
        { 
            fclose($this->fp); 
        } 
    } 
    //-------------------------------------------------------------------- 
    function get($max_lines=0) 
    { 
        //if $max_lines is set to 0, then get all the data 

        $data = array(); 

        if ($max_lines > 0) 
            $line_count = 0; 
        else 
            $line_count = -1; // so loop limit is ignored 

        while ($line_count < $max_lines && ($row = fgetcsv($this->fp, $this->length, $this->delimiter)) !== FALSE) 
        { 
            if ($this->parse_header) 
            { 
                foreach ($this->header as $i => $heading_i) 
                { 
                    $row_new[$heading_i] = isset($row[$i]) ? $row[$i] : ""; 
                } 
                $data[] = $row_new; 
            } 
            else 
            { 
                $data[] = $row; 
            } 

            if ($max_lines > 0) 
                $line_count++; 
        } 
        return $data; 
    } 
    //-------------------------------------------------------------------- 

};

function import_tsv($filename, $convertToUTF8=false, $returnHeaderOnly = false){
    

    $file = glog_file_read_as_array($filename);
 
    $res = false;
    if (!$file){
        dosyslog(__FUNCTION__."(".__LINE__."): ошибка: не найден или пустой файл '".$filename."'");
    }else{
        
        $header = explode("\t", trim($file[0]));
        if ($returnHeaderOnly) return $header;

        // $res = parse_csv(implode("\n", $file), "\t"); 
        
        $importer = new CsvImporter($filename, true);
        $res = $importer->get();
        
        // strip commented lines (which begins with ";")
        foreach($res as $k=>$v){
            
            if ( $v[$header[0]] && ($v[$header[0]]{0} == ";") ){
                unset($res[$k]);
            };
        };
        
        
    };
    
    return $res;
};


