<?php
class ChangesSet {
    private $to = array();
    private $from = array();

    /**
     *  @throws Exception
     */
    public static function createFromString($to, $from = ""){
        /*
            Strings must follow format:
                "{key1}" = "{value1}"[\n
                "{key1}" = "{value1}"[\n
                ...]]

            [] - means "optional"
            {key} - string literal
            {value} - JSON literal
            "" - quotes are required
        */

        // if ( ! $to ) throw new Exception("Parameter 'to' must be non-empty.");

        $str_to_arr = function($s){
            $a = array();
            $m = array();
            if (preg_match_all('/(\w+)\s=\s(\[".+"\]\n|"[^"]+")/u', $s, $m)){

                foreach ($m[0] as $k => $v) {
                    $a[$m[1][$k]] = json_decode(str_replace(["\n","\t"],['\n', '\t'], trim($m[2][$k])), true);
                }
                return $a;
            }else{
                dosyslog("ChangesSet::createFromString: ERROR: string does not match regexp. String:'".$s."'.");
            }
            return $a;
        };

        $changes_to = $changes_from = array();

        if (is_string($to)   && ! empty($to))   $changes_to   = $str_to_arr($to);
        if (is_string($from) && ! empty($from)) $changes_from = $str_to_arr($from);

        return new self($changes_to, $changes_from);
    }
    public function __construct(array $to = array(), array $from = null ){

        $this->to = $to;
        $this->from = $from ? $from : array();

    }

    public function &__get($key){
        if ( isset($this->$key) ) return $this->$key;
    }

    public function __set($key, array $value){
        if ( isset($this->$key) ) {
            $this->$key = $value;
        }
    }

}
