<?php
function apply_template($template_name, $content_block = ""){
    global $_USER;
    global $_PAGE;
    global $CFG;
    global $_DATA;
    
    // dump($_DATA,"_DATA");
        
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    
    dosyslog(__FUNCTION__.": NOTICE: Applying template '".$template_name."'.");
    
    if ( ! $content_block ) $content_block = $template_name;
    
    $HTML = "";
    
    if ( ! defined("TEMPLATES_DIR") ){
        dosyslog(__FUNCTION__.": FATAL ERROR: Templates directory is not set. Check define file.");
        die("Code: et-".__LINE__);
    };
    
    if ( empty($_PAGE["templates"]) ){
        dosyslog(__FUNCTION__.": FATAL ERROR: There no templates defined for page '".$_PAGE["uri"]."'. Check pages file.");
        die("Code: et-".__LINE__);
    };

    
    if ( ! empty($_PAGE["templates"][$template_name]) ){
        $template_file = $_PAGE["templates"][$template_name];
    }else{
        if (file_exists(TEMPLATES_DIR . $template_name . ".htm")){
            $template_file = $template_name . ".htm";
        }elseif(file_exists(TEMPLATES_DIR . $template_name . ".block.htm")){
            $template_file = $template_name .".block.htm";
        }else{
            dosyslog(__FUNCTION__.": FATAL ERROR: Template '".$template_name."' for page '".$_PAGE["uri"]."' is not set in pages file and not found at default paths.");
            die("Code: et-".__LINE__."-".$template_name);
        };
    };
    
    $HTML = render_template($template_file, $_DATA);

    set_content($content_block, $HTML);  
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    return $HTML;
};
function get_content($block_name){
    global $S;
    global $CFG;
    global $_USER;
    global $_PAGE;
    
    static $blocks_chain = array();
    
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    dosyslog(__FUNCTION__.": NOTICE: Getting content block '".$block_name."'.");
    
    if (in_array($block_name,$blocks_chain)) {
        return ""; // don't parse block if it contained in itself directly or indirectly.
    }else{
        array_push($blocks_chain, $block_name);
    };
       
    $HTML = "";
    if (!empty($_PAGE->contents)){
        $isFound = false;
        foreach($_PAGE["content"] as $xmlcontent){
            if($xmlcontent["name"] == $block_name){
                $HTML .= (string) $xmlcontent;
                $isFound = true;
                dosyslog(__FUNCTION__.": NOTICE: Found block '".$block_name."' in page contents.");
            };
        };
    };
    
    if(!$HTML) {
        $HTML = apply_template($block_name);
    };
       
    if ($HTML){
        $res = preg_replace_callback("/%%([\w\d_\-\s]+)%%/",create_function('$m','return get_content($m[1]);'),$HTML); // all %%block%% replacing with result of get_content("block")
            
        $res = preg_replace_callback("/{cfg_(\w+)}/", create_function('$m', 'global $CFG; return $CFG["GENERAL"][$m[1]];'), $res);
        
        
        if ($res !== NULL) {
            $HTML = $res;
            dosyslog(__FUNCTION__.": NOTICE: Included in '".$block_name."' blocks parsed.");
        }else{
            dosyslog(__FUNCTION__.": ERROR: There is an error in preg_replace_callback() while parsing block '".$block_name."'.");
        };
    }else{
        dosyslog(__FUNCTION__.":  FATAL ERROR: Content block '".$block_name."' for page '".$_PAGE["uri"]."' is empty.");
        die("Code: et-".__LINE__);
    };
    
    if (array_pop($blocks_chain) !== $block_name) {
        dosyslog(__FUNCTION__.": ERROR: Logic error in blocks chain.");
    };
       
   
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    return $HTML;
};
function render_template($template_file, $data = array() ){
    global $CFG;
    global $_PAGE;
    global $_USER;
    
    if ( ! file_exists(TEMPLATES_DIR . $template_file)){
        dosyslog(__FUNCTION__.": FATAL ERROR: Template file '".$template_file."' is not found.");
        die("Code: et-".__LINE__."-".$template_file);
    };
    
    if (is_array($data)) extract($data);
    
    dosyslog(__FUNCTION__.": NOTICE: Rendering template file '".$template_file."'.");
    // dosyslog(__FUNCTION__.": NOTICE: Rendering template file '".$template_file."' with data '" . json_encode($data) . "'.");
    
    ob_start();
        include TEMPLATES_DIR . $template_file;
        $HTML = ob_get_contents();
    ob_end_clean();
    return $HTML;
}
function set_content($block_name, $content){
    global $_PAGE;
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    dosyslog(__FUNCTION__.": NOTICE: Setting content block '".$block_name."'.");
    
    if (empty($content)){
        die("Code: et-".__LINE__);
    };
    
    if (empty($_PAGE["content"])) $_PAGE["content"] = array();
    $_PAGE["content"][$block_name] = $content;
    
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
};
function set_template_file($template_name,$template_file){
    global $_PAGE;

    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb."); 
    dosyslog(__FUNCTION__.": NOTICE: Setting template '".$template_name."' < '".$template_file."'.");

    if ( empty($_PAGE["templates"])) {
        $_PAGE["templates"] = array();
    };
      
    $_PAGE["templates"][$template_name] = $template_file;
    
    
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
}; // function
