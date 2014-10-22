#!/usr/bin/php
<?php
/**
 *
 * This file is part of openLibrary.
 * Copyright © 2009, Dansk Bibliotekscenter a/s,
 * Tempovej 7-11, DK-2750 Ballerup, Denmark. CVR: 15149043
 *
 * openLibrary is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * openLibrary is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with openLibrary.  If not, see <http://www.gnu.org/licenses/>.
 */

$startdir = dirname(realpath($argv[0]));
require_once("$startdir/OLS_class_lib/pg_database_class.php");
require_once("$startdir/OLS_class_lib/objconvert_class.php");

/**
  * This script export data from an attachment table into a file. Data in file will have
  * this format :
  * <length of xml part in ascii>
  * <xml part>
  * <length of binary part in ascii>
  * <binary part>
  * ...
  */

/**
 * This function is used to write out the parameters which can be used for the program
 * @param str  The text string to be displayed in case of any errors
 */
function usage($str='') {
  global $argv, $server, $user, $pass, $filename, $theQuery, $table_name;

  echo "$str\n\n";
  echo "Usage: \n\$ php $argv[0]\n";
  echo "\t-s <postgres server> Adress of the server on the form some.server.somewhere \n";
  echo "\t-u <postgres user> Name of the pg user \n";
  echo "\t-p <postgres users passwd> Password for the pg user \n";
  echo "\t-o <filename> the name of the output file (including path)\n";
  echo "\t-t/T select from t:attachment or T:extern_attachment, default is attachment\n";
  echo "\t-q <SQL query> the query that is to be used to create the dump - if not specified, the whole table are dumped\n";
  echo "\t-v verbose\n";
  echo "\t-h help (shows this message) \n";
  exit;
}

$table_name = 'attachment';
$verbose = false;
$options = getopt('p:ho:s:u:vq:tT');
if (array_key_exists('h', $options)) usage(); 
if (array_key_exists('s', $options)) $server = $options['s'];
if (array_key_exists('u', $options)) $user = $options['u'];
if (array_key_exists('p', $options)) $pass = $options['p'];
if (array_key_exists('o', $options)) $filename = $options['o'];
if (array_key_exists('q', $options)) $theQuery = $options['q'];
if (array_key_exists('t', $options)) $table_name = 'attachment';
if (array_key_exists('T', $options)) $table_name = 'extern_attachment';
if (array_key_exists('T', $options)) $table_name = $options['t'];
if (array_key_exists('v', $options)) $verbose = true;

if ( !isset($server) || !isset($user) || !isset($pass) || !isset($filename) ) {
    usage("Missing database/user/filename information\n");
}

if ( $table_name == 'attachment' ) {
  if ( !isset($theQuery) ) {
    $theQuery = 'SELECT a.lokalid, a.bibliotek, a.attachment_type, a.source_id, t.mimetype, a.data FROM ' .
      $table_name . " a, attachment_types t WHERE a.attachment_type = t.type;";
  } else {
    $theQuery = 'SELECT a.lokalid, a.bibliotek, a.attachment_type, a.source_id, t.mimetype, a.data FROM ' .
      $table_name .  " a, attachment_types t WHERE a.attachment_type = t.type AND $theQuery;";
  }
} else {
  if ( !isset($theQuery) ) {
    $theQuery = 'SELECT a.lokalid, a.bibliotek, a.attachment_type, a.source_id, a.mimetype, a.data FROM ' .
      $table_name . " a;";
  } else {
    $theQuery = 'SELECT a.lokalid, a.bibliotek, a.attachment_type, a.source_id, a.mimetype, a.data FROM ' .
      $table_name .  " a WHERE $theQuery;";
  }
}

$dom = new DomDocument();
$obj = new objconvert();
try {
  $db=new pg_database("host=" . $server . " user=" . $user . " password=" . $pass );
  $db->open();
  $recfile = fopen("$filename", "w");
  if ( ! $recfile ) {
    echo "ERROR, could not open file " . $filename . "\n";
    exit(1);
  }
  // PHPDOC claims that pg_unescape_bytea can't handle anything but 'escape', but that seems to have changed - works fine here
  $db->set_query('SET bytea_output = "hex";');
  $db->execute();
  $more = true;
  echo 'theq=' . $theQuery . "\n";
  $db->set_query($theQuery);
  $db->execute();
  while ( $row = $db->get_row() ) {
    if (verbose) {
      echo 'lokalid        =' . $row['lokalid'] . "\n";
      echo 'library        =' . $row['bibliotek'] . "\n";
      echo 'attachment_type=' . $row['attachment_type'] . "\n";
      echo 'source_id      =' . $row['source_id'] . "\n";
      echo 'mimetype       =' . $row['mimetype'] . "\n";
    }

    $id_rec = array();
    $id_rec['localid']->_value = $row['lokalid'];
    $id_rec['library']->_value = $row['bibliotek'];

    $info_rec = array();
    $info_rec['name']->_value = $row['attachment_type'];
    $info_rec['mime']->_value = $row['mimetype'];
    $info_rec['sourceid']->_value = $row['source_id'];

    $additionalMedia = array();
    $additionalMedia['additionalMedia']->_value->id->_value = $id_rec;
    $additionalMedia['additionalMedia']->_value->info->_value = $info_rec;

    if (verbose) {
      echo "additionalMedia = " . print_r($additionalMedia, true) . "\n";
      echo "additionalMedia = " . print_r($obj->obj2xmlNs($additionalMedia), true) . "\n";
    }


    $xml_text = $obj->obj2xmlNs($additionalMedia);
    fwrite( $recfile, strlen($xml_text) );
    fwrite( $recfile, "\n");
    fwrite( $recfile, $xml_text );
    fwrite( $recfile, "\n");


    $unescaped = pg_unescape_bytea($row['data'] );
    echo $row['lokalid'] . ' lendata=' . mb_strlen( $unescaped, 'ISO-8859-1' ) . "\n";
    fwrite( $recfile, mb_strlen($unescaped, 'ISO-8859-1') );
    fwrite( $recfile, "\n");
    fwrite( $recfile, $unescaped );
    fwrite( $recfile, "\n");
    if (verbose) {
      echo "________________________\n";
    }
  }
}


catch(Exception $e) {
  echo "ERROR," . $e->__toString() . "\n";
  exit(1);
}

//*
//* Local variables:
//* tab-width: 2
//* c-basic-offset: 2
//* End:
//* vim600: sw=2 ts=2 fdm=marker expandtab
//* vim<600: sw=2 ts=2 expandtab
//*/
