<?php
App::import('Lib', 'Date');

class CalendarHelper extends AppHelper {
	var $name = 'Calendar';
	var $helpers = array(
		'Asset','Html','Form','Time');
		
	function beforeRender($viewFile) {		$this->Asset->css('Layout.layout');
		//$this->Asset->js('event');
		parent::beforeRender($viewFile);	}
	
	function calendarDate($start, $stop = null, $options = array()) {
		if (is_array($stop)) {
			$options = $stop;
			$stop = null;
		}
		$options = array_merge(array(
			'class' => '',
			'after' => '',
		), $options);
		$options = $this->addClass($options, 'calendar-date-box');
		
		$dateRange = !empty($stop);
		
		$dateStr = 'Y-M-j';
		$now = time();
		$startStamp = strtotime($start);
		$stopStamp = strtotime($stop);
		list($y1,$m1,$d1) = explode('-', date($dateStr, $startStamp));
		list($y2,$m2,$d2) = explode('-', date($dateStr, $stopStamp));
		list($yC,$mC,$dC) = explode('-', date($dateStr));
		if (!$dateRange || $y1 == $y2) {
			$yearMatch = true;
			$year = $y1;
		} else {
			$yearMatch = false;
			$year = $this->Html->tag('span', $y1) . $this->Html->tag('span', $y2);
		}
		if (!$dateRange || ($yearMatch && ($m1 == $m2))) {
			$month = $m1;
			$monthMatch = true;
		} else {
			$month = $this->Html->tag('span', $m1) . $this->Html->tag('span', $m2);
			$monthMatch = false;
		}
		if (!$dateRange || ($monthMatch && ($d1 == $d2))) {
			$day = $d1;
			$dayMatch = true;
		} else {
			$day = $this->Html->tag('span', $d1) . $this->Html->tag('span', $d2);
			$dayMatch = false;
		}
		
		$out = '';
		$out .= $this->Html->div('month' . ($monthMatch ? '' : ' multi'), $month);
		$out .= $this->Html->div('day' . ($dayMatch ? '' : ' multi'), $day);
		if ($year != $yC || $stopStamp < $now) {
			$out .= $this->Html->div('year' . ($yearMatch ? '' : ' multi'), $year);
		}
		
		$out = $this->Html->div($options['class'], $out . $options['after']);
		
		if (!empty($options['center'])) {
			$out = $this->Html->div('calendar-date-box-wrap', $out);
		}
		if (!empty($options['url'])) {
			$out = $this->Html->link($out, $options['url'], array('escape' => false));
		}
		return $out;
	}
	
	function weekSelect($field, $options = array()) {
		$stop = !empty($options['stop']) ? $options['stop'] : date('Y-m-t', strtotime('+2 months'));
		$start = !empty($options['start']) ? $options['start'] : date('Y-m-01',strtotime($stop.' -1 year'));
		
		$options['options'] = $this->weekArray($start, $stop, false, 'F Y');
		return $this->Form->input($field, $options);
	}
	
