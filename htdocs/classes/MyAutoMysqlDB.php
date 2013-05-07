<?php
function mylog($s){
    global $log;
    if (!empty($log)) { 
    	$log->info($s, false); 
    }
}

class Verbatim 
{
	protected $s;
	public function __construct($s)
	{
		$this->s = $s;
	}

	public function toString(){
		return $this->s;
	}
}



class Now extends Verbatim
{
	public function __construct(){
		parent::__construct('NOW()');
	}
}


class DBError extends Exception {};
class DBFalscheZeilenzahl extends DBError {};


abstract class MyDB {
    protected abstract function error($sql, $mysql_error_text);
    public abstract function queryGenauEineZeileEinWert();
    public abstract function queryGenauEineZeile();
    public abstract function queryErsteSpalte();
    public abstract function query();
    public abstract function insert(); 
    public abstract function execute();
    public abstract function unlockTabellenNachFunktionArray($callback, $args = array());
}

class MyMysqlDB extends MyDB {

    public $db_conn = null;

	
	
	public function quoteNachTyp($v, $typ = ''){
		$quotingFunctions = array(
				  's' => 'quoteString'
				, 'd' => 'quoteDate'
				, 'dt' => 'quoteDateTime'
				, 't' => 'quoteTime'
				, 'i' => 'quoteInt'
				, 'f' => 'quoteFloat'
				, 'b' => 'quoteBoolean'
				, 'o' => 'quoteOp' 
				, 'n' => 'quoteName'
				, 'v' => 'quoteVerbatim'
				);
		if (substr($typ, 1,2) == 'in') {
			$typ = substr($typ,0,1);
			$in = true;
		}
		$qf = (array_key_exists(''.$typ, $quotingFunctions))
			  ? $quotingFunctions[$typ] 
			  : 'quoteSmart';
		if (isset($in)){
			
			return '('.implode( array_map(array($this, $qf), $v),', ').')';
		} else return (is_null($v) ? 'NULL' : call_user_func( array($this, $qf ), $v ) );
	}

	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	public function ersetzePlatzhalter(){
		$args = func_get_args();
		if (count($args) == 1)
			return $args[0];
		$sql = current($args); next($args);
		
		preg_match_all('/(^|\?(?:dt|[tobnfidsv])?(?:in)?)((?::[^\t\r\n) ,:]+)*)([^?]*)/', $sql, $matches);
		
		$sql = $matches[0][0];
		
		
		

		
		for ( $i = 1; $i < sizeof($matches[0]); $i++){
			if ($matches[2][$i] == ''){
				
				$wert = current($args); next($args);
			} else { 
				$wert =& $args;
				$keys = explode(':', substr($matches[2][$i],1));
				foreach( $keys as $k){
					if ((!is_array($wert)) || (!array_key_exists($k,$wert)))
						throw new Exception( 'SQL Parameter Fehler, Parameter ['.implode($keys,',').'] nicht gefunden in '.print_r($args,true));
					$wert =& $wert[$k];
				}
			}
			$type = substr($matches[1][$i], 1, 3);
			$sql .= ( $wert instanceof Verbatim
					? $wert->toString()
					: $this->quoteNachTyp( $wert, $type ) )
				. $matches[3][$i];
		}
		return $sql;
	}

    protected function error($sql, $mysql_error_text){
        throw new DBError("SQL Statement war\n$sql\n\n Fehler:\n".mysql_error($this->db_conn));
    }

    
    protected function _q($sql){
		global $db_gesamt;
        
        $time		= microtime(true); 
		$db_result  = mysql_query($sql,$this->db_conn);
		$time_diff  = microtime(true) - $time;


			if (LOG_MYSQL_ABFRAGEN === true){

                          $debug_handle = fopen('/tmp/php.log', 'a+');
                          fwrite($debug_handle, 'gebrauchte Zeit: '.print_r($time_diff,true). "\nsql: >>> ".print_r($sql,true)."\n" );
                          fclose($debug_handle);
			}
				
         $db_gesamt += $time_diff;
		if ($db_result)
			return $db_result;
		else
            $this->error($sql, mysql_error($this->db_conn));
    }
    
	protected function _query($sql){
		$db_result = $this->_q($sql);
                if (is_bool($db_result))
                  return $db_result; // happens on delete, insert etc.
		$result = array();
		while ($row = mysql_fetch_assoc($db_result))
			$result[] = $row;
		return $result;
	}

    protected function _rufeAufMit($f, $args){
        $sql = call_user_func_array(array($this,'ersetzePlatzhalter'), $args);
        return call_user_func(array($this,$f), $sql);
    }

