<?php
/**
* Layout Helper outputs some basic Html objects that help form a better organized view
*
*/

App::uses('Param', 'Lib');
 
class LayoutHelper extends AppHelper {
	var $helpers = array(
		'Html', 
		'Paginator', 
		'Layout.Asset',
		'Layout.Crumbs',
		'Layout.Iconic',
		'Layout.Calendar', 
	);

	var $actionIcons = array(
		'index' => 'list',
		'active' => 'check_alt',
		'add' => 'plus',
		'inactive' => 'x_alt',
		'edit' => 'pen',
		'settings' => 'cog',
		'delete' => 'x',
		'view' => 'magnifying_glass',
		'submit' => 'check',
		'spam' => 'target',
		'move_up' => 'arrow_up',
		'move_down' => 'arrow_down',
		'move_top' => 'upload',
		'move_bottom' => 'download',
	);
	
	var $autoActions = array(
		'index', 'edit', 'delete', 'view', 'add', 
		'move_up', 'move_down', 'move_top', 'move_bottom', 'settings',
		'spam', 'clock',
	);

	function beforeRender($viewFile) {
		$this->Asset->css('Layout.layout');
		$this->Asset->js('Layout.layout');
		parent::beforeRender($viewFile);
	}
	
	/**
	 * CONTENT BOX
	 * A basic box for displaying a section of information
	 *
	 **/
	function contentBox($title = null, $content = null, $params = null) {
		return $this->contentBoxOpen($title, $params) . $this->contentBoxClose($content);
	}
	function contentBoxOpen($title = null, $params = null) {
		$class = 'contentBox';
		if (empty($title)) {
			$class .= ' contentBoxBlank';
		}
		if ($paramClass = Param::keyCheck($params, 'class', true)) {
			$class .= ' ' . $paramClass;
		}
		if (!empty($params['toggle'])) {
			$class .= ' toggle';
			$params['url'] = '#';	//Setting toggle overwrites existing URL option
			if ($params['toggle'] == -1) {
				$toggleClose = true;
				$class .= ' toggleClose';
			}
		}
		$render = $this->Html->div($class);
		
		if (!empty($params['close'])) {
			if (empty($params['actionMenu'])) {
				$params['actionMenu'] = array(array(), array());
			}
			$params['actionMenu'][0][] = array(
				$this->Iconic->icon('x'),
				'#', 
				array(
					'escape' => false,
					'onclick' => "$(this).closest('.contentBox').hide();return false;"
				)
			);
		}
		$actionMenu = Param::keyCheck($params, 'actionMenu', true);
		$url = Param::keyCheck($params, 'url', true);
		$icon = Param::keyCheck($params, 'icon', true);
		if (!empty($title) && !empty($url)) {
			$title = $this->Html->link($title, $url);
		}
		if ($actionMenu && empty($title)) {
			$title = '&nbsp;';
		}
		if (!empty($icon)) {
			$title = $this->Iconic->icon($icon) . ' ' . $title;
		}
		if (!empty($title) || !empty($actionMenu)) {
			$actionMenu = array_merge((array)$actionMenu, array(null, null));
			$actionMenu[1]['icons'] = true;
			$render .= $this->headingActionMenu($title, $actionMenu[0], $actionMenu[1]);
		}
		
		$bodyClass = 'contentBoxBody';
		$bodyOptions = array();
		if (!empty($params['bodyClass'])) {
			$bodyClass = $params['bodyClass'];
		} else if (!empty($params['list'])) {
			$bodyClass = 'contentBoxBodyList';
		}
		if (!empty($toggleClose)) {
			$bodyOptions['style'] = 'display:none;';
		}
		$render .= $this->Html->div($bodyClass, null, $bodyOptions);
		return $render;
	}
	function contentBoxClose($content = null) {
		$render = '';
		if (!empty($content)) {
			$render .= $content;
		}
		//Close the body and container div
		return $render . "\n</div>\n</div>\n";
	}
	
	/**
	 * A uniform grouping of the Paginator functions to make all paginate menus look the same
	 *
	 **/
	function paginateNav($options = null) {
		//if (empty($this->Paginator)) {
		//	return '';
		//}
		if ($options['hideBlank'] !== false && !$this->Paginator->hasPage(2)) {
			return '';
		}
		$render = '';
		
		$render .= $this->Html->div('paginateControl');
		$render .= $this->Paginator->prev(
			'&laquo; ' . __('prev'), 
			array('class' => 'control', 'escape' => false), 
			null, 
			array('class'=>'control disabled', 'escape' => false)
		);
		//$render .= ' | ';
		
		$render .= $this->Paginator->numbers(array(
			'first' => 3,
			'last' => 3,
			'separator' => '',
		));
		//$render .= ' | ';
		
		$render .= $this->Paginator->next(
			__('next') . ' &raquo;', 
			array('class' => 'control', 'escape' => false), 
			null, 
			array('class' => 'control disabled', 'escape' => false)
		);
		$render .= "</div>";

		//'Page %page% of %pages%, showing %current% records out of %count% total, starting on record %start%, ending on %end%'
		$totalRows = number_format($this->Paginator->counter(array('format' => '%count%')));
		$render .= $this->Html->div('paginateCounter', $this->Paginator->counter(array(
			'format' => __('Showing %current% records out of ' . $totalRows . ' total')
		)));
		
		return $this->Html->div('paginateNav', $render);
	}
	
	/**
	 * Menu displayed beneath an HTML header row
	 *
	 */
	function headerMenu($menu = null, $attrs = array()) {
		$attrs = array_merge(array(
			'tag' => 'div',
			'class' => 'layoutHeaderMenu'
		), (array) $attrs);
		return $this->menu($menu, $attrs) . $this->clearFix();
	}
	
