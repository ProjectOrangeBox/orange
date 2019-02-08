<?php
/**
 * Orange
 *
 * An open source extensions for CodeIgniter 3.x
 *
 * This content is released under the MIT License (MIT)
 * Copyright (c) 2014 - 2019, Project Orange Box
 * 
 * Some parts copyright CodeIgniter 4.x MIT
 * @package	CodeIgniter
 * @author	CodeIgniter Dev Team
 * @copyright	2014-2018 British Columbia Institute of Technology (https://bcit.ca/)
 * @license	https://opensource.org/licenses/MIT	MIT License
 * @link	https://codeigniter.com
 */

/**
 * Manage Events in your Application
 *
 * Collection of errors with multiple grouping as well as displaying of errors.
 *
 * @package CodeIgniter / Orange
 * @author Don Myers
 * @copyright 2019
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 * @version v2.0
 * @filesource
 *
 */
class Event {
	const PRIORITY_LOWEST = 10;
	const PRIORITY_LOW = 20;
	const PRIORITY_NORMAL = 50;
	const PRIORITY_HIGH = 80;
	const PRIORITY_HIGHEST = 90;

	/**
	 * storage for events
	 *
	 * @var array
	 */
	 protected $listeners = [];

	/**
	 * Register a listener
	 *
	 * #### Example
	 * ```php
	 * register('open.page',function(&$var1) { echo "hello $var1"; },EVENT::PRIORITY_HIGH);
	 * ```
	 * @access public
	 *
	 * @param string $name name of the event we want to listen for
	 * @param callable $callable function to call if the event if triggered
	 * @param int $priority the priority this listener has against other listeners
	 *
	 * @return Event
	 *
	 */
	public function register(string $name,$callable,int $priority = EVENT::PRIORITY_NORMAL) : Event
	{
		/* if they pass in a array treat it as a name=>closure pair */
		if (is_array($name)) {
			foreach ($name as $n) {
				$this->register($n, $callable, $priority);
			}
			return $this;
		}

		/* clean up the name */
		$name = $this->_normalize_name($name);

		/* log a debug event */
		log_message('debug', 'event::register::'.$name);

		$this->listeners[$name][0] = !isset($this->listeners[$name]); // Sorted?
		$this->listeners[$name][1][] = $priority;
		$this->listeners[$name][2][] = $callable;

		/* allow chaining */
		return $this;
	}

	/**
	 * Trigger an event
	 *
	 * #### Example
	 * ```php
	 * trigger('open.page',$var1);
	 * ```
	 * @param string $name event to trigger
	 * @param mixed ...$arguments pass by reference
	 *
	 * @return Event
	 *
	 * @access public
	 *
	 */
	public function trigger(string $name,&...$arguments) : Event
	{
		/* clean up the name */
		$name = $this->_normalize_name($name);

		/* log a debug event */
		log_message('debug','event::trigger::'.$name);

		/* do we even have any events with this name? */
		if (isset($this->listeners[$name])) {
			foreach ($this->_listeners($name) as $listener) {
				if ($listener(...$arguments) === false) {
					break;
				}
			}
		}

		/* allow chaining */
		return $this;
	}

	/**
	 *
	 * Is there any listeners for a certain event?
	 *
	 * #### Example
	 * ```php
	 * $bool = ci('event')->has('page.load');
	 * ```
	 * @access public
	 *
	 * @param string $name event to search for
	 *
	 * @return bool
	 *
	 */
	public function has(string $name) : bool
	{
		/* clean up the name */
		$name = $this->_normalize_name($name);

		return isset($this->listeners[$name]);
	}

	/**
	 *
	 * Return an array of all of the event names
	 *
	 * #### Example
	 * ```php
	 * $triggers = ci('event')->events();
	 * ```
	 * @access public
	 *
	 * @return array
	 *
	 */
	public function events() : array
	{
		return array_keys($this->listeners);
	}

	/**
	 *
	 * Return the number of events for a certain name
	 *
	 * #### Example
	 * ```php
	 * $listeners = ci('event')->count('database.user_model');
	 * ```
	 * @access public
	 *
	 * @param string $name
	 *
	 * @return int
	 *
	 */
	public function count(string $name) : int
	{
		/* clean up the name */
		$name = $this->_normalize_name($name);

		return (isset($this->listeners[$name])) ? count($this->listeners[$name][1]) : 0;
	}

	/**
	 *
	 * Removes a single listener from an event.
	 * this doesn't work for closures!
	 *
	 * @access public
	 *
	 * @param string $name
	 * @param $listener
	 *
	 * @return bool
	 *
	 */
	public function unregister(string $name, $listener) : bool
	{
		/* clean up the name */
		$name = $this->_normalize_name($name);

		$removed = false;

		if (!($listener instanceof Closure)) {
			if (isset($this->listeners[$name])) {
				foreach ($this->listeners[$name][2] as $index=>$check) {
					if ($check === $listener) {
						unset($this->listeners[$name][1][$index]);
						unset($this->listeners[$name][2][$index]);

						$removed = true;
					}
				}
			}
		}

		return $removed;
	}

	/**
	 *
	 * Removes all listeners.
	 *
	 * If the event_name is specified, only listeners for that event will be
	 * removed, otherwise all listeners for all events are removed.
	 *
	 * @access public
	 *
	 * @param string $name
	 *
	 * @return \Event
	 *
	 */
	public function unregister_all(string $name = '') : Event
	{
		/* clean up the name */
		$name = $this->_normalize_name($name);

		if (!empty($name)) {
			unset($this->listeners[$name]);
		} else {
			$this->listeners = [];
		}

		/* allow chaining */
		return $this;
	}

	/**
	 *
	 * Normalize the event name
	 *
	 * @access protected
	 *
	 * @param string $name
	 *
	 * @return string
	 *
	 */
	protected function _normalize_name(string $name) : string
	{
		return trim(preg_replace('/[^a-z0-9]+/','.',strtolower($name)),'.');
	}

	/**
	 *
	 * Do the actual sorting
	 *
	 * @access protected
	 *
	 * @param string $name
	 *
	 * @return array
	 *
	 */
	protected function _listeners(string $name) : array
	{
		$name = $this->_normalize_name($name);
		$listeners = [];

		if (isset($this->listeners[$name])) {
			/* The list is not sorted */
			if (!$this->listeners[$name][0]) {
				/* Sort it! */
				array_multisort($this->listeners[$name][1],SORT_DESC,SORT_NUMERIC,$this->listeners[$name][2]);

				/* Mark it as sorted already! */
				$this->listeners[$name][0] = true;
			}

			$listeners = $this->listeners[$name][2];
		}

		return $listeners;
	}

} /* end class */
