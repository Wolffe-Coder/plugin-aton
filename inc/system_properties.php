<?php if ( ! defined( 'ABSPATH' ) ) exit;
$this->custom_css();
?>
<div class="wrap fm_systemPropertyWrap">
<h3 class="fm_headingTitle"><?php _e('Системные свойства','wp-file-manager'); ?></a></h3>

<table cellpadding="0" cellspacing="0" class="fm_systemPropertyTbl" style="background:#fff; padding:10px; border-left: 5px solid #09F;">

	<tr>
		<th><?php _e('Версия PHP','wp-file-manager'); ?></th>
		<td><code><?php echo phpversion(); ?></code></td>
	</tr>

	<tr>
		<th><?php _e('Максимальный размер загрузки файла (upload_max_filesize)','wp-file-manager'); ?></th>
		<td><code><?php echo ini_get('upload_max_filesize'); ?></code></td>
	</tr>

	<tr>
		<th><?php _e('Лимит памяти (memory_limit)','wp-file-manager'); ?></th>
		<td><code><?php echo ini_get('memory_limit'); ?></code></td>
	</tr>

	<tr>
		<th><?php _e('Браузер и ОС (HTTP_USER_AGENT)','wp-file-manager'); ?></th>
		<td><code><?php echo $_SERVER['HTTP_USER_AGENT']; ?></code></td>
	</tr>

</table>
</div>
