<?php
class TableHelper extends AppHelper {
	var $name = 'Table';
	var $helpers = array(
		'Html', 
		'Form',
		'Paginator',
		'Layout.Asset',	);
	
	var $row = array();
	var $rows = array();
	var $headers = array();
	
	var $columnCount = 0;
	
	var $skip = array();
	var $tableRow = array();
	var $getHeader = true;
	var $hasHeader = false;
	var $hasForm = false;
	var $checkboxCount = 0;
	var $currentCheckboxId;
	
	var $currentTableId = 1;
	
	var $trCount = 0;
	var $tdCount = 0;
	
	//Form Properties
	var $defaultModel;
	private $formAddRow = array();
	
	function beforeRender($viewFile) {
		$this->Asset->css('Layout.layout');
		$this->Asset->js('Layout.table');
		$this->defaultModel = InflectorPlus::modelize($this->request->params['controller']);
		return parent::beforeRender($viewFile);
	}
	
	//Adds an array of column ids to skip
	function skip($skipIds = null) {
		if (empty($skipIds)) {
			$skipIds = array();
		} else if (!is_array($skipIds)) {
			$skipIds = array($skipIds);
		}
		$this->skip = $skipIds;
	}
	
	//Adds another cell to the current table row
	function cell($cell, $header = null, $headerSort = null, $skipId = null, $cellOptions = array()) {
		//Checks if the skipId is in the skip array
		if (!empty($skipId) && $this->__checkSkip($skipId)) {
			return false;
		}
		$formAddCell = '&nbsp;';
		
		if ($this->getHeader) {
			$this->columnCount++;
			//Stores first instance of non-blank header
			if (!empty($header) && !$this->hasHeader) {
				$this->hasHeader = true;
			}
			if ($headerSort) {
				if ($headerSort === true) {
					$headerSort = null;
				}
				$this->headers[] = $this->_thSort($header, $headerSort);
			} else {
				$this->headers[] = $header;
			}
		}
		
		if ($editCell = Param::keyCheck($cellOptions, 'edit', true)) {
			$formAddCell = $editCell;
			$cell = $this->_editCell($cell, $editCell);
			$this->hasForm = true;
		}
		
		if (is_array($cellOptions)) {
			$cell = array($cell, $cellOptions);
		}
		$this->row[] = $cell;
		if ($this->trCount == 0) {
			$this->formAddRow[] = $formAddCell;
		}
	}
	
	function cells($cells = null, $rowEnd = false) {
		if (is_array($cells)) {
			foreach ($cells as $cell) {
				$cell += array(null, null, null, null, null);
				$this->cell($cell[0], $cell[1], $cell[2], $cell[3], $cell[4]); 
			}
		}
		if ($rowEnd) {
			$this->rowEnd();
		}
	}
	
	function tableCheckbox($options = array()) {
		if (!empty($options) && !is_array($options)) {
			$options = array('value' => $options);
		}
		$name = 'table_checkbox.' . $this->checkboxCount;
		$name = 'data[table_checkbox][' . $this->checkboxCount . ']';
		$id = 'table_checkbox' . $this->checkboxCount;
		$options = array_merge(array(
			'name' => $name,
			'type' => 'checkbox',
			'label' => false,
			'div' => false,
			'hiddenField' => false,
			'id' => $id,
		), $options);
		$this->currentCheckboxId = $options['id'];
		$this->checkboxCount++;
		return $this->Form->input($name, $options);
	}
	
	function checkbox($options = array()) {
		if (!empty($options) && !is_array($options)) {
			$options = array('value' => $options);
		}
		$cell = $this->tableCheckbox($options);
		$header = $this->Form->input('check-all-checkbox', array(
			'name' => 'check-all-checkbox',
			'type' => 'checkbox',
			'class' => 'check-all',
			'div' => false,
			'label' => false,
		));
		
		$attrs = array(
			'width' => 20,
			'edit' => array(
				'id' => array(
					'value' => $options['value'],
					'type' => 'hidden',
				)
			),
			'class' => 'table-checkbox',
		);
		return $this->cell($cell, $header, null, 'checkbox', $attrs);
	}
	
	function withChecked($content = null) {
		$return = '';
		$return .= $this->Html->div('with-checked');
		if (is_array($content)) {
			$withChecked = array('' => ' -- Select action -- ');
			foreach ($content as $action => $label) {
				if (is_int($action)) {
					$action = $label;
					$label = InflectorPlus::humanize($label);
				}
				$withChecked[$action] = $label;
			}
			$return .= $this->Form->input(null, array(
				'type' => 'select',
				'options' => $withChecked,
				'label' => 'With Checked:',
				'div' => false,
				'name' => 'checked_action',
			));
		} else {
			$return .= $content;
		}
		$return .= $this->Form->submit('Go', array('name' => 'with_checked','div' => false));
		$return .= "</div>\n";
		return $return;
	}

	
	function rowEnd() {
		$this->getHeader = false;
		$row = $this->row;
		
		$this->tdCount = 0;
		$this->trCount++;
		
		$this->rows[] = $row;
		$this->row = array();
		return $row;
	}
	
