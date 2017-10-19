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

	/**
	 * Constructor
	 * @private
	 * @author Don Myers
	 * @param object &$controller current controller reference
	 */
	public function __construct(&$controller) {
		$this->controller = &$controller;
	}

	/**
	 * wrapper - extend this
	 * @author Don Myers
	 */
	public function run() {
	}

	/**
	 * __get magic
	 *
	 * Allows Middleware to access CI's loaded classes using the same
	 * syntax as controllers.
	 *
	 * @author Don Myers
	 * @param	string	$key
	 */
	public function __get($key) {
		return get_instance()->$key;
	}

} /* end class */