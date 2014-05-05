<?php
class FindModelComponent extends Component {
	public $name = 'FindModel';
	public $components = array(
		'FormData.ControllerMethod',
		'FormData.FlashMessage',
		'FormData.FormData', 
	);
	
	public $settings;
	
	public function __construct(ComponentCollection $collection, $settings = array()) {
		$this->settings = $settings;
		return parent::__construct($collection, $settings);			
	}
	
	public function setController($controller) {
		$this->controller = $controller;
		$this->ControllerMethod->setController($controller);
	}
	
	#section Callback Methods
	public function initialize(Controller $controller) {
		$this->setController($controller);
		$this->settings = $this->setSettings();	

		return parent::initialize($controller);
	}
	#endsection

	public function setSettings($settings = array(), $overwrite = false) {
		$defaults = array(
			'notFoundRedirect' => true,					//Where to redirect if not found
			'method' => 'find',							//Specify a model method to call 
			'passIdToMethod' => false,					//If custom method requires an Id as the first argument
			'findType' => 'first',						//If method is "find", decide which type of find to use
			'plugin' => null,
		);

		//The Model it will be pulling the result from
		if (!empty($this->controller->modelClass)) {
			$defaults['model'] = $this->controller->modelClass;
			$defaults['plugin'] = $this->controller->plugin;
		}
		
		//Allows for controller overwrite function
		//Legacy
		if ($var = $this->ControllerMethod->call('_setFindModelAttrs', $defaults)) {
			$defaults = $var;
		}
		if ($var = $this->ControllerMethod->call('_setFindModelSettings', $defaults)) {
			$defaults = $val;
		}
		
		if ($overwrite) {
			$this->settings = array_merge($defaults, (array) $settings);
		} else {
			$this->settings = array_merge($defaults, (array) $this->settings, (array) $settings);
		}
		
		return $this->settings;
	}

	public function find($id = null, $config = array(), $query = array()) {
		$config = $this->setSettings($config);
		//Allows you to pass query information in the config varialbe
		//(Includes legacy "options" terminology)
		foreach (array('options', 'query') as $queryKey) {
			if (!empty($config[$queryKey])) {
				$query = array_merge($query, $config[$queryKey]);
				unset($config[$queryKey]);
			}
		}
		extract($config);

		$Model = $this->getModel($model);
		
		if (empty($query)) {
			$query = array();
		}

		if (!empty($id) || empty($query['conditions'])) {
			if (!is_numeric($id) && ($slugField = $this->_isSluggable($Model))) {
				//Searches by slug
				$conditions = array($Model->escapeField($slugField) . ' LIKE' => $id);
			} else {
				//Searches by primary ID
				$conditions = array($Model->escapeField($Model->primaryKey) => $id);
			}
			$query = Set::merge($query, compact('conditions'));
		}
		
		$controller = Inflector::tableize($model);
		$varName = Inflector::variable($model);
		$human = Inflector::humanize(Inflector::singularize($controller));
		
		$this->id = $id;
		
		if ($vars = $this->ControllerMethod->call('_beforeFindModel', $query)) {
			$query = $vars;
		}
		
		//Legacy
		if ($vars = $this->ControllerMethod->call('_setFindModelOptions', $query)) {
			$query = $vars;
		}
		if ($vars = $this->ControllerMethod->call('_setFindModelQuery', $query)) {
			$query = $vars;
		}

		if ($method == 'find') {
			$args = array($findType, $query);
		} else if ($passIdToMethod) {
			$args = array($this->id, $query);
		} else {
			$args = array($query);
		}
		$result = call_user_func_array(array($Model, $method), $args);
		
		if ($afterFindResult = $this->ControllerMethod->call('_afterFindModel', $result)) {
			if (is_array($afterFindResult)) {
				$result = $afterFindResult;
			}
		}
		
		if (!empty($config['data'])) {
			$result = $this->resultToData($result, $config);
		}
		
		if (empty($result)) {
			$this->id = null;
		}
		
		if (empty($message)) {
			$message = "$human is not found";
		}

		//Not Found
		if (empty($result[$Model->alias][$Model->primaryKey]) && !empty($notFoundRedirect)) {
			if ($notFoundRedirect === true) {
				$notFoundRedirect = $this->controller->referer();
				if ($notFoundRedirect == '/' || $notFoundRedirect == Router::url()) {
					$notFoundRedirect = array('action' => 'index');
				}
			}
			$msgs = array();
			if (!empty($message)) {
				$msgs[] = $message;
			}
			$msgs[] = sprintf("%s is looking for %s: %d", $Model->alias, $Model->primaryKey, $id);
			if (is_array($result)) {
				$msgs[] = 'Keys: ' . implode(', ', array_keys($result));
				//$msgs[] = 'Body: ' . implode(', ', $result);
			}
			//$msgs[] = implode('<br/>', debugTrace('Trace'));
			$msgs[] = Router::url($notFoundRedirect);
			$this->FlashMessage->error(implode("<br/>\n", $msgs));
			$this->controller->redirect($notFoundRedirect);
		}
		
		$this->controller->set($varName, $result);
		return $result;
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

	public function setModel($modelName = null) {
		list($plugin, $model) = $modelName;
		$this->settings['model'] = $model;
		$this->settings['plugin'] = $plugin;
	}
	
	public function getModel($modelName = null, $plugin = null) {
		$Model = null;
		if ($modelName = $this->getModelName($modelName, $plugin)) {
			list($plugin, $model) = pluginSplit($modelName);
			if (method_exists($this->controller, $model)) {
				$Model = $this->controller->{$model};
			} else {
				$Model = ClassRegistry::init($modelName, true);
			}
		}
		if (empty($Model)) {
			throw new Exception(sprintf('FormData.FindModel could not load model, %s ', $modelName));
		}
		return $Model;		
	}
	
	public function getModelName($modelName = null, $plugin = null) {
		if (empty($modelName)) {
			$modelName = $this->settings['model'];
		}
		if (empty($plugin) && !empty($this->settings['plugin'])) {
			$plugin = $this->settings['plugin'];
		}
		
		//Set model name by passing an array
		if (is_array($modelName)) {
			$options = $modelName;
			$modelName = !empty($options['model']) ? $options['model'] : null;
			$plugin = !empty($options['plugin']) ? $options['plugin'] : null;
		}
		if ($modelName === true) {	//Loads default controller model
			$modelName = $this->_pluginCombine($this->controller->modelClass, $this->controller->plugin);
		} 
		$modelName = $this->_pluginCombine($modelName, $plugin);
		return $modelName;	
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
	
	private function _pluginCombine($model, $plugin = null) {
		if (!empty($plugin) && strpos($model, '.') === false) {
			$model = "$plugin.$model";
		}
		return $model;
	}
}