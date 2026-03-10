<?php

$itemsXmlPath = '/var/www/server/data/items/items.xml';
$shopsPath = '/var/www/server/data/scripts/lib/shops.lua';
$npcGlob = '/var/www/server/data-global/npc/*.lua';
$outputPath = '/myaac-data/item-client-id-map.php';

if (!file_exists($itemsXmlPath)) {
	exit(0);
}

$xml = simplexml_load_file($itemsXmlPath);
if ($xml === false) {
	fwrite(STDERR, "Unable to parse {$itemsXmlPath}\n");
	exit(1);
}

$normalize = static function (string $value): string {
	$value = mb_strtolower($value, 'UTF-8');
	$value = preg_replace('/[^a-z0-9]+/u', ' ', $value);
	return trim((string)$value);
};

$nameToServerIds = [];
foreach ($xml->item as $item) {
	$attributes = $item->attributes();
	if (!isset($attributes['id']) || !isset($attributes['name'])) {
		continue;
	}

	$serverId = (int)$attributes['id'];
	$name = $normalize((string)$attributes['name']);
	if ($serverId <= 0 || $name === '') {
		continue;
	}

	$nameToServerIds[$name] ??= [];
	$nameToServerIds[$name][] = $serverId;
}

$clientIdsByServerId = [];
$parseLuaFile = static function (string $path) use (&$clientIdsByServerId, $nameToServerIds, $normalize): void {
	$contents = file_get_contents($path);
	if ($contents === false) {
		return;
	}

	$pattern = '/itemName\s*=\s*"([^"]+)"[^\\n\\r}]*?clientId\s*=\s*(\d+)/i';
	if (!preg_match_all($pattern, $contents, $matches, PREG_SET_ORDER)) {
		return;
	}

	foreach ($matches as $match) {
		$name = $normalize($match[1]);
		$clientId = (int)$match[2];
		if ($name === '' || $clientId <= 0 || !isset($nameToServerIds[$name])) {
			continue;
		}

		foreach ($nameToServerIds[$name] as $serverId) {
			$clientIdsByServerId[$serverId] ??= $clientId;
		}
	}
};

if (file_exists($shopsPath)) {
	$parseLuaFile($shopsPath);
}

foreach (glob($npcGlob) ?: [] as $npcFile) {
	$parseLuaFile($npcFile);
}

ksort($clientIdsByServerId);

$export = "<?php\nreturn " . var_export($clientIdsByServerId, true) . ";\n";
if (file_put_contents($outputPath, $export) === false) {
	fwrite(STDERR, "Unable to write {$outputPath}\n");
	exit(1);
}
