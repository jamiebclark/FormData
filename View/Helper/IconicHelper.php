<?php
class IconicHelper extends AppHelper {
	var $name = 'Iconic';
	var $helpers = array('Html');
	
	var $useUnicode = false;
	
	var $actions = array(
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
		'duplicate' => 'new_window',
		'settings' => 'cog',
		'photo' => 'camera',
		'story' => 'chat',
		'blog' => 'chat',
	);

	var $chars = array(
		'lightbulb' => '',
		'equalizer' => '',
		'brush_alt' => '',
		'move' => '',
		'tag_fill' => '',
		'book_alt2' => '',
		'layers' => '',
		'chat_alt_fill' => '',
		'layers_alt' => '',
		'cloud_upload' => '',
		'chart_alt' => '',
		'fullscreen_exit_alt' => '',
		'cloud_download' => '',
		'paperclip' => '',
		'heart_fill' => '❤',
		'mail' => '✉',
		'pen_alt_fill' => '',
		'check_alt' => '✘',
		'battery_charging' => '',
		'lock_fill' => '',
		'stop' => '',
		'arrow_up' => '↑',
		'move_horizontal' => '',
		'compass' => '',
		'minus_alt' => '',
		'battery_empty' => '',
		'comment_fill' => '',
		'map_pin_alt' => '',
		'question_mark' => '?',
		'list' => '',
		'upload' => '',
		'reload' => '',
		'loop_alt4' => '',
		'loop_alt3' => '',
		'loop_alt2' => '',
		'loop_alt1' => '',
		'left_quote' => '❝',
		'x' => '✓',
		'last' => '',
		'bars' => '',
		'arrow_left' => '←',
		'arrow_down' => '↓',
		'download' => '',
		'home' => '⌂',
		'calendar' => '',
		'right_quote_alt' => '',
		'unlock_fill' => '',
		'fullscreen' => '',
		'dial' => '',
		'plus_alt' => '',
		'clock' => '',
		'movie' => '',
		'steering_wheel' => '',
		'pen' => '✎',
		'pin' => '',
		'denied' => '⛔',
		'left_quote_alt' => '',
		'volume_mute' => '',
		'umbrella' => '☂',
		'list_nested' => '',
		'arrow_up_alt1' => '',
		'undo' => '',
		'pause' => '',
		'bolt' => '⚡',
		'article' => '',
		'read_more' => '',
		'beaker' => '',
		'beaker_alt' => '',
		'battery_full' => '',
		'arrow_right' => '→',
		'iphone' => '',
		'arrow_up_alt2' => '',
		'cog' => '⚙',
		'award_fill' => '',
		'first' => '',
		'trash_fill' => '',
		'image' => '',
		'comment_alt1_fill' => '',
		'cd' => '',
		'right_quote' => '❞',
		'brush' => '',
		'cloud' => '☁',
		'eye' => '',
		'play_alt' => '',
		'transfer' => '',
		'pen_alt2' => '',
		'camera' => '',
		'move_horizontal_alt2' => '',
		'curved_arrow' => '⤵',
		'move_horizontal_alt1' => '',
		'aperture' => '',
		'reload_alt' => '',
		'magnifying_glass' => '',
		'calendar_alt_fill' => '',
		'fork' => '',
		'box' => '',
		'map_pin_fill' => '',
		'bars_alt' => '',
		'volume' => '',
		'x_alt' => '✔',
		'link' => '',
		'move_vertical' => '',
		'eyedropper' => '',
		'spin' => '',
		'rss' => '',
		'info' => 'ℹ',
		'target' => '',
		'cursor' => '',
		'key_fill' => '⚿',
		'minus' => '➖',
		'book_alt' => '',
		'headphones' => '',
		'hash' => '#',
		'arrow_left_alt1' => '',
		'arrow_left_alt2' => '',
		'fullscreen_exit' => '',
		'share' => '',
		'fullscreen_alt' => '',
		'comment_alt2_fill' => '',
		'moon_fill' => '☾',
		'at' => '@',
		'chat' => '',
		'move_vertical_alt2' => '',
		'move_vertical_alt1' => '',
		'check' => '✗',
		'mic' => '',
		'book' => '',
		'move_alt1' => '',
		'move_alt2' => '',
		'document_fill' => '',
		'plus' => '➕',
		'wrench' => '',
		'play' => '',
		'star' => '★',
		'document_alt_fill' => '',
		'chart' => '',
		'rain' => '⛆',
		'folder_fill' => '',
		'new_window' => '',
		'user' => '',
		'battery_half' => '',
		'aperture_alt' => '',
		'eject' => '',
		'arrow_down_alt1' => '',
		'pilcrow' => '¶',
		'arrow_down_alt2' => '',
		'arrow_right_alt1' => '',
		'arrow_right_alt2' => '',
		'rss_alt' => '',
		'spin_alt' => '',
		'sun_fill' => '☀',
	);
	
