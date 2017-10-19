<?php
/**
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
 * Static Library
 */

class Event {
	protected static $listeners = [];

	/**
	 * No External Dependencies
   *
	 * priorities are set using the unix nice levels
	 * http://www.computerhope.com/unix/unice.htm
	 *
	 * In short - the lower the number the higher the priority
	 * 100 High
	 * 0	 Normal
	 * -100 Low
	 */

	/**
	 * register a event handler
	 * @author Don Myers
	 * @param  string $name event to listen for
	 * @param  closure $closure function to call on event trigger
	 * @param  int [$priority = 0] priority level of the event
	 */
	public static function register($name, $closure, $priority = 0) {
		/* if they want to attach the same event to multiple triggers */
		if (is_array($name)) {
			foreach ($name as $n) {
				self::register($n, $closure, $priority = 0);
			}

			return;
		}

		$name = self::_normalize_name($name);

		log_message('debug', 'event::register::' . $name);

		self::$listeners[$name][$priority][] = $closure;
	}

	/**
	 * trigger a event
	 * @author Don Myers
	 * @param string $name event to trigger
	 * @param mixed [&$a1 = null] argument 1
	 * @param mixed [&$a2 = null] argument 2
	 * @param mixed [&$a3 = null] argument 3
	 * @param mixed [&$a4 = null] argument 4
	 * @param mixed [&$a5 = null] argument 5
	 * @param mixed [&$a6 = null] argument 6
	 * @param mixed [&$a7 = null] argument 7
	 * @param mixed [&$a8 = null] argument 8
	 */
	public static function trigger($name, &$a1 = null, &$a2 = null, &$a3 = null, &$a4 = null, &$a5 = null, &$a6 = null, &$a7 = null, &$a8 = null) {
		$name = self::_normalize_name($name);

		log_message('debug', 'event::trigger::' . $name);

		/* are there any events even attach to the trigger? */
		if (self::has($name)) {
			/* let's get them */
			$events = self::$listeners[$name];

			/* Sort an array by key (priority) */
			ksort($events);

			foreach ($events as $priority) {
				foreach ($priority as $event) {
					/* call closure - stop on false */
					if ($event($a1, $a2, $a3, $a4, $a5, $a6, $a7, $a8) === false) {
						break 2;
					}
				}
			}
		}
	}

	/**
	 * to check if a event has any listeners
	 * @author Don Myers
	 * @param  string $name event to check
	 * @return bool if there are any listeners for this event
	 */
	public static function has($name) {
		$name = self::_normalize_name($name);

		return (isset(self::$listeners[$name]) && count(self::$listeners[$name]) > 0);
	}

	/**
	 * return a array of listeners for a certain event
	 * @author Don Myers
	 * @return array list of event (this function is more for debugging purpose)
	 */
	public static function events() {
		return array_keys(self::$listeners);
	}

	/**
	 * Get the count of the listeners attached
	 * @author Don Myers
	 * @param  string $name event to get the count of
	 * @return int how many listeners are registered
	 */
	public static function count($name) {
		$name = self::_normalize_name($name);

		return count(self::$listeners[$name]);
	}

	/**
	 * normalize a event trigger name
	 * @private
	 * @author Don Myers
	 * @param  string $name event trigger name
	 * @return string cleaned name
	 */
	protected static function _normalize_name($name) {
		/*
		look for any sequence of non-alphanumeric characters and replaces it with a single '.'.
		using + you also avoid having two consecutive .'s in your output.
		we also lowercase and trim any leading and trailing .'s
		*/

		return trim(preg_replace('/[^a-z0-9]+/', '.', strtolower($name)), '.');
	}

} /* end Event */