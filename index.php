<?php
/////////////////
// VARIABILI
/////////////////

$demo = false;

$api_base = './api/';

require('inc/inc_db.php');
$conf = array(
  'db_name' => 'xxxxxxxx',
  'db_host' => 'localhost',
  'db_user' => 'xxxxxxxx',
  'db_pwd'  => 'xxxxxxxx',
);
$db = db_connect();


include_once('stat_functions.php');


$js_code = '';

$demo_banner = ($demo) ? 'demo' : 'beta';

$is_home = !isset($_GET['f']) && !isset($_GET['d']);

$out_domini = '<table class="table table-bordered table-hover table-condensed">';
$domini_good = array();
$dati = file_get_contents($api_base.'domini');
$dati2 = json_decode($dati);
  foreach($dati2 as $d) {
  $domini_good[$d->dominio] = $d->num;
}
foreach ($domini_good as $dominio => $num) {
  $dominio_mostrato = ($demo) ? 'xxxxxxxxxxx.com' : $dominio;
	$out_domini .= '<tr><td>
    <a href="?d='.$dominio.'">'.$dominio_mostrato.'</a>
    <span class="pull-right"><span class="badge">'.$num.'</span> 
    <a href="http://'.$dominio.'" target="_blank"><span class="glyphicon glyphicon-new-window"></span></a></span></td></tr>';
}
$out_domini .= '</table>';

$out_domini_spam = '';
if ($is_home) {
  // AGGIUNGO LA TABELLA SOLO IN HOME
  $out_domini_spam .= '
    <div class="panel panel-warning">
      <div class="panel-heading">Spam per dominio 
      </div>
      <table class="table table-bordered table-hover table-condensed">';
  $dati = file_get_contents($api_base.'domini_spam');
  $dati2 = json_decode($dati);
  foreach($dati2 as $d) {
    $spam_perc = 100;
    $spam_perc_label = 'danger';
    if (isset($domini_good[$d->dominio])) {
      $spam_perc = round( $d->num * 100 / ($d->num + $domini_good[$d->dominio]) );
      if ($spam_perc < 15) {
        $spam_perc_label = 'success';
      } else if ($spam_perc < 35) {
        $spam_perc_label = 'warning';
      } 
    }
    $dominio_mostrato = ($demo) ? 'xxxxxxxxxxx.com' : $d->dominio;
  	$out_domini_spam .= '<tr><td>
      '.$dominio_mostrato.'
      <small><span class="label label-'.$spam_perc_label.'">'.$spam_perc.'%</span></small> 
      <span class="pull-right"><span class="badge">'.$d->num.'</span>
      <a href="http://'.$d->dominio.'" target="_blank"><span class="glyphicon glyphicon-new-window"></span></a></span></td></tr>';
  }
  $out_domini_spam .= '</table>
        </div>';
}


$parole_escluse = json_decode(file_get_contents('parole_escluse.json'));

// GREATEST HITS - PAROLE SINGOLE
$dati = file_get_contents($api_base.'greatest_hits');
$dati2 = json_decode($dati);
$out_parole_email  = $dati2->n;
$parole_esteso     = (array)$dati2->parole;
$out_parole        = $dati2->singole;
$out_parole_doppie = $dati2->doppie;

$out_feedback_recenti_t = '';
$res_feedback_recenti = '';
$out_feedback_ricerche_t = '';
$res_feedback_ricerche = '';


