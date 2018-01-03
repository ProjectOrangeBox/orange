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

class Middleware_base {
	protected $controller;

	public function __construct(&$controller) {
		$this->controller = &$controller;
	}

	public function run() {}

	public function __get($key) {
		return get_instance()->$key;
	}

} /* end file */
