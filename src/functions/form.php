<?
function form_prepare($db_table, $form_name, $object=""){

    dosyslog(__FUNCTION__.": DEBUG: " . get_callee() .": (" . $db_table . ", " . $form_name . ").");
    
    // Подготовка данных для полей формы
    $fields = array();
    $table = form_get_fields($db_table, $form_name);
    
    foreach($table as $v){
        
        $field = array();
        $field["type"] = $v["type"]; // тип поля в БД
        $template = ! empty($v["form_template"]) ? $v["form_template"] : null;
        
        switch ($template){
        case "input":
        case "file":
            $field["name"]      = $v["name"];
            $field["name_from"] = "from[".$v["name"]."]";
            $field["name_to"]   = "to[".$v["name"]."]";
            $field["id"]        = $v["name"];
            
            if ( strpos($v["name"], "phone") !== false ) $field["class"] = "phone";
            if ( strpos($v["name"], "date")  !== false ) $field["class"] = "date";
            if ( strpos($v["name"], "time")  !== false ) $field["class"] = "time";
            
            $field["value"] = "";
            if ( isset($v["form_value_default"]) )    $field["value"] = $v["form_value_default"];
            if ( ! empty($object[ $v["name"] ]))      $field["value"] = $object[ $v["name"] ];
            if ( isset($_SESSION["to"][$v["name"]]) ) $field["value"] = $_SESSION["to"][$v["name"]];
            
            $field["label"] = $v["label"];
            break;
        
        case "checkboxes":        
            $field["name"]      = $v["name"];
            $field["name_from"] = "from[".$v["name"]."][]";
            $field["name_to"]   = "to[".$v["name"]."][]";
            $field["id"] = $v["name"];
            
            if ( strpos($v["name"], "phone") !== false ) $field["class"] = "phone";
            if ( strpos($v["name"], "date")  !== false ) $field["class"] = "date";
            if ( strpos($v["name"], "time")  !== false ) $field["class"] = "time";
            
            $field["values"] = array();
            if (empty($v["form_values"])){
                dosyslog(__FUNCTION__.": ERROR: Values for ".$template." field '" . $v["name"] . "' is not set table ' " . $db_table . "' config.");
                $field = null;
                break;
            };
            
            $field["values"] = form_get_field_values($v);
            
            $field["value"] = array(); // выбраные значения
            if ( isset($v["form_value_default"]) )    $field["value"] = $v["form_value_default"];
            if ( ! empty($object[ $v["name"] ]))      $field["value"] = $object[ $v["name"] ];
            if ( isset($_SESSION["to"][$v["name"]]) ) $field["value"] = $_SESSION["to"][$v["name"]];
            
            $field["label"] = $v["label"];
            break;
            
        case "select":
        case "radio":
            $field["name"]      = $v["name"];
            $field["name_from"] = "from[".$v["name"]."]";
            $field["name_to"]   = "to[".$v["name"]."]";
            $field["id"] = $v["name"];
            
            if ( strpos($v["name"], "phone") !== false ) $field["class"] = "phone";
            if ( strpos($v["name"], "date")  !== false ) $field["class"] = "date";
            if ( strpos($v["name"], "time")  !== false ) $field["class"] = "time";
            
            $field["values"] = array();
            if (empty($v["form_values"])){
                dosyslog(__FUNCTION__.": ERROR: Values for ".$template." field '" . $v["name"] . "' is not set table ' " . $db_table . "' config.");
                $field = null;
                break;
            };
            
            $field["values"] = form_get_field_values($v);
            
            $field["value"] = ""; // выбраные значения селекта
            if ( isset($v["form_value_default"]) )    $field["value"] = $v["form_value_default"];
            if ( ! empty($object[ $v["name"] ]))      $field["value"] = $object[ $v["name"] ];
            if ( isset($_SESSION["to"][$v["name"]]) ) $field["value"] = $_SESSION["to"][$v["name"]];
            
            $field["label"] = $v["label"];
            break;
                
        case "hidden":
            $field["name"]      = $v["name"];
            $field["name_from"] = "from[".$v["name"]."]";
            $field["name_to"]   = "to[".$v["name"]."]";
            if ( strpos($v["name"], "phone") !== false ) $field["class"] = "phone";
            if ( strpos($v["name"], "date")  !== false ) $field["class"] = "date";
            if ( strpos($v["name"], "time")  !== false ) $field["class"] = "time";
            
            $field["value"] = "";
            if ( isset($v["form_value_default"]) )    $field["value"] = $v["form_value_default"];
            if ( ! empty($object[ $v["name"] ]))      $field["value"] = $object[ $v["name"] ];
            if ( isset($_SESSION["to"][$v["name"]]) ) $field["value"] = $_SESSION["to"][$v["name"]];
            
            $field["label"] = "";
            break;
        default:
            dosyslog(__FUNCTION__.": FATAL ERROR: Unsupported template '".$template."' for field '".$v["name"]."' in '".$db_table."'.");
            die("Code: ef-".__LINE__."-".$template);
        }; // switch
        
        if ( $field && $template ){
            $field["template_file"] = TEMPLATES_DIR . "form/" . $template . ".form.htm";
            $fields[] = $field;
        };
    }
    unset($v);
    
    dosyslog(__FUNCTION__.": DEBUG: " . get_callee() .": (" . $db_table . ", " . $form_name . "): created fields: " . implode(",", array_map( function($i){return $i["name"];}, $fields)) );
    
    return $fields;
}
function form_get_fields($db_table, $form_name){
    
    $schema = db_get_table_schema($db_table);
    
    if ( ! $schema ){
        dosyslog(__FUNCTION__.": FATAL ERROR: '".$db_table."' is not found in DB config.");
        die("Code: ef-".__LINE__);
    };
    
    $fields = array();
    foreach($schema as $v){
        
        $forms = array();
        $form_index = false; // порядковый номер $form_name в списке форм
        if ( empty($v["form"]) ) continue;
        if ( strpos($v["form"], "|") !== false ){
            $forms = explode("|", $v["form"]);
            
        }else{
            $forms = array($v["form"]);
        }
        $form_index = array_search($form_name, $forms);
        if ($form_index === false) continue; // поле БД не попадает в форму $form_name
    
        $v["form"] = $form_name;
    
        // Parse some data
        foreach($v as $prop_key=>$prop_value){
            if (substr($prop_key, 0, 5) == "form_"){
                if ( strpos($prop_value, "|") !== false ){
                    $tmp = explode("|", $prop_value);
                    $v[$prop_key] = $tmp[$form_index];
                    unset($tmp);
                };
            }
        }
        unset($prop_key, $prop_value);
        
        if ( ! isset($v["label"]) ) $v["label"] = "";
        
        $fields[] = $v;
        
    };
    unset($v, $schema);
    return $fields;
}
function form_get_field_values($field){
    
    switch($field["form_values"]){
    case "lst":
        $values = glog_file_read_as_array( APP_DIR . "settings/" . glog_codify($field["name"]) . ".lst" );
        break;

    case "tsv":
        $values = array();
        $tsv = import_tsv( APP_DIR . "settings/" . glog_codify($field["name"]) . ".tsv" );
        if ($tsv){
            foreach($tsv as $record){
                $values[ $record["caption"] ] = $record[ $field["name"] ];
            }
            unset($record);
        }
        unset($tsv);
        break;

    default:
    
        if ( strpos($field["form_values"], "&") !== false ){
            $values = explode("&", $field["form_values"]);
        }elseif ( function_exists($field["form_values"]) ){
            $values = call_user_func($field["form_values"]);
        }else{
            dosyslog(__FUNCTION__.": ERROR: Values for select field '" . $field["name"] . "' have unknown format. Check DB config.");
            die("Code: ef-".__LINE__);
        }
    }; // switch form_values
    
    return $values;
}