$focus_parola = '';
if ($_GET['f'] != '') {
  if (strlen($_GET['f']) < 3) {
    $focus_parola .= '<p class="alert alert-danger">Testo di ricerca troppo corto!</p>';
  } else {
  
    // CONTROLLO NUMERO DI PAROLE
    $parole_c = array();
    $condizioni = array();
    foreach (explode(' ', $_GET['f']) as $key => $parola) {
      if (substr($parola,0,1) == '-') {
        // PAROLE NEGATE
        $condizioni[] = 'and testo not like :parola'.$key;
        $parole_c['parola'.$key] = '%'.substr($parola,1).'%';
      } else {
        // PAROLE NORMALI
        $condizioni[] = 'and testo like :parola'.$key;
        $parole_c['parola'.$key] = '%'.$parola.'%';
      }
    }

    $res = db_query("select testo from collezionatore where spam = 0 ".implode(" ", $condizioni), $parole_c);

    // ESCLUDO DALLE RISPOSTE LE PAROLE CHE STO USANDO
    $parole_r = get_parole($res, array_merge($parole_escluse, explode(' ', $_GET['f'])));
    $parole_r = array_slice($parole_r, 0, 50);
  
    // GRAFICO
    $dati_grafico = array();
    $res_g = db_query("select count(id) as n, data from collezionatore where spam = 0 ".implode(" ", $condizioni)." group by data", $parole_c);
    while ($row=db_fetch($res_g)) {
      $dati_grafico[] = array('name' => $row['data'], 'y' => (int)$row['n']);
    }
    $dati_grafico_search = array();
    $res_g = db_query("select count(id) as n, data from collezionatore_search where true ".implode(" ", $condizioni)." group by data", $parole_c);
    while ($row=db_fetch($res_g)) {
      $dati_grafico_search[] = array('name' => $row['data'], 'y' => (int)$row['n']);
    }
  
    $focus_parola .= '
      <div class="row">
        <div class="col-xs-12">
        <div class="panel panel-primary">
            <div class="panel-heading">Focus su <b>'.$_GET['f'].'</b> <span class="pull-right">da '.db_rownum($res).' mail</span></div>
            <div class="panel-body">
              <div class="row">
                <div class="col-sm-6">
                  '.implode('', cloud($parole_r, true, true)).'
                </div>
                <div class="col-sm-6">
                  <div id="g1" class="spline"></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>';
    $js_code .= '
      var options_spline_1 = $.extend({}, options_spline_shared, {
        title:  { text: "" },    series: [{}]
      });
    options_spline_1.series[0] = { name: "Mail",     data: '.json_encode($dati_grafico).'.map(dateFormatCdsHighCharts) };
    options_spline_1.series[1] = { name: "Ricerche", data: '.json_encode($dati_grafico_search).'.map(dateFormatCdsHighCharts) };
    $("#g1" ).highcharts(options_spline_1);
    ';

    // FEEDBACK E RICERCHE CON PAROLA
    $out_feedback_recenti_t = ' su <b>'.$_GET['f'].'</b>';
    $res_feedback_recenti = db_query("select id, testo, dominio, data from collezionatore where spam = 0 ".implode(" ", $condizioni)." order by id desc LIMIT 15", $parole_c);

    $out_feedback_ricerche_t = ' su <b>'.$_GET['f'].'</b>';
    $res_feedback_ricerche = db_query("select id, testo, dominio, data from collezionatore_search where true ".implode(" ", $condizioni)." order by id desc LIMIT 10", $parole_c);
  }
}


