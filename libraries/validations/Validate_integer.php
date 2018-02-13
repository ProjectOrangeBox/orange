<?php
/**
 * Validate_integer
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
class Validate_integer extends Validate_base {
	public function validate(&$field, $options) {
		$this->error_string = '%s is not a integer.';
		return (bool) preg_match('/^[\-+]?[0-9]+$/', $field);
	}
}
