<?php

$path = '/var/www/html/system/functions.php';
$contents = file_get_contents($path);

if ($contents === false) {
	fwrite(STDERR, "Unable to read {$path}\n");
	exit(1);
}

$replace = <<<'PHP'
function getItemImage($id, $count = 1)
{
	$tooltip = '';
	$fallback = 'images/items/empty.gif';

	Items::load();
	$name = getItemNameById($id);
	if(!empty($name)) {
		$tooltip = ' class="item_image" title="' . $name . '"';
	}

	$item = Items::get($id);
	if(isset($item['attributes']) && is_array($item['attributes'])) {
		$attributes = $item['attributes'];
		$slot = $attributes['slot'] ?? '';

		$slotFallbacks = [
			'ammo' => 'images/items/no_ammo.gif',
			'armor' => 'images/items/no_armor.gif',
			'backpack' => 'images/items/no_backpack.gif',
			'boots' => 'images/items/no_boots.gif',
			'feet' => 'images/items/no_boots.gif',
			'head' => 'images/items/no_helmet.gif',
			'helmet' => 'images/items/no_helmet.gif',
			'left-hand' => 'images/items/no_handleft.gif',
			'legs' => 'images/items/no_legs.gif',
			'necklace' => 'images/items/no_necklace.gif',
			'ring' => 'images/items/no_ring.gif',
			'right-hand' => 'images/items/no_handright.gif',
		];

		if(isset($slotFallbacks[$slot])) {
			$fallback = $slotFallbacks[$slot];
		}
	}

	$file_name = $id;
	if($count > 1)
		$file_name .= '-' . $count;

	$src = setting('core.item_images_url') . $file_name . setting('core.item_images_extension');
	return '<img src="' . $src . '"' . $tooltip . ' width="32" height="32" border="0" alt="' . $id . '" onerror="this.onerror=null;this.src=\'' . $fallback . '\';" />';
}
PHP;

$pattern = '/function getItemImage\\(\\$id, \\$count = 1\\)\\s*\\{.*?\\n\\}\\n\\nfunction getItemRarity\\(/s';
$updated = preg_replace($pattern, $replace . "\n\nfunction getItemRarity(", $contents, 1);

if (!is_string($updated) || $updated === $contents) {
	fwrite(STDERR, "No changes were applied to {$path}\n");
	exit(1);
}

if (file_put_contents($path, $updated) === false) {
	fwrite(STDERR, "Unable to write {$path}\n");
	exit(1);
}