    public function queryGenauEineZeile(){
        $args = func_get_args();
        $rows = call_user_func_array( array($this, 'query'), $args );
        switch(sizeof($rows)) {
            default: 
				$sql = call_user_func_array(array($this,'ersetzePlatzhalter'), $args);
				throw new DBFalscheZeilenzahl('genau 1 Zeile erwartet, aber mehrere von DB erhalten!, SQL war: '.$sql);
            case 0:  
				$sql = call_user_func_array(array($this,'ersetzePlatzhalter'), $args);
				throw new DBFalscheZeilenzahl('genau 1 Zeile erwartet, aber keine erhalten!, SQL war: '. $sql);
            case 1:  return $rows[0];
        };
    }

    public function queryGenauEineZeileEinWert(){
        $args = func_get_args();
        $row = call_user_func_array( array($this,'queryGenauEineZeile'), $args );
        if (count($row) > 0) {
			 $values = array_values($row);
			 return $values[0];
         } else throw new DBError('expected at least one row. None found!');
    }

    public function queryErsteSpalte(){
        $args = func_get_args();
        $rows = call_user_func_array( array($this, 'query'), $args );
        $re = array();
        foreach( $rows as $r){
            $re[] = current($r);
        }
        return $re;
    }
    public function query(){
        $args = func_get_args();
        return $this->_rufeAufMit('_query', $args);
    }

    # gibt id zurück
    public function insert(){
          $args = func_get_args();
          call_user_func_array(array($this, 'execute'), $args);
          return mysql_insert_id($this->db_conn);
    }

    public function execute(){ 
        $args = func_get_args();
        return $this->_rufeAufMit('_q', $args);
    }

	public function unlockTabellenNachFunktionArray($callback, $args = array()){
		try {
			$r = call_user_func_array($callback, $args);
			$this->unlockTabellen();
			return $r;
		} catch (Exception $e){
			$this->unlockTabellen();
			throw $e;
		}
	}
    public function unlockTabellen(){
		$this->execute('UNLOCK TABLES');
    }

    public function __construct($db_host, $db_user, $db_password, $db){
		$this->db_conn = mysql_connect($db_host, $db_user, $db_password, true);
		if ( ($this->db_conn === false) 
				|| ( !empty($db) ) && ( false === mysql_select_db($db, $this->db_conn) ) )
			throw new DBError(mysql_error()." ".mysql_errno());
    }

    
	public function quoteString($s){ return "'".mysql_escape_string($s)."'"; }

	public function quoteBoolean($b){ return $b ? '1' : '0'; }

	public function quoteInt($i){
		if ($i != $i*1)
			throw new Exception( "Fehler: quoteInt: integer erwartet, aber '$i' bekommen");
		return $i === '' ? 'NULL' : $i; 
	}
	public function quoteFloat($f){
		if (preg_match('/[^-0-9.,e]/',$f) > 0)
			throw new Exception( "Fehler: quoteFloat: float erwartet, aber '$f' bekommen");
		return $f;
	}
	
	public function quoteTime($d, $quote = true){
		$quote = ($quote) ? '\'' : '';
        if (is_null($d) || $d === '') return 'NULL';
		if (preg_match('/^(\p{Nd}{1,2}):(\p{Nd}{1,2}):(\p{Nd}{1,2})$/',$d, $m) !== 1)
          throw new Exception( 'Fehler: Uhrzeit hh:mm:ss erwartet, jedoch '.$d.' bekommen');
        return "$quote$d$quote";
	}
	public function quoteDate($d, $quote=true){
		$quote = ($quote) ? '\'' : '';
		if (is_null($d) || $d === '') return 'NULL';
		if (is_numeric($d)){
                  $d = date('H:i:s', $d);
		}

                if ($d instanceof DateTime){
		    return $quote. $d->format('Y-m-d').$quote;
                } else {
		if (preg_match('/^(\p{Nd}{1,2})\.(\p{Nd}{1,2})\.(\p{Nd}{1,4})$/',$d, $m) !== 1)
			if (preg_match('/^(\p{Nd}{1,4})-(\p{Nd}{1,2})-(\p{Nd}{1,2})$/',$d, $m) !== 1)
				throw new EUser_Message( 'Fehler: DATUM erwartet, aber "'.$d.'" bekommen');
			else return  $quote.sprintf('%04d-%02d-%02d', $m[1], $m[2],$m[3]).$quote;
		return $quote.sprintf('%04d-%02d-%02d', $m[3], $m[2],$m[1]).$quote;
                }
	}
	public function quoteDateTime($d, $quote=true){
          $quote = ($quote) ? '\'' : '';
          if (is_null($d) || $d === '') return 'NULL';
          if (is_numeric($d)){
            $d = date('H:i:s', $d);
          }

          if ($d instanceof DateTime){
            return $quote. $d->format('Y-m-d H:m:i').$quote;
          } else {

            if (preg_match('/^(\p{Nd}{1,2})\.(\p{Nd}{1,2})\.(\p{Nd}{1,4})(\s+(\p{Nd}{1,2}):(\p{Nd}{1,2}))$/',$d, $m)){
              return  $quote
                .sprintf('%04d-%02d-%02d', $m[3], $m[2],$m[1])
                .( isset($m[6]) ? ' '.$m[5].':'.$m[6].':00' : '' )
                .$quote;
                  
            } else if (preg_match('/^(\p{Nd}{1,2})\.(\p{Nd}{1,2})\.(\p{Nd}{1,4})(\s+(\p{Nd}{1,2}):(\p{Nd}{1,2}):(\p{Nd}{1,2}))?$/',$d, $m)){
              return  $quote
                .sprintf('%04d-%02d-%02d', $m[3], $m[2],$m[1])
                .( isset($m[7]) ? ' '.$m[5].':'.$m[6].':'.$m[7]  : '' )
                .$quote;

            } elseif (preg_match('/^(\p{Nd}{1,4})-(\p{Nd}{1,2})-(\p{Nd}{1,2})(\s+(\p{Nd}{1,2}):(\p{Nd}{1,2}):(\p{Nd}{1,2}))?$/',$d, $m)) {

              return  $quote
                .sprintf('%04d-%02d-%02d', $m[1], $m[2],$m[3])
                .( isset($m[7]) ? ' '.$m[5].':'.$m[6].':'.$m[7]  : '' )
                .$quote;

            } else
                throw new EUser_Message( 'Fehler: DATUM (optional mit Zeit) erwartet, aber "'.$d.'" bekommen');

          }

	}

