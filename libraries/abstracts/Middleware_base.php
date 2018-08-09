<?php
/**
* Orange Framework Extension
*
* This content is released under the MIT License (MIT)
*
* @package	CodeIgniter / Orange
* @author	Don Myers
* @license http://opensource.org/licenses/MIT MIT License
* @link	https://github.com/ProjectOrangeBox
*
* required
* core:
* libraries:
* models:
* helpers:
*
*/
abstract class Middleware_base {
	protected $ci;

	public function __construct(&$ci) {
		$this->ci = &$ci;
	}

	public function request() {
	}

	public function responds($output) {
		return $output;
	}

	/* allow $this to work in middleware */
	public function __get($name) {
		return $this->ci->$name;
	}

}
