<?php
class FormDataComponent extends Component {
	public $name = 'FormData';
	public $components = array(
		'FormData.ControllerMethod',
		'FormData.FindModel',
		'FormData.FlashMessage',
	);
	
	public $controller;
	public $settings = array();
	public $isAjax = false;
	
	private $_log = array();
	private $_storedData = array();
	
	private $_postSave = array(
		'success' => array(),
		'fail' => array(),
	);
	
	public function __construct(ComponentCollection $collection, $settings = array()) {
		$default = array(
			'model' => true,
			'plugin' => null,
		);
		$settings = array_merge($default, $settings);
		return parent::__construct($collection, $settings);			
	}

	public function beforeRender(Controller $controller) {
		$this->FlashMessage->overwriteFlash();
	}
	
	public function setController(Controller $controller) {
		$this->controller = $controller;
		$this->FindModel->setController($controller);
		$this->ControllerMethod->setController($controller);
	}
	
	#section Callback Methods
	public function initialize(Controller $controller) {
		$this->setController($controller);
		
		if ($modelName = $this->FindModel->getModelName($this->settings)) {
			list($plugin, $model) = pluginSplit($modelName);
			$this->settings['model'] = $model;
			$this->settings['plugin'] = $plugin;
		}
		
		$this->isAjax = !empty($controller->request) && $controller->request->is('ajax');
		$this->setSuccessRedirect(array('action' => 'view', 'ID'));
		return parent::initialize($controller);
	}

	#endsection
	
	#section Custom Callback Methods
	function beforeSaveData($data, $saveOptions) {
		unset($data['FormData']);
		if (($callResult = $this->ControllerMethod->call('_beforeSaveData', $data, $saveOptions)) === false) {
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
		$this->ControllerMethod->call('_afterSaveData', $created);
		return true;
	}
	
	function afterFailedSaveData() {
		$this->ControllerMethod->call('_afterFailedSaveData');
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

	
	public function findModel($id = null, $config = array(), $query = array()) {
		$result = $this->FindModel->find($id, $config, $query);
		$this->id = $this->FindModel->id;
		return $result;
	}

	/**
	 * Sets default options for the findModel function
	 *
	 * @param Array $attrs passed options overwriting the defaults
	 * @return Array FindModel $attrs
	 **/
	protected function setFindModelAttrs($settings = array()) {
		return $this->FindModel->setSettings($settings);
	}
	
	/**
	 * Saves new request data
	 *
	 * @param array $options addData options, including default values to be passed to the form
	 * @param array $saveAttrs FormData->saveData options
	 * @param array $saveOptions Model->save options
	 * @return array Model result
	 */
	public function addData($options = array(), $saveDataOptions = array(), $saveOptions = array()) {
		$result = $this->saveData(null, $saveDataOptions, $saveOptions);
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
	 * @param int 	$id The id of the model being saved
	 * @param array $findModelSettings FormData->findModel options
	 * @param array $query Model->find options
	 * @param array $saveDataOptions FormData->saveData options
	 * @param array $saveOptions Model->save options
	 * @return array Model result
	 **/
	public function editData($id, $findModelSettings=array(), $query=array(), $saveDataOptions=array(), $saveOptions=array()) {
		$result = $this->saveData(null, $saveDataOptions, $saveOptions);
		if ($result === null) {
			$result = $this->findModel($id, $findModelSettings, $query);
			$this->controller->request->data = $result;
		} else {
			if (!empty($this->controller->request->data[$this->settings['model']]['id'])) {
				$this->findModel($this->controller->request->data[$this->settings['model']]['id'], $findModelSettings, $query);
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
				'redirect' => null,
			),
			'fail' => array(
				'message' => 'Could not update ' . $modelHuman . ' info',
			)
		);
		if ($returnOptions = $this->ControllerMethod->call('_setSaveDataOptions', $options)) {
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
			
			if ($this->isAjax) {
				echo $result ? 1 : 0;
			} else {
				if (!$result) {
					//debug($Model->alias);
					//debug($Model->invalidFields());
				}
				if (!empty($message)) {
					$this->FlashMessage->flash($message, !empty($result));
				}
				
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
					}
					$this->controller->redirect($redirect);
				}
			}
			$this->resetPostSave();
			return $result ? true : false;
		}
		return null;
	}
	
	public function deleteData($id, $options = array()) {
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
			$msg = $success ? "Deleted $modelHuman entry" : "Could not delete $modelHuman entry";
			$this->FlashMessage->flash($msg, $success);
			if (!empty($redirect)) {
				$this->controller->redirect($redirect);
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
		$this->ControllerMethod->call('_setFormElements', $id);
		$this->setRefererRedirect();
	}
	
	public function flashError($msg) {
		return $this->FlashMessage->error($msg);
	}
	
	public function flashSuccess($msg) {
		return $this->FlashMessage->success($msg);
	}
	
	public function flashInfo($msg) {
		return $this->FlashMessage->info($msg);
	}
	
	public function flash($msg, $type = 'info') {
		return $this->FlashMessage->flash($msg, $type);
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
	
}