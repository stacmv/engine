<?php 
error_reporting(E_ALL);
ini_set("display_errors",1);

header("Content-type: text/html; charset=UTF-8");
define ("APP_DIR", "../../app/");
define ("INC_DIR", "../../inc/");
define ("ENGINE_DIR", INC_DIR . "engine/");
define ("DATA_DIR", "../../.data/");
define ("LOGS_DIR", "../../.logs/");
define ("DO_SYSLOG", true);
define ("SYSLOG", LOGS_DIR . basename(__FILE__).".".date("Y-m-d").".log.txt");

require APP_DIR . "require.php";

// ============================

?>
<!DOCTYPE html>
<head>
    <meta charset="UTF-8">
    <base href="../../">
    <link rel="stylesheet" href="assets/bs2/css/bootstrap-combined.min.css">
</head>
<body>
<div class="container">
<?php

$dbs = glob(DATA_DIR . "*.db");

foreach($dbs as $db_file){
    $db_name = pathinfo($db_file,PATHINFO_FILENAME);
    
    echo "<b>Checking '".$db_name."' ...</b><br>";
    db_check_schema($db_name);
    echo "<b>Done.<br>";
};
echo "Done all.";

?>
</div>
</body>
</html>