<?php



/** \brief
 *  Class holds static functions used for handling the request.
 *  only functions get_request and get_lid_and_lok is called from outside the class;
 *  other functions are private
 */
class helpFunc {
  public static function cache_key($params,&$cachekey) {
    foreach( $params as $obj=>$var ) {
      if ( is_object($var->_value) )
        self::cache_key($var->_value,$cachekey);
      elseif( is_scalar($var->_value) ) {
        //echo $obj.":".$var->_value."\n";
        $cachekey.= $obj.":".$var->_value."_";
      }
    }
  }

  public static function convert_lid_and_lok_to_pid($id){
    if (!isset($id['lid']))
      return null;
    if (  !isset($id['lok']) || preg_match('/^7/', $id['lok']) )
      $key = '870970-basis:' . $id['lid'];
    else
      $key = $id['lok'] . '-katalog:' . $id['lid'];

    return $key;

  }

  public static function get_lid_and_lok_from_pid($pid){
    if(preg_match('@(.*)-.*:(.*)@', $pid, $matches)){
      return array(
        'lok' => $matches[1],
        'lid' => $matches[2],
      );
    }
    return null;
  }

}
