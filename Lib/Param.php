<?php
/**
 * Param Library
 * --------------
 * Used to manipulate array parameters passed into various objects within Cake
 *
 **/
 
class Param {
	function &getInstance() {
		static $instance = array();

		if (!$instance) {
			$instance[0] =& new Param();
		}
		return $instance[0];
	}

	function keyValCheck(&$params, $val, $remove = false, $default = null) {
		$self =& Param::getInstance();
		$chk = $self->valCheck($params, $val, $remove, $default);
		if (!isset($chk)) {
			$chk = $self->keyCheck($params, $val, $remove, $default);
		}
		return $chk;
	}
	
	//Checks a params array for the existence of a key
	//Returns the params value at the key if found, otherwise null
	function keyCheck(&$params, $key, $remove = false, $default = null) {
		$return = $default;		//If not found, returns NULL if user has not set a default value
		
		if (!is_array($params)) {
			return $return;
		}
		if (isset($params[$key])) {
			$val = $params[$key];
			if ($remove) {
				unset($params[$key]);
			}
			return $val;
		} else {
			return $return;
		}
	}
	
	//Checks a params array for the existence of a value
	//Returns the value if found, otherwise null
	function valCheck(&$params, $val, $remove = false, $default = null, $returnKey = false) {
		$return = $default;		//If not found, returns NULL if user has not set a default value
		if (!is_array($params)) {
			return $params == $val ? true : $return;
		}
		foreach ($params as $k => $v) {
			if (is_int($k) && $v == $val) {
				if ($remove) {
					unset($params[$k]);
				}
				return $returnKey ? $k : $val;
			}
		}
		return $return;
	}
	
	//Finds the key of a value within the parameters
	function valKey(&$params, $val) {
		$self =& Param::getInstance();
		return $self->valCheck($params, $val, false, null, true);
	}
	
	//Does a strict check of a key to see if it has been set to FALSE
	function falseCheck($params, $key, $remove = false) {
		if (isset($params[$key])) { //Key is found
			$return = $params[$key] === false;	//Key value is false
			if ($remove) {
				unset($params[$key]);
			}
			return $return;
		} else {
			return null;	//Key is not found
		}
	}

}
?>