	function table($options = array()) {
		$options = array_merge(array(
			'form' => $this->hasForm,
		), $options);
		
		$this->currentTableId++;
		
		if (!is_array($options)) {
			$options = array($options => true);
		}
		
		$isEmpty = empty($this->rows);
		
		$output = '';
		$after = '';
		
		if (!$this->hasHeader) {
			$this->headers = null;
		}
		
		if (!$isEmpty && !empty($this->checkboxCount)) {
			if (!empty($options['withChecked'])) {
				if (!isset($options['form'])) {
					$options['form'] = true;
				}
				$after .= $this->withChecked($options['withChecked']);
				unset($options['withChecked']);
			}
			
			//Wraps it in a form tag
			if (!isset($options['form'])) {
				$options['form'] = $this->hasForm;
			}
		}
		$formOptions = !empty($options['form']) ? $options['form'] : null;
		unset($options['form']);

		$output .= $this->_table($this->headers, $this->rows, $options + compact('after'));
		
		if (!empty($formOptions)) {
			$output = $this->formWrap($output, $formOptions);
		}
		
		$this->reset();
		return $output;
	}
	
	function formWrap($output, $options = null) {
		return $this->formOpen($options) . $output . $this->formClose($options);
	}
	
	function formOpen($options = null) {
		if ($this->hasForm && !isset($options)) {
			$options = true;
		}
		$out = '';
		if (!empty($options)) {
			if (!is_array($options)) {
				if ($options !== true) {
					$options = array('model' => $options['form']);
				} else {
					$options = array();
				}
			}
			$options = array_merge(array(
				'id' => 'tableOutput' . $this->currentTableId,
				'url' => '/' . $this->request->url,
				//'action' => false,
			), $options);
			
			if (!empty($options['model'])) {
				$modelName = $options['model'];
				unset($options['model']);
			} else {
				$modelName = InflectorPlus::modelize($this->request->params['controller']);
			}
			$out .= $this->Form->create($modelName, $options);
			$out .= $this->Form->hidden('useModel', array(
				'value' => $modelName,
				'name' => 'useModel',
			));
		}
		return $out;
	}
	
	function formClose($options = array()) {
		$out = '';
		if ($options === true || !isset($options['form']) || $options['form'] !== false) {
			$out .= $this->Form->end();
		}
		return $out;
	}
	
	function reset($set = null) {
		$this->_set(array(
			'skip' => array(),
			'row' => array(),
			'rows' => array(),
			'headers' => array(),
			'hasHeader' => false,
			'hasForm' => false,
			'formAddRow' => array(),
			'getHeader' => true,
			'checkboxCount' => 0,
			'currentCheckboxId' => null,
			'columnCount' => 0,
			'tdCount' => 0,
			'trRcount' => 0,
		));

		if (!empty($set)) {
			$this->_set($set);
		}
	}
	
	function isSkipped($th) {
		return $this->__checkSkip($th);
	}
	
	function __checkSkip($skipId = null) {
		if (empty($this->skip)) {
			return false;
		} else if (is_array($this->skip)) {
			return in_array($skipId, $this->skip);
		} else {
			return $skipId == $this->skip;
		}
	}
	
	function _table($headers = null, $rows = null, $options = array()) {
		if (Param::keyValCheck($options, 'paginate', true)) {
			$paginateNav = $this->_paginateNav();
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
	
		/**
	 * A uniform grouping of the Paginator functions to make all paginate menus look the same
	 *
	 **/
	function _paginateNav($options = null) {
		/*
		if (empty($this->Paginator)) {
			return '';
		}
		*/
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

	function _thSort($label = null, $sort = null, $options = array()) {
		$options = array_merge(array(
			'model' => null
		), $options);
		
		if (empty($label)) {
			$label = '&nbsp;';
		}
		$paginate = true || !empty($this->Paginator);
		if (!empty($paginate)) {
			$params = $this->Paginator->params($options['model']);
			$paginate = !empty($params);
		}

		if (!$paginate) {
			return $this->_thSortLink($sort, $label); //ucfirst($label);
		} else {
			return $this->Paginator->sort($sort, $label, array('escape' => false));
		}
	}
	
	function _thSortLink($sort, $label = null) {
		$direction = 'asc';
		$class = null;

		if (!empty($this->request->params['named']['sort']) && $this->request->params['named']['sort'] == $sort) {
			if (!empty($this->request->params['named']['direction']) && $this->request->params['named']['direction'] == 'asc') {
				$direction = 'desc';
			}
			$class = $direction;
		}
		return $this->Html->link($label, compact('sort', 'direction'), compact('class'));
	}
}