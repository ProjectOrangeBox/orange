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

class Validate_is_natural_no_zero extends Validate_base {
	public function validate(&$field, $options) {
		$this->error_string = '%s must only contain digits and must be greater than zero.';

		return ($field != 0 && ctype_digit((string) $field));
	}
} /* end file */