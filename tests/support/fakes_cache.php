<?php
// Fake request-cache and i18n helpers: no caching, identity translation.
if (!function_exists('cached')) {
    function cached()
    {
        return false;
    }
}
if (!function_exists('cache')) {
    function cache($value = null)
    {
        return $value;
    }
}
if (!function_exists('_t')) {
    function _t($s)
    {
        return $s;
    }
}