	function weekLinks($url = null, $varName = 'week_start', $dateKey) {
		//Allows for a possible "_2" suffix
		$dateSuffixChk = explode('_', $dateKey);
		if (count($dateSuffixChk)>1) {
			list($date, $suffix) = $dateSuffixChk;
		} else {
			$date = $dateSuffixChk[0];
		}
		
		if (empty($url)) {
			$url = array(
				'controller' => $this->request->params['controller'],
				'action' => $this->request->params['action'],
			);
		}
		
		$stamp = strtotime($date);
		$date1 = date('Y-m-01', $stamp);
		
		if (!empty($suffix)) {
			list($y, $m, $d) = explode('-',$date1);
			if ($m == 12) {
				$m = 1;
				$y++;
			} else {
				$m++;
			}
			$date1 = date('Y-m-d',strtotime($y.'-'.$m.'-'.$d));
			
		}
		$date1Stamp = strtotime($date1);
		$thisYear = date('Y') == date('Y',$date1Stamp);
		
		$weekArray = $this->weekArray($date1);
		$weekArray = $weekArray[$date1];
		
		$prevMonthUrl = $this->urlBuild($url, $varName, $this->_firstWeek($date1.' -1 month'));
		$nextMonthUrl = $this->urlBuild($url, $varName, $this->_firstWeek($date1.' +1 month'));
		
		$prevWeekUrl = $this->urlBuild($url, $varName, date('Y-m-d',Date::sundayStamp($date,'before',true)));
		$nextWeekUrl = $this->urlBuild($url, $varName, date('Y-m-d',Date::sundayStamp($date,'after', true)));
		
		$return = '';
		$return .= $this->Html->div('head');
		$return .= $this->Html->link('<', $prevWeekUrl, array('class' => 'nav prev'));
		
		$return .= $this->Html->link('&laquo;', $prevMonthUrl, array('class' => 'monthNav','escape'=>false));
		$return .= date($thisYear ? 'F' : 'F, Y', $date1Stamp);
		$return .= $this->Html->link('&raquo;', $nextMonthUrl, array('class' => 'monthNav','escape'=>false));
		
		$return .= $this->Html->link('>', $nextWeekUrl, array('class' => 'nav next'));
		$return .= "</div>\n";
		
		$return .= $this->Html->div('days');
		foreach ($weekArray as $weekKey => $days) {
			$newUrl = $this->urlBuild($url, $varName, $weekKey);
			$return .= $this->Html->div('week',
				$this->Html->link($days, $newUrl, 
				array(
					'escape' => false,
					'class' => strpos($weekKey, $dateKey) === 0 ? 'current' : ''
				))
			);
		}
		$return .= "</div>\n";
		return $this->Html->div('weekLinks', $return);
	}
	
	function urlBuild($url, $varName, $varVal) {
		if (is_array($url)) {
			$url[$varName] = $varVal;
			return $url;
		} else {
			$urlAdd = '/' . $varName . ':' . $varVal;
			if (($qPos = strpos($url, '?')) !== false) {
				$url = substr($url,0,$qPos) . $urlAdd . substr($url,$qPos);
			} else {
				$url .= $urlAdd;
			}
		}
		return $url;
	}
	
	/**
	 * Used to return an array of weeks in a calendar, with the days formatted like a calendar
	 *
	 * @param str start Date to start
	 * @param str stop Date to stop. If null, will only return the month of start date
	 * @param bool isHtml If false, doesn't use HTML tags
	 * @param str monthFormat The format of the first level keys of the array
	 * @param str weekFormat The format of the second level keys of the array
	 * @return array Multi-level array with Month being the first level key, and Sunday date of the week being the second level
	 **/
	function weekArray($start, $stop = null, $isHtml = true, $monthFormat = 'Y-m-01', $weekFormat = 'Y-m-d') {
		if (empty($stop)) {
			$stop = $start;
		}
		
		$start = date('Y-m-01',strtotime($start));
		$stop = date('Y-m-t', strtotime($stop));
		$startStamp = Date::sundayStamp($start, 'before');
		$stopStamp = Date::sundayStamp($stop, 'after');
		
				
		$blank = $isHtml ? '&nbsp;' : '__';
		
		list($y,$m,$d) = explode('-',date('Y-m-d',$startStamp));
		$weekArray = array();
		$oldMonthKey = 0;
		$dupWeekKeys = array();
		for ($stamp = $startStamp; $stamp <= $stopStamp; $stamp = mktime(0,0,0,$m,++$d,$y)) {
			$monthKey = date($monthFormat,$stamp);
			$dayOfWeek = date('w', $stamp);
			if ($dayOfWeek == 0) {
				$weekKey = date($weekFormat,$stamp);
			}
			$newKey = empty($weekArray[$monthKey][$weekKey]);
			$dupKey = isset($dupWeekKeys[$weekKey]) || $newKey && isset($weekKeys[$weekKey]);
			
			if ($dupKey) {
				$dupWeekKeys[$weekKey] = 1;
				$weekKey .= '_2';
			}
			if ($newKey) {
				$weekKeys[$weekKey] = 1;
				$weekArray[$monthKey][$weekKey] = '';
			}
			
			//Buffers beginning of the month for blank days
			if ($monthKey != $oldMonthKey && $weekKey > 0) {
				for($i = 0; $i < $dayOfWeek; $i++) {
					if ($isHtml) {
						$entry = $this->Html->tag('font', $blank, array('class'=>'blank'));
					} else {
						$entry = $blank . ' ';
					}
					$weekArray[$monthKey][$weekKey] .= $entry;
				}
			}
			
			//Actual day
			$entry = date('d',$stamp);
			if ($isHtml) {
				$entry = $this->Html->tag('font', $entry);
			} else {
				$entry .= ' ';
			}
			$weekArray[$monthKey][$weekKey] .= $entry; 
			
			//Buffers end of month for blank days
			if (date('t',$stamp) == date('j',$stamp)) {
				for($i = $dayOfWeek + 1; $i <= 6; $i++) {
					if ($isHtml) {
						$entry = $this->Html->tag('font', $blank, array('class'=>'blank'));
					} else {
						$entry = $blank . ' ';
					}
					$weekArray[$monthKey][$weekKey] .= $entry;
				}
			}
			
			$oldMonthKey = $monthKey;
		}
		return $weekArray;	
	}
	
