<?php
// Fake db collaborators. Load only in tests that do NOT require src/functions/db.php.
use Engine\Tests\Support\Fakes;

if (!function_exists('db_get_meta')) {
    function db_get_meta($db_table, $attr)
    {
        return Fakes::$meta[$db_table][$attr] ?? null;
    }
}
if (!function_exists('db_get_db_table')) {
    function db_get_db_table($name)
    {
        return $name;
    }
}
if (!function_exists('db_get')) {
    function db_get($db_table, $ids, $flags = 0, $limit = "", $offset = 0)
    {
        return Fakes::$dbGetResult;
    }
}
if (!function_exists('set_session_msg')) {
    function set_session_msg($message, $class = "info", array $options = [])
    {
        Fakes::$sessionMessages[] = [$message, $class];
    }
}
