<?php
/**
 * Validate_is_natural
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
class Validate_is_natural extends Validate_base {
	public function validate(&$field, $options) {
		$this->error_string = '%s must only contain digits.';
		return ctype_digit((string) $field);
	}
}