	/**
	 * Extends headerMenu, adding commonly added entries to the beginning of the menu
	 *
	 **/
	function defaultHeaderMenu($modelId = null, $menu = array(), $attrs = array()) {
		$controller = Param::keyCheck($attrs, 'controller', true, $this->request->params['controller']);
		
		$prefix = !empty($this->request->params['prefix']) ? $this->request->params['prefix'] : null;
		$prefix = Param::keyCheck($attrs, 'prefix', true, $prefix);
		$urlBase = compact('controller') + array($prefix => true);
		
		$human = Inflector::singularize(InflectorPlus::humanize($controller));
		if (!empty($modelId)) {
			$baseMenu = array(
				array(
					'Edit ' . $human,
					$urlBase + array('action' => 'edit', $modelId),
					array(
						'icon' => 'pen',
					)
				),
				array(
					'Delete ' . $human,
					$urlBase + array('action' => 'delete', $modelId),
					array(
						'icon' => 'x',
					),
					'Delete this ' . $human . '?'
				)
			);
		} else {
			$baseMenu = array(
				array('Add ' . $human, $urlBase + array('action' => 'add')),
			);
		}
		
		$finalMenu = array();
		if (!empty($baseMenu)) {
			$finalMenu = $baseMenu;
		}
		if (!empty($menu) && is_array($menu)) {
			foreach ($menu as $row) {
				$finalMenu[] = $row;
			}
		}
		return $this->headerMenu($finalMenu, $attrs);
	}
	
	function defaultHeader($modelId = null, $menu = array(), $attrs = array()) {
		if (is_array($modelId)) {
			$attrs = $modelId;
			$modelId = null;
		}
		
		$defaultAttrs = array(
			'id' => $modelId,
			//'menu' => array(),
			'model' => $human = InflectorPlus::modelize($this->request->params['controller']),
		);
		$attrs = array_merge($defaultAttrs, $attrs);
		extract($attrs);
		if (!empty($id)) {
			$modelId = $id;
		}
		
		$output = '';
		
		if (!isset($crumbs) || $crumbs !== false) {	
			$this->Crumbs->addCrumbs(!empty($crumbs) ? $crumbs : null, compact('baseCrumbs', 'defaultCrumbs'));
		}
		$action = $this->_getAction();
		if ($action == 'add' || $action == 'edit') {
			$menu = false;
			$title = InflectorPlus::humanize($action) . ' ' . InflectorPlus::humanize($model);
		}
		
		$titleAttrs = array();
		if (empty($title)) {
			$title = $model;
			
			if (empty($modelId)) {
				$title = Inflector::pluralize($model);
				$titleAttrs['class'] = 'top-title';
			}
			$title = InflectorPlus::humanize($title);
		}
		
		
		if (!empty($title)) {
			$output .= $this->Html->tag('h1', $title, $titleAttrs);
		}
		
		foreach ($defaultAttrs as $k => $v) {
			unset($attrs[$k]);
		}
		
		if (!isset($menu) || $menu !== false) {
			$output .= $this->defaultHeaderMenu($modelId, $menu, $attrs);
		}
		return $output;
	}
	
	function neighbors($prev = null, $next = null, $up = null, $options = array()) {
		$return = '';
		$class = 'neighbors';
		
		if (is_array($prev)) {
			$prevOptions = $prev;
			
			$arrayKeys = array('prev', 'next', 'up');
			$foundOptions = false;
			foreach($arrayKeys as $key) {
				if (isset($prevOptions[$key]) && (empty($$key) || $key == 'prev')) {
					$$key = $prevOptions[$key];
					unset($prevOptions[$key]);
					$foundOptions = true;
				}
			}
			if ($foundOptions) {
				$options = array_merge($options, $prevOptions);
			}
		}

		if (!empty($up)) {
			$class .= ' has-up';
		}
		
		$neighborChecks = array(
			array('arrow_left', $prev, 'prev'),
			array('arrow_up', $up, 'up'),
			array('arrow_right', $next, 'next'),
		);
		foreach ($neighborChecks as $neighborCheck) {
			list($icon, $link, $addClass) = $neighborCheck;
			$icon = $this->Iconic->icon($icon);
			if (empty($link)) {
				continue;
			}
			if (is_array($link)) {
				if (!empty($options['model']) && isset($link[$options['model']])) {
					$displayField = !empty($options['displayField']) ? $options['displayField'] : 'title';
					$url = $this->modelUrl($options['model'], $link);
					$title = $link[$options['model']][$displayField];
					$linkAttrs = array();
				} else {
					list($title, $url, $linkAttrs) = $link + array(null, null, array());
				}
				$linkAttrs['escape'] = false;
				$link = $this->Html->link("$icon $title", $url, $linkAttrs);
			} else {
				$link = "$icon $link";
			}
			$return .= $this->Html->div('neighbor ' . $addClass, $link);
		}
		
		if (!empty($return)) {
			return $this->Html->div($class, $return);
		} else {
			return '';
		}
	}
	
	/**
	 * Menu displayed above an HTML header row
	 *
	 **/
	function topMenu($menu = null, $attrs = null) {
		return $this->Html->div('layoutTopMenu', $this->menu($menu, $attrs));
	}
	
	function tabMenu($menu = null, $attrs = array()) {
		$attrs = array_merge(array(
			'tag' => 'div',
			'class' => 'layoutTabMenu'
		), $attrs);
		if (!Param::keyValCheck($attrs, 'currentSelect')) {
			$attrs['currentSelect'] = true;
		}
		return $this->menu($menu, $attrs);
	}
	
	function sideMenu($menu = null, $attrs = array()) {
		$attrs = array_merge(array(
			'tag' => 'div',
			'class' => 'layoutSideMenu',
		), $attrs);
		if (!Param::keyValCheck($attrs, 'currentSelect')) {
			$attrs['currentSelect'] = true;
		}

		return $this->menu($menu, $attrs);
	}
	
