<?php
/** \brief
 *
 * This file is part of OpenLibrary.
 * Copyright © 2009, Dansk Bibliotekscenter a/s,
 * Tempovej 7-11, DK-2750 Ballerup, Denmark. CVR: 15149043
 *
 * OpenLibrary is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * OpenLibrary is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with OpenLibrary.  If not, see <http://www.gnu.org/licenses/>.
 */


/** include for caching */
/************ TODO overvej om caching er relevant - spørg FVS
require_once('OLS_class_lib/memcache_class.php');
**************/
/** include for webservice server */
require_once('OLS_class_lib/webServiceServer_class.php');


class ADHLServer extends webServiceServer {
  /************ TODO
  public static $cache = true;
  ***********/
  protected $solr_server = '';
  protected $curl;
  protected $default_response_recs;
  protected $minimum_loans;

  public function __construct($inifile) {
    parent::__construct($inifile);
    verbose::log(TRACE, 'ini ' . $inifile );
    /************ TODO
    $host = $this->config->get_value('cache_host', 'setup');
    $port = $this->config->get_value('cache_port', 'setup');
    $expire = $this->config->get_value('cache_expire', 'setup');
    $this->cache = new cache($host, $port, $expire);
    ******************/
    $this->solr_server = $this->config->get_value( 'solr_server', 'solr' );
    $this->default_response_recs = $this->config->get_value( 'default_response_recs', 'search' );
    $this->minimum_loans = $this->config->get_value( 'minimum_loans', 'search' );
    $this->curl = new curl();
    verbose::log(TRACE, 'ini done ' . $inifile );
  }

  public function __destruct() {
    $this->watch->stop('openadhl');
    verbose::log(TIMER, $this->watch->dump());
  }


  protected function do_search($search_string ) {
    verbose::log(DEBUG, 'URL ' . $search_string );
    $get_result = $this->curl->get( $search_string );
    verbose::log(DEBUG, 'RES <' . $get_result . '>' );
    $result_array[] = @ unserialize( $get_result );
    verbose::log(DEBUG, 'RES <' . $result_array . '>' );
    $curl_status = $this->curl->get_status();
    verbose::log(DEBUG, 'CURL_STATUS=' . print_r($curl_status, true) . '>');

    if ( $curl_status['errno'] != 0  ) {
      verbose::log(ERROR, 'adhl:: adhl(...); - could not perform search - curl status : ' . print_r( $curl_status, true) );
      $retur['value'] = 'could not perform search';
      $retur['res'] = 'fail';
    } else {
      $retur['value'] = $result_array;
      $retur['res'] = 'ok';
    }
    return $retur;
  }

  public function createResponseData($pivots) {
    verbose::log(DEBUG, 'pivots : ' . print_r($pivots, true) . PHP_EOL);
    $tempo = array();
    foreach ($pivots as $tempivots) {
      unset($tempo);
      $tempo['pid']->_value = $tempivots['value'];
      $tempo['title']->_value = $tempivots['pivot'][0]['value'];
      $tempo['creator']->_value = $tempivots['pivot'][0]['pivot'][0]['value'];
      $res[] = $tempo;
    }
    return $res;
  }

