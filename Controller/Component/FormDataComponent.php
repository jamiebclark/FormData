<?php
class FormDataComponent extends Component {
	var $name = 'FormData';
	var $components = array();
	
	var $controller;
	var $settings = array();
	var $isAjax = false;
	
	var $_log = array();
	var $_storedData = array();
	
	function initialize(&$controller) {
		$this->controller =& $controller;
		$model = null;
		if (!empty($this->controller->modelClass)) {
			$model = $this->controller->modelClass;
		} else if (!empty($this->controller->modelClass)) {
			$model = $this->controller->modelClass;
		}
		if (!empty($model)) {
			$this->settings['model'] = $model;
		}
		
		$this->isAjax = !empty($controller->request) && $controller->request->is('ajax');
	}

	
	function findModel($id = null, $attrs = array(), $options = array()) {
		$defaultReferer = $this->controller->referer();
		if ($defaultReferer == '/' || $defaultReferer == Router::url()) {
			$defaultReferer = array('action' => 'index');
		}
		if (empty($attrs)) {
			$attrs = array();
		}
		$defaults = array(
			'model' => $this->settings['model'],
			'redirect' => $defaultReferer,
			'method' => null,	//Specify a method other than find('first',...)
		);
		if (method_exists($this->controller, '_setFindModelAttrs')) {
			$defaults = $this->controller->_setFindModelAttrs($defaults);
		}
		$attrs = array_merge($defaults, $attrs);
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
			App::import('Model', $importModel);
			$Model = new $model();
		}
		
		if (empty($options)) {
			$options = array();
		}
		if (!empty($id) || empty($options['conditions'])) {
			if (!is_numeric($id) && ($slugField = $this->__isSluggable($Model))) {
				//Searches by slug
				$conditions = array($Model->alias . '.' . $slugField . ' LIKE' => $id);
			} else {
				//Searches by primary ID
				$conditions = array($Model->alias . '.' . $Model->primaryKey => $id);
			}
			$options = array_merge($options, compact('conditions'));
		}
		
		$controller = Inflector::tableize($model);
		$varName = strtolower(substr($model, 0, 1)) . substr($model, 1);
		$human = Inflector::humanize(Inflector::singularize($controller));
		
		$this->id = $id;
		
		if (method_exists($this->controller, '_beforeFindModel')) {
			$options = $this->controller->_beforeFindModel($options);
		}
		
		if (method_exists($this->controller, '_setFindModelOptions')) {
			$options = $this->controller->_setFindModelOptions($options);
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
		
		if (method_exists($this->controller, '_afterFindModel')) {
			if (is_array($afterFindResult = $this->controller->_afterFindModel($result))) {
				$result = $afterFindResult;
			}
		}
		
		if (empty($result)) {
			$this->id = null;
		}
		if (empty($message)) {
			$message = $human . ' is not found';
		}

		if (empty($result[$Model->alias][$Model->primaryKey]) && !empty($redirect)) {
			$message .= '! ';
			$message .= $Model->alias . ', ';
			$message .= $Model->primaryKey . ' ';
			$message .= 'Looking for ID: ' . $id;
			$message .= "<br/>\n";
			if (is_array($result)) {
				$message .= 'Keys: ' . implode(', ', array_keys($result));
				$message .= "<br/>\n";
				//$message .= 'Body: ' . implode(', ', $result);
			}
			
			//$message .= implode('<br/>', debugTrace('Trace'));

			$message .= '<br/>' . Router::url($redirect);
			
			$this->controller->Session->setFlash($message);
			$this->controller->redirect($redirect);
		}
		$this->controller->set($varName, $result);
		return $result;
	}

	function mergeOptions($options1 = array(), $options2 = array()) {
		$options1 = $this->_prepareOptions($options1);
		$options2 = $this->_prepareOptions($options2);
		$return = array_merge($options1, $options2);
		return $return;
	}
	
