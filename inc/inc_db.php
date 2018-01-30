<?php
$debug_query = false;

function db_connect() {
  global $conf;
  $db = new PDO("mysql:host=".$conf['db_host'].";dbname=".$conf['db_name']."", $conf['db_user'], $conf['db_pwd']);
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $db->exec("SET CHARACTER SET utf8");
  return $db;
}

function db_query ($sql, $parametri='') {
	global $db;
	try {
		if (is_array($parametri)) {
			$res = $db->prepare($sql);
			$res->execute($parametri);
		} else {
			$res = $db->query($sql); 
		}
	} catch (PDOException $e) {
    echo '{"error":{"text":"Errore Sql"}}';
		die();
	}
	return $res;
}

function db_getone ($sql, $parametri='') {
	global $db;
	try {
		if (is_array($parametri)) {
			$res = $db->prepare($sql);
			$res->execute($parametri);
      $out = $res->fetchColumn();
		} else {
			$out = $db->query($sql)->fetchColumn(); 
		}
	} catch (PDOException $e) {
	    echo '{"error":{"text":"Errore Sql"}}';
		  die();
	}
	return $out;
}


function db_getarray ($sql, $campo_back, $campo_front, $parametri='') {
  $array_out = array();
  $res =  db_query($sql, $parametri);
  while ($row = db_fetch($res)) {
    if ($campo_back != '') {
      $array_out[$row[$campo_back]] = $row[$campo_front];
    } else {
      $array_out[] = $row[$campo_front];
    }
  }
  return $array_out;
}


function db_rownum ($res) {
	$numero=$res->rowCount();
	return $numero;
}


function db_fetch ($res) {
	$array=$res->fetch(PDO::FETCH_ASSOC);
	$array[]="";
	$array_campi=array_keys($array);
	array_pop($array);
	return $array;
}

function db_fetch_html ($res) {
  $array = db_fetch ($res);
  foreach ($array as $key=>$val) {
    $array[$key] = htmlspecialchars($val);
  }
	return $array;
}

function db_fetchall ($res) {
	return $res->fetchAll(PDO::FETCH_OBJ);
}
