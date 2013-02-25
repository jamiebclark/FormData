function tableAjaxEditInit() {
	$('.table-cell-edit').each(function() {
		if (!$(this).is(':visible')) {
			$(this).find('input,select').attr('disabled', true);
		}
		var $td = $(this).closest('td');
		if (!$td.hasClass('table-checkbox')) {		
			$td.css('width', $(this).closest('td').width());
			
			var tr = $(this).closest('tr');
			tr.find('a[href*="edit"],a[class*="table-add-link"]').click(function(e) {
				if (tr.toggleTableCellEdit(true)) {
					e.preventDefault();
				}
				return $(this);
			});
		}
		return $(this);
	});
	$('a.table-cell-edit-cancel').click(function(e) {
		$(this).closest('tr').toggleTableCellEdit(false);
		e.preventDefault();
	});
	
	/*
	$('table').each(function() {
		var table = $(this);
		if ($(this).find('.table-cell-edit').length) {
			var $addTr = $('<tr></tr>');
			var $rowCount = 0;
			$(this).find('tr').each(function() {
				if ($(this).find('.table-cell-edit').length) {
					if (!$rowCount) {
						$(this).find('td').each(function() {
							var $td = $(this);
							var $newTd = $('<td></td>');
							if ($(this).find('.table-cell-edit').length) {
								var $editCell = $td.find('.table-cell-edit').clone();
								$editCell.show().find('input').attr('value', '');
								$newTd.append($editCell);//$('<div class="table-cell-edit"></div>').append($editCell.contents()));
							} else {
								$newTd.html('&nbsp;');
							}
							$addTr.append($newTd);
						});
					}
					$rowCount++;
				}
			});
		}
		table.append($addTr);
	});
	*/
}

(function($) {
	var lastCheckedIndex = 0;
	var nextLastCheckedIndex = 0;
	
	$.fn.tableCheckbox = function () {
		var $checkbox = $(this),
			shiftPress = false;
		
		function getIndex() {
			var name = $checkbox.attr('name'),
				reg = /\[table_checkbox\]\[([\d]+)\]/m
				idKeyMatch = name.match(reg);
			return idKeyMatch ? idKeyMatch[1] : 0;
		}
		
		function shiftClick(start, stop, markChecked) {
			var $chk;
			if (start > stop) {
				var tmp = start;
				start = stop;
				stop = tmp;
			}
			for (var i = start; i <= stop; i++) {
				$chk = $('input[name="data[table_checkbox][' + i + ']"]');
				if ($chk.length) {
					if (markChecked) {
						$chk.attr('checked', true);
					} else {
						$chk.removeAttr('checked');
					}
				}
			}
		}
		
		$(document)
			.keydown(function(e) {
				shiftPress = (e.keyCode == 16);
				return $(this);
			})
			.keyup(function() {
				shiftPress = false;
				return $(this);
			});

		
		$checkbox
			.data('index', getIndex())
			.click(function(e) {
				var index = $checkbox.data('index'),
					reclick = index == lastCheckedIndex,
					start = reclick ? nextLastCheckedIndex : lastCheckedIndex,
					stop = index,
					markChecked = $(this).is(':checked');
				
				if (shiftPress) {
					shiftClick(start, stop, markChecked);
				}
				if (!reclick) { 
					nextLastCheckedIndex = lastCheckedIndex;
					lastCheckedIndex = index;
				}
				return $(this);
			});
			
		return $(this);
	};
	
	$.fn.tableSortLink = function() {
		var $sortLinks = $(this).filter(function() {
			return $(this).attr('href').match(/.*sort.*direction.*/);
		});
		
		$sortLinks.addClass('sort-select').wrap('<div class="table-sort-links"></div>');
		$sortLinks.after(function() {
			var $link = $(this),
				url = $link.attr('href')
				isAsc = $link.hasClass('asc'),
				isDesc = $link.hasClass('desc'),
				c = '',
				label = $link.attr('label');
			if (isAsc) {
				c = 'asc';
			} else if (isDesc) {
				c = 'desc';
			}
			if (!url) {
				return '';
			}
			var $div = $('<div class="table-sort"></div>')
				.append(function() {
					var c = 'asc';
					if (isAsc) {
						c += ' selected';
					}
					return $('<a>Ascending</a>').attr({
						'href': url.replace('direction:desc','direction:asc'),
						'class': c,
						'title': 'Sort the table by "' + label + '" in Ascending order'
					});
					
				});
			$div.append(function() {
				var c = 'desc';
				if (isDesc) {
					c += ' selected';
				}
				return $('<a>Descending</a>').attr({
					'href': url.replace('direction:asc', 'direction:desc'),
					'class': c,
					'title': 'Sort the table by this column in Descending order'
				});
			});
			return $div.before('<br/>').hide();
		});
		
		$sortLinks.closest('.table-sort-links').hover(function() {
				if ($(this).not(':animated')) {
					$(this).find('a').first().addClass('hover');
					$(this).find('.table-sort').stop(true).delay(500).slideDown(100);
				}
			},
			function() {
				if ($(this).not(':animated')) {
					$(this).find('a').first().removeClass('hover');
					$(this).find('.table-sort').stop(true).slideUp(100);
				}
			}
		);
		return $(this);
	};

	$.fn.toggleTableCellEdit = function(show) {
		var $edit = $(this).find('.table-cell-edit'),
			$view = $(this).find('.table-cell-view'),
			$inputs = $edit.find(':input');
		
		if (!$edit.length || !$edit.view) {
			return false;
		}
		
		if (!show) {
			$edit.slideUp();
			$inputs.attr('disabled', true);
			$view.show();
		} else {
			$edit.slideDown();
			$inputs.removeAttr('disabled').filter(':visible').first().select();
			$view.hide();
		}
		return $(this);
	};
})(jQuery);

$(document).ready(function() {
	tableAjaxEditInit();
	$('th a').tableSortLink();

	$('input[name*=table_checkbox]').each(function () {
		$(this).tableCheckbox();
	});
	
	$(this).bind('ajaxComplete', function() {
		tableAjaxEditInit();
	});
});