	function weekView($daysInfo) {
		$return = $this->Html->div('weekView');
		
		$return .= $this->Html->tag('ul');
		foreach($daysInfo as $dayInfo) {
		
		}
		$return .= '</ul>';
		$return .= '</div>' . "\n";
	}
	
	
	/**
	 * Returns a string output of the difference between two dates
	 *
	 * @param $dateStart str Beginning date
	 * @param $dateStop str Ending date
	 * @param $options optional
	 *
	 * @return String formatted version of the date difference
	 **/
	function dateDiff($dateStart, $dateStop, $options = null) {
		$stamp1 = Date::validStamp($dateStart);
		$stamp2 = Date::validStamp($dateStop);
		
		return $this->secondsString($stamp2 - $stamp1, $options);	
	}
	
	function dateInterval($date1, $date2) {
		$date1 = new DateTime($date1);
		$date2 = new DateTime($date2);
		
		$aIntervals = array(
			'year'   => 0,
			'month'  => 0,
			'week'   => 0,
			'day'	=> 0,
			'hour'   => 0,
			'minute' => 0,
			'second' => 0,
		);
		$negative = false;
		if ($date1 > $date2) {
			list($date1, $date2) = array($date2, $date1);
			$negative = true;
		}
		foreach($aIntervals as $sInterval => &$iInterval) {
			while($date1 <= $date2){ 
				$date1->modify('+1 ' . $sInterval);
				if ($date1 > $date2) {
					$date1->modify('-1 ' . $sInterval);
					break;
				} else {
					$iInterval++;
				}
			}
		}
		$aIntervals['negative'] = $negative;
		return $aIntervals;
	}
	/**
	 * Takes a number of seconds and returns a string broken down into individual units
	 * 
	 * @param $seconds int Number of seconds
	 * @param $maxUnit str Optional maximum time unit to divide by
	 *
	 * @return str Time broken down into units
	 **/
	function secondsString($seconds, $options = array()) {
		return Date::secondsString($seconds, $options);
	}
	