	//Iconic Unicodes. Be sure to add &#x before outputting them
	var $codes = array(
		'lightbulb' => 'e063',
		'equalizer' => 'e052',
		'brush_alt' => 'e01c',
		'move' => 'e03e',
		'tag_fill' => 'e02b',
		'book_alt2' => 'e06a',
		'layers' => 'e01f',
		'chat_alt_fill' => 'e007',
		'layers_alt' => 'e020',
		'cloud_upload' => 'e045',
		'chart_alt' => 'e029',
		'fullscreen_exit_alt' => 'e051',
		'cloud_download' => 'e044',
		'paperclip' => 'e08a',
		'heart_fill' => '2764',
		'mail' => '2709',
		'pen_alt_fill' => 'e005',
		'check_alt' => '2718',
		'battery_charging' => 'e05d',
		'lock_fill' => 'e075',
		'stop' => 'e04a',
		'arrow_up' => '2191',
		'move_horizontal' => 'e038',
		'compass' => 'e021',
		'minus_alt' => 'e009',
		'battery_empty' => 'e05c',
		'comment_fill' => 'e06d',
		'map_pin_alt' => 'e002',
		'question_mark' => '003f',
		'list' => 'e055',
		'upload' => 'e043',
		'reload' => 'e030',
		'loop_alt4' => 'e035',
		'loop_alt3' => 'e034',
		'loop_alt2' => 'e033',
		'loop_alt1' => 'e032',
		'left_quote' => '275d',
		'x' => '2713',
		'last' => 'e04d',
		'bars' => 'e06f',
		'arrow_left' => '2190',
		'arrow_down' => '2193',
		'download' => 'e042',
		'home' => '2302',
		'calendar' => 'e001',
		'right_quote_alt' => 'e012',
		'unlock_fill' => 'e076',
		'fullscreen' => 'e04e',
		'dial' => 'e058',
		'plus_alt' => 'e008',
		'clock' => 'e079',
		'movie' => 'e060',
		'steering_wheel' => 'e024',
		'pen' => '270e',
		'pin' => 'e067',
		'denied' => '26d4',
		'left_quote_alt' => 'e011',
		'volume_mute' => 'e071',
		'umbrella' => '2602',
		'list_nested' => 'e056',
		'arrow_up_alt1' => 'e014',
		'undo' => 'e02f',
		'pause' => 'e049',
		'bolt' => '26a1',
		'article' => 'e053',
		'read_more' => 'e054',
		'beaker' => 'e023',
		'beaker_alt' => 'e010',
		'battery_full' => 'e073',
		'arrow_right' => '2192',
		'iphone' => 'e06e',
		'arrow_up_alt2' => 'e018',
		'cog' => '2699',
		'award_fill' => 'e022',
		'first' => 'e04c',
		'trash_fill' => 'e05a',
		'image' => 'e027',
		'comment_alt1_fill' => 'e003',
		'cd' => 'e064',
		'right_quote' => '275e',
		'brush' => 'e01b',
		'cloud' => '2601',
		'eye' => 'e025',
		'play_alt' => 'e048',
		'transfer' => 'e041',
		'pen_alt2' => 'e006',
		'camera' => 'e070',
		'move_horizontal_alt2' => 'e03a',
		'curved_arrow' => '2935',
		'move_horizontal_alt1' => 'e039',
		'aperture' => 'e026',
		'reload_alt' => 'e031',
		'magnifying_glass' => 'e074',
		'calendar_alt_fill' => 'e06c',
		'fork' => 'e046',
		'box' => 'e06b',
		'map_pin_fill' => 'e068',
		'bars_alt' => 'e00a',
		'volume' => 'e072',
		'x_alt' => '2714',
		'link' => 'e077',
		'move_vertical' => 'e03b',
		'eyedropper' => 'e01e',
		'spin' => 'e036',
		'rss' => 'e02c',
		'info' => '2139',
		'target' => 'e02a',
		'cursor' => 'e057',
		'key_fill' => '26bf',
		'minus' => '2796',
		'book_alt' => 'e00b',
		'headphones' => 'e061',
		'hash' => '0023',
		'arrow_left_alt1' => 'e013',
		'arrow_left_alt2' => 'e017',
		'fullscreen_exit' => 'e050',
		'share' => 'e02e',
		'fullscreen_alt' => 'e04f',
		'comment_alt2_fill' => 'e004',
		'moon_fill' => '263e',
		'at' => '0040',
		'chat' => 'e05e',
		'move_vertical_alt2' => 'e03d',
		'move_vertical_alt1' => 'e03c',
		'check' => '2717',
		'mic' => 'e05f',
		'book' => 'e069',
		'move_alt1' => 'e03f',
		'move_alt2' => 'e040',
		'document_fill' => 'e066',
		'plus' => '2795',
		'wrench' => 'e078',
		'play' => 'e047',
		'star' => '2605',
		'document_alt_fill' => 'e000',
		'chart' => 'e028',
		'rain' => '26c6',
		'folder_fill' => 'e065',
		'new_window' => 'e059',
		'user' => 'e062',
		'battery_half' => 'e05b',
		'aperture_alt' => 'e00c',
		'eject' => 'e04b',
		'arrow_down_alt1' => 'e016',
		'pilcrow' => '00b6',
		'arrow_down_alt2' => 'e01a',
		'arrow_right_alt1' => 'e015',
		'arrow_right_alt2' => 'e019',
		'rss_alt' => 'e02d',
		'spin_alt' => 'e037',
		'sun_fill' => '2600'
	);
	