	/**
	 * Menu for displaying index actions
	 *
	 **/
	function actionMenu($menu = null, $attrs = array()) {
		$attrs = array_merge(array(
			'tag' => 'div',
			'class' => 'layoutActionMenu',
			'named' => false,
		), $attrs);

		$resize = Param::keyCheck($attrs, 'resize', true, true);
		$named = Param::keyCheck($attrs, 'named', true);
		$url = Param::keyCheck($attrs, 'url', true);
		$active = Param::keyCheck($attrs, 'active', true);
		
		if ($resize === false) {
			$attrs = $this->addClass($attrs, 'no-resize');
		}
		
		$useIcons = !empty($attrs['icons']);
		
		if (!empty($attrs['autoActions'])) {
			foreach ($attrs['autoActions'] as $key => $val) {
				if (is_numeric($key)) {
					$key = $val;
				}
				$this->actionIcons[$key] = $val;
				$this->autoActions[] = $key;
			}
		}
		
		if (!empty($menu)) {
			foreach ($menu as $key => $value) {
				if (is_numeric($key)) {
					list($menuItem, $config) = array($value, array());
				} else {
					list($menuItem, $config) = array($key, $value);
				}
				
				$newUrl = !empty($config['url']) ? $config['url'] : $url;
				if (!empty($config['urlAdd'])) {
					$newUrl = $config['urlAdd'] + $newUrl;
				}
				
				$id = null;
				if (!empty($newUrl['id'])) {
					$id = $newUrl['id'];
				} else if (!empty($newUrl[0]) && is_numeric($newUrl[0])) {
					$id = $newUrl[0];
				}
				
				if (in_array($menuItem, array('up', 'down', 'top', 'bottom'))) {
					$newUrl = array($menuItem => $id);
					$menuItem = 'move_' . $menuItem;
					$skipUrlBuild = true;
				} 
				
				if ($menuItem == 'duplicate') {
					$controller = !empty($newUrl['controller']) ? $newUrl['controller'] : $this->request->params['controller'];
					$model = Inflector::singularize(Inflector::camelize($controller));
					$menu[$key] = $this->Duplicate->iconLink($model, $id, array('array' => true, 'icons' => $useIcons));
				} else if ($menuItem == 'active') {
					$menu[$key] = $this->activateLink($id, !empty($active), array('icons' => $useIcons) + $config);
				} else if (!empty($url) && in_array($menuItem, $this->autoActions)) {
					if ($named) {
						$newUrl[$menuItem] = $newUrl[0];
						unset($newUrl[0]);
					} else if (empty($skipUrlBuild)) {
						$newUrl['action'] = $menuItem;
						if ($menuItem != 'view' || empty($newUrl['slug'])) {
							if (empty($newUrl[0])) {
								$newUrl[0] = $id;
							}
							unset($newUrl['id']);
							unset($newUrl['slug']);
						}

						if ($menuItem == 'spam') {
							$newUrl['spam'] = $newUrl[0];
							$newUrl['action'] = 'index';
							unset($newUrl[0]);
						}
						
						if ($menuItem == 'index') {// || $menuItem == 'add') {
							unset($newUrl[0]);
						}
					}

					$title = Inflector::humanize($menuItem);
					if (($action = $this->getAction($menuItem, $useIcons))) {
						$linkText = $action;
					} else {
						$linkText = $title;
					}
					
					$postMsg = null;
					if ($menuItem == 'delete') {
						$postMsg = 'Delete this item?';
					} else if ($menuItem == 'spam') {
						$postMsg = 'This will remove the group and all associated users and collections. Continue?';
					}

					$linkOptions = array('class' => $menuItem, 'title' => $title, 'escape' => false);
					if(!empty($config['class'])) {
						$linkOptions = $this->addClass($linkOptions, $config['class']);
					}
					$menu[$key] = array(
						$linkText, 
						$newUrl,
						$linkOptions,
						$postMsg
					);
				} else if (is_array($menuItem)) {
					$menu[$key][2]['escape'] = false;
					if (!empty($menuItem[2]['class'])) {
						$class = $menuItem[2]['class'];
						if (($action = $this->getAction($class, $useIcons))) {
							$menu[$key][0] = $action . ' ' . $menu[$key][0];
							$menu[$key][2]['escape'] = false;							
						}
					}				
				}
				
				//ID Replace
				if (is_array($menu[$key][1])) {
					foreach ($menu[$key][1] as $urlKey => $urlVal) {
						if ($urlVal == 'ID') {
							$menu[$key][1][$urlKey] = $id;
						}
					}
				}
			}
		}
		if (Param::keyValCheck($attrs, 'titleList')) {
			$tag = 'font';
			$return = $this->Html->tag($tag, null, array('class' => $attrs['class']));
			foreach ($menu as $menuItem) {
				if (is_array($menuItem)) {
					$menuItem += array(null, null, null, null);
					$return .= $this->Html->link($menuItem[0], $menuItem[1], $menuItem[2], $menuItem[3]);
				} else {
					$return .= $menuItem;
				}
			}
			$return .= "</$tag>\n";
			return $return;
		} else {
			if (!empty($attrs['vertical'])) {
				$attrs['class'] .= ' actionMenuVertical';
				foreach ($menu as $k => $link) {
					if (is_array($link) && !empty($link[2]['title'])) {
						if ($prefix = Prefix::get($link[1])) {
							$menu[$k][0] .= ' ' . Inflector::humanize($prefix);
						}
						$menu[$k][0] .= ' ' . $link[2]['title'];
					}
				}
			}
			return $this->menu($menu, $attrs);
		}
	}
	
	function getAction($key, $useIcons = false) {
		if ($useIcons) {
			$icon = !empty($this->actionIcons[$key]) ? $this->actionIcons[$key] : $key;
			return $this->Iconic->icon($icon);
		}
		return false;
	}

