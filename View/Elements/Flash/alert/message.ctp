<?php if (is_array($message)): ?>
	<ul>
		<?php foreach ($message as $listItem): ?>
			<li><?php echo $this->element('FormData.Flash/alert/message', ['message' => $listItem]); ?></li>
		<?php endforeach; ?>
	</ul>
<?php else: ?>
	<?php echo $message; ?>
<?php endif; ?>