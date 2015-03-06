<?php
class CrudComponent extends Component {
	public $name = 'Crud';
	public $components = array('Session', 'RequestHandler');
	
	public $controller;
	public $settings = array();
	public $isAjax = false;
	
	private $_log = array();
	private $_storedData = array();
	
	private $_postSave = array(
		'success' => array(),
		'fail' => array(),
	);
	
	const FLASH_ELEMENT = 'alert';
	const PLUGIN_NAME = 'FormData';
	
	public function __construct(ComponentCollection $collection, $settings = array()) {
		$settings = array_merge(array(
			'overwriteFlash' => true,		//Whether or not to overwrite the default flash element
		), $settings);
		return parent::__construct($collection, $settings);			
	}
	
	#section Callback Methods
	public function initialize(Controller $controller) {
		$this->controller =& $controller;
		// Finds the current model of the controller
		$model = null;
		if (!empty($this->controller->modelClass)) {
			$model = $this->controller->modelClass;
		} else if (!empty($this->controller->modelClass)) {
			$model = $this->controller->modelClass;
		}
		if (!empty($model)) {
			$this->settings['model'] = $model;
		}
		
		$this->isAjax = $this->isRequestType(['ajax']);
		// $this->setSuccessRedirect(array('action' => 'view', 'ID'));
	}

	public function beforeFilter(Controller $controller) {
		// Makes sure JSON is detectable
		$this->RequestHandler->setContent('json', 'application/json');
		return parent::beforeFilter($controller);
	}

	public function beforeRender(Controller $controller) {
		// Sets any variables 
		if (!empty($this->_vars)) {
			$this->controller->set($this->_vars);
			if ($this->isSerialized()) {
				// Makes sure to call serialize in case of AJAX call
				$this->controller->set('_serialize', array_keys($this->_vars));
			}
		}
		if ($this->settings['overwriteFlash']) {
			$this->overwriteFlash();
		}
	}
	#endsection
	
	#section Custom Callback Methods
	function beforeSaveData($data, $saveOptions) {
		unset($data['FormData']);
		if (($callResult = $this->callControllerMethod('_beforeSaveData', $data, $saveOptions)) === false) {
			$this->_log('Controller beforeSaveData failed');
			return false;
		} else if (!empty($callResult)) {
			$data = $callResult;
		}
		if (($data = $this->_checkCaptcha($data)) === false) {
			$this->_log('CheckCaptcha Failed');
			$this->_log($data);
			return false;
		}
		return $data;
	}
	
	function afterSaveData($created) {
		$this->callControllerMethod('_afterSaveData', $created);
		return true;
	}
	
	function afterFailedSaveData() {
		$this->callControllerMethod('_afterFailedSaveData');
		return true;
	}
	#endsection
	
	#section Getters and Setters
	public function setSuccessRedirect($redirect, $message = null) {
		$this->setPostSave('success', compact('message', 'redirect'));
	}
	
	public function setSuccessMessage($message, $redirect = null) {
		$this->setPostSave('success', compact('message', 'redirect'));
	}
	
	public function setFailRedirect($redirect, $message = null) {
		$this->setPostSave('fail', compact('message', 'redirect'));
	}
	
	public function setFailMessage($message, $redirect = null) {
		$this->setPostSave('fail', compact('message', 'redirect'));
	}

	public function getSuccessRedirect($default = null) {
		return $this->getPostSave('success', 'redirect', $default);
	}

	public function getSuccessMessage($default = null) {
		return $this->getPostSave('success', 'redirect', $default);
	}

	public function getFailRedirect($default = null) {
		return $this->getPostSave('fail', 'redirect', $default);
	}

	public function getFailMessage($default = null) {
		return $this->getPostSave('fail', 'redirect', $default);
	}
	#endsection

