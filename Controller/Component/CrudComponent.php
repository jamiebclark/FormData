<?php
App::uses('Hash', 'Utility');
App::uses('CakeLog', 'Log');

class CrudComponent extends Component {
	public $name = 'Crud';

/**
 * Other components used by CrudComponent
 * 
 * @var array
 **/
	public $components = array('Flash', 'Session', 'RequestHandler', 'FormData.JsonResponse');

/**
 * Reference to the instantiating controller object
 *
 * @var Controller
 **/
	public $controller;

/**
 * Request object
 *
 * @var CakeRequest
 */
	public $request;

/**
 * Response object
 *
 * @var CakeResponse
 */
	public $response;

/**
 * Method list for bound controller.
 *
 * @var array
 */
	protected $_methods = array();

/**
 * The referring URL
 *
 * @var string
 **/
	protected $_referer = null;

/**
 * The reffering URL in array format
 * @var array
 **/
 	protected $_refererParts = array();

/**
 * Variables to pass back to the controller
 *
 * @var array
 **/
	protected $_vars = array();

/**
 * The class name of the model used with the component
 *
 * @var string
 **/
	public $modelClass = null;

/**
 * The human name of the model used in the component
 *
 * @var string
 **/
	public $modelHuman = null;

/**
 * The variable name of the model used in the component
 *
 * @var string
 **/
	public $modelVariable = null;

/**
 * The Model object working with the component
 *
 * @var Model
 **/
	public $Model = null;

/**
 * The controller-specific settings within the Component
 *
 * @var array
 **/
	public $settings = array();

/**
 * Determines if the response should be in JSON or not
 *
 * @var bool
 **/
	public $jsonResponse;

/**
 * Whether the call is an Ajax call
 *
 * @var bool
 **/
	protected $isAjax = false;
	
	private $_log = array();
	public static $_errors = [];

/**
 * Maintains a copy of the request data before it's manipulated by the component
 *
 * @var array
 **/
	private $_storedData = array();
	
	private $_postSave = array(
		'success' => array(),
		'fail' => array(),
	);
	
	const FLASH_ELEMENT = 'alert';
	const PLUGIN_NAME = 'FormData';
	
	const ERROR_CLASS = 'alert-danger';
	const WARNING_CLASS = 'alert-warning';
	const INFO_CLASS = 'alert-info';
	const SUCCESS_CLASS = 'alert-success';

	const ERROR = 0;
	const INFO = null;
	const SUCCESS = 1;
	const WARNING = 2;

	const ID_REPLACE = '__ID__';