	/**
	 * Creates an HTML list of web addresses based around an ID back to some sort of profile
	 *
	 **/
	function idMenu($id, $items, $options = array()) {
		$menu = array();
		$url = Param::keyCheck($options, 'url', true, array());
		$active = Param::keyCheck($options, 'active', true, true);
		$useIcons = Param::keyCheck($options, 'icon');
		$model = Param::keyCheck($options, 'model', true);
		if (empty($model)) {
			$models = $this->request->params['models'];
			$model = array_shift($models);
			$model = $model['className'];
		}
		
		if (empty($url)) {
			$url = array('action' => 'view', $id);
		}
		
		foreach ($items as $item) {
			if (!is_array($item)) {
				if ($item == 'view') {
					$item = array('View', 'view');
				} else if ($item == 'edit') {
					$item = array('Update Info', 'edit');
				} else if ($item == 'delete') {
					$item = array('Remove', 'delete');
				} else if ($item == 'duplicate') {
					$item = $this->Duplicate->iconLink($model, $id, array('icons' => $useIcons, 'fullText' => true));
				} else if ($item == 'active') {
					$item = $this->activateLink($id, !empty($active), array('icons' => $useIcons, 'fullText' => true));
				} else if ($item == 'spam') {
					$item = array('Spam', array('action' => 'index', 'spam' => $id), 'spam');
				} else  {
					$item = array(Inflector::humanize($item), array('action' => $item) + $url);
				}
			}
			if (is_array($item)) {
				$item += array(null, array(), array());
				if (!is_array($item[1])) {
					$item[1] = array('action' => $item[1], $id);
				}
				$item[1] += $url;
				if (Param::keyCheck($options, 'useId', true)) {
					$item[1][0] = $id;
				}
				
				if (!empty($item[2])) {
					if (!is_array($item[2])) {
						$item[2] = array('icon' => $item[2]);
					}
				}
				if (empty($item[2]['icon'])) {
					$item[2]['icon'] = $item[1]['action'];
				}
				$icon = Param::keyCheck($item[2], 'icon', true);
				
				$item[0] = $this->Iconic->icon($icon) . ' ' . $item[0];
				$item[2]['escape'] = false;
			}
			$menu[] = $item;
		}
		
		return $this->menu($menu, array('class' => 'layout-profile-menu h-menu'));
	}
	
	/**
	 * Extends action menu to place the contents in a header tag with a prefix line of text
	 *
	 **/
	function headingActionMenu($title, $menu = null, $attrs = array()) {
		$tag = Param::keyCheck($attrs, 'tag', true, 'h2');
		$class = 'divider clearfix';
		if (!empty($attrs['class'])) {
			$class .= ' ' . $attrs['class'];
			unset($attrs['class']);
		}
		if (empty($title)) {
			$title = Param::keyCheck($attrs, 'title', true);
		}
		$output = '';
		if (!empty($title)) {
			$output .= $title;
		}
		if (!empty($menu)) {
			$output = $this->actionMenu($menu, $attrs + array('icons' => true)) . $output;
		}
		return $this->Html->tag($tag, $output, compact('class'));
	}
	
	function adminMenu($menu = null, $attrs = array()) {
		$attrs = array_merge(array(
			'tag' => 'h2',
			'class' => 'adminMenu divider',
		), $attrs);
		$title = Param::keyCheck($attrs, 'prefix', true); //Legacy Term
		if (empty($title)) {
			$title = Param::keyCheck($attrs, 'title', true, 'Staff Only');
		}
		return $this->headingActionMenu($title, $menu, $attrs);
	}
	
	function tableSortMenu($sortMenu = array(), $attrs = array()) {
		$menu = array($this->Html->div(null, 'Sort By'));
		foreach ($sortMenu as $k => $sort) {
			$sort += array(null, null, true);
			list($title, $field, $direction) = $sort;
			if (!$direction || $direction == 'desc' || $direction == 'DESC') {
				$direction = 'desc';
			} else {
				$direction = 'asc';
			}
			if (
				(!empty($this->request->params['named']['sort']) && $this->request->params['named']['sort'] == $field) && 
				(!empty($this->request->params['named']['direction']) && $this->request->params['named']['direction'] == $direction)
			) {
				$selected = true;
			} else {
				$selected = false;
			}
			
			$menu[] = array($title, array(
				'sort' => $field,
				'direction' => $direction
				),
				array('class' => $selected ? 'selected' : null)
			);
		}
		return $this->menu($menu, array(
			'class' => 'layoutTableSortMenu',
		));
	}
	
	/**
	 * Returns a menu list with large icons above linked text, followed by an optional description
	 *
	 * Accepts menu items in this format: array(title, url, description, urlOptions, onClick)
	**/
	function largeIconMenu($menuItems, $options = array()) {
		$menu = array();
		foreach ($menuItems as $listItem) {
			list($title, $url, $icon, $description, $urlOptions, $onClick) = $listItem + array(null, null, null, null, array(), null);
			$urlOptions = array('escape' => false) + (array)$urlOptions;
			$li = $this->Html->tag('h3', $this->Html->link($this->Iconic->icon($icon) . $title, $url, $urlOptions, $onClick));
			$li .= $this->Html->tag('span', $description, array('class' => 'description'));
			$menu[] = $li;
		}
		return $this->menu($menu, array('class' => 'large-icon-menu'));
	}
	
	function __checkSelectItems($key, $link, $selectItems, $translate = array(), $linkPrefix = null, $paramPrefix = null) {
		$match = true;
		if (!is_array($selectItems) || Param::keyValCheck($selectItems, $key) !== null) {
			if ($key == 'action' && !empty($this->request->params['prefix']) && strpos($link['action'], $this->request->params['prefix']) !== 0) {
				//Checks action and also prefix + "_" + action
				$match = $link['action'] == $this->request->params['prefix'] . '_' . $this->request->params['action'];
			} else if ($key == 'prefix') {
				$match = $linkPrefix == $paramPrefix;
			} else {
				$match = $link[$key] == $this->request->params[$key];
			}
		}
		if (empty($match) && !empty($translate[$key][$link[$key]])) {
			if (!is_array($translate[$key][$link[$key]])) {
				$translate[$key][$link[$key]] = array($translate[$key][$link[$key]]);
			}
			foreach ($translate[$key][$link[$key]] as $field) {
				if ($this->__checkSelectItems($key, array($key => $field) + $link, $selectItems, null, $linkPrefix, $paramPrefix)) {
					return true;
				}
			}
		}
		return $match;
	}

