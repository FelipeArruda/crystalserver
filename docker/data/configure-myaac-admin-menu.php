<?php

try {
	require '/var/www/html/common.php';
	require '/var/www/html/system/functions.php';
	require '/var/www/html/system/init.php';

	global $db;

	$table = TABLE_PREFIX . 'admin_menu';
	if (!$db->hasTable($table)) {
		exit(0);
	}

	$name = 'Shop Transactions';
	$page = 'shop_transactions';
	$ordering = 85;

	$escapedName = str_replace(['\\', "'"], ['\\\\', "\\'"], $name);
	$escapedPage = str_replace(['\\', "'"], ['\\\\', "\\'"], $page);

	$exists = $db->query("SELECT `id` FROM `{$table}` WHERE `page` = '{$escapedPage}' LIMIT 1")->fetch();
	if ($exists) {
		$db->query(
			"UPDATE `{$table}` SET `name` = '{$escapedName}', `ordering` = {$ordering}, `flags` = 0, `enabled` = 1 WHERE `page` = '{$escapedPage}'"
		);
	}
	else {
		$db->query(
			"INSERT INTO `{$table}` (`name`, `page`, `ordering`, `flags`, `enabled`) VALUES ('{$escapedName}', '{$escapedPage}', {$ordering}, 0, 1)"
		);
	}

	echo "MyAAC admin menu synced.\n";
} catch (Throwable $e) {
	fwrite(STDERR, "Skipping MyAAC admin menu sync: " . $e->getMessage() . PHP_EOL);
}
