<?php
// FEEDNEXT API 

session_start();
if (!isset($_SESSION['feednext'])) {
  $_SESSION['feednext'] = array('r'=>'', 'd'=>array());
}

$_SESSION['feednext']['ute'] = 1;


////////////////////
// VARIABILI 
////////////////////

function getConnection() {
	$dbhost="localhost";
	$dbuser="xxxxxxxxxxx";
	$dbpass="xxxxxxxxxxx";
	$dbname="xxxxxxxxxxx";

	// FORZO LA CODIFICA A UTF-8
	$db = new PDO("mysql:host=$dbhost;dbname=$dbname", $dbuser, $dbpass, 
		array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));	
	$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	return $db;
}


$db = getConnection();

require 'Slim/Slim.php';
\Slim\Slim::registerAutoloader();
$app = new \Slim\Slim();

$app->get('/prova', function() { echo 'prova'; });

$app->get('/domini',       'domini');
$app->get('/domini_spam',  'domini_spam');
$app->get('/ricerca_sito', 'ricerca_sito');

$app->get('/set_ricerca/:t',   'set_ricerca');
$app->get('/unset_ricerca',    'unset_ricerca');
$app->get('/set_dominio/:t',   'set_dominio');
$app->get('/unset_dominio/:t', 'unset_dominio');

$app->get('/spam_m/:id',   'spam_m');
$app->get('/del_m/:id',    'del_m');
$app->get('/del_s/:id',    'del_s');

$app->get('/greatest_hits',  'greatest_hits');

$app->run();


//////////////////////////
//////////////////////////
//////////////////////////