	/**
	 * Outputs an array of items in an unordered list
	 *
	 **/
	function menu($menuItems = null, $attrs = null) {
		if (!is_array($menuItems)) {
			$menuItems = array($menuItems);
		}
		
		if (!empty($attrs['urlAdd'])) {
			foreach ($menuItems as $k => $menuItem) {
				if (is_array($menuItem) && !empty($menuItem[1]) && is_array($menuItem[1])) {
					$menuItems[$k][1] += $attrs['urlAdd'];
				}
			}
			unset($attrs['urlAdd']);
		}

		$debug = !empty($attrs['debug']);
		
		$urlOptions = Param::keyCheck($attrs, 'urlOptions', true, array());
		$list = '';
		$menuCount = count($menuItems);
		foreach ($menuItems as $k => $menuItem) {
			$liAttrs = array();
			//Allows for passing just an array to be read as a link
			if (is_array($menuItem) && isset($menuItem[0]) && isset($menuItem[1])) {
				list($content, $link, $options, $confirm) = $menuItem + array('', array(), array(), null);
				$options = array_merge((array) $options, $urlOptions);
				//Checks current parameters to see if it matches the given URL
				//Can pass currentSelect as an array of what items to match array('action', 'controller')
				
				if (!isset($options['icon']) && !empty($attrs['icon']) && !empty($menuItem[1]['action'])) {
					$options['icon'] = $menuItem[1]['action'];
				}
				$iconAlign = Param::keyCheck($options, 'iconAlign', true);
				if ($icon = Param::keyCheck($options, 'icon', true)) {
					$options['escape'] = false;
					$icon = $this->Iconic->icon($icon);
					if ($iconAlign == 'left') {
						$content = "$icon $content";
					} else {
						$content .= ' ' . $icon;
					}
				}
				
				//Can pass specific values in menuItem options
				$selectItems = Param::keyCheck($options, 'currentSelect', true, false);	
				if (!$selectItems) {
					//Can set general rules in the attrs
					$selectItems = Param::keyValCheck($attrs, 'currentSelect');
				}
				if (!empty($selectItems)) {
					$translate = array();
					if (is_array($selectItems)) {
						foreach ($selectItems as $key => $val) {
							if (!is_numeric($key)) {
								if (!is_array($val)) {
									$val = array($this->request->params[$key] => array($val));
								}
								$translate[$key] = $val;
								unset($selectItems[$key]);
								$selectItems[] = $key;
							}
						}
					}
					/*
					if (!empty($translate)) {
						debug(compact('translate', 'selectItems'));
					}
					*/
					if (empty($link['controller'])) {
						$link['controller'] = $this->request->params['controller'];
					}
					if (empty($link['action'])) {
						$link['action'] = $this->request->params['action'];
					}
					
					//debug($this->request->params);
					$linkPrefix = null;
					$paramPrefix = null;

					if (!empty($this->request->params['prefix'])) {
						$paramPrefix = $this->request->params['prefix'];
					}
					
					$linkPrefix = Prefix::get($link);
					$paramPrefix = Prefix::get($this->request->params);
					
					if (empty($linkPrefix) && (!isset($link[$paramPrefix]) || $link[$paramPrefix] !== false)) {
						$linkPrefix = $paramPrefix;
					}
					
					if (!empty($linkPrefix)) {
						$link['action'] = $linkPrefix . '_' . $link['action'];
					}
					$trace  = $link['controller'] . ' == ' . $this->request->params['controller'] . ' and ';
					$trace .= $link['action'] . ' == ' . $this->request->params['action'] . ' and ';
					$trace .= $linkPrefix . ' == ' . $paramPrefix;
					

					if ($debug) {
						debug($trace);
					}
					
					$checkKeys = array('controller', 'action', 'prefix');
					$match = true;
					foreach ($checkKeys as $key) {
						if (!($match = $this->__checkSelectItems(
							$key, 
							$link, 
							$selectItems, 
							$translate,
							$linkPrefix,
							$paramPrefix
						))) {
							if ($debug) {
								debug(array("Failed matching $key", $link, $this->request->params));
							}
							break;
						}
					}
					
					/*
					if (!is_array($selectItems) || Param::keyValCheck($selectItems, 'controller') !== null) {
						$match *= $link['controller'] == $this->request->params['controller'];
					}
					if (!is_array($selectItems) || Param::keyValCheck($selectItems, 'action') !== null) {
						//Checks action and also prefix + "_" + action
						$match *= ($link['action'] == $this->request->params['action']) || (!empty($this->request->params['prefix']) && $link['action'] == $this->request->params['prefix'] . '_' . $this->request->params['action']);
					}
					if (!is_array($selectItems) || Param::keyValCheck($selectItems, 'prefix') !== null) {
						$match *= $linkPrefix == $paramPrefix;
					}
					*/
					if ($match) {
						$liAttrs = $this->addClass($liAttrs, 'selected');
					}
				}
				$menuItem = $this->Html->link($content, $link, $options, $confirm);
			} else {
				if (!empty($attrs['currentSelect'])) {
					if (preg_match('#href="([^"]*)"#', $menuItem, $matches)) {
						if ($matches[1] == $this->request->url) {
							$liAttrs = $this->addClass($liAttrs, 'selected');
						}
					}
				}
			}
			$liAttrs['escape'] = false;
			if ($k == $menuCount - 1) {
				$liAttrs = $this->addClass($liAttrs, 'last');
			}
			
			$list .= $this->Html->tag('li', $menuItem, $liAttrs);
		}
		unset($attrs['currentSelect']);
		
		if (empty($attrs['class'])) {
			$attrs['class'] = 'layoutMenu';
		}
		
		$tag = Param::keyCheck($attrs, 'tag', true, 'div');
		$output = $this->Html->tag($tag, $this->Html->tag('ul', $list), $attrs);
		if (!empty($attrs['preCrumb']) || !empty($attrs['pre_crumb'])) {
			$View =& $this->getView();
			$View->viewVars['pre_crumb'] = $output;
			$output = '';
		}
		return $output;
	}
	
	/**
	 * Creates a definition list formatted for vertical listing
	 *
	 **/
	function definitionList($definitions = null, $options = array()) {
		if (!empty($definitions)) {
			$render = $this->definitionListClose($definitions, $options);
		} else {
			$render = $this->Html->tag('dl', null, $options);
		}
		return $render;
	}
	
	function definitionListClose($definitions = null, $options = array()) {
		$render = $this->definitionList(null, $options);
		if (!empty($definitions)) {
			foreach ($definitions as $dt => $dd) {
				if (empty($dd) && !empty($options['hideEmpty'])) {
					continue;
				}
				$render .= $this->definition($dt, $dd);
			}
		}
		$render .= "</dl>\n";
		return $render;
	}
		
