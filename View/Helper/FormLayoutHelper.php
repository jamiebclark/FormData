<?php
/**
 * Layout Helper outputs some basic Html objects that help form a better organized view
 *
 **/
 
class FormLayoutHelper extends AppHelper {
	var $helpers = array('Asset','Html', 'Form', 'Layout', 'Iconic');
	var $buttonIcons = array(
		'add' => 'plus',
		'update' => 'check',
		'cancel' => 'minus_alt',
		'next' => 'arrow_right',
		'prev' => 'arrow_left',
		'upload' => 'arrow_up',
	);
	
	var $toggleCount = 0;
	
	var $_inputCount = array();
	function beforeRender($viewFile) {
		parent::beforeRender($viewFile);
		$this->Asset->js('Layout.form_layout');
		$this->Asset->css('Layout.layout');
	}
	
	function newPassword($name, $options = array()) {
		$pw = $this->_randomString(10);
		$pwMsg = 'Use random password: <strong>' . $pw . '</strong>';
		$after = $this->Html->link(
			$pwMsg,
			array('#' => 'top'),
			array(
				'onclick' => "$(this).prev().attr('value', '$pw');return false;",
				'escape' => false,
				'class' => 'newPassword',
			)
		);
		$options = array_merge(array(
			'type' => 'password',
			'after' => $after,
		), $options);
		return $this->Form->input($name, $options);
	}
		//Our old version of input auto complete
	function inputAutoCompleteOLD($name, $url, $options = array()) {
		if (is_array($url)) {
			$url = Router::url($url);
		}
		if (substr($url,-1) != '/') {
			$url .= '/';
		}
		$options = $this->addClass($options, 'input text inputAutoComplete', 'div');
		$options['type'] = 'text';
		$options['autocomplete'] = 'off';
		$options['multiple'] = false;
		$options['after'] = $this->Html->div('selectDropdownWrapper', $this->Html->div(
			'selectDropdown', 
			'',
			array(
				'url' => $url
			)
		));
		
		if (!empty($options['submit'])) {
			unset($options['submit']);
			$options['after'] .= $this->Form->button(
				$this->Iconic->icon('magnifying_glass'), //$this->Html->image('/img/icn/16x16/magnifier.png'), 
					array(
						'type' => 'submit',
						'div' => false,
					)
			);
		}
		
		return $this->Form->input($name, $options);
	}
	
	function inputAutoCompleteMulti($model, $url = null, $attrs = array()) {
		$attrs = array_merge(array(
			'vals' => array(),
			'label' => 'Search',
			'displayField' => 'title',
			'primaryKey' => 'id',
			'habtm' => false,
			'checked' => array(),
			'unchecked' => array(),
			'default' => array(),
		), $attrs);
		extract($attrs);
		$i = 0;
		
		$habtm = empty($field);
		$field = $habtm ? '' : ".$primaryKey";
		
		$checked = $this->__resultToList($checked, $model);
		$unchecked = $this->__resultToList($unchecked, $model);
		if (!empty($this->request->data[$model])) {
			$hasData = true;
			$checked += $this->__resultToList($this->request->data[$model], $model);
		}
		if (!empty($default)) {
			if (!empty($hasData)) {
				$unchecked += $this->__resultToList($default, $model);
			} else {
				$checked += $this->__resultToList($default, $model);
			}
		}
		//if (!empty($suggested)) {
		$usedVals = array();
		$allVals = array($checked, $unchecked);

		$valsOutput = '';
		foreach ($allVals as $unchecked => $vals) {
			foreach ($vals as $id => $title) {
				if (!empty($usedVals[$id])) {
					continue;
				}
				$usedVals[$id] = $id;
				$valsOutput .= $this->Html->tag('label',
					$this->Form->checkbox("$model.$i$field", array(
						'hiddenField' => false,
						'checked' => !$unchecked,
						'value' => $id,
					))
				. $title) . "\n";
				$i++;
			}
		}
		$valsOutput = $this->Html->div('vals', $valsOutput);
		
		if (!empty($options)) {
			$valsOutput .= $this->Form->input("$model.$i$field", array(
				'type' => 'select',
				'div' => false,
				'label' => false,
				'style' => 'display: none',
				'class' => 'default-vals',
				'options' => array('' => '---') + $this->__resultToList($options, $model),
			));
			$i++;
		}
		
		$out = $this->Form->input("$model.$i$field", compact('url', 'label') + array(
			'type' => 'text',
			'div' => 'input text input-autocomplete-multi',
			'after' => $valsOutput,
		));
		
		return $out;
	}
	
