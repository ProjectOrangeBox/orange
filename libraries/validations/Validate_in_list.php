<?php
/**
 * Validate_in_list
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
class Validate_in_list extends Validate_base {
	public function validate(&$field, $options) {
		$this->error_string = '%s must contain one of the available selections.';
		$types = ($options) ? $options : '';
		return (in_array($field, explode(',', $types)));
	}
}