	/**
	 * Creates one element in a definition list
	 *
	 **/
	function definition($term = null, $definition = null, $options = array() ) {
		$render  = $this->Html->tag('dt', !empty($term) ? $term : '&nbsp;');
		$render .= $this->Html->tag('dd', !empty($definition) ? $definition : '&nbsp;');
		return $render;
	}
	
	//Creates a fieldset with legend at the top
	//Closes the tag if $content is present
	function fieldset($legend = null, $content = null, $params = null) {
		$return = $this->Html->tag('fieldset', null, $params);
		if (!empty($legend)) {
			$return .= $this->Html->tag('legend', $legend);
		}
		if (!empty($params['note'])) {
			$return .= $this->Html->tag('font', $params['note'], array('class' => 'note'));
		}
		if (!empty($content)) {
			$return .= $content . "</fieldset>\n";
		}
		return $return;		
	}
	
	function thSort($label = null, $sort = null, $options = array()) {
		$options = array_merge(array(
			'model' => null
		), $options);
		
		if (empty($label)) {
			$label = '&nbsp;';
		}
		$paginate = !empty($this->Paginator);
		if (!empty($paginate)) {
			$params = $this->Paginator->params($options['model']);
			$paginate = !empty($params);
		}
		if (!$paginate) {
			return $this->thSortLink($label, $sort); //ucfirst($label);
		} else {
			return $this->Paginator->sort($label, $sort, array('escape' => false));
		}
	}
	
	function thSortLink($label, $sort = null) {
		$direction = 'asc';
		$class = null;
		if (empty($sort)) {
			$sort = str_replace(' ', '_', strtolower($label));
		}
		if (!empty($this->request->params['named']['sort']) && $this->request->params['named']['sort'] == $sort) {
			if (!empty($this->request->params['named']['direction']) && $this->request->params['named']['direction'] == 'asc') {
				$direction = 'desc';
			}
			$class = $direction;
		}
		return $this->Html->link($label, compact('sort', 'direction'), compact('class'));
	}
	
	function table($headers = null, $rows = null, $options = array()) {
		if (Param::keyValCheck($options, 'paginate', true)) {
			$paginateNav = $this->paginateNav();
		} else {
			$paginateNav = '';
		}
		
		if (empty($rows) && ($empty = Param::keyCheck($options, 'empty', true))) {
			return $empty;
		}
		$options = array_merge(array(
				'cellspacing' => 0,
				'border' => 0,
				//Default Html->tableCells stuff
				'tableCells' => array(),
			), (array) $options
		);
		$options['tableCells'] = array_merge(array(
			'oddTrOptions' => array('class' => 'altrow'),
			'evenTrOptions' => null,
			'useCount' => false,
			'continueOddEven' => true
		), $options['tableCells']);

		$return = '';
		
		$tableCellOptions = Param::keyCheck($options, 'tableCells', true, array());
		$div = Param::keyCheck($options, 'div', true);
		
		if ($div) {
			$return .= $this->Html->div($div);
		}
		$return .= $paginateNav;
		if (!empty($options['before'])) {
			$return .= $options['before'];
			unset($options['before']);
		}
		if (!empty($options['after'])) {
			$after = $options['after'];
			unset($options['after']);
		} else {
			$after = '';
		}
		
		$return .= $this->Html->tag('table', null, $options);
		if (!empty($headers)) {
			$return .= $this->Html->tableHeaders($headers);
		}
		if (!empty($rows)) {
			extract($tableCellOptions);
			if (!empty($options['full_width'])) {
				foreach ($rows as $r => $row) {
					foreach ($row as $c => $cell) {
						if (!empty($cell[0])) {
							$rows[$r][$c][0] = preg_replace('/([\s]+)/', '&nbsp;', $cell[0]);
						}
					}
				}
			}
			$return .= $this->Html->tableCells($rows, $oddTrOptions, $evenTrOptions, $useCount, $continueOddEven);
		}
		$return .= "</table>\n";
		$return .= $after;
		
		$return .= $paginateNav;
		
		if ($div) {
			$return .= "</div>\n";
		}
		
		return $return;
	}
	
	function infoTable($list = array(), $options = array()) {
		$options = array_merge(array(
				'class' => 'layoutInfoTable',
				'hideEmpty' => false,
			), $options);

		$return = '';
		foreach ($list as $k => $v) {
			if (!empty($options['hideEmpty']) && empty($v)) {
				continue;
			}
			$row = sprintf('<th>%s</th>', $k);
			if (!is_array($v)) {
				$v = array($v);
			}
			foreach ($v as $td) {
				$row .= sprintf('<td>%s</td>', $td);
			}
			$return .= sprintf('<tr>%s</tr>', $row) . "\n";
		}
		$return = sprintf('<table>%s</table>', $return) . "\n";
		return $this->Html->div($options['class'], $return);
	}
	
