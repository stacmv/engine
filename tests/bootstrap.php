<?php
// Test bootstrap. DATA_DIR (needed by glog_util) is defined by
// tests/support/prepend.php, which `make test` passes as auto_prepend_file.
error_reporting(E_ALL);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/support/helpers.php';
