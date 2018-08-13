<?php
/**
 * Event
 * Manage Events in your Application
 *
 * @package CodeIgniter / Orange
 * @author Don Myers
 * @copyright 2018
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 * @version 2.0
 *
 * required
 * core:
 * libraries:
 * models:
 * helpers:
 * functions: log_message
 *
 * @show Event handler
 */
class Event {
	/**
	 * storage for all listeners
	 *
	 * @var array
	 */
	protected $listeners = [];

	/**
	 * Register a listener
	 *
	 * @param $name - string - name of the event we want to listen for
	 * @param $closure - function to call if the event if triggered
	 * @param $priority - integer - the priority this listener has against other listeners
	 *										A priority of −100 is the highest priority and 100 is the lowest priority.
	 *
	 * @return $this
	 *
   * @access public
	 * @example register('open.page',function(&$var1) { echo "hello $var1"; },-100);
	 */
	public function register($name, $closure, $priority = 0) {
		/* if they pass in a array treat it as a name=>closure pair */
		if (is_array($name)) {
			foreach ($name as $n) {
				$this->register($n, $closure, $priority);
			}
			return $this;
		}

		/* clean up the name */
		$this->_normalize_name($name);

		/* log a debug event */
		log_message('debug', 'event::register::'.$name);

		/* save the listener */
		$this->listeners[$name][$priority][] = $closure;

		/* allow chaining */
		return $this;
	}

	/**
	 * Trigger an event
	 *
	 * @param $name - string - event to trigger
	 * @param $a1,$a2,$a3,...,$a8 - mixed - variables to pass by reference
	 *
	 * @return $this
	 *
   * @access public
	 * @example trigger('open.page',$var1);
	 */
	public function trigger($name, &$a1 = null, &$a2 = null, &$a3 = null, &$a4 = null, &$a5 = null, &$a6 = null, &$a7 = null, &$a8 = null) {
		/* clean up the name */
		$this->_normalize_name($name);

		/* log a debug event */
		log_message('debug','event::trigger::'.$name);

		/* do we even have any events with this name? */
		if ($this->has($name)) {

			/* let's get them all then */
			$events = $this->listeners[$name];

			/* sort the keys (priority) */
			ksort($events);

			/* call each event */
			foreach ($events as $priority) {
				foreach ($priority as $event) {
					/* if false is returned from the event then do not process the rest of the events */
					if ($event($a1, $a2, $a3, $a4, $a5, $a6, $a7, $a8) === false) {
						/* jump out of both foreach loops */
						break 2;
					}
				}
			}
		}

		/* allow chaining */
		return $this;
	}

	/**
	 * Is there any listeners for a certain event?
	 *
	 * @param $name - string - event to search for
	 *
	 * @return boolean
	 *
   * @access public
	 * @example has('page.load');
	 */
	public function has($name) {
		/* clean up the name */
		$this->_normalize_name($name);

		return (isset($this->listeners[$name]) && count($this->listeners[$name]) > 0);
	}

	/**
	 * Return an array of all of the event names
	 *
	 * @return array
	 *
   * @access public
	 */
	public function events() {
		return array_keys($this->listeners);
	}

	/**
	 * Return the number of events for a certain name
	 *
	 * @param $name - string - event to search for
	 *
	 * @return integer
	 *
   * @access public
	 */
	public function count($name) {
		/* clean up the name */
		$this->_normalize_name($name);

		return count($this->listeners[$name]);
	}

	/**
	 * Normalize the event name
	 *
	 * @param $name string
	 *
	 * @return string
	 *
   * @access protected
	 */
	protected function _normalize_name(&$name) {
		$name = trim(preg_replace('/[^a-z0-9]+/','.',strtolower($name)),'.');
	}

} /* end class */