	function resultDisplay($Result, $lines = array(), $options = array()) {
		$options = array_merge(array(
			'separator' => "<br/>\n",
		), $options);
		
		$return = '';
		foreach ($lines as $field => $fieldOptions) {
			if (is_numeric($field)) {
				if (is_array($fieldOptions)) {
					$return .= $this->resultDisplay($Result, $fieldOptions, array('separator' => ' ') + $options);
					continue;
				} else {
					$field = $fieldOptions;
					$fieldOptions = array();
				}
			} else if (!is_array($fieldOptions)) {
				$fieldOptions = array($fieldOptions => true);
			}
			
			if (!empty($fieldOptions['class']) && empty($fieldOptions['tag'])) {
				$fieldOptions['tag'] = 'div';
			}
			
			//debug(array($field, $Result[$field], $Result));
			if (empty($Result[$field])) {
				if (!empty($fieldOptions['notEmpty'])) {
					continue;
				} else {
					$Result[$field] = '';
				}
			}
			
			if (!empty($fieldOptions['tag'])) {
				$tagOptions = array();
				if (!empty($fieldOptions['class'])) {
					$tagOptions['class'] = $fieldOptions['class'];
				}
				$return .= $this->Html->tag($fieldOptions['tag'], null, $tagOptions);
			}
			
			$return .= $Result[$field];
			
			if (!empty($fieldOptions['tag'])) {
				$return .= '</' . $fieldOptions['tag'] . ">\n";
			}
			
			if (isset($fieldOptions['separator'])) {
				$return .= $fieldOptions['separator'];
			} else {
				$return .= $options['separator'];
			}
		}
		return $return;
	}
	
	
	function infoTableResult($result, $options = array()) {
		$options = array_merge(array(
			'fields' => array(),
			'blacklist' => array(),
			'model' => InflectorPlus::modelize($this->request->params['controller']),
			'values' => array(),
		), $options);
		
		extract($options);
		
		if (!empty($model) && empty($fields)) {
			App::import('Model', $model);
			$Model = new $model();
			$fields = $Model->schema();
			$useSchema = true;
		} else {
			$useSchema = false;
		}
	
		$modelResult = !empty($result[$model]) ? $result[$model] : $result;
		$controller = Inflector::tableize($model);
		
		foreach ($blacklist as $modelName => $modelFields) {
			if (is_numeric($modelName)) {
				$modelName = $model;
			}
			
			if (!preg_match('/^[A-Z]/', $modelName)) {
				$modelFields = array($modelName);
				$modelName = $model;
			}
			
			foreach ($modelFields as $field) {
				unset($fields[$modelName][$field]);
				if ($modelName == $model) {
					unset($fields[$field]);
				}
			}
		}
		/**
		 - skipEmpty
		 - showEmpty
		 - type
		 - url
		**/
		foreach ($fields as $field => $config) {
			if (is_numeric($field)) {
				$field = $config;
				$config = array();
			}
			//First char is capital, treats it like a model name
			if (preg_match('/^[A-Z]/', $field)) {
				debug($field);
				$values = $this->infoTableResult($result, array(
					'model' => $field, 
					'fields' => $config,
					'returnArray' => true,
				) + compact('blacklist', 'values'));
			} else {
				$value = !empty($config['derived']) ? $result[0][$field] : $modelResult[$field];
				$modelId = !empty($modelResult['id']) ? $modelResult['id'] : null;
				
				if ($useSchema) {
					if (substr($field, -3) == '_id') {
						$config['type'] = 'hidden';
					}
					if ($field == $Model->displayField) {
						$config['url'] = true;
						$config['showEmpty'] = true;
					}
				}
				
				if (empty($value)) {
					if (!empty($config['skipEmpty']) || !empty($skipEmpty)) {
						continue;
					}
					if (!empty($config['showEmpty'])) {
						$value = '<em>blank</em>';
					}
				}
				
				if (!empty($config['type'])) {
					if ($config['type'] == 'hidden') {
						continue;
					} else if ($config['type'] == 'date' || $config['type'] == 'datetime') {
						$value = $this->Calendar->niceShort($value);
					} else if ($config['type'] == 'boolean' || $config['type'] == 'tinyint') {
						$value = $this->boolOutput($value);
					} else if ($config['type'] == 'int') {
						$value = number_format($value);
					}
				}
				
				if (!empty($config['url'])) {
					if ($config['url'] === true) {
						$url = array('controller' => $controller, 'action' => 'view', $modelId);
					} else if (is_array($url)) {
						$url[] = $modelId;
					}
					$value = $this->Html->link($value, $url, array('escape' => false));
				}						
				
				$label = !empty($config['label']) ? $config['label'] : Inflector::humanize($field);
				$values[$label] = $value;
			}
		}	
		if (!empty($returnArray)) {
			return $values;
		} else {
			return $this->infoTable($values);
		}
	}
	
	function infoResultTable($Result, $cols = null, $alias = null) {
		$info = array();
		foreach ($cols as $col => $config) {
			if (is_numeric($col)) {
				$col = $config;
				$config = array();
			}
			if (empty($config['label'])) {
				$config['label'] = Inflector::humanize($col);
			}
			if (!empty($alias)) {
				if (!empty($config['alias'])) {
					$resultInfo =& $Result[$config['alias']];
				} else {
					$resultInfo =& $Result[$alias];
				}
			} else {
				$resultInfo =& $Result;
			}
			
			if (empty($resultInfo[$col])) {
				if (Param::keyValCheck($config, 'notEmpty')) {
					continue;
				} else {
					$resultInfo[$col] = '';
				}
			}
			$value = $resultInfo[$col];
			if (!empty($config['format'])) {
				if (!is_array($config['format'])) {
					$config['format'] = array($config['format']);
				}
				foreach ($config['format'] as $format) {
					if ($format == 'date') {
						$value = $this->Calendar->niceShort($value);
					} else if ($format == 'cash') {
						$value = '$' . number_format($value, 2);
					} else if ($format == 'percent') {
						$value = round($value * 100) . '%';
					} else if ($format == 'yesno') {
						$value = $this->boolOutput(!empty($value), 'Yes');
					}
				}
			}
			if (!empty($config['url'])) {
				$value = $this->Html->link($value, $config['url']);
			}
			
			if (!empty($config['div'])) {
				$config['tag'] = 'div';
				if ($config['div'] !== true) {
					$config['class'] = $config['div'];
				}
			}
			if (!empty($config['tag']) || empty($config['class'])) {
				$tag = Param::keyCheck($config, 'tag', false, 'div');
				$class = Param::keyCheck($config, 'class', false, null);
				$value = $this->Html->tag($tag, $value, compact('class'));
			}
			$info[$config['label']] = $value;
		}
		return $this->infoTable($info);
	}
	
