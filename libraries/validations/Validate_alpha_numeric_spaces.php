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

class Validate_alpha_numeric_spaces extends Validate_base {
	public function validate(&$field, $options) {
		$this->error_string = '%s may only contain alpha-numeric characters and spaces.';
		return (bool) preg_match('/^[A-Z0-9 ]+$/i', $field);
	}
} /* end file */