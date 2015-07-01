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
require_once("$startdir/OLS_class_lib/curl_class.php");
require_once("$startdir/OLS_class_lib/timer_class.php");
require_once("$startdir/OLS_class_lib/verbose_class.php");

/**
  * This script export data from an adhl postgres database.
  * Output is as described for the solr docs in schema.xml 
  */

/**
 * This function is used to write out the parameters which can be used for the program
 * @param str  The text string to be displayed in case of any errors
 */
function usage($str='') {
  global $argv;

  echo "$str\n\n";
  echo "Usage: \n\$ php $argv[0]\n";
  echo "\t-s <postgres server> Adress of the server on the form some.server.somewhere \n";
  echo "\t-u <postgres user> Name of the pg user \n";
  echo "\t-p <postgres users passwd> Password for the pg user \n";
  echo "\t-o <filename> the name of the output file (including path)\n";
  echo "\t-b Solr broend server\n";
  echo "\t-a Solr adhl server\n";
  echo "\t-v <logfile> verbose to file logfile\n";
  echo "\t-h help (shows this message) \n";
  exit;
}

/** \brief do_search
 *
http://solar-p02.dbc.dk:8080/solr/broend/select?q=1234567&rows=999&fl=unit.id+term.primaryLanguage&wt=phps&defType=edismax&stopwords=true&lowercaseOperators=true&group=true&group.main=true&group.field=fedoraPid
http://solar-p02.dbc.dk:8080/solr/broend/select?q=rec.id%3A25700872%7C870970&rows=999&fl=unit.id+term.primaryLanguage&wt=php&indent=true
 */
function do_search($search_string, $result_fields ) {
  global $curl;
  global $broend_server;
  $url_append = "/select?q=" . $search_string . "&rows=999&fl=" . $result_fields . 
    "&wt=phps&defType=edismax&stopwords=true&lowercaseOperators=true" .
    '&group=true&group.main=true&group.field=fedoraPid';
  // verbose::log(DEBUG, 'URL ' . $broend_server . $url_append );
  $get_result = $curl->get( $broend_server . $url_append );
  $result_array[] = @ unserialize( $get_result );
  // verbose::log(DEBUG, 'RES <' . print_r($result_array, true) . ">" );
  return $result_array;
}

$doverbose = '';
$broend_server = '';
$adhl_server = '';
$options = getopt('p:ho:s:u:v:b:a:');
if (array_key_exists('h', $options)) usage(); 
if (array_key_exists('s', $options)) $server = $options['s'];
if (array_key_exists('u', $options)) $user = $options['u'];
if (array_key_exists('p', $options)) $pass = $options['p'];
if (array_key_exists('o', $options)) $filename = $options['o'];
if (array_key_exists('b', $options)) $broend_server = $options['b'];
if (array_key_exists('a', $options)) $adhl_server = $options['a'];
if (array_key_exists('v', $options)) $doverbose = $options['v'];

if ( !isset($server) || !isset($user) || !isset($pass) || !isset($filename) ) {
    usage("Missing database/user/filename information\n");
}

if ( $doverbose != '' ) {
  verbose::open( $doverbose, 'DEBUG');
}

