<?php
/** include for webservice server */
require_once("OLS_class_lib/webServiceServer_class.php");

class ADHLServer extends webServiceServer {

  public static $cache = true;

  public function __construct($inifile) {
    parent::__construct($inifile);
    $this->watch->start("openadhl");
  }


  /** ADHLRequest
   * @param $params
   * @return stdClass Response formed as an object
   */
  public function ADHLRequest($params) {

    $records=$this->ADHLRequestMethod($params);

    return $this->response($records, 'adhlResponse');
  }

  /** \brief The function handling the request
   * @params $params; the request given from soapclient or derived from url-query
   * @return array with response in abm_xml format
   */
  public function ADHLRequestMethod($params) {
    if (!isset ($params->id->_value->pid->_value)) {
      return array();
    }

    $cachekey = 'ADHLRequest_';
    $cachekey = helpFunc::cache_key($params, $cachekey);

    if ($cache = $this->getCache($cachekey, $params)) {
      return $cache;
    }

    $pid = $params->id->_value->pid->_value;
    $query_params = helpFunc::get_lid_and_lok_from_pid($pid);
    if (isset($params->numRecords->_value)) {
      $query_params['numRecords'] = $params->numRecords->_value;
    }
    $ids = $this->request($query_params, 'AdhlRequest');

    $this->setCache($cachekey, $ids);

    return !isset($ids) || empty($ids) ? array() : $ids;
  }


  /** topTenRequest
   * @param $params
   * @return stdClass
   */
  public function topTenRequest($params) {

    $records=$this->topTenRequestMethod($params,$this->watch);
    print_r($records);
    return $this->response($records, 'topTenResponse');
  }


  /** The function handling the request
   * @param $params
   * @param $watch
   * @return array
   */
  public function topTenRequestMethod($params,$watch) {
    $query_params = array();

    if (isset($params->numRecords->_value))
      $query_params['numRecords'] = $params->numRecords->_value;

    $ids = $this->request($query_params, 'topADHLRequest');

    return ( !isset($ids) || empty($ids) ) ? array() : $ids;
  }

  /** Generic request method. Makes a pg connection and returns the result
   * @param $params
   * @param $request
   * @return array
   */
  private function request($params, $request) {
    $connection = $this->config->get_value("CONNECTION");
    $db = new pg_db($connection, $this->watch);
    return $db->request($params, $request);

  }

  /** Generic response processor. Wraps a request result into a valid response object
   * @param $records
   * @param $responseWrapper
   * @return stdClass
   */
  private function response($records, $responseWrapper){
    $response = new stdClass();

    // prepare response-object
    $response->$responseWrapper->_namespace="http://oss.dbc.dk/ns/adhl";

    foreach( $records as $record )
      $response->$responseWrapper->_value->pid[]->_value=helpFunc::convert_lid_and_lok_to_pid($record);
    return $response;
  }

  /** Get cache if it exists
   * @param $cachekey
   * @param $params
   * @return array|bool|null|string
   */
  private function getCache($cachekey, $params){
    if (self::$cache && class_exists('cache')){
      verbose::log(TRACE,"request-key::".$cachekey);

      if ( $ret = cache::get($cachekey) ) {
        verbose::log(TRACE,"cachehit: ".$cachekey);
        return $ret;
      }
    }
    return null;
  }

  /** Set Cache
   * @param $cachekey
   * @param $value
   */
  private function setCache($cachekey, $value){
    if (!isset($value) || empty($value) || !class_exists('cache') || !self::$cache){
      return;
    }
    cache::set($cachekey,$value);
  }

  /** \brief Echos config-settings
   *
   */
  public function show_info() {
    echo "<pre>";
    echo "version             " . $this->config->get_value("version", "setup") . "<br/>";
    echo "log                 " . $this->config->get_value("logfile", "setup") . "<br/>";
    echo "</pre>";
    die();
  }


  public function __destruct() {
    $this->watch->stop("openadhl");
    verbose::log(TIMER, $this->watch->dump());
  }

}
