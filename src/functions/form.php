<?php
define ("FORM_PASS_SUBSTITUTION", "--cut--");

function form_prepare($db_table, $form_name, $object=""){

    dosyslog(__FUNCTION__.": DEBUG: " . get_callee() .": (" . $db_table . ", " . $form_name . ").");

    $subform_prepare = function($db_table, $form_name, $object){
        // Подготовка данных для полей формы
        $fields = array();
        $table = form_get_fields($db_table, $form_name);

        foreach($table as $v){
            $is_stand_alone = false;
            $value = isset($_SESSION["to"][$v["name"]])  ? $_SESSION["to"][$v["name"]] : ( !empty($object[ $v["name"] ]) ? $object[ $v["name"] ] : "");
            $value_from = ! empty($object[ $v["name"] ]) ? $object[ $v["name"] ] : "";
            $fields[ $v["name"] ] = form_prepare_field($v, $is_stand_alone, $value, $value_from);
        }
        unset($v);

        dosyslog(__FUNCTION__.": DEBUG: " . get_callee() .": (" . $db_table . ", " . $form_name . "): created fields: " . implode(",", array_map( function($i){ return $i["name"];}, $fields)) );
        return $fields;
    };

    if (db_get_meta($db_table, $form_name."_tabs")){
        $tabs_config = form_tabs_config($db_table, $form_name);


        $form_tabs = array_map(function($tab) use ($db_table, $tabs_config, $subform_prepare, $object){
            return array(
                "tab" => $tab,
                "caption" => $tabs_config[$tab],
                "fields" => $subform_prepare($db_table, $tab, $object),
            );
        }, array_keys($tabs_config));

        return array(
            "form_tabbed" => true,
            "tabs" => $form_tabs,
        );

    };

    return $subform_prepare($db_table, $form_name, $object);

}
function form_prepare_field($field, $is_stand_alone = false, $value = "", $value_from = ""){

        if ( empty($field["form_template"]) ){
            $field["form_template"] = "static";
        };

        $type     = $field["type"]; // тип поля в БД
        $template = $field["template"] = $field["form_template"];


        // Label
        if ($template == "hidden"){
            $field["label"] = "";
        };

        // Name & Id
        $name = $field["name"];
        $field["id"]    = $name;

        if ($is_stand_alone){
            $field["name_from"] = "";
            $field["name_to"]   = $name;
        }else{
            $field["name_from"] = "from[" . $name . "]";

            if ($type == "list"){
                $field["name_to"]   = "to[" . $name . "][]";
            }else{
                $field["name_to"]   = "to[" . $name . "]";
            };
        };

        // CSS Class
        if ( strpos($name, "phone") !== false ) $field["class"] = "phone";
        if ( strpos($name, "date")  !== false ) $field["class"] = "date";
        if ( strpos($name, "time")  !== false ) $field["class"] = "time";

        // Available values
        $field["values"] = form_get_field_values($field);


        // Current value
        // from значение поля
        $field["value_from"] = "";
        if ($value_from){
            switch($type){
                case "password":
                    $field["value_from"] = FORM_PASS_SUBSTITUTION;
                    break;
                case "timestamp":
                case "money":
                    $field["value_from"] = $value_from;
                    break;
                default:
                    $field["value_from"] = db_prepare_value($value_from, $type);
            };
        };

        // to значение поля
        $field["value"] = "";
        if ($value){
            switch($type){
                case "password":
                    $field["value"] = "";
                    break;
                case "money":
                case "list":
                    $field["value"] = $value;
                    break;
                default:
                    $field["value"] = db_prepare_value($value, $type);
            }
        }elseif(!empty($field["form_value_default"])){
            $field["value"] = form_get_field_values($field, "form_value_default");
            if (is_array($field["value"])){
                $field["value"] = $field["value"][0];
            };
        };

        if ( ($type !== "list") && is_array($field["value"]) ){
            if (count($field["value"]) == 1){
                $field["value"] = $field["value"][0];
            }else{
                // Несколько выбранных значений, при том, что может быть только одно. Не устанавливаем значение по умолчанию, перекладываем выбор на пользователя
                dosyslog(__FUNCTION__.": WARNING: Value of array type for " . $template . " field '".$field["name"]."' in form '".$form_name."'. Field[value]: '".json_encode($field["value"])."'. Check form config.");
            };
        }

        if ($template == "hidden"){
            if (empty($field["value"]) && !empty($field["values"])){
                $field["value"] = form_get_field_values($field);
                if (is_array($field["value"])){
                    $field["value"] = $field["value"][0];
                };
            };
        }

        // Подсказки, обязательность, валидация ...
        $field["hint"] = ! empty($field["form_hint"]) ? $field["form_hint"] : "";
        if ( ! isset($field["required"]) ) $field["required"]   = "";
        if ( ! isset($field["validate"]) ) $field["validate"]   = "";



        // Шаблон поля : показывать ли поле на форме и как именно
        $field["template_file"] = form_get_template_file($template);
        if ( !$field["template_file"] ){
            dosyslog(__FUNCTION__.": FATAL ERROR: Template file '".$template."' is not found.");
            if (DEV_MODE){
                die("Form template not exists: '".$template."'");
            }else{
                die("Code: efrm-".__LINE__."-".$template);
            };
        };

        // Убрать не нужные на форме свойства
        // unset($field["form"]);
        // unset($field["form_template"]);
        // unset($field["form_values"]);
        // unset($field["form_hint"]);

        return $field;
}
function form_prepare_view($items, $fields){

    if (!empty($items)){
        $tmp = $fields;
        $fields = array();
        foreach($tmp as $field){
            $fields[ $field["name"] ] = $field;
        };

        $items = array_map(function($item) use($fields){
            return form_prepare_view_item($item, $fields);
        }, $items);
    };

    return $items;
}
function form_prepare_view_item(EModel $item, $fields){

    return json_decode(json_encode($item), true);


}
function form_tabs_config($db_table, $form_name){
    $schema = db_get_meta($db_table, $form_name."_tabs");

    $tabs_schema = explode("|", $schema);

    $config = array();
    foreach ($tabs_schema as $tab_str){
        list($tab, $caption) = array_map("trim", explode(":", $tab_str));
        $config[$tab] = $caption;
    }

    return $config;

}
function form_get_fields($db_table, $form_name){

    if (cached()) return cache();

    $schema = db_get_table_schema($db_table);

    if ( ! $schema ){
        dosyslog(__FUNCTION__.": " . get_callee() .": FATAL ERROR:  '".$db_table."' is not found in DB config.");
        die("Code: efrm-".__LINE__."-".$db_table);
    };

    $fields = array();
    foreach($schema as $v){

        if ( $form_name != "all" ){

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

                        // ////
                        if ($prop_key == "form_value_default"){
                            $tmp_marker = "_".uniqid()."_";
                            $prop_value = str_replace("||", $tmp_marker, $prop_value); // экранируем ||, на случай когда значение типа list
                        }
                        // ////

                        $tmp = explode("|", $prop_value);
                        if ( isset($tmp[$form_index]) ){
                            $v[$prop_key] = $tmp[$form_index];
                        }else{
                            $v[$prop_key] = $tmp[count($tmp)-1];  // если для формы свойство не задано явно, используем последнее явно заданное значение
                        }
                        unset($tmp);

                        // ////
                        if ($prop_key == "form_value_default"){
                            $v[$prop_key] = str_replace($tmp_marker, "||", $v[$prop_key]); // снимаем экран ||
                        }
                        // ////
                    };

                }
            }
            unset($prop_key, $prop_value);
        };


        if ( ! isset($v["label"]) ) $v["label"] = "";
        if ( ! isset($v["required"]) ) $v["required"] = "";

        $fields[ $v["name"] ] = $v;

    };
    unset($v, $schema);
    return cache($fields);
}
function form_get_field_values($field, $key = "form_values"){
    global $_DATA;
    global $_USER;


    if ($field["type"] == "boolean"){
        return array(1);
    };


    if ( ! isset($field[$key]) ){
        return array();
    };

    switch($field[$key]){
    case "lst":
        $values = glog_file_read_as_array( cfg_get_filename("settings", glog_codify($field["name"]) . ".lst") );
        $values = array_map("trim", $values);
        break;

    case "tsv":
        $values = array();
        $tsv = import_tsv( cfg_get_filename("settings", glog_codify($field["name"]) . ".tsv") );
        if ($tsv){
            foreach($tsv as $record){
                $values[] = array(
                    "value" => trim( isset($record[ $field["name"] ]) ? $record[ $field["name"] ] : $record["value"] ),
                    "caption" =>$record["caption"],
                );
            }
            unset($record);
        }
        unset($tsv);
        break;
    case "data":
        if (isset($_DATA[$field["name"]])){
            $values = $_DATA[$field["name"]];
        }else{
            dosyslog(__FUNCTION__.": WARNING: Not values for field '" . $field["name"] . "' are in _DATA.");
            $values = "";
        }
        break;
    case "data[id]":
        if (isset($_DATA["id"])){
            $values = array($_DATA["id"]);
        }else{
            dosyslog(__FUNCTION__.": WARNING: There are NO 'id' value for field '" . $field["name"] . "' in _DATA.");
            $values = "";
        }
        break;
    case "user_id":
        if (isset($_USER["id"])){
            $values = $_USER["id"];
        }else{
            dosyslog(__FUNCTION__.": WANING: Not values for field '" . $field["name"] . "' are in _USER.");
            $values = "";
        }
        break;
    default:

        if ( strpos($field[$key], "&") !== false ){
            $values = explode("&", $field[$key]);
        }elseif ( function_exists($field[$key]) ){
            $values = call_user_func($field[$key]);
        }else{
            $values = array($field[$key]);
        }

        if ( is_array($values) && ! empty($values) ){

            if ( isset($values[0]) && is_scalar($values[0]) ){ // одномерный список значений
                $values = array_map("trim", $values);
            }else{
                $values = array_map(function($v){
                    return array_map("trim", $v);
                }, $values);
            };
        };

    }; // switch form_values

    return $values;
}
function form_get_template_file($template){
    return cfg_get_filename("templates/form", $template . ".form.htm");
}
function form_get_action_link($form_name, $is_public=false){
    global $IS_IFRAME_MODE;
    global $CFG;

    $form_uri = implode("/", explode("_", $form_name, 2));


    return ($is_public ? "pub/" : "") . $form_uri . $CFG["URL"]["ext"] . ($IS_IFRAME_MODE ? "?i=1" : "");
}