	function activateLink($id = null, $isActive = true, $options = array()) {
		$options = array_merge(array(
			'fullText' => false,
			'paramOn' => 'activate',
			'paramOff' => 'deactivate',
			'url' => array(),
		), $options);
		
		$output  = $this->Html->tag('font', null, array('class' => 'activateLayout ' . ($isActive ? 'active' : 'inactive')));
		$useIcons = !empty($options['icons']);
		
		$alt = 'Activate';
		$output .= $this->Html->link(
			$this->getAction('inactive', $useIcons) .($options['fullText'] ? $alt : ''),
			array_merge($options['url'], array($options['paramOn'] => $id)),
			array('escape' => false, 'title' => $alt, 'class' => 'inactive activate')
		);
		
		$alt = 'Deactivate';
		$output .= $this->Html->link(
			$this->getAction('active', $useIcons) . ($options['fullText'] ? $alt : ''),
			array_merge($options['url'], array($options['paramOff'] => $id)),
			array('escape' => false, 'title' => $alt, 'class' => 'active deactivate')
		);
		$output .= "</font>\n";
		return $output;
	}
	
	
	function hover($content, $hoverContent, $attrs = array()) {
		$hoverWrap = $this->hoverWrap($hoverContent, $attrs);
		return $hoverWrap[0] . $content . $hoverWrap[1];
	}
	
	function hoverLink($title, $url, $hoverContent, $linkAttrs = array(), $hoverAttrs = array()) {
		$linkAttrs['escape'] = false;
		return $this->hover($this->Html->link($title, $url, $linkAttrs), $hoverContent, $hoverAttrs);
	}
	
	function hoverWrap($hoverContent, $attrs = array()) {
		if (is_array($hoverContent)) {
			list($hoverContent, $attrsPass) = $hoverContent;
			$attrs = array_merge($attrs, $attrsPass);
		}

		$windowAttrs = array('class' => 'hover-layout');
		$contentAttrs = !empty($attrs['contentAttrs']) ? $attrs['contentAttrs'] : array();
		$contentAttrs['style'] = 'display: none;';
		$contentAttrs = $this->addClass($contentAttrs, 'hover-content');
		
		if (!empty($attrs['width'])) {
			if ($attrs['width'] == 'slim') {
				$contentAttrs = $this->addClass($contentAttrs, 'width-slim');
			} else if ($attrs['width'] == 'wide') {
				$contentAttrs = $this->addClass($contentAttrs, 'width-wide');
			} else {
				if (is_numeric($attrs['width'])) {
					$attrs['width'] = 'width: ' . $attrs['width'] . 'px';
				}
				$width = 'width: ' . $attrs['width'];
				$contentAttrs = $this->addClass($contentAttrs, $width . ';', 'style');
			}
			unset($attrs['width']);
		}
		if (!empty($attrs['height'])) {
			if (is_numeric($attrs['height'])) {
				$attrs['height'] .= 'px';
			}
			$contentAttrs = $this->addClass($contentAttrs, $attrs['height'] . ';overflow:scroll; overflow-x: hidden;', 'style');
			unset($attrs['height']);
		}
		if (!empty($attrs['class'])) {
			$windowAttrs = $this->addClass($windowAttrs, $attrs['class']);
		}
		$open = '';
		$open .= $this->Html->tag('span', null, $windowAttrs);
		$open .= $this->Html->tag('span', null, array('class' => 'hover-over'));
		
		$close = "</span>\n";
		$close .= $this->Html->div(null, null, $contentAttrs);
		$close .= $this->Html->div('hover-arrow', '&nbsp;');
		$close .= $this->Html->div('hover-window', $hoverContent);
		$close .= "</div>\n";	//hover-content
		$close .= "</span>\n";	//hover-layout
		return array($open, $close);
	}
	
	/***
	 * Outputs a string representation whether a boolean value is true or false
	 *
	 **/
	function boolOutput($val, $trueOptions = array(), $falseOptions = array(), $basicOptions = array()) {
		if (!empty($trueOptions) && !is_array($trueOptions)) {
			$trueOptions = array('text' => $trueOptions);
		}
		if (!empty($falseOptions) && !is_array($falseOptions)) {
			$falseOptions = array('text' => $falseOptions);
		}
		$trueOptions = array_merge(array(
			'class' => 'positive',
			'text' => 'Yes',
			'tag' => 'font',
		), $trueOptions);
		
		$pairs = array(
			'True' => 'False',
			'true' => 'false',
			'yes' => 'no',
			1 => 0,
		);
		
		foreach ($pairs as $true => $false) {
			if ($trueOptions['text'] == $true) {
				$falseOptions['text'] = $false;
				break;
			}
		}

		$falseOptions = array_merge(
			$trueOptions, 
			array(
				'class' => 'negative',
				'text' => 'No',
			),
			$falseOptions
		);

		$options = $val ? $trueOptions : $falseOptions;
		$options = array_merge($options, $basicOptions);
		
		$tag = $options['tag'];
		$text = $options['text'];
		unset($options['tag']);
		unset($options['text']);
		if (!empty($options['activate'])) {
			$options['url'] = array(($val ? 'deactivate' : 'activate') => $options['activate']);				
			unset($options['activate']);
		}
		
		if (!empty($options['url'])) {
			$text = $this->Html->link($text, $options['url'], array('class' => $options['class']));
			unset($options['url']);
		}
		
		return $this->Html->tag($tag, $text, $options);
	}
	
	function dropdown($text, $dropdown, $options = array()) {
		$dropdownOptions = array('class' => 'layout-dropdown');
		$boxOptions = array('class' => 'dropdown-box');
		
		$arrow = $this->Html->image('icn/16x16/bullet_arrow_down.png');
		$out = $this->Html->link($arrow, '#', array('escape' => false, 'class' => 'arrow'));
		if (is_array($dropdown)) {
			$dropdown = $this->menu($dropdown, array('class' => 'link-list'));
			$boxOptions = $this->addClass($boxOptions, 'list');
		}
		$out .= $this->Html->tag('span', $dropdown, $boxOptions);
		return $text . $this->Html->tag('span', $out, $dropdownOptions);
	}
	
	/**
	 * Outputs a blank DIV that will fix CSS float errors
	 *
	 **/
	function clearFix() {
		return $this->Html->div('clearFix', '&nbsp;');
	}
	
	function _getAction() {
		$action = $this->request->params['action'];
		if (!empty($this->request->params['prefix'])) {
			$action = Prefix::removeFromAction($action, $this->request->params['prefix']);
		}
		return $action;
	}
}
?>