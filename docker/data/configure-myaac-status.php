<?php

$statusHost = trim((string) getenv('MYAAC_STATUS_HOST'));
$statusPort = trim((string) getenv('MYAAC_STATUS_PORT'));

if ($statusHost === '' && $statusPort === '') {
	exit(0);
}

try {
	require '/var/www/html/common.php';
	require '/var/www/html/system/functions.php';
	require '/var/www/html/system/init.php';

	global $db;

	$escape = static function (string $value): string {
		return str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
	};

	if ($statusHost !== '') {
		$escapedHost = $escape($statusHost);
		$db->query(
			"INSERT INTO `myaac_config` (`name`, `value`) VALUES ('core.status_ip', '{$escapedHost}') " .
			"ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
		);
	}

	if ($statusPort !== '') {
		$escapedPort = $escape($statusPort);
		$db->query(
			"INSERT INTO `myaac_config` (`name`, `value`) VALUES ('core.status_port', '{$escapedPort}') " .
			"ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
		);
	}

	$db->query(
		"INSERT INTO `myaac_config` (`name`, `value`) VALUES ('status_lastCheck', '0') " .
		"ON DUPLICATE KEY UPDATE `value` = '0'"
	);

	echo "MyAAC status configuration synced.\n";
} catch (Throwable $e) {
	fwrite(STDERR, "Skipping MyAAC status sync: " . $e->getMessage() . PHP_EOL);
}
