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

	/*
	No External Dependencies

	priorities are set using the unix nice levels
	http://www.computerhope.com/unix/unice.htm

	In short - the lower the number the higher the priority
	100 High
	0	 Normal
	-100 Low
	 */

	/**
	 * Register a new event
	 *
	 * This event will be called if and when the trigger matching it is called
	 *
	 * @param	string $name name of the event
	 * @param closure $closure event to happen as a PHP Anonymous function
	 * @param priority $priority order in which matching events should trigger priorities are set using the unix nice levels
	 *
	 * @return Event $this allow chaining
	 */
	public static function register($name, $closure, $priority = 0) {
		/* if they want to attach the same event to multiple triggers */
		if (is_array($name)) {
			foreach ($name as $n) {
				self::register($n, $closure, $priority = 0);
			}

			return $this;
		}

		$name = self::_normalize_name($name);

		log_message('debug', 'event::register::' . $name);

		self::$listeners[$name][$priority][] = $closure;
	}

	/**
	 * Trigger a event
	 *
	 * Triggers a event optionally passing between 1 and 8 variables by reference
	 * if a event returns false this stop the processing of other events in the priority chain
	 *
	 * @param	string $name	name of the event
	 * @params between 1 - 8 variables passed by reference an event changes them directly
	 *
	 * @return Event $this allow chaining
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
	 * Are there any events matching this name
	 *
	 * @return boolean
	 */
	public static function has($name) {
		$name = self::_normalize_name($name);

		return (isset(self::$listeners[$name]) && count(self::$listeners[$name]) > 0);
	}

	/**
	 * return array of events trigger names
	 *
	 * @return array
	 */
	public static function events() {
		return array_keys(self::$listeners);
	}

	/**
	 * return count of attached events to a trigger
	 *
	 * @return integer
	 */
	public static function count($name) {
		$name = self::_normalize_name($name);

		return count(self::$listeners[$name]);
	}

	/**
	 * normalize the name
	 *
	 * @param	string $name name of the event
	 * @return string
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