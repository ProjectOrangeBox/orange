<?php
/*
 * Orange Framework Extension
 *
 * @package	CodeIgniter / Orange
 * @author Don Myers
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 *
 * required
 * core:
 * libraries:
 * models:
 * helpers:
 * functions:
 *
 */

class Event {
	protected static $listeners = [];

	public static function register($name, $closure, $priority = 0) {

		if (is_array($name)) {
			foreach ($name as $n) {
				self::register($n, $closure, $priority = 0);
			}
			return;
		}

		$name = self::_normalize_name($name);

		log_message('debug', 'event::register::'.$name);

		self::$listeners[$name][$priority][] = $closure;
	}

	public static function trigger($name, &$a1 = null, &$a2 = null, &$a3 = null, &$a4 = null, &$a5 = null, &$a6 = null, &$a7 = null, &$a8 = null) {
		$name = self::_normalize_name($name);

		log_message('debug', 'event::trigger::'.$name);

		if (self::has($name)) {
			$events = self::$listeners[$name];

			ksort($events);

			foreach ($events as $priority) {
				foreach ($priority as $event) {

					if ($event($a1, $a2, $a3, $a4, $a5, $a6, $a7, $a8) === false) {
						break 2;
					}
				}
			}
		}
	}

	public static function has($name) {
		$name = self::_normalize_name($name);

		return (isset(self::$listeners[$name]) && count(self::$listeners[$name]) > 0);
	}

	public static function events() {
		return array_keys(self::$listeners);
	}

	public static function count($name) {
		$name = self::_normalize_name($name);

		return count(self::$listeners[$name]);
	}

	protected static function _normalize_name($name) {
		return str_replace('_','.',strtolower(trim(preg_replace('#\W+#', '_', $name), '_')));
	}

} /* end file */