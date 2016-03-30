<?php
App::uses('Hash', 'Utility');
App::uses('Inflector', 'Utility');
App::uses('Router', 'Utility');

App::uses('CakeLog', 'Log');
App::uses('Debugger', 'Utility');


class FormDataComponent extends Component {
	public $name = 'FormData';
	public $components = array('Session', 'RequestHandler', 'FormData.JsonResponse');
	
	public $controller;
	public $settings = array();

	public $isAjax = false;
	
	// The ID of the current model
	public $id = null;

	private $_log = array();
	private $_storedData = array();
	
	private $_postSave = array(
		'success' => array(),
		'fail' => array(),
	);
	
	private $_vars = array();

	const FLASH_ELEMENT = 'alert';
	
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
		//$this->setSuccessRedirect(array('action' => 'view', 'ID'));
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
		$this->overwriteFlash();
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

	public function resultsToData($results, $attrs = array()) {
		extract($attrs);
		$return = [];
		foreach ($results as $k => $row) {
			$modelResult = $row[$model];
			unset($row[$model]);
			$return[$model][$k] = $modelResult + $row;
		}
		return $return;
	}

	public function resultToData($result, $attrs = array()) {
		$attrs = $this->setFindModelAttrs($attrs);
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
 * Returns a list of model entries
 * 
 * @param Array $query Additional query parameters to pass to the model
 * @param Array $options Options to customize the result
 * 		- 'paginate'	Should the result be a paged value
 * 		- 'method'		A method other than 'find' to retrieve the results
 * 		- 'varName'		The name of the returned result. 
 *
 * @return The found Model result
 **/
	public function findAll($query = null, $options = array()) {
		$Model = $this->getModel();
		$options = array_merge(array(
			'paginate' => true,
			'method' => false,
			'varName' => Inflector::pluralize(Inflector::variable($Model->alias)),
		), $options);
		extract($options);

		if ($method) {
			$result = $Model->{$method}($query);
		} else if ($paginate) {
			if (isset($query)) {
				$this->controller->paginate = $query;
			}
			$result = $this->controller->paginate();
		} else {
			$result = $Model->find('all', $query);
		}

		$this->set($varName, $result);
		return $result;
	}

/**
 * Finds one entry from the current model
 * 
 * @param int $id The current model ID
 * @param Array $attrs Options to be passed 
 * @param Array $options Additional query information to be passed to the Model's find method
 * 
 * @return Array|bool The resulting array if found, false if not
 **/
	public function findModel($id = null, $attrs = array(), $options = array()) {
		$attrs = $this->setFindModelAttrs($attrs);
		if (!empty($attrs['options'])) {
			$options = array_merge($options, $attrs['options']);
			unset($attrs['options']);
		}
		extract($attrs);

		$Model = $this->getModel($model);
		
		if (empty($options)) {
			$options = array();
		}

		if (!empty($id) || empty($options['conditions'])) {
			if (!is_numeric($id) && ($slugField = $this->_isSluggable($Model))) {
				//Searches by slug
				$conditions = array($Model->escapeField($slugField) . ' LIKE' => $id);
			} else {
				//Searches by primary ID
				$conditions = array($Model->escapeField() => $id);
			}
			$options = Set::merge($options, compact('conditions'));
		}
		
		$controller = Inflector::tableize($model);
		$varName = strtolower(substr($model, 0, 1)) . substr($model, 1);
		$human = Inflector::humanize(Inflector::singularize($controller));
		
		$this->id = $id;
		
		if ($vars = $this->callControllerMethod('_beforeFindModel', $options)) {
			$options = $vars;
		}
		
		if ($vars = $this->callControllerMethod('_setFindModelOptions', $options)) {
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
		
		if ($afterFindResult = $this->callControllerMethod('_afterFindModel', $result)) {
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

		// Result was not found
		if (empty($result[$Model->alias][$Model->primaryKey])) {
			$errorMessage = sprintf('FormDataComponent could not find model "%s" %s: %d', $Model->alias, $Model->primaryKey, $id);
			$errorMessage .= "\nRequest URL: " . $this->controller->request->here();
			$errorMessage .= "\nStack Trace:\n" . Debugger::trace();
			CakeLog::write('error', $errorMessage);
			$message .= sprintf("! %s, %s Looking for ID: %d<br/>\n", $Model->alias, $Model->primaryKey, $id);
			if (is_array($result)) {
				$message .= 'Keys: ' . implode(', ', array_keys($result)) . "<br/>\n";
				//$message .= 'Body: ' . implode(', ', $result);
			}
			//$message .= implode('<br/>', debugTrace('Trace'));
			$message .= '<br/>' . Router::url($redirect);

			if (!$this->isAjax && !empty($redirect)) {
				$this->flash($message, 'danger');	
				$this->controller->redirect($redirect);
			} else {
				throw new NotFoundException($message);
			}
		}

		$this->set($varName, $result);
		return $result;
	}

/**
 * Sets default options for the findModel function
 *
 * @param Array $attrs passed options overwriting the defaults
 * @return Array FindModel $attrs
 **/
	protected function setFindModelAttrs($attrs = array()) {
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
		if (method_exists($this->controller, '_setFindModelAttrs')) {
			$defaults = $this->controller->_setFindModelAttrs($defaults);
		}
		return array_merge($defaults, (array) $attrs);
	}
	
/**
 * Saves new request data
 *
 * @param array $options addData options, including default values to be passed to the form
 * @param array $saveAttrs FormData->saveData options
 * @param array $saveOptions Model->save options
 * @return array Model result
 */
	public function addData($options = array(), $saveAttrs = array(), $saveOptions = array()) {
		$result = $this->saveData(null, $saveAttrs, $saveOptions);
		if ($result === null && !empty($options['default'])) {
			$this->setData($options['default'], true);
		}		
		$this->setFormElements();
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
	public function editData($id, $findAttrs=array(), $findOptions=array(), $saveAttrs=array(), $saveOptions=array()) {
		$result = $this->saveData(null, $saveAttrs, $saveOptions);
		if ($result === null) {
			$result = $this->findModel($id, $findAttrs, $findOptions);
			$this->controller->request->data = $result;
		} else {
			if (!empty($this->controller->request->data[$this->settings['model']]['id'])) {
				$this->findModel($this->controller->request->data[$this->settings['model']]['id'], $findAttrs, $findOptions);
			}
		}
		$this->setFormElements($id);
		return $result;
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
		} else if (!$Model = ClassRegistry::init($model, true)) {
			debug("$model not found");
		}
		
		if (!empty($passedOptions['bypassSave']) || !empty($this->controller->request->data['bypass_save'])) {
			unset($this->controller->request->data['bypass_save']);
			return null;
		}
		
		list($plugin, $model) = pluginSplit($model);
		$modelHuman = Inflector::humanize(Inflector::tableize($model));
		$options = array(
			'success' => array(
				'message' => 'Updated ' . $modelHuman . ' entry',
				'redirect' => null,
			),
			'fail' => array(
				'message' => 'Could not update ' . $modelHuman . ' entry',
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
			//debug($data);

			if (($data = $this->beforeSaveData($data, $saveOptions)) !== false) {
				if (!empty($data[$model]) && count($data) == 1) {
					if (empty($data[$model][0])) {
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
					if (Configure::read('debug') == 2 && !empty($Model->validationErrors)) {
						$options['fail']['message'] .= $this->getValidationErrorsList($Model->validationErrors);
					}
					$this->_log(array('Save Failed'));
				}
			} else {
				$this->_log('FormData beforeSaveData failed');
				$data = $this->_storedData;
			}
			
			//Loads default message and redirect values
			$state = $result ? 'success' : 'fail';
			$use = $options[$state] + array('message' => null, 'redirect' => null);

			// Sets default redirect back to view on success
			if ($state === 'success' && empty($use['redirect']) && $use['redirect'] !== false) {
				$use['redirect'] = array(
					'controller' => Inflector::tableize($Model->alias),
					'action' => 'view', 
					'ID'
				);
			}
			
			$message = $this->getPostSave($state, 'message', $use['message']);
			$redirect = $this->getPostSave($state, 'redirect', $use['redirect']);
		
			if (!empty($redirect)) {
				if (is_array($redirect)) {
					if (($key = array_search('ID', $redirect, true)) !== false) {
						$redirect[$key] = $Model->id;
					}
					if (!empty($data['FormData']['redirectAction'])) {
						$redirect['action'] = $data['FormData']['redirectAction'];
					}
					if (!empty($data['FormData']['redirectController'])) {
						$redirect['controller'] = $data['FormData']['redirectController'];
					}
					$redirect = Router::url($redirect, true);
				}
			}
			
			if ($this->isAjax) {
				$this->JsonResponse->respond(
					$message,
					!empty($result),
					$redirect,
					$Model->id,
					$Model->validationErrors
				);
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

	private function getValidationErrorsList($errors, $depth = 0) {
		$list = "<ul>";
		foreach ($errors as $key => $error) {
			$list .= "<li>";
			if (!is_numeric($key)) {
				$list .= "$key: ";
			}
			if (is_array($error)) {
				$list .= $this->getValidationErrorsList($error, $depth + 1);
			} else {
				$list .= $error;
			}
			$list .= "</li>";
		}
		$list .= "</ul>";
		return $list;
	}
	
/**
 * Deletes data from the current model
 *
 * @param int $id The model ID to delete
 * @param Array $options Additional options
 *
 * @return void
 **/
	public function deleteData($id, $options = array()) {
		$redirect = $this->controller->referer();
		$referParams = Router::parse($this->controller->referer(null, true));

		// Ensures it doesn't redirect to a view that references the deleted value
		if (
			$redirect == '/' || (
				!empty($referParams['controller']) && 
				$referParams['controller'] == $this->controller->request->params['controller'] && 
				(
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
 * Looks in the requested data for an HABTM list of ids
 *
 * @param string $modelName The name of the model you're searching for
 * @return array A list of ($id => $title) values
 **/
	public function findHabtmList($modelName) {
		$controllerModelClass = $this->controller->modelClass;
		if (!empty($this->controller->$controllerModelClass->hasAndBelongsToMany[$modelName]['className'])) {
			$className = $this->controller->$controllerModelClass->hasAndBelongsToMany[$modelName]['className'];
		} else {
			$className = $modelName;
		}
		$data =& $this->controller->request->data;
		if (!empty($data[$modelName][0])) {
			// Initially pulled from the database
			$extract = $modelName . '.{n}.id';
		} else if (!empty($data[$modelName][$modelName])) {
			// Passed as a form
			$extract = $modelName . '.' . $modelName . '.{n}';
		} else {
			$extract = false;
		}
		if ($extract) {
			$Model = ClassRegistry::init($className);
			return $Model->find('list', [
				'conditions' => [$Model->escapeField() => Hash::extract($data, $extract)]
			]);
		}
		return null;
	}

/**
 * Stores a variable to be set to the controller
 *
 * @param String|Array $name Either the name of the variable, or an array of variables to be set
 * @param String|null $value If $name is a string, the corresponding value
 *
 * @return void;
 **/
	private function set($name, $value = null) {
		if (is_array($name)) {
			foreach ($name as $k => $v) {
				$this->set($k, $v);
			}
		} else {
			$this->_vars[$name] = $value;
		}
	}


/**
 * OVerwrites the default Session Flash parameters
 *
 **/
	private function overwriteFlash() {
		$session = 'Message';
		if (!empty($this->settings['overwriteFlash']) && $this->Session->check($session)) {
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
	
	public function flash($msg, $type = self::INFO) {
		$element = $this->settings['overwriteFlash'] ? self::FLASH_ELEMENT : 'default';
		$params = $this->_flashParams($type);
		// Uses the new Flash Component if present
		if (!empty($this->controller->Flash)) {
			$paramKeys = ['element', 'key'];
			$attrs = [];
			if (!empty($params['key'])) {
				$attrs['key'] = $params['key'];
			}
			if (!empty($params['plugin'])) {
				$element = $params['plugin'] . '.' . $element;
			}
			$attrs = compact('element', 'params');
			return $this->controller->Flash->set(__($msg), $attrs);
		} else {
			return $this->Session->setFlash(__($msg), $element, $params);
		}
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
		$params['plugin'] = $this->name;
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
		return $this->RequestHandler->prefers($type);
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


	private function getModel($model = null) {
		if (empty($model)) {
			$model = $this->settings['model'];
		}
		if (method_exists($this, $model)) {
			$Model =& $this->controller->{$model};
		} else {
			$importModel = $model;
			if (!empty($this->settings['plugin'])) {
				$importModel = $this->settings['plugin'] . '.' . $model;
			}
			$Model = ClassRegistry::init($importModel, true);
			if (empty($Model) && !empty($this->controller->plugin) && empty($this->settings['plugin'])) {
				$Model = ClassRegistry::init($this->controller->plugin . '.' . $importModel, true);
			}
		}
		return $Model;
	}
}