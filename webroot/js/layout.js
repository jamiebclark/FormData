(function($) {
	$.fn.layoutDropdown = function() {
		var $dropdown = $(this),
			$arrow = $dropdown.find('> .arrow'),
			$box = $dropdown.find('> .dropdown-box'),
			sidePadding = 10;
		if ($dropdown.data('dropdown-init')) {
			return $(this);
		}

		function hideBox() {
			if (!$box.is(':hidden')) {
				$box.slideUp();
			}
			$dropdown.removeClass('clicked');
		}
		function showBox() {
			if ($box.is(':hidden')) {
				$box.slideDown().css({
					'display': 'block', 
					'right': 0,
					'top': $arrow.outerHeight()
				});
				var off = $box.offset(),
					r = (off.left < sidePadding) ? off.left - sidePadding : 0;
				$box.css('right', r);
			}
			$dropdown.addClass('clicked');
		}
		$(document).click(function(e) {
			hideBox();
		});
		$arrow.click(function(e) {
			if (!$dropdown.hasClass('clicked')) {
				e.stopPropagation();
				showBox();
			}
			e.preventDefault();
		});
		$dropdown.data('dropdown-init', true);
		hideBox();
		return $(this);
	};
	
	$.fn.contentBoxToggle = function(openCommand, isInit) {
		if (!openCommand) {
			var openCommand = isInit ? !$(this).hasClass('toggleClose') : $(this).hasClass('toggleClose');
		}
		if (openCommand) {
			$(this).removeClass('toggleClose').find('.contentBoxBody').slideDown();
		} else {
			$(this).addClass('toggleClose').find('.contentBoxBody').slideUp();
		}
		return $(this);
	};
	
	var hoverCount = 0;
	$.fn.hoverContent = function() {
		var $this = $(this),
			$content = $this.find('.hover-content'),
			showWait = 600,
			hideWait = 250,
			isHovered = false,
			hoverLeft = $this.hasClass('hover-left');
		
		if (!$('#hover-content-holder').length) {
			$('body').append($('<div id="hover-content-holder"></div>').css('position', 'static'));
		}
		var $hoverContentHolder = $('#hover-content-holder');
		
		if (!$this.data('hover-init')) {
			$this.find('.hover-content,.hover-over').hover(function() {
				isHovered = true;
				$content.delay(showWait).queue(function(n) {
					if (isHovered) {
						$this.addClass('hovering');
						var $pos = $this.offset();
						$content.show();
						//Left-Right
						if (($pos.left + 30 + $content.width()) > $(window).width()) {
							$content.addClass('position-right');
							$pos.left = $pos.left - $content.width() + $this.width();
						} else {
							$content.removeClass('position-right');
						}
						var $css = {
							top : $pos.top + $this.height(),
							left : $pos.left,
							bottom : 'auto'
						};
						//Top-Bottom
						if (($pos.top + $content.height()) > ($(window).scrollTop() + $(window).height())) {
							$content.addClass('position-down');
							$css.top = $pos.top - $content.height();
						} else {
							$content.removeClass('position-down');
						}
						
						if (hoverLeft) {
							$css.top = $pos.top;
							$css.left = $pos.left - $content.width();
							$content.addClass('hover-left');
						} else {
							$content.removeClass('hover-left');
						}
						
						$content.css($css);
					}
					n();
				});
			}, function() {
				isHovered = false;
				$content.delay(hideWait).queue(function(n) {
					if (!isHovered) {
						$this.removeClass('hovering');
						$content.hide();
					}
					n();
				});
			});
			hoverCount++;
			$this.data('hoverId', hoverCount);
			$content.data('hoverId', hoverCount);
			$content.attr('id', 'hover-content' + hoverCount);
			
			$this.bind('remove', function() {
				$('#hover-content' + $this.data('hoverId')).remove();
			});
			$hoverContentHolder.append($content);
		}
		$this.data('hover-init', true);
	};
	

})(jQuery);

$(document).ajaxComplete(function() {
	$('.hover-layout').each(function() {$(this).hoverContent()});
	$('.layout-dropdown').each(function() {$(this).layoutDropdown();});
});

$(document).ready(function() {
	$('.layout-dropdown').each(function() {$(this).layoutDropdown();});
	
	$('.contentBox.toggle').contentBoxToggle(false, true).each(function() {
		$(this).find('h2 a').first().click(function() {
			$(this).closest('.contentBox').contentBoxToggle();
			return false;
		});
	});
	
	$('input.check-all').click(function(e) {
		$(this).closest('form').find('.table-checkbox input[type="checkbox"]').attr('checked', $(this).is(':checked'));
		return $(this);
	});
	
	$('.user-profiles-dropdown-arrow').click(function(e) {
		var $dropdown = $(this).closest('.user-profiles').find('.user-profiles-dropdown').first();
		if ($dropdown.is(':visible')) {
			$(this).removeClass('clicked');
			$dropdown.hide();
		} else {
			$(this).addClass('clicked');
			$dropdown.show();
		}
		e.preventDefault();
	});
	
	$('.hover-layout').each(function() {$(this).hoverContent()});	
	
	$('.layoutActionMenu').not('.no-resize').each(function() {
		//Makes sure parent TD tightly wraps around the menu
		var $td = $(this).parent('td').removeAttr('width'),
			w = 0,
			$ul = $(this).find('> ul'),
			$lis = $ul.find('> li');
		if ($td.length) {
			$lis.each(function() {
				w += $(this).outerWidth() + 5;
			});
			w = Math.ceil(w / $lis.length) * $lis.length;
			$td.width(w).css({'padding-left':0,'padding-right':0});		
		}
	});
});