var lastAutoComplete;
var skipFocus = false;
var forceAutoComplete = false;
var autoCompleteVars = new Array();


var dropdownOver = false;
var dropdownInputFocus = false;

function autoCompleteVar(key, val) {
	for (var i = 0; i < autoCompleteVars.length; i++) {
		if (autoCompleteVars[i][0] == key) {
			autoCompleteVars[i][1] = val;
			return true;
		}
	}
	autoCompleteVars.push(new Array(key, val));
	return true;
}

jQuery.fn.log = function (msg) {
	console.log("%s: %o", msg, this);
	return this;
}

jQuery.fn.selectDropdownInit = function() {
	$(this).find('li').mouseover(function() {
		$(this).addClass('hover');
	}).mouseout(function() {
		$(this).removeClass('hover');
	}).click(function() {
		$(this).selectDropdownClick();
	}).find('a').click(function() {
		$(this).closest('li').selectDropdownClick();
		//return false;
	});
	return $(this);
};

jQuery.fn.selectDropdownClick = function() {
	skipFocus = true;
	forceAutoComplete = true;
	$(this).trigger('autoCompleteClick', $(this).html()).closest('.selectDropdown').hide();
	//$(this).closest('.inputAutoComplete').find('input').attr('value', '').focus();
	
	return $(this);
}
jQuery.fn.autoCompleteEntry = function() {
	if (forceAutoComplete || lastAutoComplete != $(this).attr('value')) {
		var t = (new Date()).getTime();
		forceAutoComplete = false;
		lastAutoComplete = $(this).attr('value');
		var dropdown = $(this).parent().find('.selectDropdown');
		var url = dropdown.attr('url') + '?';
		url += 'search=' + $(this).attr('value');
		if (autoCompleteVars) {
			for (var i = 0; i < autoCompleteVars.length; i++) {
				url += '&';
				url += autoCompleteVars[i][0];
				url += '=';
				url += autoCompleteVars[i][1];
				/*if (i < autoCompleteVars.length - 1) {
					
				}*/
			}
		}
		
		$('#javascriptDebug').append($('<div></div>').html(t));
		
		dropdown.ajaxLoad(url, {
			success : function (msg) {
				dropdown.selectDropdownInit();
				dropdown.closest('.inputAutoComplete').trigger('autoCompleteUpdate');
			}
		});
	}
	return $(this);
};

jQuery.fn.hideDropdown = function() {
	if (!dropdownOver && !dropdownInputFocus) {
		$(this).parent().find('.selectDropdown').slideUp();
	}
	return $(this);
};

jQuery.fn.multiSelectInit = function() {
	var $parent = $(this);
	var $content = $parent.contents();
	var $container = $('<div></div>').attr('class', 'select-item').append($content);
	$(this).html($('<div></div>').attr('class', 'select-list').append($container));
	
	var $addLink = $('<div><a href="#">Add</a></div>').find('a').click(function(e) {
		var $selectItem = $parent.find('.select-item').first();
		$parent.find('.select-list').append($selectItem);
		e.preventDefault();
	});
};

$.fn.hideDisableChildren = function() {
	if ($(this).is(':visible')) {
		$(this).slideUp();
	}
	$(this).find(':input').each(function() {
		console.log('Disable: ' + $(this).attr('id'));
		this.disabled = 'disabled';
	});
	return $(this);
};

$.fn.showEnableChildren = function(focusFirst) {
	if ($(this).is(':hidden')) {
		$(this).slideDown();
	}
	var $openInputs = $(this).find(':input').each(function() {
		this.disabled = false;
	});
	
	if (focusFirst) {
		$openInputs.first().select();
	}
	return $(this);
};

$.fn.clickInputChoice = function(clicked) {
	var $val = $(this).attr('value');
	var checkVal;
	$(this).closest('div[class="input-choices"]').find('div.input-choice').each(function() {
		checkVal = $(this).prev().find('input').attr('value');
		if (checkVal == $val) {
			$(this).showEnableChildren(clicked);
		} else {
			$(this).hideDisableChildren();
		}
	});
	return $(this);
};