	function _prepareOptions($options) {
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
	
	function _numericKeysFix($array = array()) {
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
	
	function addData($options = array(), $saveAttrs = array(), $saveOptions = array()) {
		$result = $this->saveData(null, $saveAttrs, $saveOptions);
		if ($result === null && !empty($options['default'])) {
			$this->setData($options['default'], true);
		}		
		$this->setFormElements();
		return $result;
	}
	
	function editData($id, $findAttrs = array(), $findOptions = array(), $saveAttrs = array(), $saveOptions = array()) {
		$result = $this->saveData(null, $saveAttrs, $saveOptions);
		if ($result === null) {
			$this->controller->request->data = $this->findModel($id, $findAttrs, $findOptions);
			//debug($this->controller->request->data);
		} else {
			$this->findModel($this->controller->request->data[$this->settings['model']]['id'], $findAttrs, $findOptions);
		}
		$this->setFormElements($id);
		return $result;
	}	
	
	/**
	 * Handles the basic code appearing at the top of any add or edit controller function
	 * returns true if successfully saved, false if failed at saving, null if no data is detected
	 * 
	 **/
	function saveData($model = null, $passedOptions = array(), $saveOptions = array()) {
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
					$this->_log(array('Save Failed'));
				}
			} else {
				$this->_log('FormData beforeSaveData failed');
				$data = $this->_storedData;
			}
			$use = $result ? $options['success'] : $options['fail'];
			if ($this->isAjax) {
				echo $result ? 1 : 0;
			} else {
				if (!$result) {
					//debug($Model->alias);
					//debug($Model->invalidFields());
				}
				if (!empty($use['message'])) {
					$this->__setFlash($use['message']);
				}
				
				if (!empty($use['redirect'])) {
					if (is_array($use['redirect'])) {
						if (($key = array_search('ID', $use['redirect'])) !== false) {
							$use['redirect'][$key] = $Model->id;
						}
						if (!empty($data['FormData']['redirectAction'])) {
							$use['redirect']['action'] = $data['FormData']['redirectAction'];
						}
						if (!empty($data['FormData']['redirectController'])) {
							$use['redirect']['controller'] = $data['FormData']['redirectController'];
						}
					}
					$this->controller->redirect($use['redirect']);
				}
			}
			return $result ? true : false;
		}
		return null;
	}
	
	function beforeSaveData($data, $saveOptions) {
		unset($data['FormData']);
		
		if (method_exists($this->controller, '_beforeSaveData')) {
			if (($data = $this->controller->_beforeSaveData($data, $saveOptions)) === false) {
				$this->_log('Controller beforeSaveData failed');
				return false;
			}
		}
		if (($data = $this->__checkCaptcha($data)) === false) {
			$this->_log('CheckCaptcha Failed');
			$this->_log($data);
			return false;
		}
		return $data;
	}
	
	function afterSaveData($created) {
		if (method_exists($this->controller, '_afterSaveData')) {
			$this->controller->_afterSaveData($created);
		}
		return true;
	}


	function deleteData($id, $options = array()) {
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
			$this->controller->Session->setFlash(__($msg));
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
		if (method_exists($this->controller, '_setFormElements')) {
			$this->controller->_setFormElements($id);
		}
		$this->setRefererRedirect();
	}
	
	function __setFlash($msg) {
		$this->controller->Session->setFlash(__($msg));
	}

	//Checks if Model can be searched by slug. If so, returns the slug field. Otherwise, returns false
	//Uses the Sluggable Behavior
	function __isSluggable($Model) {
		$return = false;
		if (array_key_exists('Sluggable', $Model->actsAs)) {
			$return = !empty($Model->actsAs['Sluggable']['slugColumn']) ? $Model->actsAs['Sluggable']['slugColumn'] : 'slug';
		}
		return $return;
	}
	
	function __checkCaptcha($data) {
		$this->_log($data);
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
}