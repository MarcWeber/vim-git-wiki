<?php



class Arr
{


	static public function subkey_as_key($key, $a){
          $r = array();
          foreach ($a as $v) {
            $r[$v[$key]] = $v;
          }
          return $r;
        }

	static public function entferneNulls($a, $entferne_keys = true){
		return $entferne_keys 
		? array_values(array_filter($a, 'nicht_null')) 
		: array_filter($a, 'nicht_null');
	}
    

	static public function copyKeys($keys, $a, $mustExist = true, array $result = array()){ 
		foreach( $keys as $key){
			if (!array_key_exists($key, $a))
				if ($mustExist) throw new Exception( 'Array::copyKeys key '.$key.' nicht in Array gefunden, vorhandene keys :'.implode(array_keys($a),',') );
				else continue;
			else
				$result[$key] = $a[$key];
		}
		return $result;
	}

	
	
	static public function subKeyValues($arr, $key){
		$r = array();
		foreach( $arr as $v){
			$r[] = $v[$key];
		}
		return $r;
	}

	function nicht_null($a){ return !is_null($a); }
	
	
	static public function append($value_oder_array, $value){
		if (is_null($value_oder_array))
			return array($value);

		$v = (is_array($value_oder_array)) ? $value_oder_array : array($value_oder_array);
		$v[] = $value;
		return $v;
	}
	
	static public function dv($a, $key, $default){
		return (array_key_exists($key, $a)) ? $a[$key] : $default;
	}

	
	
	
	
	static public function erstelleKeyValueArr($array, $key_key, $value_key){
       return array_combine(Arr::subKeyValues( $array, $key_key), Arr::subKeyValues( $array, $value_key));
	}

	static public function range($start, $stop, $step = 1){
		$r = array();
		 for ($i = $start; $i <= $stop; $i += $step)
			$r[] = $i;
		return $r;
	}
	
	
	static public function rand($a){
		$a = array_values($a);
		return $a[rand(0, count($a)-1)];
	}

	static public function groupBy($array, $key){
		$r = array();
		foreach( $array as $a){
			$r[$a[$key]][] = $a;
		}
		return $r;
	}
	
    static public function toArr($v){
		if (is_null($v))
			return array();
		elseif (is_array($v))
			return $v;
		else 
			return array($v);
	}
	
	static public function keysNurMitPrefix($p, $a){
		$r = array(); $l = strlen($p);
		foreach ($a as $k => $v) {
			if (substr($k,0, $l) == $p)
				$r[$k] = $v;
		}
		return $r;
	}

        static public function combine($a){
          return array_combine($a, $a);
        }

        static public function repeat($x, $count){
          $a = array();
          for ($i = 0; $i < $count; $i++) {
            $a[] = $x;
          }
          return $a;
        }
}