var dropdownDelay = 500;
var dropdownTimeout;
var dropdownInput;

function inputChoicesInit() {
	$('.input-choices .input-choice-input input').click(function() {
		$(this).clickInputChoice(true);
	}).filter(':checked').first().each(function() {
		$(this).clickInputChoice();
	});
}

$.fn.inputList = function() {
	if ($(this).data('input-list-init')) {
		return $(this);
	}
	
	var $container = $(this),
		$list = $container.find('.input-list-item'),
		$addLink = $('<a></a>', {
			'href' : '#',
			'html' : 'Add',
			'class' : 'add-input-list-item add align-left'
		});
	
	$list.bind('cloned', function (e, $cloned) {
		addRemoveBox($cloned);
		$list = $container.find('.input-list-item');
	});

	function addRemoveBox($listItem) {
		var $id = $listItem.find('input[name*="id]"]').first();
		if (!$id.length) {
			return false;
		}
		var removeClass = 'input-list-remove',
			$checkbox = $listItem.find('.' + removeClass + ' input[type=checkbox]'),
			$content = $listItem.children(':not(.'+removeClass+')');
			
		if (!$checkbox.length) {
			var removeName = $id.attr('name').replace(/\[id\]/,'[remove]'),
				removeBoxId = removeName.replace(/(\[([^\]]+)\])/g, '_$2'),
				$checkbox = $('<input/>', {
					'name' : removeName,
					'type' : 'checkbox',
					'value' : 1,
					'id' : removeBoxId
					
				})
				.appendTo($listItem)
				.wrap($('<div></div>', {'class' : removeClass}))
				.after($('<label></label>', {'for' : removeBoxId, 'html' : 'Remove'}));
		}
		$checkbox.change(function() {
			if ($(this).attr('checked')) {
				$(this).parent().addClass('active');
				$listItem.addClass('remove').find(':input').filter(function() {
					var name = $(this).attr('name');
					return name != $checkbox.attr('name') && !(name.match(/\[id\]/));
				}).attr('disabled',true);
				$content.slideUp();
			} else {
				$(this).parent().removeClass('active');
				$listItem.removeClass('remove').find(':input').attr('disabled',false);
				$content.slideDown();
			}
		}).attr('checked', false).change();
	}
	
	$addLink.click(function(e) {
			e.preventDefault();
			$list.cloneNumbered().trigger('inputListAdd');
		})
		.appendTo($(this))
		.wrap('<div class="layout-buttons"></div>');
			
	$list.filter(':visible').each(function() {
		addRemoveBox($(this));
		return $(this);
	});
	
	$(this).data('input-list-init', true);
	return $(this);
};

$.fn.renumberInput = function(newIdKey) {
	if (!$(this).attr('name')) {
		return $(this);
	}
	var reg = /\[(\d+)\]/,
		name = $(this).attr('name'),
		id = $(this).attr('id'),
		idKeyMatch = name.match(reg),
		idKeyP = idKeyMatch[0],
		idKey = idKeyMatch[1];
	$(this).attr('name', name.replace(idKeyP, "["+newIdKey+"]"));
	if (id) {
		var oldId = id,
			$labels = $('label').filter(function() { return $(this).attr('for') == oldId;}),
			newId = id.replace(idKey, newIdKey);
		$(this).attr('id', newId);
		$(this).next('label[for="'+oldId+'"]').attr('for',newId);
		$(this).prev('label[for="'+oldId+'"]').attr('for',newId);
	}
	return $(this);
};

