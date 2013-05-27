<?php


/** Mockup class for testing */

class pg_database
{

  private $query;
  private $params = array();
  private $result = array();

  public function __construct($connectionstring)
  {

  }



  public function execute($statement_key=null)
  {
    switch($this->query){
      case 'adhl_request' :
        $this->set_adhl_result();
        break;
      case 'top_ten_request' :
        $this->set_top_ten_results();
        break;
    }


  }

  private function set_adhl_result(){

    if ($this->params[0] == '04231066' && isset($this->params[1])){
      $this->result = array
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

      array_splice($this->result, $this->params[1], 5-$this->params[1]);
    }
    $this->params = array();
  }

  private function set_top_ten_results(){
      $this->result = array
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

        5 => array
        (
          'lid' => '28088078',
          'lok' => '775100',
          'count' => '2365'
        ),
        6 => array
        (
          'lid' => '27670806',
          'lok' => '775100',
          'count' => '2677'
        ),

        7 => array
        (
          'lid' => '28186061',
          'lok' => '775100',
          'count' => '2667'
        ),

        8 => array
        (
          'lid' => '27925715',
          'lok' => '710100',
          'count' => '2411'
        ),

        9 => array
        (
          'lid' => '28088078',
          'lok' => '775100',
          'count' => '2365'
        )

      );

      array_splice($this->result, $this->params[0], 10-$this->params[0]);
    $this->params = array();
  }

  public function open(){
    // Do nothing
  }
 public function close(){
    // Do nothing
  }

  public function stop(){
    // Do nothing
  }

  public function set_query($query){
    //$this->query = $query;
  }

  public function bind($table, $value, $constant, $type) {
    $this->query = $table;
    $this->params[] = $value;
  }

  public function get_row(){
    if ($value = current($this->result)){
      next($this->result);
    }
    return $value;
  }


}

?>
