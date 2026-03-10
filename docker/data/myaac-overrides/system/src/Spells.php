<?php
/**
 * Spells class
 *
 * @package   MyAAC
 * @author    Gesior <jerzyskalski@wp.pl>
 * @author    Slawkens <slawkens@gmail.com>
 * @copyright 2019 MyAAC
 * @link      https://my-aac.org
 */

namespace MyAAC;

use MyAAC\Models\Spell;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class Spells {
	private static $spellsList = null;
	private static $lastError = '';

	public static function loadGroup($tGroup) {
		switch ($tGroup) {
			case 'attack':
				return 1;
			case 'healing':
				return 2;
			case 'summon':
				return 3;
			case 'supply':
				return 4;
			case 'support':
				return 5;
		}

		return 0;
	}

	public static function loadFromXML($show = false) {
		global $config;

		try {
			Spell::query()->delete();
		}
		catch(\Exception $error) {}

		if($show) {
			echo '<h2>Reload spells.</h2>';
			echo '<h2>All records deleted from table <b>' . TABLE_PREFIX . 'spells</b> in database.</h2>';
		}

		$filePath = $config['data_path'] . 'spells/spells.xml';
		if (file_exists($filePath)) {
			return self::loadLegacyXml($filePath, $show);
		}

		return self::loadFromLua($show);
	}

	private static function loadLegacyXml($filePath, $show) {
		try {
			self::$spellsList = new \OTS_SpellsList($filePath);
		}
		catch(\Exception $e) {
			self::$lastError = $e->getMessage();
			return false;
		}

		$conjurelist = self::$spellsList->getConjuresList();
		if($show) {
			echo "<h3>Conjure:</h3>";
		}

		foreach($conjurelist as $spellname) {
			$spell = self::$spellsList->getConjure($spellname);
			$name = $spell->getName();
			$words = $spell->getWords();
			if(strpos($words, '#') !== false) {
				continue;
			}

			self::storeSpell([
				'name' => $name,
				'words' => $words,
				'type' => 2,
				'mana' => $spell->getMana(),
				'level' => $spell->getLevel(),
				'maglevel' => $spell->getMagicLevel(),
				'soul' => $spell->getSoul(),
				'premium' => $spell->isPremium() ? 1 : 0,
				'vocations' => $spell->getVocations(),
				'conjure_count' => $spell->getConjureCount(),
				'conjure_id' => $spell->getConjureId(),
				'reagent' => $spell->getReagentId(),
				'item_id' => 0,
				'hide' => $spell->isEnabled() ? 0 : 1,
				'category' => 0,
			], $show);
		}

		$instantlist = self::$spellsList->getInstantsList();
		if($show) {
			echo "<h3>Instant:</h3>";
		}

		foreach($instantlist as $spellname) {
			$spell = self::$spellsList->getInstant($spellname);
			$name = $spell->getName();
			$words = $spell->getWords();
			if(strpos($words, '#') !== false) {
				continue;
			}

			self::storeSpell([
				'name' => $name,
				'words' => $words,
				'type' => 1,
				'mana' => $spell->getMana(),
				'level' => $spell->getLevel(),
				'maglevel' => $spell->getMagicLevel(),
				'soul' => $spell->getSoul(),
				'premium' => $spell->isPremium() ? 1 : 0,
				'vocations' => $spell->getVocations(),
				'conjure_count' => 0,
				'conjure_id' => 0,
				'reagent' => 0,
				'item_id' => 0,
				'hide' => $spell->isEnabled() ? 0 : 1,
				'category' => 0,
			], $show);
		}

		$runeslist = self::$spellsList->getRunesList();
		if($show) {
			echo "<h3>Runes:</h3>";
		}

		foreach($runeslist as $spellname) {
			$spell = self::$spellsList->getRune($spellname);

			self::storeSpell([
				'name' => $spell->getName() . ' Rune',
				'words' => $spell->getWords(),
				'type' => 3,
				'mana' => $spell->getMana(),
				'level' => $spell->getLevel(),
				'maglevel' => $spell->getMagicLevel(),
				'soul' => $spell->getSoul(),
				'premium' => $spell->isPremium() ? 1 : 0,
				'vocations' => $spell->getVocations(),
				'conjure_count' => 0,
				'conjure_id' => 0,
				'reagent' => 0,
				'item_id' => $spell->getID(),
				'hide' => $spell->isEnabled() ? 0 : 1,
				'category' => 0,
			], $show);
		}

		self::$lastError = '';
		return true;
	}

	private static function loadFromLua($show) {
		$spellDirs = self::resolveLuaDirectories('data/scripts/spells');
		$runeDirs = self::resolveLuaDirectories('data/scripts/runes');
		$vocationMap = self::buildVocationMap();
		$loaded = 0;

		foreach ($spellDirs as $dir) {
			$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
			foreach ($files as $file) {
				if (!$file->isFile() || strtolower($file->getExtension()) !== 'lua' || str_starts_with($file->getFilename(), '#')) {
					continue;
				}

				$spell = self::parseLuaSpell(file_get_contents($file->getPathname()), $file->getPathname(), $vocationMap, false);
				if ($spell === null) {
					continue;
				}

				self::storeSpell($spell, $show);
				$loaded++;
			}
		}

		foreach ($runeDirs as $dir) {
			$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
			foreach ($files as $file) {
				if (!$file->isFile() || strtolower($file->getExtension()) !== 'lua' || str_starts_with($file->getFilename(), '#')) {
					continue;
				}

				$spell = self::parseLuaSpell(file_get_contents($file->getPathname()), $file->getPathname(), $vocationMap, true);
				if ($spell === null) {
					continue;
				}

				self::storeSpell($spell, $show);
				$loaded++;
			}
		}

		if ($loaded === 0) {
			self::$lastError = 'Cannot load spells from Lua datapack.';
			return false;
		}

		self::$lastError = '';
		return true;
	}

	private static function parseLuaSpell($content, $path, array $vocationMap, $forceRune) {
		if (!preg_match('/local\s+\w+\s*=\s*Spell\("([^"]+)"\)/i', $content, $kindMatch)) {
			return null;
		}

		$kind = strtolower($kindMatch[1]);
		$type = $forceRune || $kind === 'rune' ? 3 : (str_contains(str_replace('\\', '/', $path), '/conjuring/') ? 2 : 1);
		$name = self::extractStringCall($content, 'name');
		if ($name === null) {
			return null;
		}

		$conjure = self::extractConjureItem($content);
		$itemId = $type === 3 ? self::extractNumberCall($content, 'runeId', 0) : 0;
		$words = self::extractStringCall($content, 'words') ?? '';
		$vocations = self::extractVocations($content, $vocationMap);
		$group = self::extractGroup($content);

		return [
			'spell' => basename($path, '.lua'),
			'name' => self::normalizeSpellName($name, $type, $itemId),
			'words' => $words,
			'category' => self::loadGroup($group),
			'type' => $type,
			'level' => self::extractNumberCall($content, 'level', 0),
			'maglevel' => self::extractNumberCall($content, 'magicLevel', 0),
			'mana' => self::extractNumberCall($content, 'mana', 0),
			'soul' => self::extractNumberCall($content, 'soul', 0),
			'conjure_id' => $type === 2 ? ($conjure['item_id'] ?? 0) : 0,
			'conjure_count' => $type === 2 ? ($conjure['count'] ?? 0) : 0,
			'reagent' => $type === 2 ? ($conjure['reagent'] ?? 0) : 0,
			'item_id' => $itemId,
			'premium' => 0,
			'vocations' => $vocations,
			'hide' => 0,
		];
	}

	private static function normalizeSpellName($name, $type, $itemId) {
		if ($type !== 3) {
			return $name;
		}

		return preg_match('/ rune$/i', $name) ? $name : ucwords($name);
	}

	private static function storeSpell(array $spell, $show) {
		try {
			Spell::create([
				'spell' => $spell['spell'] ?? '',
				'name' => $spell['name'],
				'words' => $spell['words'],
				'category' => $spell['category'] ?? 0,
				'type' => $spell['type'],
				'level' => $spell['level'],
				'maglevel' => $spell['maglevel'],
				'mana' => $spell['mana'],
				'soul' => $spell['soul'],
				'conjure_id' => $spell['conjure_id'],
				'conjure_count' => $spell['conjure_count'],
				'reagent' => $spell['reagent'],
				'item_id' => $spell['item_id'],
				'premium' => $spell['premium'],
				'vocations' => json_encode($spell['vocations']),
				'hide' => $spell['hide']
			]);

			if($show) {
				success('Added: ' . $spell['name'] . '<br/>');
			}
		}
		catch(\PDOException $error) {
			if($show) {
				warning('Error while adding spell (' . $spell['name'] . '): ' . $error->getMessage());
			}
		}
	}

	private static function resolveLuaDirectories($suffix) {
		$basePath = dirname(rtrim(config('data_path'), '/'));
		$candidates = [
			$basePath . '/' . $suffix,
			$basePath . '/data-global/' . basename($suffix),
		];

		$found = [];
		foreach ($candidates as $candidate) {
			if (is_dir($candidate)) {
				$found[] = $candidate;
			}
		}

		return array_values(array_unique($found));
	}

	private static function buildVocationMap() {
		global $config;

		$map = [];
		foreach ($config['vocations'] as $id => $name) {
			$map[mb_strtolower($name)] = (int)$id;
		}

		return $map;
	}

	private static function extractStringCall($content, $method) {
		if (preg_match('/:\s*' . preg_quote($method, '/') . '\("((?:[^"\\\\]|\\\\.)*)"\)/i', $content, $match)) {
			return stripcslashes($match[1]);
		}

		return null;
	}

	private static function extractNumberCall($content, $method, $default = 0) {
		if (preg_match('/:\s*' . preg_quote($method, '/') . '\((-?\d+)/i', $content, $match)) {
			return (int)$match[1];
		}

		return $default;
	}

	private static function extractVocations($content, array $vocationMap) {
		if (!preg_match('/:\s*vocation\(([^)]*)\)/is', $content, $match)) {
			return [];
		}

		preg_match_all('/"([^"]+)"/', $match[1], $entries);
		$vocations = [];
		foreach ($entries[1] as $entry) {
			$parts = explode(';', $entry);
			$name = mb_strtolower(trim($parts[0]));
			if ($name === 'none') {
				return [];
			}

			if (isset($vocationMap[$name])) {
				$vocations[] = $vocationMap[$name];
			}
		}

		return array_values(array_unique($vocations));
	}

	private static function extractGroup($content) {
		if (!preg_match('/:\s*group\(([^)]*)\)/is', $content, $match)) {
			return '';
		}

		if (preg_match('/"([^"]+)"/', $match[1], $value)) {
			return strtolower($value[1]);
		}

		return '';
	}

	private static function extractConjureItem($content) {
		if (!preg_match('/conjureItem\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/i', $content, $match)) {
			return null;
		}

		return [
			'reagent' => (int)$match[1],
			'item_id' => (int)$match[2],
			'count' => (int)$match[3],
		];
	}

	public static function getSpellsList() {
		return self::$spellsList;
	}

	public static function getLastError() {
		return self::$lastError;
	}
}
