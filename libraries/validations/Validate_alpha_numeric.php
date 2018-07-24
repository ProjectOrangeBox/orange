<?php
/**
 * Validate_alpha_numeric
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
 * @show may only contain alpha-numeric characters.
 *
 */
class Validate_alpha_numeric extends Validate_base {
	public function validate(&$field, $options) {
		$this->error_string = '%s may only contain alpha-numeric characters.';
		return ctype_alnum((string) $field);
	}
}
