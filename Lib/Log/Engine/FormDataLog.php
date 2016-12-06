<?php
App::uses('FileLog', 'Log/Engine');

class FormDataLog extends FileLog {
	public function __construct($options = array()) {
		$options['path'] = APP . 'tmp' . DS . 'logs' . DS . 'FormData' . DS;
		$options['types'] = ['FormData_invalid'];

		parent::__construct($options);
	}
}