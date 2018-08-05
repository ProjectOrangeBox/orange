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
 * required Nothing!
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
		$name = $this->_normalize_name($name);

		/* log a debug event */
		log_message('debug','event::register::'.$name);

		/* save the listener */
		if (!isset($this->listeners[$name])) {
			$this->listeners[$name] = new SplPriorityQueue();
		}
		
		/* flip the priority because we are using UNIX priority not SplPriorityQueue format */
		$this->listeners[$name]->insert($closure,-$priority);

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
		$name = $this->_normalize_name($name);

		/* log a debug event */
		log_message('debug', 'event::trigger::'.$name);

		/* do we even have any events with this name? */
		if ($this->has($name)) {
			foreach(clone $this->listeners[$name] as $event) {
				if ($event($a1, $a2, $a3, $a4, $a5, $a6, $a7, $a8) === false) {
					/* jump out of foreach loops on return of false */
					break;
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
		$name = $this->_normalize_name($name);

		return (isset($this->listeners[$name]) && !$this->listeners[$name]->isEmpty());
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
		return count($this->listeners[$this->_normalize_name($name)]);
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
	protected function _normalize_name($name) {
		return str_replace('_','.',strtolower(trim(preg_replace('#\W+#', '_', $name), '_')));
	}

} /* end class */