	function inputAutoComplete($searchField = 'title', $url = null, $options = array()) {
		$custom = array(
			'action' => null,
			'idField' => null,
			'display' => false,
			'prefix' => '',
			'addDiv' => false,
			'displayInput' => null,
			'redirectUrl' => null,
		);
		$options = array_merge(array(
			'label' => null,
			'div' => 'input text input-autocomplete',
			'value' => null,
		), $custom, $options);
		extract($options);
		$hasValue = !empty($value);

		//debug(array($displayOptions, $this->Html->value($prefix . $idField)));
		
		if (!$hasValue && $this->Html->value($prefix . $idField)) {
		
			if (!empty($displayOptions[$this->Html->value($prefix . $idField)])) {
				$value = $displayOptions[$this->Html->value($prefix . $idField)];
			}		
		}
		
		foreach ($custom as $key => $val) {
			unset($options[$key]);
		}
		
		$url = $this->_cleanupUrl($url);
		
		if (!isset($prefix) || $prefix !== false) {
			$prefix = '';
			if (isset($model)) {
				$prefix .= $model . '.';
			}
			if (isset($count)) {
				$prefix .= $count . '.';
			}
		}
		
		if (!empty($display) && empty($displayInput)) {
			$displayInput = $this->Html->div('display fakeInput text', $hasValue ? $value : '', array('style'=> 'display:none;'));
		}
		$idInput = !empty($idField) ? $this->Form->hidden($prefix . $idField) : '';

		$return = '';
		if (!empty($action)) {
			$options = $this->addClass($options, 'action-' . $action, 'div');
		}
		if (!empty($addDiv)) {
			$options = $this->addClass($options, $addDiv, 'div');
		}
		if (!empty($redirectUrl)) {
			$redirect_url = is_array($redirectUrl) ? Router::url($redirectUrl) . '/' : $redirectUrl;
		}
		$return .= $this->input($prefix . $searchField, array_merge($options, array(
			'type' => 'text',
			'before' => $idInput,
			'between' => $displayInput,
		) + compact('url', 'value', 'redirect_url')));
		return $return;
	}
	
	function input($name, $options = array()) {
		$beforeInput = '';
		$afterInput = '';

		$options = array_merge(array(
			'type' => 'text',
			'div' => 'input',
		), $options);
		$options = $this->addClass($options, $options['type'], 'div');
		
		if ($search = Param::keyCheck($options, 'search', true)) {
			if (!isset($options['form'])) {
				$options['form'] = true;
			}
			
			if (!isset($options['submit'])) {
				$options['submit'] = array(
					$this->Iconic->icon('magnifying_glass'), 
					array(
						'div' => false,
						'type' => 'search',
					)
				);
			}
			$options = $this->addClass($options, 'search-input', 'div');
		}
		
		if ($form = Param::keyCheck($options, 'form', true)) {
			list($formName, $formOptions) = array(null, array());
			if (is_array($form)) {
				$formOptions = $form;
			} else {
				$formName = $form;
			}

			
			$beforeInput = $this->Form->create($formName === true ? null : $formName, $formOptions);
			$afterInput = $this->Form->end();
		}
		
		if ($submit = Param::keyCheck($options, 'submit', true)) {
			$default = array('div' => false, null);
			if (is_array($submit)) {
				if (!isset($submit[1])) {
					$submit[1] = $default;
				} else {
					$submit[1] = array_merge($default, $submit[1]);
				}
			} else {
				$submit = array($submit, $default);
			}
			
			if (empty($options['before'])) {
				$options['before'] = '';
			}
			if (empty($options['after'])) {
				$options['after'] = '';
			}
			$options['after'] .= $this->submit($submit[0], $submit[1]);
			$options = $this->addClass($options, 'contain-button', 'div');
		}
		
		return $beforeInput . $this->Form->input($name, $options) . $afterInput;
	}
	
