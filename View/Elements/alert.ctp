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
	} else if ($class == 'alert-error') {
		$icon = 'x_alt';
	}
}

?>
<div class="media alert<?php echo ($class) ? ' ' . $class : null; ?>">
<?php if (!empty($icon)): ?>
	<div class="pull-left">
		<?php echo $this->Iconic->icon($icon); ?>
	</div>
<?php endif; ?>
	<div class="media-body">
		<?php if ($close): ?>
			<a class="close" data-dismiss="alert" href="#">&times;</a>
		<?php endif; ?>
		<?php echo $message; ?>
	</div>
</div>