function domini () {
  $res = db_query("select count(id) as num, dominio from collezionatore 
    where id_ute = :ute and spam = 0 
    group by dominio order by num desc", array(':ute'=> $_SESSION['feednext']['ute']));
  $elenco = $res->fetchAll(PDO::FETCH_OBJ);
  echo_json($elenco); 
}

function domini_spam () {
  $res = db_query("select count(id) as num, dominio from collezionatore 
    where id_ute = :ute and spam = 1 
    group by dominio order by num desc LIMIT 15", array(':ute'=> $_SESSION['feednext']['ute']));
  $elenco = $res->fetchAll(PDO::FETCH_OBJ);
  echo_json($elenco); 
}

function ricerca_sito () {
  $parole_escluse = json_decode(file_get_contents('../parole_escluse.json'));

  $res = db_query("select testo from collezionatore_search");
  $parole_s = get_parole($res, $parole_escluse);
  $parole_s = array_slice($parole_s, 0, 40);
  echo_json($parole_s); 
}


function set_ricerca ($t) {
  $_SESSION['feednext']['r'] = $t;
  print_r($_SESSION);
}

function unset_ricerca () {
  $_SESSION['feednext']['r'] = '';
  print_r($_SESSION);
}

function set_dominio ($t) {
  if (!isset($_SESSION['feednext']['d'])) {
    $_SESSION['feednext']['d'] = array();
  }
  $_SESSION['feednext']['d'][] = $t;
  print_r($_SESSION);
}

function unset_dominio ($t) {
  $_SESSION['feednext']['d'] = array_diff($_SESSION['feednext']['d'], array($t));
  print_r($_SESSION);
}

function spam_m ($id) {
  if (is_numeric($id)) {
    db_query("update collezionatore set spam = 1 
      where id_ute = :ute and id = :id", array(':id'=>$id, ':ute'=>$_SESSION['feednext']['ute']));
  }
}

function del_m ($id) {
  if (is_numeric($id)) {
    db_query("delete from collezionatore 
      where id_ute = :ute and id = :id", array(':id'=>$id, ':ute'=>$_SESSION['feednext']['ute']));
  }
}

function del_s ($id) {
  if (is_numeric($id)) {
    db_query("delete from collezionatore_search 
      where id_ute = :ute and id = :id", array(':id'=>$id, ':ute'=>$_SESSION['feednext']['ute']));
  }
}

function greatest_hits () {
  // RESTITUISCE NUVOLA PAROLE SINGOLE E DOPPIE
  
  $parole_escluse = json_decode(file_get_contents('../parole_escluse.json'));

  $res = db_query("select testo from collezionatore 
    where id_ute = :ute and spam = 0", array(':ute'=> $_SESSION['feednext']['ute']));
  $out_parole_email = db_rownum($res);
  $parole_esteso = get_parole($res, $parole_escluse);
  $parole = array_slice($parole_esteso, 0, 50);
  $out_parole = implode('', cloud($parole, true));

  $parole_doppie = array();
  $parola_piu_usata = array_pop(array_keys(array_slice($parole,0,1)));
  $parole1 = array_slice($parole, 1, 30); // SALTO LA PIU USATA
  foreach ($parole1 as $parola => $num) {
    $res2 = db_query("select testo from collezionatore 
      where id_ute = :ute and spam = 0 and testo like :parola", array(':parola' => '%'.$parola.'%', ':ute'=> $_SESSION['feednext']['ute']));
    $seconde_parole = get_parole($res2, $parole_escluse);
    foreach ($seconde_parole as $parola2 => $num2) {
      // EVITO PAROLE DOPPIONI E COPPIE INVERTITE + SALTO LA PIU USATA ANCHE QUI
      if ($parola >= $parola2 || $parola2 == $parola_piu_usata) {
        continue;
      }
      if (!isset($parole_doppie[$parola.' '.$parola2])) {
        $parole_doppie[$parola.' '.$parola2] = 0;
      }
      $parole_doppie[$parola.' '.$parola2] += $num2;
    }
  }
  arsort($parole_doppie);
  $parole_doppie = array_slice($parole_doppie, 0, 50);
  $out_parole_doppie = implode('', cloud($parole_doppie, true));

  echo_json(array(
    'n'       => $out_parole_email, 
    'parole'  => $parole_esteso,
    'singole' => $out_parole, 
    'doppie'  => $out_parole_doppie,
    )); 
}



///////////////////////////////////
///////////////////////////////////
///////////////////////////////////

function get_parole($res, $parole_escluse) {
  $sost = array(','=>' ', '.'=>' ', ':'=>' ', ';'=>' ', '!'=>' ', "\t"=>' ', "\r"=>' ', "\n"=>' ', "'"=>' ', 
    '"'=>' ', '?'=>' ', '('=>' ', ')'=>' ', '['=>' ', ']'=>' ', '{'=>' ', '}'=>' ', '/'=>' ', "\\"=>' ',
    '&#039;' =>' ');
  $lunghezza_minima = 4;

  while ($row=db_fetch($res)) {
    // TODO FILTRO SPAM
  
    // PULIZIA CARATTERI
    $row['testo'] = strtr($row['testo'],$sost);

    // USO UNIQUE PER CONSIDERARE OGNI PAROLA UNA SOLA VOLTA PER MESSAGGIO
    $p = array_filter(array_unique(explode(' ',strtolower($row['testo']))));
    foreach ($p as $parola) {
      if (strlen($parola) < $lunghezza_minima || in_array($parola, $parole_escluse) || is_numeric($parola)) {
        continue;
      }

      if (!isset($parole[$parola])) {
        $parole[$parola] = 0;
      } 
      $parole[$parola] += 1;

    }
  }
  arsort($parole);
  return $parole;
}


function cloud($parole, $link=false, $attiva_plus=false) {
  // DIMENSIONI CLOUD
  $smallest = 13;
  $largest  = 32;

  $parole_div = array();

  $min_count = min( $parole );
  $spread = max( $parole ) - $min_count;
  if ( $spread <= 0 ) $spread = 1;
  $font_step = round(($largest - $smallest) / $spread, 2);

  foreach ($parole as $parola=>$num) {
    $dimensione = $smallest + ( $num - $min_count ) * $font_step;
    if ($link) {
    	$parola_orig = $parola;
      $parola = '<a href="?f='.$parola.'">'.$parola_orig.'</a>';
      if ($attiva_plus && $_GET['f'] != '') {
        $parola .= '<a href="?f='.$_GET['f'].'%20'.$parola_orig.'"><sup>+</sup></a>';
      }
      if ($attiva_plus && $_GET['f'] != '') {
        $parola = '<a href="?f='.$_GET['f'].'%20-'.$parola_orig.'"><sup>x</sup></a>'.$parola;
      }
    }
  	$parole_div []= '<span style="padding-right: 6px;">
        <span class="nobr" style="font-size:'.$dimensione.'px">'.$parola.'</span><sub>'.$num.'</sub>
      </span>';
  }
  shuffle($parole_div);
  return $parole_div;
}



function db_query ($sql, $parametri='') {
	global $db, $debug_query;
	//echo "$sql<br />";
	if ($debug_query) {
		$time1 = microtime(true);
	}
	try {
		if (is_array($parametri)) {
			$res = $db->prepare($sql);
			$res->execute($parametri);
		} else {
			$res = $db->query($sql); 
		}
	} catch (PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}';
    echo '{"error":{"text":"Errore Sql"}}';
		die();
	}
	return $res;
}

function db_getone ($sql, $parametri='') {
	global $db, $debug_query;
	//echo "$sql<br />";
	if ($debug_query) {
		$time1 = microtime(true);
	}
	try {
		if (is_array($parametri)) {
			$res = $db->prepare($sql);
			$res->execute($parametri);
      $out = $res->fetchColumn();
		} else {
			$out = $db->query($sql)->fetchColumn(); 
		}
	} catch (PDOException $e) {
    echo '{"error":{"text":'. $e->getMessage() .'}}';
    echo '{"error":{"text":"Errore Sql"}}';
	  die();
	}
	return $out;
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

/* FUNZIONE USATA PER GESTIRE JSON E JSONP */
function echo_json ($res) {
  $app = \Slim\Slim::getInstance();
  $app->contentType('application/json');
	// Include support for JSONP requests
	if (!isset($_GET['callback'])) {
	    echo json_encode($res);
	} else {
	    echo $_GET['callback'] . '(' . json_encode($res) . ');';
	}
}