$.fn.cloneNumbered = function() {
	if ($(this).data('cloning')) {
		return $(this);
	}
	$(this).data('cloning', true);
	var $ids = $(this).find('input[name*="[id]"]:enabled'),
		$id = $ids.last(),
		name = $id.attr('name');
		
	if ($id.length) {
		var $entry = $(this).last(),
			$cloned = $entry.clone().insertAfter($entry),
			newIdKey = $ids.length;
		$cloned.find(':text,textarea').val('').trigger('reset');
		$cloned
			.slideDown()
			.data('added', true)
			.find(':input').each(function() {
				return $(this).renumberInput(newIdKey).removeAttr('disabled').removeAttr('checked');
			});
		$cloned
			.find(':input:visible')
			.first()
			.focus();
		$(this).trigger('cloned', [$cloned]);
		formLayoutInit();
	}
	$(this).data('cloning', false);
	return $(this);
};
 (function ($) {
	var toggleCount = 1;
	$.fn.formLayoutToggle = function() {
		var $toggle = $(this),
			$input = $toggle.find('.toggle-input input[type*=checkbox]').first(),
			$content = $toggle.find('> .toggle-content'),
			$offContent = $toggle.find('> .toggle-off-content');
			tc = toggleCount++;
		
		$toggle.addClass('toggle' + tc);
		
		function toggleOn() {
			console.log('Toggling ' + tc + ': ' + $toggle.attr('class'));
			
			$content.showEnableChildren();
			$offContent.hideDisableChildren();
		}
		function toggleOff() {
			$content.hideDisableChildren();
			$offContent.showEnableChildren();
		}
		function toggleCheck() {
			if ($input.is(':checked')) {
				toggleOn();
			} else {
				toggleOff();
			}
		}
			
		$input.change(function() {
			toggleCheck();
		});
		
		toggleCheck();
		
		return $(this);
	};
})(jQuery);

function formLayoutToggleInit() {
	$('.form-layout-toggle').each(function() {
		$(this).formLayoutToggle();
	});
}


