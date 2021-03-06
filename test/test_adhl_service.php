<?php

set_include_path(
    get_include_path() . PATH_SEPARATOR .
    dirname(__FILE__) . '/../' . PATH_SEPARATOR .
    dirname(__FILE__) . '/../OLS_class_lib/simpletest' . PATH_SEPARATOR .
    __DIR__ . '/..');

require_once( 'unit_tester.php');
require_once( 'reporter.php');
require_once( 'xml.php');

class TestADHLService extends UnitTestCase {

  function setUp() {
    /** include ADHL service classes */
    require_once("lib/ADHLServer.php");
    require_once("lib/pg_db.php");
    require_once("lib/helpFunc.php");

    // Turn of cache
    ADHLServer::$cache = false;

    // This constant may not have been defined
    if (!defined('SQLT_INT')) {
      define('SQLT_INT', 3);
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
    $params->id->_value->pid = $pid;

    var_dump($params);

    $server = new ADHLServer(dirname(__FILE__) . "/../adhl.ini");

    $result = $server->ADHLRequestMethod($params, 500);
    var_dump($result);

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
      2 => array
        (
        'lid' => '21131598',
        'lok' => '786000',
      ),
      3 => array
        (
        'lid' => '21932108',
        'lok' => '758000',
      ),
      4 => array
        (
        'lid' => '23534819',
        'lok' => '710100',
      ),
    );


    // Test if request gives the expected response
    $this->assertEqual($result, $expected_result);
    // Test empty request
    $params->id->_value = null;
    $result = $server->ADHLRequestMethod($params, null);

    $this->assertEqual($result, array());


    // Test Malformed request
    unset($params->id);
    $result = $server->ADHLRequestMethod($params, null);

    $this->assertEqual($result, array());
  }

  public function testTopTenRequestFlow() {

    //TODO: Extend mockup to handle top ten requests
    require_once('pg_database_mockup.php');

    // Create a request object
    $params = new stdClass();
    $params->numRecords = new stdClass();
    $params->numRecords->_namespace = 'http://oss.dbc.dk/ns/adhl';
    $params->numRecords->_value = 5;


    $server = new ADHLServer(dirname(__FILE__) . "/../adhl.ini");

    $result = $server->topTenRequestMethod($params, null);

    $expected_result = array
      (
      0 => array
        (
        'lid' => '28088078',
        'lok' => '710100',
        'count' => '3936'
      ),
      1 => array
        (
        'lid' => '28186061',
        'lok' => '710100',
        'count' => '2705'
      ),
      2 => array
        (
        'lid' => '27670806',
        'lok' => '775100',
        'count' => '2677'
      ),
      3 => array
        (
        'lid' => '28186061',
        'lok' => '775100',
        'count' => '2667'
      ),
      4 => array
        (
        'lid' => '27925715',
        'lok' => '710100',
        'count' => '2411'
      ),
    );

    // Test if request gives the expected response
    $this->assertEqual($result, $expected_result);


    // Test empty response
    $params = new stdClass();
    $result = $server->topTenRequestMethod($params, null);

    $this->assertTrue(count($result) == 10, 'Correct number of results, when request is empty');
  }

  public function testPG_DB() {
    // This constant has not been set when running the test
    require_once('pg_database_mockup.php');
    require_once('OLS_class_lib/timer_class.php');

    $w = new stopwatch('', ' ', '', '%s:%01.3f');
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
      2 => array
        (
        'lid' => '21131598',
        'lok' => '786000',
      ),
      3 => array
        (
        'lid' => '21932108',
        'lok' => '758000',
      ),
      4 => array
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

  public function testHelpFunc() {
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

$test = new TestADHLService();
$test->run(new XmlReporter());


