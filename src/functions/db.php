<?php
if (!defined("TEST_MODE")) define ("TEST_MODE", false);
if (!defined("DB_NOTICE_QUERY")) define("DB_NOTICE_QUERY",true); // писать запросы в лог
define("DB_LIST_DELIMITER", "||"); // разделитель элементов в полях типа list
define("DB_MONEY_PRECISION", 2);  // количество знаков после запятой для значений типа money
define("DB_PREPARE_VALUE", 32); // флаг для db_get(), что надо вернуть поля типа list, json и др. в виде готовом для записи в БД, т.е. в виде строки, возвращаемой db_prepare_value()
define("DB_DONT_PARSE", 64); //  флаг для db_get() и db_select(), что надо вернуть данные как есть, не выполняя db_parse_result()
define("DB_RETURN_ID", 1);  // флаг для db_find() и db_select(), что надо вернуть только ID
define("DB_RETURN_ROW",2);  // флаг для db_find() и db_select(), что надо вернуть всю запись
define("DB_RETURN_ONE",4);  // флаг для db_find(), что надо вернуть только одну запись, а не список
define("DB_RETURN_DELETED",8);  // флаг для db_get() и db_find(), что надо вернуть и удаленные записи тоже
define("DB_RETURN_ID_INDEXED",16);  // флаг для db_get() и db_select(), что надо вернуть записи с ключами, равными id, а не порядковым номрам
define("DB_RETURN_NEW_FIRST", 128); // флаг для db_get() и db_select(), что надо вернуть записи в порядке убывания created

$_DB = array();

