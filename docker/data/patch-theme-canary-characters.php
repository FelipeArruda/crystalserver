<?php

$path = '/myaac-data/plugins/theme-canary/pages/characters.php';
$twigPath = '/myaac-data/plugins/theme-canary/themes/canary/characters.html.twig';

if (!file_exists($path)) {
	exit(0);
}

$contents = file_get_contents($path);

if ($contents === false) {
	fwrite(STDERR, "Unable to read {$path}\n");
	exit(1);
}

$updated = $contents;

$replace = "\t\t\t\t\t\$equipment[\$i] = str_replace(\n"
	. "\t\t\t\t\t\t['images/items/empty.gif', 'width=\"32\" height=\"32\"'],\n"
	. "\t\t\t\t\t\t['images/items/' . \$empty_slots[\$i] . '.gif', 'width=\"40\" height=\"40\"'],\n"
	. "\t\t\t\t\t\tgetItemImage(\$equipment[\$i])\n"
	. "\t\t\t\t\t);";

if (strpos($updated, $replace) === false) {
	$pattern = '/^(\s*)\$equipment\[\$i\] = getItemImage\(\$equipment\[\$i\]\);$/m';
	$patched = preg_replace($pattern, $replace, $updated, 1);
	if (is_string($patched)) {
		$updated = $patched;
	}
}

$capacityHelper = <<<'PHP'
function themeCanaryCalculateFreeCapacity(OTS_Player $player, $db): int
{
	$totalCapacity = (int) $player->getCap();

	if (!$db->hasTableAndColumns('player_items', ['player_id', 'pid', 'sid', 'itemtype', 'count'])) {
		return max(0, $totalCapacity);
	}

	if (!is_array(\MyAAC\Items::$items) || empty(\MyAAC\Items::$items)) {
		\MyAAC\Items::load();
		if (!is_array(\MyAAC\Items::$items) || empty(\MyAAC\Items::$items)) {
			\MyAAC\Items::loadFromXML();
			\MyAAC\Items::load();
		}
	}

	if (!is_array(\MyAAC\Items::$items) || empty(\MyAAC\Items::$items)) {
		return max(0, $totalCapacity);
	}

	$rows = $db->query(
		'SELECT `pid`, `sid`, `itemtype`, `count` FROM `player_items` WHERE `player_id` = ' . $player->getId()
	)->fetchAll();

	if (!$rows) {
		return max(0, $totalCapacity);
	}

	$itemsBySid = [];
	$childrenByPid = [];
	foreach ($rows as $row) {
		$sid = (int) $row['sid'];
		$pid = (int) $row['pid'];
		$itemsBySid[$sid] = $row;
		$childrenByPid[$pid][] = $sid;
	}

	$calculateWeight = function (int $sid) use (&$calculateWeight, $itemsBySid, $childrenByPid): int {
		if (!isset($itemsBySid[$sid])) {
			return 0;
		}

		$row = $itemsBySid[$sid];
		$weight = 0;
		$item = \MyAAC\Items::get((int) $row['itemtype']);
		$attributes = $item['attributes'] ?? [];
		$weight = (int) ($attributes['weight'] ?? 0);
		$count = max(1, (int) $row['count']);
		$isFluid = isset($attributes['fluidsource']) || (($attributes['type'] ?? '') === 'fluid');
		$isCharged = isset($attributes['charges']);
		if ($count > 1 && !$isFluid && !$isCharged) {
			$weight *= $count;
		}

		if (isset($childrenByPid[$sid])) {
			foreach ($childrenByPid[$sid] as $childSid) {
				$weight += $calculateWeight($childSid);
			}
		}

		return $weight;
	};

	$totalWeight = 0;
	for ($slot = 1; $slot <= 10; $slot++) {
		if (!isset($childrenByPid[$slot])) {
			continue;
		}

		foreach ($childrenByPid[$slot] as $sid) {
			$totalWeight += $calculateWeight($sid);
		}
	}

	return max(0, (int) floor($totalCapacity - ($totalWeight / 100)));
}
PHP;

$accountStatusHelper = <<<'PHP'
function themeCanaryResolveAccountStatus(OTS_Account $account, array $config): string
{
	$premDays = $account->getPremDays();
	$freePremium = (isset($config['lua']['freePremium']) && getBoolean($config['lua']['freePremium']))
		|| $premDays == OTS_Account::GRATIS_PREMIUM_DAYS;

	if ($freePremium || $account->isPremium()) {
		return '<b><span style="color: green">Premium Account</span></b>';
	}

	return '<b><span style="color: red">Free Account</span></b>';
}
PHP;

if (strpos($updated, 'function themeCanaryCalculateFreeCapacity(') !== false) {
	$helperPattern = '/function themeCanaryCalculateFreeCapacity\(OTS_Player \$player, \$db\): int\s*\{.*?\n\}\n\n\$player = new OTS_Player\(\);/s';
	$patched = preg_replace($helperPattern, $capacityHelper . "\n\n\$player = new OTS_Player();", $updated, 1);
	if (is_string($patched)) {
		$updated = $patched;
	}
}
else {
	$search = "\$oldName = '';\n\n\$player = new OTS_Player();";
	$replacement = "\$oldName = '';\n\n{$capacityHelper}\n\n\$player = new OTS_Player();";
	$patched = str_replace($search, $replacement, $updated, $count);
	if ($count > 0) {
		$updated = $patched;
	}
}