	public function quoteName($n){ 
		
		
		return '`'.str_replace('.','`.`', $n).'`';
	}
   public function quoteVerbatim($s){ return $s; }
   public function quoteOp($o){ 
	   if (preg_match('/=|>|<|!=|like|any|in|all/i', $o) > 0) 
		   return $o;
	   else throw new Exception( 'illegal op '.$o); 
   }

}

class MyAutoMysqlDB extends MyMysqlDB {

    public function quoteSmart($in){
        if (is_int($in)) {
            return $in;
        } elseif (is_float($in)) {
            return $this->quoteFloat($in);
        } elseif (is_bool($in)) {
            return $this->quoteBoolean($in);
        } elseif (is_string($in)) {
            return $this->quoteString($in);
        } elseif (is_null($in)) {
            return 'NULL';
        } else {
            return "'" . $this->escapeSimple($in) . "'";
        }
    }

      
      
      
      
      
      public function whereAll($werte){
          if (is_null($werte))
              return '';
          $bed = array();
          foreach( $werte as $key => $v){
              if (is_null($v))
                  $bed[] = $this->quoteName($key).' is null';
              else
                  $bed[] = $this->quoteName($key).' = '.$this->quoteSmart($v);
          }
          return implode($bed, ' AND ');
      }

    
    
    
    
    
    
    
    
    public function autoInsert($tabelle, $werte, $prefix= '', $alias = null, $cmd = "INSERT"){
        $felder = isset($this->tabellenUndFeldTypen[$tabelle])
            ? $this->tabellenUndFeldTypen[$tabelle]
            : ($felder = $this->tabellenUndFeldTypen[$tabelle] = $this->queryTabelleFeldTypen($tabelle));

        $namen = array(); $v_types = array();
        foreach( $felder as $k => $t){
            if (array_key_exists($prefix.$k,$werte)){
                $namen[] = $this->quoteName($k); 
                $v_types[] = $t.':1:'.$prefix.$k;
            }
        }
        return $this->insert($cmd.' INTO '.$this->quoteName($tabelle).((is_null($alias)) ? '' : ' AS '.$this->quoteName($alias) )
              .' ( '. implode($namen, ', ').') VALUES ( '.implode($v_types,', ').')', $werte);
    }

    
    
    public function autoInsertUpdate($tabelle, $werte, $prefix= '', $alias = null){
        $felder = isset($this->tabellenUndFeldTypen[$tabelle])
            ? $this->tabellenUndFeldTypen[$tabelle]
            : ($felder = $this->tabellenUndFeldTypen[$tabelle] = $this->queryTabelleFeldTypen($tabelle));

        $namen = array(); $v_types = array(); $updates = array();
        foreach( $felder as $k => $t){
            if (array_key_exists($prefix.$k,$werte)){
				$qn = $this->quoteName($k);
				$expr = $t.':1:'.$prefix.$k;
                $namen[] = $qn; 
                $v_types[] = $expr;
				$updates[] = $qn.'='.$expr;
            }
        }
        return $this->insert(' INSERT INTO '.$this->quoteName($tabelle).((is_null($alias)) ? '' : ' AS '.$this->quoteName($alias) )
            .' ( '. implode($namen, ', ').') VALUES ( '.implode($v_types,', ').')'
			.' ON DUPLICATE KEY UPDATE '.implode($updates,','),
			$werte);
    }

    
    
