<?php 
abstract class EView
{
    protected $data;
    
    public static function getView($object){
        
        $custom_class_name = get_class($object) . "View";
        if (class_exists($custom_class_name, true)){
            return new $custom_class_name($object);
        }else{
        
            if (is_a($object, "ERepository")){
                return new RepositoryView($object);
            }elseif(is_a($object, "EModel")){
                return new ModelView($object);
            }else{
                return null;
            };
            
        };
    }
    public function __construct (array $data){
        $this->data = $data;
    }
    public function prepare($view_name = "",  $options = array()){
        
        if (!$view_name) $view_name = "default";
        
        $method_name = glog_codify($view_name, GLOG_CODIFY_FUNCTION) . "View";
        
        if (method_exists($this, $method_name)){
            return $this->$method_name($options);
        }else{
            die("Code: ".__CLASS__."-".__LINE__."-".$method_name);
        }
    }
    
    protected function prepare_view($itemData, $fields, $strict = false){        
        static $tsv = array();
        
        $item = array();
        
        foreach($itemData as $key => $value){
            
            if (!$strict){
                $item[$key] = $value;
            };
            
            if (! isset($fields[$key])) continue;
            
            
            if ( (substr($key,-3) == "_id") || (substr($key,-4) == "_ids") ){
                $obj_name = (substr($key,-4) == "_ids") ? substr($key, 0,-4) : substr($key, 0,-3);
                $get_name_function = "get_".$obj_name."_name";
                if (function_exists($get_name_function)){
                    if ($fields[$key]["type"] == "list"){
                        $item["_".$key] = array_map(function($v) use($get_name_function){
                            return $v ? call_user_func($get_name_function, $v) : "";
                        }, $value);
                    }else{
                        $item["_".$key] = $value ? call_user_func($get_name_function, $value) : "";
                    };
                };
            }elseif(isset($fields[$key]["form_values"]) && ($fields[$key]["form_values"] == "tsv")){
                $tsv_file = cfg_get_filename("settings", $key.".tsv");

                if ( ! isset($tsv[$key]) ){
                    $tsv[$key] = array();
                    $tmp = import_tsv( $tsv_file );
                    if ($tmp){
                        foreach($tmp as $v){
                            $tsv[$key][ isset($v["value"]) ? $v["value"] : $v[$key] ] = $v["caption"];
                        };
                    };
                    unset($tmp, $v);
                };
                    
                if ($fields[$key]["type"] == "list"){
                    $item["_".$key] = array_map(function($v)use($tsv, $key, $tsv_file){
                        if (isset($tsv[$key][$v])){
                            return $tsv[$key][$v];
                        }else{
                            dosyslog(__METHOD__.get_callee().": WARNING: Caption for value '".json_encode_array($v)."' of field '".$key."' is not defined in '".$tsv_file."'.");
                            return $v;
                        };
                    }, $value);
                }else{
                
                    if (isset($tsv[$key][$value])){
                        $item["_".$key] = $tsv[$key][$value];
                    }else{
                        dosyslog(__METHOD__.get_callee().": WARNING: Caption for value '".json_encode_array($value)."' of field '".$key."' is not defined in '".$tsv_file."'.");
                    }
                };
            };
            
            
        }
        
        return $item;
    }
}