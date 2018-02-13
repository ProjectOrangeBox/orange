<?php
/**
 * Validate_less_than
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
class Validate_less_than extends Validate_base {
	public function validate(&$field, $options) {
		$this->error_string = '%s must contain a number less than %s.';
		if (!is_numeric($field)) {
			return false;
		}
		return is_numeric($field) ? ($field < $options) : false;
	}
}