	public function __construct(ComponentCollection $collection, $settings = array()) {
		$settings = array_merge(array(
			'overwriteFlash' 	=> true,		// Whether or not to overwrite the default flash element
			'postDelete' 		=> false,		// If true, will only delete from a post request type
			'respond'			=> null,
			'active'			=> true
		), $settings);
		return parent::__construct($collection, $settings);			
	}

#section CakePHP Callbacks

/**
 * Initializes CrudComponent for use in the controller.
 *
 * @param Controller $controller A reference to the instantiating controller object
 * @return void
 */
	public function initialize(Controller $controller) {
		$this->controller = $controller;

		$this->request = $controller->request;
		$this->response = $controller->response;
		$this->_methods = $controller->methods;
		$this->_referer = $controller->referer();
		$this->_refererParts = Router::parse($controller->referer(null, true));
		
		$this->isAjax = $this->isRequestType(['ajax']);
		if ($this->settings['respond'] == 'json') {
			$this->jsonResponse = true;
		} else if (!isset($this->jsonResponse)) {
			$this->jsonResponse = $this->isAjax;
		}

		$modelClass = $controller->modelClass;
		if (!empty($controller->plugin)) {
			$modelClass = $controller->plugin . '.' . $modelClass;
		}
		if (!empty($this->settings['active'])) {
			$this->setModelClass($modelClass);
		}
	}

/**
 * Sets the model to be used with the component
 *
 * @param string $modelClass Class name of the model
 * @return void
 **/
	public function setModelClass($modelClass) {
		list($plugin, $model) = pluginSplit($modelClass);

		$this->modelClass = $model;

		$controller = Inflector::tableize($model);
		$this->modelVariable = Inflector::variable($model);
		$this->modelHuman = Inflector::humanize(Inflector::singularize($controller));
		$this->modelPlugin = $plugin;

		if (!($this->Model = ClassRegistry::init($modelClass, true))) {
			// throw new Exception("Could not set model: $modelClass");
		}
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

	public function getFormDefaults() {
		return $this->callControllerMethod('_getFormDefaults');
	}

	public function getFormElements($id = null) {
		$formElements = [];
		if (method_exists($this->controller, '_setFormElements')) {
			$oViewVars = $this->controller->viewVars;
			$this->callControllerMethod('_setFormElements', $id);
			$formElements = array_diff_key($this->controller->viewVars, $oViewVars);
		}
		if ($this->jsonResponse) {
			$formElements = $this->wrapListChildren($formElements);
		}

		return $formElements;
	}

/**
 * Wraps every array value in another array
 * Prevents sorting of JSON keys
 *
 * @param array $list;
 * @return array;
 **/
	public function wrapList($list) {
		if (is_array($list)) {
			$newList = [];
			foreach ($list as $key => $v) {
				$newList[] = ['key' => $key, 'value' => $this->wrapList($v)];
			}
			$list = $newList;
		}
		return $list;
	}

	private function wrapListChildren($list) {
		foreach ($list as $k => $v) {
			$list[$k] = $this->wrapList($v);
		}
		return $list;
	}

	private function overwriteFlash() {
		//Overwrites the current Flash setup
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
				list($plugin, $element) = pluginSplit($flash['element']);
				if (!empty($plugin)) {
					$flash['params']['plugin'] = $plugin;
					$flash['element'] = $element;
				}
				$this->Session->write("$session.$var", $flash);
			}
		}	
	}
	
/**
 * Saves new request data
 *
 * @param array $options create options, including default values to be passed to the form
 * @param array $saveAttrs Crud->save options
 * @param array $saveOptions Model->save options
 * @return array Model result
 */
	public function create($options = array(), $saveAttrs = array(), $saveOptions = array()) {
		$result = $this->save($saveAttrs, $saveOptions);
		if ($default = $this->getFormDefaults()) {
			$oDefault = !empty($options['default']) ? $options['default'] : [];
			$options['default'] = Hash::merge($default, (array) $oDefault);
		}
		if ($result === null && !empty($options['default'])) {
			$result = $this->setData($options['default'], true);
		}		
		$this->setFormElements();
		$view = isset($options['view']) ? $options['view'] : null;
		if ($view !== true) {
			$this->formRender();
		}
		return $result;
	}


	
/**
 * Reads a single model row. Usually used with the "view" controller method
 *
 * @param int $id The model id to be read
 * @param array $query Any additional find query options
 * @return array|bool The value of the returned set if found, false if not;
 **/
	public function read($id = null, $attrs = array(), $query = array()) {
		$attrs = $this->setCrudReadAttrs($attrs);
		if (!empty($attrs['query'])) {
			$query = array_merge($query, $attrs['query']);
			unset($attrs['query']);
		}
		extract($attrs);

		$primaryKey = $this->Model->primaryKey;
		$alias = $this->Model->alias;

		if (empty($query)) {
			$query = array();
		}

		if (!empty($id) || empty($query['conditions'])) {
			if (!is_numeric($id) && ($slugField = $this->_getSluggableField($this->Model))) {
				//Searches by slug
				$conditions = array($this->Model->escapeField($slugField) . ' LIKE' => $id);
			} else {
				//Searches by primary ID
				$conditions = array($this->Model->escapeField() => $id);
			}
			$query = Set::merge($query, compact('conditions'));
		}
		
		$this->id = $id;
		
		if ($vars = $this->callControllerMethod('_beforeCrudRead', $query)) {
			$query = $vars;
		}
		
		if ($vars = $this->callControllerMethod('_setCrudReadOptions', $query)) {
			$query = $vars;
		}

		if (!empty($method) && method_exists($this->Model, $method)) {
			if (!empty($passIdToMethod)) {
				$result = $this->Model->{$method}($this->id, $query);
			} else {
				$result = $this->Model->{$method}($query);
			}
		} else {
			$result = $this->Model->find('first', $query);
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
			$message = $this->modelHuman . " is not found";
		}

		if (empty($result[$alias][$primaryKey]) && !empty($redirect)) {
			$message .= sprintf("! %s, %s Looking for ID: %d<br/>\n", $alias, $primaryKey, $id);
			if (is_array($result)) {
				$message .= 'Keys: ' . implode(', ', array_keys($result)) . "<br/>\n";
				//$message .= 'Body: ' . implode(', ', $result);
			}
			//$message .= implode('<br/>', debugTrace('Trace'));
			$message .= '<br/>' . Router::url($redirect);

			$this->flash($message, 'danger');
			$this->redirect($redirect);
		}

		$this->controller->set($this->modelVariable, $result);
		return $result;
	}

	protected function readData($id, $read, $query, $convertHabtm = true) {
		$data = $this->read($id, $read, $query);
		// Converts any HABTM data into an array of only the IDs
		if($convertHabtm) {
			foreach ($this->Model->hasAndBelongsToMany as $associated => $joins) {
				if (!empty($data[$associated][0][$this->Model->primaryKey])) {
					$ids = Hash::extract($data, $associated . '.{n}.' . $this->Model->primaryKey);
					$data[$associated] = array($associated => $ids);
				}
			}
		}
		return $data;
	}