$focus_dominio = '';
if ($_GET['d'] != '') {
  $dominio_mostrato = ($demo) ? 'xxxxxxxxxxx.com' : $_GET['d'];
  $res = db_query("select testo from collezionatore where spam = 0 and dominio = :dominio", array(':dominio'=> $_GET['d']));
  $parole_d = get_parole($res, $parole_escluse);
  $parole_d = array_slice($parole_d, 0, 50);

  // GRAFICO
  $dati_grafico = array();
  $res_g = db_query("select count(id) as n, data from collezionatore where spam = 0 and dominio = :dominio group by data", array(':dominio'=> $_GET['d']));
  while ($row=db_fetch($res_g)) {
    $dati_grafico[] = array('name' => $row['data'], 'y' => (int)$row['n']);
  }

  $focus_parola .= '<div class="panel panel-primary">
        <div class="panel-heading">Focus da <b>'.$dominio_mostrato.'</b> <span class="pull-right">da '.db_rownum($res).' mail</span></div>
        <div class="panel-body">
            <div class="row">
              <div class="col-sm-6">
                '.implode('', cloud($parole_d, true, true)).'
              </div>
              <div class="col-sm-6">
                <div id="g1" class="spline"></div>
              </div>
            </div>
        </div>
      </div>
      <br>';
  $js_code .= '
    var options_spline_1 = $.extend({}, options_spline_shared, {
      title:  { text: "" },    series: [{}]
    });
  options_spline_1.series[0] = { name: "Data", data: '.json_encode($dati_grafico).'.map(dateFormatCdsHighCharts) };
  $("#g1" ).highcharts(options_spline_1);
  ';

  // FEEDBACK E RICERCHE DAL DOMINIO
  $out_feedback_recenti_t = ' da <b>'.$dominio_mostrato.'</b>';
  $res_feedback_recenti = db_query("select id, testo, dominio, data from collezionatore where spam = 0 and dominio = :dominio order by id desc LIMIT 15", array(':dominio'=> $_GET['d']));

  $out_feedback_ricerche_t = ' da <b>'.$dominio_mostrato.'</b>';
  $res_feedback_ricerche = db_query("select id, testo, dominio, data from collezionatore_search where dominio = :dominio order by id desc LIMIT 10", array(':dominio'=> $_GET['d']));
}


$out_feedback_recenti = '<table class="table table-bordered table-hover table-condensed">';
if ($res_feedback_recenti == '') {
  $res_feedback_recenti = db_query('select id, testo, dominio, data from collezionatore where spam = 0 order by id desc LIMIT 20');
}
while ($row=db_fetch($res_feedback_recenti)) {
  if (strlen($row['testo']) < 10) { continue; }
  $testo_mostrato = ($demo) ? 'lorem ipsum dolor sit amet' : stripslashes($row['testo']);
  if (!$demo) {
    // AGGIUNGO LA DATA
    $testo_mostrato = '<small class="text-success">'.substr($row['data'],8,2).'/'.substr($row['data'],5,2).'</small> '.$testo_mostrato;
  }
  $dominio_mostrato = ($demo) ? 'xxxxxx.com' : $row['dominio'];

  if (isset($_GET['d'])) {
    $dominio_mostrato = '';
  }
  if (isset($_GET['f'])) {
    foreach (explode(' ', $_GET['f']) as $parola) {
      if (strlen($parola) < 3) {
        continue;
      }
      $testo_mostrato = str_ireplace($parola, '<span class="bg-primary">'.$parola.'</span>', $testo_mostrato);
    }
  }
	$out_feedback_recenti .= '<tr><td>
      <small class="text-success pull-right">
        '.$dominio_mostrato.'
      <button class="btn btn-sm btn-default" onclick="confirm(\'Confermi?\') ? get_and_reload(\''.$api_base.'spam_m/'.$row['id'].'\') : void(0)"><span class="glyphicon glyphicon-fire"></span></button>
      <button class="btn btn-sm btn-default" onclick="confirm(\'Confermi?\') ? get_and_reload(\''.$api_base.'del_m/' .$row['id'].'\') : void(0)"><span class="glyphicon glyphicon-remove"></span></button>
      </small>
      '.$testo_mostrato.'
    </td></tr>';
}
$out_feedback_recenti .= '</table>';


