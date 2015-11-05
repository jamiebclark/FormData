<?php
if (!isset($class)) {
	$class = false;
}
if (!isset($close)) {
	$close = true;
}
?>
<div id="formdata-alert" class="alert<?php echo ($class) ? ' ' . $class : null; ?>">
	<?php echo $message; ?>
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