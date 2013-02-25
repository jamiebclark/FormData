<?php
App::uses('InflectorPlus', 'Utilities');

class CrumbsHelper extends AppHelper {
	var $name = 'Crumbs';
	var $helpers = array('Html', 'Iconic',);
	
	public $hide = false;
	public $title; //Title of the current page being view with crumbs
	
	var $baseCrumbs;
	var $defaultCrumbs;
	var $parentCrumbs;
	var $controllerCrumbs;
	var $actionCrumbs;
	var $userSetCrumbs;
	
	var $controllerParentVar = array();
	
	/* The crumbs are made of the following parts
		- Base Crumbs
		- Default Crumbs * Legacy system, replaced by Controller / Action
		- Controller Crumbs
		- Action Crumbs
		
		- User-added Crumbs
	*/
	var $crumbTypes = array('base', 'default', 'parent', 'controller', 'action', 'userSet');
	var $legacyTypes = array('default');
	
	var $_crumbs = array();
	
	function __construct(View $view, $settings = array()) {
		parent::__construct($view, $settings);
		$vars = array();
		foreach ($this->crumbTypes as $type) {
			if (isset($settings[$type . 'Crumbs'])) {
				$vars[$type] = $settings[$type . 'Crumbs'];
				unset($settings[$type . 'Crumbs']);
			}
		}
		$this->_set($settings);
		foreach ($vars as $type => $vars) {
			$this->_setCrumbType($type, $vars);
		}
		
		$this->addVars($settings);

	}
	
	function add($title, $link = null, $options = null) {
		if (is_array($title)) {
			$crumb = $title + array(null, null, null);
		} else {
			$crumb = array($title, $link, $options);
		}
		$crumbs = array($crumb);
		return $this->userSetCrumbs(compact('crumbs'));	
	}
	
	function debug() {
		$out = array();
		foreach ($this->crumbTypes as $type) {
			$out[$type] = $this->{$type . 'Crumbs'};
		}
		debug($out);
	}
	
	function addVars($vars = array(), $options = array()) {
		foreach ($this->crumbTypes as $type) {
			if (isset($vars[$type . 'Crumbs'])) {
				$this->_setCrumbType($type, array('crumbs' => $vars[$type . 'Crumbs']), $options);
			}
		}
		if (!empty($vars['crumbs'])) {
			$this->addCrumbs($vars['crumbs']);
		}
		
		if (!empty($vars['parent'])) {
			$parentModel = !empty($vars['parentModel']) ? $vars['parentModel'] : $this->viewVars['models'][0];
			$this->setParent($parentModel, $vars['parent']);
		}
		
		return true;
	}
	
	function addCrumbs($crumbs = array(), $options = array()) {
		foreach ($this->crumbTypes as $type) {
			if (!empty($options[$type . 'Crumbs'])) {
				$this->_setCrumbType($type, array('crumbs' => $options[$type . 'Crumbs']));
			}
		}
		if (!is_array($crumbs)) {
			$crumbs = array($crumbs);
		}
		foreach ($crumbs as $crumb) {
			$this->add($crumb);
		}
		return true;
	}
	
	function output($options = array()) {
		$options = array_merge(array(
			'tag' => 'li',
			'home' => $this->Iconic->icon('home'),
			'homeUrl' => '/',
			'before' => '',
			'after' => '',
			'crumbs' => array(),
			//'wrap' => 'li',
			'separator' => '<font>&gt;</font>',
		), $options);
		extract($options);

		$home = array($this->_getHomeUrl($home, $homeUrl));

		if ($this->hide && (!isset($hide) || $hide !== false)) {
			return null;
		}
		
		if (!empty($wrap)) {
			if (is_array($wrap)) {
				list($wrap, $wrapOptions) = $wrap;
			} else {
				$wrapOptions = array();
			}
			$wrapOpen = $this->Html->tag($wrap, null, $wrapOptions);
			$wrapClose = '</' . $wrap . '>';
			$before .= $wrapOpen;
			$after .= $wrapClose;
			$separator = $wrapClose . $wrapOpen;
		}
		
		$setCrumbs = array();
		
		if (!empty($this->Html->_crumbs)) {
			$setCrumbs = $this->Html->_crumbs;
		} else {
			foreach ($this->crumbTypes as $type) {
				$addCrumb = null;
				if (isset(${$type . 'Crumbs'})) {
					$addCrumb = ${$type . 'Crumbs'};
				} else {
					$addCrumb = $this->_setCrumbType($type, array(
						'skipDefault' => in_array($type, $this->legacyTypes),
					));
				}
				$setCrumbs = $this->_mergeCrumbs($setCrumbs, $addCrumb);
			}
		}			
		$crumbs = $this->_mergeCrumbs($home, $setCrumbs, $crumbs);
		
		if (!empty($crumbs) && $crumbs != $home) {
			$out = array();
			$lastKey = count($crumbs) - 1;
			foreach ($crumbs as $k => $crumb) {
				if (is_array($crumb) && !empty($crumb[1]) && $k < $lastKey) { //Ensures last crumb is never a link
					$out[] = $this->Html->link($crumb[0], $crumb[1], $crumb[2]);
				} else {
					$out[] = $crumb[0];
				}
			}
			return $this->Html->div('crumbs', $before . join($separator, $out) . $after);
		} else {
			return null;
		}
	}
	