	function menu($list = array(), $options = array()) {
		$options = array_merge(array(
			'tag' => 'nav',
			'class' => 'iconic-list',
		), $options);
		extract($options);
		
		$return = '';
		foreach ($list as $item) {
			list($title, $url, $attrs, $onclick) = $item + array(null, null, array(), null);
			/*
			$return .= $this->Html->tag('li', $this->Html->link(
				$this->icon($title),
				$url,
				array('escape' => false) + (array) $attrs,
				$onclick
			));
			*/
			$return .= $this->Html->link(
				$this->icon($title),
				$url,
				array('escape' => false) + (array) $attrs,
				$onclick
			);

		}
		//$return = $this->Html->tag('ul', $return);
		return $this->Html->tag($tag, $return, compact('class'));
	}
	
	function icon($title, $options = array()) {
		$options = array_merge(array(
			'class' => '',
			'tag' => 'span',
		), $options);
		extract($options);
		$out = '';
		
		if (!empty($class)) {
			$class .= ' iconic';
		} else {
			$class = 'iconic';
		}
		
		if ($icon = $this->getIcon($title)) {
			if (!empty($url)) {
				$icon = $this->Html->link($icon, $url);
			}
			$out .= $this->Html->tag($tag, $icon, compact('class') + array('escape' => false));
		}
		
		return $out;
	}
	
	function getIcon($title) {
		$icon = $this->useUnicode ? $this->getCode($title) : $this->getChar($title);
		if (!$icon && !empty($this->actions[$title])) {
			return $this->getIcon($this->actions[$title]);
		}
		return $icon;
	}
	
	function getCode($title) {
		if (!empty($this->codes[$title])) {
			return '&#x' . $this->codes[$title];
		} else {
			return false;
		}
	}
	
	function getChar($title) {
		return !empty($this->chars[$title]) ? $this->chars[$title] : false;
	}
	
	function test() {
		$return = '';
		foreach ($this->codes as $title => $val) {
			$return .= "'$title' => '&#x$val',<br/>\n";
		}
		return $return;
	}

}
?>