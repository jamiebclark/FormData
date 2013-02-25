<?php
class Prefix {
	function reset($allow = null, $url = array()) {
		$prefixes = Router::prefixes();
		if (!empty($prefixes)) {
			foreach ($prefixes as $prefix) {
				if (empty($allow) || !in_array($allow, $prefix)) {
					$url[$prefix] = false;
				}
			}
		}
		return $url;
	}
	
	function remove($url, $allow = null) {
		unset($url['prefix']);
		
		$prefixes = Router::prefixes();
		if (!empty($prefixes)) {
			foreach ($prefixes as $prefix) {
				if (empty($allow) || !in_array($allow, $prefix)) {
					unset($url[$prefix]);
				}
			}
		}
		return $url;
	}
	
	function get($linkArray) {
		$prefixes = Router::prefixes();
		if (!empty($linkArray['prefix'])) {
			return $linkArray['prefix'];
		}
		foreach ($prefixes as $prefix) {
			if (!empty($linkArray[$prefix])) {
				return $prefix;
			}
		}
		return false;
	}
	
	function removeFromAction($action, $prefix) {
		$prefix .= '_';
		if (!empty($prefix) && strpos($action, $prefix) === 0) {
			$action = substr($action, strlen($prefix));
		}
		return $action;
	}
}
?>