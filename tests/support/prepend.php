<?php
// auto_prepend_file for the test run: glog_util (loaded by composer's "files"
// autoload before PHPUnit reads its bootstrap) throws without DATA_DIR.
if (!defined('DATA_DIR')) {
    $dataDir = sys_get_temp_dir() . '/engine-tests-data/';
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0777, true);
    }
    define('DATA_DIR', $dataDir);
}
