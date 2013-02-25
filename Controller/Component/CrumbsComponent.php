<?php
class CrumbsComponent extends Component {
	var $name = 'Crumbs';
	var $controller;
	var $settings = array();
	
	function __construct(ComponentCollection $collection, $settings = array()) {
		$this->settings = $settings;
		parent::__construct($collection, $settings);
	}

	function initialize(&$controller) {
		$this->controller =& $controller;
	}
	
	function setBaseCrumbsChain($id, $models = array(), $options = array()) {
		$baseCrumbs = $this->getBaseCrumbsChain($id, $models);
		$this->controller->set(compact('baseCrumbs'));
	}
	
	function getBaseCrumbsChain($id, $models = array(), $options = array()) {
		$options = array_merge(array(
			'baseCrumbs' => array(),
			'truncate' => 50,
		));
		extract($options);
		
		if (empty($baseCrumbs) && !empty($this->settings['baseCrumbs'])) {
			$baseCrumbs = $this->settings['baseCrumbs'];
		}
		
		$baseModel =& $this->controller->modelClass;
		if (empty($Model)) {
			$Model =& $this->controller->{$baseModel};
		}
		
		if (empty($models) && !empty($this->settings['baseCrumbChain'])) {
			$models = $this->settings['baseCrumbChain'];
		}
		
		$Model->Behaviors->attach('FindBelongsTo');
		$result = $Model->findBelongsTo('list', $id, $models);
		
		if (!empty($result)) {
			foreach ($result as $model => $row) {
				if ($model == $baseModel) {
					continue;
				}
				list($id, $title) = array_values($row);
				if (!empty($truncate) && strlen($title) > $truncate) {
					$title = substr($title, 0, $truncate) . '...';
				}
				
				$baseCrumbs[] = array($title, array(
					'controller' => Inflector::tableize($model),
					'action' => 'view',
					$id,
				));
			}
		}
		return $baseCrumbs;
	}
}
