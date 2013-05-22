<?php

set_include_path(
  get_include_path() . PATH_SEPARATOR .
  dirname(__FILE__) . '/../' . PATH_SEPARATOR .
  dirname(__FILE__) . '/../OLS_class_lib/simpletest' .  PATH_SEPARATOR .
  __DIR__ . '/..');

require_once('autorun.php');
//require_once('../server.php');

class TestADHLRequest extends UnitTestCase {

  function setUp(){
    /** include ADHL service classes */
    require_once("lib/ADHLServer.php");
    require_once("lib/pg_db.php");
    require_once("lib/helpFunc.php");

    // Turn of cache
    ADHLServer::$cache = false;

    // This constant may not have been defined
    if (!defined('SQLT_INT')){
      define ('SQLT_INT', 3);
    }
  }

  function testADHLRequestFlow() {

    require_once('pg_database_mockup.php');

    // Create a request object
    $pid = new stdClass();
    $pid->_namespace = 'http://oss.dbc.dk/ns/adhl';
    $pid->_value = '870970-basis:04231066';

    $params = new stdClass();
    $params->id = new stdClass();
    $params->id->_namespace = 'http://oss.dbc.dk/ns/adhl';
    $params->id->_value->pid= $pid;


    $server = new ADHLServer("../adhl.ini");

    $result = $server->ADHLRequestMethod($params, null);

    // Expected response
    $expected_result = array
    (
      0 => array
      (
        'lid' => '06977731',
        'lok' => '756100',
      ),

      1 => array
      (
        'lid' => '07303033',
        'lok' => '737600',
      ),

      2 => Array
      (
        'lid' => '21131598',
        'lok' => '786000',
      ),

      3 => Array
      (
        'lid' => '21932108',
        'lok' => '758000',
      ),

      4 => Array
      (
        'lid' => '23534819',
        'lok' => '710100',
      ),
    );


    // Test if request gives the expected response
    $this->assertEqual($result, $expected_result);


    // Test empty request
    $params->id->_value= null;
    $result = $server->ADHLRequestMethod($params, null);

    $this->assertEqual($result, array());


    // Test Malformed request
    unset($params->id);
    $result = $server->ADHLRequestMethod($params, null);

    $this->assertEqual($result, array());

  }

  public function testPG_DB(){
    // This constant has not been set when running the test
    require_once('pg_database_mockup.php');
    require_once('OLS_class_lib/timer_class.php');

    $w =  new stopwatch('', ' ', '', '%s:%01.3f');
    $pg = new pg_db('test', $w);
    $params = array(
      'lid' => '04231066',
      'numRecords' => 5,
    );
    $result = $pg->request($params, 'ADHLRequest');
    $expected_result = array
    (
      0 => array
      (
        'lid' => '06977731',
        'lok' => '756100',
      ),

      1 => array
      (
        'lid' => '07303033',
        'lok' => '737600',
      ),

      2 => Array
      (
        'lid' => '21131598',
        'lok' => '786000',
      ),

      3 => Array
      (
        'lid' => '21932108',
        'lok' => '758000',
      ),

      4 => Array
      (
        'lid' => '23534819',
        'lok' => '710100',
      ),
    );

    $this->assertEqual($result, $expected_result, 'Excepted results returned');

    // Test numRecords
    $params['numRecords'] = 3;
    $result = $pg->request($params, 'ADHLRequest');

    $this->assertEqual(count($result), 3, 'Correct number of results returned');

    //Test default numRecords
    unset($params['numRecords']);
    $result = $pg->request($params, 'ADHLRequest');

    $this->assertEqual(count($result), 5, 'Correct number of results returned');

    // Test invalid lid
    $params = array(
      'lid' => 'invalid_pid',
      'numRecords' => 5,
    );
    $result = $pg->request($params, 'ADHLRequest');

    $this->assertEqual($result, array(), 'Result is %s');

  }


  public function testHelpFunc(){
    /* convert_lid_and_lok_to_pid */

    //empty
    $id = null;
    $result = helpFunc::convert_lid_and_lok_to_pid($id);
    $expected_result = null;

    $this->assertEqual($result, $expected_result, 'Empty id returns null');

    //missing lok
    $id = array(
      'lid' => 'test12345'
    );
    $result = helpFunc::convert_lid_and_lok_to_pid($id);
    $expected_result = '870970-basis:test12345';

    $this->assertEqual($result, $expected_result, 'Only lid returns pid from basic');


    //basis
    $id = array(
      'lid' => 'test12345',
      'lok' => '7lok'
    );
    $result = helpFunc::convert_lid_and_lok_to_pid($id);
    $expected_result = '870970-basis:test12345';

    $this->assertEqual($result, $expected_result, 'lok starting with 7 returns pid from basic');

    // katalog
    $id = array(
      'lid' => 'test12345',
      'lok' => '8lok'
    );
    $result = helpFunc::convert_lid_and_lok_to_pid($id);
    $expected_result = '8lok-katalog:test12345';

    $this->assertEqual($result, $expected_result, 'lok not starting with 7 returns pid from katalog');

    //get_lid_and_lok_from_pid

    // empty
    $pid = null;
    $result = helpFunc::get_lid_and_lok_from_pid($pid);
    $expected_result = null;

    $this->assertEqual($result, $expected_result, 'empty pid returns null');




    // malformed
    $pid = 'notapid:test';
    $result = helpFunc::get_lid_and_lok_from_pid($pid);
    $expected_result = null;

    $this->assertEqual($result, $expected_result, 'malformed pid returns null');


    // basic
    $pid = '870970-basis:test12345';
    $result = helpFunc::get_lid_and_lok_from_pid($pid);
    $expected_result = array(
      'lid' => 'test12345',
      'lok' => '870970'
    );

    $this->assertEqual($result, $expected_result, 'pid from basic returns correct lid and lok');


    // katalog

    $pid = '8lok-katalog:test12345';
    $result = helpFunc::get_lid_and_lok_from_pid($pid);
    $expected_result = array(
      'lid' => 'test12345',
      'lok' => '8lok'
    );

    $this->assertEqual($result, $expected_result, 'pid from katalog returns correct lid and lok');



  }


}


