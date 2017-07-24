<?php
define("ENGINE_TEMPLATE_DEBUG_LOGGING", false);

function apply_template($template_name, $content_block = ""){
    global $_USER;
    global $_PAGE;
    global $CFG;
    global $_DATA;

    // dump($_DATA,"_DATA");
    if (ENGINE_TEMPLATE_DEBUG_LOGGING) dosyslog(__FUNCTION__.": DEBUG: Applying template '".$template_name."'.");

    if ( ! $content_block ) $content_block = $template_name;

    $HTML = "";

    if ( empty($_PAGE["templates"]) ){
        dosyslog(__FUNCTION__.": FATAL ERROR: There no templates defined for page '".$_PAGE["uri"]."'. Check pages file.");
        die("Code: et-".__LINE__);
    };

    $template_file = find_template_file($template_name);

    if ($template_file){
        if (ENGINE_TEMPLATE_DEBUG_LOGGING) dosyslog(__FUNCTION__.": DEBUG: Start rendering template '".$template_name."'.");
        $HTML = render_template($template_file, /*array_map("escape_template_data",*/ (array) $_DATA/*)*/ );
        if (ENGINE_TEMPLATE_DEBUG_LOGGING)dosyslog(__FUNCTION__.": DEBUG: Finish rendering template '".$template_name."'.");

        set_content($content_block, $HTML);
    }else{
        dosyslog(__FUNCTION__.": ERROR: Can not find file for template '".$template_name."'.");
        if (DEV_MODE){
          die("Code: et-".__LINE__."-".$template_name);
        };
    }

    return $HTML;
};
function escape_template_data($data_item){

    if ( is_array($data_item) ){
        return array_map("escape_template_data", $data_item);
    }elseif(is_object($data_item)){

        if (method_exists($data_item, "jsonSerialize")){
            return array_map("escape_template_data", $data_item->jsonSerialize());
        }else{
            dosyslog(__FUNCTION__.get_callee().": FATAL ERROR: Class '".get_class($data_item)." has not method 'jsonSerialize'.");
            die("Code: et-".__LINE__."-".get_class($data_item)."-jsonSerialize");
        };

    }else{
        return htmlspecialchars($data_item, ENT_QUOTES, "UTF-8");
    };
}
function unescape_template_data($data_item, $mode="html"){

    if ( is_array($data_item) ){
        return array_map(
            function($data_item) use ($mode){
                return unescape_template_data($data_item, $mode);
            },
            $data_item
        );
    }else{
        return htmlspecialchars_decode($data_item, ENT_QUOTES);
    };
}
function get_content($block_name){
    global $CFG;
    global $_USER;
    global $_PAGE;

    static $blocks_chain = array();
    static $dont_parse_blocks = array();

    if (ENGINE_TEMPLATE_DEBUG_LOGGING) dosyslog(__FUNCTION__.": DEBUG: Getting content block '".$block_name."'.");

    if (in_array($block_name, $dont_parse_blocks)){
        if (ENGINE_TEMPLATE_DEBUG_LOGGING) dosyslog(__FUNCTION__.get_callee().": DEBUG: block ". $block_name . " shoud not be parsed.");
        return "%%".$block_name."%%";
    };


    if (in_array($block_name,$blocks_chain)) {
        return ""; // don't parse block if it contained in itself directly or indirectly.
    }elseif (in_array("form",$blocks_chain)){
        $dont_parse_blocks[] = $block_name;
        return "%%".$block_name."%%"; // don't parse block inside 'form' since it's not really block but some string in form data
    }else{
        array_push($blocks_chain, $block_name);
        if (ENGINE_TEMPLATE_DEBUG_LOGGING) dosyslog(__FUNCTION__.": DEBUG: blocks_chain: ".implode(", ", $blocks_chain));
    };

    $HTML = "";
    if ( ! empty($_PAGE["content"][$block_name]) ){
        $HTML .= $_PAGE["content"][$block_name];
        if (ENGINE_TEMPLATE_DEBUG_LOGGING) dosyslog(__FUNCTION__.": DEBUG: Found block '".$block_name."' in page contents.");
    };

    if(!$HTML) {
        $HTML = apply_template($block_name);
    };

    if ($HTML){
            $res = preg_replace_callback(
            "/%%([\w\d_\-\s\.]+)%%/",
            function($m) use ($block_name){
                return get_content($m[1]);
            },
            $HTML
        ); // all %%block%% replacing with result of get_content("block")

        $res = preg_replace_callback("/{cfg_(\w+)}/", function($m){
                global $CFG;
                return isset($CFG["GENERAL"][$m[1]]) ? $CFG["GENERAL"][$m[1]] : "";
            },
            $res
        );


        if ($res !== NULL) {
            $HTML = $res;
            if (ENGINE_TEMPLATE_DEBUG_LOGGING) dosyslog(__FUNCTION__.": DEBUG: Included in '".$block_name."' blocks parsed.");
        }else{
            dosyslog(__FUNCTION__.": ERROR: There is an error in preg_replace_callback() while parsing block '".$block_name."'.");
        };
    }else{
        dosyslog(__FUNCTION__.":  WARNING: Content block '".$block_name."' for page '".$_PAGE["uri"]."' is empty.");
        // die("Code: et-".__LINE__);
    };

    if (array_pop($blocks_chain) !== $block_name) {
        dosyslog(__FUNCTION__.": ERROR: Logic error in blocks chain.");
    };

    return $HTML;
};
function find_template_file($template_name){
    global $_PAGE;

    if (cached()) return cache();

    if ( ! empty($_PAGE["templates"][$template_name]) ){
        $template_file = cfg_get_filename("templates", $_PAGE["templates"][$template_name]);
    }else{

        $template_file = cfg_get_filename("templates", $template_name . ".htm");
        if (!$template_file){
            $template_file = cfg_get_filename("templates", $template_name . ".block.htm");
        };

        if (!$template_file){
            dosyslog(__FUNCTION__.": ERROR: Template '".$template_name."' for page '".$_PAGE["uri"]."' is not set in pages file and not found at default paths.");
            $template_file = false;
        };
    };

    return cache($template_file);
}
function render_template($template_file, $data = array() ){
    // Declare global which must be visible from within templates
    global $CFG;
    global $_SITE;
    global $_PAGE;
    global $_URI;
    global $_USER;
    global $IS_IFRAME_MODE;

    if ($template_file === false){
        dosyslog(__FUNCTION__.": ERROR: Can not find file for template '".$template_file."'.");
        return "";
    }

    if (ENGINE_TEMPLATE_DEBUG_LOGGING) dosyslog(__FUNCTION__.": DEBUG: Prepare to render template file '".$template_file."'.");

    $cacheable = strpos($template_file, ".cacheable") > 0 ? true : false;


    if ( ! file_exists( $template_file )){
        dosyslog(__FUNCTION__.": FATAL ERROR: Template file '".$template_file."' is not found.");
        die("Code: et-".__LINE__."-".$template_file);
    };

    if ($cacheable && file_cached(basename($template_file))){
        $HTML = file_cache_get(basename($template_file));
    }else{
        if (is_array($data)) extract($data);

        if (ENGINE_TEMPLATE_DEBUG_LOGGING) dosyslog(__FUNCTION__.": DEBUG: Rendering template file '".$template_file."'.");
        // dosyslog(__FUNCTION__.": NOTICE: Rendering template file '".$template_file."' with data '" . json_encode($data) . "'.");

        ob_start();
            include $template_file;
            $HTML = ob_get_contents();
        ob_end_clean();
        if (ENGINE_TEMPLATE_DEBUG_LOGGING) dosyslog(__FUNCTION__.": DEBUG: template file '".$template_file."' rendered.");

        if ($cacheable) file_cache_set(basename($template_file), $HTML);
    };

    return $HTML;
}
function set_content($block_name, $content){
    global $_PAGE;

    if (ENGINE_TEMPLATE_DEBUG_LOGGING) dosyslog(__FUNCTION__.": DEBUG: Setting content block '".$block_name."'.");

    if (empty($content)){
        dosyslog(__FUNCTION__.": WARNING: Content for block '".$block_name."' is empty.");
        // die("Code: et-".__LINE__);
    };

    if (empty($_PAGE["content"])) $_PAGE["content"] = array();
    $_PAGE["content"][$block_name] = $content;


};
function set_template_file($template_name,$template_file){
    global $_PAGE;


    dosyslog(__FUNCTION__.": NOTICE: Setting template '".$template_name."' < '".$template_file."'.");

    if ( empty($_PAGE["templates"])) {
        $_PAGE["templates"] = array();
    };

    if (file_exists( cfg_get_filename("templates", $template_file) )){
        $_PAGE["templates"][$template_name] = $template_file;
    }else{
        dosyslog(__FUNCTION__.": FATAL ERROR: Template '".$template_name."' file '".$template_file." does not exists.");
        die("Code: et-".__LINE__."-".$template_file);
    }



}; // function
/* *** */
function expand_youtube_links($data_item){

    /* Place these styles in your app CSS:

    <style>.embed-container { position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%; } .embed-container iframe, .embed-container object, .embed-container embed { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }</style>
    */



    return preg_replace("/https:\/\/(youtu\.be|www\.youtube\.com\/embed)\/(\w+)/", "\n<div class='embed-container'><iframe src='http://www.youtube.com/embed/$2' frameborder='0' allowfullscreen></iframe></div>", $data_item);

}