	function inputAutoCompleteSelect($searchField = 'title', $idField = 'id', $url = null, $options = array()) {
		$options = array_merge(array(
			'display' => true,
			'value' => '',
		) + compact('idField', 'searchField'),$options);
		
		extract($options);
		
		return $this->inputAutoComplete($searchField, $url, $options);
		/*
		if (!isset($prefix)) {
			$prefix = '';
			if (isset($model)) {
				$prefix .= $model . '.';
			}
			if (isset($count)) {
				$prefix .= $count . '.';
			}
		}
		
		$return = '';
		$class = 'input-autocomplete';
		if (!empty($action)) {
			$class .= ' action-' . $action;
		}
		
		$return .= $this->Html->div($class, null, compact('url'));
		$return .= $this->Form->hidden($prefix . $idField);
		$return .= $this->Form->input($prefix . $searchField, array(
			'type' => 'text',
			'between' => $this->Html->div('display fakeInput text', empty($value) ? '' : $value),
		) + compact('label', 'value'));
		$return .= "</div>\n";
		return $return;
		*/
	}

	//Creates an input not used for submitting, but for highlighting and copying the text inside
	function inputCopy($value, $options = array()) {
		$return = '';
		
		$showForm = Param::keyCheck($options, 'form', true, true);
		$name = Param::keyCheck($options, 'name', true, 'copy_input');
		
		$options = array_merge(array(
			'type' => 'text',
			'value' => $value,
			'label' => false,
			'onclick' => 'this.select()',
			'readonly' => 'readonly',
		), $options);
		
		if ($showForm) {
			$return .= $this->Form->create(null, array('class' => 'fullFormWidth'));
		}
		$return .= $this->Form->input($name, $options);
		if ($showForm) {
			$return .= $this->Form->end();
		}
		
		return $return;
	}
	
	function button($text, $attrs = array()) {
		$tagAttrs = array();
		$attrs = array_merge(array(
			'class' => 'button',
			'imgPosition' => 'before',
		), $attrs);
		
		if ($align = Param::keyCheck($attrs, 'align', true)) {
			$attrs = $this->addClass($attrs, 'align-' . $align);
		}

		$type = Param::keyCheck($attrs, 'type', true);
		if (empty($type) && !empty($text) && !is_array($text)) {
			$words = explode(' ', strip_tags($text));
			if (!empty($words)) {
				$type = strtolower($words[0]);
			}
		}
		
		if (!empty($type)) {
			$attrs = $this->addClass($attrs, $type);
			/*
			if ($type == 'submit') {
				return $this->submit($text, $attrs);
			} else if ($type == 'reset') {
				return $this->reset($text, $attrs);
			}
			*/
		}
		
		if (!empty($attrs['tagAttrs'])) {
			$tagAttrs = array_merge($tagAttrs, $attrs['tagAttrs']);
		}
		if ($align = Param::keyCheck($attrs, 'align', true)) {
			$tagAttrs += compact('align');
		}
		
		$img = Param::keyCheck($attrs, 'img', true);
		if (!isset($img)) {
			$class = Param::keyCheck($attrs, 'class');
		}
		if (!empty($img)) {
			if ($attrs['imgPosition'] == 'after') {
				$text .= ' ' . $this->Html->image($img);
			} else {
				$text = $this->Html->image($img) . ' ' . $text;
			}
			$attrs['escape'] = false;
		}
		unset($attrs['imgPosition']);
		
		$text = $this->buttonIcon($type) . $text;
		
		if ($url = Param::keyCheck($attrs, 'url', true)) {
			$button = $this->Html->link($text, $url, $attrs);
		} else {
			$button = $this->Form->button($text, $attrs);
		}
		return $this->buttonWrapper($button, $attrs, $tagAttrs);
	}

