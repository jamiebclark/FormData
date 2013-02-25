<?php
class Date {
	var $fiscalYearMonth = 6;
	
	function &getInstance() {
		static $instance = array();

		if (!$instance) {
			$instance[0] =& new Date();
		}
		return $instance[0];
	}

	function validStamp($str) {
		//Already a stamp
		if(ctype_digit($str)) {
			return $str;
		}
		if (round($str) < 0) {
			return false;
		}
		$stamp = strtotime($str);
		if($stamp === false || date('Y',$stamp) <= 0 || $stamp <= 0) {
			return false;
		} 
		
		return $stamp;
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
				$self =& Date::getInstance();
				return $self->sundayStamp($dateStr.' -1 day','before');
			} else {
				return strtotime($dateStr.' -'.date('w',$stamp).' days');
			}
		}
	}
	
	function payPeriodEnd($dateStr = null) {
		$self =& Date::getInstance();
		if ($stamp = $self->validStamp(empty($dateStr) ? 'now' : $dateStr)) {
			list($d, $t) = explode('-', date('d-t', $stamp));
			$d = $d > 15 ? $t : 15;
			return date('Y-m-'.$d, $stamp);
		} else {
			return false;
		}
	}
	
	function payPeriodStart($dateStr = null) {
		$self =& Date::getInstance();
		if ($stamp = $self->validStamp(empty($dateStr) ? 'now' : $dateStr)) {
			$d = date('j', $stamp);
			return date('Y-m-' . ($d > 15 ? '15' : '01'), $stamp);
		} else {
			return false;
		}
	}
	
	function fiscalYear($dateStr = null) {
		$self =& Date::getInstance();
		if ($stamp = $self->validStamp(empty($dateStr) ? 'now' : $dateStr)) {
			list($y, $m) = explode('-', date('Y-m', $stamp));
			return ($m >= $self->fiscalYearMonth) ? $y+1 : $y;
		} else {
			return false;
		}
	
	}
	//Builds a properly formatted array of date variables
	//Primarily used for passing into CakePHP link arrays
	function dateArray($yearOrDateString = null, $month = null, $day = null) {
		if (empty($yearOrDateString)) {
			$yearOrDateString = date('Y-m-d');
		}
		if (!preg_match('/^[0-9]{4}$/', $yearOrDateString)) {
			$dateString = $yearOrDateString;
			$stamp = strtotime($dateString);
		} else {
			$year = $yearOrDateString;
			if (empty($day)) {
				$day = 1;
			}
			$stamp = mktime(0,0,0,$month,$day,$year);
		}
		list($year, $month, $day) = explode('-', date('Y-m-d', $stamp));
		
		$dateArray = array('year' => $year);
		if (!empty($month)) {
			$dateArray['month'] = str_pad($month, 2, '0', STR_PAD_LEFT);
		}
		if (!empty($day)) {
			$dateArray['day'] = str_pad($day, 2, '0', STR_PAD_LEFT);
		}
		return $dateArray;
	}
	
	function calendarImage($dateStr = null, $options = array()) {
		App::import('Lib', 'Image');
		$options = array_merge(array(
			'width' => 200,
			'height' => 200,
			'font' => WWW_ROOT . 'files' . DS . 'fonts' . DS . 'MavenPro-Bold.ttf',
			'backgroundColor' => '#FFFFFF',
			'borderColor' => '#999999',
			'fontColor' => '#999999',
			'topBackgroundColor' => '#F26532',
			'padding' => '5%',
			'borderWidth' => 2,
		), (array) $options);
		
		$output = false; 
		extract($options);
		$self =& Date::getInstance();
		if (empty($dateStr)) {
			$dateStr = 'now';
		}
		$stamp = $self->validStamp($dateStr);
		$topText = strtoupper(date('M', $stamp));
		$bottomText = date('j', $stamp);
		
		$img = imagecreatetruecolor($width, $height);
		
		$innerWidth = $width - $borderWidth;
		$innerHeight = $height - $borderWidth;
		
		if (strstr('%', $padding)) {
			$padding = $height * (str_replace('%', '', $padding) / 100);
		}
		
		$backgroundColor = Image::colorAllocateStr($img, $backgroundColor);
		$topBackgroundColor = Image::colorAllocateStr($img, $topBackgroundColor);
		$borderColor = Image::colorAllocateStr($img, $borderColor);
		$fontColor = Image::colorAllocateStr($img, $fontColor);
		
		$sepHeight = round($height / 3);
		
		//Background
		imagefilledrectangle($img, $borderWidth, $borderWidth, $innerWidth, $innerHeight, $backgroundColor);
		
		//Top bar
		$fontSize = $sepHeight - $padding * 2;
		imagefilledrectangle($img, $borderWidth, $borderWidth, $innerWidth, $sepHeight, $topBackgroundColor);
		$bounds = Image::calculateTextBox($topText, $font, $fontSize);
		
		$x = -$bounds['left'] + $width / 2 - $bounds['width'] / 2;
		$y = $bounds['top'] + $borderWidth + ($sepHeight - $bounds['height']) / 2;

		if ($output) {
			debug('Top Bar');
			debug($bounds);
			debug(compact('width', 'height', 'x', 'y'));
		}
		
		imagettftext($img, $fontSize, 0, $x, $y, $backgroundColor, $font, $topText);
		
		//Day
		$fontSize = $height - $sepHeight - $padding * 2;
		$bounds = Image::calculateTextBox($bottomText, $font, $fontSize);
		$x = -$bounds['left'] + ($width - $bounds['width']) / 2;
		$y = $bounds['top'] + $height - $borderWidth - ($height - $sepHeight) / 2 - $bounds['height'] / 2;
		imagettftext($img, $fontSize, 0, $x, $y, $fontColor, $font, $bottomText);
		if ($output) {
			debug('Bottom Bar');
			debug($bounds);
			debug(compact('width', 'height', 'x', 'y'));
		}
		
		//Border
		if ($borderWidth > 0) {
			$width -= 1;
			$height -= 1;

			for ($i = 0; $i < $borderWidth; $i++) {
				if ($output) {
					debug("$img, $i, $i, $width - $i, $height - $i,");
				}
				imagerectangle($img, $i, $i, $width - $i, $height - $i, $borderColor);
			}
		}
		
		imagejpeg($img, $dst, 100);	
	}
	
	/**
	 * Calculate differences between two dates with precise semantics. Based on PHPs DateTime::diff()
	 * implementation by Derick Rethans. Ported to PHP by Emil H, 2011-05-02. No rights reserved.
	 * 
	 * See here for original code:
	 * http://svn.php.net/viewvc/php/php-src/trunk/ext/date/lib/tm2unixtime.c?revision=302890&view=markup
	 * http://svn.php.net/viewvc/php/php-src/trunk/ext/date/lib/interval.c?revision=298973&view=markup
	 */

	function dateRangeLimit($start, $end, $adj, $a, $b, $result) {
		if ($result[$a] < $start) {
			$result[$b] -= intval(($start - $result[$a] - 1) / $adj) + 1;
			$result[$a] += $adj * intval(($start - $result[$a] - 1) / $adj + 1);
		}

		if ($result[$a] >= $end) {
			$result[$b] += intval($result[$a] / $adj);
			$result[$a] -= $adj * intval($result[$a] / $adj);
		}

		return $result;
	}

	function dateRangeLimitDays(&$base, &$result)	{
		$self =& Date::getInstance();

		$days_in_month_leap = array(31, 31, 29, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
		$days_in_month = array(31, 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);

		$base = $self->dateRangeLimit(1, 13, 12, "m", "y", $base);

		$year = $base["y"];
		$month = $base["m"];

		if (!$result["invert"]) {
			while ($result["d"] < 0) {
				$month--;
				if ($month < 1) {
					$month += 12;
					$year--;
				}

				$leapyear = $year % 400 == 0 || ($year % 100 != 0 && $year % 4 == 0);
				$days = $leapyear ? $days_in_month_leap[$month] : $days_in_month[$month];

				$result["d"] += $days;
				$result["m"]--;
			}
		} else {
			while ($result["d"] < 0) {
				$leapyear = $year % 400 == 0 || ($year % 100 != 0 && $year % 4 == 0);
				$days = $leapyear ? $days_in_month_leap[$month] : $days_in_month[$month];

				$result["d"] += $days;
				$result["m"]--;

				$month++;
				if ($month > 12) {
					$month -= 12;
					$year++;
				}
			}
		}
		
		return $result;
	}

	function dateNormalize(&$base, &$result) {
		$self =& Date::getInstance();
		$result = $self->dateRangeLimit(0, 60, 60, "s", "i", $result);
		$result = $self->dateRangeLimit(0, 60, 60, "i", "h", $result);
		$result = $self->dateRangeLimit(0, 24, 24, "h", "d", $result);
		$result = $self->dateRangeLimit(0, 12, 12, "m", "y", $result);

		$result = $self->dateRangeLimitDays($base, $result);

		$result = $self->dateRangeLimit(0, 12, 12, "m", "y", $result);

		return $result;
	}

	/**
	 * Accepts two unix timestamps.
	 */
	function dateDiff($one, $two) {
		$self =& Date::getInstance();
		$invert = false;
		if ($one > $two) {
			list($one, $two) = array($two, $one);
			$invert = true;
		}

		$key = array("y", "m", "d", "h", "i", "s");
		$a = array_combine($key, array_map("intval", explode(" ", date("Y m d H i s", $one))));
		$b = array_combine($key, array_map("intval", explode(" ", date("Y m d H i s", $two))));

		$result = array();
		$result["y"] = $b["y"] - $a["y"];
		$result["m"] = $b["m"] - $a["m"];
		$result["d"] = $b["d"] - $a["d"];
		$result["h"] = $b["h"] - $a["h"];
		$result["i"] = $b["i"] - $a["i"];
		$result["s"] = $b["s"] - $a["s"];
		$result["invert"] = $invert ? 1 : 0;
		$result["days"] = intval(abs(($one - $two)/86400));

		if ($invert) {
			$self->dateNormalize($a, $result);
		} else {
			$self->dateNormalize($b, $result);
		}

		return $result;
	}
	
	function age($dateStr) {
		$self =& Date::getInstance();
		$stamp = $self->validStamp($dateStr);
		$diff = $self->dateDiff($stamp, time());
		return $diff['y'];
	}
	
	
	function secondsString($seconds, $options = array()) {
		$units = array(
			DAY => 'day',
			HOUR => 'hour',
			MINUTE => 'minute',
			SECOND => 'second'
		);
		
		if (!is_array($options)) {
			$maxUnit = $options;
		} else if (!empty($options['maxUnit'])) {
			$maxUnit = $options['maxUnit'];
		}
		$minUnit = isset($options['minUnit']) ? $options['minUnit'] : false;
		if ($minUnit) {
			if (($key = array_search($minUnit, $units)) !== false) {
				$seconds = round($seconds / $key) * $key;	
			}
		}
		
		$maxMet = false;
		$minMet = false;
		$return = array();
		
		if (isset($options['short'])) {
			$short = $options['short'];
		} else if (($key = array_search('short', $options)) !== false) {
			$short = $options[$key];
		} else {
			$short = false;
		}
		foreach($units as $div => $unit) {
			if (!empty($maxUnit) && !$maxMet) {
				if ($unit == $maxUnit) {
					$maxMet = true;
				} else {
					continue;
				}
			}
			
			if ($unit == $minUnit) {
				$minMet = true;
				$time = round($seconds / $div);
			} else {
				$time = floor($seconds / $div);
			}
			if($time > 0) {
				if ($short) {
					$unit = substr($unit,0,1);
				} else {
					$unit = '&nbsp;' . $unit;
					if ($time != 1) {
						$unit .= 's';
					}
				}
				$return[] = number_format($time) . $unit;
				$seconds -= $time * $div;
			}
			
			if ($minMet) {
				break;
			}
		}
		return implode(' ', $return);
	}
}
?>