    public function autoUpdate($tabelle, $werte, $selector, $prefix=''){
        $felder = isset($this->tabellenUndFeldTypen[$tabelle])
        ? $this->tabellenUndFeldTypen[$tabelle]
        : ($felder = $this->tabellenUndFeldTypen[$tabelle] = $this->queryTabelleFeldTypen($tabelle));

        foreach( $felder as $k => $t){
        if (array_key_exists($prefix.$k,$werte)){ 
        $sets[] = $this->quoteName($k).'='.$t.':3:'.$prefix.$k;
        }
        }
        return $this->execute('UPDATE ?n SET '. implode($sets, ', ').' WHERE ?v', $tabelle, $this->whereAll($selector), $werte);
    }

    
    
    public function autoSelect($table, $selector = array()){
        return $this->query('SELECT * FROM '.$this->quoteName($table)
                .  ( empty($selector) ? '' : ' WHERE '.$this->whereAll($selector)) );
    }

    public function enumWerte($table, $feld){
        foreach( $this->query('DESCRIBE ?n', $table) as $r){
            if (($r['Field']) == $feld){
				$s = $r['Type'];
				$s = substr($s, 5, strlen($s) - 6);
				preg_match_all("/'(([^']*)')/", $s, $m);
                return $m[2];
            }
        }
        throw new Exception( 'Feld '.$feld.' nicht gefunden' );
    }
 
    public function describe($table){
      global $db;
      static $cache;
      if (is_null($cache))
        $cache = $table;
      if (!isset($cache[$table]))
        $cache[$table] = $db->querySpalteAlsKeys('field', 'describe ?n', $table);
      return $cache[$table];
    }

    public function queryTabelleFeldTypen($table){
        $felder = array();
        foreach($this->query('DESCRIBE ?n', $table) as $f){
            if (strpos( $f['Type'], 'datetime') !== false)
                $ty = '?dt';
            else
            switch(substr($f['Type'],0,3)) {
                case 'int': $ty = '?i'; break;
                case 'cha': $ty = '?s'; break;
                case 'var': $ty = '?s'; break;
                case 'tin': $ty = '?i'; break;
                case 'boo': $ty = '?b'; break;
                case 'dat': $ty = '?d'; break;
                case 'tim': $ty = '?dt'; break;
                            
                default: $ty = '?';
            }
            $felder[$f['Field']] = $ty;
        }
        return $felder;
    }


	protected function _queryNumRows($sql){
		$db_result = $this->_q($sql);
		$result = mysql_num_rows($db_result);
		mysql_free_result($db_result);
		return $result;
	}

    
    public function queryNumRows(){
        $args = func_get_args();
        return $this->_rufeAufMit('_queryNumRows', $args);
    }

    
	
	
    public function queryFuerOptions(){
        $args = func_get_args();
        $rows = call_user_func_array( array($this, 'query'), $args );
		$r = array();
		foreach( $rows as $row)
			$r[] = array($row['null'], $row['eins']);
		return $r;
    }

    public function queryKeyValueArray(){
        $args = func_get_args();
        $rows = call_user_func_array( array($this, 'query'), $args );
        return (count($rows) === 0) ? array()
		       :  Arr::erstelleKeyValueArr($rows, 'K', 'V');
    }

	
	
	
	
	public function querySpalteAlsKeys(){
        $args = func_get_args();
		$spalte = $args[0]; 
		$args = array_slice($args,1);
        $rows = call_user_func_array( array($this, 'query'), $args );
		if (count($rows) == 0)
			return array();
        return array_combine(Arr::subKeyValues($rows, $spalte), $rows);
	}


    public function dumpTable($table, $orderby = null, $was){
        $typen = $this->queryTabelleFeldTypen($table);
        $felder2 = array();
        $value_Platzhalter = array();
        foreach( $typen as $key => $value){
            $felder[] = $this->quoteName($key);
            $value_Platzhalter[] = $value.':1:'.$key;
        }
        $value_Platzhalter = '('.implode($value_Platzhalter, ', ').')';
        $sql = 'INSERT INTO '.$this->quoteName($table).' ('.implode($felder,', ').") VALUES \n";
        $was = (is_null($was) ? '*' : $was);
        $sqlq = new SQLQuery("SELECT $was FROM ".$this->quoteName($table));
        if (!is_null($orderby))
        $sqlq->orderBy($orderby);
        $rows = $this->query($sqlq->sql());
        $sql_rows = array();
        foreach( $rows as $row){
			$sql_rows[] = $this->ersetzePlatzhalter($value_Platzhalter, $row);
		}
		return $sql.implode($sql_rows, ",\n").";";
    }
}

?>