$curl = new curl();
$obj = new objconvert(); // TODO : bruges denne til noget ?
try {
  $db=new pg_database("host=" . $server . " user=" . $user . " password=" . $pass );
  $db->open();
  $recfile = fopen("$filename", "w");
  if ( ! $recfile ) {
    echo "ERROR, could not open file " . $filename . "\n";
    exit(1);
  }
  $se_res = do_search('1234567', "unit.id+term.primaryLanguage+term.type");
  $curl_status = $curl->get_status('http_code');
  if ( $curl_status != 200  ) {
    echo "Could not reach solr server : " . print_r( $curl_status, true) . "\n";
    return 1;
  }

  /*
     Here it comes :
     calc date for oldest loan (current month - 12)
     Find unique local_id's and create an array with bibliographical data
     Foreach loaner do
        Make a loan doc (without end)
        get the loaners loan and create a complete doc for each 
        close the loan doc
     done
     */
  $totalrun = 'y';
  $today = date('Y-m-d');
  $start_date = '2014-06-19';

  echo "XXX Pre select : " . date('c') . "\n";
  $sql = "SELECT DISTINCT laanerid, koen, to_char(foedt, 'YYYY') as foedt, to_char(dato, 'YYYY') as ldato, lokalid, laant_pa_bibliotek FROM laan ";
  $sql = "SELECT DISTINCT laanerid, koen, lokalid, laant_pa_bibliotek FROM laan ";
  $sql = "SELECT DISTINCT laanerid, koen, to_char(foedt, 'YYYY') as foedt, lokalid, laant_pa_bibliotek FROM laan ";
  if ( $totalrun == 'y') {
    echo "Extracting adhl data from the time the pyramids was build to now ($today)\n";
    $sql .= "WHERE lokalid in " .
      " (select lokalid from laan group by lokalid having count(*) >= 2) ORDER BY laanerid";
  } else {
    echo "Extracting adhl data from $start_date to now ($today)\n";
    $sql .= "WHERE dato > to_date(:bind_date, 'YYYY-MM-DD') AND lokalid in " .
      " (select lokalid from laan WHERE dato > to_date(:bind_date, 'YYYY-MM-DD') group by lokalid having count(*) >= 2) ORDER BY laanerid";
    $db->bind('bind_date', $start_date);
  }
  echo "<$sql>\n<$start_date>\n";
  $db->set_query($sql);
  $db->execute();
  $bibls = array();
  echo "XXX Pre finde : " . date('c') . "\n";

  // create a temp sequence for unique id's
  $unique_id = 1;

  $current_loaner = '';
  $vufl = $db->get_all_rows();
  echo "XXX post finde : " . date('c') . "\n";
  $solrop = 0;
  $laanloop = 0;
  $laantop = 0;
  foreach ( $vufl as $row ) {
    // echo "_________________________\n";
    // echo "VUFL-row : " . print_r($row, true) . "\n";
    $laanloop += 1;
    $laantop += 1;
    if ( $laanloop >= 500000 ) {
      echo "loopy : " . date('c') . 'solrs :' . $solrop . ' laans : ' . $laantop . "\n";
      $laanloop = 0;
    }
    $nine_number = substr($row['lokalid'], 0, 1) == '9';
    if ($nine_number) {
        $make_a_search = ! isset($nines[ $row['lokalid'] ][ $row['laant_pa_bibliotek'] ]);
    } else {
        $make_a_search = ! isset($lokalids[$row['lokalid']]);
    }
    if ($make_a_search) {
      $solrop += 1;
      if (! $nine_number) {
        $search = 'rec.id:' . $row['lokalid'];
      } else {
        $search = 'rec.id:' . $row['lokalid'] . "|" . $row['laant_pa_bibliotek'];
      }
      // $returns = "unit.id+dkcclterm.sp+display.titleFull+display.creator";
      // $returns = "unit.id+term.primaryLanguage+term.type+display.titleFull+display.creator+dkcclterm.sp";
      $returns = "rec.id+unit.id+term.primaryLanguage+term.type+display.titleFull+display.creator+dkcclterm.sp";
      $se_res = do_search($search, $returns);
      // echo "findetid : " . date('c') . "\n";
    // TODO
      // echo 'SEARCH TXT ' . print_r($search, true) . "\n";
      // echo 'SEARCH RES ' . print_r($se_res, true) . "\n";
      $res_ptr = $se_res[0]['response'];
      if ($res_ptr['numFound'] > 0 ) {
        // Vi skal have dannet en PID, dvs <katalog>:<lokalid>. Det gør vi ved at kigge på rec.id = <lokalid>|<bibliotek>
        // hvis der er et 7* bibliotek skal det være 870970-basis:<lokalid>
        // ellers første <bibliotek>-katalog:<lokalid>
        $newpid = "";
        $potentiel = "";
        // echo "DOCS:" . print_r($res_ptr['docs'], true) . "\n";
        foreach( $res_ptr['docs'] as &$doctmp ) {
          foreach( $doctmp['rec.id'] as &$recidtmp) {
            // echo "RECIDTMP:" . print_r($recidtmp, true) . "\n";
            if ( strstr($recidtmp, '|') ) {
              // echo "strstr:" . print_r(strstr($recidtmp, '|'), true) . "\n";
              $tempo = explode('|', $recidtmp);
              // echo "TEMPO:" . print_r($tempo, true) . "\n";
              // $vuf1 = strpos( $tempo[1], '7') === 0;
              // $vuf2 = $tempo[1] === '870970';
              // $vuf2 = $tempo[1] === '870970';
              // echo "JUHUU 1 7:" . $vuf1 . " 870970 : " . $vuf2 . "\n";
              if ( strpos( $tempo[1], '7') === 0 || $tempo[1] === '870970' ) {
                // echo "JUHUU 2 9:" . strpos($tempo[0], '9') === 0 . "\n";
                if ( strpos($tempo[0], '9') === 0  ) {
                  $newpid = $tempo[1] . '-katalog:' . $tempo[0];
                } else {
                  $newpid = '870970-basis:' . $tempo[0];
                }
                break 2; // Found a 870970 or 7? rec and that is just fine
              } else {
                $potentiel = $tempo[1] . '-katalog:' . $tempo[0];
              }
            }
          }
        }
        // echo "SETTING NEWPID:" . print_r($newpid, true) . " POTEN:" . print_r($potentiel, true) . "\n";
        if ( $newpid === "" ) {
          $se_res[0]['response']['docs'][0]['newpid'] = $potentiel;
        } else {
          $se_res[0]['response']['docs'][0]['newpid'] = $newpid;
        }

        if ($nine_number) {
          $nines[ $row['lokalid'] ][ $row['laant_pa_bibliotek'] ] = $se_res[0]['response']['docs'][0];
        } else {
          $lokalids[$row['lokalid']] = $se_res[0]['response']['docs'][0];
        }
      }
    }
    $res_ptr = $se_res[0]['response'];
    // TODO
    // echo 'THIS IS res_ptr : ' . print_r($res_ptr, true) . "\n";
    if ($res_ptr['numFound'] > 0 || ! $make_a_search) {
      // hvis lånerskifte/start på skidtet
      if ($current_loaner == '' || $current_loaner != $row['laanerid']) {
        if ($current_loaner == '' ) {
          fwrite($recfile, "<add>\n<doc>\n");
        } else {
          fwrite($recfile, "</doc>\n<doc>\n");
        }
        $current_loaner = $row['laanerid'];
        fwrite($recfile, '  <field name="unique_id">'    . $unique_id . "</field>\n");
        $unique_id++;
        fwrite($recfile, '  <field name="laanerid">'     . $row['laanerid']           . "</field>\n");
        fwrite($recfile, '  <field name="bibliotek">'    . $row['laant_pa_bibliotek'] . "</field>\n");
        fwrite($recfile, '  <field name="koen">'         . $row['koen']               . "</field>\n");
        fwrite($recfile, '  <field name="foedt">'        . $row['foedt']              . "</field>\n");
        fwrite($recfile, '  <field name="parent_field">' . '1'                        . "</field>\n");
      }
      // put a book (det skal vi sku da altid !)
      if ($nine_number) {
        $doc_ptr = $nines[ $row['lokalid'] ][ $row['laant_pa_bibliotek'] ];
      } else {
        $doc_ptr = $lokalids[$row['lokalid']];
      }
      fwrite($recfile, "    <doc>\n");
      fwrite($recfile, '    <field name="unique_id">'    . $unique_id          . "</field>\n");
      $unique_id++;
      fwrite($recfile, '    <field name="lokalid">'      . $row['lokalid']                     . "</field>\n");
      fwrite($recfile, '    <field name="pid">'      . $doc_ptr['newpid']                 . "</field>\n");
      fwrite($recfile, '    <field name="unit_id">'      . $doc_ptr['unit.id']                 . "</field>\n");
      fwrite($recfile, '    <field name="laan_date">'    . $row['ldato']                        . "</field>\n");

      if ( isset($doc_ptr['term.type']) ) {
        fwrite($recfile, '    <field name="mattype">'      . strtolower($doc_ptr['term.type'][0])               . "</field>\n");
      }
      if ( isset($doc_ptr['display.titleFull']) ) {
        fwrite($recfile, '    <field name="title">'      . htmlspecialchars($doc_ptr['display.titleFull'][0])               . "</field>\n");
      }
      if ( isset($doc_ptr['display.creator']) ) {
        fwrite($recfile, '    <field name="author">'      . htmlspecialchars($doc_ptr['display.creator'][0])               . "</field>\n");
      }
      if ( isset($doc_ptr['term.primaryLanguage']) ) {
        $sprstr = '';
        if ( $doc_ptr['term.primaryLanguage'] == 'mul' ) {
          if ( is_array($doc_ptr['dkcclterm.sp']) ) {
            foreach ( $doc_ptr['dkcclterm.sp'] as $lillesp ) {
              $sprstr = $sprstr . " " . $lillesp;
            }
          } else {
            $sprstr = $doc_ptr['dkcclterm.sp'];
          }
        } else {
          if ( is_array($doc_ptr['term.primaryLanguage']) ) {
            foreach ( $doc_ptr['term.primaryLanguage'] as $lillesp ) {
              $sprstr = $sprstr . " " . $lillesp;
            }
          } else {
            $sprstr = $doc_ptr['term.primaryLanguage'];
          }
        }
        fwrite($recfile, '    <field name="sprog">'    . trim($sprstr) . "</field>\n");
      }
      fwrite($recfile, "  </doc>\n");
    } else {
      echo 'WARN : did not find lid <' . $row['lokalid'] . '> library <' . $row['laant_pa_bibliotek'] . '> search <' . $search . ">\n";
    }
  }
  fwrite($recfile, "</doc>\n</add>");
  echo "XXX pos finde : " . date('c') . "\n";
    // echo print_r($lokalids, true) . "\n";
  echo "XXX do done : " . date('c') . "\n";

  /*
  $watch = new stopwatch();
  $watch->format('screen');
    $watch->start('a');
    $watch->stop('a');
    echo $watch->dump();
    echo "...\n";
    */
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