	private function overwriteFlash() {
		//Overwrites the current Flash setup
		$session = 'Message';
		if (!empty($this->settings['overwriteFlash']) && $this->Session->check($session)) {
			$msgs = $this->Session->read($session);
			debug($msgs);
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
	
/**
 * Saves new request data
 *
 * @param array $options create options, including default values to be passed to the form
 * @param array $saveAttrs FormData->saveData options
 * @param array $saveOptions Model->save options
 * @return array Model result
 */
	public function create($options = array(), $saveAttrs = array(), $saveOptions = array()) {
		$result = $this->saveData(null, $saveAttrs, $saveOptions);
		if ($result === null && !empty($options['default'])) {
			$this->setData($options['default'], true);
		}		
		$this->setFormElements();
		return $result;
	}
	
	public function read($id = null, $attrs = array(), $options = array()) {
		$attrs = $this->setCrudReadAttrs($attrs);
		if (!empty($attrs['options'])) {
			$options = array_merge($options, $attrs['options']);
			unset($attrs['options']);
		}
		extract($attrs);
		if (method_exists($this, $model)) {
			$Model =& $this->controller->{$model};
		} else {
			$importModel = $model;
			if (!empty($this->settings['plugin'])) {
				$importModel = $this->settings['plugin'] . '.' . $model;
			}
			$Model = ClassRegistry::init($importModel, true);
		}
		
		if (empty($options)) {
			$options = array();
		}

		if (!empty($id) || empty($options['conditions'])) {
			if (!is_numeric($id) && ($slugField = $this->_isSluggable($Model))) {
				//Searches by slug
				$conditions = array($Model->alias . '.' . $slugField . ' LIKE' => $id);
			} else {
				//Searches by primary ID
				$conditions = array($Model->alias . '.' . $Model->primaryKey => $id);
			}
			$options = Set::merge($options, compact('conditions'));
		}
		
		$controller = Inflector::tableize($model);
		$varName = strtolower(substr($model, 0, 1)) . substr($model, 1);
		$human = Inflector::humanize(Inflector::singularize($controller));
		
		$this->id = $id;
		
		if ($vars = $this->callControllerMethod('_beforeCrudRead', $options)) {
			$options = $vars;
		}
		
		if ($vars = $this->callControllerMethod('_setCrudReadOptions', $options)) {
			$options = $vars;
		}
		if (!empty($method) && method_exists($Model, $method)) {
			if (!empty($passIdToMethod)) {
				$result = $Model->{$method}($this->id, $options);
			} else {
				$result = $Model->{$method}($options);
			}
		} else {
			$result = $Model->find('first', $options);
		}
		
		if ($afterFindResult = $this->callControllerMethod('_afterCrudRead', $result)) {
			if (is_array($afterFindResult)) {
				$result = $afterFindResult;
			}
		}
		
		if (!empty($attrs['data'])) {
			$result = $this->resultToData($result, $attrs);
		}
		
		if (empty($result)) {
			$this->id = null;
		}
		if (empty($message)) {
			$message = "$human is not found";
		}

		if (empty($result[$Model->alias][$Model->primaryKey]) && !empty($redirect)) {
			$message .= sprintf("! %s, %s Looking for ID: %d<br/>\n", $Model->alias, $Model->primaryKey, $id);
			if (is_array($result)) {
				$message .= 'Keys: ' . implode(', ', array_keys($result)) . "<br/>\n";
				//$message .= 'Body: ' . implode(', ', $result);
			}
			//$message .= implode('<br/>', debugTrace('Trace'));
			$message .= '<br/>' . Router::url($redirect);
			$this->flash($message, 'danger');
			$this->controller->redirect($redirect);
		}
		$this->controller->set($varName, $result);
		return $result;
	}


/**
 * Saves request data based on an existing model id. 
 * Regardless of whether it saves anything, it will still find and store the model id result
 *
 * @param int $id The id of the model being saved
 * @param array $findAttrs FormData->findModel options
 * @param array $findOptions Model->find options
 * @param array $saveAttrs FormData->saveData options
 * @param array $saveOptions Model->save options
 * @return array Model result
 **/
	public function update($id, $findAttrs=array(), $findOptions=array(), $saveAttrs=array(), $saveOptions=array()) {
		$result = $this->saveData(null, $saveAttrs, $saveOptions);
		if ($result === null) {
			$result = $this->read($id, $findAttrs, $findOptions);
			$this->controller->request->data = $result;
		} else {
			if (!empty($this->controller->request->data[$this->settings['model']]['id'])) {
				$this->read($this->controller->request->data[$this->settings['model']]['id'], $findAttrs, $findOptions);
			}
		}
		$this->setFormElements($id);
		return $result;
	}

	public function delete($id, $options = array()) {
		$redirect = $this->controller->referer();
		$referParams = Router::parse($this->controller->referer(null, true));
		if (
			$redirect == '/' || (
				!empty($referParams['controller']) && 
				$referParams['controller'] == $this->controller->request->params['controller'] && 
				!empty($referParams['action']) && 
				$referParams['action'] == 'view' && (
					(!empty($referParams['pass']) && $referParams['pass'][0] == $id) ||
					(!empty($referParams['named']['id']) && $referParams['named']['id'] == $id)
				)
			)
		) {
			$redirect = array('action' => 'index');
		}
			
		$options = array_merge(array(
			'model' => $this->controller->modelClass,
			'redirect' =>  $redirect,
		), $options);
		extract($options);
		
		$modelHuman = Inflector::humanize(Inflector::tableize($model));
		$success = $this->controller->{$model}->delete($id);
		if ($this->isAjax) {
			echo round($success);
		} else {
			$msg = $success ? 'Deleted ' . $modelHuman . ' info' : 'Could not delete ' . $modelHuman . ' info';
			$this->flash($msg, $success);
			if (!empty($redirect)) {
				$this->controller->redirect($redirect);
			}
		}
	}
	
	
/**
 * Handles the basic code appearing at the top of any add or edit controller function
 * returns true if successfully saved, false if failed at saving, null if no data is detected
 * 
 * @param string/null $model Model to save. Uses controller model if null
 * @param array $passedOptions FormData-specific options
 * @param array $saveOptions Model save options
 * @return bool/null True if success, false if failed, null if no data present
 **/
	public function saveData($model = null, $passedOptions = array(), $saveOptions = array()) {
		if (!empty($this->controller)) {
			$this->controller->disableCache();
		}
		if (empty($model)) {
			$model = $this->controller->modelClass;
		}
		if (isset($this->controller->{$model})) {
			$Model =& $this->controller->{$model};
		} else if (!$Model = ClassRegistry::init($model)) {
			debug("$model not found");
		}
		
		if (!empty($passedOptions['bypassSave'])) {
			return null;
		}
		
		$modelHuman = Inflector::humanize(Inflector::tableize($model));
		$options = array(
			'success' => array(
				'message' => 'Updated ' . $modelHuman . ' info',
				'redirect' => array('action' => 'view', 'ID'),
			),
			'fail' => array(
				'message' => 'Could not update ' . $modelHuman . ' info',
			)
		);
		if ($returnOptions = $this->callControllerMethod('_setSaveDataOptions', $options)) {
			$options = $returnOptions;
		}
		
		if (!empty($passedOptions['success'])) {
			$options['success'] = array_merge($options['success'], $passedOptions['success']);
		}
		if (!empty($passedOptions['fail'])) {
			$options['fail'] = array_merge($options['fail'], $passedOptions['fail']);
		}
		
		if (!empty($this->controller->request->data)) {
			$data =& $this->controller->request->data;
			$result = false;
			$this->_storedData = $data;
			
			if (($data = $this->beforeSaveData($data, $saveOptions)) !== false) {
				if (!empty($data[$model]) && count($data) == 1) {
					if (!empty($data[$model][0])) {
						$result = $Model->save($data[$model], $saveOptions);
					} else {
						$result = $Model->saveAll($data[$model], $saveOptions);
					}
				} else {
					$result = $Model->saveAll($data, $saveOptions);
				}
				$created = !empty($data[$model]) ? empty($data[$model][$Model->primaryKey]) : empty($data[$Model->primaryKey]);
				if ($result) {
					$this->afterSaveData($created);
					$this->_log('Save was successful');
				} else {
					$this->afterFailedSaveData();
					$this->_log(array('Save Failed'));
				}
			} else {
				$this->_log('FormData beforeSaveData failed');
				$data = $this->_storedData;
			}
			
			//Loads default message and redirect values
			$state = $result ? 'success' : 'fail';
			$use = $options[$state] + array('message' => null, 'redirect' => null);
			
			$message = $this->getPostSave($state, 'message', $use['message']);
			$redirect = $this->getPostSave($state, 'redirect', $use['redirect']);

			if (is_array($redirect)) {
				if (($key = array_search('ID', $redirect, true)) !== false) {
					$redirect[$key] = $Model->id;
				}
			}
			
			if ($this->isAjax) {
				echo json_encode([
					'message' => $message,
					'success' => !empty($result),
					'url' => Router::url($redirect, true),
					'id' => $Model->id,
				]);
				exit();
			} else {
				if (!$result) {
					//debug($Model->alias);
					//debug($Model->invalidFields());
				}
				if (!empty($message)) {
					$this->flash($message, $result ? 'success' : 'danger');
				}
			
				if (!empty($redirect)) {
					$this->controller->redirect($redirect);
				}
			}
			
			$this->resetPostSave();
			
			return $result ? true : false;
		}
		return null;
	}

	public function resultToData($result, $attrs = array()) {
		$attrs = $this->setCrudReadAttrs($attrs);
		if (!empty($attrs['options'])) {
			$options = array_merge($options, $attrs['options']);
			unset($attrs['options']);
		}
		extract($attrs);
		$data = array();
		if (!empty($result[$model])) {
			$data[$model] = $result[$model];
			unset($result[$model]);
			$data[$model] += $result;
		}
		return $data;
	}
	
/**
 * Sets default options for the findModel function
 *
 * @param Array $attrs passed options overwriting the defaults
 * @return Array CrudRead $attrs
 **/
	protected function setCrudReadAttrs($attrs = array()) {
		$defaultReferer = $this->controller->referer();
		if ($defaultReferer == '/' || $defaultReferer == Router::url()) {
			$defaultReferer = array('action' => 'index');
		}
		$defaults = array(
			'model' => $this->settings['model'],	//The Model it will be pulling the result from
			'redirect' => $defaultReferer,			//Where to redirect if not found
			'method' => null,						//Specify a method other than find('first',...)
			'passIdToMethod' => false,				//If custom method requires an Id as the first argument
		);
		//Allows for controller overwrite function
		if (method_exists($this->controller, '_setCrudReadAttrs')) {
			$defaults = $this->controller->_setCrudReadAttrs($defaults);
		}
		return array_merge($defaults, (array) $attrs);
	}
	

	function setRefererRedirect() {
		$referer = Router::parse($this->controller->referer(null, true));
		$options = !empty($this->settings['refererRedirect']) ? $this->settings['refererRedirect'] : null;
		$checkKeys = array('controller', 'action');
		$match = true;
		foreach ($checkKeys as $checkKey) {
			$field = 'redirect' . ucfirst($checkKey);
			if (!empty($options[$checkKey])) {
				$checkValues = !is_array($options[$checkKey]) ? array($options[$checkKey]) : $options[$checkKey];
				foreach ($checkValues as $check) {
					if ($referer[$checkKey] == $check && !isset($this->controller->request->data['FormData'][$field])) {
						$this->setData(array('FormData' => array($field => $check)));
					}
				}
			}
		}
	}
	
	function setData($setData = array(), $reset = false) {
		$data =& $this->controller->request->data;
		if (!$reset && !empty($data)) {
			$data = $this->array_merge_data($data, $setData);
		} else {
			$data = $setData;
		}
		return true;
	}
	
	function array_merge_data($array1, $array2) {
		if (array_keys($array2) === range(0, count($array2) - 1)) {	//Resets array if non-associative
			$array1 = array();
		}
		foreach ($array2 as $key => $val) {
			if (isset($array1[$key]) && is_array($array1[$key]) && is_array($val)) {
				$array1[$key] = $this->array_merge_data($array1[$key], $val);
			} else {
				$array1[$key] = $val;
			}
		}
		return $array1;
	}
	
	function setFormElements($id = null) {
		$this->callControllerMethod('_setFormElements', $id);
		$this->setRefererRedirect();
	}
	
	public function flashError($msg) {
		return $this->flash($msg, 'danger');
	}
	
	public function flashSuccess($msg) {
		return $this->flash($msg, 'success');
	}
	
	public function flashInfo($msg) {
		return $this->flash($msg, 'info');
	}
	
	public function flash($msg, $type = 'info') {
		$element = $this->settings['overwriteFlash'] ? self::FLASH_ELEMENT : 'default';
		$this->Session->setFlash(__($msg), $element, $this->_flashParams($type));
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
			$type = 'success';
		} else if ($type === false) {
			$type = 'danger';
		} else if (empty($type)) {
			$type = 'info';
		}
		$class = "alert-$type";
		if ($this->settings['overwriteFlash']) {
			$params['plugin'] = self::PLUGIN_NAME;
		}
		$params['close'] = CakePlugin::loaded('Layout');		//Only adds a close button if Layout plugin is also used
		$params += compact('class');
		return $params;
	}

/**
 * Checks if Model can be searched by slug. If so, returns the slug field. 
 * Otherwise, returns false. 
 * Uses the Sluggable Behavior
 *
 * @param AppModel $Model Model to check
 * @return bool If successful
 **/
	private function _isSluggable($Model) {
		$return = false;
		if (array_key_exists('Sluggable', $Model->actsAs)) {
			$return = !empty($Model->actsAs['Sluggable']['slugColumn']) ? $Model->actsAs['Sluggable']['slugColumn'] : 'slug';
		}
		return $return;
	}
	
	private function _checkCaptcha($data) {
		$this->_log($data);
		if (!isset($data[$this->settings['model']])) {
			return false;
		}
		$checkData = $data[$this->settings['model']];
		$this->_log($checkData);
		if (!empty($this->controller->Captcha) && is_object($this->controller->Captcha) && empty($checkData['captcha_valid'])) {
			$checkData = $this->controller->Captcha->validateData($checkData, true);
			if (isset($checkData['captcha_valid']) && !$checkData['captcha_valid']) {
				$this->_storedData[$this->settings['model']] = $checkData;
				return false;
			}
		}
		$data[$this->settings['model']] = $checkData;
		return $data;
	}
	
	function _log($msg) {
		$this->_log[] = $msg;
	}

	public function getLog() {
		return $this->_log;
	}
	
	function mergeOptions($options1 = array(), $options2 = array()) {
		$options1 = $this->_prepareOptions($options1);
		$options2 = $this->_prepareOptions($options2);
		$return = array_merge($options1, $options2);
		return $return;
	}

	private function _prepareOptions($options) {
		$numericKeys = array('link', 'contain');
		foreach ($numericKeys as $field) {
			if (isset($options[$field])) {
				$options[$field] = $this->_numericKeysFix($options[$field]);
			}
		}
		foreach ($options as $key => $val) {
			if (!empty($val) && !is_array($val)) {
				$options[$key] = array($val);
			}
		}
		return $options;
	}
	
	private function _numericKeysFix($array = array()) {
		if (!is_array($array)) {
			$array = array($array);
		}
		$return = array();
		foreach ($array as $key => $val) {
			if (is_array($val)) {
				$return[$val] = $this->_numericKeysFix($val);
			} else if (is_numeric($key)) {
				$return[$val] = array();
			} else {
				$return[$key] = $val;
			}
		}
		return $return;
	}
	
	private function setPostSave($state, $vars) {
		foreach ($vars as $k => $v) {
			if (isset($v)) {
				$this->_postSave[$state][$k] = $v;
			}
		}
	}
	
	private function getPostSave($state, $field, $default = null) {
		if (!empty($this->_postSave[$state][$field])) {
			return $this->_postSave[$state][$field];
		} else {
			return $default;
		}
	}
	
	private function resetPostSave($state = null) {
		if (!empty($state)) {
			$this->_postSave[$state] = array();
		} else {
			foreach ($this->_postSave as $key => $val) {
				$this->resetPostSave($key);
			}
		}
	}
	
/**
 * Checks to see if a controller method exists and calls it
 *
 * @param String $methodName The name of the method to call in the controller
 * @param [Mixed $...] Optional parameters to pass to method
 *
 * @return Boolean|NULL Returns the method if it exists, null if it does not
 **/
	private function callControllerMethod($methodName) {
		$args = func_get_args();
		array_shift($args);	//Removes method name
		if (method_exists($this->controller, $methodName)) {
			return call_user_func_array(array($this->controller, $methodName), $args);
		} else {
			return null;
		}
	}

	private function isSerialized() {
		return $this->isRequestType(array('ajax', 'json', 'xml', 'rss', 'atom'));
	}

	private function isRequestType($type) {
		if (is_array($type)) {
			foreach ($type as $val) {
				if ($this->isRequestType($val)) {
					return true;
				}
			}
			return false;
		}
		
		/*
		debug([
			'Type' => $type,
			'Ext' => !empty($this->controller->request->params['ext']) ? $this->controller->request->params['ext'] : 'None',
			'Request Accepts' => $this->controller->request->accepts($type),
			'RequestHandler Accepts' => $this->controller->RequestHandler->accepts($type),
			'RequestHandler Prefers' => $this->controller->RequestHandler->prefers($type),
			'Is' => $this->controller->request->is($type)
		]);
		return false;
		*/
		if ($type == 'ajax') {
			return !empty($this->controller->request) && $this->controller->request->is('ajax');
		}
		return $this->controller->RequestHandler->prefers($type);
	}
}