/**
 * Saves request data based on an existing model id. 
 * Regardless of whether it saves anything, it will still find and store the model id result
 *
 * @param int $id The id of the model being saved
 * @param array $readAttrs Crud->read() options
 * @param array $query Model->find options
 * @param array $saveAttrs Crud->save options
 * @param array $saveOptions Model->save options
 * @return array Model result
 **/
	//public function update($id, $readAttrs = array(), $query = array(), $saveAttrs = array(), $saveOptions = array()) {
	public function update ($id, $options = array()) {
		$options = array_merge(array(
			'read' => array(),
			'save' => array(),
			'saveOptions' => [],
			'query' => array(),
			'convertHabtm' => true,
		), $options);
		extract($options);

		$result = $this->save($save, $saveOptions);
		if ($result === null) {
			$data = $this->readData($id, $read, $query, $convertHabtm);
			$this->request->data = $data;
			$result = $data;
		} else {
			if (!empty($this->request->data[$this->modelClass]['id'])) {
				$result = $this->read($this->request->data[$this->modelClass]['id'], $read, $query);
			}
		}

		$this->setFormElements($id);
		$this->formRender(isset($read['view']) ? $read['view'] : null);
		return $result;
	}

/**
 * Accepts either a single numeric value, a comma separated list of values, or an array of values
 *
 * @param string|int|array $id The mixed id values
 * @return array An array of the id values
 **/
	public function getIds($id) {
		$ids = [];
		if (is_array($id)) {
			$ids = $id;
		} else if (strpos($id, ',')) {
			$ids = explode(',', $id);
		} else if (is_numeric($id)) {
			$ids = [$id];
		}
		return $ids;
	}

