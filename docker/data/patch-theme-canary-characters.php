<?php

$path = '/myaac-data/plugins/theme-canary/pages/characters.php';

if (!file_exists($path)) {
	exit(0);
}

$contents = file_get_contents($path);

if ($contents === false) {
	fwrite(STDERR, "Unable to read {$path}\n");
	exit(1);
}

$replace = "\t\t\t\t\t\$equipment[\$i] = str_replace(\n"
	. "\t\t\t\t\t\t['images/items/empty.gif', 'width=\"32\" height=\"32\"'],\n"
	. "\t\t\t\t\t\t['images/items/' . \$empty_slots[\$i] . '.gif', 'width=\"40\" height=\"40\"'],\n"
	. "\t\t\t\t\t\tgetItemImage(\$equipment[\$i])\n"
	. "\t\t\t\t\t);";

if (strpos($contents, $replace) !== false) {
	exit(0);
}

$pattern = '/^(\s*)\$equipment\[\$i\] = getItemImage\(\$equipment\[\$i\]\);$/m';
$updated = preg_replace($pattern, $replace, $contents, 1);

if (!is_string($updated) || $updated === $contents) {
	fwrite(STDERR, "No changes were applied to {$path}\n");
	exit(1);
}

if (file_put_contents($path, $updated) === false) {
	fwrite(STDERR, "Unable to write {$path}\n");
	exit(1);
}
