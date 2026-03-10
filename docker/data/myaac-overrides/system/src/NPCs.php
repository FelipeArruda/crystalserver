<?php
/**
 * NPC class
 *
 * @package   MyAAC
 * @author    Gesior <jerzyskalski@wp.pl>
 * @author    Slawkens <slawkens@gmail.com>
 * @author    Lee
 * @copyright 2021 MyAAC
 * @link      https://my-aac.org
 */

namespace MyAAC;

use MyAAC\Cache\PHP as CachePHP;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class NPCs
{
	public static $npcs;

	public static function loadFromXML($show = false)
	{
		$npcPath = config('data_path') . 'npc/';
		if (file_exists($npcPath) && self::loadLegacyXml($npcPath)) {
			return true;
		}

		$luaPath = self::resolveLuaNpcDirectory();
		if ($luaPath === null) {
			return false;
		}

		$npcs = [];
		$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($luaPath));
		foreach ($files as $file) {
			if (!$file->isFile() || strtolower($file->getExtension()) !== 'lua') {
				continue;
			}

			$content = file_get_contents($file->getPathname());
			if (preg_match('/Game\.createNpcType\("([^"]+)"\)/', $content, $match)) {
				$npcs[] = strtolower($match[1]);
				continue;
			}

			if (preg_match('/local\s+internalNpcName\s*=\s*"([^"]+)"/', $content, $match)) {
				$npcs[] = strtolower($match[1]);
			}
		}

		$npcs = array_values(array_unique($npcs));
		if (count($npcs) === 0) {
			return false;
		}

		self::cacheNpcs($npcs);
		return true;
	}

	private static function loadLegacyXml($npcPath)
	{
		$npcs = [];
		$xml = new \DOMDocument();
		foreach (preg_grep('~\.(xml)$~i', scandir($npcPath)) as $npc) {
			$xml->load($npcPath . $npc);
			if ($xml) {
				$element = $xml->getElementsByTagName('npc')->item(0);
				if (isset($element)) {
					$name = $element->getAttribute('name');
					if (!empty($name) && !in_array($name, $npcs, true)) {
						$npcs[] = strtolower($name);
					}
				}
			}
		}

		if (count($npcs) === 0) {
			return false;
		}

		self::cacheNpcs($npcs);
		return true;
	}

	private static function cacheNpcs(array $npcs)
	{
		$cache_php = new CachePHP(config('cache_prefix'), CACHE . 'persistent/');
		$cache_php->set('npcs', $npcs, 5 * 365 * 24 * 60 * 60);
	}

	private static function resolveLuaNpcDirectory()
	{
		$basePath = rtrim(config('data_path'), '/');
		$candidates = [
			$basePath . '/npc',
			dirname($basePath) . '/data-global/npc',
			dirname($basePath) . '/data-crystal/npc',
		];

		foreach ($candidates as $candidate) {
			if (is_dir($candidate)) {
				return $candidate;
			}
		}

		return null;
	}

	public static function load()
	{
		if (self::$npcs) {
			return;
		}

		$cache_php = new CachePHP(config('cache_prefix'), CACHE . 'persistent/');
		self::$npcs = $cache_php->get('npcs');
	}
}