/* *********************************************************** */
function db_get_obj_name($db_table){
    if ( ! is_string($db_table) ){
        dosyslog(__FUNCTION__.get_callee().": FATAL ERROR: Parameter 'db_table' should be of type 'string', '" . gettype($db_table). "' given.");
        die("Code: db-".__LINE__);
    };

    $a =  explode(".",$db_table);

    if ( (count($a) >=2) && ($a[0] == $a[1]) ){   // main table in db; table name == db name
        array_shift($a);
    };

    $a = array_map("_singular", $a);

    return implode(".", $a);
};
function db_get_db_table($obj_name){
    if ( ! is_string($obj_name) ){
        dosyslog(__FUNCTION__.get_callee().": FATAL ERROR: Parameter 'obj_name' should be of type 'string', '" . gettype($obj_name). "' given.");
        die("Code: db-".__LINE__);
    };
    $a =  explode(".",$obj_name);

    if (count($a) == 1){   // main table in db; table name == db name
        array_push($a, $a[0]);
    };
    $a = array_map("_plural", $a);

    return implode(".", $a);
};
function db_get_name($db_table){

    if (strpos($db_table,".") != false){
        list($name, $table) = explode(".", $db_table, 2);
    }else{
        $name = $db_table;
        $table = $name;
    };

    if ( ! $name && $table) $name = $table;

    return $name;
}
function db_get_table($db_table){

    if (strpos($db_table,".") != false){
        list($name, $table) = explode(".", $db_table, 2);
    }else{
        $name = "";
        $table = $db_table;

    };

    if ( $name && ! $table) $table = $name;

    return $table;
}
function db_get_meta($db_table, $attr){
    $dbs = array_map("xml_to_array", db_get_tables_list_from_xml($db_table));

    $db = array_reduce($dbs, function($acc, $db) use ($db_table){
        if (!is_null($acc)) return $acc;
        if ($db["@attributes"]["name"] == db_get_name($db_table)){
           $acc = $db;
        };
        return $acc;
    }, null);

    $tables = $db["table"];

    if ($tables){

        $table = array_reduce($tables, function($acc, $table) use ($db_table){
            if (!is_null($acc)) return $acc;
            if ($table["@attributes"]["name"] == db_get_table($db_table)){
               $acc = $table;
            };
            return $acc;
        }, null);

        $table_meta = $table["@attributes"];

        return isset($table_meta[$attr]) ? $table_meta[$attr] : (isset($db["@attributes"][$attr]) ? $db["@attributes"][$attr] : "");
    };

    return "";
}
/* ***********************************************************
**  DATABASE FUNCTIONS
**
** ******************************************************** */
function db_add($db_table, ChangesSet $data, $comment=""){
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    global $_USER;

    // ДОРАБОТАТЬ: добавить проверку существования полей в таблице и обработку ошибок

    $added_id = db_insert($db_table, $data);

    if ( $added_id ){

        if (db_get_table($db_table) !== "history") {

            $changes = $data;

            if ( ! db_add_history($db_table, $added_id, (!empty($_USER["id"]) ? $_USER["id"] : 0), "db_add", $comment, $changes)){
                // ДОРАБОТАТЬ: реализовать откат операции INSERT

                dosyslog(__FUNCTION__.": ERROR: " . get_callee() . " Can not add record to history table of db '".$db_table."'.");
                if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
                return false;
            };

        };

        if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
        return  $added_id;
    }else{
        return false;
    }
};
function db_add_history($db_table, $objectId, $subjectId, $action, $comment, ChangesSet $changes){

    $res = true;

    $db_name =  db_get_name($db_table);
    $table_name = db_get_table($db_table);

    // Есть ли таблица history в БД
    $skipHistoryTable = false;
    $tables_list = db_get_tables_list($db_name, $skipHistoryTable);
    if ( ! in_array($db_name . ".history", $tables_list) ){
        return true; // не нужно писать историю для этой БД
    };

    db_set($db_name . ".history");


    if ($db_name == $table_name){
        $db = $db_name;
    }else{
        $db = $db_table;
    };

    $history_record = array(
        "db"        => $db,
        "action"    => $action,
        "objectId"  => (int) $objectId,
        "subjectId" => (int) $subjectId,
        "subjectIP" => ! empty($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : null,
        "timestamp" => time(),
    );

    if ($comment) $history_record["comment"] = $comment;

    $changes_from = $changes_to = array();
    foreach($changes->to as $key=>$v_to){
        if ( empty($changes->from[$key]) ){ // action == add
            $v_from = null;
        }else{
            $v_from = $changes->from[$key];
        };
        if ($v_from != $v_to){
            $changes_from[] = $key." = ".json_encode_array($v_from);
            $field_type = db_get_field_type($db_table, $key);
            if ( $field_type == "password" ){
                $changes_to[]   = $key." = ".json_encode_array(db_prepare_value($v_to, $field_type));
            }else{
                $changes_to[]   = $key." = ".json_encode_array($v_to);
            };
        };
    };
    $history_record["changes_from"] = implode("\n",$changes_from);
    $history_record["changes_to"]   = implode("\n",$changes_to);


    $res = db_add($db_name . ".history", new ChangesSet($history_record));

    if (!$res){
        dosyslog(__FUNCTION__.": ERROR: " . get_callee() . " Can not add history record into '".$db_name."' db.");
        $res = false;
    };

    return $res;
};
function db_check_schema($db_table){ // проверяет схему таблицы $db_table базыданных $db_name на соответствие файлу db.xml

    $db_name = db_get_name($db_table);
    $table = db_get_table($db_table);

	$columns = array(); // поля в текущей БД
	$fields_to_be = array(); // поля, описанные в XML
	$fields_to_add = array(); // поля, которые есть в XML, но нет в текущей БД
	$fields_to_del = array(); // поля, которые должны быть удалены (и за "бэкаплены" в поле extra)


    $schema = db_get_table_schema($db_table);
    if (empty($schema)){
        echo "<p class='alert alert-warning'>Таблица ".$db_name.".".$table." не определена в XML.<p>";
        return;
    };
    $dbh = db_set($db_table);


    if (db_is_exists($db_table)){
        $columns[$table] = array_keys(db_get_table_columns($db_table));
    }else{ // таблица не существует в БД
        echo "Таблица ".$table." отсутствует в БД ".$db_name.". Она будет создана движком при первом реальном использовании. <br>";
        return;                 // на надо создавать таблицу в ходе миграции, она будет создана движком при первом реальном использовании.
    };

    $fields_to_add[$table] = array();
    $fields_to_be[$table] = array();
    $fields_to_del[$table] = array();
    foreach($schema as $field){
        $fields_to_be[$table][] = (string) $field["name"];
        if ( ! in_array($field["name"], $columns[$table]) ){ // поле есть в xml, но нет в реальной БД.
				$fields_to_add[$table][] = $field["name"];
			};
		};
    foreach($columns[$table] as $k=>$v){
        if (!in_array($v, $fields_to_be[$table])) $fields_to_del[$table][] = $v;
    };


	echo "<h3>БД $db_name</h3>";

	if (!empty($fields_to_add)){

		foreach($fields_to_add as $table=>$fields){

			if (!empty($fields)){

                ?>
                    <table class="table table-bordered">
                        <caption><?=$table;?></caption>
                        <tr><th>Текущие поля</th><td><?=implode(", ", $columns[$table]);?></td></tr>
                        <tr><th>Поля</th><th>Операции</th></tr>
                        <?php foreach(array_merge($columns[$table], $fields_to_add[$table]) as $k=>$v):?>

                            <?php if (in_array($v, $fields_to_add[$table]) ):?>
                                <tr><th><?=$v;?></th> <td><i class="icon icon-plus text-success"></i></td> </tr>
                            <?php elseif(in_array($v, $fields_to_del[$table]) ):?>
                                <tr><th><?=$v;?></th> <td><i class="icon icon-remove text-danger"></i></td> </tr>
                            <?php endif;?>
                        <?php endforeach;?>
                    </table>
                <?php


				$temp_table = $table."_".date("Y_m_d__H_i")."_bak";
				$query = array();
				if (!empty($fields)){
					$query[] = "CREATE TABLE ".$temp_table." (".implode(", ",$columns[$table]).", " . implode(", ", $fields).");\n";
					$query[] = "INSERT INTO ".$temp_table." SELECT *, " . implode(", ", array_fill(0, count($fields), "NULL")) . " FROM ".$table.";\n";
					if (!empty($fields_to_del[$table])){
						$query[] = "backup";
					};
					$query[] = "DROP TABLE ".$table.";\n";
					$query[] = db_create_table_query($db_name.".".$table)."\n";
					$query[] = "INSERT INTO ".$table." SELECT ".implode(", ",$fields_to_be[$table])." FROM ".$temp_table.";";
					echo "<p>table <b>".$table."</b>:</p>";
				};

				if ($query){
					echo "<p>Выполненные запросы:</p>";
					echo "<ol>";
					$dbh = db_set($db_table);
                    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $dbh->beginTransaction();
                    dosyslog(__FUNCTION__.": INFO: Begin SQL transaction.");
                    try{
					foreach($query as $q){
                        set_time_limit(300);
						echo "<li>".$q;
						if ($q=="backup"){
                                if (in_array("extra", $columns)){
							$qb = "SELECT id, ".implode(", ",$fields_to_del[$table]).", extra FROM ".$temp_table.";";
							echo "<p>$qb</p>";
                            if (DB_NOTICE_QUERY) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " SQL: '".$qb."'.");
							$res_b = $dbh->query($qb);
							if ($res_b){
								echo "<ol>";
								while ( ($row = $res_b->fetch(PDO::FETCH_ASSOC)) !== false){
									if (!empty($row["extra"])){
										$extra_decoded = json_decode_array($row["extra"],true);
										if ($extra_decoded && is_array($extra_decoded)){
											$extra = $extra_decoded;
										}else{
											$extra["_invalid__extra_".date("Y-m-d")] = $row["extra"];
										};
									}else{
										$extra = array();
									};

									foreach($fields_to_del[$table] as $ftd){
										if ( ($row[$ftd]!=="") && ($row[$ftd] !== NULL) ){
											$extra[$ftd."__".date("Y-m-d")] = $row[$ftd];
										};
									};
									$extra_encoded = json_encode_array($extra);

									if (!empty($extra) && ($extra_encoded != $row["extra"]) ){
										$qu = "UPDATE ".$temp_table." SET extra = ".$dbh->quote($extra_encoded)." WHERE id=".(int) $row["id"].";";
										echo "<li>$qu</li>";
                                        if (DB_NOTICE_QUERY) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " SQL: '".$qu."'.");
										$res_u = $dbh->exec($qu);

										if(!$res_u){
                                            dosyslog(__FUNCTION__.": ERROR: " . get_callee() . " SQL ERROR:  [" . $temp_table . "]: '".db_error($dbh)."'. Query: '".$qu."'.");
											dosyslog(__FUNCTION__.": FATAL ERROR: Can not backup data while migrate DB schema. Query failed: '$qu'.");
											die("FATAL ERROR: Can not backup data while migrate DB schema.");
                                                };
                                            };
                                        }; //while
                                        echo "</ol>";
                                    }else{
                                        dosyslog(__FUNCTION__.": ERROR: " . get_callee() . " Can not backup data while migrate '".$db_table."'. Query failed: '$qb'.");
                                        die("FATAL ERROR: Can not backup data while migrate DB schema.");
                                    };
                                }else{
                                    dosyslog(__FUNCTION__.": WARNING: " . get_callee() . " Can not backup data while migrate '".$db_table."'. Field 'extra' is not exists in table schema. Backup skipped.");
                                    echo "<div class='alert alert-warning'>Can not backup data while migrate '".$db_table."'. Field 'extra' is not exists in table schema. <b>Backup skipped!</b></div>";
                                }
                            }else{
                                if (DB_NOTICE_QUERY) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " SQL: '".$q."'.");
                                $res[$table][$q] = $dbh->query($q);
                                if(!$res[$table][$q]){
                                    dosyslog(__FUNCTION__.": FATAL ERROR: " . get_callee() . " Can not backup data while migrate DB schema. Query failed: '$q'. SQL ERROR:  [" . $temp_table . "]: '".db_error($dbh).".");
                                    die("FATAL ERROR: Can not backup data while migrate DB schema. SQL ERROR:  [" . $temp_table . "]: '".db_error($dbh).".");
                                };
                            };
                            echo "</li>";
                        };
                        $dbh->commit();
                        dosyslog(__FUNCTION__.": INFO: SQL transaction commited.");
                    } catch(PDOException $e){
                        $dbh->rollback();
                        dosyslog(__FUNCTION__.": ERROR: SQL ERROR: ". json_encode($e->errorInfo));
                        dosyslog(__FUNCTION__.": WARNING: SQL transaction rollback!");
                        echo "<div class='alert alert-danger'>SQL ERROR: ". json_encode($e->errorInfo)."</div>";
                        echo "<div class='alert alert-warning'>SQL transaction rollback!</div>";
                    }
                    echo "</ol>";
				};
			}else{
				echo "<p>Изменения в схемe таблицы ".$db_name.".".$table." не обнаружены.</p>";
			};
		};
	}else{
		echo "<p>Изменения в схемах БД не обнаружены.</p>";
	};
	echo "</pre>";



};
function db_create_insert_query($db_table, array $keys){

    $table_name = db_get_table($db_table);

    $query_base = "INSERT INTO ".$table_name." (" . implode(", ", $keys) . ") VALUES ";
    $query = "";

    $timestamp = time();

    $placeholders = array_fill(0, count($keys), "?");
    $query .= $query_base . " (" . implode(", ", $placeholders) . ");\n";

    return $query;

}
function db_create_table_query($db_table){

    global $CFG;

    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");

    $dbh = new PDO("sqlite::memory:");

    $table = db_get_table_schema($db_table);

    if (empty($table)){
        dosyslog(__FUNCTION__.": FATAL ERROR: " . get_callee() . " Can not get table '".$db_table."' from XML.");
        die("platform_db:create-table-1");
    };

    $table_name = db_get_table($db_table);

    $query = "CREATE TABLE ".$dbh->quote($table_name);

    $aTmp = array();
    foreach($table as $field){
        $tmp = (string) $field["name"];
        $type = ! empty($field["type"]) ? (string) $field["type"] : "string";
        $unique = ! empty($field["unique"]);
        switch ($type){
            case "autoincrement": $tmp .= " INTEGER PRIMARY KEY"; break;
            case "number":        $tmp .= " NUMERIC NOT NULL ON CONFLICT REPLACE DEFAULT 0"; break;
            case "money":         $tmp .= " NUMERIC NOT NULL ON CONFLICT REPLACE DEFAULT 0"; break;
            case "timestamp":     $tmp .= " NUMERIC"; break;
            case "string":        $tmp .= " TEXT"; break;
            case "json": $tmp .=" TEXT"; break;
        };

        if ($unique) $tmp .= " UNIQUE";


        $aTmp[] = $tmp;
    };
    $query .= " (" . implode(", ",$aTmp).")";

    $dbh = null;
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    return $query;
};
function db_create_update_query($db_table, $keys){

    $table_name = db_get_table($db_table);
    // Create query.

    $query = "UPDATE ".$table_name." SET ";
    $tmp = array();
    foreach($keys as $k){
        $tmp[] = $k . " = ? ";
    };
    $tmp[] = "modified = '" . time(). "'";
    $query .= implode(", ",$tmp);

    $query .= " WHERE id = ? ;";

    return $query;

}
function db_delete($db_table, $id, $comment=""){
    global $_USER;
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    // ДОРАБОТАТЬ: добавить проверку существования полей в таблице и обработку ошибок
    // или ДОРАБОТАТЬ: переписать функцию с использование db_edit.

    //dump($comment,"comment");

	$dbh = db_set($db_table);
	$object = db_get($db_table, $id, DB_RETURN_DELETED);
    $table_name = db_get_table($db_table);

    if (empty($object)) {
        dosyslog(__FUNCTION__.": Attempt to delete object which is absent in DB '".$db_table."'. ID='".$id."'.");
        return array(false, "wrong_id");
    };

    // Check if object can be deleted - field 'deleted' is in table schema.

    if (!array_key_exists("deleted", $object)){
        dosyslog(__FUNCTION__.": Attempt to delete object from DB '".$db_table."' which does not support delete operation. ID='".$id."'.");
        return array(false, "delete_not_supported");
    };

    // Check if object is already deleted - field 'deleted' is set to some value.
    if (!empty($object["deleted"])){
        dosyslog(__FUNCTION__.": Attempt to delete object which is already deleted ('".date("Y-m-d H:i:s",$object["deleted"])."') from DB '".$db_table."'. ID='".$id."'.");
        return array(true, "already_deleted");
    };

    // Create query.

    $query = "UPDATE ".$table_name." SET deleted=".time()." WHERE id=".$id.";";

    if (DB_NOTICE_QUERY) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " SQL: '".$query."'.");
    $res = $dbh->exec($query);

    if (!$res){
        dosyslog(__FUNCTION__.": ERROR: " . get_callee() . " SQL ERROR:  [" . $db_table . "]: '".db_error($dbh)."'. Query: '".$query."'.");
        if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
        return array(false,"db_fail");
    }else{
        dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " Update db (delete): '".$query."'");

        if (!db_add_history($db_table, $id, $_USER["id"], "db_delete", $comment, new ChangesSet)){
            // ДОРАБОТАТЬ: реализовать откат операции UPDATE

            dosyslog(__FUNCTION__.": ERROR: " . get_callee() . " Can not add record to history table od db '".$db_table."'.");
            if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
            return array(false, "history_fail");
        };


        if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
        return array(true,"success");

    };
}
function db_edit($db_table, $id, ChangesSet $changes, $comment=""){

    global $_USER;

    // ДОРАБОТАТЬ: добавить проверку существования полей в таблице и обработку ошибок

    //dump($comment,"comment");
    $dbh = db_set($db_table);
    $object = db_get($db_table, $id, DB_RETURN_DELETED);

    $table_name = db_get_table($db_table);

    if (empty($object)) {
        dosyslog(__FUNCTION__.": Attempt to edit object which is absent in DB '".$db_table."'. ID='".$id."'.");
        return array(false, "wrong_id");
    };

    // dump($changes,"changes_1");

    // Check that the changes are really change something.
    foreach ($changes->to as $key=>$value){
        if (isset($changes->from[$key]) && ($changes->from[$key] === $changes->to[$key]) ){
            unset($changes->to[$key]);
            unset($changes->from[$key]);
        };
    };
    unset($key, $value);


    $changes_arr = (array) $changes->to;
    if (empty($changes_arr)){
        dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " No changes.");
        return array(true, "no_changes");
    };



    // Check if object is in state it supposed to be in.
    $conflicted = array(); // список полей, у которых состояние from не совпадает с текущим состоянием в БД.
    $not_existed = array(); // поля, которые отсутствуют у объекта, взятого из БД.
    foreach ($changes->to as $key=>$value){
        if ( ! array_key_exists($key, $object) ){
            $not_existed[] = $key;
            continue;
        };

        $field_type = db_get_field_type($db_table, $key);

        if ( ($field_type == "password") ) { // при смене пароля оригинальный пароль (или хэш) на сервер от клиента не приходит, только новый.
            continue;
        }

        if (isset($changes->from[$key]) &&  (db_prepare_value($changes->from[$key], $field_type) != db_prepare_value($object[$key], $field_type))){
            // Проблема в переводах строки?  Хак. На случай когда в БД уже есть данные с неверными переводами строки.
            if (is_string($changes->from[$key]) && is_string($object[$key]) && (preg_replace('~\R~u', "\n", $changes->from[$key]) == preg_replace('~\R~u', "\n", $object[$key])) ){
                // Это не конфликт.
            }else{
                $conflicted[] = $key . "(passed 'from': '".(is_string($changes->from[$key]) ? $changes->from[$key] : json_encode($changes->from[$key]))."', in db: '".(is_string($object[$key]) ? $object[$key] : json_encode($object[$key]))."')";
            };
        };
    };
    unset($key, $value);

    if ( ! empty($not_existed) ){
        dosyslog(__FUNCTION__.": ERROR: " . get_callee() . " These fields are not exist in [".$db_table."]: ". implode(", ", $not_existed).".");
    };
    if ( ! empty($conflicted) ){
        dosyslog(__FUNCTION__.": WARNING " . get_callee() . " Changes conflict: object state changed during editing time: ". implode(",", $conflicted) . ".");
        return array(false,"changes_conflict");
    };



    $update_data = db_prepare_record($db_table, $changes->to);
    $query = db_create_update_query($db_table, array_keys($update_data) );
    $update_data[] = $id;

    // dump($query, "query");
    // dump($update_data,"update_data");die(__FUNCTION__);



    $statement = db_prepare_query($db_table, $query);
    if ($statement) {
        if (DB_NOTICE_QUERY) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " SQL: '".$query."'.");
        $res = $statement->execute(array_values($update_data));
        $count = $statement->rowCount();
    }else{
        $res = false;
    };

    if ( ! $res){
        dosyslog(__FUNCTION__.": ERROR: " . get_callee() . " SQL ERROR:  [" . $db_table . "]: '".db_error($dbh)."'. Query: '".$query."'. Update data: '".json_encode_array($update_data)."'.");
        return array(false,"db_fail");
    }elseif( ! $count){
        dosyslog(__FUNCTION__.": ERROR: " . get_callee() . " No rows updated:  [" . $db_table . "]: '".db_error($dbh)."'. Query: '".$query."'. Update data: '".json_encode_array($update_data)."'.");
        return array(false,"db_no_update");
    }else{
        dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " Update db: '".$query."'");

        if (db_get_table($db_table) !== "history") {


            if ( !empty($_USER["id"]) ){
                $user_id = $_USER["id"];
            }else{
                $user_id = 0;
            };

            if (!db_add_history($db_table, $id, $user_id, "db_edit", $comment, $changes)){
                // ДОРАБОТАТЬ: реализовать откат операции UPDATE

                dosyslog(__FUNCTION__.": ERROR: " . get_callee() . " Can not add record to history table od db '".$db_table."'.");
                return array(false, "history_fail");
            };
        };

        if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
        return array(true,"success");

    };
}
function db_error($dbh){
    $err = $dbh->errorInfo();
    return $err[2];
}
function db_field_exists($db_table, $field_name){
  $table_schema = db_get_table_schema($db_table);
  if ($table_schema){
    $fields = array_keys(arr_index($table_schema, "name"));
  }else{
    $fields = array();
  }

  return in_array($field_name, $fields);
}
function db_find($db_table, $field, $value, $returnOptions=DB_RETURN_ID, $order_by="", $limit=""){
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");

	$dbh = db_set($db_table);

    $result = array();

    $table = db_get_table($db_table);

    $table_schema = db_get_table_schema($db_table);
    $field_data = null;
    foreach($table_schema as $v){
        if ($v["name"] == $field){
            $field_data = $v;
            break;
        };
    };
    if ( ! $field_data ){
        dosyslog(__FUNCTION__.": ERROR: Field '".$field."' does not exist in ".$db_table." schema. Check DB config.");
        return array();
    }

    $field_type = $field_data["type"];


    if($dbh) {

        switch ($field_type){
        case "list":
            $where_clause = $field." LIKE ".$dbh->quote("%".DB_LIST_DELIMITER.$value.DB_LIST_DELIMITER."%");
            break;
        default:
            if (is_array($value)){
                $where_clause = $field." IN (".implode(", ", array_map(function($v)use($dbh){
                    return $dbh->quote($v);
                }, $value) ) . ")";
                if (in_array(null,$value)){
                    $where_clause .= " OR ".$field." IS NULL";
                };
            }else{
                if (is_null($value)){
                    $where_clause = $field." IS NULL";
                }else{
                    $where_clause = $field."=".$dbh->quote($value);
                };
            };
        }

        if (db_field_exists($db_table, "deleted")){
          $where_clause .= ( ! ($returnOptions & DB_RETURN_DELETED) ? " AND (deleted IS NULL)" : "");
        };

        // ORDER BY
        $order_by_clause = "";
        if ($returnOptions & DB_RETURN_NEW_FIRST){
            if (empty($order_by)) $order_by = array();
            if (!empty($order_by) && !is_array($order_by)) $order_by = array($order_by);
            $order_by["created"] ="DESC";
        };
        if ( ! empty($order_by) ){
            if (!is_array($order_by)) $order_by = array($order_by);

            $order_by_clause = " ORDER BY ";
            $i = 0;
            foreach($order_by as $k=>$v){
                $order_by_clause .= ($i++>0 ? ", " : "") . $k . " " . (strtoupper($v) == "DESC" ? "DESC" : "ASC");
            };
        };

        // LIMIT
        $limit_clause = "";
        if ((int)$limit > 0){
            $limit_clause = " LIMIT ".(int) $limit;
        };
        if ( $returnOptions & DB_RETURN_ONE ){
            $limit_clause = " LIMIT 1";
        };

        if ($returnOptions & DB_RETURN_ID){
            $query = "SELECT id, " . $field . " FROM " . $table . " WHERE " . $where_clause . $order_by_clause . $limit_clause . ";";
        }elseif($returnOptions & DB_RETURN_ROW){
            $query = "SELECT *  FROM " . $table . " WHERE " . $where_clause . $order_by_clause . $limit_clause . ";";
        }else{
            die("Code: db-".__LINE__."-db_find");
        };

        if (DB_NOTICE_QUERY) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " SQL: '".$query."'.");
        $result = db_select($db_table, $query, $returnOptions);

    }else{
        dosyslog(__FUNCTION__.": ERROR: " . get_callee() . " DB is not set. Db_set() has to called before db_find().");
    };

    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");

    if ($returnOptions & DB_RETURN_ONE){
        if (isset($result[0])) return $result[0];
        else return null;
    }else{
        return $result;
    };
};
function db_get($db_table, $ids, $flags=0, $limit="", $offset = 0){

    if (! $ids) {
        dosyslog(__FUNCTION__.get_callee().": ERROR: Mandatory padameter 'ids' is empty.");
        return array();
    };

    $result = array();
    $dbh = db_set($db_table);

    $table_name = db_get_table($db_table);


    $get_all = false;
    $get_random = false;
    if ( is_array($ids) ){
        $tmp  = $ids;
    }elseif($ids == "all"){
        $tmp = array();
        $get_all = true;
    }elseif($ids == "random"){
        $tmp = array();
        $get_random = true;
        if (!$limit) $limit = 1;
    }else{
        $flags |= DB_RETURN_ONE;
        $tmp  = array($ids);
    };

    $ids = array();
    if ( ! $get_all && ! $get_random ){
        foreach($tmp as $k=>$id){
            if ( ! is_numeric($id)){
                dosyslog(__FUNCTION__.": ERROR: " . get_callee() . " Non-numeric id: '".serialize($id)."' while querying DB '" . $db_table . "'. Skipped.");
            }else{
                $ids[] = (int) $id;
            };
        };
        unset($tmp, $k, $id);
    };

    if (empty($ids) && ! $get_all && ! $get_random){
        dosyslog(__FUNCTION__. get_callee() .": FATAL ERROR:  Empty ids  while querying DB '" . $db_table . "'.");
        die("Code: db-".__LINE__."-".$db_table);
    }

    // Select
    if ($get_all || $get_random){
        $query = "SELECT * FROM " . $table_name;
    }elseif (count($ids) == 1){
        $query = "SELECT * FROM " . $table_name . " WHERE id = ?";
    }else{
        $query = "SELECT * FROM " . $table_name . " WHERE id IN (" . implode(", ", array_fill(0,count($ids), "?")) . ")";
    };


    // Where
    if ( ! ($flags & DB_RETURN_DELETED) && db_field_exists($db_table, "deleted")){
        $query .= ($get_all || $get_random ? " WHERE" : " AND") . " (deleted IS NULL)";
    };

    // Order by
    if ($flags & DB_RETURN_NEW_FIRST){
        $query .= " ORDER BY created DESC";
    }elseif ($get_random){
        $query .= " ORDER BY RANDOM()";

    }

    // Limit
    if ($flags & DB_RETURN_ONE){
        $query .= " LIMIT 1";
    }elseif ($limit){
        $query .= " LIMIT ". (int) $limit;
    };

    // Offset
    if ($limit && $offset){
        $query .= " OFFSET ". (int) $offset;
    };


    // Finish
    $query .=";";

    $statement = db_prepare_query($db_table, $query);

    if ($statement){
        if ($get_all || $get_random){
            $res = $statement->execute();
        }else{
            $res = $statement->execute($ids);
        };
    }else{
        $res = false;
    };


    if ($res){
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);
        if ( ! empty($result) ){
            if (count($ids) == 1){
                if (count($result) > 1){
                    dosyslog(__FUNCTION__.": ERROR: Found more than one record with id '".$ids[0]."' in '".$db_table."'. First taken.");
                };
                $result = array($result[0]);
            };

            foreach($result as $k=>$v){
                if ( ! ($flags & DB_DONT_PARSE) ){
                    $result[$k] = db_parse_result($db_table, $result[$k]);
                };
                if ( $flags & DB_PREPARE_VALUE ){
                    $result[$k] = db_prepare_record($db_table,$result[$k]);
                };
            };
            unset($k,$v);

            if ($flags & DB_RETURN_ID_INDEXED){
                $tmp = array();
                foreach($result as $k=>$v){
                    $tmp[$v["id"]] = $v;
                };
                $result = $tmp;
                unset($tmp, $k, $v);
            };

            if (DB_NOTICE_QUERY) dosyslog(__FUNCTION__.": DEBUG: " . get_callee() . ": Fetched ".count($result)." records. Query: '".$statement->queryString ."', parameters: ".json_encode($ids).".");

            if ($flags & DB_RETURN_ONE){
                $result = $result[key($result)];
            };

        };

    }else{
        dosyslog(__FUNCTION__.": ERROR: " . get_callee() . " SQL ERROR:  [" . $db_table . "]: '".db_error($dbh).". Query: ".$query);
    };
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");

    return $result;
};
function db_get_count($db_table, $where_clause = ""){
    $dbh = db_set($db_table);

    $table_name = db_get_table($db_table);

    $query = "SELECT count(*) as c FROM " . $table_name;
    if ($where_clause){
        $query .= " WHERE " . $where_clause;
    }else{
        $query .= " WHERE (deleted = '' OR deleted IS NULL)";
    }
    $query .= ";";

    $res = db_select($db_table, $query);

    if (isset($res[0]["c"])){
        return $res[0]["c"];
    }else{
        return 0;
    };

}
function db_get_max($db_table, $key){
    $dbh = db_set($db_table);

    $table_name = db_get_table($db_table);

    $query = "SELECT max(".$key.") as m FROM " . $table_name;
    $query .= " WHERE (deleted IS NULL)";
    $query .= ";";

    $res = db_select($db_table, $query);

    if (isset($res[0]["m"])){
        return $res[0]["m"];
    }else{
        return false;
    };

}
function db_get_min($db_table, $key){
    $dbh = db_set($db_table);

    $table_name = db_get_table($db_table);

    $query = "SELECT min(" .$key . ") as m FROM " . $table_name;
    $query .= " WHERE (deleted IS NULL)";
    $query .= ";";

    $res = db_select($db_table, $query);

    if (isset($res[0]["m"])){
        return $res[0]["m"];
    }else{
        return false;
    };

}
function db_get_field_type($db_table, $field_name){
    $schema = db_get_table_schema($db_table);

    foreach($schema as $field){
        if ($field["name"] == $field_name){

            if ( in_array($field["type"], array("password"))) {
                dosyslog(__FUNCTION__.get_callee().": DEBUG: Field '".$field_name."' has type '".$field["type"]."'.");
            };

            return $field["type"];
        };
    };

    dosyslog(__FUNCTION__.get_callee().": ERROR: Field '".$field_name."' not found in db table '".$db_table."'.");
    if (DEV_MODE) die("Code: db-".__LINE__."-".$field_name."-type");
    return "";
}
function db_get_list($db_table, array $fields = array("id"), $limit=""){
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    // ДОРАБОТАТЬ: проверить существования поля $field в БД.

    $dbh = db_set($db_table);

    $result = array();

    $table_name = db_get_table($db_table);

    if($dbh) {
        $query = "SELECT DISTINCT ".implode(", ",$fields)." FROM ".$table_name.($limit?" LIMIT ".((int)$limit):"").";";

        if (DB_NOTICE_QUERY) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " SQL: '".$query."'.");
        $res = $dbh->query($query);
        if ($res){
            $tmp = $res->fetchAll(PDO::FETCH_ASSOC);
            if (count($fields == 1)){
                foreach($tmp as $k=>$v){
                    if ( ! empty($v["id"]) ){
                        $result[ $v["id"] ] = $v;
                    }else{
                        $result[] = $v[ $fields[0] ];
                    };
                };
            };
        }else{
            dosyslog(__FUNCTION__.": ERROR: " . get_callee() . " SQL ERROR:  [" . $db_table . "]: '".db_error($dbh).". Query: ".$query);
        };
    }else{
        dosyslog(__FUNCTION__.": ERROR: " . get_callee() . " DB is not set. Db_set() has to called before db_get_list().");
    };
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    return $result;
}
function db_get_table_columns($db_table){

    $dbh = db_set($db_table);
    $table = db_get_table($db_table);


    $res = $dbh->query("PRAGMA table_info('".$table."');")->fetchAll(PDO::FETCH_ASSOC);

    // $res record keys for sqlite: cid	name	type	notnull	dflt_value	pk

    if ($res) $res = arr_index($res, "name");

    return $res;

}
function db_get_table_schema($db_table){

    global $CFG;
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");

    $db = false;
    $table = false;

    $db_name = db_get_name($db_table);
    $db_table = db_get_table($db_table);

    $db_files = get_db_files();
    $isFound = false;
    foreach($db_files as $db_file){
        $xml = xml_load_file($db_file);
        if ($xml){
            foreach($xml->db as $xmldb){
                if ($db_name == (string) $xmldb["name"]){
                    $db = $xmldb;
                    $isFound = true;
                    break;
                };
            }; //foreach xml
        };
    };

    if (!$isFound){
        dosyslog(__FUNCTION__.": ERROR: " . get_callee() . " Db '".$db_name."' is not found in any db XML files.");
        return false;
    };

    if (!empty($db->table)){
        foreach($db->table as $xmltable){
            if ($db_table == $xmltable["name"]){
                $table = $xmltable;
                $table = xml_to_array($table);
                break;
            };
        };
    };
    if (empty($table)){
        dosyslog(__FUNCTION__.": WARNING: " . get_callee() . " Can not find table '".$db_table."' definition in db '".$db_name."' XML.");
        return false;
    };

     //
    $tmp = $table["field"];
    $table = array();
    foreach($tmp as $v){
        $table[] = $v["@attributes"];
    };
    unset($v, $tmp);
    //

    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    return $table;
};
function db_get_tables($db_name = ""){

    $dbs = db_get_tables_list_from_xml($db_name);

    foreach($dbs as $db){
        if (!empty($db->table)){
            foreach($db->table as $xmltable){

                $cur_db_name = (string)$db["name"];

                if (empty($tables_list[ $cur_db_name ])) $tables_list[ $cur_db_name ] = array();
                if (!in_array((string) $xmltable["name"], $tables_list[ $cur_db_name ])){
                    $tables_list[ $cur_db_name ][] = (string) $xmltable["name"];
                };
            };
        };
    };


    if (!isset($tables_list[$db_name])){
        // dump($db_name,"db_name");
        // dump(get_callee(), "stack");
        // dump($tables_list,"tables_list");
        // die();
    }

    if ($db_name) return $tables_list[$db_name];
    else return $tables_list;

}
function db_get_tables_list($db_name = "", $skipHistoryTable = true){
    $db_tables_info = db_get_tables($db_name);

    $full_table_name = function($db_name, $tables) use ($skipHistoryTable){
            return array_map(function($table) use ($db_name){
                return $db_name . "." . $table;
        }, array_filter($tables, function($table) use ($skipHistoryTable){
            return !($skipHistoryTable && ($table == "history"));
            }));
    };

    if ($db_name){
        $db_tables = $full_table_name($db_name, $db_tables_info);
    }else{

        $db_tables = array();
        foreach($db_tables_info as $db_name => $tables){
            $db_tables = array_merge($db_tables, $full_table_name($db_name, $tables));
        };

    }

    return $db_tables;
}
function db_get_tables_list_from_xml($db_name=""){
    global $CFG;

    $dbs = array();
    $table = false;
    $tables_list = array();

    $db_name = db_get_name($db_name);

    $db_files = get_db_files();

    $isFound=false;
    foreach ($db_files as $k=>$xmlfile){
        $xml = xml_load_file($xmlfile);
        if ($xml){

            foreach($xml->db as $xmldb){
                if ($db_name){
                    if ($db_name == (string) $xmldb["name"]){
                        $dbs[] = $xmldb;
                        $isFound = true;
                        break;
                    };
                }else{  // вернуть все таблицы
                    $dbs[] = $xmldb;
                }
            }; //foreach xml

        };
        if (!empty($db)) break;
    };
    if ($db_name && !$isFound){
        dosyslog(__FUNCTION__.": ERROR: " . get_callee() . " Db '".$db_name."' is not found in any db XML files.");
        return array();
    };

    return $dbs;

};
function db_insert($db_table, ChangesSet $data){
    $result = false;

    $dbh = db_set($db_table);

    $timestamp = time();


    $schema = db_get_table_schema($db_table);
    $fields = array();
    foreach($schema as $f){
        $fields[$f["name"]] = $f;
    };
    unset($schema, $f);

    // created
    if ( isset($fields["created"]) && ! isset($data->to["created"]) ){
        $data->to["created"] = $timestamp;
    };


    $keys = array_keys($data->to);
    $keys = array_filter($keys, function($k) use ($fields, $db_table){
        if ( ! isset($fields[$k]) ){
            dosyslog(__FUNCTION__.get_callee().": FATAL ERROR: Field '".$k."' does not exist in table '".$db_table."'.");
            die("Code: db-".__LINE__);
        };
        return ( isset($fields[$k]) && ($fields[$k]["type"] != "autoincrement") && ($k != "modified") && ($k != "deleted") );
    });


    $insert_data = array();
    $record = db_prepare_record($db_table, $data->to);
    $keys = array_keys($record);  // db_prepare_record() may delete 'pass' field if it's empty

    $query = db_create_insert_query($db_table, $keys);
    $statement = db_prepare_query($db_table, $query);


    foreach($keys as $k){
        $insert_data[] = $record[$k];
    };

    if ( $statement ){
        $res = $statement->execute($insert_data);
    }else{
        $res = false;
    };

    if ($res){
        $result = $dbh->lastInsertId();

        if (DB_NOTICE_QUERY) dosyslog(__FUNCTION__. get_callee() .": DEBUG: ".($res ? "Inserted " . count($data) . " records." : "Insert failed.") . " Query: '".$query .", parameters: '" . json_encode_array($insert_data) ."'. Result: ".$result);

    }else{
        dosyslog(__FUNCTION__.": ERROR: " . get_callee() . " SQL ERROR:  [" . $db_table . "]: '".db_error($dbh)."'. Query: '".$query.", parameters: '" . json_encode_array($insert_data) ."'.");
        $result = false;
    };


    return $result;
};
function db_is_exists($db_table){

    $dbh = db_set($db_table);
    $table = db_get_table($db_table);



    $res = db_select($db_table, "SELECT count(*) as c FROM sqlite_master WHERE type='table' AND name='".$table."'");

    if (isset($res[0]["c"])){
        return (boolean) $res[0]["c"];
    }else{
        return false;
    };
}
function db_last_modified($db_table){

	$table_name = db_get_table($db_table);

	// Max created
	$tmp = db_select($db_table, "SELECT max(created) as m FROM ".$table_name, DB_RETURN_ONE);
	$max_created = ! empty($tmp[0]["m"]) ? $tmp[0]["m"] : 0;

	// Max modified
	$tmp = db_select($db_table, "SELECT max(modified) as m FROM ".$table_name, DB_RETURN_ONE);
	$max_modified = ! empty($tmp[0]["m"]) ? $tmp[0]["m"] : 0;


	$last_modified = max($max_created, $max_modified);

	return $last_modified ? $last_modified : null;
}
function db_search_substr($db_table, $field, $search_query, $limit=100, $flags = 18){
    static $lower_custom_function_registered = array();

    $dbh = db_set($db_table);

    // SQLITE3 specific code
    $db_name = db_get_name($db_table);
    if ( ! isset($lower_custom_function_registered[$db_name]))  $lower_custom_function_registered[$db_name] = false;

    if ( ! $lower_custom_function_registered[$db_name] ) {
        $lower_custom_function_registered[$db_name] = db_sqlite_register_function($dbh, "lower");
    };
    unset($db_name);
    //


    $table_name = db_get_table($db_table);

    $sql_count = "SELECT count(*) FROM ".$table_name." WHERE lower(" . $field . ") LIKE lower(".$dbh->quote("%".$search_query."%").");";
    $stmt_count = $dbh->query($sql_count);
    if (DB_NOTICE_QUERY) dosyslog(__FUNCTION__. get_callee() .": DEBUG: Query: '" . $sql_count . ".");
    if (!$stmt_count){
        dosyslog(__FUNCTION__.get_callee().": SQL ERROR: ".db_error($dbh).".");
        return array(array(),0);
    };

    list($count) = $stmt_count->fetchAll(PDO::FETCH_COLUMN, 0);


    $sql = "SELECT * FROM ".$table_name." WHERE lower(" . $field . ") LIKE lower(?) LIMIT ?;";
    $stmt = $dbh->prepare($sql);
    if (!$stmt){
        dosyslog(__FUNCTION__.get_callee().": SQL ERROR: ".db_error($dbh).".");
        return array(array(),0);
    };

    $params = array("%".$search_query."%", $limit);
    $stmt->execute($params);
    if (DB_NOTICE_QUERY) dosyslog(__FUNCTION__. get_callee() .": DEBUG: Query: '" . $sql .", parameters: '" . json_encode_array($params) ."'.");

    $tmp = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $res = array();

    foreach($tmp as $rec){
        if ($flags & DB_RETURN_ROW){
            if ($flags & DB_RETURN_ID_INDEXED){
                if ($flags & DB_DONT_PARSE){
                    $res[ $rec["id"] ] = $rec;
                }else{
                    $res[ $rec["id"] ] = db_parse_result($db_table, $rec);
                }
            }else{
                $res[] = $rec;
            }
        }else{
            $res[] = $rec["id"];
        };
    };

    return array($res, $count);
}
function db_set($db_table){
    global $_DB;

    if ( ! defined("DATA_DIR") ) {
        dosyslog(__FUNCTION__.": FATAL ERROR: " . get_callee() . " DATA_DIR is not defined.");
        die("platform_db:db-set-1");
    };

    if ( ! is_dir(DATA_DIR) ) mkdir(DATA_DIR,0777,true);
    if ( ! is_dir(DATA_DIR) ) {
        dosyslog(__FUNCTION__.": FATAL ERROR: " . get_callee() . " DATA_DIR (".DATA_DIR.") is not exist and can not be created.");
        die("platform_db:db-set-2");
    };

	$db_name = db_get_name($db_table);
    $table_name = db_get_table($db_table);

    if ( empty($_DB[$db_name]) ) $_DB[$db_name] = array("handler"=>null, "tables"=>array());

    if ( empty($_DB[$db_name]["handler"]) ) {
        try{
            $dbh = new PDO("sqlite:" . DATA_DIR . $db_name . ".db");
            $_DB[$db_name]["handler"] = $dbh;
        }catch(PDOException $e){
            dosyslog(__FUNCTION__.": FATAL ERROR: " . get_callee() . " DB ".$db_name." can not be opened/created in directory '".DATA_DIR."'. PDO message:".$e->getMessage() );
            die("platform_db:db-set-3");
        };

    }else{

        $dbh = $_DB[$db_name]["handler"];

    };

    if ( ! in_array($table_name, $_DB[$db_name]["tables"]) ){
        // Проверка существования таблицы
        $query_table_check = "SELECT count(*) FROM sqlite_master WHERE type='table' AND name=".$dbh->quote($table_name).";";
        if (DB_NOTICE_QUERY) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " SQL: '".$query_table_check."'.");
        //

        if ( ! (int) ($dbh->query($query_table_check)->fetchColumn()) ){  // создаем таблицу, если она не сущестует
            $query = db_create_table_query($db_table);
            // dump($query,"q");
            dosyslog(__FUNCTION__.": INFO: " . get_callee() . " Creating table ".$db_table.".");

            if (DB_NOTICE_QUERY) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " SQL: '".$query."'.");
            $res = $dbh->query($query);
            if (!$res) {
                dosyslog(__FUNCTION__.": ERROR: " . get_callee() . " SQL ERROR:  [" . $db_table . "]: '".db_error($dbh).". Query: ".$query);
            };
        };

        if ( ! (int) $dbh->query($query_table_check)->fetchColumn() ){
            dosyslog(__FUNCTION__.": FATAL ERROR: " . get_callee() . " Can not create table '" . $db_table ."'.");
            die("platform_db:db-set-4");
        }else{
            $_DB[$db_name]["tables"][] = $table_name;
        };
    };

	return $dbh;
};
function db_select($db_table, $select_query, $flags=0){

    $result = array();
    $dbh = db_set($db_table);

    $select_query = trim($select_query);


    if ( (strtoupper(substr($select_query,0,strlen("SELECT "))) !== "SELECT ") &&
         (strtoupper(substr($select_query,0,strlen("WITH RECURSIVE"))) !== "WITH RECURSIVE")
      ){
        dosyslog(__FUNCTION__.": FATAL ERROR: " . get_callee() . " Only SELECT query is allowed. Query: '".htmlspecialchars($select_query)."'. IP:".$_SERVER["REMOTE_ADDR"]);
        die();
    };


    $res = $dbh->query($select_query);

    if ($res){
        while ( ($row = $res->fetch(PDO::FETCH_ASSOC) ) !== false) {
            if ( $flags & DB_RETURN_ID ){
                $result[] = $row["id"];
            }elseif ( $flags & DB_PREPARE_VALUE ){
                $result[] = db_prepare_record(db_parse_result($db_table, $row));
            }elseif ( $flags & DB_DONT_PARSE ){
                $result[] = $row;
            }else{
                $result[] = db_parse_result($db_table, $row);
            };
        };

        if ($flags & DB_RETURN_ID_INDEXED){
            $tmp = array();
            foreach($result as $k=>$v){
                $tmp[$v["id"]] = $v;
            };
            $result = $tmp;
            unset($tmp, $k, $v);
        };

        if (DB_NOTICE_QUERY) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " SQL: '".$select_query."'. Fetched: ".count($result)." rows.");

    }else{
        dosyslog(__FUNCTION__.": ERROR: " . get_callee() . " SQL ERROR:  '".db_error($dbh)."'. Query: '".$select_query."'.");
    };
    return $result;
};
function db_sqlite_register_function($dbh, $func_name){

    switch($func_name){
    case "lower":
        if (method_exists($dbh, "sqliteCreateFunction")){ // this is experimental method since PHP 5.1 (http://php.net/manual/ru/pdo.sqlitecreatefunction.php)
            $dbh->sqliteCreateFunction("lower", function($value){
                return mb_convert_case($value, MB_CASE_LOWER, "UTF-8");
            });
            return true;
        }else{
            dosyslog(__FUNCTION__.get_callee().": ERROR: It seems that PDO has not method sqliteCreateFunction().");
            return false;
        }
        break;

    }

    return false;
}
function db_parse_result($db_table, $result){

    // Десериализация данных, полученных из БД
    $schema = db_get_table_schema($db_table);
    $fields = array();
    if (is_array($schema)){
        foreach($schema as $field){
            $fields[ $field["name"] ] = $field;
        };
    };
    unset($schema, $field);

    foreach($result as $k=>$v){
        if ( isset($fields[$k]) ){
            $result[ $k ] = db_parse_value($v, $fields[$k]["type"]);
        };
    };

    return $result;
}
function db_parse_value($value, $field_type){

    switch($field_type){
    case "money":
        if (preg_match("/\.\d\d$/", $value)){ // for backward compatibility
            // do nothing
        }else{
            $value = (string) bcadd($value/100, 0, DB_MONEY_PRECISION);
        }
        break;
    case "phone":
        if (!preg_match("/\d{10}$/", $value)){
            // do nothing
        }else{
            $value = db_prepare_value($value, "phone");
        }
        break;
    case "list":
        if (isset($value) ){
            if (strpos($value, DB_LIST_DELIMITER) !== false){
                $value = explode(DB_LIST_DELIMITER, trim($value, DB_LIST_DELIMITER));

                if (
                        ! empty($value) &&
                        ($value[0] === "") &&
                        ($value[ count($value)-1 ] === "")
                   ){  // убираем первый и последний пустые элементы, если есть (они добавляются с версии 1.1.0 для удобства поиска, см. db_prepare_value())
                    $value = array_slice($value, 1,-1);
                };

            }else{
                $value = array($value);
            };
        }else{
            $value = array();
        };
        break;
    case "json":
        if ( ! empty($value) && ($value != "[]") ){
            $stored = $value;
            $value = json_decode_array( $value);
            if ($value == false){
                dosyslog(__FUNCTION__.": ERROR: JSON parse error: '".$stored."'.");
                $value = array();
            };
            unset($stored);
        }else{
            $value = array();
        };
        break;
    case "string":
        $value = htmlspecialchars_decode($value, ENT_QUOTES);
        break;
    case "boolean":
        $value = (boolean) $value;
        break;
    }; // switch

    return $value;

}
function db_prepare_query($db_table, $query){
    // dosyslog(__FUNCTION__.get_callee() . ": DEBUG: Preparing query '".$query."' for '".$db_table."'.");
    $dbh = db_set($db_table);
    try{
        $stmt = $dbh->prepare($query);
    }catch(PDOException $e){
        dosyslog(__FUNCTION__.": FATAL ERROR: " . get_callee() . " Could not prepare statement for ".$db_table.". PDO message:".$e->getMessage() );
        die("Code: db-".__LINE__);
    };
    return $stmt;
}
function db_prepare_record($db_table, $record){

    // Сериализовать поля, требующие этого перед записью в БД
    $schema = db_get_table_schema($db_table);
    foreach($schema as $field){
        if (isset($record[ $field["name"] ])){
            if ( ($field["type"] == "password") && ! $record[ $field["name"] ] ){ // if new password is empty it is supposed that password should not be changed
                unset($record[ $field["name"] ]);
            }else{
                $record[ $field["name"] ] = db_prepare_value($record[ $field["name"] ], $field["type"]);
            };
        };
    };

    return $record;
}
function db_prepare_value($value, $field_type){

    $res = $value;

    switch($field_type){
        case "list":
            if (empty($value)){
                $res = null;
                break;
            };
            if ($value == "null"){
                $res = null;
                break;
            };

            if ( ! is_array($value)){
                $res = db_parse_value($value, $field_type);

                if ( ! is_array($res)){
                    $res = (array) $value;
                };
                if (empty($res)){
                    $res = null;
                    break;
                };
            };

            array_unshift($res,"");// добавим в начало и конец массива пустые строки, чтобы можно было искать отдельные значения массива с помощью SQL выражения LIKE "%||value||%"
            array_push($res,"");
            $res = implode(DB_LIST_DELIMITER, $res);
            break;
        case "json":
            if (empty($value)){
                $res = null;
                break;
            };
            if (is_array($value)){
                $res = json_encode_array($value);
            };
            break;
        case "number":
            if ($value === "") $res = null;
            elseif ( ! is_null($value) ){
                $res = (int) $value;
            };
            break;
        case "double":
            if ($value === "") $res = null;
            elseif ( ! is_null($value) ){
                $res = (double) $value;
            };
            break;
        case "money":
            if ($value === "") $res = 0;
            elseif ( ! is_null($value) ){
                $res = (int) ($value*100);
            };
            break;
        case "phone":
            if ($value === "") $res = null;
            elseif ( ! is_null($value) ){
                $res = substr(glog_clear_phone($value), -10);
            };
            break;
        case "date":
            if ($value === "") $res = null;
            elseif ( ! is_null($value) ){
                $res = glog_isodate($value);
            };
            break;
        case "timestamp":
        case "boolean": // prepare as timestamp
            if ( in_array( $value, array("1", "yes", "y", "Y", "on", "true") ) ){
                $res = time();
            }elseif(in_array( $value, array("", "0", "no", "n", "N", "off", "false") )){
                $res = null;
            }elseif($value == glog_isodate($value)){ // Check if value is date, convert to timestamp
                $res = strtotime($value);
            }else{   // Check if value is valid timestamp, if not (i.e it's string "yes", "on", ... ) generate current timestamp

                list($month, $day, $year) = explode("/", date("m/d/Y", $value));
                if ( ! checkdate($month, $day, $year) ){
                    dosyslog(__FUNCTION__.get_callee().": ERROR: Invalid timestamp: '".$value."'.");
                    $res = time();
                };
            };
            break;
        case "password":
            $res = passwords_hash($value);
            if ( ! empty($value) && empty($res) ){
                dosyslog(__FUNCTION__.": ".get_callee().": ERROR: Empty hash for no-empty password");
            };
            break;
        case "string":
            if ($value){
                $res = htmlspecialchars($value, ENT_QUOTES);
            }else{
                $res = null;
            }
            break;
        default:
            $res = $value;
    };

    return $res;
}
function db_quote($value){
    $dbh = new PDO("sqlite::memory:");
    return $dbh->quote($value);
}