if (strpos($updated, 'function themeCanaryResolveAccountStatus(') !== false) {
	$helperPattern = '/function themeCanaryResolveAccountStatus\(OTS_Account \$account, array \$config\): string\s*\{.*?\n\}\n\n\$player = new OTS_Player\(\);/s';
	$patched = preg_replace($helperPattern, $accountStatusHelper . "\n\n\$player = new OTS_Player();", $updated, 1);
	if (is_string($patched)) {
		$updated = $patched;
	}
}
else {
	$search = "\$player = new OTS_Player();";
	$replacement = "{$accountStatusHelper}\n\n\$player = new OTS_Player();";
	$patched = str_replace($search, $replacement, $updated, $count);
	if ($count > 0 && is_string($patched)) {
		$updated = $patched;
	}
}

if (strpos($updated, '$player_cap = themeCanaryCalculateFreeCapacity($player, $db);') === false) {
	$patched = str_replace(
		'$player_cap = $player->getCap();',
		'$player_cap = themeCanaryCalculateFreeCapacity($player, $db);',
		$updated,
		$count
	);
	if ($count > 0) {
		$updated = $patched;
	}
}

if (strpos($updated, '$account_status = themeCanaryResolveAccountStatus($account, $config);') === false) {
	$patched = preg_replace(
		'/(\$account = \$player->getAccount\(\);\s*\n)(\s*\$rows = 0;)/',
		"$1\t\t\$account_status = themeCanaryResolveAccountStatus(\$account, \$config);\n$2",
		$updated,
		1,
		$count
	);
	if ($count > 0 && is_string($patched)) {
		$updated = $patched;
	}
}

if (strpos($updated, "'account_status' => \$account_status") === false) {
	$patched = str_replace(
		"\t\t'canEdit' => hasFlag(FLAG_CONTENT_PLAYERS) || superAdmin(),\n\t\t'vip_enabled' => isVipSystemEnabled()",
		"\t\t'canEdit' => hasFlag(FLAG_CONTENT_PLAYERS) || superAdmin(),\n\t\t'account_status' => \$account_status,\n\t\t'vip_enabled' => isVipSystemEnabled()",
		$updated,
		$count
	);
	if ($count > 0) {
		$updated = $patched;
	}
}

if (!is_string($updated)) {
	exit(0);
}

if ($updated !== $contents) {
	if (file_put_contents($path, $updated) === false) {
		fwrite(STDERR, "Unable to write {$path}\n");
		exit(1);
	}
}

if (file_exists($twigPath)) {
	$twigContents = file_get_contents($twigPath);
	if ($twigContents === false) {
		fwrite(STDERR, "Unable to read {$twigPath}\n");
		exit(1);
	}

	$soulOldLine = '                                    <td style="color: #fff; text-align: center; font-size: 10px;">Soul: {{ soul }}</td>';
	$soulNewLine = '                                    <td style="color: #fff; text-align: center; font-size: 10px;"><span style="display: inline-block; width: 26px; text-align: left;">Soul:</span> <span style="display: inline-block; min-width: 18px; text-align: right;">{{ soul }}</span></td>';
	$capNewLine = '                                    <td style="color: #fff; text-align: center; font-size: 10px;"><span style="display: inline-block; width: 26px; text-align: left;">Cap:</span> <span style="display: inline-block; min-width: 18px; text-align: right;">{{ cap }}</span></td>';

	$twigUpdated = str_replace(
		'                                    <td style="color: #fff; text-align: center; font-size: 10px;">Soul: {{ soul }}</td>',
		$soulNewLine,
		$twigContents,
		$soulCount
	);
	$twigUpdated = str_replace(
		'                                    <td style="color: #fff; text-align: center; font-size: 10px;">{{ cap > 0 ? \'Cap: \' ~ cap : \'\' }}</td>',
		$capNewLine,
		$twigUpdated,
		$capCount
	);

	$accountStatusOldBlock = <<<'TWIG'
                            <td>
                              {% if vip_enabled %}
                                VIP
                                {% if account.isPremium() %}
                                  <strong
                                    style="color:green">actived</strong> until {{ account.getExpirePremiumTime()|date("j M Y, H:i") }}
                                {% else %}
                                  <strong style="color:red">desactivated</strong>
                                {% endif %}
                              {% else %}
                                {% if account.isPremium() %}
                                  <font color="green"><b>Premium Account</b></font>
                                {% else %}
                                  <font color="red">Free Account</font>
                                {% endif %}
                              {% endif %}
                            </td>
TWIG;
	$accountStatusNewBlock = <<<'TWIG'
                            <td>{{ account_status|raw }}</td>
TWIG;

	$twigUpdated = str_replace(
		$accountStatusOldBlock,
		$accountStatusNewBlock,
		$twigUpdated,
		$accountStatusCount
	);

	if (is_string($twigUpdated) && $twigUpdated !== $twigContents) {
		if (file_put_contents($twigPath, $twigUpdated) === false) {
			fwrite(STDERR, "Unable to write {$twigPath}\n");
			exit(1);
		}
	}
}
