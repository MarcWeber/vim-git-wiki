<?php

ob_start();

session_start();

/* load this in each php file .. */
define('LIB',dirname(__FILE__));
define('PROJECT_ID','VIM_WIKI');

// returns value identified by key or $default
function d($ar, $key, $default = null){ return isset($ar[$key]) ? $ar[$key] : $default; }

require_once dirname(__FILE__).'/config.php';

{ // strip slashes
    if (get_magic_quotes_gpc()){
            function strip_slashes_deep($value) {
                            return is_array($value) ? array_map('strip_slashes_deep', $value) : stripslashes($value);
            };
            $_GET = array_map('strip_slashes_deep', $_GET);
            $_POST = array_map('strip_slashes_deep', $_POST);
            $_COOKIE = array_map('strip_slashes_deep', $_COOKIE);
    }
}

{ /* simple error handling
     Usage see index.php

  */

  require_once 'error-handling.php';

  function handle_unexpected_failure($message, $trace){

	$time = date(DATE_ATOM);

	$message = "$time $message";

	try { @ob_end_flush(); } catch (Exception $e) { }

	try {
	  $trace = Trace::traceToText($trace);
	} catch (Exception $e){
	  $trace = array('exception in handler!');
	}

	echo "
	  Unerwarteter Fehler. Dieser Vorfall wurde geloggt: $time.<br/>
	  Wenn der Fehler in nach 24h immer noch besteht, bitte kontaktieren: ".ERROR_CONTACT_EMAIL."<br/>
	  <br/>
      <br/>
	  Unexpected failure. this incident was logged: $time<br/>
	  If this failure still happens in 24h please contact us: ".ERROR_CONTACT_EMAIL."<br/>

	  <br/>\n
	  <br/>\n
	  $message<br/>\n
	  ".str_replace("\n","<br/>\n", $trace)
	  ."\n";



	$all = "$message\n$trace";

	try {
		file_put_contents(ERROR_LOG_FILE, "\n\n===========\n".$all."\n", FILE_APPEND | LOCK_EX);
		chmod(ERROR_LOG_FILE, 0777);
        } catch (Exception $e){
          try {
            try {
              $all .= $e->getMessage();
            } catch (Exception $e) {
              $all .= "\n".$e->getMessage();
            }
          } catch (Exception $e2) {
            $all .= "\n".$e->getMessage()."\n".$e2->getMessage();
          }
        }


	$header = 'ERROR '.(defined('PROJECT_ID') ? constant('PROJECT_ID') : 'NO PROJECT ID').' '.$time;
	$emails = explode(',', ERROR_CONTACT_EMAIL);

	foreach ( $emails as $email ){
		$args = array($email, $header, "$message\n$trace");
	  if (! call_user_func_array('mail', $args) )
		  echo "sending email to admin failed\n";
	}
	echo "<br/>notification mail with header ".$header." was sent to ".count($emails)." admins addresses\n";
	exit();

  }

}

{ // automatically find classes by filename:

  function autload_class($class){

    if ($class == 'TCPDF'){
      require(LIB.'/tcpdf/tcpdf.php');
      return true;
    }
    if (in_array($class , (array('parent')))) return false;
    $files = array();
    $files[] = dirname(__FILE__).'/classes/'.$class.'.php';
    foreach ($files as $f) {
      if (file_exists($f)){
        require_once $f;
        return true;
      }
    }
    return false;
  }

  spl_autoload_register('autload_class');
}


/*
$db = new MyAutoMysqlDB(DB_HOST,DB_USER,DB_PASSWORD,DB_DATABASENAME);
// Transaktion wird in index.php comitted und bei Exceptions abgebrochen
$db->execute("SET sql_mode = 'STRICT_ALL_TABLES'");
$db->execute("SET NAMES 'utf8'");
$db->execute('START TRANSACTION');
 */

require_once dirname(__FILE__).'/classes/Trace.php' ;

$status_werte = array("?","aktiv","wieder_anfragen","kein_interesse_mehr","Mitarbeiter"); 

$scheme = array(

  'tables' => array(

    // version table, never delete or modify. used internally
    array(
      'name' => 'version',
      'fields' => array(
          array('name' => 'version', 'type' => 'int(10)')
      )
    ),

    // you can play around adding tew tables
    array(
      'name' => 'domains',
      'primaryKeyFields' => array('domain_id'),
      'fields' => array(
        array('name' => 'domain_id', 'type' => 'int(10)', 'auto_increment' => true),
        array('name' => 'domain', 'type' => 'varchar(255)'),
      )
    ),

    array(
      'name' =>  'domain_visited',
      'primaryKeyFields' => array(),
      'fields' => array(
        array('name' => 'domain_id', 'nullable' => true, 'type' => 'int(10)', 'references' => array('table' => 'domains', 'field' =>  'domain_id')),
        array('name' => 'time', 'type' => 'timestamp', 'default' => 'CURRENT_TIMESTAMP'),
      )
    )
  )
);