/**
 * Deletes a model entry. Usually used with the "delete" controller method.
 *
 * @param int $id The model id to be deleted
 * @return void;
 **/
	public function delete($id = null, $options = array()) {
		// Skips the delete if the request isn't being sent via POST
		if (!empty($this->settings['postDelete']) && !$this->request->is('post')) {
			$this->redirect(true);
		}

		$controller = Inflector::tableize($this->modelClass);
		$defaultUrl = compact('controller') + array('action' => 'index');
		$referer = $this->_refererParts;
		$success = false;

		// If the referring URL is from the associating view of the same ID, redirect to the index. 
		// Otherwise redirect to referer
		$successRedirect = true;
		if (
			is_array($referer) && 
			(isset($referer['controller']) && $referer['controller'] == $controller) && 
			(isset($referer['action']) && $referer['action'] == 'view') &&
			(isset($referer['pass'][0]) && $referer['pass'][0] == $id)
		) {
			$successRedirect = $defaultUrl;
		}

		$ids = $this->getIds($id);

		if (count($ids) == 1) {
			$label = 'id #' . $ids[0];
		} else {
			$label = 'ids #' . implode(',', $ids);
		}

		$result = $this->Model->find('all', [
			'fields' => [$this->Model->primaryKey, $this->Model->displayField],
			'conditions' => [
				$this->Model->escapeField() => $ids,
			]
		]);

		$default = array(
			'callbacks' => true,
			'cascade' => true,

			'success' => array(
				'message' => 'Successfully deleted ' . $label,
				'type' => self::SUCCESS,
				'redirect' => $successRedirect,
			),
			'fail' => array(
				'message' => 'There was an error deleting ' . $label,
				'type' => self::ERROR,
				'redirect' => true,
			),
			'notFound' => array(
				'message' => 'Please select an id',
				'type' => self::ERROR,
				'redirect' => true,
			)
		);

		/*
		if (!empty($result) && !empty($result[$this->Model->alias][$this->Model->displayField])) {
			$default['success']['message'] = sprintf('Successfully deleted %s "%s"', 
				$this->Model->alias,
				$result[$this->Model->alias][$this->Model->displayField]
			);
		}
		*/

		$options = Hash::merge($default, $options);

		if (empty($ids) || empty($result)) {
			extract($options['notFound']);
		} else {
			if ($success = $this->Model->deleteAll(
					[$this->Model->escapeField() => $ids], 
					$options['cascade'], 
					$options['callbacks']
			)) {
				extract($options['success']);
			} else {
				extract($options['fail']);
			}
		}

		if ($this->jsonResponse) {
			$this->JsonResponse->respond(
				$message,
				$success,
				Router::url($redirect, true)
			);
		} else {
			$this->flash($message, $type);
			$this->redirect($redirect);
		}
	}
	
	public static function errorHandler($code, $message, $file = null, $line = null) {
		if (!empty($file)) {
			self::$_errors[] = sprintf('Error: %s in file `%s` on line %s', $message, $file, $line);
		} else {
			self::$_errors[] = sprintf('Error: %s', $code);
		}
	}

	public static function fatalErrorShutdownHandler() {
		$lastError = error_get_last();
		if ($lastError['type'] === E_ERROR) {
			// fatal error
			self::errorHandler(E_ERROR, $lastError['message'], $lastError['file'], $lastError['line']);
		}
	}
	
	public function validate($data = false) {
		if ($data === false) {
			$data = $this->_getData();
		}
		$validationErrors = '';
		$validationErrorList = [];
		if (!empty($data)) {
			$this->Model->set($data);
			if ($this->Model->validates()) {
				$message = $this->modelHuman . ' is valid';
				$response = self::SUCCESS;
			} else {
				$message = $this->modelHuman . ' is NOT valid';
				$validationErrors = $this->_getValidationErrors();
				$validationErrorList = $this->_getValidationErrorList();
				$response = self::ERROR;
			}
		} else {
			$message = 'No data found. Cannot validate';
			$response = self::WARNING;
		}

		// Executes wrapup
		if ($this->jsonResponse) {
			// Only outputs JSON if call was AJAX
			$this->JsonResponse->respond(
				$message,
				$response,
				false,
				false,
				(array) $validationErrorList,
				Hash::merge(
					['Data' => (array) $data],
					['Log' => (array) $this->getLog()],
					['Errors' => (array) $this->_errors], 
					['Save' => []], 
					['Validation' => (array) $validationErrorList], 
					['Validation2' => (array) $validationErrors],
					['Model: ' . $this->Model->alias]
				)
			);
		} else {
			$this->flash($message . $validationErrors, $response);
		}
	}

