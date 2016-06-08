<?php
class IniFileStorage extends EStorage
{
    const DATA_DIR = DATA_DIR;
    private $repo_name;
    private $fields;
    private $select_clause;
    private $where_clause;
    private $orderBy_clause;
    private $limit_clause;
    
    /**
    * @throws Exception not valid directory
    */
    public function __construct($repo_name){
        if (!is_dir(self::DATA_DIR)){
            throw new Exception(self::DATA_DIR . " is not valid directory.");
        };
        $this->repo_name = $repo_name;
        $this->fields = form_get_fields($repo_name, "all");
    }
    public function create($resource, ChangesSet $changes, $comment = ""){
        $file = $this->getFile($resource);
        $filename = $this->getFilename($resource);
        
        $last_id = $this->getLastId($file);
        
        $file[$last_id+1] = $changes->to;
        
        $this->write_php_ini($filename, $file);
        return $last_id+1;
    }
    public function read($resource, $comment = ""){
        $file      = $this->getFile($resource);
        $id        = $this->parseResource($resource)["id"];
        $repo_name = $this->repo_name;
        
        if ($id){
            if (isset($file[$id])){
                return db_parse_result($repo_name, $file[$id]);
            }else{
                return null;
            }
        }else{
            
            return array_map(function($result, $id) use ($repo_name){
                return db_parse_result($repo_name, $result);
            }, $file, array_keys($file));
        };
    }
    public function update($resource, ChangesSet $changes, $comment = ""){
        $filename = $this->getFilename($resource);
        $file = $this->getFile($resource);
        $id   = $this->parseResource($resource)["id"];
        
        if ($id && isset($file[$id])){
            $file[$id] = $changes->to;
        
            $this->write_php_ini($filename, $file);
            return $id;
        }else{
            return null;
        }
    }
    public function delete($resource, $comment = ""){
        $filename = $this->getFilename($resource);
        $file = $this->getFile($resource);
        $id   = $this->parseResource($resource)["id"];
        
        if ($id && isset($file[$id])){
            unset($file[$id]);
            $this->write_php_ini($filename, $file);
            return true;
        }else{
            return null;
        }
    }
    
    public function orderBy($orderBy_clause){
        $this->orderBy_clause = $orderBy_clause;
        return $this;
    }
    public function where(callable $where_clause){
        $this->where_clause = $where_clause;
        return $this;
    }
    
    public function fetchAssoc(){
        $res = $this->fetchAllAssoc();
        if ($res){
            return reset($res);
        }else{
            return array();
        }
    }
    public function fetchAllAssoc(){
        
        $res = $this->read($this->repo_name);
        if ($this->where_clause){
            $where_clause = $this->where_clause;
            $res = array_filter($res, $where_clause);
        };
        if ($this->orderBy_clause){
            if (is_callable($this->orderBy_clause)){
                uasort($res, $this->orderBy_clause);
            }elseif (is_array($this->orderBy_clause)){
                // Incomplete temporary implementation
                foreach($this->orderBy_clause as $k=>$sort_order){
                    uasort($res, function($a,$b)use($k, $sort_order){
                        if ($sort_order == "DESC") return $b[$k] - $a[$k];
                        else return $a[$k] - $b[$k];
                    });
                    break;
                };
            }
        };
        if ($this->limit_clause){
            $res = array_slice(0, $this->limit_clause);
        };
        if ($this->select_clause){
            $select_clause = $this->select_clause;
            $res = array_reduce($res, function($res, array $record) use ($select_clause){
                $rec = array();
                foreach($record as $k=>$v){
                    if (in_array($k, $select_clause)) $rec[$k] = $v;
                };
                $res[] = $rec;
                return $res;
            }, array());
        };
        
        return $res;
        
    }
    
    private function getFile($resource){
        
        $path = $this->parseResource($resource)["path"];
        
        $filename = $this->getFilename($path);
        if (!file_exists($filename)){
            touch($filename);
        };
        $file = parse_ini_file($filename, true);
        
        return $file;
    }
    private function getFilename($path){
        return self::DATA_DIR . glog_codify($path, GLOG_GET_FILENAME);
    }
    private function getLastId(array $file){
        if (empty($file)) return 0;
        
        $numeric_keys = array_filter(array_keys($file), function($key){
            return is_numeric($key);
        });
        if (!empty($numeric_keys)){
            return max($numeric_keys);
        }else{
            return 0;
        }
    }
    
    
    private function write_php_ini($filename, array $array){
        $res = array();
            
        foreach($array as $key => $val){
            if(is_array($val)){
                foreach($val as $skey => $sval){
                    if (!isset($this->fields[$skey])){
                        $val["extra"][$skey] = $sval;
                        unset($val[$skey]);
                    };
                };
                $res[] = "[$key]";
                $res[] = "id = $key";
                foreach($val as $skey => $sval){
                    $value = db_prepare_value($sval, $this->fields[$skey]["type"]);
                    $res[] = "$skey = ".(is_numeric($value) ? $value : '"'.$value.'"');
                };
            }else{
                $res[] = "$key = ".(is_numeric($val) ? $val : '"'.$val.'"');
            };
        };
        $this->safefilerewrite($filename, implode("\n", $res));
    }
    private function safefilerewrite($fileName, $dataToSave){
        if ($fp = fopen($fileName, 'w')){
            $startTime = microtime(TRUE);
            do{
                $canWrite = flock($fp, LOCK_EX);
                // If lock not obtained sleep for 0 - 100 milliseconds, to avoid collision and CPU load
                if(!$canWrite) usleep(round(rand(0, 100)*1000));
            } while ((!$canWrite)and((microtime(TRUE)-$startTime) < 5));

            //file was locked so now we can store information
            if ($canWrite){
                fwrite($fp, $dataToSave);
                flock($fp, LOCK_UN);
            };
            fclose($fp);
        }else{
            throw new Exception("Can not open file for write.");
        };

    }
}