$out_feedback_ricerche = '<table class="table table-bordered table-hover table-condensed">';
if ($res_feedback_ricerche == '') {
  $res_feedback_ricerche = db_query('select id, testo, dominio, data from collezionatore_search order by id desc LIMIT 20');
}
while ($row=db_fetch($res_feedback_ricerche)) {
  //if (strlen($row['testo']) < 10) { continue; }
  $testo_mostrato = ($demo) ? 'lorem ipsum dolor sit amet' : stripslashes($row['testo']);
  if (!$demo) {
    // AGGIUNGO LA DATA
    $testo_mostrato = '<small class="text-success">'.substr($row['data'],8,2).'/'.substr($row['data'],5,2).'</small> '.$testo_mostrato;
  }
  $dominio_mostrato = ($demo) ? 'xxxxxx.com' : $row['dominio'];

  if (isset($_GET['d'])) {
    $dominio_mostrato = '';
  }
  if (isset($_GET['f'])) {
    foreach (explode(' ', $_GET['f']) as $parola) {
      if (strlen($parola) < 3) {
        continue;
      }
      $testo_mostrato = str_ireplace($parola, '<span class="bg-primary">'.$parola.'</span>', $testo_mostrato);
    }
  }
	$out_feedback_ricerche .= '<tr><td>
      <small class="text-success pull-right">
        '.$dominio_mostrato.'
        <button class="btn btn-sm btn-default" onclick="confirm(\'Confermi?\') ? get_and_reload(\''.$api_base.'del_s/' .$row['id'].'\') : void(0)"><span class="glyphicon glyphicon-remove"></span></button>
      </small>
      '.$testo_mostrato.'
    </td></tr>';
}
$out_feedback_ricerche .= '</table>';


$out_ricerca_sito = '
  <div class="panel panel-info">
    <div class="panel-heading">Ricerche sui siti 
    </div>
    <div class="panel-body">
       <div class="row">';
$dati = file_get_contents($api_base.'ricerca_sito');
$parole_s = (array)json_decode($dati);
$out_ricerca_sito_arr = array();
foreach ($parole_s as $parola=>$num) {
  $mail = (isset($parole_esteso[$parola])) ? $parole_esteso[$parola] : 0;
  $potenziale = $num * round(log($mail + 1) + 1);
  $spam_perc_label = 'danger';
  if ($mail > ($num)) {
    $spam_perc_label = 'success';
  } else if ($mail > 0) {
    $spam_perc_label = 'warning';
  } 
	$out_ricerca_sito_arr[str_pad($potenziale,4,'0',STR_PAD_LEFT).' '.$parola] = '<div class="col-xs-6">
    <a href="?f='.$parola.'">'.$parola.'</a><sub>'.$num.'</sub>
    <small class=""><span class="label label-'.$spam_perc_label.'">'.$mail.'
    </span></small></div>';
}
krsort($out_ricerca_sito_arr);
foreach ($out_ricerca_sito_arr as $val) {
  $out_ricerca_sito .= $val;
}
$out_ricerca_sito .= '</div>
      </div>
    </div>';