/**
 * Handles the basic code appearing at the top of any add or edit controller function
 * returns true if successfully saved, false if failed at saving, null if no data is detected
 * 
 * @param string/null $model Model to save. Uses controller model if null
 * @param array $options FormData-specific options
 * @param array $saveOptions Model save options
 * @return bool/null True if success, false if failed, null if no data present
 **/
	public function save($options = array(), $saveOptions = array()) {
		if ($this->jsonResponse) {
			set_error_handler(['CrudComponent', 'errorHandler']);
		}
		if (!empty($this->controller)) {
			$this->controller->disableCache();
		}
		
		if (!empty($this->request->data['bypass_save']) || !empty($options['bypassSave'])) {
			unset($this->request->data['bypass_save']);
			return null;
		}

		$defaultOptions = array(
			// Wrapup instructions if save is successful
			'success' => array(
				'message' => 'Updated ' . $this->modelHuman,
				'redirect' => array(
					'controller' => Inflector::tableize($this->modelClass),
					'action' => 'view', 
					self::ID_REPLACE
				),
			),
			// Wrapup instructions if save fales
			'fail' => array(
				'message' => 'Could not update ' . $this->modelHuman,
				'redirect' => false,
			)
		);
		if ($returnOptions = $this->callControllerMethod('_setSaveDataOptions', $defaultOptions)) {
			$defaultOptions = $returnOptions;
		}
		
		$alias = $this->Model->alias;
		$primaryKey = $this->Model->primaryKey;

		// Makes sure there's a clean merge betwen redirect URLs
		foreach (array('success', 'fail') as $key) {
			if (!empty($options[$key]['redirect'])) {
				$defaultOptions[$key]['redirect'] = $options[$key]['redirect'];
				unset($options[$key]['redirect']);
			}
		}
		$options = Hash::merge($defaultOptions, $options);

		// Copies any saveOptions fields from options
		$saveArgs = array('validate', 'atomic', 'fieldList', 'deep', 'callbacks');
		foreach ($saveArgs as $arg) {
			if (isset($options[$arg])) {
				$saveOptions[$arg] = $options[$arg];
				unset($options[$arg]);
			}
		}

		$validationErrorList = [];
		$saveErrors = [];

		// Checks for passed data
		if ($this->_hasData()) {
			$result = false;
			// Before save
			if (($data = $this->_getData($saveOptions)) !== false) {
				// Saves data
				try {

					$deadlockRetries = 4;
					$deadlockPause = 1;

					for ($i = 0; $i < $deadlockRetries; $i++):
						$retry = false;
						try {
							// Saves data
							if (!empty($data[$alias]) && count($data) == 1) {
								if (!empty($data[$alias][0])) {
									$result = $this->Model->save($data[$alias], $saveOptions);
								} else {
									$result = $this->Model->saveAll($data[$alias], $saveOptions);
								}
							} else {	
								$result = $this->Model->saveAll($data, $saveOptions);
							}
						} catch (PDOException $e) {
							// Checks for a possible deadlock in reporting
							$message = $e->getMessage();
							if (strpos($message, 'Deadlock') !== false) {
								// If a deadlock is found, wait a second and try again
								CakeLog::write('error', 
									sprintf('Found a deadlock. Retrying: $message', $message)
								);
								sleep($deadlockPause);
								$retry = true;
							} else {
								throw $e;
							}			
						}
						if (!$retry) {
							break;
						}
					endfor;

					$created = !empty($data[$alias]) ? empty($data[$alias][$primaryKey]) : empty($data[$primaryKey]);
				} catch (Exception $e) {
					if (true || $this->jsonResponse) {
						$result = false;
						$saveErrors[] = $e->getMessage();
					} else {
						throw($e);
					}
				}

				// After save
				if ($result) {
					$this->afterSaveData($created);
					$this->_log('Save was successful');
				} else {
					$this->afterFailedSaveData();
					$this->_log('Save Failed');
					$validationErrors = $this->_getValidationErrors();
					$validationErrorList = $this->_getValidationErrorList();
				}
			} else {
				$data = $this->_storedData;
			}
			
			// Loads default message and redirect values
			$state = $result ? 'success' : 'fail';
			$use = $options[$state] + array('message' => null, 'redirect' => null);
			
			if (!$result) {
				$error = ['FormData.Crud save error'];
				if (!empty($validationErrorList)) {
					$error[] = 'Invalid Fields:';
					foreach ($validationErrorList as $field => $rules) {
						foreach ($rules as $rule) {
							$error[] = " - $field: $rule";
						}
					}
				}
				if (!empty($saveErrors)) {
					$error[] = 'Save Errors:';
					$error[] = implode("\n - ", $saveErrors);
				}
				CakeLog::write('FormData_invalid', implode("\n", $error));
			}

			$message = $this->getPostSave($state, 'message', $use['message']);
			$redirect = $this->getPostSave($state, 'redirect', $use['redirect']);

			if (empty($validationErrors)) {
				$validationErrors = [];
			}

			if (is_array($redirect)) {
				if (($key = array_search(self::ID_REPLACE, $redirect, true)) !== false) {
					$redirect[$key] = $this->Model->id;
				}
			}

			//mail('jamie@souperbowl.org', "LOG", implode("\n", $this->getLog()));

			// Executes wrapup
			if ($this->jsonResponse) {
				// Only outputs JSON if call was AJAX
				$this->JsonResponse->respond(
					$message,
					!empty($result),
					Router::url($redirect, true),
					$this->Model->id,
					(array) $validationErrorList,
					Hash::merge(
						['Data' => (array) $data],
						['Log' => (array) $this->getLog()],
						['Errors' => (array) $this->_errors], 
						['Save' => (array) $saveErrors], 
						['Validation' => (array) $validationErrorList], 
						['Validation2' => (array) $validationErrors],
						['Model: ' . $this->Model->alias]
					)
				);
			} else {
				if (!empty($message)) {
					$this->flash($message, $result ? self::SUCCESS : self::ERROR);
				}
				$this->redirect($redirect);
			}
			$this->resetPostSave();
			return $result ? true : false;
		}
		return null;
	}

	protected function _hasData() {
		return !empty($this->request->data);
	}

	protected function _getData($saveOptions = []) {
		$data =& $this->request->data;
		$this->_storedData = $data;
	
		// HABTM Updates
		foreach ($this->Model->hasAndBelongsToMany as $associated => $join) {
			if (!empty($data[$associated][$associated])) {
				// Removes blank values
				foreach ($data[$associated][$associated] as $key => $val) {
					if (empty($val) || is_array($val)) {
						unset($data[$associated][$associated][$key]);
					}
				}
				// De-dups the ids
				if (!empty($data[$associated][$associated])) {
					$data[$associated][$associated] = array_keys(array_flip($data[$associated][$associated]));
				}
			}
		}	

		// Before Save
		if (($data = $this->beforeSaveData($data, $saveOptions)) === false) {
			$this->_log('FormData beforeSaveData failed');
			return false;
		}
		return $data;
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
			'model' => $this->modelClass,			// The Model it will be pulling the result from
			'redirect' => $defaultReferer,			// Where to redirect if not found
			'method' => null,						// Specify a method other than find('first',...)
			'passIdToMethod' => false,				// If custom method requires an Id as the first argument
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
		$data = $this->controller->request->data;
		if (!$reset && !empty($data)) {
			$data = $this->array_merge_data($data, $setData);
		} else {
			$data = Hash::merge($data, $setData);
		}

		$this->request->data = $data;
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
		return $this->flash($msg, self::ERROR);
	}
	
	public function flashSuccess($msg) {
		return $this->flash($msg, self::SUCCESS);
	}
	
	public function flashInfo($msg) {
		return $this->flash($msg, self::INFO);
	}
	
	public function flash($msg, $type = self::INFO) {
		$element = $this->settings['overwriteFlash'] ? self::FLASH_ELEMENT : 'default';
		$params = $this->_flashParams($type);
		// Uses the new Flash Component if present
		$paramKeys = ['element', 'key'];
		if (!empty($params['plugin'])) {
			$element = $params['plugin'] . '.' . $element;
		}
		$attrs = compact('element', 'params');
		if (!empty($params['key'])) {
			$attrs['key'] = $params['key'];
		}
		return $this->Flash->set(__($msg), $attrs);
	}
	
