<?php
/**
 * Creatures class
 *
 * @package   MyAAC
 * @author    Gesior <jerzyskalski@wp.pl>
 * @author    Slawkens <slawkens@gmail.com>
 * @copyright 2019 MyAAC
 * @link      https://my-aac.org
 */

namespace MyAAC;

use MyAAC\Models\Monster;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class Monsters {
	/**
	 * @var \OTS_MonstersList|object
	 */
	private static $monstersList;
	private static $lastError = '';

	public static function loadFromXML($show = false) {
		try {
			Monster::query()->delete();
		}
		catch(\Exception $error) {}

		if($show) {
			echo '<h2>Reload monsters.</h2>';
			echo "<h2>All records deleted from table '" . TABLE_PREFIX . "monsters' in database.</h2>";
		}

		try {
			self::$monstersList = new \OTS_MonstersList(config('data_path') . 'monster/');
			return self::loadLegacyXml($show);
		}
		catch(\Exception $e) {
			$luaDir = self::resolveLuaMonsterDirectory();
			if($luaDir === null) {
				self::$lastError = $e->getMessage();
				return false;
			}

			self::$monstersList = new class {
				public function hasErrors() {
					return false;
				}
			};

			return self::loadFromLua($luaDir, $show);
		}
	}

	private static function loadLegacyXml($show) {
		$items = self::buildItemLookup();
		$names_added = [''];

		foreach(self::$monstersList as $lol) {
			$monster = self::$monstersList->current();
			if(!$monster->loaded()) {
				if($show) {
					warning('Error while adding monster: ' . self::$monstersList->currentFile());
				}
				continue;
			}

			$mana = $monster->getManaCost();
			$name = $monster->getName();
			$health = $monster->getHealth();
			$speed_ini = $monster->getSpeed();
			$speed_lvl = $speed_ini <= 220 ? 1 : ($speed_ini - 220) / 2;
			$defenses = $monster->getDefenses();
			$use_haste = 0;
			foreach($defenses as $defense) {
				if($defense == 'speed') {
					$use_haste = 1;
				}
			}

			$race = $monster->getRace();
			$armor = $monster->getArmor();
			$defensev = $monster->getDefense();
			$look = $monster->getLook();
			$flags = $monster->getFlags();
			$flags = self::normalizeLegacyFlags($flags);

			$summons = $monster->getSummons();
			$loot = $monster->getLoot();
			foreach($loot as &$item) {
				if(!\Validator::number($item['id'])) {
					$key = mb_strtolower((string)$item['id']);
					if(isset($items[$key])) {
						$item['id'] = $items[$key];
					}
				}
			}

			if(in_array($name, $names_added, true)) {
				continue;
			}

			self::storeMonster([
				'name' => $name,
				'mana' => empty($mana) ? 0 : $mana,
				'exp' => $monster->getExperience(),
				'health' => $health,
				'speed_lvl' => $speed_lvl,
				'use_haste' => $use_haste,
				'voices' => $monster->getVoices(),
				'immunities' => $monster->getImmunities(),
				'elements' => $monster->getElements(),
				'flags' => $flags,
				'defense' => $defensev,
				'armor' => $armor,
				'race' => $race,
				'loot' => $loot,
				'look' => $look,
				'summons' => $summons,
			], $show);

			$names_added[] = $name;
		}

		return true;
	}

	private static function loadFromLua($luaDir, $show) {
		self::$lastError = '';
		$items = self::buildItemLookup();
		$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($luaDir));
		$loaded = 0;

		foreach($files as $file) {
			if(!$file->isFile() || strtolower($file->getExtension()) !== 'lua') {
				continue;
			}

			$monster = self::parseLuaMonster(file_get_contents($file->getPathname()), $items);
			if($monster === null) {
				continue;
			}

			self::storeMonster($monster, $show);
			$loaded++;
		}

		if($loaded === 0) {
			self::$lastError = 'Cannot load monsters from Lua datapack at ' . $luaDir;
			return false;
		}

		return true;
	}

	private static function storeMonster(array $monster, $show) {
		try {
			Monster::create([
				'name' => $monster['name'],
				'mana' => $monster['mana'],
				'exp' => $monster['exp'],
				'health' => $monster['health'],
				'speed_lvl' => $monster['speed_lvl'],
				'use_haste' => $monster['use_haste'],
				'voices' => json_encode($monster['voices']),
				'immunities' => json_encode($monster['immunities']),
				'elements' => json_encode($monster['elements']),
				'summonable' => $monster['flags']['summonable'],
				'convinceable' => $monster['flags']['convinceable'],
				'pushable' => $monster['flags']['pushable'],
				'canpushitems' => $monster['flags']['canpushitems'],
				'canpushcreatures' => $monster['flags']['canpushcreatures'],
				'runonhealth' => $monster['flags']['runonhealth'],
				'canwalkonenergy' => $monster['flags']['canwalkonenergy'],
				'canwalkonpoison' => $monster['flags']['canwalkonpoison'],
				'canwalkonfire' => $monster['flags']['canwalkonfire'],
				'hostile' => $monster['flags']['hostile'],
				'attackable' => $monster['flags']['attackable'],
				'rewardboss' => $monster['flags']['rewardboss'],
				'defense' => $monster['defense'],
				'armor' => $monster['armor'],
				'race' => $monster['race'],
				'loot' => json_encode($monster['loot']),
				'look' => json_encode($monster['look']),
				'summons' => json_encode($monster['summons'])
			]);

			if($show) {
				success('Added: ' . $monster['name'] . '<br/>');
			}
		}
		catch(\Exception $error) {
			if($show) {
				warning('Error while adding monster (' . $monster['name'] . '): ' . $error->getMessage());
			}
		}
	}

	private static function parseLuaMonster($content, array $items) {
		if(!preg_match('/Game\.createMonsterType\("([^"]+)"\)/', $content, $nameMatch)) {
			return null;
		}

		$outfitBlock = self::extractTable($content, 'monster.outfit');
		$defenseBlock = self::extractTable($content, 'monster.defenses');
		$flagsBlock = self::extractTable($content, 'monster.flags');
		$voicesBlock = self::extractTable($content, 'monster.voices');
		$elementsBlock = self::extractTable($content, 'monster.elements');
		$immunitiesBlock = self::extractTable($content, 'monster.immunities');
		$lootBlock = self::extractTable($content, 'monster.loot');
		$summonsBlock = self::extractTable($content, 'monster.summons');

		$speed = self::extractNumber($content, 'monster.speed', 0);

		return [
			'name' => $nameMatch[1],
			'mana' => self::extractNumber($content, 'monster.manaCost', 0),
			'exp' => self::extractNumber($content, 'monster.experience', 0),
			'health' => self::extractNumber($content, 'monster.health', 0),
			'speed_lvl' => $speed <= 220 ? 1 : (int)(($speed - 220) / 2),
			'use_haste' => $defenseBlock !== null && strpos($defenseBlock, 'speed') !== false ? 1 : 0,
			'voices' => self::parseVoices($voicesBlock),
			'immunities' => self::parseImmunities($immunitiesBlock),
			'elements' => self::parseElements($elementsBlock),
			'flags' => self::parseFlags($flagsBlock),
			'defense' => self::extractNumberFromBlock($defenseBlock, 'defense', 0),
			'armor' => self::extractNumberFromBlock($defenseBlock, 'armor', 0),
			'race' => self::extractString($content, 'monster.race', 'blood'),
			'loot' => self::parseLoot($lootBlock, $items),
			'look' => self::parseLook($outfitBlock),
			'summons' => self::parseSummons($summonsBlock),
		];
	}

	private static function resolveLuaMonsterDirectory() {
		$basePath = rtrim(config('data_path'), '/');
		$candidates = [
			$basePath . '/monster',
			dirname($basePath) . '/data-global/monster',
			dirname($basePath) . '/data-crystal/monster',
		];

		foreach($candidates as $candidate) {
			if(is_dir($candidate)) {
				return $candidate;
			}
		}

		return null;
	}

	private static function buildItemLookup() {
		Items::load();
		$items = [];
		foreach((array)Items::$items as $id => $item) {
			if(!empty($item['name'])) {
				$items[mb_strtolower($item['name'])] = (int)$id;
			}
			if(!empty($item['plural'])) {
				$items[mb_strtolower($item['plural'])] = (int)$id;
			}
		}

		return $items;
	}

	private static function normalizeLegacyFlags(array $flags) {
		$defaults = [
			'summonable' => 0,
			'convinceable' => 0,
			'pushable' => 0,
			'canpushitems' => 0,
			'canpushcreatures' => 0,
			'runonhealth' => 0,
			'canwalkonenergy' => 0,
			'canwalkonpoison' => 0,
			'canwalkonfire' => 0,
			'hostile' => 0,
			'attackable' => 0,
			'rewardboss' => 0,
		];

		foreach($defaults as $key => $value) {
			if(!isset($flags[$key])) {
				$flags[$key] = $value;
			}
			else {
				$flags[$key] = $flags[$key] > 0 ? 1 : 0;
			}
		}

		return $flags;
	}

	private static function parseLook($block) {
		if($block === null) {
			return [];
		}

		return array_filter([
			'type' => self::extractNumberFromBlock($block, 'lookType', 0),
			'head' => self::extractNumberFromBlock($block, 'lookHead', 0),
			'body' => self::extractNumberFromBlock($block, 'lookBody', 0),
			'legs' => self::extractNumberFromBlock($block, 'lookLegs', 0),
			'feet' => self::extractNumberFromBlock($block, 'lookFeet', 0),
			'addons' => self::extractNumberFromBlock($block, 'lookAddons', 0),
			'mount' => self::extractNumberFromBlock($block, 'lookMount', 0),
			'typeEx' => self::extractNumberFromBlock($block, 'lookTypeEx', 0),
		], static function($value) {
			return $value !== 0;
		});
	}

	private static function parseFlags($block) {
		$map = [
			'summonable' => 'summonable',
			'convinceable' => 'convinceable',
			'pushable' => 'pushable',
			'canPushItems' => 'canpushitems',
			'canPushCreatures' => 'canpushcreatures',
			'runHealth' => 'runonhealth',
			'canWalkOnEnergy' => 'canwalkonenergy',
			'canWalkOnPoison' => 'canwalkonpoison',
			'canWalkOnFire' => 'canwalkonfire',
			'hostile' => 'hostile',
			'attackable' => 'attackable',
			'rewardBoss' => 'rewardboss',
		];
		$flags = array_fill_keys(array_values($map), 0);
		if($block === null) {
			return $flags;
		}

		foreach($map as $source => $target) {
			if(preg_match('/' . preg_quote($source, '/') . '\s*=\s*(true|false|-?\d+)/i', $block, $match)) {
				$value = strtolower($match[1]);
				$flags[$target] = ($value === 'true' || (is_numeric($value) && (int)$value > 0)) ? 1 : 0;
			}
		}

		return $flags;
	}

	private static function parseVoices($block) {
		if($block === null) {
			return [];
		}

		preg_match_all('/text\s*=\s*"((?:[^"\\\\]|\\\\.)*)"/', $block, $matches);
		return array_values(array_map('stripcslashes', $matches[1]));
	}

	private static function parseElements($block) {
		if($block === null) {
			return [];
		}

		$map = [
			'COMBAT_PHYSICALDAMAGE' => 'physical',
			'COMBAT_ENERGYDAMAGE' => 'energy',
			'COMBAT_EARTHDAMAGE' => 'earth',
			'COMBAT_FIREDAMAGE' => 'fire',
			'COMBAT_LIFEDRAIN' => 'lifedrain',
			'COMBAT_MANADRAIN' => 'manadrain',
			'COMBAT_DROWNDAMAGE' => 'drown',
			'COMBAT_ICEDAMAGE' => 'ice',
			'COMBAT_HOLYDAMAGE' => 'holy',
			'COMBAT_DEATHDAMAGE' => 'death',
		];
		$elements = [];

		preg_match_all('/\{\s*type\s*=\s*([A-Z_]+)\s*,\s*percent\s*=\s*(-?\d+)/', $block, $matches, PREG_SET_ORDER);
		foreach($matches as $match) {
			$elements[] = [
				'name' => $map[$match[1]] ?? strtolower($match[1]),
				'percent' => (int)$match[2],
			];
		}

		return $elements;
	}

	private static function parseImmunities($block) {
		if($block === null) {
			return [];
		}

		$immunities = [];
		preg_match_all('/\{\s*type\s*=\s*"([^"]+)"\s*,\s*condition\s*=\s*(true|false)/i', $block, $matches, PREG_SET_ORDER);
		foreach($matches as $match) {
			if(strtolower($match[2]) === 'true') {
				$immunities[] = $match[1];
			}
		}

		return $immunities;
	}

	private static function parseLoot($block, array $items) {
		if($block === null) {
			return [];
		}

		$loot = [];
		preg_match_all('/\{([^{}]+)\}/', $block, $matches);
		foreach($matches[1] as $entry) {
			$itemId = 0;
			if(preg_match('/\bid\s*=\s*(\d+)/', $entry, $idMatch)) {
				$itemId = (int)$idMatch[1];
			}
			elseif(preg_match('/\bname\s*=\s*"([^"]+)"/', $entry, $nameMatch)) {
				$key = mb_strtolower($nameMatch[1]);
				$itemId = $items[$key] ?? 0;
			}

			if($itemId === 0) {
				continue;
			}

			preg_match('/\bchance\s*=\s*(\d+)/', $entry, $chanceMatch);
			preg_match('/\b(maxCount|count)\s*=\s*(\d+)/', $entry, $countMatch);
			$loot[] = [
				'id' => $itemId,
				'chance' => isset($chanceMatch[1]) ? (int)$chanceMatch[1] : 0,
				'count' => isset($countMatch[2]) ? (int)$countMatch[2] : 1,
			];
		}

		return $loot;
	}

	private static function parseSummons($block) {
		if($block === null) {
			return [];
		}

		$summons = [];
		preg_match_all('/\{\s*name\s*=\s*"([^"]+)"\s*,\s*chance\s*=\s*(\d+)/', $block, $matches, PREG_SET_ORDER);
		foreach($matches as $match) {
			$summons[] = [
				'name' => $match[1],
				'chance' => (int)$match[2],
			];
		}

		return $summons;
	}

	private static function extractString($content, $field, $default = '') {
		if(preg_match('/' . preg_quote($field, '/') . '\s*=\s*"((?:[^"\\\\]|\\\\.)*)"/', $content, $match)) {
			return stripcslashes($match[1]);
		}

		return $default;
	}

	private static function extractNumber($content, $field, $default = 0) {
		if(preg_match('/' . preg_quote($field, '/') . '\s*=\s*(-?\d+)/', $content, $match)) {
			return (int)$match[1];
		}

		return $default;
	}

	private static function extractNumberFromBlock($block, $field, $default = 0) {
		if($block !== null && preg_match('/' . preg_quote($field, '/') . '\s*=\s*(-?\d+)/', $block, $match)) {
			return (int)$match[1];
		}

		return $default;
	}

	private static function extractTable($content, $field) {
		$assignment = preg_quote($field, '/') . '\s*=\s*\{';
		if(!preg_match('/' . $assignment . '/', $content, $match, PREG_OFFSET_CAPTURE)) {
			return null;
		}

		$start = $match[0][1];
		$braceStart = strpos($content, '{', $start);
		if($braceStart === false) {
			return null;
		}

		$depth = 0;
		$length = strlen($content);
		for($i = $braceStart; $i < $length; $i++) {
			$char = $content[$i];
			if($char === '{') {
				$depth++;
			}
			elseif($char === '}') {
				$depth--;
				if($depth === 0) {
					return substr($content, $braceStart, $i - $braceStart + 1);
				}
			}
		}

		return null;
	}

	public static function getMonstersList() {
		return self::$monstersList;
	}

	public static function getLastError() {
		return self::$lastError;
	}
}