	/**
	 * Outputs two date strings as they relate to one another:
	 * April 1 - 4
	 * April 1 - June 2
	 * April 1, 2009 - June 2, 2010
	 *
	 **/
	function dateRange($date1, $date2, $options = array()) {
		$options = array_merge(array(
			'm' => 'M.',
			'd' => 'j',
			'y' => 'Y',
			't' => array(
				'h' => 'g',
				':' => ':',
				'm' => 'i',
				'a' => 'a',
			),
			'time' => false,
			
			'dateTag' => 'strong',
			'timeTag' => 'em',
			
			'html' => true,
		), $options);
		if ($options['html'] === false) {
			$options['dateTag'] = null;
			$options['timeTag'] = null;
		}		
		extract($options);
		
		$stamp1 = Date::validStamp($date1);
		$stamp2 = Date::validStamp($date2);
		
		if (empty($stamp2)) {
			return $this->niceShort($stamp1, $options);
		}
		
		if ($stamp1 > $stamp2) {
			list($stamp1, $stamp2) = array($stamp2, $stamp1);
		}
		$str = compact('m', 'd', 'y');
		list($str1, $str2) = array($str, $str);
		
		if (!empty($time)) {
			$str1 += compact('t');
			$str2 = compact('t') + $str2;
		}
		
		list($Y1, $M1, $D1, $T1, $H1, $I1, $A1) = explode('-', date('Y-m-d-H:i-H-i-a', $stamp1));
		list($Y2, $M2, $D2, $T2, $H2, $I2, $A2) = explode('-', date('Y-m-d-H:i-H-i-a', $stamp2));
		//Year Match
		if ($Y1 == $Y2) {
			$yearMatch = true;
			unset($str1['y']);
			//Month Match
			if ($M1 == $M2) {
				if (empty($time)) {
					unset($str2['m']);
				}
				//Day Match
				if ($D1 == $D2) {
					unset($str2['m']);
					unset($str2['d']);
					if (!empty($time)) {
						unset($str2['y']);
						$str1 += compact('y');
					}
					$dayMatch = true;
					//Time Match
					if ($T1 == $T2) {
						$timeMatch = true;
						unset($str2['t']);
					} else if ($A1 == $A2) {
						$amMatch = true;
						unset($str1['a']);
					}
				}
			}
		}
		$thisYear = date('Y');
		if (!empty($yearMatch)) {
			if ($thisYear == $Y1) {
				unset($str1['y']);
			}
			if ($thisYear == $Y2) {
				unset($str2['y']);
			}
		}
		
		$return = '';

		$time1 = Param::keyCheck($str1, 't', true, null);
		$date = date(implode(' ', $str1), $stamp1);
		if (!empty($dateTag)) {
			$date = $this->Html->tag($dateTag, $date);
		}
		$return .= $date;
		
		if (!empty($time1)) {
			if ($I1 == '00') {
				unset($time1['m']);
				unset($time1[':']);
			}
			$time = date(implode('', $time1), $stamp1);
			if (!empty($timeTag)) {
				$time = $this->Html->tag($timeTag, $time);
			}
			$return .= ' ' . $time;
		}
		
		if (!empty($str2)) {
			if (count($str2) > 1 || empty($str2['y'])) {
				$return .= '-';
			} else {
				$return .= ' ';
			}
			if ($time2 = Param::keyCheck($str2, 't', true, null)) {
				if ($I2 == '00') {
					unset($time2['m']);
					unset($time2[':']);
				}
				$time = date(implode('', $time2), $stamp2);
				if (!empty($timeTag)) {
					$time = $this->Html->tag($timeTag, $time);
				}
				$return .= $time . ' ';
			}
			$date = date(implode(' ', $str2), $stamp2);
			if (!empty($dateTag)) {
				$date = $this->Html->tag($dateTag, $date);
			}
			$return .= $date;
		}
		
		if (!empty($nbsp)) {
			$return = str_replace(' ', '&nbsp;', $return);
		}

		return $return;
	}
	