	function buttonWrapper($return, $attrs = array(), $tagAttrs = array()) {
		if (($div = Param::keyCheck($attrs, 'div', true)) !== null) {
			if (!$div) {
				return $return;
			}
			$attrs['tag'] = 'div';
			if ($div !== true) {
				$tagAttrs = $this->addClass($tagAttrs, $div);
			}
		}			
		if (!Param::falseCheck($attrs, 'tag')) {
			$tag = Param::keyCheck($attrs, 'tag', true, 'div');
			if (!Param::falseCheck($attrs, 'clear', true)) {
				$tagAttrs = $this->addClass($tagAttrs, 'clearfix');
			}
			$return = $this->Html->tag($tag, $return, $this->buttonsAttrs($tagAttrs));
		}
		if (!empty($attrs['end'])) {
			$return .= $this->Form->end();
		}
		return $return;
	}
	
	function buttonsAttrs($attrs = array()) {
		$attrs = $this->addClass($attrs, 'layout-buttons');
		if ($align = Param::keyCheck($attrs, 'align', true)) {
			if ($align == 'left') {
				$attrs = $this->addClass($attrs, 'align-left');
			} else if ($align == 'right') {
				$attrs = $this->addClass($attrs, 'align-right');
			}
		}
		return $attrs;
	}
	
	function buttonIcon($type) {
		if (!empty($this->buttonIcons[$type])) {
			return $this->Iconic->icon($this->buttonIcons[$type]);
		} else {
			return '';
		}
	}
	
	/*
	
	$layout-buttons = array(
		0 => 'Submit',
		'Add Photo' => 'Upload',
		'Add' => array(
			'type' => 'add',
			'class' => 'secondary',
		)
		//The Old Way:
		4 => array(
			'Submit', 
			array(
				'type' => 'submit',
				'class' => 'secondary',
			)
		)
	);
	
	*/
	
	function inputList($listContent = '', $options = array()) {
		$options = array_merge(array(
			'model' => InflectorPlus::modelize($this->request->params['controller']),
			'count' => 1,
			'type' => 'element',
			'tag' => 'div',
			'titleTag' => 'h3',
			'class' => '',
			'pass' => array(),
		), $options);
		extract($options);
		$return = '';
		if (is_array($listContent)) {
			$total = count($listContent);
			$type = 'array';
		} else {
			//Adds an extra blank one
			$total = !empty($this->request->data[$model]) ? count($this->request->data[$model]) + 1 : $count;
		}
		if ($total < 0) {
			return $return;
		}
		for ($count = 0; $count < $total; $count++) {
			$return .= $this->Html->div('input-list-item');
			if ($type == 'array') {
				$return .= $listContent[$count];
			} else if ($type == 'element') {
				$return .= $this->element($listContent, compact('count') + $pass);
			} else if ($type == 'eval') {
				eval('$return .= ' . $listContent . ';');
			}
			$return .= "</div>\n";
		}
		if (!empty($legend)) {
			$tag = 'fieldset';
			$title = $legend;
		}
		if ($tag == 'fieldset') {
			$titleTag = 'legend';
		}
		if (!empty($titleTag) && !empty($title)) {
			$return = $this->Html->tag($titleTag, $title) . $return;
		}
		return $this->Html->tag($tag, $return, array('class' => 'input-list ' . $class));
	}
	
	function toggle($content, $offContent = null, $label, $options = array()) {
		$count = $this->toggleCount++;
		$options = array_merge(array(
			'checked' => null,
			'name' => 'form_layout_toggle' . $count,
			'value' => 1,
		), $options);
		extract($options);
		
		$return = $this->Html->div('form-layout-toggle');
		$toggleId = $options['name'];
		$toggleInput = $this->Form->input($name, array(
			'type' => 'checkbox',
			'label' => false,
			'div' => false,
			'id' => $toggleId,
		) + compact('checked'));
		$ellipses = '<span class="ell">...</span>';
		$return .= $this->Html->div('toggle-input', $this->Html->tag('label', $toggleInput . $label, array('for' => $toggleId)) . $ellipses);
		$return .= $this->Html->div('toggle-content', $content, array(
			'style' => $checked ? null : 'display:none;',
		));
		
		if (!empty($offContent)) {
			$return .= $this->Html->div('toggle-off-content', $offContent, array(
				'style' => !$checked ? null : 'display:none;',
			));
		}
		
		$return .= "</div>\n";
		return $return;
	}
	