//Input Auto Complete
(function($) {
	$.fn.dropdown = function(options) {
		if ($(this).data('dropdown-init')) {
			return $(this);
		}
		
		var defaults = {
			'tag': 'ul',
			'itemTag': 'li',
			'emptyMessage': false,
			'emptyResult': false,
			'defaultTitle': 'Default'
		};
		var options = $.extend(defaults, options);
		
		if (!$(this).closest('.dropdown-holder').length) {
			$(this).wrap($('<div class="dropdown-holder"></div>'));
		}
		var $parent = $(this),
			$dropdown = $('<' + options.tag + '></' + options.tag + '>'),
			$wrap = $parent.closest('.dropdown-holder'),
			offset = $parent.offset(),
			dropOffset = $wrap.offset(),
			defaultVals = new Array(),
			lastTimestamp = 0,
			lastUrl = false;

		function addDropdownOption(value, label) {
			var $option = $('<' + options.itemTag + '></' + options.itemTag + '>');
			if (!label && !value) {
				return false;
			} else if (label) {
				$option.append($('<a></a>', {
						'html' : label,
						'href' : '#'
					}).click(function(e) {
						e.preventDefault();
						$dropdown.trigger('dropdownClicked', [value, label]);
					})
				);
			} else {
				$option.append(value);
			}
			$option.appendTo($dropdown);
		}
		
		function addEmptyMessage() {
			if (options.emptyMessage) {
				addDropdownOption('<em>' + options.emptyMessage + '</em>');
			}
		}
		
		$dropdown
			.addClass('dropdown')
			.appendTo($('body'))
			.hide()
			.bind({
				'show': function() {
					offset = $parent.offset();
					$(this).css({
						'top' : offset.top + $parent.outerHeight(),
						'left' : offset.left,// - $parent.outerWidth(),
						'width' : $parent.outerWidth()
					}).trigger('checkEmpty').show();
				},
				'set': function(e, vals, skipEmpty) {
					if (!skipEmpty) {
						$(this).trigger('empty');
					}
					for (var v = 0; v < vals.length; v++) {
						addDropdownOption(vals[v][0], vals[v][1]);
					}
				},
				'empty': function() {
					$(this).html('');
				},
				'checkEmpty': function() {
					if ($(this).html() == '') {
						$(this).trigger('clear');
					}
				},
				'setDefault': function(e, vals) {
					if (vals) {
						defaultVals = vals;
					}
					$(this).trigger('empty');
					
					if (options.emptyResult && $(this).val() != '') {
						addDropdownOption($('<em></em>').html(options.emptyResult));
					}					
					if (options.defaultTitle) {
						addDropdownOption($('<strong></strong>').html(options.defaultTitle));
					}
					$(this).trigger('set', [defaultVals, true]);
					addEmptyMessage();
				},
				'clear': function(e) {
					if (defaultVals && defaultVals.length) {
						$(this).trigger('setDefault');
					} else {
						addEmptyMessage();
					}
				},
				'loading': function(e, loadOptions) {
					var loadOptions = $.extend({
						dataType: 'json',
						url: false
					}, loadOptions);
					
					$(this).trigger('show').html('Loading...').addClass('loading');
					
					if (loadOptions.url.indexOf('json') > 0) {
						loadOptions.dataType = 'json';
					} else {
						loadOptions.dataType = 'html';
					}
					
					if (loadOptions.url && loadOptions.url != lastUrl) {
						lastUrl = loadOptions.url;
						var request = $.ajax(loadOptions)
							.error(function(data) {
								console.log('Dropdown call failed');
							})
							.success(function(data, text, httpRequest) {
								var timestamp = Math.round(new Date().getTime() / 1000);
								if (timestamp < lastTimestamp) {
									$(this).log('Skipping return on result: ' + $(this).val());
									return false;
								}
								lastTimestamp = timestamp;
								if (loadOptions.dataType == 'json') {
									$dropdown.trigger('empty');
									$.each(data, function(key, val) {
										addDropdownOption(val.value, val.label);
									});
								} else {
									$dropdown.html(data);
									$dropdown.find('a').click(function(e) {
										$dropdown.trigger('dropdownClicked', [$(this).attr('href'), $(this).html()]);
									});
								}
								$dropdown.trigger('checkEmpty').trigger('loaded');
							});
					}
				},
				'loaded': function() {
					$(this).removeClass('loading');
				},
				'dropdownClicked' : function(e, value, label) {
					e.preventDefault();
					$dropdown.hide();
					if ($.isFunction(options.afterClick)) {
						options.afterClick(value, label);
					}
				}
			});
		$(this).data('dropdown-init', true);
		return $dropdown;
	};
	
	$.fn.formAutoComplete = function(options) {
		var defaults = {
			'click' : false,
			'afterClick' : false,
			'timeoutWait' : 250,
			'store' : 'hidden',
			'dataType' : 'json',
			'action' : 'select',
			'searchTerm': 'text',
			'reset': false
		};
		var options = $.extend(defaults, options);
		
		if ($(this).data('autocomplete-init') && !options.reset) {
			return $(this);
		}
		
		var $this = $(this),
			$text = $this.find('input[type*=text]').attr('autocomplete', 'off'),
			$hidden = $this.find('input[type*=' + options.store + ']'),
			$display = $this.find('div.display'),
			url = $text.attr('url'),
			redirectUrl = $text.attr('redirect_url'),
			isJson = (url.indexOf('json') > 0),
			$defaultVals = $this.find('select.default-vals').first(),
			$dropdown = $text.dropdown({
				'tag': isJson ? 'ul' : 'div',
				'itemTag': isJson ? 'li' : 'div',
				'emptyMessage': 'Begin typing to load results',
				'emptyResult': 'No results found. Please try searching for a different phrase',
				'afterClick': function(value, label) {
					if (options.action == 'select') {
						showDisplay();
					} else if (options.action == 'redirect') {
						window.location.href = redirectUrl ? redirectUrl + value : value;
					} else {
						$.error('Action: ' + options.action + ' not found for jQuery.formAutoComplete');
					}
					
					if (!$.isFunction(options.click)) {
						clickDisplay(value, label);
					} else {
						options.click(value, label);
					}
					if ($.isFunction(options.afterClick)) {
						options.afterClick(value, label);
					}
				}
			}),
			timeout = false;
		
		function clickDisplay(value, label) {
			showDisplay();
			$display.html(label);
			$hidden.attr('value', value);
		}
		
		function showDisplay() {
			if ($display.length) {
				$display.show();
				$text.hide().attr('disabled', true);
				$hidden.attr('disabled', false);
			} else {
				showText();
			}
		}
		function showText() {
			$display.hide();
			$text.show().attr('disabled', false);
			$hidden.attr('disabled', true);
		}
		
		if ($defaultVals.length) {
			var defaultVals = new Array();
			$defaultVals.attr('disabled', true).hide().find('option').each(function() {
				if ($(this).val() != '') {
					defaultVals.push(new Array($(this).val(), $(this).html()));
				}
			});
			$dropdown.trigger('setDefault', [defaultVals]);
		}
		
		$display
			.hover(function() {
				$(this).css('cursor', 'pointer');
			})
			.click(function(e) {
				e.preventDefault();
				showText();
				$text.select();
			});
		
		//Init Values
		if (options.action == 'select' && $hidden.val() && $text.val()) {
			clickDisplay($hidden.val(), $text.val());
		} else if ($hidden.val()) {
			if ($display.html() == '') {
				$display.html('Value Set');
			}
			showDisplay();
		} else {
			showText();
		}
		
		$text.keyup(function() {
			if (timeout) {
				clearTimeout(timeout);
			}
			timeout = setTimeout(function() {
				$dropdown.trigger('loading', [{
					dataType: isJson ? 'json' : 'html',
					url: (url + (url.indexOf('?') > 0 ? '&' : '?') + options.searchTerm + '=' + $text.attr('value'))
				}]);
			}, options.timeoutWait);
		}).focus(function() {
			$dropdown.trigger('show');
		}).blur(function() {
			$dropdown.delay(400).slideUp();
		}).bind({
			'reset': function() {
				showText();
			}
		});
		/*
		if ($text.val() == '') {
			showText();
		}
		*/
		
		$(this).data('autocomplete-init', true);
		return $(this);
	};
})(jQuery);