/**
  * Finds params used with Session Flash
  *
  * @param String $type The type of flash message it is. Will be returned as part of the element class appended by alert-
  * @param Array (optional) $params Existing params to be merged into result
  * @return Array Params
  **/
	private function _flashParams($type = null, $params = array()) {
		switch ($type) {
			case true;
			case self::SUCCESS:
				$class = self::SUCCESS_CLASS;
				break;
			case false;
			case self::ERROR:
				$class = self::ERROR_CLASS;
				break;
			case self::WARNING:
				$class = self::WARNING_CLASS;
				break;
			default:
				$class = self::INFO_CLASS;
				break;
		}
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
	private function _getSluggableField($Model) {
		if ($Model->hasMethod('getSluggableField')) {
			return $Model->getSluggableField();
		}
		return false;
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
 * Finds the validation errors of all models. Returns a nested HTML unordered list
 *
 * @return string 
 **/
	private function _getValidationErrors() {
		$validationErrors = $this->_getValidationErrorList();
		if (empty($validationErrors)) {
			return "";
		}
		if (!is_array($validationErrors)) {
			$validationErrors = [$validationErrors];
		}
		$validationErrors = Hash::flatten($validationErrors);
		return "<ul><li>" . implode('</li><li>', $validationErrors) . "</li></ul>";
	}

	private function _getValidationErrorList() {
		/*
		$models = ClassRegistry::keys();
		$validationErrors = array();
		foreach ($models as $currentModel) {
			$currentObject = ClassRegistry::getObject($currentModel);
			if ($currentObject instanceof Model && !empty($currentObject->validationErrors)) {
				$validationErrors[$currentObject->alias] = $currentObject->validationErrors;

			}
		}
		*/
		$validationErrors = $this->Model->validationErrors;
		if (empty($validationErrors)) {
			return '';
		}
		// Flattens the error list
		$flattened = Hash::flatten($validationErrors);
		$validationErrors = [];

		// Un-flattens the deepest level of numeric keys
		foreach ($flattened as $k => $v) {
			if (preg_match('/(.*?)\.([0-9]+)$/', $k, $matches)) {
				$validationErrors[$matches[1]][$matches[2]] = $v;
			} else {
				$validationErrors[$k] = $v;
			}
		}
		return $validationErrors;
	}

/**
 * Sets a url to be redirected
 * 
 * @param array|string|boolean $redirect Where to redirect after the message is displayed
 * 			- If true it will redirect to referer
 *			- If false, it will not redirect
 *			- If string or array, it will redirect to the new URL
 * @return void;
 **/
 	protected function redirect($redirect = true) {
 		if ($redirect !== false) {
			if ($redirect === true) {
				$redirect = $this->_referer;
			}
			$this->controller->redirect($redirect);
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
		if ($type == 'ajax') {
			return !empty($this->controller->request) && $this->controller->request->is('ajax');
		}
		return $this->controller->RequestHandler->prefers($type);
	}

/**
 * Render a view or element and skip the method view
 *
 * @param string $view The view you'd like to render
 * 		- If no view is present, it will default to /Elements/CONTROLLER NAME/form.ctp
 * @return void
 **/
	protected function formRender($view = null) {
		if ($view !== false) {
			if (empty($view) || $view === true) {
				$view = $this->getDefaultView();
			}
			list($plugin, $pluginView) = pluginSplit($view);
			$path = $this->_getViewFilePath($view);

			// Prepends plugin if regular view hadn't been found
			if (!is_file($path) && empty($plugin) && !empty($this->Model->plugin)) {
				$view = $this->Model->plugin . '.' . $view;
				$path = $this->_getViewFilePath($view);
			}

			if (is_file($path)) {
				return $this->controller->render($view);
			}
		}
		return null;
	}

/**
 * Checks for the default rendering view path
 *
 * Looks first at the default action, and then second in the Elements directory
 * @return string The view;
 **/
	private function getDefaultView() {
		$defaultViews = array(
			DS . Inflector::pluralize($this->modelClass) . DS . $this->controller->params->action,
			DS . 'Elements' . DS . Inflector::tableize($this->modelClass) . DS . 'form',
		);
		$view = null;
		foreach ($defaultViews as $view) {
			if ($this->Model->plugin) {
				$view = $this->Model->plugin . '.' . $view;
			}
			$path = $this->_getViewFilePath($view);
			if (is_file($path)) {
				break;
			}
		}
		return $view;
	}

/**
 * Returns the file path of the view
 *
 * @param string $view The view file
 * @return string;
 **/
	private function _getViewFilePath($view = null) {
		list($plugin, $view) = pluginSplit($view);
		if (!empty($plugin)) {
			$path = APP . 'Plugin' . DS . $this->Model->plugin . DS . 'View' . DS . $view . '.ctp';
		} else {
			$path = APP . 'View' . DS . $view . '.ctp';
		}
		return $path;
	}
}