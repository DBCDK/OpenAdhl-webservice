<?php

/*
brief \wrapper for pg_database_class
 */
class pg_db {
// member to hold instance of pg_database_class
  private $pg;
  public $watch;


  // constructor
  function pg_db($connectionstring, $watch) {
    $this->pg=new pg_database($connectionstring);

    try {
      $this->pg->open();
    }
    catch (Exception $e) {
      verbose::log(ERROR,$e->__toString());
    }
    if ( $watch ) {
      $this->watch = $watch;
      $watch->start("POSTGRES");
    }
  }

  /** Generic request method
   * @param $params
   * @param $requestMethod
   * @return array
   * @throws Exception
   */
  public function request($params, $requestMethod){

    if (!method_exists($this, $requestMethod))
      throw new Exception($requestMethod . 'is not a valid request method');

    $this->$requestMethod($params);

    if ( $error=$this->get_error() )
      verbose::log(FATAL," OCI error: ".$error);

    $ids = array();
    while ( $row = $this->get_row() ) {
      $ids[]= $row;
    }
    return $ids;
  }

  /** Wrapper for AdhlRequest queries
   * @param $params
   */
  private function ADHLRequest($params){
    //$this->bind("adhl_request",$params['lid'],SQLT_INT);

    $lid = $params['lid'];
    $limit = isset($params['numRecords']) ? $params['numRecords'] : 5;
    // $this->bind("adhl_request",$numRecords,SQLT_INT);

    $query = "SELECT min(laant_pa_bibliotek) as lok, lokalid as lid, count(*) from laan where lokalid <> '%u' and laanerid in (SELECT distinct laanerid FROM laan WHERE lokalid = '%u') group by lokalid order by count(*) desc limit %u";
    $query = sprintf($query,$lid,$lid,$limit);


    $this->query($query);
  }

  /** Wrapper for topADHLRequest queries
   * @param $params
   */
  private function topADHLRequest($params){
    $numRecords= isset($params['numRecords']) ? $params['numRecords'] : 10;
    $this->bind("top_ten_request",$numRecords, SQLT_INT);
    $query = 'select lokalid as lid, laant_pa_bibliotek as lok, count(lokalid) as count from laan where laan_i_klynge in( select distinct laan_i_klynge from laan order by laan_i_klynge desc limit $1 ) group by lid, lok order by count desc limit $1';

    $this->query($query);
  }

  /** Bind parameters
   * @param $name
   * @param $value
   * @param int $type
   */
  function bind($name,$value,$type=SQLT_CHR) {
    $this->pg->bind($name, $value, -1, $type);
  }

  /** Set query
   * @param $query
   */
  function query($query) {

    try {
      $this->pg->set_query($query);
    }
    catch (Exception $e) {
      verbose::log(ERROR,$e->__toString());
      die($e->__toString());
    }

    try {
      $this->pg->execute();
    }
    catch (Exception $e) {
      verbose::log(ERROR,$e->__toString());
      die($e->__toString());
    }

  }

  /** return one row from db */
  function get_row() {
    return $this->pg->get_row();
  }

  /** destructor; disconnect from database */
  function __destruct() {
    if ( $this->watch )
      $this->watch->stop("POSTGRES");

    $this->pg->close();
  }

  /** get error from database-class */
  function get_error() {
    //return $this->oci->get_error_string();
    return false;
  }

}