function formLayoutInit() {
	$('.input-list').each(function() {
		$(this).inputList();
	});
	
	$('.input-autocomplete').each(function() {
		var loadOptions = {'action': 'select'};
		if ($(this).hasClass('action-redirect')) {
			loadOptions.action = 'redirect';
		}
		$(this).formAutoComplete(loadOptions);
	});
	$('.input-autocomplete-multi').each(function() {
		if (!$(this).data('autocomplete-init')) {
			var $vals = $(this).find('> .vals'),
				$input = $(this).find('input').first();
			
			if (!$vals.length) {
				$vals = $('<div class="vals"></div>').appendTo($(this));
			}
			var loadOptions = {
				'afterClick': function(value, label) {
					var $existing = $vals.find('[value="'+value+'"]');
					if (!$existing.length) {
						var length = $vals.find(':input').length,
							name = $input.attr('name');
						$('<label>'+label+'</label>').prepend(
							$('<input/>', {
								'type': 'checkbox',
								'name': name,
								'value': value,
								'checked': true
							}).renumberInput(length)
						).appendTo($vals);
						$input.renumberInput(length + 1).val('');
					} else {
						$existing.attr('checked', true);
					}
				}
			};
			if ($(this).hasClass('action-redirect')) {
				loadOptions.action = 'redirect';
			}
			$(this).formAutoComplete(loadOptions);
			if ($(this).find('.dropdown-holder').length) {
				$vals.appendTo($(this).find('> .dropdown-holder'));
			}
		}
	});
	
}

$(document).ajaxComplete(function() {
	inputChoicesInit();
	formLayoutInit();
});

$(document).ready(function() {
	formLayoutInit();
	inputChoicesInit();
	formLayoutToggleInit();
	$(this).find('.multi-select').multiSelectInit();
	$(this).find('select[name*="input_select"]').change(function() {
		$(this).closest('div').find('input').first().attr('value', $(this).attr('value')).change();
	});
});