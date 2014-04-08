<?php
if (!isset($class)) {
	$class = false;
}
if (!isset($close)) {
	$close = true;
}

//If you're using Layout.Iconic helper, it can also insert an icon
if (!empty($this->Iconic)) {
	if ($class == 'alert-warning') {
		$icon = 'question_mark';
	} else if ($class == 'alert-info') {
		$icon = 'info';
	} else if ($class == 'alert-success') {
		$icon = 'check_alt';
	} else if ($class == 'alert-danger') {
		$icon = 'x_alt';
	}
}

?>
<div id="formdata-alert" class="media alert<?php echo ($class) ? ' ' . $class : null; ?>">
<?php if (!empty($icon)): ?>
	<div class="pull-left">
		<?php echo $this->Iconic->icon($icon); ?>
	</div>
<?php endif; ?>
	<div class="media-body">
		<?php echo $message; ?>
	</div>
</div>

<?php $this->Html->scriptStart(array('inline' => false)); ?>
$(document).ready(function() {
	$('#formdata-alert').each(function() {
		console.log("Found");
		var $alert = $(this),
			$body = $('.media-body', $alert),
			$close = $('<a></a>', {
				html: '&times;',
				class: 'close',
				href: '#'
			})
			.css({
				position: 'static',
				top: '-2px',
				right: '-21px',
				'line-height': '20px',
				'font-size': '20px'
			})
			.click(function(e) {
				e.preventDefault();
				$alert.slideUp();
			});
			
		$close.prependTo($body);
	});
});
<?php $this->Html->scriptEnd(); ?>