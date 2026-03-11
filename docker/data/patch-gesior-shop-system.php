<?php

$paths = [
	'/var/www/html/plugins/gesior-shop-system/libs/shop-system.php',
	'/myaac-data/plugins/gesior-shop-system/libs/shop-system.php',
];

$path = null;
foreach ($paths as $candidate) {
	if (is_file($candidate)) {
		$path = $candidate;
		break;
	}
}

if ($path === null) {
	fwrite(STDOUT, "Gesior shop plugin not found, skipping patch.\n");
	exit(0);
}

$contents = file_get_contents($path);

if ($contents === false) {
	fwrite(STDERR, "Unable to read {$path}\n");
	exit(1);
}

$contents = str_replace("\r\n", "\n", $contents);

$searches = [
	<<<'PHP'
							else {
								$buy_player_account->setCustomField('premdays', (int)$buy_player_account->getCustomField
								('premdays') + (int)$buy_offer['days']);

								if ($buy_player_account->getCustomField('lastday') == 0) {
									$buy_player_account->setCustomField('lastday', time());
								}
							}
PHP,
	<<<'PHP'
							else {
								$buy_player_account->setCustomField('premdays', (int)$buy_player_account->getCustomField('premdays') + (int)$buy_offer['days']);

								if ($buy_player_account->getCustomField('lastday') == 0) {
									$buy_player_account->setCustomField('lastday', time());
								}
							}
PHP,
];

$replace = <<<'PHP'
							else {
								$currentTime = time();
								$currentLastDay = (int) $buy_player_account->getCustomField('lastday');
								if ($currentLastDay < $currentTime) {
									$currentLastDay = $currentTime;
								}

								$newLastDay = $currentLastDay + ((int) $buy_offer['days'] * (60 * 60 * 24));
								$buy_player_account->setCustomField('lastday', $newLastDay);

								$secondsLeft = max(0, $newLastDay - $currentTime);
								$remainingDays = (int) floor($secondsLeft / (60 * 60 * 24));
								if ($secondsLeft > 0 && $remainingDays === 0) {
									$remainingDays = 1;
								}

								$buy_player_account->setCustomField('premdays', $remainingDays);

								if ($db->hasColumn('accounts', 'premdays_purchased')) {
									$buy_player_account->setCustomField(
										'premdays_purchased',
										(int) $buy_player_account->getCustomField('premdays_purchased') + (int) $buy_offer['days']
									);
								}
							}
PHP;

$updated = $contents;
$count = 0;
foreach ($searches as $search) {
	$updated = str_replace($search, $replace, $updated, $count);
	if ($count === 1) {
		break;
	}
}

if ($count === 1) {
	if (file_put_contents($path, $updated) === false) {
		fwrite(STDERR, "Unable to write {$path}\n");
		exit(1);
	}
}
elseif (strpos($contents, '$currentLastDay = (int) $buy_player_account->getCustomField(\'lastday\');') === false) {
	fwrite(STDERR, "Unable to patch premium account handling in {$path}\n");
	exit(1);
}

$pluginRoot = dirname(dirname($path));
$historyTemplate = $pluginRoot . '/templates/show-history.html.twig';
if (is_file($historyTemplate)) {
	$templateContents = file_get_contents($historyTemplate);
	if ($templateContents === false) {
		fwrite(STDERR, "Unable to read {$historyTemplate}\n");
		exit(1);
	}

	$templateUpdated = str_replace(
		'{{ pacc_received.pacc.days }}',
		'{{ pacc_received.item_name.days }} day{% if pacc_received.item_name.days != 1 %}s{% endif %}',
		str_replace("\r\n", "\n", $templateContents),
		$templateCount
	);

	if ($templateCount === 1 && file_put_contents($historyTemplate, $templateUpdated) === false) {
		fwrite(STDERR, "Unable to write {$historyTemplate}\n");
		exit(1);
	}
}

fwrite(STDOUT, "Patched Gesior shop plugin in {$pluginRoot}\n");
