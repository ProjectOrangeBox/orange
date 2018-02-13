<?php
/**
 * Validate_numeric
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
class Validate_numeric extends Validate_base {
	public function validate(&$field, $options) {
		$this->error_string = '%s must contain only numeric characters.';
		return (bool) preg_match('/^[\-+]?[0-9]*\.?[0-9]+$/', $field);
	}
}
