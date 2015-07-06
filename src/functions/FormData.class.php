<?php
class FormData{
    private $db_table;
    private $form_name;
    private $id;
    private $files;
    private $changes;
    private $is_valid;
    private $errors;
    
    
    public function __construct($db_table, array $params){
        
        $this->changes = new ChangesSet;
        
        $this->id = ! empty($params["id"]) ? (int) $params["id"] : null;
        $this->files = array();
        
        
        $this->db_table = $db_table;
        if ( ! empty($params["form_name"]) ){
            $this->form_name = $params["form_name"];
        }elseif( ! empty($params["object_name"]) && ! empty($params["action"]) ){
            $this->form_name = db_get_db_table($params["object_name"]) . "_" . $params["action"];
        }else{
            dosyslog(__METHOD__.get_callee().": FATAL ERROR: Could not determine form_name. There are 'form_name' or 'object_name' & 'action' fields must be defined in params.");
            die("Code: ".__CLASS__ . "-".__LINE__);
        };
        
        
        
        
        // Обработка загружаемых файлов
        $files = upload_files($this);

        if ( $files ){
            dosyslog(__METHOD__.": DEBUG: ". get_callee().": Обнаружены загруженные файлы: '".implode(", ",$files)."'.");
            foreach($files as $field_name => $uploaded_file_name){
                $this->files[ $field_name ] = $uploaded_file_name;
                
                $this->changes->from[ $field_name ] = isset($params["from"][ $field_name ]) ? $params["from"][ $field_name ] : null;
                $this->changes->to[ $field_name ] = $uploaded_file_name;
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
        
        foreach($params["to"] as $what=>$v){
            if ( $v ){
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
    
        $this->is_valid = true;
        $this->errors = array();
        
        
        $object = $this->id  ? db_get($this->db_table, $this->id) : null;
        $fields_form = form_prepare($this->db_table, $this->form_name, $object);
        $changes_to = $this->changes->to;
                
        foreach($fields_form as $field){
            $name = $field["name"];
            
            if ( ! isset($changes_to[$name]) ) continue;
            
            // required
            if ( $field["required"] && empty($changes_to[$name]) ){
                $this->is_valid = false;
                $this->errors[] = array($name => array("rule" => "required", "msg" => "Поле '" . ($field["label"] ? $field["label"] : $field["name"]) . "' обязательно для заполнения."));
            };
            
            // field specific validation rules
            if ( ! empty($field["validate"]) ){
                $rules = explode("|", $field["validate"]);
            
                foreach($rules as $rule){
                    $params = array();
                    if (strpos($rule, ":") > 0){
                        $params = explode(":",$rule);
                        $rule = array_shift($params);
                    };
                    
                    if (function_exists("validate_".$rule)){
                        list($$this->is_valid, $rule_errors) = call_user_func_array("validate_".$rule, $name, $changes_to[$name], $params);
                    }else{
                        dosyslog(__METHOD__.get_callee().": ERROR: Validate function for rule '".$rule."' on field '".$name."' on form '".$this->form_name."' is not defined.");
                        $this->is_valid = false;
                    };
                    
                    if ( ! empty($rule_errors) ){
                        $this->errors = array_merge($this->errors, $rule_errors);
                    };
                    
                };
            };
            
        };
        
        if ( ! empty($this->errors) ){
            dosyslog(__METHOD__.get_callee().": WARNING: Form '".$this->form_name."' validation errors: '".json_encode_array($this->errors)."', form data: '". json_encode_array($this->changes)."'.");
        };
        
    }
}