	/**
	 * Outputs two date strings as they relate to one another:
	 * April 1 - 4
	 * April 1 - June 2
	 * April 1, 2009 - June 2, 2010
	 *
	 **/
	function dateRangeOld($date1, $date2, $options = null) {
		//Checks whether time should be displayed also
		$showTime = !empty($options['time']);
		//Will surround the date and times in HTML tags
		if (!empty($options) && (!isset($options['html']) || $options['html'] !== false)) {
			$dateTag = !empty($options['dateTag']) ? $options['dateTag'] : 'strong';
			$timeTag = !empty($options['timeTag']) ? $options['timeTag'] : 'em';
		}
		
		if (($stamp1 = Date::validStamp($date1)) === false || ($stamp2 = Date::validStamp($date2)) === false) {
			return '';
		}
		if($stamp2 < $stamp1) {
			list($stamp1, $stamp2) = array($stamp2, $stamp1);
		}		
		$m = 'F';
		$d = 'j';
		$y = ', Y';
		$sep = '-'; //$showTime ? '-' : ' - ';
		$dateStr1 = "$m $d";
		$dateStr2 = '';
		
		$thisYear = date('Y');
		
		$yearMatch = date('Y',$stamp1) == date('Y',$stamp2);
		$dayMatch = date('Ymd',$stamp1) == date('Ymd',$stamp2);
		
		if ((!$yearMatch)) {// && date('Y',$stamp1) != date('Y'))) {
			$dateStr1 .= $y;
		}
	
		if($yearMatch) {
			if (date('m',$stamp1) != date('m',$stamp2)) {
				$dateStr2 .= "$m $d";	//Same year, different month
			} elseif (date('d',$stamp1) != date('d',$stamp2)) {
				//Same year and month, different day
				if ($showTime) {
					$dateStr2 .= "$m $d";
					if ( date('Y',$stamp2) != $thisYear) {
						$dateStr2 .= "$y";
					}
				} else {
					$dateStr2 .= $d;
				}
			} else {
				$dateStr2 = '';	//Same year, month, and day
				/*
				if ($showTime && date('Y',$stamp2) != $thisYear) {
					$dateStr1 .= $y;
				}
				*/
			}
		} else {
			$dateStr2 = "$m $d";
			if (date('Y',$stamp2) != $thisYear) {
				$dateStr2 .= $y;
			}
			//$dateStr1 .= $y;
		}
		/*
		if ((!$yearMatch || date('Y',$stamp2) != $thisYear)) {
			$dateStr2 .= $y;
		}
		*/

		$dateStr1 = trim($dateStr1);
		$dateStr2 = trim($dateStr2);
		
	
		$return1 = '';
		$return2 = '';
		$return = '';
		
		if($dateStr1 != '') {
			$date1 = date($dateStr1, $stamp1);
			if (!empty($dateTag)) {
				$date1 = $this->Html->tag($dateTag, $date1);
			}
			$return1 .= $date1;
			
			if ($showTime) {
				$timeStr1 = date('i',$stamp1) > 0 ? 'g:i' : 'g';
				//Only adds AM/PM if it happens on different days, or if it's not going to display time2
				if (!$dayMatch || $stamp1 == $stamp2) {
					$timeStr1 .= 'a';
				}
				$time1 = date($timeStr1, $stamp1);
				if (!empty($timeTag)) {
					$time1 = $this->Html->tag($timeTag, $time1);
				}
				$return1 .= ' ' . $time1;
			}
		}
		if ($dateStr2 == 'Y' && !$showTime) {
			$sep = ' ';
		}
		if ($dayMatch && !$showTime) {
			$sep = '';
		}

		if ($showTime && $stamp1 != $stamp2) {
			$timeStr2 = date(date('i',$stamp2) > 0 ? 'g:ia' : 'ga', $stamp2);
			if (!empty($timeTag)) {
				$timeStr2 = $this->Html->tag($timeTag, $timeStr2);
			}
			$return2 = $timeStr2;			
		}
		
		if($dateStr2 != '') {
			$date2 = date($dateStr2,$stamp2);
			if (!empty($dateTag)) {
				$date2 = $this->Html->tag($dateTag, $date2);
			}
			if ($return2 != '') {
				$return2 .= ' ';
			}
			$return2 .= $date2;
		}
		
		//Combines the two
		$return = $return1;
		if($return1 != '' && $return2 != '') {
			$return .= $sep;
		}
		$return .= $return2;
		
		if (!empty($options['nbsp'])) {
			$return = str_replace(' ', '&nbsp;', $return);
		}
		return $return;
	}
	
