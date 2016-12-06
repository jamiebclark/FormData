<?php
$default = [
	'class' => false,
	'close' => false,
];
if (!empty($params)) {
	$default = array_merge($default, $params);
}
extract(array_merge($default, compact(array_keys($default))));

if (empty($class)) {
	$class = 'alert-info';
}

?>
<div id="formdata-alert" class="alert <?php echo $class; ?>">
	<?php echo $this->element('FormData.Flash/alert/message', compact('message')); ?>
</div>

<?php $this->Html->scriptStart(array('inline' => false)); ?>
$(document).ready(function() {
	$('#formdata-alert').each(function() {
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