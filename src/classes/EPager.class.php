<?php
class EPager implements ArrayAccess, jsonSerializable
{
    protected $pager;
    protected $current;
    protected $count;        // number of pages
    protected $url_template;
    protected $url_params;

    public function __construct($url_template, $items_count, $items_per_page, $current_page, array $url_params=array(), $width = 10){
        $min_width = 7;
        if ($width < $min_width){
            throw new Exception("Pager width should be equal or more than 7. You pass $width.");
        }
        if ($current_page < 1) $current_page = 1;
        $n = ceil($items_count/$items_per_page); // number of pages total

        $pager = array();

        if ($items_count > $items_per_page){

            $pager[] = (int) $current_page;

            // Wings
            $l = $current_page-1;
            $r = $current_page+1;
            while(count($pager) < min($width, $n)){
                if ($l>=1){
                    array_unshift($pager, $l--);
                    if (count($pager) == min($width, $n)){
                        break;
                    };
                };
                if ($r<=$n){
                    array_push($pager, $r++);
                }
            };

            // Right end
            if ( $pager[count($pager)-2] != $n-1){
                $pager[count($pager)-2] = "...";
            };
            $pager[count($pager)-1] = $n;

            // Left end
            if ($pager[1] > 2){
                $pager[1] = "...";
            };
            $pager[0] = 1;
        };


        $this->pager   = $pager;
        $this->count   = $n;
        $this->current = $current_page;
        $this->url_template = $url_template;
        $this->url_params   = $url_params;
    }

    public function url($page){
        $data = $this->url_params;
        $data["page"] = $page;

        return glog_render_string($this->url_template, $data);
    }

    public function getUrl(){
        $url = array();
        // Previous link
        if ($this->current > 1){
            $url[$this->current - 1] = $this->url($this->current - 1);
        };
        // visible pages links
        foreach($this->pager as $p){
            if (is_numeric($p)){
                $url[$p] = $this->url($p);
            };
        };
        // next link
        if ($this->current < $this->count){
            $url[$this->current + 1] = $this->url($this->current + 1);
        };

        return $url;
    }

    /* ArrayAccess implementation */
    public function offsetSet($offset, $value) {

    }

    public function offsetExists($offset) {
        return isset($this->$offset);

    }

    public function offsetUnset($offset) {
    }

    public function offsetGet($offset) {

        if (isset($this->$offset)) {
            return $this->$offset;
        }else{

            if ($offset == "url"){
                return $this->getUrl();
            };

            if (DEV_MODE){
                dosyslog(__METHOD__.get_callee().": FATAL ERROR: Neither property '".$offset."' nor method '"."get".ucfirst($offset)."' are exists in class '".__CLASS__."'.");
                die("Code: ".__CLASS__."-".__LINE__."-".$offset);
            };
            return null;
        }
    }

    /* jsonSerializable implementation */
    public function jsonSerialize(){

        $res = array();
        foreach( get_class_vars(__CLASS__) as $property => $default_value){
            $res[$property] = $this->$property;
        };

        $res["url"] = $this->getUrl();

        return $res;

    }
}