	function baseCrumbs($options = array()) {
		return $this->_setCrumbType('base', $options);
	}
	
	function defaultCrumbs($options = array()) {
		return $this->_setCrumbType('default', $options);
	}
	
	function setParent($model, $result, $options = array()) {
		if (!empty($result[$model])) {
			$result = $result[$model];
		}
		$controller = Inflector::tableize($model);
		$crumbs = array();
		$crumbs[] = array(InflectorPlus::humanize($controller), compact('controller') + array('action' => 'index'));
		$crumbs[] = array($result['title'], compact('controller') + array('action' => 'view', $result['id']));
		
		if (!empty($options['controllerVar'])) {
			$varName = ($options['controllerVar']==1 || $options['controllerVar']===true) ? 0 : $options['controllerVar'];
			$this->controllerParentVar = array($varName, $result['id']);
		}
		
		return $this->parentCrumbs(compact('crumbs'));
	}
	
	function parentCrumbs($options = array()) {
		return $this->_setCrumbType('parent', $options);
	}
	
	function controllerCrumbs($options = array()) {
		return $this->_setCrumbType('controller', $options);
	}
	
	function actionCrumbs($options = array()) {
		return $this->_setCrumbType('action', $options);
	}
	
	function userSetCrumbs($options = array()) {
		return $this->_setCrumbType('userSet', $options);
	}


	function hide($set = true) {
		$this->hide = $set;
	}
	
	function title($title) {
		$this->title = $title;
	}
	
	function _getCrumbType($type, $options = array()) {
		$varName = $type . 'Crumbs';
		if (!isset($this->{$varName}) || !empty($options['overwrite'])) {
			$crumbs = $this->_setCrumbType($type, $options);
		} else {
			$crumbs = $this->$varName;
		}
		return $crumbs;
	}
	
	function _setCrumbType($type, $options = array()) {
		if (empty($options['crumbs'])) {
			if (is_array($options)) {
				foreach ($options as $k => $v) {
					if (is_numeric($k)) {
						$options['crumbs'][$k] = $v;
						unset($options[$k]);
					}
				}
			} else {
				$options = array('crumbs' => $options);
			}
		}
		
		if (!empty($options['crumbs']) || (isset($options['crumbs']) && $options['crumbs'] === false)) {
			$crumbs = $options['crumbs'];
		} else if ((!isset($this->{$type . 'Crumbs'}) || !empty($options['reset'])) && empty($options['skipDefault'])) {
			$crumbs = $this->_getDefaultCrumbType($type, $options);
		} else {
			$crumbs = $this->{$type . 'Crumbs'};
		}
		
		if (!empty($options['prepend'])) {
			$crumbs = $this->_mergeCrumbs($options['prepend'], $crumbs);
		}
		if (!empty($options['append'])) {
			$crumbs = $this->_mergeCrumbs($crumbs, $options['append']);
		}
		$this->{$type . 'Crumbs'} = $crumbs;
		
		//Unsets Controller and Action, using the legacy 'default' format
		if (!empty($crumbs) && $type == 'default') {
			$this->actionCrumbs = false;
			$this->controllerCrumbs = false;
		}
		
		return $crumbs;
	}
	