	function timeRange($date1, $date2, $options = array()) {
		if (($stamp1 = Date::validStamp($date1)) === false || ($stamp2 = Date::validStamp($date2)) === false) {
			return '';
		}
		if (date('gia',$stamp1) == date('gia',$stamp2)) {
			return $this->timeFormat($stamp1);
		} else if (date('mdy',$stamp1) != date('mdy',$stamp2)) {
			return $this->dateTimeRange($date1, $date2, $options);
		} else {
			return $this->timeFormat($stamp1, $stamp2) . '-' . $this->timeFormat($stamp2);
		}
	}
	
	function dateTimeRange($date1, $date2, $options = array()) {
		if (($stamp1 = Date::validStamp($date1)) === false || ($stamp2 = Date::validStamp($date2)) === false) {
			return '';
		}
		$options = array_merge($options, array('time' => true));
		return $this->dateRange($date1, $date2, $options);
	}
	
	/**
	 * Outputs a timestamp in proper date format
	 **/
	function dateFormat($stamp, $options = array()) {
		$options = array_merge(array(
			'options' => 'full',
		), $options);
		
		$empty = Param::keyCheck($options, 'empty', true, '');
		if (($stamp = Date::validStamp($stamp)) === false) {
			return $empty;
		}

		$preDate = '';
		if (!empty($options['dateStr'])) {
			$dateStr = $options['dateStr'];
		} else {
			$dateStr = 'F j';
			$yStr = ' Y';
			if ($options['format'] == 'small') {
				$dateStr = 'M jS';
			} else if ($options['format'] == 'tiny') {
				$dateStr = 'n/j';
				$yStr = '/Y';
			}
			if (Param::keyValCheck($options, 'year') || date('Y',$stamp) != date('Y')) {
				$dateStr .= $yStr;
			}
		}
		
		if (!empty($options['today'])) {
			if (date('Ymd', $stamp) == date('Ymd')) {
				$preDate = 'Today';
				$dateStr = '';
			} else if (date('Ymd', $stamp) == date('Ymd', strtotime('yesterday'))) {
				$preDate = 'Yesterday';
				$dateStr = '';
			} else if (date('Ymd', $stamp) == date('Ymd', strtotime('tomorrow'))) {
				$preDate = 'Tomorrow';
				$dateStr = '';
			}
		}
		
		if (Param::keyValCheck($options, 'time')) {
			if (Param::keyCheck($options, 'minuteCollapse') && date('i', $stamp) == '00') {
				$dateStr .= ' ga';
			} else {
				$dateStr .= ' g:ia';
			}
		}
		
		$return = $preDate . date($dateStr, $stamp);
		
		if (!empty($options['url'])) {
			$return = $this->Html->link($return, $options['url']);
		}
		
		if (($div = Param::keyCheck($options, 'div'))) {
			$tag = 'div';
			$class = $div;
		} else {
			$class = Param::keyCheck($options, 'class');
		}
		if (empty($tag) && !empty($class)) {
			if (!($tag = Param::keyCheck($options, 'tag'))) {
				$tag = 'div';
			}
		}
		if (!empty($tag)) {
			$return = $this->Html->tag($tag, $return, array('class' => $class));
		}
		return $return;
	}
	
	function niceShort($stamp, $options = array()) {
		$options = array_merge(array(
			'format' => 'small',
			'today' => true,
			'time' => true,
			'minuteCollapse' => true,
		), $options);
		return $this->dateFormat($stamp, $options);
	}
	
	function deadline($stamp, $complete = false, $options = array()) {
		$class = 'deadline ' . $this->deadlineClass($stamp, $complete, $options);
		return $this->niceShort($stamp, compact('class') + $options);
	}
	