	//Keeps track of re-using helper input elements, adding a counter to prevent two inputs with the same name
	function __inputNameCount($name) {
		if (empty($this->_inputCount[$name])) {
			$this->_inputCount[$name] = 0;
		}
		return $name . ($this->_inputCount[$name]++);
	}
	
	function inputChoices($inputs, $options = array()) {
		$options = array_merge(array(
			'name' => $this->__inputNameCount('input_choice'),
		), $options);
		if (!isset($options['default'])) {
			$options['default'] = isset($options['values'][0]) ? $options['values'][0] : 0;
		}
		extract($options);
				
		$return = "\n";
		$count = 0;
		foreach ($inputs as $label => $input) {
			//$input = '<input name="input_choice" value="'. $count . '"';
			$radioValue = isset($options['values'][$count]) ? $options['values'][$count] : $count;
			$isDefault = $radioValue == $default || (empty($default) && $count == 0);
			$return .= $this->Html->div('input-choice-input radio side-inputs',
				$this->Form->radio($name, array($radioValue => $label), array(
					'label' => !is_numeric($label) ? $label : false,
					'legend' => false,
					'value' => $default,
				))
			) . "\n";
			if (is_array($input)) {
				if (empty($input['fieldset']) && empty($input['legend'])) {
					$input['fieldset'] = false;
				}
				$input = $this->Form->inputs($input);
			}

			$return .= $this->Html->div('input-choice', $input, array(
				'style' => empty($isDefault) ? 'display:none;' : null,
			)) . "\n";
			$count++;
		}
		$return = $this->Html->div('input-choices', $return) . "\n";
		
		if (!empty($legend)) {
			$return = $this->Layout->fieldset($legend, $return);
		}
		return $return;
	}
	function buttons($buttons = array(), $attrs = array()) {
		$return = '';
		$buttonCount = 0;
		$attrs = array_merge(array(
			'align' => 'left',
		), $attrs);
		
		$secondary = Param::keyCheck($attrs, 'secondary', true);
		
		foreach ($buttons as $buttonText => $buttonAttrs) {
			if (is_numeric($buttonText)) {
				if (!is_array($buttonAttrs)) {
					if (preg_match('/[^a-zA-Z 0-9]+/', $buttonAttrs)) {
						$return .= $buttonAttrs;
						continue;
					} else {
						list($buttonText, $buttonAttrs) = array($buttonAttrs, array());
					}
				} else {
					list($buttonText, $buttonAttrs) = $buttonAttrs + array(null, array());
				}
			} else if (!is_array($buttonAttrs)) {
				$buttonAttrs = array('type' => $buttonAttrs);
			}
			$buttonAttrs = array('tag' => false) + $buttonAttrs;
			if ($secondary && ++$buttonCount > 1) {
				$buttonAttrs = $this->addClass($buttonAttrs, 'secondary');
			}
			$return .= $this->button($buttonText, $buttonAttrs);
		}
		$tag = Param::keyCheck($attrs, 'tag', true);
		return $this->buttonWrapper($return, compact('tag'), $attrs);
	}
	
	function submit($text = null, $attrs = array()) {
		$return = '';
		if (is_array($text)) {
			$return .= $this->buttons($text, $attrs);
		} else {
			if ($text === false) {
				$text = '';
			} else if (!isset($text)) {
				$text = 'Submit';
			}
			$attrs = array_merge(array(
				'class' => 'submit',
			), $attrs);
			$return .= $this->button($text, $attrs);
		}
		return $return;
	}
	
	function end($text = null, $attrs = array()) {
		return $this->submit($text, $attrs) . $this->Form->end();
	}

