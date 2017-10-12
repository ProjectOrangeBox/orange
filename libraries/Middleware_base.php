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
 * Base Class
 */

class Middleware_base {
	protected $controller;

	public function __construct(&$controller) {
		$this->controller = &$controller;
	}

	/**
	 * run function.
	 *
	 * @access public
	 * @return void
	 */
	public function run() {
	}

	/**
	 * __get magic
	 *
	 * Allows Middleware to access CI's loaded classes using the same
	 * syntax as controllers.
	 *
	 * @param	string	$key
	 */
	public function __get($key) {
		return get_instance()->$key;
	}

} /* end class */