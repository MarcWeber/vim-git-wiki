<?php

// formatieren von Exceptions
// license: do the fuck you want with it

class Trace
{
	static public function exceptionToText($e){
		// Klassenname
		$c = new ReflectionClass($e);
		return "Exception of type ".$c->getName().", message: \n".$e->getMessage()."\n";
	}

	static public function traceToText($trace){
		$s=''; $n="";
		foreach( $trace as $k){
		  $s.="\n".(isset($k['file']) ? $k['file'] : 'nofile') 
				   .':'.(isset($k['line']) ? $k['line'] : 'no line');
		}
		return $s;
	}

	static public function exFullTrace($e){
		return array_merge(
			 array (array('file' => $e->getFile(), 'line' => $e->getLine() ) )
			, $e->getTrace());
	}
}
?>
