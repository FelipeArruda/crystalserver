<?php

$path = '/var/www/html/system/settings.php';
$contents = file_get_contents($path);

if ($contents === false) {
	fwrite(STDERR, "Unable to read {$path}\n");
	exit(1);
}

$updated = str_replace(
	[
		"'default' => 'https://item-images.ots.me/1092/',",
		"'default' => '.gif',",
		"'default' => '127.0.0.1',",
	],
	[
		"'default' => 'https://item-images.ots.me/latest_otbr/',",
		"'default' => '.png',",
		"'default' => 'otserver',",
	],
	$contents
);

if ($updated === $contents) {
	fwrite(STDERR, "No changes were applied to {$path}\n");
	exit(1);
}

if (file_put_contents($path, $updated) === false) {
	fwrite(STDERR, "Unable to write {$path}\n");
	exit(1);
}
