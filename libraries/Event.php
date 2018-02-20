<?php
/**
 * Event
 * Insert description here
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
 * functions:
 *
 */
class Event {
	/**
	 * track if the combined cached configuration has been loaded
	 *
	 * @var boolean
	 */
	protected $listeners = [];

	/**
	 * register
	 * Insert description here
	 *
	 * @param $name
	 * @param $closure
	 * @param $priority
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function register($name, $closure, $priority = 0) {
		if (is_array($name)) {
			foreach ($name as $n) {
				$this->register($n, $closure, $priority = 0);
			}
			return;
		}

		$name = $this->_normalize_name($name);
		log_message('debug', 'event::register::'.$name);
		$this->listeners[$name][$priority][] = $closure;

		return $this;
	}

	/**
	 * trigger
	 * Insert description here
	 *
	 * @param $name
	 * @param $a1
	 * @param $a2
	 * @param $a3
	 * @param $a4
	 * @param $a5
	 * @param $a6
	 * @param $a7
	 * @param $a8
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function trigger($name, &$a1 = null, &$a2 = null, &$a3 = null, &$a4 = null, &$a5 = null, &$a6 = null, &$a7 = null, &$a8 = null) {
		$name = $this->_normalize_name($name);
		log_message('debug', 'event::trigger::'.$name);

		if ($this->has($name)) {
			$events = $this->listeners[$name];
			ksort($events);
			foreach ($events as $priority) {
				foreach ($priority as $event) {
					if ($event($a1, $a2, $a3, $a4, $a5, $a6, $a7, $a8) === false) {
						break 2;
					}
				}
			}
		}

		return $this;
	}

	/**
	 * has
	 * Insert description here
	 *
	 * @param $name
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function has($name) {
		$name = $this->_normalize_name($name);

		return (isset($this->listeners[$name]) && count($this->listeners[$name]) > 0);
	}

	/**
	 * events
	 * Insert description here
	 *
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function events() {
		return array_keys($this->listeners);
	}

	/**
	 * count
	 * Insert description here
	 *
	 * @param $name
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function count($name) {
		$name = $this->_normalize_name($name);

		return count($this->listeners[$name]);
	}

	/**
	 * _normalize_name
	 * Insert description here
	 *
	 * @param $name
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	protected function _normalize_name($name) {
		return str_replace('_','.',strtolower(trim(preg_replace('#\W+#', '_', $name), '_')));
	}

} /* end class */
