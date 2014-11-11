<?php

if (!defined("TEST_MODE")) define ("TEST_MODE", false);
define("DB_NOTICE_QUERY",true); // писать запросы в лог
define("DB_LIST_DELIMITER", "||"); // разделитель элементов в полях типа list
define("DB_DONT_EXPLODE_LISTS", 1); // флаг для db_get(), что не надо десериализовать поля типа list, т.е. вернуть строку, а не массив.

$_DB = array();

/* ***********************************************************
**  DATABASE FUNCTIONS
**
** ******************************************************** */
function db_add($db_table, $data, $comment=""){
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    global $_USER;
    
    // ДОРАБОТАТЬ: добавить проверку существования полей в таблице и обработку ошибок
    
    $data["created"] = time();
    
    $added_id = db_insert($db_table, $data);
       
    if ( $added_id ){
       
        if (db_get_table($db_table) !== "history") {
            $changes=array();
            foreach($data as $k=>$v){
                $changes[$k]["from"] = "";
                $changes[$k]["to"] = $v;
            };
            
            if ( ! db_add_history($db_table, $added_id, $_USER["profile"]["id"], "db_add", $comment, $changes)){
                // ДОРАБОТАТЬ: реализовать откат операции INSERT
                
                dosyslog(__FUNCTION__.": ERROR: " . get_callee() . " " . get_callee() . " Can not add record to history table of db '".$db_table."'.");
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
function db_add_history($db_table, $objectId, $subjectId, $action, $comment, $changes){
    
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    $res = true;
    
    $comment .= " IP:" . @$_SERVER["REMOTE_ADDR"];
    
    $db_name =  db_get_name($db_table);
    $table_name = db_get_table($db_table);

    db_set($db_name . ".history");
    

    if ($db_name == $table_name){
        $db = $db_name;
    }else{
        $db = $db_table;
    };
    
    $record = array(
        "db"            =>  $db,
        "objectId"      =>  (int) $objectId,
        "subjectId"     =>  (int) $subjectId,
        "action"        =>  $action,
        "action_uid"    =>  md5(time()),
        "timestamp"     =>  time()
    );
    
    $changes["_comment"] = array("to"=>$comment);
    
    foreach($changes as $k=>$v){
        $record["changes_what"] = $k;
        $record["changes_from"] = isset($v["from"]) ? (string) $v["from"] : "";
        $record["changes_to"] = isset($v["to"]) ? (string) $v["to"] : "";
        if (!db_add($db_name . ".history", $record)){
            dosyslog(__FUNCTION__.": ERROR: " . get_callee() . " Can not add history record into '".$db_name."' db.");
            $res = false;
        };
    };

    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    return $res;
};
function db_check_schema($db_table){ // проверяет схему таблицы $db_table базыданных $db_name на соответствие файлу db.xml

	if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
	
    $db_name = db_get_name($db_table);
    $db_table = db_get_table($db_table);
    
	$tables = db_get_tables_list_from_xml($db_name);
	$columns = array(); // поля в текущей БД
	$fields_to_be = array(); // поля, описанные в XML
	$fields_to_add = array(); // поля, которые есть в XML, но нет в текущей БД
	$fields_to_del = array(); // поля, которые должны быть удалены (и за "бэкаплены" в поле extra)
	
	foreach($tables as $table){
		$schema = db_get_table_schema($db_name . ".". $table);
		if (empty($schema)){
			echo "<p class='alert alert-warning'>Таблица ".$db_name.".".$table." не определена в XML.<p>";
			continue;
		};
        $dbh = db_set($db_name . "." . $table);
		$tmp = $dbh->query("SELECT * FROM ".$table." LIMIT 1")->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($tmp[0])){
            $columns[$table] = array_keys($tmp[0]);
        }else{ // таблица не существует в БД
            echo "Таблица ".$table." отсутствует в БД ".$db_name.". Она будет создана движком при первом реальном использовании. <br>";
            continue;                 // на надо создавать таблицу в ходе миграции, она будет создана движком при первом реальном использовании.
        };
        
		// dump($columns,$table);
	
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
                        <?foreach(array_merge($columns[$table], $fields_to_add[$table]) as $k=>$v):?>
                            
                            <?if (in_array($v, $fields_to_add[$table]) ):?>
                                <tr><th><?=$v;?></th> <td><i class="icon icon-plus text-success"></i></td> </tr>
                            <?elseif(in_array($v, $fields_to_del[$table]) ):?>
                                <tr><th><?=$v;?></th> <td><i class="icon icon-remove text-danger"></i></td> </tr>
                            <?endif;?>
                        <?endforeach;?>
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
					$query[] = db_create_table_query($db_name, $table)."\n";
					$query[] = "INSERT INTO ".$table." SELECT ".implode(", ",$fields_to_be[$table])." FROM ".$temp_table.";";
					echo "<p>table <b>".$table."</b>:</p>";
				};
				
				if ($query){
					echo "<p>Выполненные запросы:</p>";
					echo "<ol>";
					$dbh = db_set($db_name . "." . $table);
					foreach($query as $q){
                        set_time_limit(300);
						echo "<li>".$q;
						if ($q=="backup"){
							$qb = "SELECT id, ".implode(", ",$fields_to_del[$table]).", extra FROM ".$temp_table.";";
							echo "<p>$qb</p>";
                            if (DB_NOTICE_QUERY) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " SQL: '".$qb."'.");
							$res_b = $dbh->query($qb);
							if ($res_b){
								echo "<ol>";
								while ( ($row = $res_b->fetch(PDO::FETCH_ASSOC)) !== false){
									if (!empty($row["extra"])){
										$extra_decoded = json_decode($row["extra"],true);
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
									$extra_encoded = json_encode($extra);
									
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
								dosyslog(__FUNCTION__.": FATAL ERROR: " . get_callee() . " Can not backup data while migrate DB schema. Query failed: '$qb'.");
								die("FATAL ERROR: Can not backup data while migrate DB schema.");
							};
						}else{
                            if (DB_NOTICE_QUERY) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " SQL: '".$q."'.");
							$res[$table][$q] = $dbh->query($q);
							if(!$res[$table][$q]){
								dosyslog(__FUNCTION__.": FATAL ERROR: " . get_callee() . " Can not backup data while migrate DB schema. Query failed: '$q'.");
								die("FATAL ERROR: Can not backup data while migrate DB schema.");
							};
						};
						echo "</li>";
					};
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
function db_delete($db_table, $id, $comment=""){
    global $_USER;
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    // ДОРАБОТАТЬ: добавить проверку существования полей в таблице и обработку ошибок
    // или ДОРАБОТАТЬ: переписать функцию с использование db_edit.
    
    //dump($comment,"comment");
    
	$dbh = db_set($db_table);
	
    $object = db_get($db_table, $id);
    if (empty($object)) {
        dosyslog(__FUNCTION__.": Attempt to delete object which is absent in DB '".$db_table."'. ID='".$id."'.");
        return array(false, "wrong_id");
    };
    
    // Check if object can be deleted - field 'isDeleted' is in table schema.
    
    if (!array_key_exists("isDeleted", $object)){
        dosyslog(__FUNCTION__.": Attempt to delete object from DB '".$db_table."' which does not support delete operation. ID='".$id."'.");
        return array(false, "delete_not_supported");
    };
    
    // Check if object is already deleted - field 'isDeleted' is set to some value.
    if (!empty($object["isDeleted"])){
        dosyslog(__FUNCTION__.": Attempt to delete object which is already deleted ('".date("Y-m-d H:i:s",$object["isDeleted"])."') from DB '".$db_table."'. ID='".$id."'.");
        return array(true, "already_deleted");
    };
    
    // Create query.
         
    $query = "UPDATE ".$db_table." SET isDeleted=".time()." WHERE id=".$id.";";
    
    if (DB_NOTICE_QUERY) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " SQL: '".$query."'.");
    $res = $dbh->exec($query);
       
    if (!$res){
        dosyslog(__FUNCTION__.": ERROR: " . get_callee() . " SQL ERROR:  [" . $db_table . "]: '".db_error($dbh)."'. Query: '".$query."'.");
        if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
        return array(false,"db_fail");
    }else{  
        dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " Update db (delete): '".$query."'");
        
        if (!db_add_history($db_table, $id, $_USER["profile"]["id"], "db_delete", $comment, array())){
            // ДОРАБОТАТЬ: реализовать откат операции UPDATE
            
            dosyslog(__FUNCTION__.": ERROR: " . get_callee() . " Can not add record to history table od db '".$db_table."'.");
            if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
            return array(false, "history_fail");
        };

        
        if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
        return array(true,"success");
        
    };
}
function db_edit($db_table, $id, $changes, $comment=""){
    
    global $_USER;
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    // ДОРАБОТАТЬ: добавить проверку существования полей в таблице и обработку ошибок
    
    //dump($comment,"comment");
    $dbh = db_set($db_table);
    $object = db_get($db_table, $id, DB_DONT_EXPLODE_LISTS);
    if (empty($object)) {
        dosyslog(__FUNCTION__.": Attempt to edit object which is absent in DB '".$db_table."'. ID='".$id."'.");
        return array(false, "wrong_id");
    };
    
    
    // Check that the changes are really change something.
    foreach ($changes as $what=>$v){
        if ($changes[$what]["from"] == $changes[$what]["to"]){
            unset($changes[$what]);
        };
    };
    unset($what, $v);
    
    if (empty($changes)){
        dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " No changes.");
        return array(true, "no_changes");
    };
    
    // Check if object is in state it supposed to be in.
    $conflicted = array(); // список полей, у которых состояние from не совпадает с текущим состоянием в БД.
    $not_existed = array(); // поля, которые отсутствуют у объекта, взятого из БД.
    foreach ($changes as $what=>$v){
        if ( ! array_key_exists($what, $object) ){
            $not_existed[] = $what;
            continue;
        };
        
        if ( ($what == "pass") && ($changes[$what]["from"] == "") ) { // при смене пароля оригинальный пароль (или хэш) на сервер от клиента не приходит, только новый.
            continue;
        }
        
        if ($changes[$what]["from"] != $object[$what]){ 
            $conflicted[] = $what . "(from: '".$changes[$what]["from"]."', in db: '".$object[$what]."')";
        };
    };
    unset($what, $v);
   
    if ( ! empty($not_existed) ){
        dosyslog(__FUNCTION__.": ERROR: " . get_callee() . " Theese fields are not exist in [".$db_table."]: ". implode(", ", $not_existed).".");
    };
    if ( ! empty($conflicted) ){
        dosyslog(__FUNCTION__.": WARNING " . get_callee() . " Changes conflict: object state changed during editing time: ". implode(",", $conflicted) . ".");
        return array(false,"changes_conflict");
    };
    
    
    // Create query.
         
    $query = "UPDATE ".$db_table." SET ";
    $tmp = array();
    foreach($changes as $k=>$v){
        if ($v["to"] === NULL){
            $tmp[] = $k."= NULL";
        }else{
            $tmp[] = $k."=".$dbh->quote($v["to"])."";
        }
    };
    $tmp[] = "modified = '" . time(). "'";
    $query .= implode(", ",$tmp);
    
    $query .= " WHERE id=".$id.";";
      
    if (DB_NOTICE_QUERY) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " SQL: '".$query."'.");
    $res = $dbh->exec($query);
       
    if (!$res){
        dosyslog(__FUNCTION__.": ERROR: " . get_callee() . " SQL ERROR:  [" . $db_table . "]: '".db_error($dbh)."'. Query: '".$query."'.");
        return array(false,"db_fail");
    }else{  
        dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " Update db: '".$query."'");
        
        if (db_get_table($db_table) !== "history") {
            
            if ( $_USER["isUser"] ){
                if ( !empty($_USER["profile"]["id"]) ){
                    $user_id = $_USER["profile"]["id"];
                }else{
                    dosyslog(__FUNCTION__.": ERROR: " . get_callee() . " [" . $db_table . "]: user id is not set. Query: '".$query."'.");
                    die("Code: db-" . __LINE__);
                }
            }elseif($_USER["isGuest"]){
                $user_id = 0;
            }else{
              dosyslog(__FUNCTION__.": ERROR: " . get_callee() . " [" . $db_table . "]: unkkown user. Query: '".$query."'.");
              die("Code: db-" . __LINE__);
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
function db_find($db_table, $field, $keyOrValue, $value=false, $returnDeleted=false){
    
    global $S;
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    $key = false;
    if(false === $value){
        $value = $keyOrValue;
    }else{
        $key = $keyOrValue;
    };

	$dbh = db_set($db_table);
    
    $result = array();
    
    if($key) { // ДОРАБОТАТЬ: добавить поддержку поиска по паре ключ:значение в полях типа json.
        dosyslog(__FUNCTION__.": FATAL ERROR: " . get_callee() . " Search in JSON is not implemented yet.");
        die();
    };
    
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
            $where_clause = $field." LIKE ".$dbh->quote("%".$value."%");
            break;
        default:
            $where_clause = $field."=".$dbh->quote($value);
        }
        $where_clause .= ( ! $returnDeleted ? " AND (isDeleted IS NULL OR isDeleted = '')" : "");
        
    
        $query = "SELECT id, " . $field . " FROM " . $table . " WHERE " . $where_clause . ";";
        // d($query);
        
        if (DB_NOTICE_QUERY) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " SQL: '".$query."'.");
        $res = $dbh->query($query);
        if ($res){
            $tmp = $res->fetchAll(PDO::FETCH_ASSOC); //  ДОРАБОТАТЬ: формат result не проверен, привести в соответствие с ожидаемым
            
            if ( $tmp ){
            
                foreach($tmp as $k=>$v){
                    switch($field_type){
                    case "list":
                        $tmp_list = explode(",",$v[$field]); foreach($tmp_list as $tmp_list_k=>$tmp_list_v) $tmp_list[$tmp_list_k] = trim($tmp_list_v);
                        if ( in_array($value, $tmp_list)){
                            $result[] = $v["id"];
                        }
                        break;
                    default:
                        $result[] = (int) $v["id"];
                    }
                } 
            };
            // dump($result,"result");
        }else{
            dosyslog(__FUNCTION__.": ERROR: " . get_callee() . " SQL ERROR:  [" . $db_table . "]: '".db_error($dbh).". Query: ".$query);
        };
    }else{
        dosyslog(__FUNCTION__.": ERROR: " . get_callee() . " DB is not set. Db_set() has to called before db_find().");
    };
    
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");   
    return $result;
};
function db_get($db_table, $id, $flags=0){
    
    global $S;
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    $result = array();
    $dbh = db_set($db_table);
    
    $table_name = db_get_table($db_table);
	
    if ( ! is_numeric($id)){
        dosyslog(__FUNCTION__.": FATAL ERROR: " . get_callee() . " Non-numeric id: '".serialize($id)."' while querying DB '" . $db_table . "'.");
    };
    
    $query = "SELECT * FROM " . $table_name . " WHERE id='" . $id . "';";
    
    if (DB_NOTICE_QUERY) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " SQL: '".$query."'.");
    $res = $dbh->query($query);
    
    if ($res){
        $result = $res->fetchAll(PDO::FETCH_ASSOC); //  ДОРАБОТАТЬ: добавить обработку ситуации, когда найдено более одной запсии.
        if (!empty($result)){
            $result = $result[0]; 
           
            if ( ! ($flags & DB_DONT_EXPLODE_LISTS) ){
                $result = db_parse_result($db_table, $result);
            };
        };
    }else{
        dosyslog(__FUNCTION__.": ERROR: " . get_callee() . " SQL ERROR:  [" . $db_table . "]: '".db_error($dbh).". Query: ".$query);
    };
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    return $result;
};
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
function db_insert($db_table, $data){
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    $result = false;
    
    $dbh = db_set($db_table);
    
    $query = db_create_insert_query($db_table, $data);
    
    
    if (DB_NOTICE_QUERY) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " SQL: '".$query."'.");
    $res = $dbh->exec($query);
    
    if ($res){
        
        $result = $dbh->lastInsertId();
        
    }else{
        dosyslog(__FUNCTION__.": ERROR: " . get_callee() . " SQL ERROR:  [" . $db_table . "]: '".db_error($dbh)."'. Query: '".$query."'.");
    };
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    
    return $result;
};
function db_set($db_table){
    global $_DB;
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
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
    
        if ( ! (int) $dbh->query($query_table_check)->fetchColumn() ){  // создаем таблицу, если она не сущестует
            $query = db_create_table_query($db_table);
            // dump($query,"q");
            dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " Creating table ".$db_table.".");
            
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
    
    
    
    $S["_DB"] = $dbh;
    $S["_DB_NAME"] = $db_name;
    $S["_DB_TABLE"] = $db_table;
    
    
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
	return $dbh;
};
function db_select($db_table, $select_query, $flags=0){
    
    global $S;
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    $result = array();
    $dbh = db_set($db_table);
	
    
    if (substr($select_query,0,strlen("SELECT ")) !== "SELECT "){
        dosyslog(__FUNCTION__.": FATAL ERROR: " . get_callee() . " Only SELECT query is allowed. Query: '".htmlspecialchars($select_query)."'. IP:".$_SERVER["REMOTE_ADDR"]);
        die();
    };
    
    if ( (strpos($select_query,";") !== false) && (strpos($select_query,";") < strlen($select_query)-1) ){
        dosyslog(__FUNCTION__.": FATAL ERROR: " . get_callee() . " Only one SELECT query is allowed. Query: '".htmlspecialchars($select_query)."'. IP:".$_SERVER["REMOTE_ADDR"]);
        die();
    };
    
    if (DB_NOTICE_QUERY) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " SQL: '".$select_query."'.");
    $res = $dbh->query($select_query);
    
    if ($res){
        while ( ($row = $res->fetch(PDO::FETCH_ASSOC) ) !== false) {
            if ( ! ($flags & DB_DONT_EXPLODE_LISTS) ){
                $result[] = db_parse_result($db_table, $row);
            }else{
                $result[] = $row;
            };
        };
    }else{
        dosyslog(__FUNCTION__.": ERROR: " . get_callee() . " SQL ERROR:  '".db_error($dbh)."'. Query: '".$select_query."'.");
    };
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    
    return $result;
};
function db_create_insert_query($db_table, $data){
	
    $dbh = db_set($db_table);
    $table_name = db_get_table($db_table);
    
    $query = "INSERT INTO ".$table_name." (";
    $tmp = array();
    foreach($data as $k=>$v){
        if ( ($v!==NULL) || ($v!==false) ){
            $tmp[] = $k;
        };
    };

    $query .= implode(", ",$tmp);
    
    $query .= ") VALUES (";
    
    $tmp = array();
    foreach($data as $k=>$v){
       if (is_array($v)){
            $tmp[] = $dbh->quote( implode(DB_LIST_DELIMITER, $v) );
       }elseif ( ($v!==NULL) && ($v!==false) ){
			$tmp[] = $dbh->quote($v);
        }elseif($v === NULL){
			$tmp[] = "NULL";
		};
    };

    $query .= implode(", ",$tmp);    
    $query .= ");";
    
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
        $type = (string) $field["type"];
        switch ($type){
            case "autoincrement": $tmp .= " INTEGER PRIMARY KEY"; break;
            case "number":        $tmp .= " NUMERIC"; break;
            case "timestamp":     $tmp .= " NUMERIC"; break;
            case "string":        $tmp .= " TEXT"; break;
            case "json": $tmp .=" TEXT"; break;
        };
        $aTmp[] = $tmp;
    };
    $query .= " (" . implode(", ",$aTmp).")";
    
    $dbh = null;
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    return $query;
}; 
function db_get_table_schema($db_table){
    
    global $CFG;
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    
    $db = false;
    $table = false;
    
    $db_name = db_get_name($db_table);
    $db_table = db_get_table($db_table);
    
    $db_file = APP_DIR . "settings/db.xml";
    $xml = xml_load_file($db_file);
    $isFound = false;
    if ($xml){
        foreach($xml->db as $xmldb){
            if ($db_name == (string) $xmldb["name"]){
                $db = $xmldb;
                $isFound = true;
                break;
            };
        }; //foreach xml
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
function db_get_tables_list_from_xml($db_name=""){
    
    global $CFG;
	if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");   
	
    
    $db = false;
    $table = false;
    $tables_list = array();
    
    $db_name = db_get_name($db_name);
    
    $db_files = array(APP_DIR . "settings/db.xml");
    
    $isFound=false;
    foreach ($db_files as $k=>$xmlfile){
        $xml = xml_load_file($xmlfile);
        if ($xml){
            
            foreach($xml->db as $xmldb){
                if ($db_name == (string) $xmldb["name"]){
                    $db = $xmldb;
                    $isFound = true;
                    break;
                };
            }; //foreach xml
            
        };
        if (!empty($db)) break;
    };
    if (!$isFound){
        dosyslog(__FUNCTION__.": FATAL ERROR: " . get_callee() . " Db '".$db_name."' is not found in any db XML files.");
        die("Code: db-".__LINE__);
    };
        
        
        
    if (!empty($db->table)){
        foreach($db->table as $xmltable){
            $tables_list[] = (string) $xmltable["name"];
        };
    };
    
    if (TEST_MODE) dosyslog(__FUNCTION__.": NOTICE: " . get_callee() . " Memory usage: ".(memory_get_usage(true)/1024/1024)." Mb.");
    return $tables_list;
};
function db_parse_result($db_table, $result){

    // Поля типа list преобразовать в массив
    $schema = db_get_table_schema($db_table);
    foreach($schema as $field){
        if ($field["type"] == "list"){
            if (isset($result[$field["name"]]) ){
                if (strpos($result[$field["name"]], DB_LIST_DELIMITER) !== false){
                    $result[$field["name"]] = explode(DB_LIST_DELIMITER, trim($result[$field["name"]], DB_LIST_DELIMITER));
                }else{
                    $result[$field["name"]] = array($result[$field["name"]]);
                };
            };
        };
    };

    return $result;
}