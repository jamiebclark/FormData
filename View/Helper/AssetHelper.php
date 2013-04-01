<?php
class AssetHelper extends AppHelper {
	var $name = 'Asset';
	var $helpers = array('Html');		var $jquery = '//ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js';		function __construct(View $view, $options = array()) {		parent::__construct($view, $options);		if (!empty($options['jquery'])) {			$this->js($this->jquery);		}	}
	var $_assets = array();
	
	function js($file, $config = array()) {
		return $this->_addFile('js', $file, $config);
	}
	
	function css($file, $config = array()) {
		return $this->_addFile('css', $file, $config);
	}	
	
	function block($script, $config = array()) {
		return $this->_addFile('block', $script, $config);
	}
	
	function removeCss($file) {
		return $this->_removeFile('css', $file);
	}
	
	function removeJs($file) {
		return $this->_removeFile('js', $file);
	}

	function output($inline = false) {
		$assetOrder = array('css', 'js', 'block');
		$eol = "\n\t";
		$out = $eol . '<!--- ASSETS -->'. $eol;
		foreach ($assetOrder as $type) {
			if (!empty($this->_assets[$type])) {
				foreach ($this->_assets[$type] as $file => $config) {					$out .= $this->_output($type, $file, $config, $inline) . $eol;
				}
			}
		}
		$out .= '<!--- END ASSETS -->'. $eol;
		return $out;
	}
	
	protected function _addFile($type, $files, $configAll = array()) {
		if (!is_array($files)) {
			$files = array($files);
		}
		if (!isset($this->_assets[$type])) {
			$this->_assets[$type] = array();
		}
		$typeFiles =& $this->_assets[$type];
		$prependCount = 0;
		foreach ($files as $file => $config) {
			if (is_numeric($file)) {
				$file = $config;
				$config = array();
			}
			$config = array_merge($configAll, $config);
			if (!empty($config['prepend'])) {
				unset($config['prepend']);
				$insert = array($file => $config);
				if (empty($typeFiles)) {
					$this->_assets[$type] += $insert;
				} else if (empty($prependCount)) {
					$this->_assets[$type] = $insert + $typeFiles;
					$prependCount++;
				} else {
					$before = array_slice($typeFiles,0,$prependCount);
					$after = array_slice($typeFiles,$prependCount);
					$this->_assets[$type] = $before + $insert + $after;
					$prependCount++;
				}
			} else {
				$typeFiles[$file] = $config;
			}
		}
		return true;
	}
	
	protected function _removeFile($type, $files) {
		if (!is_array($files)) {
			$files = array($files);
		}
		foreach ($files as $file) {
			unset($this->{$type}[$file]);
		}
	}
	
	protected function _output($type, $file, $config = array(), $inline = false) {
		$options = compact('inline');		if (!empty($config['plugin'])) {			$options['plugin'] = $config['plugin'];			unset($config['plugin']);		}		if ($type == 'css') {
			$keys = array('media');
			foreach ($keys as $key) {
				if (!empty($config[$key])) {
					$options[$key] = $config[$key];
				}
			}
			$out = $this->Html->css($file, null, $options);
			if (!empty($config['if'])) {
				$out = sprintf('<!--[if %s]>%s<![endif]-->', $config['if'], $out);
			}
		} else if ($type == 'js') {
			$out = $this->Html->script($file, $options);
		} else if ($type == 'block') {
			$out = $this->Html->scriptBlock($file, $options);
		}		return $out;
	}
}