<?php
class GridHelper extends AppHelper {
	var $name = 'Grid';
	var $helpers = array('Html', 'Layout.Asset');
	
	var $colCount = 0;

	//CSS
	var $colClassPrefix = 'col';
	var $lastClass = 'last';
	
	//Bool
	var $isOpen = false;
	var $isColOpen = false;
	
	function beforeRender($viewFile) {
		$this->Asset->css('Layout.layout');
		parent::beforeRender($viewFile);
	}
	
	function open() {
		$this->__reset();
		$this->isOpen = true;
		return $this->Html->div('grid');
	}
	
	function close() {
		$this->isOpen = false;
		$return = '';
		if ($this->isColOpen) {
			$return .= $this->colClose();
		}
		$return .= "</div>\n";
		return $return;
	}
	
	function col($class, $content = null, $options = array()) {
		if (!is_array($options)) {
			$options = array('close' => $options);
		}
		$return = $this->colOpen($class, $options);
		if ($content !== null) {
			$return .= $this->colClose($content);
		}
		if (!empty($options['close'])) {
			$return .= $this->close();
		}
		return $return;
	}
	
	function cols($cols = array(), $close = false) {
		$colCount = count($cols);
		$return = '';
		if (!empty($colCount)) {
			foreach ($cols as $content) {
				if (is_array($content)) {
					list($content, $colOptions) = $content;
				} else {
					$colOptions = array();
				}
				if (is_numeric($colOptions)) {
					$colOptions = array('cols' => $colOptions);
				}
				$colOptions['totalCols'] = $colCount;
				$return .= $this->col(null, $content, $colOptions);
			}
		}
		if ($close) {
			$return .= $this->close();
		}
		return $return;
	}
	
	function colOpen($class, $options = array()) {
		$return = '';
		if (!$this->isOpen) {
			$return .= $this->open();
		}
		$return .= $this->Html->div($this->__parseClass($class, $options));
		$return .= $this->Html->div('grid-inner');
		$this->isColOpen = true;
		return $return;
	}
	
	function colClose($content = '') {
		$close = false;
		if ($content === true) {
			$content = '';
			$close = true;
		}
		$return = $content . "\n</div>\n</div>\n";
		$this->isColOpen = false;

		if ($close) {
			$return .= $this->close();
		}
		return $return;
	}
	
	function colContinue($class, $content = null, $options = array()) {
		$return = '';
		if ($this->isColOpen) {
			$return .= $this->colClose();
		}
		$return .= $this->col($class, $content, $options);
		return $return;
	}
	
	function __parseClass($class, $options = array()) {
		if (!empty($options['totalCols'])) {
			$class = (!empty($options['cols']) ? $options['cols'] : 1) . '/' . $options['totalCols'];
		}
		$class = preg_replace('#(([\d]+)/([\d]+))#e', '$this->__getFractionClass($2,$3)', $class);
		if (!empty($options['class'])) {
			$class .= ' ' . $options['class'];
		}
		return $class;
	}
	
	function __getFractionClass($numerator, $denominator) {
		$fraction = $this->__reduce(array($numerator, $denominator));
		$class = "{$this->colClassPrefix}{$fraction[0]}-{$fraction[1]}";
		if (($this->colCount += ($numerator / $denominator)) >= 1) {
			$class .= ' ' . $this->lastClass;
		}
		return $class;
	}
	
	function __reduce($fraction = array()) {
		list($n, $d) = $fraction;
		for ($f = $n; $f > 0; $f--) {
			$testN = $n / $f;
			$testD = $d / $f;
			if ($testN == round($testN) && $testD == round($testD)) {
				return array($testN, $testD);
			}
		}
		return $fraction;
	}
	
	function __reset() {
		$this->colCount = 0;
	}
}
?>