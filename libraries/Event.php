<?php
/* some parts copyright CodeIgniter others from the original ProjectOrangeBox Event library */

/**
 * CodeIgniter
 *
 * An open source application development framework for PHP
 *
 * This content is released under the MIT License (MIT)
 *
 * Copyright (c) 2014-2018 British Columbia Institute of Technology
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @package	CodeIgniter
 * @author	CodeIgniter Dev Team
 * @copyright	2014-2018 British Columbia Institute of Technology (https://bcit.ca/)
 * @license	https://opensource.org/licenses/MIT	MIT License
 * @link	https://codeigniter.com
 * @since	Version 3.0.0
 * @filesource
 */

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

/* Follows Linux Priority negative values are higher priority and positive values are lower priority */
define('EVENT_PRIORITY_LOW', 100);
define('EVENT_PRIORITY_NORMAL', 0);
define('EVENT_PRIORITY_HIGH', −100);

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
	 * @param $callable - function to call if the event if triggered
	 * @param $priority - integer - the priority this listener has against other listeners
	 *										A priority of −100 is the highest priority and 100 is the lowest priority.
	 *
	 * @return $this
	 *
	 * @access public
	 * @example register('open.page',function(&$var1) { echo "hello $var1"; },-100);
	 */
	public function register($name, $callable, $priority = EVENT_PRIORITY_NORMAL) {
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
	 * @param $name - string - event to trigger
	 * @param ...$arguments - mixed - pass by reference
	 *
	 * @return $this
	 *
	 * @access public
	 * @example trigger('open.page',$var1);
	 */
	public function trigger($name,&...$arguments) {
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

		return isset($this->listeners[$name]);
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
		$name = $this->_normalize_name($name);

		return (isset($this->listeners[$name])) ? count($this->listeners[$name][1]) : 0;
	}

	/**
	 * Removes a single listener from an event.
	 * NOTE: this doesn't work for closures!
	 *
	 * @param          $name
	 * @param callable $listener
	 *
	 * @return boolean
	 */
	public function unregister($name, $listener) {
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
	 * Removes all listeners.
	 *
	 * If the event_name is specified, only listeners for that event will be
	 * removed, otherwise all listeners for all events are removed.
	 *
	 * @param $name
	 *
	 * @return $this
	 */
	public static function unregister_all($name='') 	{
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
	 * Normalize the event name
	 *
	 * @param $name string
	 *
	 * @return string
	 *
	 * @access protected
	 */
	protected function _normalize_name($name) {
		return trim(preg_replace('/[^a-z0-9]+/','.',strtolower($name)),'.');
	}
	
	/**
	 * Do the actual sorting
	 *
	 * @param $name string
	 *
	 * @return array
	 *
	 * @access protected
	 */
	protected function _listeners($name) {
		$name = $this->_normalize_name($name);
		$listeners = [];

		if (isset($this->listeners[$name])) {
			/* The list is not sorted */
			if (!$this->listeners[$name][0]) {
				/* Sort it! */
				array_multisort($this->listeners[$name][1], SORT_NUMERIC, $this->listeners[$name][2]);

				/* Mark it as sorted already! */
				$this->listeners[$name][0] = true;
			}

			$listeners = $this->listeners[$name][2];
		}

		return $listeners;
	}

} /* end class */
