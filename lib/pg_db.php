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

  public function request($params, $requestMethod){

    // TODO: Check if method exists
    $this->$requestMethod($params);

    if ( $error=$this->get_error() )
      verbose::log(FATAL," OCI error: ".$error);

    $ids = array();
    while ( $row = $this->get_row() ) {
      $ids[]= $row;
    }
    return $ids;
  }

  private function ADHLRequest($params){
    $this->bind("adhl_request",$params['lid'],SQLT_INT);

    $numRecords= isset($params['numRecords']) ? $params['numRecords'] : 5;

    $this->bind("adhl_request",$numRecords,SQLT_INT);


    $foo = "select lokalid as lid, count(*) as count from laan where laanerid in (select laanerid from laan where lokalid=$1 order by laan_i_klynge desc limit 500 ) and lokalid != $1 group by lokalid order by count desc limit $2";
    $query =  "select distinct on (laan.lokalid) laan.lokalid as lid, laan.laant_pa_bibliotek as lok from ($foo) as lids left join laan on lid=lids.lid where lokalid in (select lid from ($foo) as foo) group by lok, laan.lokalid limit $2";

    $this->query($query);
  }

  private function topADHLRequest($params){
    $numRecords= isset($params['numRecords']) ? $params['numRecords'] : 10;
    define('PG_TABLE', 'laan');
    $this->bind("top_ten",$numRecords, SQLT_INT);
    $query = 'select lokalid as lid, count(lokalid) as count from '.PG_TABLE.' where laan_i_klynge in( select distinct laan_i_klynge from '.PG_TABLE.' order by laan_i_klynge desc limit $1 ) group by lid order by count desc';

    $this->query($query);
  }


  function bind($name,$value,$type=SQLT_CHR) {
    $this->pg->bind($name, $value, -1, $type);
  }

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