	function deadlineClass($stamp, $complete = false, $options = array()) {
		$options = array_merge(array(
			'upcoming' => '+2 weeks',
			'far' => '+8 months',
		), $options);
		
		$upcoming = strtotime($options['upcoming']);
		$far = strtotime($options['far']);
		
		$now = time();
		
		$class = '';
		if (($stamp = Date::validStamp($stamp)) !== false) {
			if ($complete) {
				$class .= ' deadline-complete';
			} else if ($stamp < $now) {
				$class .= ' deadline-past';
			} else if ($stamp < $upcoming) {
				$class .= ' deadline-upcoming';
			} else if ($stamp >= $far) {
				$class .= ' deadline-far';
			}
		}
		return trim($class);
	}
		
	/**
	 * Outputs a timestamp in proper time format
	 **/
	function timeFormat($stamp, $cmpStamp = null) {
		if (($stamp = Date::validStamp($stamp)) === false) {
			return '';
		}
		if (!empty($cmpStamp)) {
			$cmpStamp = Date::validStamp($cmpStamp);
		}
		$timeStr = date('i',$stamp) > 0 ? 'g:i' : 'g';
		if (empty($cmpStamp) || date('a', $stamp) != date('a', $cmpStamp)) {
			$timeStr .= 'a';
		}
		return date($timeStr, $stamp);
	}
	
	/**
	 * Rounds the given date string to the start or end of a pay period
	 *
	 * @param str $dateStr The given date string. Defaults to today
	 * @param str $pos The position, either "start" or "end" of the pay period
	 *
	 * @return timestamp of the pay period
	 **/
	function payPeriodStamp($dateStr = null, $pos = 'start') {
		if (empty($dateStr)) {
			$dateStr = 'now';
		}
		$stamp = Date::validStamp($dateStr);
		list($y,$m,$d) = explode('-',date('Y-m-d', $stamp));
		$t = date('t', $stamp);
		if ($pos == 'start') {
			$d = ($d > 15) ? 16 : 1;
		} else {
			$d = ($d > 15) ? $t : 15;
		}
		return strtotime("$y-$m-$d");
	}
	
	function payPeriodRange($start = null, $end = null, $options = array()) {
		if (empty($start) && empty($end)) {
			$start = $this->payPeriodStamp();
		}
		if (empty($start)) {
			$start = $this->payPeriodStamp($end);
		} else if (empty($end)) {
			$end = $this->payPeriodStamp($start, 'end');
		}
		return $this->dateRange($start, $end, $options);
	}
	
	/**
	 * Returns the timestamp of the relative Sunday to the given date
	 *
	 * @param $dateStr str The given date
	 * @param $position str Either 'before' or 'after', depening on which Sunday
	 * @param $skipThis bool If true, it will not return current value if it is a Sunday
	 * @return timestamp
	 **/
	function sundayStamp($dateStr = null, $position = 'after', $skipThis = false) {
		if (empty($dateStr)) {
			$dateStr = 'now';
		}
		
		$stamp = strtotime($dateStr);
		$dayNum = date('w',$stamp);
		$sundayMatch = false;
		
		//Current day matches as Sunday
		if ($dayNum == 0) {
			$sundayMatch = true;
			if (!$skipThis) {
				return $stamp;
			}
		}
		
		if ($position == 'after') {
			//End Sunday
			return strtotime($dateStr.' +'.(7 - date('w',$stamp)).' days');
		} else {
			//Beginning Sunday
			if ($sundayMatch) {
				return Date::sundayStamp($dateStr.' -1 day','before');
			} else {
				return strtotime($dateStr.' -'.date('w',$stamp).' days');
			}
		}
	}
	
	
	function _firstWeek($date) {
		//Finds first day of the month
		$date = date('Y-m-01',strtotime($date));
		//Finds Sunday
		$stamp = Date::sundayStamp($date,'before');
		//Adds the _2 suffix to let the page know to go to the second week version of the selected week
		return date('Y-m-d',$stamp).'_2';
	}
				
			
}
?>