	function _getDefaultCrumbType($type, $options = array()) {
		$crumbs = array();
		if ($type == 'controller') {
			$urlBase = !empty($options['urlBase']) ? $options['urlBase'] : $this->_getUrlBase($options);
			if (!empty($this->controllerParentVar)) {
				list($controllerParentVarName, $controllerParentVar) = $this->controllerParentVar;
				$urlBase[$controllerParentVarName] = $controllerParentVar;
			}
			$crumbs = array(
				array(InflectorPlus::humanize($urlBase['controller']), array('action' => 'index') + $urlBase)
			);
		} else if ($type == 'action') {
			$urlBase = !empty($options['urlBase']) ? $options['urlBase'] : $this->_getUrlBase($options);
			$action = $urlBase['action'];
			if ($modelInfo = $this->_getModel()) {
				extract($modelInfo);	//model, primaryKey, displayField
				$result = $this->_getResult($model);
				
				if (!empty($result) && !empty($result[$model][$primaryKey])) {
					if ($action == 'view' && !empty($this->title)) {
						$title = $this->title;
					} else if (!empty($result[$model][$displayField])) {
						$title = $result[$model][$displayField];
					} else {
						$title = $this->Html->tag('em', 'blank');
					}
					$crumbs[] = array(
						$title, 
						array('action' => 'view', $result[$model][$primaryKey]) + $urlBase,
						array('escape' => false)
					);
				}
				
				if ($action != 'view' && $action != 'index') {
					$crumbs[] = array(
						!empty($this->title) ? $this->title : InflectorPlus::humanize($action),
						$urlBase,
					);
				}
			}
		} else if ($type == 'default') {
			$crumbs = $this->_mergeCrumbs($this->_getDefaultCrumbType('controller'), $this->_getDefaultCrumbType('action'));
		}
		return $crumbs;	
	}
	
	function _getHtmlCrumbs() {
		$crumbs = array();
		if (!empty($this->Html->_crumbs)) {
			$crumbs = $this->Html->_crumbs;
		}
		if ($crumbs == array('',null,null)) {
			return array();
		}
		return $crumbs;		
	}
	
	function _mergeCrumbs() {
		$args = func_get_args();
		$crumbs = array();
		foreach ($args as $crumb) {
			if (empty($crumb)) {
				continue;
			}
			if (!is_array($crumb)) {
				$crumb = array($crumb);
			}
			foreach ($crumb as $c) {
				if (empty($c) || $c == array(null, null, null)) {
					continue;
				}
				if (!is_array($c)) {
					$c = array($c);
				}
				$crumbs[] = ($c + array(null, null, null));
			}
		}
		return $crumbs;
	}
	
	//Checks the passed variables to see if there has been a query result passed to view
	function _getResult($model) {
		$varName = InflectorPlus::varNameSingular($model);
		if (isset($this->viewVars[$varName]) && is_array($this->viewVars[$varName])) {
			$result = $this->viewVars[$varName];
		} else if (isset($this->request->data[$model])) {
			$result = array($model => $this->request->data[$model]);
		} else {
			$result = null;
		}
		return $result;
	}
	
	function _getModel() {
		$model = $primaryKey = $displayField = null;
		if (isset($this->request->params['currentModel'])) {
			$model = $this->request->params['currentModel']['name'];
			$primaryKey = $this->request->params['currentModel']['primaryKey'];
			if (!empty($this->request->params['currentModel']['displayField'])) {
				$displayField = $this->request->params['currentModel']['displayField'];
			} else {
				$displayField = 'title';
			}
		} else if (!empty($this->request->params['models'][0])) {
			$model = $this->request->params['models'][0]['name'];
			if ($Model = ClassRegistry::init($model)) {
				$model = $Model->alias;
				$displayField = $Model->displayField;
				$primaryKey = $Model->primaryKey;
			}
		}
		return compact('model', 'displayField', 'primaryKey');
	}
	
	function _getHomeUrl($home, $url = array()) {
		if (!empty($home)) {
			if (empty($url) && !empty($this->homeUrl)) {
				$url = $this->homeUrl;
			}
			if (!empty($url)) {
				$home = !is_array($home) ? array($home, $url) : array(1 => $url) + $home;
			}
			if (is_array($home)) {
				$home += array(null, null, array());
				$home[2] += array(
					'escape' => false,
					'title' => 'Home',
					'class' => 'home',
				);
			}
		} else {
			$home = null;
		}
		return $home;	
	}
	
	function _getUrlBase($options = array()) {
		$options = array_merge(array(
			'controller' => $this->request->params['controller'],
			'action' => $this->request->params['action'],
		), $options);
		extract($options);

		if (!empty($this->request->params['prefix'])) {
			$action = Prefix::removeFromAction($action, $this->request->params['prefix']);
		}

		$urlBase = compact('controller', 'action');
		if (!isset($prefix)) {
			$prefix = !empty($this->request->params['prefix']) ? $this->request->params['prefix'] : false;
		}
		if ($prefix) {
			$urlBase[$prefix] = true;
		}
		
		if (!empty($options['urlAdd'])) {
			$urlBase = $options['urlAdd'] + $urlBase;
		}
		return $urlBase;	
	}
}