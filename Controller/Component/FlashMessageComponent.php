<?php
/**
 * FlashMessageComponent
 * A series of functions to make calling Session->setFlash easier
 *
 **/
class FlashMessageComponent extends Component {
	public $name = 'FlashMessage';
	public $components = array('Session');

	const FLASH_ELEMENT 	= 'alert';
	
	//Flash type classes
	const ERROR_CLASS 		= 'danger';
	const INFO_CLASS 		= 'info';
	const WARNING_CLASS		= 'warning';
	const SUCCESS_CLASS		= 'success';
	
	public function beforeRender(Controller $controller) {
		$this->overwriteFlash();
	}

	public function overwriteFlash() {
		//Overwrites the current Flash setup
		$session = 'Message';
		if ($this->Session->check($session)) {
			$msgs = $this->Session->read($session);
			foreach ($msgs as $var => $flash) {
				if ($flash['element'] == 'default') {
					$flash['element'] = self::FLASH_ELEMENT;
					if (empty($flash['params'])) {
						$flash['params'] = array();
					}
					$flash['params'] = $this->_flashParams(
						!empty($flash['params']['type']) ? $flash['params']['type'] : null,
						$flash['params']
					);
				}
				$this->Session->write("$session.$var", $flash);
			}
		}	
	}

	//A generic session message flash
	public function flash($msg, $type = self::INFO_CLASS) {
		$this->Session->setFlash(__($msg), self::FLASH_ELEMENT, $this->_flashParams($type));
	}

	/**
	 * Type-specific message flashes
	 *
	 **/
	 
	public function error($msg) {
		return $this->flash($msg, self::ERROR_CLASS);
	}
	
	public function success($msg) {
		return $this->flash($msg, self::SUCCESS_CLASS);
	}
	
	public function info($msg) {
		return $this->flash($msg, self::INFO_CLASS);
	}
	
	/**
	  * Finds params used with Session Flash
	  *
	  * @param String $type The type of flash message it is. Will be returned as part of the element class appended by alert-
	  * @param Array (optional) $params Existing params to be merged into result
	  * @return Array Params
	  **/
	private function _flashParams($type = null, $params = array()) {
		if ($type === true) {
			$type = self::SUCCESS_CLASS;
		} else if ($type === false) {
			$type = self::ERROR_CLASS;
		} else if (empty($type)) {
			$type = self::INFO_CLASS;
		}
		$class = "alert-$type";
		$params['plugin'] = 'FormData';
		$params['close'] = CakePlugin::loaded('Layout');		//Only adds a close button if Layout plugin is also used
		$params += compact('class');
		return $params;
	}
}