<?php
function form_prepare($db_table, $form_name, $object=""){

    dosyslog(__FUNCTION__.": DEBUG: " . get_callee() .": (" . $db_table . ", " . $form_name . ").");
    
    // Подготовка данных для полей формы
    $fields = array();
    $table = form_get_fields($db_table, $form_name);
    
    foreach($table as $v){
        
        $field = array();
        $field["type"] = $v["type"]; // тип поля в БД
        $template = ! empty($v["form_template"]) ? $v["form_template"] : null;
        
        
         // from значение поля 
        $field["value_from"] = "";
        if ( isset($object[ $v["name"] ])){
            $field["value_from"] = $object[ $v["name"] ];
            if ($v["type"] == "password") {
                $field["value_from"] = "--cut--";
            }else{
                $field["value_from"] = db_prepare_value($object[ $v["name"] ], $v["type"]);
            };
        };
        if ($v["name"] == "pass") $field["value"] = ""; // не показывать хэш пароля
        
        // to значение поля 
        $field["value"] = "";
        if ( isset($v["form_value_default"]) )    $field["value"] = $v["form_value_default"];
        if ( isset($object[ $v["name"] ])){
            $field["value"] = $object[ $v["name"] ];
            if (! in_array($v["type"], array("string", "number","list"))){
                $field["value"] = db_prepare_value($object[ $v["name"] ], $v["type"]);
            };
        };
        if ( isset($_SESSION["to"][$v["name"]]) ) $field["value"] = $_SESSION["to"][$v["name"]];
        if ($v["name"] == "pass") $field["value"] = ""; // не показывать хэш пароля

        
        switch ($template){
        case "checkboxes": 
        case "checkboxes_pub":
        case "multiselect":
            $field["name"]      = $v["name"];
            $field["name_from"] = "from[".$v["name"]."]";
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
            
            $field["label"] = $v["label"];
            break;
            
        case "select":
        case "select_pub":
        case "radio":
        case "radio_pub":
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
            
            $field["label"] = $v["label"];
            
            
            if ( is_array($field["value"]) ){
                if (count($field["value"]) == 1){
                    $field["value"] = $field["value"][0];
                }else{
                    
                    // Несколько выбранных значений, при том, что может быть только одно. Не устанавливаем значение по умолчанию, перекладываем выбор на пользователя
                    dosyslog(__FUNCTION__.": WARNING: Value of array type for " . $template . " field '".$field["name"]."' in form '".$form_name."'. Field[value]: '".json_encode($field["value"])."'. Check form config.");
                };
            }
            
            
            break;
             
        case "hidden":
            $field["name"]      = $v["name"];
            $field["name_from"] = "from[".$v["name"]."]";
            $field["name_to"]   = "to[".$v["name"]."]";
            if ( strpos($v["name"], "phone") !== false ) $field["class"] = "phone";
            if ( strpos($v["name"], "date")  !== false ) $field["class"] = "date";
            if ( strpos($v["name"], "time")  !== false ) $field["class"] = "time";
            
            if (empty($field["value"]) && !empty($v["form_values"])){
                $field["value"] = form_get_field_values($v);
            };
            
            $field["label"] = "";
            break;
        case "input":
        case "textarea":
        case "file":
        default:
            $field["name"]      = $v["name"];
            $field["name_from"] = "from[".$v["name"]."]";
            $field["name_to"]   = "to[".$v["name"]."]";
            $field["id"]        = $v["name"];
            
            if ( strpos($v["name"], "phone") !== false ) $field["class"] = "phone";
            if ( strpos($v["name"], "date")  !== false ) $field["class"] = "date";
            if ( strpos($v["name"], "time")  !== false ) $field["class"] = "time";
            
            $field["label"] = $v["label"];
            break;
        }; // switch
        
        
        
        
        
        // Подсказки, обязательность, ...
        $field["hint"]      = ! empty($v["form_hint"]) ? $v["form_hint"] : "";
        $field["required"]  = ! empty($v["required"])  ? $v["required"]  : "";
        
         
         
         // Шаблон поля : показывать ли поле на форме и как именно
        if ( $field && $template ){
            $field["template"] = $template;
            $field["template_file"] = form_get_template_file($template);
            if ( ! file_exists($field["template_file"]) ){
                dosyslog(__FUNCTION__.": FATAL ERROR: Template file '".$template."' for form '".$form_name."' is not found.");
                die("Code: efrm-".__LINE__."-".$template);
            };
            
            $fields[] = $field;
            
        }else{
            if (! $template ) dosyslog(__FUNCTION__.": ERROR: Template is not set for field ".$v["name"]." of form '" . $form_name . "'.");
        }
    }
    unset($v);
        
    dosyslog(__FUNCTION__.": DEBUG: " . get_callee() .": (" . $db_table . ", " . $form_name . "): created fields: " . implode(",", array_map( function($i){ return $i["name"];}, $fields)) );
    
    return $fields;
}
function form_get_fields($db_table, $form_name){
    
    $schema = db_get_table_schema($db_table);
    
    if ($form_name == "all") return $schema;
    
    
    if ( ! $schema ){
        dosyslog(__FUNCTION__.": " . get_callee() .": FATAL ERROR:  '".$db_table."' is not found in DB config.");
        die("Code: efrm-".__LINE__);
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
                    if ( isset($tmp[$form_index]) ){
                        $v[$prop_key] = $tmp[$form_index];
                    }else{
                        
                        dosyslog(__FUNCTION__.": FATAL ERROR: Template for field '".$prop_key."' in form '".$form_name."' is not set. Check db config file.");
                        die("Code: efrm-".__LINE__);
                        
                    }
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
        $values = array_map("trim", $values);
        break;

    case "tsv":
        $values = array();
        $tsv = import_tsv( APP_DIR . "settings/" . glog_codify($field["name"]) . ".tsv" );
        if ($tsv){
            foreach($tsv as $record){
                $values[ trim($record["caption"]) ] = trim( isset($record[ $field["name"] ]) ? $record[ $field["name"] ] : $record["value"] );
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
            die("Code: efrm-".__LINE__);
        }
        
        if ( is_array($values) && ! empty($values) ){
        
            $keys = array_keys($values);
            if ( ! is_array($values[ $keys[0] ]) ){ // одномерный список значений
                $values = array_map("trim", $values);
            }else{
                $tsv = $values;
                $values = array();
                foreach($tsv as $record){
                    $values[ trim($record["caption"]) ] = trim($record["value"]);
                };
                unset($record);
            };
        };
        
    }; // switch form_values
    
    return $values;
}
function form_get_template_file($template){
    return TEMPLATES_DIR . "form/" . $template . ".form.htm";
}