?><!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="utf-8" />
  <meta name="robots" content="noindex">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">  <title>FeedNext</title>
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css">
  <style>
    body     { background:#eee; }
    sub, .sw { color: gray; }
    .nobr    { white-space:nowrap; }
    #g1      { height: 200px; }
  </style>
</head>
<body>
<div class="container">
  <form>
    <div class="row">
      <div class="col-xs-8 col-sm-4 col-md-3">
        <h1 class="nobr">
          <img src="assets/logo_feednext.png" alt="logo" title="">
          <a href="?"">FeedNext</a><small><sub><?= $demo_banner ?></sub></small></h1>
      </div>
      <div class="col-xs-4 col-sm-2 col-md-2">
      </div>
      <div class="col-xs-12 col-sm-6 col-md-7">
        <br>
        <div class="input-group">
          <span class="input-group-addon"><span class="glyphicon glyphicon-search"></span></span>
          <input type="text" name="f" id="f" class="form-control" placeholder="Cerca..." value="<?= $_GET['f'] ?>">
          <div class="input-group-btn">
            <button class="btn btn-default dropdown-toggle" type="button" id="dropdownMenu1" data-toggle="dropdown">
              Totale
              <span class="caret"></span>
            </button>
            <ul class="dropdown-menu">
              <li><a href="#">Totale</a></li>
              <li><a href="#">7 giorni</a></li>
              <li><a href="#">30 giorni</a></li>
              <li><a href="#">3 mesi</a></li>
              <li><a href="#">6 mesi</a></li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </form>
  <br>
  <?= $focus_parola ?>
  <?= $focus_dominio ?>

  <? if ($is_home) : ?>
  <div class="row">
    <div class="col-sm-4">
      <div class="panel panel-default">
        <div class="panel-heading">Greatest Hits singole</div>
        <div class="panel-body">
          <?= $out_parole ?>
        </div>
      </div>
    </div>
    <div class="col-sm-8">
      <div class="panel panel-default">
        <div class="panel-heading">Greatest Hits doppie <span class="pull-right">da <?= $out_parole_email ?> feedback</span></div>
        <div class="panel-body">
          <?= $out_parole_doppie ?>
        </div>
      </div>
    </div>
  </div>
  <? endif; ?>

  <div class="row">
    <div class="col-sm-8">
      <div class="panel panel-success">
        <div class="panel-heading">Feedback recenti <?= $out_feedback_recenti_t ?>
          <a class="pull-right text-success" href="#" onclick="get_and_reload('controllore.php?advanced')">pulisci <span class="glyphicon glyphicon-fire"></span></a>
        </div>
        <?= $out_feedback_recenti ?>
      </div>
      <div class="panel panel-success">
        <div class="panel-heading">Ricerche sui siti recenti <?= $out_feedback_ricerche_t ?></div>
        <?= $out_feedback_ricerche ?>
      </div>
    </div>
    <div class="col-sm-4">
      <div class="panel panel-default">
        <div class="panel-heading">Feedback per dominio</div>
        <?= $out_domini ?>
      </div>
      <?= $out_domini_spam ?>
      <?= $out_ricerca_sito ?>
    </div>
  </div>
</div>


<script src="https://code.jquery.com/jquery-1.11.2.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/js/bootstrap.min.js"></script>
<script src="https://code.highcharts.com/4.0.4/highcharts.js"></script>
<script>
function get_and_reload (url) {
  $.get(url, function() {
    location.reload();
  });
}

$(function() {
  $("#f").keypress(function(e) {
      if(e.which == 13) {
          location.href = "?f=" + $("#f").val();
      }
  });  

  Highcharts.setOptions({ colors: ['#7cb5ec', '#90ed7d', '#f7a35c', '#8085e9', '#f15c80', '#e4d354', '#8085e8', '#8d4653', '#91e8e1'],
    lang: {
      months: ['Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'],
      shortMonths: ['Gen', 'Feb', 'Mar', 'Apr', 'Mag', 'Giu', 'Lug', 'Ago', 'Set', 'Ott', 'Nov', 'Dic'],
      weekdays: ['Dom', 'Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab']
    }
  });

  var options_spline_shared = {
    chart:       { type: "area" },
    credits:     { enabled: false },
    xAxis:       { type: 'datetime' },
    yAxis:       { title:  { text: '' } }
  };

  function dateFormatCdsHighCharts (data) {
    // TRASFORMA LA DATA DA 2012-10-31 A UNIX TIME E CONVERTE L'OGGETTO IN ARRAY
    var reggie = /(\d{4})-(\d{2})-(\d{2})/;
    var dateArray = reggie.exec(data.name);
    // Careful, month starts at 0!
    if (typeof dateArray === undefined || dateArray === null) {
      // HO MESE E ANNO IN FORMATO 2012/10
      reggie = /(\d{4})\/(\d{2})/;
      dateArray = reggie.exec(data.name);
      if (typeof dateArray !== undefined && dateArray !== null) {
        dateArray[3] = 1;
      } else {
        // HO SOLO L'ANNO
        dateArray = [];
        dateArray[1] = data;
        dateArray[2] = 1;
        dateArray[3] = 1;
      }
    }

    return [ Date.UTC((+dateArray[1]), (+dateArray[2])-1, (+dateArray[3])) , data.y ];
  }

  <?= $js_code ?>
});
</script>
</body>
</html>