  public function ADHLRequestMethod($params) {
    verbose::log(DEBUG, 'Call params : ' . print_r($params, true) . PHP_EOL);
    if (isset($params->numRecords->_value)) {
      $numcount = $params->numRecords->_value;
    } else {
      $numcount = $this->default_response_recs;
    }
    $work = array();
    $work = is_array($params->id->_value->pid) ? $params->id->_value->pid : array($params->id->_value->pid);

    // Now we start to build the solr query.
    $querystring = '';
    $negatestring = '';
    foreach ($work as $wrk) {
      if ( $querystring == '') {
        $querystring = $wrk->_value;
        $negatestring = '-pid:"' . $wrk->_value . '"';
      } else {
        $querystring .= ',' . $wrk->_value;
        $negatestring .= ' AND -pid:"' . $wrk->_value . '"';
      }
    }
    verbose::log(DEBUG, 'QUERYSTR : ' . print_r($querystring, true) . PHP_EOL); // the query string for the given PID's
    verbose::log(DEBUG, 'NEGASTR : ' . print_r($negatestring, true) . PHP_EOL); // filter query with the given PID's since we don't want them in our result

    // solr call string
    // This gets all the loans that loaners found has lent
    $call_string = '{!child of="parent_field:1"}{!join from=laanerid to=laanerid}laanerid:';

    // This gives the loaners that have lent the given pid's - goes down to ENDHERE
    $call_string .= '{!join from=laanerid to=laanerid}';

    // With limitations
    $sex = $age = 'N'; // TODO
    if ($sex === 'Y' || $age === 'Y') {
      // add stuff to include such not yet implemented but it belongs here
      // ex :
      $call_string .= 'koen=k AND ';
      $call_string .= 'foedt:[' . $lowage . ' TO ' . $highage . '] AND ';
    }

    $call_string .='{!parent which="parent_field:1"}';
    $call_string .= '{!terms f=pid}' . $querystring;
    // ENDHERE

    $pivotmm = '&rows=0&wt=phps&indent=true&facet=true&facet.pivot=pid,title,author&facet.pivot.mincount=' . $this->minimum_loans . '&facet.limit=' . $numcount; 
    $filterq = '&fq=' . urlencode($negatestring);

    $finalestr = $this->solr_server . '/select?q=' . urlencode($call_string) . $pivotmm . $filterq;
    $search_res = self::do_search($finalestr);

    $res = array();
    $tempo = array();
    if ( $search_res['res'] == 'fail' ) {
      $tempo['error']->_value = $search_res['value'];
      $res[] = $tempo;
      return $res;
    } else {
      if (is_null($search_res['value'][0]['facet_counts']['facet_pivot']['pid,title,author']) ) {
        $tempo = array();
        $tempo['error']->_value = 'No results';
        $res[] = $tempo;
      } else {
        return $this::createResponseData($search_res['value'][0]['facet_counts']['facet_pivot']['pid,title,author']);
      }
    }

  }

  public function topTenRequest($params) {
    verbose::log(DEBUG, 'Call params : ' . print_r($params, true) . PHP_EOL);
    if (isset($params->numRecords->_value)) {
      $numcount = $params->numRecords->_value;
    } else {
      $numcount = $this->default_response_recs;
    }
    $finalestr = $this->solr_server . '/select?q=*:*' . '&rows=0&wt=phps&indent=true&facet=true&facet.pivot=unit_id,title,author&facet.limit=' . $numcount;
    $search_res = self::do_search($finalestr);

    $res = array();
    $tempo = array();
    if ( $search_res['res'] == 'fail' ) {
      $tempo['error']->_value = $search_res['value'];
      $res[] = $tempo;
    } else {
      if (is_null($search_res['value'][0]['facet_counts']['facet_pivot']['unit_id,title,author']) ) {
        $tempo = array();
        $tempo['error']->_value = 'No results';
        $res[] = $tempo;
      } else {
        $res = $this::createResponseData($search_res['value'][0]['facet_counts']['facet_pivot']['unit_id,title,author']);
      }
    }

    return $this->response($res, 'topTenResponse');

  }

  public function adhlRequest($params) {

    $records = $this->ADHLRequestMethod($params);
    return $this->response($records, 'adhlResponse');
  }

  public function pingRequest($params) {

    $records = $this->ADHLRequestMethod($params);
    return $this->response($records, 'pingResponse');
  }

  /** Generic response processor. Wraps a request result into a valid response object
   * @param $records
   * @param $responseWrapper
   * @return stdClass
   */
  private function response($records, $responseWrapper) {
    $response = new stdClass();

    // prepare response-object
    $response->$responseWrapper->_namespace = 'http://oss.dbc.dk/ns/adhl';

    foreach ($records as $record) {
      $sumup['recorddata'][]->_value = $record;
    }
    $response->$responseWrapper->_value = $sumup;
    verbose::log(DEBUG, 'response : ' . print_r($response, true) . PHP_EOL);
    return $response;
  }

}

// initialize server
$server = new ADHLServer('adhl.ini');

// handle the request
$server->handle_request();

//*
//* Local variables:
//* tab-width: 2
//* c-basic-offset: 2
//* End:
//* vim600: sw=2 ts=2 fdm=marker expandtab
//* vim<600: sw=2 ts=2 expandtab
//*/

