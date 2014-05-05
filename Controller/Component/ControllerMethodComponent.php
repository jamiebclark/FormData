<?php
/**
 * ControllerMethodComponent
 * Used by the FormData Plugin to communicate back to the Controller
 *
 **/
class ControllerMethodComponent extends Component {
	public $name = 'ControllerMethod';
	
	#section Callback Methods
	public function initialize(Controller $controller) {
		$this->setController($controller);
		return parent::initialize($controller);
	}
	#endsection
	
	public function setController($controller) {
		$this->controller = $controller;
	}
	
	/**
	 * Checks to see if a controller method exists and calls it
	 *
	 * @param String $methodName The name of the method to call in the controller
	 * @param [Mixed $...] Optional parameters to pass to method
	 *
	 * @return Boolean|NULL Returns the method if it exists, null if it does not
	 **/
	public function call($methodName) {
		$args = func_get_args();
		array_shift($args);	//Removes method name
		if (method_exists($this->controller, $methodName)) {
			return call_user_func_array(array($this->controller, $methodName), $args);
		} else {
			return null;
		}
	}
}