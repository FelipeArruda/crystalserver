<?php

$path = '/myaac-data/plugins/theme-canary/themes/canary/index.php';

if (!file_exists($path)) {
	exit(0);
}

$contents = file_get_contents($path);

if ($contents === false) {
	fwrite(STDERR, "Unable to read {$path}\n");
	exit(1);
}

$pattern = '/<img id="Boss"\s+src="<\?= \$config\[\'item_images_url\'\] \?><\?= \$bosstypeEx; \?>\.gif"/';
$replace = '<img id="Boss" src="<?= setting(\'core.item_images_url\') ?><?= $bosstypeEx; ?><?= setting(\'core.item_images_extension\') ?>"';

$updated = preg_replace($pattern, $replace, $contents, 1);

if (!is_string($updated)) {
	fwrite(STDERR, "Unable to patch boss image block in {$path}\n");
	exit(1);
}

$templateSelectorSearch = <<<'PHP'
					if ($config['template_allow_change'])
						echo '<span style="color: white">Template:</span><br/>' . template_form();
PHP;

$updated = str_replace($templateSelectorSearch, '', $updated);

if ($updated === $contents) {
	exit(0);
}

if (file_put_contents($path, $updated) === false) {
	fwrite(STDERR, "Unable to write {$path}\n");
	exit(1);
}
