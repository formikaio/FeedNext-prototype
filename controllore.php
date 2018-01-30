<?php
/**
 * CONTROLLORE
 */

require('inc/inc_db.php');

////////////////////
/// VARIABILI
////////////////////
$conf = array(
  'db_host' => 'localhost',
  'db_name' => 'xxxxxxx',
  'db_user' => 'xxxxxxx',
  'db_pwd'  => 'xxxxxxx',
);
$db = db_connect();

if (isset($_GET['advanced'])) {
  // AUTOMATISMI AGGIUNTIVI, CARICATI A RICHIESTA

  // ELIMINA DOPPIONI MAIL
  $res = db_query("SELECT a.id FROM collezionatore a 
    join collezionatore b on (a.testo = b.testo and a.mail = b.mail and a.id < b.id) where a.testo <> '' and a.spam = 0");
  while ($row=db_fetch($res)) {
    db_query("delete from collezionatore where id = :id", array(':id'=>$row['id']));
  }
  // ELIMINA DOPPIONI SEARCH
  $res = db_query("SELECT a.id FROM collezionatore_search a 
    join collezionatore_search b on (a.testo = b.testo and a.data = b.data and a.id < b.id) where a.testo <> ''");
  while ($row=db_fetch($res)) {
    db_query("delete from collezionatore_search where id = :id", array(':id'=>$row['id']));
  }
  
  // PULIZIA SEARCH
  db_query("delete from collezionatore_search where testo like '{search_term%' or testo = 4");
  
  echo '<h2>Pulizia effettuata, grazie!</h2>';

} else {

  // AUTOMATISMI CARICATI SILENZIOSAMENTE SEMPRE
  $id_max_spam = db_getone("select max(id) from collezionatore where spam = 1");
  
  $cond_spam = (is_numeric($id_max_spam)) ? ' id > '.$id_max_spam.' and ' : '';
  
  // CONTROLLA LINK NEL CAMPO "TELEFONO"
  db_query("update collezionatore set spam = 1 where $cond_spam (tel like '%www%' or tel like '%http%' or tel like '%href%' or tel like '%url%') ");

  // CONTROLLA LINK NEL CAMPO "TESTO"
  db_query("update collezionatore set spam = 1 where $cond_spam (testo like '%href%' or testo like '%http%' or testo like '%url=%') ");
}

die;
