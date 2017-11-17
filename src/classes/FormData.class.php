<?php
class FormData{
    private $db_table;
    private $form_name;
    private $id;
    private $files;
    private $changes;
    private $is_valid;
    private $errors;


    public function __construct($db_table, array $params, $action = ""){

        $this->changes = new ChangesSet;

        $this->id = ! empty($params["id"]) ? (int) $params["id"] : null;
        $this->files = array();


        $this->db_table = $db_table;
        if ( ! empty($params["form_name"]) ){
            $this->form_name = $params["form_name"];
        }elseif( ! empty($action) ){
            $this->form_name = $action . "_" . db_get_obj_name($db_table);
        }elseif( ! empty($params["action"]) ){
            $this->form_name = $params["action"] . "_" . db_get_obj_name($db_table);
        }else{
            $action = "edit";
            $this->form_name = $action . "_" . db_get_obj_name($db_table);
        };




        // Обработка загружаемых файлов
        $files = upload_files($this);


        if ( $files ){
            dosyslog(__METHOD__.": DEBUG: ". get_callee().": Обнаружены загруженные файлы: '".json_encode_array($files)."'.");
            foreach($files as $field_name => $uploaded_file_name){
                $this->files[ $field_name ] = $uploaded_file_name; // string or array of strings

                $this->changes->from[ $field_name ] = isset($params["from"][ $field_name ]) ? $params["from"][ $field_name ] : null;
                if (!empty($params["to"][ $field_name ]) && (db_get_field_type($this->db_table, $field_name) == "list")){ // $field_name represents the list of files. $uploaded_file_name is newly uploaded, but old filename from $params["to"][ $field_name ] must be stored.
                    $uploaded_files = $params["to"][ $field_name ];
                    if ($uploaded_files == "null"){ $uploaded_files = array();};
                    if (is_array($uploaded_file_name)){
                        $uploaded_files = array_merge($uploaded_files, $uploaded_file_name);
                    }else{
                        $uploaded_files[] = $uploaded_file_name;
                    };
                    $this->changes->to[ $field_name ] = $uploaded_files;
                }else{
                    $this->changes->to[ $field_name ] = $uploaded_file_name;
                };
            };
        };

        if ( ! empty($params["from"]) ){
            $diff1 = array_diff(array_keys($params["to"]), array_keys($params["from"]));
            if ( ! empty($diff1) ) dosyslog(__METHOD__.": ERROR: These fields of 'to' are absent in 'from' data:" . implode(", ",$diff1).".");
            $diff2 = array_diff(array_keys($params["from"]), array_keys($params["to"]));
            if ( ! empty($diff2) ){
                foreach($diff2 as $v) unset($params["from"][$v]);
                dosyslog(__METHOD__.": WARNING: These fields of 'from' are absent in 'to' data:" . implode(", ",$diff2).". Removed.");
            };

            // Убрать поля, значения которых не будут меняться (одинаковые)
            foreach($params["to"] as $k=>$v){
                if ( ! isset($params["from"][$k])) continue;

                if ( $params["to"][$k] == $params["from"][$k] ){
                    unset($params["to"][$k], $params["from"][$k]);

                };
            };

        };



        if (isset($params["from"]["created"])) unset($params["from"]["created"]);
        if (isset($params["to"]["created"])) unset($params["to"]["created"]);
        if (isset($params["from"]["modified"])) unset($params["from"]["modified"]);
        if (isset($params["to"]["modified"])) unset($params["to"]["modified"]);

        // Трансляция данных в формат для записи в БД.
        //   Для многострочных текстовых строк - заменить конец строки на \n;


        if (!empty($params["to"])){
            foreach($params["to"] as $what=>$v){
                if ( ! empty($this->changes->to[$what])) continue; //  don't touch data about uploaded files


                if ( is_string($v) ){
                    $this->changes->to[$what] = preg_replace('~\R~u', "\n", $v);
                }else{
                    $this->changes->to[$what] = $v;
                };

                if ( isset($params["from"][$what]) ){
                    $this->changes->from[$what] = ( $params["from"][$what] && is_string($params["from"][$what]) ) ? preg_replace('~\R~u', "\n", $params["from"][$what]) : $params["from"][$what];
                };

            };
        };




        // Валидация
        $this->validate();

        dosyslog(__METHOD__.": DEBUG: ". get_callee().": Оставлены поля [to] '".implode(", ",array_keys($this->changes->to))."'.");

    }

    public function __get($key){
        if (isset($this->$key)) return $this->$key;
    }

    protected function validate(){

        $this->errors = array();


        $object = $this->id  ? db_get($this->db_table, $this->id) : null;
        $fields_form = form_prepare($this->db_table, $this->form_name, $object);
        $changes_to = $this->changes->to;

        foreach($fields_form as $field){
            $name = $field["name"];

            if ( (strpos($this->form_name, "edit_") === 0) && ! isset($changes_to[$name]) ){ // validation for field which are not changed on edit should be skipped.
                continue;
            };

            // field specific validation rules
            if ( ! empty($field["validate"]) ){
                $rules = explode("|", $field["validate"]);

                foreach($rules as $rule){
                    $rule_params = array();
                    if (strpos($rule, ":") > 0){
                        $rule_params = explode(":",$rule);
                        $rule = array_shift($rule_params);
                    };

                    if (function_exists("validate_rule_".$rule)){
                        $rule_error_msg = call_user_func("validate_rule_".$rule, $name, isset($changes_to[$name]) ? $changes_to[$name] : "", $rule_params, $this);
                        if ( $rule_error_msg ){
                            if ( ! isset($this->errors[$name]) ) $this->errors[$name] = array();
                            $this->errors[$name][] = array("rule" => $rule, "msg" => $rule_error_msg);
                        };
                    }else{
                        dosyslog(__METHOD__.get_callee().": FATAL ERROR: Validate function for rule '".$rule."' on field '".$name."' on form '".$this->form_name."' is not defined.");
                        $this->is_valid = false;
                        throw new BadFunctionCallException("Validate function for rule '".$rule."' on field '".$name."' on form '".$this->form_name."' is not defined.");
                    };
                };

            }elseif (function_exists("validate_field_type_".$field["type"])){
                // field type specific validation
                $type_validation_err_msg = call_user_func("validate_field_type_".$field["type"], $name, isset($changes_to[$name]) ? $changes_to[$name] : "", $this);
                if ( $type_validation_err_msg ){
                    if ( ! isset($this->errors[$name]) ) $this->errors[$name] = array();
                    $this->errors[$name][] = array("rule" => "type_".$field["type"], "msg" => $type_validation_err_msg);
                };
            };

            if ( $field["required"] && (!isset($changes_to[$name]) || ($changes_to[$name] === "")) ){ // value is required but may be equal to 0 or "0"
                // required
                $this->is_valid = false;
                if ( ! isset($this->errors[$name]) ) $this->errors[$name] = array();
                $this->errors[$name][] = array("rule" => "required", "msg" => _t("Field")." '" . ($field["label"] ? $field["label"] : $field["name"]) . "' "._t("must be filled."));
            };

        };

        if ( empty($this->errors) ){
            $this->is_valid = true;
        }else{
            $this->is_valid = false;
            dosyslog(__METHOD__.get_callee().": WARNING: Form '".$this->form_name."' validation errors: '".json_encode_array($this->errors)."', form data: '". json_encode($this->changes)."'.");
        };
    }
}
