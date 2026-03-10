<?php
/**
 * Weapons class
 *
 * @package   MyAAC
 * @author    Gesior <jerzyskalski@wp.pl>
 * @author    Slawkens <slawkens@gmail.com>
 * @copyright 2019 MyAAC
 * @link      https://my-aac.org
 */

namespace MyAAC;

use MyAAC\Models\Weapon;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

defined('MYAAC') or die('Direct access not allowed!');

class Weapons {
	private static $error = '';

	public static function loadFromXML($show = false)
	{
		global $config;

		try {
			Weapon::query()->delete();
		}
		catch (\PDOException $error) {
		}

		$file_path = $config['data_path'] . 'weapons/weapons.xml';
		if (file_exists($file_path)) {
			return self::loadLegacyXml($file_path, $show);
		}

		return self::loadFromLua($show);
	}

	private static function loadLegacyXml($file_path, $show) {
		$xml = new \DOMDocument;
		$xml->load($file_path);

		foreach ($xml->getElementsByTagName('wand') as $weapon) {
			self::parseNode($weapon, $show);
		}
		foreach ($xml->getElementsByTagName('melee') as $weapon) {
			self::parseNode($weapon, $show);
		}
		foreach ($xml->getElementsByTagName('distance') as $weapon) {
			self::parseNode($weapon, $show);
		}

		self::$error = '';
		return true;
	}

	private static function loadFromLua($show) {
		global $config;

		$dir = dirname(rtrim(config('data_path'), '/')) . '/data/scripts/weapons';
		if (!is_dir($dir)) {
			self::$error = 'Cannot load Lua weapons from ' . $dir;
			return false;
		}

		$vocations = array_flip($config['vocations']);
		$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
		$loaded = 0;

		foreach ($files as $file) {
			if (!$file->isFile() || strtolower($file->getExtension()) !== 'lua') {
				continue;
			}

			$content = file_get_contents($file->getPathname());
			preg_match_all('/:\s*id\((\d+)\)/', $content, $idMatches);
			if (empty($idMatches[1])) {
				continue;
			}

			preg_match('/:\s*level\((\d+)\)/', $content, $levelMatch);
			preg_match('/:\s*mana\((\d+)\)/', $content, $manaMatch);
			preg_match('/:\s*vocation\(([^)]*)\)/is', $content, $vocationMatch);

			$weaponVocations = [];
			if (!empty($vocationMatch[1])) {
				preg_match_all('/"([^"]+)"/', $vocationMatch[1], $entries);
				foreach ($entries[1] as $entry) {
					$parts = explode(';', $entry);
					$name = trim($parts[0]);
					if (isset($vocations[$name])) {
						$weaponVocations[$vocations[$name]] = true;
					}
				}
			}

			foreach (array_unique($idMatches[1]) as $id) {
				if (Weapon::find((int)$id)) {
					continue;
				}

				Weapon::create([
					'id' => (int)$id,
					'level' => isset($levelMatch[1]) ? (int)$levelMatch[1] : 0,
					'maglevel' => isset($manaMatch[1]) ? (int)$manaMatch[1] : 0,
					'vocations' => json_encode($weaponVocations)
				]);
				$loaded++;

				if($show) {
					success('Added weapon: ' . $id . '<br/>');
				}
			}
		}

		if ($loaded === 0) {
			self::$error = 'Cannot find weapons in Lua datapack.';
			return false;
		}

		self::$error = '';
		return true;
	}

	public static function parseNode($node, $show = false) {
		global $config;

		$id = (int)$node->getAttribute('id');
		$vocations_ids = array_flip($config['vocations']);
		$level = (int)$node->getAttribute('level');
		$maglevel = (int)$node->getAttribute('maglevel');

		$vocations = [];
		foreach($node->getElementsByTagName('vocation') as $vocation) {
			$show = $vocation->getAttribute('showInDescription');
			if(!empty($vocation->getAttribute('id'))) {
				$voc_id = $vocation->getAttribute('id');
			}
			else {
				$voc_id = $vocations_ids[$vocation->getAttribute('name')];
			}

			$vocations[$voc_id] = strlen($show) == 0 || $show != '0';
		}

		if(Weapon::find($id)) {
			if($show) {
				warning('Duplicated weapon with id: ' . $id);
			}
		}
		else {
			Weapon::create([
				'id' => $id, 'level' => $level, 'maglevel' => $maglevel, 'vocations' => json_encode($vocations)
			]);
		}
	}

	public static function getError() {
		return self::$error;
	}
}