	function submitOLD_VERSION($text = null, $attrs = array()) {
		$return = '';
		if (is_array($text)) {
			foreach ($text as $buttonText => $buttonAttrs) {
				if (is_numeric($buttonText)) {
					$buttonText = $buttonAttrs;
					$buttonAttrs = array();
				}
				if (!empty($attrs)) {
					$buttonAttrs = array_merge($attrs, $buttonAttrs);
				}
				$buttonAttrs += array('tag' => false);
				$return .= $this->submit($buttonText, $buttonAttrs);
			}
		} else {
			if ($text === false) {
				$text = '';
			} else if (!isset($text)) {
				$text = 'Submit';
			}
			$attrs = array_merge(array(
				'class' => 'submit',
				'escape' => false,
				'tag' => null,
			), $attrs);
			if ($url = Param::keyCheck($attrs, 'url', true)) {
				$attrs = $this->addClass($attrs, 'button');
				$return .= $this->Html->link($text, $url, $attrs);
			} else {
				$return .= $this->button($text, $attrs);
			}
		}
		if (!Param::falseCheck($attrs, 'tag')) {
			$class = 'layout-buttons';
			if (!empty($attrs['div'])) {
				$tag = 'div';
				if ($attrs['div'] !== true) {
					$class =  $attrs['div'];
				}
			} else {					
				$tag = empty($attrs['tag']) || $attrs['tag'] === true ? 'div' : $attrs['tag'];
			}
			$return = $this->Html->tag($tag, $return, compact('class'));
		}
		return $return;
	}
	
	function searchInput($name, $options = array(), $form = false) {
		$button = $this->Form->button(
			$this->Iconic->icon('magnifying_glass'), 
			array(
				'type' => 'submit',
				'div' => false,
			)
		);

		$options = array_merge(array(
			'placeholder' => false,
			'label' => false,
			'value' => '',
			'type' => 'text',
		), $options, array(
			'div' => 'search-box',
			'between' => $this->Html->div('search-box-border') . $this->Html->div('search-box-container'),
			'after' => "</div>\n" . $button . "</div>\n",
		));
		$return = '';
		if ($form) {
			if (!is_array($form)) {
				if ($form === true) {
					$form = array();
				} else {
					$form = array($form);
				}
			}
			$form += array(null, array());
			$return .= $this->Form->create($form[0], $form[1]);
		}
		$return .= $this->Form->input($name, $options);
		if ($form) {
			$return .= $this->Form->end();
		}
		return $return;
	}
	
	function reset($text = null, $attrs = array()) {
		if (empty($text)) {
			$text = 'Reset';
		}
		$attrs = array_merge(array(
			'class' => 'reset'
		), $attrs);
		return $this->button($text, $attrs);
	}
	
	function buttonLink($text, $url, $attrs = null, $confirm = null) {
		return $this->Html->div('layout-buttons', 
			$this->Html->link($text, $url, $attrs, $confirm)
		);
	}
	
	function fakeInput($value, $options = array()) {
		$options = array_merge(array(
			'div' => 'input text',
			'label' => false,
		), $options);
		$options = $this->addClass($options, 'fakeInput');
		
		extract($options);
		$return = '';
		if (!empty($label)) {
			$return .= $this->Html->tag('label', $label);
		}
		$return .= $this->Html->div($class, $value);
		if (!empty($div)) {
			$return = $this->Html->div($div, $return);
		}
		return $return;
	}
	
	function _paramCheck($param, $val = null, $attrs = null) {
		if ($param == 'submit') {
			return $this->submit($val, $attrs);
		} else if ($param == 'reset') {
			return $this->reset($val, $attrs);
		} else {
			return $param;
		}
	}
	
	/**
	 * Generates a random string of letters and numbers
	 * Taken from: http://www.lost-in-code.com/programming/php-code/php-random-string-with-numbers-and-letters/
	 *
	 **/
	function _randomString($length = 10) {
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$string = '';
		for ($p = 0; $p < $length; $p++) {
			$string .= $characters[mt_rand(0, strlen($characters) - 1)];
		}
		return $string;
	}
	
	function _cleanupUrl($url) {
		if (is_array($url)) {
			$url = Router::url($url);
		}
		if (substr($url,-1) != '/') {
			$url .= '/';
		}		
		return $url;
	}
	
	function __resultToList($result, $model, $primaryKey = 'id', $displayField = 'title') {
		$list = array();
		if (!empty($result[$model])) {
			$result = $result[$model];
		}
		//Twice for HABTM
		if (!empty($result[$model])) {
			$result = $result[$model];
		}
		if (is_array($result)) {
			foreach ($result as $key => $val) {
				if (is_array($val)) {
					if (!empty($val[$model])) {
						$val = $val[$model];
					}
					$key = $val[$primaryKey];
					$val = $val[$displayField];
				}
				$list[$key] = $val;
			}
		}
		return $list;
	}
}
?>