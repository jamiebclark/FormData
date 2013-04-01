<?php
/**
 * Table Component
 *
 * Works to manipulate data that has been submitted with the Table Helper 
 *
 **/
class TableComponent extends Component {
	var $controller;
	var $settings = array();
	
	function __construct(ComponentCollection $collection, $settings = array()) {
		$this->settings = $settings;
		parent::__construct($collection, $settings);
	}

	function initialize(&$controller) {
		$this->controller =& $controller;
		
		//debug($controller->data);
		//debug($controller->request->params);
		
		$this->setLimit();
		
		//$this->saveData();
		$this->setCheckbox();
	}
	
	function setLimit() {
		if (!empty($_GET['limit']) && is_numeric($_GET['limit'])) {
			$this->controller->paginate['limit'] = $_GET['limit'];
		}
	}
	
	//Looks for in-table form edits
	function saveData() {
		$result = null;
		if (!empty($this->controller->request->data['TableEdit'])) {
			$model = !empty($settings['model']) ? $settings['model'] : $this->controller->modelClass;
			$pluralModel = Inflector::pluralize($model);
			
			$result = true;
			$successCount = 0;
			$errorCount = 0;
			
			foreach ($this->controller->request->data['TableEdit'] as $key => $data) {
				$this->controller->{$model}->create();
				if (!($success = $this->controller->{$model}->saveAll($data))) {
					$this->validationErrors['TableEdit'][$key] = $this->controller->{$model}->validationErrors;
					$errorCount++;
				} else {
					unset($this->controller->request->data['TableEdit'][$key]);
					$successCount++;
				}
				$result *= $success;
			}
			$msg = 'Successfully updated ' ;
			$msg .= (($errorCount || $successCount > 1) ? ($successCount . ' ' . $pluralModel) : $model) . '.';
			if ($errorCount) {
				$msg .= ' Could not update ' . $errorCount . ' ' . ($errorCount == 1 ? $model : $pluralModel);
			}
			
			if (!empty($this->controller->Session)) {
				$this->controller->Session->setFlash($msg);
			}
			if ($result) {
				$this->controller->redirect($this->controller->referer());
			}
		}
		return $result;
	}
	
	//Scans for passed checked info
	function setCheckbox() {
		$data = array();
		$model = $this->controller->modelClass;

		//debug($this->controller->request->data);
		if (
			isset($this->controller->request->data['with_checked']) &&
			!empty($this->controller->request->data['checked_action']) && 
			!empty($this->controller->request->data['table_checkbox'])
		) {
			$ids = array_values($this->controller->request->data['table_checkbox']);
			$action = $this->controller->request->data['checked_action'];
			$data =& $this->controller->request->data;
		} else if (
			!empty($_POST['with_checked']) && 
			!empty($_POST['table_checkbox']) &&
			!empty($_POST['checked_action'])
		) {
			$data =& $_POST;
			$ids = array_values($_POST['table_checkbox']);
			$count = 1;
			while (!empty($_POST[$count])) {
				$ids[] = $_POST[$count];
				$count++;
			}
			$action = $_POST['checked_action'];
		}
		$options = array();
		if (!empty($data['useModel'])) {
			$options['model'] = $data['useModel'];
		}
		if (!empty($ids) && !empty($action)) {
			$return = $this->withChecked($action, $ids, $options);
			if (!empty($return['message'])) {
				$this->controller->Session->setFlash($return['message']);
			}
			if (!empty($return['redirect'])) {
				if ($return['redirect'] === true) {
					$return['redirect'] = $this->controller->referer();
				}
				$this->controller->redirect($return['redirect']);
			}
			return true;
		}
		return false;
	}
	
	function withChecked($action, $ids, $options = array()) {
		$function = '_withChecked';
		$redirect = true;
		$message = false;
		if (method_exists($this->controller, $function)) {
			$options = $this->controller->{$function}($action, $ids);
		}
		$model = !empty($options['model']) ? $options['model'] : $this->controller->modelClass;
		if (empty($options['result'])) {
			if (!empty($this->controller->{$model})) {
				$Model =& $this->controller->{$model};
			} else {
				App::import('Model', $model);
				$Model =& new $model();
			}
			$verb = 'Set';
			if (empty($options['conditions'])) {
				$options['conditions'] = array(
					$model . '.id' => $ids,
				);
			}			
			if ($action == 'approve') {
				$options['verb'] = 'Approved';
				$options['updateAll'] = array($model . '.approved' => 1);
			} else if ($action == 'unapprove') {
				$options['verb'] = 'Unapproved';
				$options['updateAll'] = array($model . '.approved' => 0);
			} else if ($action == 'active') {
				$options['verb'] = 'Activated';
				$options['updateAll'] = array($model . '.active' => 1);
			} else if ($action == 'inactive') {
				$options['verb'] = 'Deactivated';
				$options['updateAll'] = array($model . '.active' => 0);
			} else if ($action == 'delete') {
				$options['delete'] = true;
			} else if ($action == 'duplicate') {
				$options['result'] = true;
				$options['redirect'] = array(
					'controller' => 'duplicates',
					'action' => 'view',
					'staff' => true,
					$model,
				);
				foreach ($ids as $id) {
					$options['redirect'][] = $id;
				}
			}
			
			if (!empty($options['delete'])) {
				if ($options['delete'] === true) {
					$options['delete'] = $options['conditions'];
				}
				$Model->order = array();
				$result = $Model->deleteAll($options['delete']);
				$options['verb'] = 'Deleted';
			} else if (!empty($options['updateAll'])) {
				$result = $Model->updateAll($options['updateAll'], $options['conditions']);
				$options['verb'] = 'Updated';
			}	
			$options['count'] = $Model->getAffectedRows();
		}
		if (!empty($options['message'])) {
			$message = $options['message'];
		} else {
			$message = (!empty($options['verb']) ? $options['verb'] : 'Adjusted') . ' ';
			if (!empty($options['count'])) {
				$message .= $options['count'] . ' ' . Inflector::pluralize($model);
			} else {
				$message .= $model;
			}
		}
		if (!empty($options['redirect'])) {
			$redirect = $options['redirect'];
		}
		return compact('redirect', 'message');
	}
}
