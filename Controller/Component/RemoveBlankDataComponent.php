<?php
/**
 * RemoveBlankDataComponent
 * 
 * Used to check request data for blank entries and remove them before saving.
 *
 * This is mainly useful when saving hasMany related models for the primary model, removing any extraneous entries before actually saving
 *
 **/

class RemoveBlankDataComponent extends Component {
	public $name = 'RemoveBlankData';

/**
 * The array of models to search for blank information, along with the qualifying criteria for what makes them "blank"
 *
 * Format:
 * 		array(
 *			'ModelName' => array(	// The Model name
 *				'and' => array('title'),				// Will be marked blank if ALL of these fields are empty
 *				'or' => array('posted', 'modified'),	// Will be marked blank if ANY of these fields are empty
 *				// If neither and nor or are present, it will assume 'and'
 *				// If no variables are passed, it will assume the displayField
 *			)
 *		)
 * @var array
 **/
	public $settings = array();
	public $controller;

	public function initialize(Controller $controller) {
		$this->controller = $controller;

		// Checks for config data passed into settings
		if (!empty($this->settings) && !empty($controller->request->data)) {
			$this->process($this->settings);
		}

		return parent::initialize($controller);
	}

/**
 * Scans the data array blank data entries
 *
 * @param array $config Configuration information about models to remove if blank
 * @param 
 **/
	public function process($config = array()) {
		$data = $this->controller->request->data;
		foreach ($config as $className => $options) {
			unset($config[$className]);
			if (is_numeric($className)) {
				$className = $options;
				$options = array();
			}
			$Model = ClassRegistry::init($className);
			if (empty($options)) {
				$options = array($Model->displayField);
			}
			if (!isset($options['and']) && !isset($options['or'])) {
				$options = array('and' => $options);
			}
			$config[$className] = $options;
			if (is_array($data)) {
				$data = $this->findAndRemoveBlankData($Model, $data, $options);
			}
		}

		// Updates data
		$this->controller->request->data = $data;

	}

/**
 * Locates a reference to a specific model and removes any blank options
 *
 * @param Model $Model The specified model
 * @param array $data The passed data array
 * @param array $options Blank configuration options
 * @return array The updated data array
 **/
	private function findAndRemoveBlankData($Model, $data, $options) {
		$alias = $Model->alias;
		// Finds appropriate spot in data
		if (array_key_exists($alias, $data)) {
			$data[$alias] = $this->removeBlankData($Model, $data[$alias], $options);
		} else {
			foreach ($data as $key => $row) {
				if (is_array($row)) {
					$data[$key] = $this->findAndRemoveBlankData($Model, $row, $options);
				}
			}
		}
		return $data;
	}

/**
 * Removes any blank data entries
 *
 * @param Model $Model The specified model
 * @param array $data The passed data array
 * @param array $options Blank configuration options
 * @return array The updated data array
 **/
	private function removeBlankData($Model, $data, $options) {
		// If the data is a numeric array, then it's a hasMany formatted result and checks each entry
		if ($this->isArrayNumeric($data)) {
			foreach ($data as $k => $rowData) {
				$rowData = $this->removeBlankData($Model, $rowData, $options);
				if (empty($rowData)) {
					unset($data[$k]);
				}
			}
			$data = array_values($data);	//Re-numbers
		} else {
			if ($this->isBlank($Model, $data, $options)) {
				if (!empty($data[$Model->primaryKey])) {
					$Model->delete($data[$Model->primaryKey]);
				} 
				$data = array();
			}
		}
		return $data;
	}

/**
 * Determines is a specific data row is blank or not
 *
 * @param Model $Model The specified model
 * @param array $dataRow The passed data array
 * @param array $options Blank configuration options
 * @return bool True if blank, false if not
 **/
	private function isBlank($Model, $dataRow, $options) {
		if (!empty($options['and'])) {
			foreach ($options['and'] as $field) {
				if (!empty($dataRow[$field])) {
					return false;
				}
			}
		}
		if (!empty($options['or'])) {
			foreach ($options['or'] as $field) {
				if (array_key_exists($field, $dataRow) && empty($dataRow[$field])) {
					return true;
				}
			}
		}
		return true;
	}


/**
 * Determines if an array has entirely numeric keys
 *
 * @param array $array
 * @return bool True if numeric, false if not;
 **/
	private function isArrayNumeric($array) {
		return (bool) count(array_filter(array_keys($array), 'is_numeric'));
	}
}