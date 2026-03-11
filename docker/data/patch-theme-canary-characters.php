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

	if (is_string($twigUpdated) && $twigUpdated !== $twigContents) {
		if (file_put_contents($twigPath, $twigUpdated) === false) {
			fwrite(STDERR, "Unable to write {$twigPath}\n");
			exit(1);
		}
	}
}
